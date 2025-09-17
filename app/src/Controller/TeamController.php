<?php
declare(strict_types=1);

namespace App\Controller;

use App\Entity\Team;
use App\Repository\FixtureRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;

final class TeamController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private FixtureRepository $fixtures
    ) {}

    // Pretty URL: /team/{name-of-the-team}
    #[Route('/team/{slug}', name: 'team_show', requirements: ['slug' => '[a-z0-9\-]+'])]
    public function __invoke(string $slug): Response
    {
        $team = $this->em->getRepository(Team::class)->findOneBy(['slug' => $slug]);
        if (!$team) {
            throw $this->createNotFoundException('Team not found');
        }

        $past     = $this->fixtures->findTeamPast($team->getId(), 15);
        $upcoming = $this->fixtures->findTeamUpcoming($team->getId(), 15);

        return $this->render('team/show.html.twig', compact('team', 'past', 'upcoming'));
    }
}
