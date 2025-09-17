<?php
declare(strict_types=1);

namespace App\Form;

use App\Entity\Country;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class CountryHomeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $b, array $options): void
    {
        $b
            ->add('showOnHome', CheckboxType::class, [
                'required' => false,
                'label' => 'Show on Home selectors',
            ])
            ->add('homeSort', IntegerType::class, [
                'required' => false,
                'label' => 'Sort (lower = earlier)',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Country::class]);
    }
}
