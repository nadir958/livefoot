<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Country;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

final class CountryApiController extends AbstractController
{
    #[Route('/api/countries', name: 'api_countries', methods: ['GET'])]
    public function __invoke(EntityManagerInterface $em): JsonResponse
    {
        $rows = $em->createQueryBuilder()
            ->select('c.id, c.code, c.name, c.slug, c.flag')
            ->from(Country::class, 'c')
            ->andWhere('c.showOnHome = :v')->setParameter('v', true)
            ->orderBy('c.homeSort', 'ASC')
            ->addOrderBy('c.name', 'ASC')
            ->getQuery()->getArrayResult();

        // no cache while you iterate on the UI
        $res = $this->json($rows);
        $res->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        return $res;
    }
}
