<?php

namespace App\Form;

use App\Entity\Produit;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class ProduitFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom du produit',
                'attr' => ['class' => 'form-control', 'placeholder' => 'Entrez le nom du produit', 'maxlength' => 200],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Le nom ne peut pas être vide']),
                    new Assert\Length(['max' => 200]),
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'attr' => ['class' => 'form-control', 'rows' => 4],
                'constraints' => [new Assert\NotBlank()],
            ])
            ->add('prix', MoneyType::class, [
                'label' => 'Prix (€)',
                'currency' => 'EUR',
                'attr' => ['class' => 'form-control', 'step' => '0.01'],
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Positive(),
                ],
            ])
            ->add('stock', IntegerType::class, [
                'label' => 'Stock',
                'attr' => ['class' => 'form-control', 'min' => 0],
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\PositiveOrZero(),
                ],
            ])
            ->add('categorie', TextType::class, [
                'label' => 'Catégorie',
                'attr' => ['class' => 'form-control', 'placeholder' => 'Ex: Vitamines, Compléments...'],
                'constraints' => [new Assert\NotBlank()],
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Produit actif (publié)',
                'required' => false,
                'attr' => ['class' => 'form-check-input'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Produit::class]);
    }
}
