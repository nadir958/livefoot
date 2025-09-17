<?php
declare(strict_types=1);

namespace App\Command;

use App\Entity\Country;
use App\Service\FootballProvider;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\String\Slugger\AsciiSlugger;

#[AsCommand(name: 'app:import:countries', description: 'Import or update countries from the provider')]
final class ImportCountriesCommand extends Command
{
    public function __construct(
        private FootballProvider $fp,
        private EntityManagerInterface $em,
    ) { parent::__construct(); }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io      = new SymfonyStyle($input, $output);
        $slugger = new AsciiSlugger();

        $rows    = $this->fp->getCountries();
        $created = 0; $updated = 0; $i = 0;

        foreach ($rows as $row) {
            $rawCode = (string)($row['code'] ?? '');
            $name    = (string)($row['name'] ?? '');
            $flag    = $row['flag'] ?? null;

            // --- sanitize to ISO2 (2 letters) ---
            $code = $this->iso2($rawCode, $name, $slugger);

            if ($code === '' && $name === '') {
                // nothing usable—skip
                continue;
            }

            /** @var Country|null $c */
            $repo = $this->em->getRepository(Country::class);
            $c = $code !== ''
                ? $repo->findOneBy(['code' => $code])
                : $repo->findOneBy(['name' => $name]);

            $isNew = false;
            if (!$c) {
                $c = new Country();
                $isNew = true;
            }

            $c->setCode($code !== '' ? $code : 'XX');
            $c->setName($name !== '' ? $name : $code);
            $c->setFlag($flag);

            if (($c->getSlug() ?? '') === '') {
                $slug = $slugger->slug($c->getName())->lower()->toString();
                $c->setSlug($slug !== '' ? $slug : 'country');
            }

            $this->em->persist($c);
            $isNew ? $created++ : $updated++;

            if ((++$i % 200) === 0) { $this->em->flush(); $this->em->clear(); }
        }

        $this->em->flush();

        $io->success(sprintf('Countries: +%d / ~%d', $created, $updated));
        return Command::SUCCESS;
    }

    /**
     * Normalize provider "code" into ISO2 (2 letters) or fallback.
     */
    private function iso2(string $rawCode, string $name, AsciiSlugger $slugger): string
    {
        $code = strtoupper(trim($rawCode));

        // Common oddities → map to 2-letter
        if ($code === 'XKX' || $code === 'XK') { return 'XK'; } // Kosovo
        if ($code === 'UK') { return 'GB'; }                     // normalize UK → GB
        // Subdivision codes like GB-ENG → take first 2 letters
        if (preg_match('/^[A-Z]{2}\-[A-Z]{2,}$/', $code)) {
            $code = substr($code, 0, 2);
        }

        // Accept only 2 letters A–Z
        if (preg_match('/^[A-Z]{2}$/', $code)) {
            return $code;
        }

        // Fallback: derive from name (2 chars) or 'XX'
        $base = $slugger->slug($name)->lower()->toString(); // "cote-d-ivoire" → "cote-d-ivoire"
        $base = preg_replace('~[^a-z]~', '', $base) ?? '';  // keep letters only → "cotedivoire"
        $two  = strtoupper(substr($base, 0, 2));

        return $two !== '' ? $two : 'XX';
    }
}
