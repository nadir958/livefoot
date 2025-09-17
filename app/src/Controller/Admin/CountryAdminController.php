<?php
declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Country;
use App\Form\CountryHomeType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[Route('/admin/country', name: 'admin_country_')]
final class CountryAdminController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em) {}

    #[Route('/', name: 'index')]
    public function index(): Response
    {
        // DQL: avoid COALESCE for compatibility
        $countries = $this->em->createQueryBuilder()
            ->select('c, CASE WHEN c.homeSort IS NULL THEN 1 ELSE 0 END AS HIDDEN nullsLast')
            ->from(Country::class, 'c')
            ->orderBy('nullsLast', 'ASC')
            ->addOrderBy('c.homeSort', 'ASC')
            ->addOrderBy('c.name', 'ASC')
            ->getQuery()->getResult();

        return $this->render('admin/country/index.html.twig', compact('countries'));
    }

    #[Route('/{id}/edit', name: 'edit', requirements: ['id' => '\d+'])]
    public function edit(Country $country, Request $r): Response
    {
        $form = $this->createForm(CountryHomeType::class, $country);
        $form->handleRequest($r);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->flush();
            $this->addFlash('success', 'Country updated.');
            return $this->redirectToRoute('admin_country_index');
        }
        return $this->render('admin/country/edit.html.twig', [
            'country' => $country,
            'form' => $form->createView(),
        ]);
    }
}
