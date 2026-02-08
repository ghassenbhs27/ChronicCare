<?php

namespace App\Form;

use App\Entity\Activite;
use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ActiviteType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
{
    $builder
        ->add('type', TextType::class, [
            'trim' => true,
            'attr' => ['class' => 'form-control']
        ])
        ->add('duree', IntegerType::class, [
            'attr' => ['class' => 'form-control']
        ])
        ->add('calories', IntegerType::class, [
            'attr' => ['class' => 'form-control']
        ])
        ->add('dateActivite', DateType::class, [
            'widget' => 'single_text',
            'input' => 'datetime_immutable',
            'attr' => ['class' => 'form-control']
        ])
        ->add('notes', TextareaType::class, [
            'required' => false,
            'trim' => true,
            'attr' => ['class' => 'form-control', 'rows' => 4]
        ]);
}


    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Activite::class]);
    }
}
