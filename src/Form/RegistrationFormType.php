<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('prenom', TextType::class, [
                'label' => 'Prenom',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Prenom',
                    'minlength' => 2,
                    'maxlength' => 50,
                    'autocomplete' => 'given-name',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Le prenom est obligatoire.']),
                    new Length(['min' => 2, 'max' => 50]),
                ],
            ])
            ->add('nom', TextType::class, [
                'label' => 'Nom',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Nom',
                    'minlength' => 2,
                    'maxlength' => 50,
                    'autocomplete' => 'family-name',
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Le nom est obligatoire.']),
                    new Length(['min' => 2, 'max' => 50]),
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Adresse email',
                    'autocomplete' => 'email',
                ],
                'constraints' => [
                    new NotBlank(['message' => "L'email est obligatoire."]),
                ],
            ])
            ->add('telephone', TelType::class, [
                'label' => 'Telephone',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Telephone',
                    'autocomplete' => 'tel',
                    'maxlength' => 20,
                ],
            ])
            ->add('genre', ChoiceType::class, [
                'label' => 'Genre',
                'choices' => [
                    'Homme' => 'Homme',
                    'Femme' => 'Femme',
                ],
                'placeholder' => 'Selectionnez',
                'attr' => ['class' => 'form-select'],
                'constraints' => [new NotBlank(['message' => 'Le genre est obligatoire.'])],
            ])
            ->add('requestedRole', ChoiceType::class, [
                'label' => 'Role souhaite',
                'mapped' => false,
                'choices' => [
                    'Patient' => 'ROLE_PATIENT',
                    'Coach' => 'ROLE_COACH',
                    'Nutritionniste' => 'ROLE_NUTRITIONNISTE',
                ],
                'placeholder' => 'Choisissez votre role',
                'attr' => ['class' => 'form-select'],
                'constraints' => [new NotBlank(['message' => 'Veuillez choisir un role.'])],
            ])
            ->add('profileImage', FileType::class, [
                'label' => 'Photo de profil',
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'accept' => 'image/jpeg,image/png,image/gif,image/webp',
                ],
                'help' => 'Formats autorises: JPEG, PNG, GIF, WebP (Max 5 MB)',
            ])
            ->add('password', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'first_options' => [
                    'label' => 'Mot de passe',
                    'attr' => [
                        'class' => 'form-control',
                        'placeholder' => 'Mot de passe (min. 8 caracteres)',
                        'minlength' => 8,
                        'maxlength' => 4096,
                        'autocomplete' => 'new-password',
                    ],
                    'constraints' => [
                        new NotBlank(['message' => 'Le mot de passe est obligatoire.']),
                        new Length(['min' => 8, 'minMessage' => 'Le mot de passe doit contenir au moins {{ limit }} caracteres.']),
                    ],
                ],
                'second_options' => [
                    'label' => 'Confirmer le mot de passe',
                    'attr' => [
                        'class' => 'form-control',
                        'placeholder' => 'Confirmer le mot de passe',
                        'minlength' => 8,
                        'maxlength' => 4096,
                        'autocomplete' => 'new-password',
                    ],
                ],
                'invalid_message' => 'Les mots de passe doivent correspondre.',
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
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
