<?php
declare(strict_types=1);

namespace App\Command;

use App\Service\FootballProvider;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:test:provider',
    description: 'Quickly test FootballProvider endpoints (countries, leagues, teams, matches).'
)]
final class TestProviderCommand extends Command
{
    public function __construct(private FootballProvider $fp)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('country', null, InputOption::VALUE_REQUIRED, 'Country code (ISO2), e.g. FR', 'FR')
            ->addOption('league',  null, InputOption::VALUE_OPTIONAL, 'External league ID (API)')
            ->addOption('season',  null, InputOption::VALUE_REQUIRED, 'Season (year)', (string)date('Y'))
            ->addOption('date',    null, InputOption::VALUE_OPTIONAL, 'Filter matches by date (YYYY-MM-DD, UTC)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io      = new SymfonyStyle($input, $output);
        $country = strtoupper((string)$input->getOption('country'));
        $season  = (int)$input->getOption('season');
        $date    = $input->getOption('date') ? (string)$input->getOption('date') : null;

        $io->title('FootballProvider smoke test');

        try {
            // 1) Countries
            $io->section('1) Countries');
            $countries = $this->fp->getCountries();
            $io->writeln(sprintf('Total countries: <info>%d</info>', \count($countries)));
            $this->dumpSample($io, $countries, 5, 'countries');

            // 2) Leagues by country
            $io->section(sprintf('2) Leagues for country %s', $country));
            $leagues = $this->fp->getLeaguesByCountry($country);
            $io->writeln(sprintf('Total leagues: <info>%d</info>', \count($leagues)));
            $this->dumpSample($io, $leagues, 5, 'leagues');

            // Choose league
            $leagueId = $input->getOption('league');
            if ($leagueId === null) {
                if (empty($leagues)) {
                    $io->warning('No leagues returned. Check API base URL, key, and country code.');
                    return Command::SUCCESS;
                }
                // pick the first league that has an externalId
                $leagueId = (int)($leagues[0]['externalId'] ?? 0);
                if (!$leagueId) {
                    $io->warning('First league has no externalId; try passing --league explicitly.');
                    return Command::SUCCESS;
                }
                $io->writeln(sprintf('Using league externalId: <comment>%d</comment> (%s)', $leagueId, $leagues[0]['name'] ?? 'unknown'));
            } else {
                $leagueId = (int)$leagueId;
                $io->writeln(sprintf('Using provided league externalId: <comment>%d</comment>', $leagueId));
            }

            // 3) Teams
            $io->section(sprintf('3) Teams for league=%d, season=%d', $leagueId, $season));
            $teams = $this->fp->getTeamsByLeagueSeason($leagueId, $season);
            $io->writeln(sprintf('Total teams: <info>%d</info>', \count($teams)));
            $this->dumpSample($io, $teams, 5, 'teams');

            // 4) Matches
            $io->section(sprintf('4) Matches for league=%d, season=%d%s',
                $leagueId, $season, $date ? ", date={$date}" : ''));
            $matches = $this->fp->getMatchesByLeagueSeason($leagueId, $season, $date);
            $io->writeln(sprintf('Total matches: <info>%d</info>', \count($matches)));
            $this->dumpSample($io, $matches, 5, 'matches');

            $io->success('Provider test completed.');
            return Command::SUCCESS;

        } catch (\Throwable $e) {
            $io->error($e::class . ': ' . $e->getMessage());
            if ($io->isVerbose()) {
                $io->writeln($e->getTraceAsString());
            }
            return Command::FAILURE;
        }
    }

    /**
     * Pretty-print a small sample of rows to the console.
     *
     * @param SymfonyStyle $io
     * @param array<int,mixed> $rows
     * @param int $limit
     * @param string $label
     */
    private function dumpSample(SymfonyStyle $io, array $rows, int $limit, string $label): void
    {
        if (empty($rows)) {
            $io->writeln(sprintf('<comment>No %s to show.</comment>', $label));
            return;
        }

        $sample = \array_slice($rows, 0, $limit);
        $json = json_encode($sample, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $io->writeln(sprintf("<info>Sample %s (first %d):</info>\n%s", $label, \count($sample), $json));
    }
}
