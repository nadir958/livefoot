<?php
declare(strict_types=1);

namespace App\Command;

use App\Entity\Country;
use App\Entity\League;
use App\Service\FootballProvider;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\String\Slugger\AsciiSlugger;

#[AsCommand(name: 'app:import:leagues', description: 'Import or update leagues for a country (ISO2 code)')]
final class ImportLeaguesCommand extends Command
{
    public function __construct(
        private FootballProvider $fp,
        private EntityManagerInterface $em,
    ) { parent::__construct(); }

    protected function configure(): void
    {
        $this->addOption('country', null, InputOption::VALUE_REQUIRED, 'Country ISO2 code, e.g. FR');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io     = new SymfonyStyle($input, $output);
        $code   = strtoupper((string)$input->getOption('country'));
        if ($code === '') {
            $io->error('--country=ISO2 is required (e.g. --country=FR)');
            return Command::INVALID;
        }

        $slugger = new AsciiSlugger();

        $rows    = $this->fp->getLeaguesByCountry($code);
        $created = 0; $updated = 0; $i = 0;

        // Ensure Country exists
        $countryRepo = $this->em->getRepository(Country::class);
        $country = $countryRepo->findOneBy(['code' => $code]);
        if (!$country) {
            $country = new Country();
            $country->setCode($code)->setName($code)->setSlug($slugger->slug($code)->lower()->toString());
            $this->em->persist($country);
            $this->em->flush();
        }

        $repo = $this->em->getRepository(League::class);

        foreach ($rows as $r) {
            $extId  = (int)($r['externalId'] ?? 0);
            $name   = (string)($r['name'] ?? '');
            $type   = (string)($r['type'] ?? 'league');
            $logo   = $r['logo'] ?? null;
            $season = (int)($r['season'] ?? (int)date('Y'));

            if ($extId <= 0 || $name === '') { continue; }

            /** @var League|null $l */
            $l = $repo->findOneBy(['externalId' => $extId]) ?? new League();
            $isNew = !$l->getId();

            $l->setExternalId($extId);
            $l->setCountry($country);
            $l->setName($name);
            $l->setType($type);
            $l->setSeasonCurrent($season);
            $l->setLogo($logo);

            if (($l->getSlug() ?? '') === '') {
                $slug = $slugger->slug($name)->lower()->toString();
                if ($slug === '') { $slug = 'league-'.$extId; }
                $l->setSlug($slug);
            }

            $this->em->persist($l);
            $isNew ? $created++ : $updated++;

            if ((++$i % 200) === 0) { $this->em->flush(); $this->em->clear(); }
        }

        $this->em->flush();

        $io->success(sprintf('Leagues[%s]: +%d / ~%d', $code, $created, $updated));
        return Command::SUCCESS;
    }
}
