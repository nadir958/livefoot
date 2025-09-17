<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\League;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\EntityManagerInterface;

final class LeagueApiController extends AbstractController
{
    #[Route('/api/leagues', name: 'api_leagues', methods: ['GET'])]
    public function __invoke(Request $r, EntityManagerInterface $em): JsonResponse
    {
        $code = strtoupper((string)$r->query->get('country', 'FR'));

        $qb = $em->createQueryBuilder()
            ->select('l.id, l.name, l.slug, l.logo, l.seasonCurrent')
            ->from(League::class, 'l')
            ->join('l.country', 'c')
            ->andWhere('c.code = :code')
            ->setParameter('code', $code)
            ->orderBy('l.name', 'ASC');

        $rows = array_map(fn($x) => [
            'id'     => (int)$x['id'],
            'name'   => $x['name'],
            'slug'   => $x['slug'],
            'logo'   => $x['logo'],
            'season' => (int)$x['seasonCurrent'],
        ], $qb->getQuery()->getArrayResult());

        return $this->json($rows);
        
    }
}
