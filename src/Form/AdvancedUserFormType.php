<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class AdvancedUserFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'Adresse email',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'utilisateur@exemple.com',
                    'maxlength' => '180'
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'L\'email est obligatoire']),
                    new Assert\Email(['message' => 'L\'email n\'est pas valide: {{ value }}']),
                    new Assert\Length([
                        'max' => 180,
                        'maxMessage' => 'L\'email ne peut pas dépasser 180 caractères'
                    ])
                ]
            ])
            ->add('nom', TextType::class, [
                'label' => 'Nom',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Entrez votre nom',
                    'maxlength' => '50'
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Le nom est obligatoire']),
                    new Assert\Length([
                        'min' => 2,
                        'max' => 50,
                        'minMessage' => 'Le nom doit contenir au moins 2 caractères',
                        'maxMessage' => 'Le nom ne peut pas dépasser 50 caractères'
                    ]),
                    new Assert\Regex([
                        'pattern' => "/^[a-zA-Z\s\-àâäéèêëïîôöùûüœæçÀÂÄÉÈÊËÏÎÔÖÙÛÜŒÆÇ']+$/",
                        'message' => 'Le nom ne contient que des lettres, espaces et tirets'
                    ])
                ]
            ])
            ->add('prenom', TextType::class, [
                'label' => 'Prénom',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Entrez votre prénom',
                    'maxlength' => '50'
                ],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Le prénom est obligatoire']),
                    new Assert\Length([
                        'min' => 2,
                        'max' => 50,
                        'minMessage' => 'Le prénom doit contenir au moins 2 caractères',
                        'maxMessage' => 'Le prénom ne peut pas dépasser 50 caractères'
                    ]),
                    new Assert\Regex([
                        'pattern' => "/^[a-zA-Z\s\-àâäéèêëïîôöùûüœæçÀÂÄÉÈÊËÏÎÔÖÙÛÜŒÆÇ']+$/",
                        'message' => 'Le prénom ne contient que des lettres, espaces et tirets'
                    ])
                ]
            ])
            ->add('telephone', TelType::class, [
                'label' => 'Téléphone',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => '06 12 34 56 78 ou +33612345678',
                    'maxlength' => '20'
                ],
                'required' => false,
                'empty_data' => null,
                'constraints' => [
                    new Assert\Length([
                        'min' => 10,
                        'max' => 20,
                        'minMessage' => 'Le numéro doit contenir au moins 10 chiffres',
                        'maxMessage' => 'Le numéro ne peut pas dépasser 20 chiffres'
                    ]),
                    new Assert\Regex([
                        'pattern' => '/^(\+33|0)[1-9](\d{8})$|^$/',
                        'message' => 'Le téléphone doit être au format français valide (06 12 34 56 78 ou +33612345678)'
                    ])
                ]
            ])
            ->add('genre', ChoiceType::class, [
                'label' => 'Genre',
                'choices' => [
                    'Non spécifié' => '',
                    'Homme' => 'Homme',
                    'Femme' => 'Femme',
                ],
                'attr' => ['class' => 'form-control'],
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Le genre est obligatoire']),
                    new Assert\Choice([
                        'choices' => ['Homme', 'Femme'],
                        'message' => 'Le genre doit être "Homme" ou "Femme"'
                    ])
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
