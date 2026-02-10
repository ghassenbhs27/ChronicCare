<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RDVFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if ($options['include_professionnel']) {
            $builder->add('professionnel', EntityType::class, [
                'class' => User::class,
                'choices' => $options['professionnels'],
                'choice_label' => fn (User $user) => $user->getPrenom() . ' ' . $user->getNom(),
                'label' => 'Professionnel',
                'placeholder' => 'Choisir un professionnel',
            ]);
        }

        $builder
            ->add('dateRdv', DateTimeType::class, [
                'label' => 'Date et heure',
                'widget' => 'single_text',
            ])
            ->add('motif', TextareaType::class, [
                'label' => 'Motif',
                'required' => false,
                'attr' => ['rows' => 4],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'include_professionnel' => false,
            'professionnels' => [],
        ]);

        $resolver->setAllowedTypes('include_professionnel', 'bool');
        $resolver->setAllowedTypes('professionnels', 'array');
    }
}
