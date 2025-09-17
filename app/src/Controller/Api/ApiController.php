<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Service\FootballProvider;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class ApiController
{
    #[Route('/api/live', name: 'api_live', methods: ['GET'])]
    public function live(FootballProvider $fp): JsonResponse
    {
        $items = $fp->liveFixtures();

        $data = array_map(static function ($f): array {
            return [
                'id'       => $f->id,
                'leagueId' => $f->leagueId,
                'league'   => $f->leagueName,
                'kickoff'  => $f->kickoff->format(DATE_ATOM),
                'status'   => $f->status,
                'minute'   => $f->minute,
                'home'     => ['id' => $f->homeId, 'name' => $f->homeName, 'goals' => $f->homeGoals],
                'away'     => ['id' => $f->awayId, 'name' => $f->awayName, 'goals' => $f->awayGoals],
            ];
        }, $items);

        // API interne → pas de cache HTTP côté client
        return new JsonResponse($data, 200, ['Cache-Control' => 'no-store']);
    }

    #[Route('/api/fixtures', name: 'api_fixtures', methods: ['GET'])]
    public function fixtures(Request $request, FootballProvider $fp): JsonResponse
    {
        $dateParam = $request->query->get('date', 'today');

        try {
            // Accepte 'today' ou une date 'YYYY-MM-DD'
            $date = $dateParam === 'today'
                ? new \DateTimeImmutable('today')
                : \DateTimeImmutable::createFromFormat('Y-m-d', $dateParam);

            if (!$date instanceof \DateTimeImmutable) {
                throw new \InvalidArgumentException('Invalid date format, expected YYYY-MM-DD or "today".');
            }
        } catch (\Throwable $e) {
            return new JsonResponse(
                ['error' => $e->getMessage()],
                400,
                ['Cache-Control' => 'no-store']
            );
        }

        $items = $fp->fixturesByDate($date);

        $data = array_map(static function ($f): array {
            return [
                'id'      => $f->id,
                'league'  => $f->leagueName,
                'kickoff' => $f->kickoff->format(DATE_ATOM),
                'status'  => $f->status,
                'home'    => $f->homeName,
                'away'    => $f->awayName,
            ];
        }, $items);

        return new JsonResponse($data, 200, ['Cache-Control' => 'no-store']);
    }
}
