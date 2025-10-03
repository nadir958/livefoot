<?php
declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\League;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

final class HomeLeaguesApiController extends AbstractController
{
    #[Route('/api/home-leagues', name: 'api_home_leagues', methods: ['GET'])]
    public function __invoke(\Doctrine\ORM\EntityManagerInterface $em): JsonResponse
    {
        $repo = $em->getRepository(League::class);

        // Assumes you have a boolean property like "showOnHome" set via Admin → Leagues.
        // If your property name differs, change the findBy criteria below.
        $rows = $repo->findBy(
            ['showOnHome' => true],
            ['name' => 'ASC'] // tweak ordering as you like
        );

        $out = [];
        foreach ($rows as $l) {
            // Skip if no logo — or remove this guard if you want placeholders
            if (!$l->getLogo()) {
                continue;
            }
            $out[] = [
                'id'          => $l->getId(),
                'name'        => $l->getName(),
                'slug'        => $l->getSlug(),
                'logo'        => $l->getLogo(),
                'countrySlug' => $l->getCountry()?->getSlug(),
            ];
        }

        $res = $this->json($out);
        $res->headers->set('Cache-Control', 'no-store'); // keep it fresh while you iterate
        return $res;
    }
}
