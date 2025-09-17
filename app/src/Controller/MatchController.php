<?php
declare(strict_types=1);

namespace App\Controller;

use App\Entity\Fixture;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;

final class MatchController extends AbstractController
{
    #[Route('/match/{id}', name: 'match_show', requirements: ['id' => '\d+'])]
    public function __invoke(Fixture $m): Response
    {
        return $this->render('match/show.html.twig', ['m' => $m]);
    }
}
