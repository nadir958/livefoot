<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\League;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;

final class StandingsApiController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em, private Connection $db) {}

    #[Route('/api/standings', name: 'api_standings', methods: ['GET'])]
    public function __invoke(Request $r): JsonResponse
    {
        $leagueId = $r->query->getInt('league');
        $season   = $r->query->getInt('season');

        if (!$leagueId || !$season) {
            return $this->json(['error' => 'league and season required'], 400);
        }

        // Ensure league exists (optional sanity)
        if (!$this->em->getRepository(League::class)->find($leagueId)) {
            return $this->json(['error' => 'league not found'], 404);
        }

        // Compute standings from finished fixtures
        $sql = <<<SQL
WITH src AS (
  SELECT f.home_team_id AS team_id,
         f.home_score   AS gf,
         f.away_score   AS ga,
         CASE WHEN f.home_score > f.away_score THEN 3 WHEN f.home_score = f.away_score THEN 1 ELSE 0 END AS pts,
         CASE WHEN f.home_score > f.away_score THEN 1 ELSE 0 END AS w,
         CASE WHEN f.home_score = f.away_score THEN 1 ELSE 0 END AS d,
         CASE WHEN f.home_score < f.away_score THEN 1 ELSE 0 END AS l
    FROM matches f
   WHERE f.league_id = :leagueId AND f.season = :season AND f.status = 'finished'
  UNION ALL
  SELECT f.away_team_id AS team_id,
         f.away_score   AS gf,
         f.home_score   AS ga,
         CASE WHEN f.away_score > f.home_score THEN 3 WHEN f.away_score = f.home_score THEN 1 ELSE 0 END AS pts,
         CASE WHEN f.away_score > f.home_score THEN 1 ELSE 0 END AS w,
         CASE WHEN f.away_score = f.home_score THEN 1 ELSE 0 END AS d,
         CASE WHEN f.away_score < f.home_score THEN 1 ELSE 0 END AS l
    FROM matches f
   WHERE f.league_id = :leagueId AND f.season = :season AND f.status = 'finished'
)
SELECT t.id          AS teamId,
       t.name        AS teamName,
       COALESCE(COUNT(1),0)              AS played,
       COALESCE(SUM(w),0)                AS wins,
       COALESCE(SUM(d),0)                AS draws,
       COALESCE(SUM(l),0)                AS losses,
       COALESCE(SUM(gf),0)               AS gf,
       COALESCE(SUM(ga),0)               AS ga,
       COALESCE(SUM(gf) - SUM(ga),0)     AS gd,
       COALESCE(SUM(pts),0)              AS points,
       t.logo                             AS logo
  FROM team t
  LEFT JOIN src ON src.team_id = t.id
 WHERE t.id IN (
   SELECT DISTINCT f.home_team_id FROM matches f WHERE f.league_id = :leagueId AND f.season = :season
   UNION
   SELECT DISTINCT f.away_team_id FROM matches f WHERE f.league_id = :leagueId AND f.season = :season
 )
 GROUP BY t.id, t.name, t.logo
 ORDER BY points DESC, gd DESC, gf DESC, teamName ASC
SQL;

        $rows = $this->db->fetchAllAssociative($sql, ['leagueId' => $leagueId, 'season' => $season]);

        return $this->json($rows);
    }
}
