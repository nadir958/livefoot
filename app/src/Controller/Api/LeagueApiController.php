<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\League;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

final class LeagueApiController extends AbstractController
{
    #[Route('/api/leagues', name: 'api_leagues', methods: ['GET'])]
    public function __invoke(Request $request, EntityManagerInterface $em): JsonResponse
    {
        // Required: ISO2 country code (e.g., FR, GB, ES, IT, MA)
        $code = strtoupper((string)$request->query->get('country', ''));

        if ($code === '') {
            // No country → return empty list (home UI will show notice)
            return $this->json([]);
        }

        // DQL: avoid COALESCE for broad compatibility
        $qb = $em->createQueryBuilder()
            ->select("
                l.id           AS id,
                l.name         AS name,
                l.slug         AS slug,
                l.logo         AS logo,
                l.seasonCurrent AS season,
                c.slug         AS countrySlug,
                CASE WHEN l.homeSort IS NULL THEN 1 ELSE 0 END AS HIDDEN nullsLast
            ")
            ->from(League::class, 'l')
            ->join('l.country', 'c')
            ->andWhere('c.code = :code')->setParameter('code', $code)
            ->andWhere('l.showOnHome = :on')->setParameter('on', true)
            ->orderBy('nullsLast', 'ASC')
            ->addOrderBy('l.homeSort', 'ASC')
            ->addOrderBy('l.name', 'ASC');

        $rows = $qb->getQuery()->getArrayResult();

        // no HTTP caching while iterating on the UI
        $res = $this->json($rows);
        $res->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        return $res;
    }
}
