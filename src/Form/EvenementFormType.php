<?php

namespace App\Form;

use App\Entity\Evenement;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class EvenementFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('titre')
            ->add('description')
            ->add('dateDebut', DateTimeType::class, [
                'label' => 'Date début',
                'widget' => 'single_text',
                'invalid_message' => 'Veuillez saisir une date et une heure valides.',
            ])
            ->add('dateFin', DateTimeType::class, [
                'label' => 'Date fin',
                'widget' => 'single_text',
                'invalid_message' => 'Veuillez saisir une date et une heure valides.',
            ])
            ->add('lieu')
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Evenement::class,
        ]);
    }
}
