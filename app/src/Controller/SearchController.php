<?php
declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class SearchController extends AbstractController
{
    #[Route('/search', name: 'search', methods: ['GET'])]
    public function __invoke(Request $r): Response
    {
        // Just render the shell; data is fetched from the admin-filtered APIs:
        //  - GET /api/countries         (only showOnHome)
        //  - GET /api/leagues?country=  (only showOnHome)
        //  - GET /api/matches?...
        $today = (new \DateTimeImmutable('today', new \DateTimeZone('UTC')))->format('Y-m-d');

        // Allow optional preselect via query (?country=FR&league=61&season=2025&date=YYYY-MM-DD&status=finished)
        $preset = [
            'country' => $r->query->get('country'),
            'league'  => $r->query->get('league'),
            'season'  => $r->query->get('season'),
            'date'    => $r->query->get('date', $today),
            'status'  => $r->query->get('status'),
        ];

        return $this->render('home/index.html.twig', [
            'today'  => $today,
            'preset' => $preset,
        ]);
    }
}
