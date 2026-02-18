<?php

namespace App\Form;

use App\Entity\Etat;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class EtatType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('type', TextType::class, [
                'attr' => ['class' => 'form-control'],
                'label_attr' => ['class' => 'form-label fw-semibold'],
            ])
            ->add('valeur', NumberType::class, [
                'attr' => ['class' => 'form-control', 'step' => '0.01'],
                'label_attr' => ['class' => 'form-label fw-semibold'],
            ])
            ->add('unite', TextType::class, [
                'attr' => ['class' => 'form-control'],
                'label_attr' => ['class' => 'form-label fw-semibold'],
            ])
            ->add('dateReleve', DateType::class, [
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control'],
                'label_attr' => ['class' => 'form-label fw-semibold'],
            ])
            ->add('utilisateur', EntityType::class, [
                'class' => User::class,
                'required' => false,
                'choice_label' => fn (User $u) => $u->getFullName(),
                'attr' => ['class' => 'form-select'],
                'label_attr' => ['class' => 'form-label fw-semibold'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Etat::class]);
    }
}
