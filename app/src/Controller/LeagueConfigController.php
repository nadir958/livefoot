<?php

namespace App\Controller;

use App\Entity\LeagueConfig;
use App\Form\LeagueConfigType;
use App\Repository\LeagueConfigRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/league/config')]
final class LeagueConfigController extends AbstractController
{
    #[Route(name: 'app_league_config_index', methods: ['GET'])]
    public function index(LeagueConfigRepository $leagueConfigRepository): Response
    {
        return $this->render('league_config/index.html.twig', [
            'league_configs' => $leagueConfigRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_league_config_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $leagueConfig = new LeagueConfig();
        $form = $this->createForm(LeagueConfigType::class, $leagueConfig);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($leagueConfig);
            $entityManager->flush();

            return $this->redirectToRoute('app_league_config_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('league_config/new.html.twig', [
            'league_config' => $leagueConfig,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_league_config_show', methods: ['GET'])]
    public function show(LeagueConfig $leagueConfig): Response
    {
        return $this->render('league_config/show.html.twig', [
            'league_config' => $leagueConfig,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_league_config_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, LeagueConfig $leagueConfig, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(LeagueConfigType::class, $leagueConfig);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_league_config_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('league_config/edit.html.twig', [
            'league_config' => $leagueConfig,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_league_config_delete', methods: ['POST'])]
    public function delete(Request $request, LeagueConfig $leagueConfig, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$leagueConfig->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($leagueConfig);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_league_config_index', [], Response::HTTP_SEE_OTHER);
    }
}
