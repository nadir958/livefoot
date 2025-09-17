<?php
declare(strict_types=1);

namespace App\Controller;

use App\Entity\Country;
use App\Entity\League;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\ORM\EntityManagerInterface;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'home')]
    public function __invoke(EntityManagerInterface $em): Response
    {
        $countries = $em->getRepository(Country::class)->createQueryBuilder('c')
            ->orderBy('c.name', 'ASC')->getQuery()->getResult();

        // Pick a reasonable default country & league
        $defaultCountry = $em->getRepository(Country::class)->findOneBy(['code' => 'MA']) ?? $countries[0] ?? null;

        $defaultLeague = null;
        if ($defaultCountry) {
            $defaultLeague = $em->getRepository(League::class)->createQueryBuilder('l')
                ->join('l.country', 'co')
                ->andWhere('co.id = :cid')->setParameter('cid', $defaultCountry->getId())
                ->orderBy('l.name', 'ASC')->setMaxResults(1)->getQuery()->getOneOrNullResult();
        }

        return $this->render('home/index.html.twig', [
            'countries' => $countries,
            'defaultCountry' => $defaultCountry,
            'defaultLeague'  => $defaultLeague,
            'year' => (int)date('Y'),
        ]);
    }
}
