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
        $leagueId   = $r->query->get('league');      // id or null
        $leagueSlug = $r->query->get('league_slug'); // optional
        $season     = $r->query->getInt('season') ?: null;
        $dateStr    = $r->query->get('date') ?: null;     // YYYY-MM-DD (UTC)
        $statusStr  = $r->query->get('status') ?: null;   // scheduled|live|finished

        // Resolve league id if slug provided (or if league is a non-digit string)
        if (!$leagueId && $leagueSlug) {
            $league = $this->em->getRepository(League::class)->findOneBy(['slug' => (string)$leagueSlug]);
            $leagueId = $league?->getId();
        } elseif (\is_string($leagueId) && !ctype_digit($leagueId)) {
            $league = $this->em->getRepository(League::class)->findOneBy(['slug' => (string)$leagueId]);
            $leagueId = $league?->getId();
        } elseif ($leagueId !== null) {
            $leagueId = (int)$leagueId;
        }

        $date   = $dateStr ? new \DateTimeImmutable($dateStr.' 00:00:00', new \DateTimeZone('UTC')) : null;
        $status = $statusStr ? MatchStatus::from((string)$statusStr) : null;

        $items = $this->repo->findByFilters($leagueId, $season, $date, $status);

        // 🔗 Include team slugs so the UI can link to /team/{slug}
        $out = array_map(function (Fixture $m) {
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
                    'goals' => $m->getHomeScore(),
                ],
                'away'    => [
                    'id'    => $m->getAwayTeam()->getId(),
                    'slug'  => $m->getAwayTeam()->getSlug(),
                    'name'  => $m->getAwayTeam()->getName(),
                    'logo'  => $m->getAwayTeam()->getLogo(),
                    'goals' => $m->getAwayScore(),
                ],
            ];
        }, $items);

        $res = $this->json($out);
        // No caching while iterating on UI
        $res->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        return $res;
    }
}
