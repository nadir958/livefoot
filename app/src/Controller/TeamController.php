<?php
declare(strict_types=1);

namespace App\Controller;

use App\Entity\Team;
use App\Repository\FixtureRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class TeamController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private FixtureRepository $fixtures
    ) {}

    // Pretty URL: /team/{slug}
    #[Route('/team/{slug}', name: 'team_show', requirements: ['slug' => '[a-z0-9\-]+'], methods: ['GET'])]
    public function __invoke(string $slug, Request $request): Response
    {
        $team = $this->em->getRepository(Team::class)->findOneBy(['slug' => $slug]);
        if (!$team) {
            throw $this->createNotFoundException('Team not found');
        }

        // Season from query (?season=2024), fallback to current UTC year
        $season = $request->query->getInt('season') ?: (int)(new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y');

        // Reference date (UTC) for past/upcoming split
        $today  = new \DateTimeImmutable('today', new \DateTimeZone('UTC'));
        $teamId = $team->getId();

        // Requires FixtureRepository::findTeamPast/findTeamUpcoming
        $past     = $this->fixtures->findTeamPast($teamId, $season, $today, 50);
        $upcoming = $this->fixtures->findTeamUpcoming($teamId, $season, $today, 10);

        return $this->render('team/show.html.twig', [
            'team'     => $team,
            'season'   => $season,
            'past'     => $past,
            'upcoming' => $upcoming,
        ]);
    }
}
