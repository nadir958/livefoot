<?php
declare(strict_types=1);

namespace App\Command;

use App\Entity\Country;
use App\Entity\Fixture;
use App\Entity\League;
use App\Entity\Team;
use App\Enum\MatchStatus;
use App\Service\FootballProvider;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\String\Slugger\AsciiSlugger;

#[AsCommand(name: 'app:import:matches', description: 'Import or update matches (fixtures) for a league/season[/date]')]
final class ImportMatchesCommand extends Command
{
    public function __construct(
        private FootballProvider $fp,
        private EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('league', null, InputOption::VALUE_REQUIRED, 'External league ID (API-Football league id)')
            ->addOption('season', null, InputOption::VALUE_REQUIRED, 'Season year', (string)date('Y'))
            ->addOption('date',   null, InputOption::VALUE_OPTIONAL, 'Filter by date (YYYY-MM-DD, UTC)')
            ->addOption('include-live', null, InputOption::VALUE_NONE, 'Also persist live running scores (default: only FT)')
            ->addOption('patch-finished', null, InputOption::VALUE_NONE, 'For FINISHED rows missing goals, refetch by fixture id to fill scores');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io      = new SymfonyStyle($input, $output);
        $leagueE = (int)$input->getOption('league');
        $season  = (int)$input->getOption('season');
        $date    = $input->getOption('date') ? (string)$input->getOption('date') : null;
        $includeLive   = (bool)$input->getOption('include-live');
        $patchFinished = (bool)$input->getOption('patch-finished');

        if ($leagueE <= 0) {
            $io->error('--league=<externalId> is required');
            return Command::INVALID;
        }

        $slugger = new AsciiSlugger();

        // Resolve target league (by externalId)
        $league = $this->em->getRepository(League::class)->findOneBy(['externalId' => $leagueE]);
        if (!$league) {
            $io->warning('League not found locally. Run app:import:leagues first (or create the league).');
            return Command::FAILURE;
        }
        $leagueId = (int)$league->getId();

        // Ensure a fallback country exists (for teams lacking country code)
        $fallbackCountry = $this->em->getRepository(Country::class)->findOneBy(['code' => 'XX']);
        if (!$fallbackCountry) {
            $fallbackCountry = new Country();
            $fallbackCountry
                ->setCode('XX')
                ->setName('Unknown')
                ->setSlug($slugger->slug('unknown')->lower()->toString());
            $this->em->persist($fallbackCountry);
            $this->em->flush();
        }
        $fallbackCountryId = (int)$fallbackCountry->getId();

        // Fetch provider rows (already mapped by FootballProvider)
        $rows = $this->fp->getMatchesByLeagueSeason($leagueE, $season, $date);

        $fixtureRepo = $this->em->getRepository(Fixture::class);
        $teamRepo    = $this->em->getRepository(Team::class);

        $created = 0;
        $updated = 0;
        $patched = 0;
        $i = 0;

        // caches
        $teamIdCache = [];   // extId => team id
        $teamNewCache = [];  // extId => Team (unflushed)

        $leagueRefFn = function() use ($leagueId): League {
            return $this->em->getReference(League::class, $leagueId);
        };

        $resolveTeam = function (array $side) use ($teamRepo, $slugger, $fallbackCountryId, &$teamIdCache, &$teamNewCache): ?Team {
            $extId = (int)($side['id'] ?? 0);
            $name  = (string)($side['name'] ?? '');
            $logo  = $side['logo'] ?? null;

            if ($extId <= 0 && $name === '') return null;

            if ($extId > 0 && isset($teamNewCache[$extId])) {
                return $teamNewCache[$extId];
            }
            if ($extId > 0 && isset($teamIdCache[$extId])) {
                return $this->em->getReference(Team::class, $teamIdCache[$extId]);
            }

            if ($extId > 0) {
                /** @var Team|null $found */
                $found = $teamRepo->findOneBy(['externalId' => $extId]);
                if ($found) {
                    $teamIdCache[$extId] = (int)$found->getId();
                    return $this->em->getReference(Team::class, $teamIdCache[$extId]);
                }
            }

            $t = new Team();
            if ($extId > 0) $t->setExternalId($extId);
            $t->setName($name !== '' ? $name : 'Team '.($extId ?: 'N/A'));
            $t->setLogo($logo);
            $t->setSlug($slugger->slug($t->getName())->lower()->toString());
            $t->setCountry($this->em->getReference(Country::class, $fallbackCountryId));

            $this->em->persist($t);

            if ($extId > 0) $teamNewCache[$extId] = $t;

            return $t;
        };

        foreach ($rows as $r) {
            $extId = (int)($r['externalId'] ?? 0);
            if ($extId <= 0) continue;

            $dateIso = (string)($r['dateUtc'] ?? '');
            $statusS = (string)($r['status'] ?? 'scheduled');
            $round   = $r['round'] ?? null;
            $stage   = $r['stage'] ?? null;
            $venue   = $r['venue'] ?? null;
            $home    = $r['home'] ?? [];
            $away    = $r['away'] ?? [];

            /** @var Fixture|null $fx */
            $fx = $fixtureRepo->findOneBy(['externalId' => $extId]) ?? new Fixture();
            $isNew = !$fx->getId();

            // Basic fields
            $fx->setLeague($leagueRefFn());
            $fx->setSeason($season);

            if ($round !== null && $fx->getRound() !== $round) { $fx->setRound($round); }
            if ($stage !== null && $fx->getStage() !== $stage) { $fx->setStage($stage); }
            if ($venue !== null && $fx->getVenue() !== $venue) { $fx->setVenue($venue); }

            if ($dateIso !== '') {
                try {
                    $dtUtc = (new \DateTimeImmutable($dateIso))->setTimezone(new \DateTimeZone('UTC'));
                    $fx->setDateUtc($dtUtc);
                } catch (\Throwable) {
                    // ignore bad date
                }
            }

            // Provider already normalized status names; map to enum safely
            $status = $this->toEnumStatus($statusS);
            $fx->setStatus($status);

            // Teams
            $homeTeam = $resolveTeam($home);
            $awayTeam = $resolveTeam($away);
            if ($homeTeam) $fx->setHomeTeam($homeTeam);
            if ($awayTeam) $fx->setAwayTeam($awayTeam);

            // Scores policy
            $homeGoals = array_key_exists('goals', $home) ? $home['goals'] : null; // may be 0
            $awayGoals = array_key_exists('goals', $away) ? $away['goals'] : null;

            $shouldWriteScores =
                $status === MatchStatus::FINISHED ||
                ($includeLive && $status === MatchStatus::LIVE);

            if ($shouldWriteScores) {
                // If the list endpoint has null goals for FINISHED and we were asked to patch, try /fixtures?id=...
                if ($status === MatchStatus::FINISHED && ($homeGoals === null || $awayGoals === null) && $patchFinished) {
                    try {
                        $one = $this->fp->getMatchByExternalId($extId);
                        if (is_array($one)) {
                            $homeGoals = $one['home']['goals'] ?? $homeGoals;
                            $awayGoals = $one['away']['goals'] ?? $awayGoals;
                            $patched++;
                        }
                    } catch (\Throwable) {
                        // ignore
                    }
                }

                // Important: 0 is a valid FT score; **only** null means "unknown"
                $fx->setHomeScore($homeGoals !== null ? (int)$homeGoals : null);
                $fx->setAwayScore($awayGoals !== null ? (int)$awayGoals : null);
            } else {
                // Not started (or we chose not to persist live): keep NULL to avoid fake 0–0
                $fx->setHomeScore(null);
                $fx->setAwayScore(null);
            }

            $fx->setExternalId($extId);
            $this->em->persist($fx);

            $isNew ? $created++ : $updated++;

            if ((++$i % 150) === 0) {
                $this->em->flush();
                $this->em->clear();
                $teamNewCache = [];
            }
        }

        $this->em->flush();

        $io->success(sprintf(
            'Matches[league=%d, season=%d%s]: +%d / ~%d%s',
            $leagueE, $season, $date ? ", date=$date" : '', $created, $updated,
            $patchFinished ? " (patched=$patched)" : ""
        ));

        return Command::SUCCESS;
    }

    private function toEnumStatus(string $s): MatchStatus
    {
        $s = strtolower(trim($s));
        return match ($s) {
            'finished'  => MatchStatus::FINISHED,
            'live'      => MatchStatus::LIVE,
            default     => MatchStatus::SCHEDULED,
        };
    }
}
