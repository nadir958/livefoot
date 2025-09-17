<?php
declare(strict_types=1);

namespace App\Util;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;

final class UniqueSlugger
{
    /** @var string[] */
    private array $reserved = ['new','edit','api','admin','login','logout','match','matches'];

    public function __construct(private Slugger $slugger, private EntityManagerInterface $em){}

    /**
     * @param class-string $entityClass  (ex: App\Entity\League::class)
     * @param string       $field        (ex: 'slug')
     * @param string       $text         (source: name)
     * @param int          $maxLen       (longueur max DB)
     * @param mixed|null   $excludeId    (id courant à ignorer si update)
     */
    public function generate(string $entityClass, string $field, string $text, int $maxLen = 128, mixed $excludeId = null): string
    {
        $base = $this->sanitizeBase($this->slugger->base($text));
        if ($base === '' || \in_array($base, $this->reserved, true)) {
            $base = 'item';
        }

        $repo = $this->em->getRepository($entityClass);
        \assert($repo instanceof EntityRepository);

        $slug = $this->truncate($base, $maxLen);
        $i = 2;

        while ($this->exists($repo, $field, $slug, $excludeId)) {
            // garde de la place pour "-X"
            $suffix = '-'.$i++;
            $slug = $this->truncate($base, $maxLen - \strlen($suffix)) . $suffix;
        }
        return $slug;
    }

    private function sanitizeBase(string $base): string
    {
        // supprime les doubles tirets éventuels
        $base = preg_replace('~-+~', '-', $base) ?? $base;
        // supprime tirets début/fin
        return trim($base, '-');
    }

    private function truncate(string $s, int $max): string
    {
        return mb_strimwidth($s, 0, $max, '', 'UTF-8');
    }

    private function exists(EntityRepository $repo, string $field, string $slug, mixed $excludeId = null): bool
    {
        $qb = $repo->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->andWhere("e.$field = :s")->setParameter('s', $slug);

        if ($excludeId !== null) {
            $qb->andWhere('e.id <> :id')->setParameter('id', $excludeId);
        }
        return (int)$qb->getQuery()->getSingleScalarResult() > 0;
    }
}
