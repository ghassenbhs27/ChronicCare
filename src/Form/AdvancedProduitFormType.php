<?php

namespace App\Form;

use App\Entity\Produit;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
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
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Entrez le nom du produit',
                    'maxlength' => '200'
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Le nom est obligatoire']),
                    new Assert\Length([
                        'min' => 3,
                        'max' => 200,
                        'minMessage' => 'Le nom doit contenir au moins 3 caractères',
                        'maxMessage' => 'Le nom ne peut pas dépasser 200 caractères'
                    ]),
                ]
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 5,
                    'placeholder' => 'Décrivez le produit en détail (minimum 10 caractères)',
                    'maxlength' => '2000'
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'La description est obligatoire']),
                    new Assert\Length([
                        'min' => 10,
                        'max' => 2000,
                        'minMessage' => 'La description doit contenir au moins 10 caractères',
                        'maxMessage' => 'La description ne peut pas dépasser 2000 caractères'
                    ]),
                ]
            ])
            ->add('prix', MoneyType::class, [
                'label' => 'Prix (€)',
                'currency' => 'EUR',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => '0,00',
                    'step' => '0.01',
                    'min' => '0.01'
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Le prix est obligatoire']),
                    new Assert\GreaterThan([
                        'value' => 0,
                        'message' => 'Le prix doit être supérieur à 0 €'
                    ]),
                    new Assert\LessThanOrEqual([
                        'value' => 9999.99,
                        'message' => 'Le prix ne peut pas dépasser 9999,99 €'
                    ])
                ]
            ])
            ->add('stock', IntegerType::class, [
                'label' => 'Quantité en stock',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => '0',
                    'min' => '0',
                    'max' => '999999'
                ],
                'constraints' => [
                    new Assert\NotNull(['message' => 'Le stock est obligatoire']),
                    new Assert\GreaterThanOrEqual([
                        'value' => 0,
                        'message' => 'Le stock ne peut pas être négatif'
                    ]),
                    new Assert\LessThanOrEqual([
                        'value' => 999999,
                        'message' => 'Le stock ne peut pas dépasser 999999 unités'
                    ])
                ]
            ])
            ->add('categorie', ChoiceType::class, [
                'label' => 'Catégorie',
                'choices' => [
                    'Sélectionnez une catégorie' => '',
                    'Vitamines & Minéraux' => 'Vitamines & Minéraux',
                    'Suppléments alimentaires' => 'Suppléments alimentaires',
                    'Produits de bien-être' => 'Produits de bien-être',
                    'Produits bio' => 'Produits bio',
                    'Matériel médical' => 'Matériel médical',
                    'Équipement de sport' => 'Équipement de sport',
                ],
                'attr' => ['class' => 'form-control'],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'La catégorie est obligatoire']),
                ]
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Produit actif',
                'required' => false,
                'attr' => ['class' => 'form-check-input']
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Produit::class,
        ]);
    }
}
