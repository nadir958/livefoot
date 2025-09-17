<?php
namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:import:fixtures',
    description: 'Import football fixtures from API'
)]
class ImportFixturesCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addArgument('league', InputArgument::REQUIRED, 'League ID')
            ->addArgument('season', InputArgument::REQUIRED, 'Season year');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $league = $input->getArgument('league');
        $season = $input->getArgument('season');

        // TODO: call API-Football here & save to DB
        $output->writeln("Importing fixtures for league {$league}, season {$season}...");
        return Command::SUCCESS;
    }
}
