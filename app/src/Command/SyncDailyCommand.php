<?php
declare(strict_types=1);

namespace App\Command;

use App\Entity\League;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:sync:daily',
    description: 'Fetch today fixtures for stored leagues (optionally filtered) using app:import:matches.'
)]
final class SyncDailyCommand extends Command
{
    public function __construct(private EntityManagerInterface $em)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            // Filters
            ->addOption('country', null, InputOption::VALUE_OPTIONAL, 'Filter leagues by country ISO2 (e.g. FR)')
            ->addOption('leagues', null, InputOption::VALUE_OPTIONAL, 'Comma-separated external league IDs, e.g. "61,39"')
            // Date/season
            ->addOption('date', null, InputOption::VALUE_OPTIONAL, 'Date (UTC) YYYY-MM-DD; defaults to today', (new \DateTimeImmutable('today', new \DateTimeZone('UTC')))->format('Y-m-d'))
            ->addOption('season', null, InputOption::VALUE_OPTIONAL, 'Override season for all leagues (defaults to league.seasonCurrent)')
            // Execution behavior
            ->addOption('sleep', null, InputOption::VALUE_OPTIONAL, 'Sleep between leagues in seconds (float allowed)', '0.25')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Print what would run, do not execute');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io       = new SymfonyStyle($input, $output);
        $date     = (string)$input->getOption('date');
        $seasonOv = $input->getOption('season');
        $sleepSec = (float)$input->getOption('sleep');
        $dryRun   = (bool)$input->getOption('dry-run');

        // Parse leagues filter
        $leagueFilter = null;
        if ($lf = $input->getOption('leagues')) {
            $leagueFilter = array_values(array_filter(array_map('trim', explode(',', (string)$lf)), 'strlen'));
            $leagueFilter = array_map('intval', $leagueFilter);
            if (empty($leagueFilter)) { $leagueFilter = null; }
        }

        $country = $input->getOption('country');
        $country = $country ? strtoupper((string)$country) : null;

        // Build league list from DB
        $qb = $this->em->createQueryBuilder()
            ->select('l.id, l.externalId, l.seasonCurrent, l.name, c.code AS country')
            ->from(League::class, 'l')
            ->join('l.country', 'c')
            ->orderBy('l.name', 'ASC');

        if ($country) {
            $qb->andWhere('c.code = :cc')->setParameter('cc', $country);
        }
        if ($leagueFilter) {
            $qb->andWhere('l.externalId IN (:exts)')->setParameter('exts', $leagueFilter);
        }

        $leagues = $qb->getQuery()->getArrayResult();
        if (empty($leagues)) {
            $io->warning('No leagues matched your filters.');
            return Command::SUCCESS;
        }

        $io->title(sprintf('Daily sync for %s (UTC)', $date));
        $io->writeln(sprintf('Leagues selected: %d%s%s',
            \count($leagues),
            $country ? " | country={$country}" : '',
            $leagueFilter ? " | leagues=".implode(',', $leagueFilter) : ''
        ));
        if ($seasonOv) {
            $io->writeln("Season override: {$seasonOv}");
        }

        // We will attempt to run the other command in-process (fast).
        // If that fails (e.g., different runtime), we fallback to a shell execution.
        $kernel = $this->getApplication()?->getKernel();
        $app = $kernel ? new Application($kernel) : null;
        if ($app) {
            $app->setAutoExit(false);
        }

        $ok = 0; $fail = 0;
        foreach ($leagues as $l) {
            $ext    = (int)$l['externalId'];
            $season = $seasonOv ? (int)$seasonOv : (int)$l['seasonCurrent'];
            $name   = (string)$l['name'];
            $cc     = (string)$l['country'];

            $cmd = sprintf('app:import:matches --league=%d --season=%d --date=%s', $ext, $season, $date);
            $io->section(sprintf('[%s] %s', $cc, $cmd . "  # {$name}"));

            if ($dryRun) {
                $ok++;
            } else {
                $exitCode = $this->runImportMatches($app, $output, $ext, $season, $date);
                if ($exitCode === Command::SUCCESS) {
                    $ok++;
                } else {
                    $fail++;
                }
                if ($sleepSec > 0) {
                    usleep((int)round($sleepSec * 1_000_000));
                }
            }
        }

        $io->success(sprintf('Done. OK=%d, FAIL=%d', $ok, $fail));
        return $fail === 0 ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * Try to run app:import:matches in-process; if that fails, fallback to shell.
     */
    private function runImportMatches(?Application $app, OutputInterface $out, int $leagueExtId, int $season, string $date): int
    {
        // 1) In-process run (fast, no extra PHP process)
        if ($app) {
            $buffer = new BufferedOutput($out->getVerbosity(), true);
            $input  = new ArrayInput([
                'command'   => 'app:import:matches',
                '--league'  => (string)$leagueExtId,
                '--season'  => (string)$season,
                '--date'    => $date,
            ]);
            $code = $app->run($input, $buffer);
            // forward buffer to real output
            $out->writeln($buffer->fetch());
            if ($code === Command::SUCCESS) {
                return $code;
            }
            // else, fall through to shell fallback for robustness
        }

        // 2) Shell fallback (works even without Symfony Process)
        $cmd = sprintf('php bin/console app:import:matches --league=%d --season=%d --date=%s', $leagueExtId, $season, $date);
        // Show output live to the console:
        passthru($cmd, $code);
        return (int)$code;
    }
}
