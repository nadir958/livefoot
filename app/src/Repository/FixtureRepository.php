<?php
declare(strict_types=1);

namespace App\Repository;

use App\Entity\Fixture;
use App\Enum\MatchStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class FixtureRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Fixture::class);
    }

    /** @return Fixture[] */
    public function findPast(int $leagueId, int $season, \DateTimeImmutable $pivot, int $limit = 20): array
    {
        $qb = $this->createQueryBuilder('f')
            ->andWhere('f.league = :l')
            ->andWhere('f.season = :s')
            ->andWhere('f.dateUtc < :p')
            ->orderBy('f.dateUtc', 'DESC')
            ->setMaxResults($limit);

        $qb->setParameter('l', $leagueId);
        $qb->setParameter('s', $season);
        $qb->setParameter('p', $pivot);

        return $qb->getQuery()->getResult();
    }

    /** @return Fixture[] */
    public function findUpcoming(int $leagueId, int $season, \DateTimeImmutable $pivot, int $limit = 20): array
    {
        $qb = $this->createQueryBuilder('f')
            ->andWhere('f.league = :l')
            ->andWhere('f.season = :s')
            ->andWhere('f.dateUtc >= :p')
            ->orderBy('f.dateUtc', 'ASC')
            ->setMaxResults($limit);

        $qb->setParameter('l', $leagueId);
        $qb->setParameter('s', $season);
        $qb->setParameter('p', $pivot);

        return $qb->getQuery()->getResult();
    }

    /** @return Fixture[] */
    public function findByFilters(
        ?int $leagueId,
        ?int $season,
        ?\DateTimeImmutable $date = null,
        ?MatchStatus $status = null
    ): array {
        $qb = $this->createQueryBuilder('f')->orderBy('f.dateUtc', 'ASC');

        if ($leagueId) { $qb->andWhere('f.league = :l')->setParameter('l', $leagueId); }
        if ($season)   { $qb->andWhere('f.season = :s')->setParameter('s', $season); }
        if ($date) {
            $start = $date->setTime(0,0,0);
            $end   = $start->modify('+1 day');
            $qb->andWhere('f.dateUtc >= :start')->setParameter('start', $start);
            $qb->andWhere('f.dateUtc < :end')->setParameter('end', $end);
        }
        if ($status) { $qb->andWhere('f.status = :st')->setParameter('st', $status); }

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

    /** Team upcoming matches at/after now (UTC). @return Fixture[] */
    public function findTeamUpcoming(int $teamId, int $limit = 20): array
    {
        $qb = $this->createQueryBuilder('f')
            ->andWhere('(f.homeTeam = :t OR f.awayTeam = :t)')
            ->andWhere('f.dateUtc >= :now')
            ->orderBy('f.dateUtc', 'ASC')
            ->setMaxResults($limit);

        $qb->setParameter('t', $teamId);
        $qb->setParameter('now', new \DateTimeImmutable('now', new \DateTimeZone('UTC')));

        return $qb->getQuery()->getResult();
    }
}
