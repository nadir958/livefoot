<?php

namespace App\Form;

use App\Entity\LeagueConfig;
use Symfony\Component\Form\AbstractType;
use App\Form\Type\SeasonsCsvTransformer;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class LeagueConfigType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('providerLeagueId')
            ->add('name')
            ->add('country')
            ->add('seasonsActive')
            ->add('enabled')
            ->add('sortOrder')
            ->add('seasonsActive', TextType::class, ['required'=>false,'help'=>'CSV ex: 2023,2024']);
        ;
        $builder->get('seasonsActive')->addModelTransformer(new SeasonsCsvTransformer());
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => LeagueConfig::class,
        ]);
    }
}
