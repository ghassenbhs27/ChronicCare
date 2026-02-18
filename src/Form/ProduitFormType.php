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
                    new Assert\Length(['max' => 200, 'maxMessage' => 'Le nom ne peut pas dépasser 200 caractères']),
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'attr' => ['class' => 'form-control', 'rows' => 4, 'placeholder' => 'Décrivez le produit'],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'La description ne peut pas être vide']),
                ],
            ])
            ->add('prix', MoneyType::class, [
                'label' => 'Prix (€)',
                'currency' => 'EUR',
                'attr' => ['class' => 'form-control', 'placeholder' => '0.00', 'step' => '0.01'],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Le prix ne peut pas être vide']),
                    new Assert\Positive(['message' => 'Le prix doit être positif']),
                ],
            ])
            ->add('stock', IntegerType::class, [
                'label' => 'Stock (Quantité)',
                'attr' => ['class' => 'form-control', 'placeholder' => '0', 'min' => 0],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Le stock ne peut pas être vide']),
                    new Assert\PositiveOrZero(['message' => 'Le stock doit être >= 0']),
                ],
            ])
            ->add('categorie', TextType::class, [
                'label' => 'Catégorie',
                'attr' => ['class' => 'form-control', 'placeholder' => 'Ex: Vitamines, Compléments...'],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'La catégorie ne peut pas être vide']),
                ],
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
