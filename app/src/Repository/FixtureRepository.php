<?php
declare(strict_types=1);

namespace App\Repository;

use App\Entity\Fixture;
use App\Enum\MatchStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Fixture>
 */
final class FixtureRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Fixture::class);
    }

    /**
     * @param int|null                 $leagueId
     * @param int|null                 $season
     * @param \DateTimeImmutable|null  $dateUtcMidnight
     * @param MatchStatus|null         $status
     * @param int                      $limit
     * @param int                      $offset
     * @return Fixture[]
     */
    public function findByFilters(
        ?int $leagueId,
        ?int $season,
        ?\DateTimeImmutable $dateUtcMidnight,
        ?MatchStatus $status,
        int $limit = 10,
        int $offset = 0
    ): array {
        $qb = $this->createQueryBuilder('f')
            ->leftJoin('f.league', 'l')->addSelect('l')
            ->leftJoin('f.homeTeam', 'ht')->addSelect('ht')
            ->leftJoin('f.awayTeam', 'at')->addSelect('at');

        if ($leagueId) {
            $qb->andWhere('l.id = :lid')->setParameter('lid', $leagueId);
        }
        if ($season) {
            $qb->andWhere('f.season = :season')->setParameter('season', $season);
        }
        if ($dateUtcMidnight) {
            $qb->andWhere('f.dateUtc >= :d0 AND f.dateUtc < :d1')
               ->setParameter('d0', $dateUtcMidnight)
               ->setParameter('d1', $dateUtcMidnight->modify('+1 day'));
        }
        if ($status) {
            $qb->andWhere('f.status = :st')->setParameter('st', $status->value);
        }
        return $qb->getQuery()->getResult();
    }

    /** Team past matches strictly before now (UTC). @return Fixture[] */
    public function findTeamPast(int $teamId, int $limit = 20): array
    {
        $qb = $this->createQueryBuilder('f')
            ->andWhere('(f.homeTeam = :t OR f.awayTeam = :t)')
            ->andWhere('f.dateUtc < :now')
            ->orderBy('f.dateUtc', 'DESC')
            ->setMaxResults($limit);

        $qb->setParameter('t', $teamId);
        $qb->setParameter('now', new \DateTimeImmutable('now', new \DateTimeZone('UTC')));

        return $qb->getQuery()->getResult();
    }

    /** @return Fixture[] */
    public function findTeamUpcoming(
        int $teamId,
        int $season,
        \DateTimeImmutable $refDateUtc,
        int $limit = 10
    ): array {
        return $this->createQueryBuilder('f')
            ->leftJoin('f.league', 'l')->addSelect('l')
            ->leftJoin('f.homeTeam', 'ht')->addSelect('ht')
            ->leftJoin('f.awayTeam', 'at')->addSelect('at')
            ->andWhere('(ht.id = :tid OR at.id = :tid)')->setParameter('tid', $teamId)
            ->andWhere('f.season = :season')->setParameter('season', $season)
            ->andWhere('f.dateUtc >= :ref')->setParameter('ref', $refDateUtc)
            ->orderBy('f.dateUtc', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findPast(
        int $leagueId,
        int $season,
        \DateTimeImmutable $refDateUtc,
        int $limit = 10
    ): array {
        return $this->createQueryBuilder('f')
            ->leftJoin('f.league', 'l')->addSelect('l')
            ->leftJoin('f.homeTeam', 'ht')->addSelect('ht')
            ->leftJoin('f.awayTeam', 'at')->addSelect('at')
            ->andWhere('l.id = :lid')->setParameter('lid', $leagueId)
            ->andWhere('f.season = :season')->setParameter('season', $season)
            ->andWhere('f.dateUtc < :ref')->setParameter('ref', $refDateUtc)
            ->orderBy('f.dateUtc', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findUpcoming(
        int $leagueId,
        int $season,
        \DateTimeImmutable $refDateUtc,
        int $limit = 10
    ): array {
        return $this->createQueryBuilder('f')
            ->leftJoin('f.league', 'l')->addSelect('l')
            ->leftJoin('f.homeTeam', 'ht')->addSelect('ht')
            ->leftJoin('f.awayTeam', 'at')->addSelect('at')
            ->andWhere('l.id = :lid')->setParameter('lid', $leagueId)
            ->andWhere('f.season = :season')->setParameter('season', $season)
            ->andWhere('f.dateUtc >= :ref')->setParameter('ref', $refDateUtc)
            ->orderBy('f.dateUtc', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }


}
