<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Fixture;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

final class MatchShowApiController extends AbstractController
{
    #[Route('/api/match/{id}', name: 'api_match_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function __invoke(Fixture $m): JsonResponse
    {
        $out = [
            'id'      => $m->getId(),
            'dateUtc' => $m->getDateUtc()->format(DATE_ATOM),
            'status'  => $m->getStatus()->value,
            'round'   => $m->getRound(),
            'stage'   => $m->getStage(),
            'venue'   => $m->getVenue(),
            'home'    => [
                'id'    => $m->getHomeTeam()->getId(),
                'name'  => $m->getHomeTeam()->getName(),
                'logo'  => $m->getHomeTeam()->getLogo(),
                'goals' => $m->getHomeScore(),
            ],
            'away'    => [
                'id'    => $m->getAwayTeam()->getId(),
                'name'  => $m->getAwayTeam()->getName(),
                'logo'  => $m->getAwayTeam()->getLogo(),
                'goals' => $m->getAwayScore(),
            ],
        ];

        $response = $this->json($out);
        $response->setPublic(); // we’ll add caching later if you like
        return $response;
    }
}
