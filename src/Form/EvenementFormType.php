<?php

namespace App\Form;

use App\Entity\Evenement;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class EvenementFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('titre', TextType::class, [
                'trim' => true,
                'attr' => [
                    'maxlength' => 200,
                ],
            ])
            ->add('description', TextareaType::class, [
                'trim' => true,
                'attr' => [
                    'maxlength' => 5000,
                ],
            ])
            ->add('dateDebut', DateTimeType::class, [
                'label' => 'Date debut',
                'widget' => 'single_text',
                'invalid_message' => 'Veuillez saisir une date et une heure valides.',
            ])
            ->add('dateFin', DateTimeType::class, [
                'label' => 'Date fin',
                'widget' => 'single_text',
                'invalid_message' => 'Veuillez saisir une date et une heure valides.',
            ])
            ->add('lieu', TextType::class, [
                'trim' => true,
                'attr' => [
                    'maxlength' => 255,
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Evenement::class,
        ]);
    }
}
