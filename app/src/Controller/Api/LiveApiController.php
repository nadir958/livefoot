<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Fixture;
use App\Repository\FixtureRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

final class LiveApiController extends AbstractController
{
    public function __construct(private FixtureRepository $repo) {}

    #[Route('/api/live', name: 'api_live', methods: ['GET'])]
    public function __invoke(Request $r): JsonResponse
    {
        $leagueId = $r->query->getInt('league') ?: null;
        $season   = $r->query->getInt('season') ?: null;
        $limit    = $r->query->getInt('limit') ?: 20;

        $items = $this->repo->findLive($leagueId, $season, $limit);

        $out = array_map(function(Fixture $m){
            return [
                'id'      => $m->getId(),
                'dateUtc' => $m->getDateUtc()->format(DATE_ATOM),
                'home'    => ['name' => $m->getHomeTeam()->getName(), 'logo' => $m->getHomeTeam()->getLogo(), 'goals' => $m->getHomeScore()],
                'away'    => ['name' => $m->getAwayTeam()->getName(), 'logo' => $m->getAwayTeam()->getLogo(), 'goals' => $m->getAwayScore()],
            ];
        }, $items);

        return $this->json($out);
    }
}
