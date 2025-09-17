<?php
declare(strict_types=1);

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class UserAdminType extends AbstractType
{
    public function buildForm(FormBuilderInterface $b, array $options): void
    {
        $b
          ->add('email', EmailType::class)
          ->add('roles', ChoiceType::class, [
              'choices'  => ['Admin' => 'ROLE_ADMIN', 'User' => 'ROLE_USER'],
              'multiple' => true,
              'expanded' => true,
          ])
          ->add('plainPassword', PasswordType::class, [
              'mapped' => false,
              'required' => false,
              'label' => 'New password (leave blank to keep current)',
          ]);
    }
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => User::class]);
    }
}
