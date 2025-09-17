<?php
declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\League;
use App\Entity\Country;
use App\Form\LeagueHomeType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/league', name: 'admin_league_')]
final class LeagueAdminController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em) {}

    #[Route('/', name: 'index')]
    public function index(Request $r): Response
    {
        $cc = $r->query->get('country'); // ISO2 optional filter

        $qb = $this->em->createQueryBuilder()
            ->select('l, c, CASE WHEN l.homeSort IS NULL THEN 1 ELSE 0 END AS HIDDEN nullsLast')
            ->from(League::class, 'l')
            ->join('l.country', 'c');

        if ($cc) {
            $qb->andWhere('c.code = :cc')->setParameter('cc', strtoupper($cc));
        }

        $qb->orderBy('c.name', 'ASC')
            ->addOrderBy('nullsLast', 'ASC')   // non-null first
            ->addOrderBy('l.homeSort', 'ASC')  // then by sort value
            ->addOrderBy('l.name', 'ASC');     // then by name

        $leagues = $qb->getQuery()->getResult();
        $countries = $this->em->getRepository(Country::class)->findBy([], ['name'=>'ASC']);

        return $this->render('admin/league/index.html.twig', compact('leagues','countries','cc'));
    }

    #[Route('/{id}/edit', name: 'edit', requirements: ['id' => '\d+'])]
    public function edit(League $league, Request $r): Response
    {
        $form = $this->createForm(LeagueHomeType::class, $league);
        $form->handleRequest($r);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->flush();
            $this->addFlash('success', 'League updated.');
            return $this->redirectToRoute('admin_league_index');
        }
        return $this->render('admin/league/edit.html.twig', [
            'league' => $league,
            'form' => $form->createView(),
        ]);
    }
}
