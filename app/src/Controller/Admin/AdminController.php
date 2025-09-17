<?php
declare(strict_types=1);

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AdminController extends AbstractController
{
    #[Route('/admin', name: 'admin_index', methods: ['GET'])]
    public function index(): Response
    {
        // Protect if needed: $this->denyAccessUnlessGranted('ROLE_EDITOR');
        return $this->render('admin/index.html.twig', [
            'kpis' => ['live' => 0, 'today' => 0, 'apiErrors' => 0],
        ]);
    }
}
