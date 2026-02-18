<?php

namespace App\Form;
use Symfony\Component\Validator\Constraints as Assert;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\File;

class ProfileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('prenom', TextType::class, [
                'label' => 'Prénom',
                'attr' => ['class' => 'form-control', 'placeholder' => 'Prénom'],
            ])
            ->add('nom', TextType::class, [
                'label' => 'Nom',
                'attr' => ['class' => 'form-control', 'placeholder' => 'Nom'],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Adresse email',
                'attr' => ['class' => 'form-control', 'placeholder' => 'nom@exemple.com'],
            ])
            ->add('genre', ChoiceType::class, [
                'label' => 'Genre',
                'choices' => [
                    'Homme' => 'Homme',
                    'Femme' => 'Femme',
                ],
                'attr' => ['class' => 'form-select'],
            ])
            ->add('mainRole', ChoiceType::class, [
                'label' => 'Vous êtes',
                'mapped' => false,
                'choices' => [
                'Patient' => 'ROLE_PATIENT',
                'Coach' => 'ROLE_COACH',
                'Nutritionniste' => 'ROLE_NUTRITIONNISTE',
                 ],
                'placeholder' => 'Sélectionnez',
                'attr' => ['class' => 'form-select'],
            ])
            // mot de passe optionnel
           ->add('newPassword', RepeatedType::class, [
    'type' => PasswordType::class,
    'mapped' => false,
    'required' => false,
    'first_options' => [
        'label' => 'Nouveau mot de passe (optionnel)',
        'attr' => ['class' => 'form-control', 'placeholder' => 'Laisser vide si pas de changement'],
    ],
    'second_options' => [
        'label' => 'Confirmer le nouveau mot de passe',
        'attr' => ['class' => 'form-control', 'placeholder' => 'Confirmer'],
    ],
    'invalid_message' => 'Les mots de passe doivent correspondre.',
    'constraints' => [
        new Assert\When([
            'expression' => 'this != null and this != ""',
            'constraints' => [
                new Assert\Length([
                    'min' => 8,
                    'minMessage' => 'Le mot de passe doit contenir au moins {{ limit }} caractères.',
                    'max' => 4096,
                ]),
            ],
        ]),
    ],
])
            ->add('photoProfil', FileType::class, [
                'label' => 'Photo de profil (optionnel)',
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'accept' => 'image/png,image/jpeg,image/webp',
                ],
                'constraints' => [
                    new File([
                        'maxSize' => '4M',
                        'mimeTypes' => ['image/jpeg', 'image/png', 'image/webp'],
                        'mimeTypesMessage' => 'Veuillez importer une image JPG, PNG ou WEBP.',
                    ]),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
