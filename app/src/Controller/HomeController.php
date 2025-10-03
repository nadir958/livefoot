<?php
declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class HomeController extends AbstractController
{
    /**
     * React homepage (Today view).
     * Renders templates/home/react.html.twig which mounts the React app.
     */
    #[Route('/', name: 'home', methods: ['GET'])]
    public function indexReact(Request $r): Response
    {
        $todayUtc = (new \DateTimeImmutable('today', new \DateTimeZone('UTC')))->format('Y-m-d');

        // Optional presets (if you want to deep-link a specific league/date)
        $preset = [
            'league' => $r->query->get('league'),  // db id or slug (your React can ignore if not needed)
            'date'   => $r->query->get('date', $todayUtc),
        ];

        $res = $this->render('home/react.html.twig', [
            'today'   => $todayUtc,
            'preset'  => $preset,
            'is_home' => true, // hide Home/Back buttons in the topbar
        ]);

        // No caching during development / rapid iteration
        $res->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');

        return $res;
    }
}
