<?php
declare(strict_types=1);

namespace App\Command;

use App\Entity\Country;
use App\Entity\League;
use App\Entity\Team;
use App\Util\UniqueSlugger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:slugify')]
final class SlugifyCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private UniqueSlugger $uniqueSlugger
    ){ parent::__construct(); }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $total = 0;
        $total += $this->process(Country::class, 'name', 'slug', 96, $output);
        $total += $this->process(League::class,  'name', 'slug', 128, $output);
        $total += $this->process(Team::class,    'name', 'slug', 128, $output);

        $output->writeln("<info>Done. {$total} items updated.</info>");
        return Command::SUCCESS;
    }

    /**
     * @param class-string $entityClass
     */
    private function process(string $entityClass, string $sourceField, string $slugField, int $maxLen, OutputInterface $out): int
    {
        $repo = $this->em->getRepository($entityClass);
        $items = $repo->createQueryBuilder('e')->getQuery()->getResult();

        $count = 0;
        foreach ($items as $e) {
            $getSlug = 'get'.ucfirst($slugField);
            $setSlug = 'set'.ucfirst($slugField);
            $getSrc  = 'get'.ucfirst($sourceField);

            $slug = $e->$getSlug ? $e->$getSlug() : null;
            if (!$slug || trim($slug) === '') {
                $new = $this->uniqueSlugger->generate($entityClass, $slugField, (string)$e->$getSrc(), $maxLen, $e->getId());
                $e->$setSlug($new);
                $this->em->persist($e);
                $count++;
            }
        }
        if ($count > 0) { $this->em->flush(); }
        $out->writeln(sprintf('%s: +%d', (new \ReflectionClass($entityClass))->getShortName(), $count));
        return $count;
    }
}
