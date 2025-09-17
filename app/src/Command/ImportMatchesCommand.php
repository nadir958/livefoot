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
            ->addOption('league', null, InputOption::VALUE_REQUIRED, 'External league ID')
            ->addOption('season', null, InputOption::VALUE_REQUIRED, 'Season year', (string)date('Y'))
            ->addOption('date',   null, InputOption::VALUE_OPTIONAL, 'Filter by date (YYYY-MM-DD, UTC)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io      = new SymfonyStyle($input, $output);
        $leagueE = (int)$input->getOption('league');
        $season  = (int)$input->getOption('season');
        $date    = $input->getOption('date') ? (string)$input->getOption('date') : null;

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
        $leagueId = $league->getId(); // keep DB id; we’ll use getReference() per iteration

        // Ensure a fallback country exists (for teams lacking country code)
        $fallbackCountry = $this->em->getRepository(Country::class)->findOneBy(['code' => 'XX']);
        if (!$fallbackCountry) {
            $fallbackCountry = new Country();
            $fallbackCountry
                ->setCode('XX')
                ->setName('Unknown')
                ->setSlug($slugger->slug('unknown')->lower()->toString());
            $this->em->persist($fallbackCountry);
            $this->em->flush(); // we want an ID for references if needed
        }
        $fallbackCountryId = $fallbackCountry->getId();

        $rows = $this->fp->getMatchesByLeagueSeason($leagueE, $season, $date);

        $fixtureRepo = $this->em->getRepository(Fixture::class);
        $teamRepo    = $this->em->getRepository(Team::class);

        $created = 0;
        $updated = 0;
        $i = 0;

        // Caches to reduce lookups and avoid duplicates inside a batch
        /** @var array<int,int> $teamIdCache extId => team id (from DB) */
        $teamIdCache = [];
        /** @var array<int,Team> $teamNewCache extId => Team (new, not yet flushed) */
        $teamNewCache = [];

        // Helper to get a managed League reference each iteration (safe after clear())
        $leagueRefFn = function() use ($leagueId): League {
            return $this->em->getReference(League::class, $leagueId);
        };

        // Helper to resolve a Team (managed in current EM context)
        $resolveTeam = function (array $side) use ($teamRepo, $slugger, $fallbackCountryId, &$teamIdCache, &$teamNewCache): ?Team {
            $extId = (int)($side['id'] ?? 0);
            $name  = (string)($side['name'] ?? '');
            $logo  = $side['logo'] ?? null;

            if ($extId <= 0 && $name === '') {
                return null; // nothing to link
            }

            // If we already created a new (unflushed) Team in this batch, reuse it
            if ($extId > 0 && isset($teamNewCache[$extId])) {
                return $teamNewCache[$extId];
            }

            // If we know the DB id, return a managed reference
            if ($extId > 0 && isset($teamIdCache[$extId])) {
                return $this->em->getReference(Team::class, $teamIdCache[$extId]);
            }

            // Try fetching from DB
            if ($extId > 0) {
                /** @var Team|null $found */
                $found = $teamRepo->findOneBy(['externalId' => $extId]);
                if ($found) {
                    // Cache id and return a fresh reference (cheap, safe across clear())
                    $teamIdCache[$extId] = (int)$found->getId();
                    return $this->em->getReference(Team::class, $teamIdCache[$extId]);
                }
            }

            // Create a minimal new Team (managed). We’ll assign a fallback country.
            $t = new Team();
            if ($extId > 0) {
                $t->setExternalId($extId);
            }
            $t->setName($name !== '' ? $name : 'Team '.($extId ?: 'N/A'));
            $t->setLogo($logo);
            $t->setSlug($slugger->slug($t->getName())->lower()->toString());

            // Use a reference to fallback country to avoid loading it
            $t->setCountry($this->em->getReference(Country::class, $fallbackCountryId));

            $this->em->persist($t);

            // If extId is known, keep this new (unflushed) instance in cache
            if ($extId > 0) {
                $teamNewCache[$extId] = $t;
            }

            return $t; // managed in current context
        };

        foreach ($rows as $r) {
            $extId = (int)($r['externalId'] ?? 0);
            if ($extId <= 0) {
                continue;
            }

            $dateIso = (string)($r['dateUtc'] ?? '');
            $statusS = (string)($r['status'] ?? 'scheduled');
            $round   = $r['round'] ?? null;
            $stage   = $r['stage'] ?? null;
            $venue   = $r['venue'] ?? null;

            $home   = $r['home'] ?? [];
            $away   = $r['away'] ?? [];

            /** @var Fixture|null $fx */
            $fx = $fixtureRepo->findOneBy(['externalId' => $extId]) ?? new Fixture();
            $isNew = !$fx->getId();

            // Always attach a fresh league reference (safe across clear())
            $fx->setLeague($leagueRefFn());
            $fx->setSeason($season);

            if ($round !== null && $fx->getRound() !== $round) { $fx->setRound($round); }
            if ($stage !== null && $fx->getStage() !== $stage) { $fx->setStage($stage); }
            if ($venue !== null && $fx->getVenue() !== $venue) { $fx->setVenue($venue); }

            if ($dateIso !== '') {
                $dt = new \DateTimeImmutable($dateIso);
                $fx->setDateUtc($dt->setTimezone(new \DateTimeZone('UTC')));
            }

            try {
                $fx->setStatus(MatchStatus::from($statusS));
            } catch (\Throwable) {
                $fx->setStatus(MatchStatus::SCHEDULED);
            }

            // Resolve teams (managed)
            $homeTeam = $resolveTeam($home);
            $awayTeam = $resolveTeam($away);
            if ($homeTeam) { $fx->setHomeTeam($homeTeam); }
            if ($awayTeam) { $fx->setAwayTeam($awayTeam); }

            // Scores (nullable)
            $fx->setHomeScore(isset($home['goals']) ? (int)$home['goals'] : null);
            $fx->setAwayScore(isset($away['goals']) ? (int)$away['goals'] : null);

            $fx->setExternalId($extId);
            $this->em->persist($fx);

            $isNew ? $created++ : $updated++;

            // Batch flush/clear to control memory
            if ((++$i % 100) === 0) {
                $this->em->flush();
                $this->em->clear();

                // After clear(), everything is detached. Reset new-entity cache.
                $teamNewCache = [];

                // Keep id-only caches; they still work with getReference()
                // (teamIdCache remains valid; leagueId is kept; fallbackCountryId kept)
            }
        }

        $this->em->flush();

        $io->success(sprintf(
            'Matches[league=%d, season=%d%s]: +%d / ~%d',
            $leagueE, $season, $date ? ", date=$date" : '', $created, $updated
        ));

        return Command::SUCCESS;
    }
}
