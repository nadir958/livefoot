<?php
declare(strict_types=1);

namespace App\Controller;

use App\Entity\League;
use App\Repository\FixtureRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class LeagueController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private FixtureRepository $fixtures
    ) {}

    #[Route('/league/{key}', name: 'league_show')]
    public function __invoke(string $key, Request $r): Response
    {
        $repo   = $this->em->getRepository(League::class);
        $league = ctype_digit($key) ? $repo->find((int)$key) : $repo->findOneBy(['slug' => $key]);

        if (!$league) {
            throw $this->createNotFoundException('League not found');
        }

        $season = (int)($r->query->get('season') ?? $league->getSeasonCurrent());
        $tab    = (string)$r->query->get('tab', 'standings');

        $today    = new \DateTimeImmutable('today', new \DateTimeZone('UTC'));
        $past     = $this->fixtures->findPast($league->getId(), $season, $today, 10);
        $upcoming = $this->fixtures->findUpcoming($league->getId(), $season, $today, 10);

        return $this->render('league/show.html.twig', compact('league', 'season', 'tab', 'past', 'upcoming'));
    }
}
