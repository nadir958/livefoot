<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Fixture;
use App\Enum\MatchStatus;
use App\Repository\FixtureRepository;
use App\Entity\League;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\EntityManagerInterface;

final class MatchApiController extends AbstractController
{
    public function __construct(private FixtureRepository $repo) {}

    #[Route('/api/matches', name: 'api_matches', methods: ['GET'])]
    public function __invoke(Request $r, EntityManagerInterface $em): JsonResponse
    {
        $leagueId   = $r->query->getInt('league') ?: null;
        $leagueSlug = $r->query->get('league_slug') ?: null;
        $season     = $r->query->getInt('season') ?: null;
        $dateStr    = $r->query->get('date') ?: null;
        $statusStr  = $r->query->get('status') ?: null;

        // Accept league by id OR slug
        if (!$leagueId && $leagueSlug) {
            $league = $em->getRepository(League::class)->findOneBy(['slug' => $leagueSlug]);
            $leagueId = $league?->getId();
        }

        $date = $dateStr ? new \DateTimeImmutable($dateStr . ' 00:00:00', new \DateTimeZone('UTC')) : null;
        $status = $statusStr ? MatchStatus::from($statusStr) : null;

        $items = $this->repo->findByFilters($leagueId, $season, $date, $status);

        $out = array_map(function (Fixture $m) {
            return [
                'id'      => $m->getId(),
                'dateUtc' => $m->getDateUtc()->format(DATE_ATOM),
                'status'  => $m->getStatus()->value,
                'round'   => $m->getRound(),
                'stage'   => $m->getStage(),
                'venue'   => $m->getVenue(),
                'home'    => [
                    'id'   => $m->getHomeTeam()->getId(),
                    'name' => $m->getHomeTeam()->getName(),
                    'logo' => $m->getHomeTeam()->getLogo(),
                    'goals'=> $m->getHomeScore(),
                ],
                'away'    => [
                    'id'   => $m->getAwayTeam()->getId(),
                    'name' => $m->getAwayTeam()->getName(),
                    'logo' => $m->getAwayTeam()->getLogo(),
                    'goals'=> $m->getAwayScore(),
                ],
            ];
        }, $items);

        return $this->json($out);
    }
}
