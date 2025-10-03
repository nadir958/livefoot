<?php
// app/src/Command/DebugProviderMatchCommand.php
declare(strict_types=1);

namespace App\Command;

use App\Entity\Fixture;
use App\Entity\League;
use App\Service\FootballProvider;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:debug:provider-match', description: 'Dump provider payload for one fixture')]
final class DebugProviderMatchCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private FootballProvider $provider
    ) { parent::__construct(); }

    protected function configure(): void
    {
        $this
            ->addOption('fixture', null, InputOption::VALUE_REQUIRED, 'Fixture DB id')
            ->addOption('ext',     null, InputOption::VALUE_OPTIONAL, 'External match id (overrides DB value)')
            ->addOption('date',    null, InputOption::VALUE_OPTIONAL, 'YYYY-MM-DD override for fallback');
    }

    protected function execute(InputInterface $in, OutputInterface $out): int
    {
        $io = new SymfonyStyle($in, $out);
        $fxId = (int)$in->getOption('fixture');
        if ($fxId <= 0) { $io->error('--fixture=<id> required'); return Command::INVALID; }

        /** @var Fixture|null $fx */
        $fx = $this->em->find(Fixture::class, $fxId);
        if (!$fx) { $io->error('Fixture not found'); return Command::FAILURE; }

        $extId = $in->getOption('ext') ? (int)$in->getOption('ext') : (int)$fx->getExternalId();
        $date  = $in->getOption('date') ?: $fx->getDateUtc()->format('Y-m-d');

        $io->section(sprintf('Fixture #%d ext:%s date:%s', $fx->getId(), $extId ?: '—', $date));

        // Try several provider methods so we see which exist/return data
        $payload = null;
        $methods = [
            'getMatchByExternalId',
            'getFixtureById',
            'getEventById',
        ];
        foreach ($methods as $m) {
            if (!method_exists($this->provider, $m)) continue;
            try {
                $payload = $this->provider->{$m}($extId);
                if ($payload) { $io->writeln("✅ $m returned data"); break; }
                $io->writeln("— $m returned empty");
            } catch (\Throwable $e) {
                $io->writeln("✗ $m threw: ".$e->getMessage());
            }
        }

        if (!$payload) {
            // Fallback: league+season+date (±3 days)
            $league = $fx->getLeague();
            $leagueExt = $league instanceof League ? (int)$league->getExternalId() : 0;
            $season = $fx->getSeason() ?? (int)$fx->getDateUtc()->format('Y');

            $io->writeln(sprintf('Fallback via getMatchesByLeagueSeason leagueExt=%s season=%s date≈%s', $leagueExt, $season, $date));
            for ($d=-3; $d<=3; $d++) {
                $day = (new \DateTimeImmutable($date, new \DateTimeZone('UTC')))->modify(($d>=0?'+':'').$d.' day')->format('Y-m-d');
                try {
                    $list = $this->provider->getMatchesByLeagueSeason($leagueExt, $season, $day);
                    $io->writeln(sprintf('  • %s -> %d rows', $day, is_countable($list)?count($list):0));
                    if (is_array($list)) {
                        foreach ($list as $row) {
                            if ((int)($row['externalId'] ?? 0) === $extId) { $payload = $row; break 2; }
                        }
                    }
                } catch (\Throwable $e) {
                    $io->writeln("  • $day threw: ".$e->getMessage());
                }
            }
        }

        if (!$payload) { $io->warning('No payload found – likely wrong endpoint, leagueExt/season mapping, or provider expects a different id.'); return Command::SUCCESS; }

        // Print relevant keys
        $sample = [
            'status' => $payload['status'] ?? null,
            'dateUtc'=> $payload['dateUtc'] ?? null,
            'home'   => $payload['home'] ?? null,
            'away'   => $payload['away'] ?? null,
            'score'  => $payload['score'] ?? null,
            'goals'  => $payload['goals'] ?? null,
        ];
        $io->success('Payload snapshot:');
        $io->writeln(json_encode($sample, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
        return Command::SUCCESS;
    }
}
