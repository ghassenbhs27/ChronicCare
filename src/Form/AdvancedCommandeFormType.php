<?php

namespace App\Form;

use App\Entity\Commande;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class AdvancedCommandeFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('numeroCommande', TextType::class, [
                'label' => 'Numéro de commande',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: CMD-2025-001',
                    'maxlength' => '50',
                    'pattern' => '^[A-Z0-9\-]+$'
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Le numéro de commande est obligatoire']),
                    new Assert\Length([
                        'min' => 3,
                        'max' => 50,
                        'minMessage' => 'Le numéro doit contenir au moins 3 caractères',
                        'maxMessage' => 'Le numéro ne peut pas dépasser 50 caractères'
                    ]),
                    new Assert\Regex([
                        'pattern' => '/^[A-Z0-9\-]+$/',
                        'message' => 'Le numéro doit contenir uniquement des lettres majuscules, des chiffres et des tirets'
                    ])
                ]
            ])
            ->add('statut', ChoiceType::class, [
                'label' => 'Statut de la commande',
                'choices' => [
                    'En attente' => 'en_attente',
                    'Validée' => 'validée',
                    'Expédiée' => 'expédiée',
                    'Livrée' => 'livrée',
                    'Annulée' => 'annulée',
                ],
                'attr' => ['class' => 'form-control'],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Le statut est obligatoire']),
                    new Assert\Choice([
                        'choices' => ['en_attente', 'validée', 'expédiée', 'livrée', 'annulée'],
                        'message' => 'Le statut sélectionné n\'est pas valide'
                    ])
                ]
            ])
            ->add('adresse', TextareaType::class, [
                'label' => 'Adresse de livraison',
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 4,
                    'placeholder' => 'Ex: 123 Rue de Paris, 75001 Paris',
                    'maxlength' => '500'
                ],
                'required' => false,
                'empty_data' => null,
                'constraints' => [
                    new Assert\Length([
                        'max' => 500,
                        'maxMessage' => 'L\'adresse ne peut pas dépasser 500 caractères'
                    ]),
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Commande::class,
        ]);
    }
}
