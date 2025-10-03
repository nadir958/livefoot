<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Fixture;
use App\Entity\League;
use App\Enum\MatchStatus;
use App\Repository\FixtureRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

final class MatchApiController extends AbstractController
{
    public function __construct(
        private FixtureRepository $repo,
        private EntityManagerInterface $em
    ) {}

    #[Route('/api/matches', name: 'api_matches', methods: ['GET'])]
    public function __invoke(Request $r): JsonResponse
    {
        $leagueId   = $r->query->get('league');       // id or slug or null
        $leagueSlug = $r->query->get('league_slug');  // optional explicit slug
        $season     = $r->query->getInt('season') ?: null;
        $dateStr    = $r->query->get('date') ?: null;     // YYYY-MM-DD (UTC)
        $statusStr  = $r->query->get('status') ?: null;   // scheduled|live|finished
        $limit      = \min(\max($r->query->getInt('limit', 10), 1), 50); // clamp 1..50
        $offset     = \max($r->query->getInt('offset', 0), 0);

        // Resolve league id from slug if needed
        if (!$leagueId && $leagueSlug) {
            $league = $this->em->getRepository(League::class)->findOneBy(['slug' => (string) $leagueSlug]);
            $leagueId = $league?->getId();
        } elseif (\is_string($leagueId) && !ctype_digit($leagueId)) {
            // league provided but not a number → treat as slug
            $league = $this->em->getRepository(League::class)->findOneBy(['slug' => (string) $leagueId]);
            $leagueId = $league?->getId();
        } elseif ($leagueId !== null) {
            $leagueId = (int) $leagueId;
        }

        // Parse date safely (expecting YYYY-MM-DD in UTC)
        $date = null;
        if ($dateStr) {
            try {
                $date = new \DateTimeImmutable($dateStr . ' 00:00:00', new \DateTimeZone('UTC'));
            } catch (\Exception) {
                return $this->badRequest('Invalid date format. Expected YYYY-MM-DD in UTC.');
            }
        }

        // Parse status safely
        $status = null;
        if ($statusStr) {
            try {
                $status = MatchStatus::from((string) $statusStr);
            } catch (\ValueError) {
                return $this->badRequest('Invalid status. Allowed: scheduled | live | finished.');
            }
        }

        // Query
        $items = $this->repo->findByFilters(
            $leagueId,
            $season,
            $date,
            $status,
            $limit,
            $offset
        );

        // Shape output for UI (include team slugs for linking)
        $out = \array_map(static function (Fixture $m): array {

            $status = is_string($m->getStatus()) ? $m->getStatus() : $m->getStatus()->value; // if enum, adapt
            $homeGoals = $status === 'finished' ? $m->getHomeScore() : null;
            $awayGoals = $status === 'finished' ? $m->getAwayScore() : null;
            return [
                'id'      => $m->getId(),
                'dateUtc' => $m->getDateUtc()->format(DATE_ATOM),
                'status'  => $m->getStatus()->value,
                'round'   => $m->getRound(),
                'stage'   => $m->getStage(),
                'venue'   => $m->getVenue(),
                'home'    => [
                    'id'    => $m->getHomeTeam()->getId(),
                    'slug'  => $m->getHomeTeam()->getSlug(),
                    'name'  => $m->getHomeTeam()->getName(),
                    'logo'  => $m->getHomeTeam()->getLogo(),
                    'goals' => $homeGoals,
                ],
                'away'    => [
                    'id'    => $m->getAwayTeam()->getId(),
                    'slug'  => $m->getAwayTeam()->getSlug(),
                    'name'  => $m->getAwayTeam()->getName(),
                    'logo'  => $m->getAwayTeam()->getLogo(),
                    'goals' => $awayGoals,
                ],
            ];
        }, $items);

        $res = $this->json($out);
        // Disable caching during UI iteration
        $res->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');

        return $res;
    }

    private function badRequest(string $message): JsonResponse
    {
        $res = $this->json(['error' => $message], 400);
        $res->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        return $res;
    }
}
