<?php
declare(strict_types=1);

namespace App\Command;

use App\Entity\Fixture;
use App\Entity\League;
use App\Enum\MatchStatus;
use App\Service\FootballProvider;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:repair:match-scores',
    description: 'Re-check provider API and fix scores/status for already-imported fixtures (robust fallback).'
)]
final class RepairMatchScoresFromApiCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private FootballProvider $provider // <- ADAPT if your service alias differs
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('days',         null, InputOption::VALUE_REQUIRED, 'How many days back (inclusive).', '180')
            ->addOption('league',       null, InputOption::VALUE_REQUIRED, 'Limit to a league (INTERNAL DB id).')
            ->addOption('only-missing', null, InputOption::VALUE_NONE,     'Only rows with NULL/0–0.')
            ->addOption('include-live', null, InputOption::VALUE_NONE,     'Also write live scores.')
            ->addOption('limit',        null, InputOption::VALUE_REQUIRED, 'Max rows (0=all).', '0')
            ->addOption('dry-run',      null, InputOption::VALUE_NONE,     'No writes, just report.')
            ->addOption('verbosen',      'vn',  InputOption::VALUE_NONE,     'Verbose per-row logs.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io          = new SymfonyStyle($input, $output);
        $days        = max(0, (int)$input->getOption('days'));
        $leagueId    = $input->getOption('league') !== null ? (int)$input->getOption('league') : null;
        $onlyMissing = (bool)$input->getOption('only-missing');
        $includeLive = (bool)$input->getOption('include-live');
        $limit       = max(0, (int)$input->getOption('limit'));
        $dryRun      = (bool)$input->getOption('dry-run');
        $verbose     = (bool)$input->getOption('verbosen');

        $todayUtc = new \DateTimeImmutable('today', new \DateTimeZone('UTC'));
        $start    = $todayUtc->modify(sprintf('-%d days', $days));
        $end      = $todayUtc->modify('+1 day');

        $qb = $this->em->createQueryBuilder()
            ->select('f, l')
            ->from(Fixture::class, 'f')
            ->leftJoin('f.league', 'l')
            ->where('f.dateUtc >= :start AND f.dateUtc < :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('f.dateUtc', 'ASC');

        if ($leagueId) {
            $qb->andWhere('IDENTITY(f.league) = :lid')->setParameter('lid', $leagueId);
        }
        if ($onlyMissing) {
            $qb->andWhere(' ( (f.homeScore IS NULL OR f.awayScore IS NULL) OR (f.homeScore = 0 AND f.awayScore = 0) ) ');
        }
        if ($limit > 0) $qb->setMaxResults($limit);

        /** @var Fixture[] $rows */
        $rows = $qb->getQuery()->getResult();

        if (!$rows) {
            $io->note('No matches found for the given window/filters.');
            return Command::SUCCESS;
        }

        $io->writeln(sprintf(
            'Scanning %d rows (days=%d, league=%s, only-missing=%s, include-live=%s, dry-run=%s)',
            \count($rows),
            $days,
            $leagueId !== null ? (string)$leagueId : '—',
            $onlyMissing ? 'yes' : 'no',
            $includeLive ? 'yes' : 'no',
            $dryRun ? 'yes' : 'no'
        ));

        $cNoExtId=0; $cNoPayload=0; $cNoScores=0; $cNotAllowed=0; $cNoChange=0; $cUpdated=0;
        $processed = 0;

        foreach ($rows as $m) {
            $processed++;
            $id    = $m->getId();
            $extId = $m->getExternalId();

            if (!$extId) { $cNoExtId++; if ($verbose) $io->writeln("[$id] SKIP no externalId"); continue; }

            // ---- 1) Direct lookup by external ID
            $payload = $this->fetchByExternalId((int)$extId);
            if (!$payload) {
                // ---- 2) Fallback by league + season + date, and also ±1 day (tz drift)
                $payload = $this->fallbackByLeagueSeasonAndDate($m, $verbose ? $io : null);
            }

            if (!$payload) {
                $cNoPayload++;
                if ($verbose) $io->writeln("[$id:$extId] SKIP no payload from provider");
                continue;
            }

            // Normalize status
            $normalized = $this->normalizeStatus((string)($payload['status'] ?? ''));

            // Extract scores (supports multiple shapes)
            [$pHome, $pAway] = $this->extractScores($payload);

            // Are we allowed to write scores?
            $canWrite = $normalized === MatchStatus::FINISHED
                     || ($includeLive && $normalized === MatchStatus::LIVE);

            if (!$canWrite) {
                $cNotAllowed++;
                if ($verbose) $io->writeln("[$id:$extId] SKIP status={$normalized->value} (include-live=".($includeLive?'y':'n').")");
                // still update status if changed
                if ($this->statusChanged($m, $normalized)) {
                    if (!$dryRun) $m->setStatus($normalized);
                    $cUpdated++;
                    if ($verbose) $io->writeln("[$id:$extId] STATUS -> {$normalized->value}");
                }
                continue;
            }

            if ($normalized === MatchStatus::FINISHED && ($pHome === null || $pAway === null)) {
                $cNoScores++;
                if ($verbose) $io->writeln("[$id:$extId] SKIP: finished but missing FT scores in payload");
                // still update status if needed
                if ($this->statusChanged($m, $normalized)) {
                    if (!$dryRun) $m->setStatus($normalized);
                    $cUpdated++;
                    if ($verbose) $io->writeln("[$id:$extId] STATUS -> {$normalized->value}");
                }
                continue;
            }

            $newHome = $pHome;
            $newAway = $pAway;

            $didSomething = false;

            if ($this->statusChanged($m, $normalized)) {
                if (!$dryRun) $m->setStatus($normalized);
                $didSomething = true;
            }

            if ($this->scoresChanged($m, $newHome, $newAway)) {
                if (!$dryRun) {
                    if ($newHome !== null) $m->setHomeScore($newHome);
                    if ($newAway !== null) $m->setAwayScore($newAway);
                }
                $didSomething = true;
            }

            if ($didSomething) {
                $cUpdated++;
                if ($verbose) {
                    $io->writeln(sprintf(
                        "[%d:%s] UPDATE %s | %s→%s  %s→%s",
                        $id,
                        (string)$extId,
                        $normalized->value,
                        var_export($m->getHomeScore(), true),
                        var_export($newHome, true),
                        var_export($m->getAwayScore(), true),
                        var_export($newAway, true)
                    ));
                }
            } else {
                $cNoChange++;
                if ($verbose) $io->writeln("[$id:$extId] no change");
            }

            if (!$dryRun && ($processed % 200) === 0) {
                $this->em->flush();
                $this->em->clear();
            }
        }

        if (!$dryRun) $this->em->flush();

        $io->section('Summary');
        $io->writeln("Processed:    $processed");
        $io->writeln("Updated:      $cUpdated");
        $io->writeln("No change:    $cNoChange");
        $io->writeln("No extId:     $cNoExtId");
        $io->writeln("No payload:   $cNoPayload");
        $io->writeln("No FT scores: $cNoScores");
        $io->writeln("Not allowed:  $cNotAllowed");

        return Command::SUCCESS;
    }

    // ---------- Provider calls & matching ----------

    // Try multiple provider methods by id
    private function fetchByExternalId(int $extId): ?array
    {
        foreach (['getMatchByExternalId','getFixtureById','getEventById'] as $m) {
            if (!method_exists($this->provider, $m)) continue;
            try { $p = $this->provider->{$m}($extId); if ($p) return $p; } catch (\Throwable) {}
        }
        return null;
    }

    // Wider fallback: league+season(+span) over ±7 days; then try by-date if available
    private function fallbackByLeagueSeasonAndDate(Fixture $m, ?\Symfony\Component\Console\Style\SymfonyStyle $io = null): ?array
    {
        $league = $m->getLeague();
        $leagueExt = $league?->getExternalId() ? (int)$league->getExternalId() : 0;
        if ($leagueExt === 0) return null;

        $baseSeason = $m->getSeason() ?? (int)$m->getDateUtc()->format('Y');
        $seasonCandidates = [$baseSeason, sprintf('%d-%d', $baseSeason, $baseSeason+1)];

        $date = $m->getDateUtc()->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d');

        // Build ±7d window
        $dates = [];
        $base = \DateTimeImmutable::createFromFormat('Y-m-d', $date, new \DateTimeZone('UTC'));
        for ($d=-7; $d<=7; $d++) {
            $dates[] = $base->modify(($d>=0?'+':'').$d.' day')->format('Y-m-d');
        }

        $extId = (int)$m->getExternalId();

        // 1) league+season+date
        foreach ($seasonCandidates as $seasonParam) {
            foreach ($dates as $day) {
                try { $list = $this->provider->getMatchesByLeagueSeason($leagueExt, $seasonParam, $day); }
                catch (\Throwable) { $list = null; }
                if (!\is_array($list) || !$list) continue;

                foreach ($list as $row) {
                    if ((int)($row['externalId'] ?? 0) === $extId) return $row;
                }
            }
        }

        // 2) by-date (no league), if your provider supports it
        foreach (['getMatchesByDate','getFixturesByDate','getEventsByDate'] as $method) {
            if (!method_exists($this->provider, $method)) continue;
            foreach ($dates as $day) {
                try { $list = $this->provider->{$method}($day); } catch (\Throwable) { $list = null; }
                if (!\is_array($list) || !$list) continue;
                foreach ($list as $row) {
                    if ((int)($row['externalId'] ?? 0) === $extId) return $row;
                }
            }
        }

        if ($io) $io->writeln(sprintf(
            "[%d:%s] fallback failed (leagueExt=%s season≈%s date≈%s)",
            $m->getId(), (string)$extId, (string)$leagueExt, (string)$baseSeason, $date
        ));
        return null;
    }

    private function fetchLeagueSeasonDate(int $leagueExtId, int $season, string $dateYmd): ?array
    {
        try {
            // ADAPT to your provider method signature:
            return $this->provider->getMatchesByLeagueSeason($leagueExtId, $season, $dateYmd);
        } catch (\Throwable) {
            return null;
        }
    }

    private function shiftDay(string $ymd, int $delta): string
    {
        $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $ymd, new \DateTimeZone('UTC'));
        return $dt->modify(sprintf('%+d day', $delta))->format('Y-m-d');
    }

    /**
     * Pick a match from list based on home/away external IDs and nearest kickoff to target.
     */
    private function findByTeamsAndKickoff(array $list, int $homeExt, int $awayExt, \DateTimeImmutable $targetUtc): ?array
    {
        $best = null;
        $bestDiff = null;

        foreach ($list as $row) {
            $rh = (int)($row['home']['id'] ?? 0);
            $ra = (int)($row['away']['id'] ?? 0);
            if ($rh !== $homeExt || $ra !== $awayExt) continue;

            $iso = (string)($row['dateUtc'] ?? '');
            if ($iso === '') continue;

            try { $kick = new \DateTimeImmutable($iso, new \DateTimeZone('UTC')); }
            catch (\Throwable) { continue; }

            $diff = abs($kick->getTimestamp() - $targetUtc->getTimestamp());
            if ($best === null || $diff < $bestDiff) {
                $best = $row; $bestDiff = $diff;
            }
        }
        return $best;
    }

    // ---------- Mapping helpers ----------

    private function normalizeStatus(string $raw): MatchStatus
    {
        $s = strtolower(trim($raw));

        if ($s === 'finished' || $s === 'match_finished' || $s === 'ft' || str_starts_with($s, 'ft')) {
            return MatchStatus::FINISHED;
        }
        if (\in_array($s, ['live','inplay','1h','2h','ht','et','pens'], true)) {
            return MatchStatus::LIVE;
        }
        if (\in_array($s, ['scheduled','schedule','sched','shudled','not_started','ns'], true)) {
            return MatchStatus::SCHEDULED;
        }
        return MatchStatus::SCHEDULED;
    }

    /**
     * Try many common score shapes; return [home|null, away|null] as ints.
     */
    private function extractScores(array $p): array
    {
        $candidates = [
            fn($p) => [$p['score']['fulltime']['home'] ?? null, $p['score']['fulltime']['away'] ?? null],
            fn($p) => [$p['goals']['home'] ?? null, $p['goals']['away'] ?? null],
            fn($p) => [$p['home_score'] ?? null, $p['away_score'] ?? null],
            fn($p) => [$p['home']['goals'] ?? null, $p['away']['goals'] ?? null],
            fn($p) => [$p['full_time']['home'] ?? null, $p['full_time']['away'] ?? null],
        ];
        foreach ($candidates as $fn) {
            [$h, $a] = $fn($p);
            if ($h !== null || $a !== null) {
                return [$h !== null ? (int)$h : null, $a !== null ? (int)$a : null];
            }
        }
        return [null, null];
    }

    private function statusChanged(Fixture $m, MatchStatus $new): bool
    {
        $old = $m->getStatus();
        if ($old instanceof MatchStatus) return $old !== $new;
        return strtolower((string)$old) !== $new->value;
    }

    private function scoresChanged(Fixture $m, ?int $newHome, ?int $newAway): bool
    {
        $changed = false;
        if ($newHome !== null && $newHome !== $m->getHomeScore()) $changed = true;
        if ($newAway !== null && $newAway !== $m->getAwayScore()) $changed = true;
        return $changed;
    }
}
