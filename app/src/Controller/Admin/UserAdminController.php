<?php
declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\User;
use App\Form\UserAdminType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/user', name: 'admin_user_')]
final class UserAdminController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em) {}

    #[Route('/', name: 'index')]
    public function index(): Response
    {
        $users = $this->em->getRepository(User::class)->findBy([], ['email'=>'ASC']);
        return $this->render('admin/user/index.html.twig', compact('users'));
    }

    #[Route('/new', name: 'new')]
    public function new(Request $r, UserPasswordHasherInterface $hasher): Response
    {
        $user = new User();
        $form = $this->createForm(UserAdminType::class, $user);
        $form->handleRequest($r);

        if ($form->isSubmitted() && $form->isValid()) {
            $plain = (string)$form->get('plainPassword')->getData();
            if ($plain !== '') {
                $user->setPassword($hasher->hashPassword($user, $plain));
            }
            $this->em->persist($user);
            $this->em->flush();
            $this->addFlash('success', 'User created.');
            return $this->redirectToRoute('admin_user_index');
        }

        return $this->render('admin/user/edit.html.twig', ['form' => $form->createView(), 'isNew' => true]);
    }

    #[Route('/{id}/edit', name: 'edit', requirements: ['id' => '\d+'])]
    public function edit(User $user, Request $r, UserPasswordHasherInterface $hasher): Response
    {
        $form = $this->createForm(UserAdminType::class, $user);
        $form->handleRequest($r);

        if ($form->isSubmitted() && $form->isValid()) {
            $plain = (string)$form->get('plainPassword')->getData();
            if ($plain !== '') {
                $user->setPassword($hasher->hashPassword($user, $plain));
            }
            $this->em->flush();
            $this->addFlash('success', 'User updated.');
            return $this->redirectToRoute('admin_user_index');
        }

        return $this->render('admin/user/edit.html.twig', ['form' => $form->createView(), 'isNew' => false]);
    }
}
