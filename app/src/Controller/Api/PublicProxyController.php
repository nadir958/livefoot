<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\League;
use App\Service\FootballProvider;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/public', name: 'api_public_')]
final class PublicProxyController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private FootballProvider $fp,
    ) {}

    /**
     * Return featured leagues (chosen in back office) + their API externalId
     * Output: [{ id, slug, name, logo, externalId }, ...]
     */
    #[Route('/home-leagues', name: 'home_leagues', methods: ['GET'])]
    public function homeLeagues(): JsonResponse
    {
        // Assuming you have boolean fields on League: showOnHome=true
        // Adjust the property/filter to match your schema.
        $rows = $this->em->getRepository(League::class)->findBy(
            ['showOnHome' => true],
            ['name' => 'ASC']
        );

        $out = array_map(static function (League $l) {
            return [
                'id'         => $l->getId(),
                'slug'       => $l->getSlug(),
                'name'       => $l->getName(),
                'logo'       => $l->getLogo(),
                'externalId' => $l->getExternalId(), // REQUIRED for API-Football calls
            ];
        }, $rows);

        $res = new JsonResponse($out);
        $res->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        return $res;
    }

    /**
     * Proxy to API-Football fixtures by leagueExt + date.
     * Query:  ?leagueExt=39&date=YYYY-MM-DD  (UTC)
     * Output: same normalized shape you already use in the app.
     */
    #[Route('/matches', name: 'matches', methods: ['GET'])]
    public function matches(Request $r): JsonResponse
    {
        $leagueExt = (int)$r->query->get('leagueExt', 0);
        if ($leagueExt <= 0) {
            return new JsonResponse(['error' => 'leagueExt is required'], 400);
        }

        $date = $r->query->get('date'); // optional YYYY-MM-DD (UTC)
        $season = (int)($r->query->get('season') ?? (($date && preg_match('/^\d{4}/', $date)) ? substr($date, 0, 4) : (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y')));

        // Call API-Football via your provider
        $items = $this->fp->getMatchesByLeagueSeason($leagueExt, (int)$season, $date ? (string)$date : null);

        // Pass through as-is (already normalized in FootballProvider)
        $res = new JsonResponse($items);
        $res->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        return $res;
    }
}
