<?php

namespace App\Form;

use App\Entity\Etat;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class EtatFrontType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('type', TextType::class, [
                'trim' => true,
                'label' => 'Type',
                'attr' => ['class' => 'form-control'],
                'label_attr' => ['class' => 'form-label fw-semibold'],
            ])
            ->add('valeur', NumberType::class, [
                'label' => 'Valeur',
                'scale' => 2,
                'attr' => ['class' => 'form-control', 'step' => '0.01'],
                'label_attr' => ['class' => 'form-label fw-semibold'],
            ])
            ->add('unite', TextType::class, [
                'trim' => true,
                'label' => 'Unité',
                'attr' => ['class' => 'form-control'],
                'label_attr' => ['class' => 'form-label fw-semibold'],
            ])
            ->add('dateReleve', DateType::class, [
                'label' => 'Date relevé',
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control'],
                'label_attr' => ['class' => 'form-label fw-semibold'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Etat::class]);
    }
}
