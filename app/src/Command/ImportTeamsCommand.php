<?php
declare(strict_types=1);

namespace App\Command;

use App\Entity\Country;
use App\Entity\League;
use App\Entity\Team;
use App\Service\FootballProvider;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\String\Slugger\AsciiSlugger;

#[AsCommand(name: 'app:import:teams', description: 'Import or update teams for a league and season')]
final class ImportTeamsCommand extends Command
{
    public function __construct(
        private FootballProvider $fp,
        private EntityManagerInterface $em,
    ) { parent::__construct(); }

    protected function configure(): void
    {
        $this
            ->addOption('league', null, InputOption::VALUE_REQUIRED, 'External league ID (API)')
            ->addOption('season', null, InputOption::VALUE_REQUIRED, 'Season year', (string)date('Y'));
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io      = new SymfonyStyle($input, $output);
        $leagueE = (int)$input->getOption('league');
        $season  = (int)$input->getOption('season');
        if ($leagueE <= 0) {
            $io->error('--league=<externalId> is required');
            return Command::INVALID;
        }

        $slugger = new AsciiSlugger();

        // Ensure the league exists (upsert minimal if missing)
        $leagueRepo = $this->em->getRepository(League::class);
        $league = $leagueRepo->findOneBy(['externalId' => $leagueE]);
        if (!$league) {
            $league = new League();
            // create a minimal country if absolutely nothing exists
            $country = $this->em->getRepository(Country::class)->findOneBy([]);
            if (!$country) {
                $country = new Country();
                $country->setCode('XX')->setName('Unknown')->setSlug($slugger->slug('unknown')->lower()->toString());
                $this->em->persist($country);
                $this->em->flush();
            }

            $league->setExternalId($leagueE)->setCountry($country)->setName('League '.$leagueE)
                   ->setType('league')->setSeasonCurrent($season)->setSlug('league-'.$leagueE);
            $this->em->persist($league); $this->em->flush();
        }

        $rows    = $this->fp->getTeamsByLeagueSeason($leagueE, $season);
        $repo    = $this->em->getRepository(Team::class);
        $created = 0; $updated = 0; $i = 0;

        foreach ($rows as $r) {
            $extId  = (int)($r['externalId'] ?? 0);
            $name   = (string)($r['name'] ?? '');
            $short  = $r['shortName'] ?? null;
            $logo   = $r['logo'] ?? null;

            // ✅ sanitize team country to ISO2 (EXACTLY 2 chars)
            $rawCc  = (string)($r['countryCode'] ?? '');
            $cc     = $this->iso2($rawCc, $name, $slugger);
            if (strlen($cc) !== 2) { $cc = 'XX'; }   // double safety

            if ($extId <= 0 || $name === '') { continue; }

            /** @var Team|null $t */
            $t = $repo->findOneBy(['externalId' => $extId]) ?? new Team();
            $isNew = !$t->getId();

            // link country (upsert by sanitized ISO2)
            $country = $this->em->getRepository(Country::class)->findOneBy(['code' => $cc]);
            if (!$country) {
                $country = new Country();
                $country->setCode($cc)
                        ->setName($cc)
                        ->setSlug($slugger->slug($cc)->lower()->toString());
                $this->em->persist($country);
                $this->em->flush();
            }

            $t->setExternalId($extId);
            $t->setCountry($country);
            $t->setName($name);
            $t->setShortName($short);
            $t->setLogo($logo);

            if (($t->getSlug() ?? '') === '') {
                $slug = $slugger->slug($name)->lower()->toString();
                if ($slug === '') { $slug = 'team-'.$extId; }
                $t->setSlug($slug);
            }

            $this->em->persist($t);
            $isNew ? $created++ : $updated++;

            if ((++$i % 200) === 0) { $this->em->flush(); $this->em->clear(); }
        }

        $this->em->flush();

        $io->success(sprintf('Teams[league=%d, season=%d]: +%d / ~%d', $leagueE, $season, $created, $updated));
        return Command::SUCCESS;
    }

    /**
     * Normalize provider "countryCode" into ISO2 (2 letters) or fallback.
     * Ensures ALWAYS exactly 2 ASCII letters.
     */
    private function iso2(string $rawCode, string $name, AsciiSlugger $slugger): string
    {
        // 1) Normalize common cases/format
        $code = strtoupper(trim($rawCode));

        // Subdivisions like GB-ENG, BR-SP → take first 2 letters
        if (preg_match('/^[A-Z]{2}\-[A-Z\-]+$/', $code)) {
            $code = substr($code, 0, 2);
        }

        // Kosovo special
        if ($code === 'XKX' || $code === 'XK') { $code = 'XK'; }

        // 2) Keep ONLY A–Z, then cut to 2
        $code = preg_replace('/[^A-Z]/', '', $code) ?? '';
        if (strlen($code) >= 2) {
            $code = substr($code, 0, 2);
        }

        // 3) If still not exactly 2, derive from name or fallback
        if (strlen($code) !== 2) {
            $base = $slugger->slug($name)->lower()->toString();     // "paris-saint-germain"
            $base = preg_replace('~[^a-z]~', '', $base) ?? '';       // "parissaintgermain"
            $two  = strtoupper(substr($base, 0, 2));                 // "PA"
            $code = $two !== '' ? $two : 'XX';
        }
        return $code; // ALWAYS 2 ASCII letters
    }
}
