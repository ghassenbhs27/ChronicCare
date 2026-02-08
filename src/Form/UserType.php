<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isEdit = $options['is_edit'];

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
                'placeholder' => 'Sélectionnez',
                'attr' => ['class' => 'form-select'],
            ]);

        // ✅ INSCRIPTION
        if (!$isEdit) {

            // 1) Mot de passe (inscription)
            $builder->add('password', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'first_options' => [
                    'label' => false,
                    'attr' => ['class' => 'form-control', 'placeholder' => 'Mot de passe (min. 8 caractères)'],
                    'constraints' => [
                        new NotBlank(['message' => 'Le mot de passe est obligatoire.']),
                        new Length([
                            'min' => 8,
                            'minMessage' => 'Le mot de passe doit contenir au moins {{ limit }} caractères.',
                            'max' => 4096,
                        ]),
                    ],
                ],
                'second_options' => [
                    'label' => false,
                    'attr' => ['class' => 'form-control', 'placeholder' => 'Confirmer le mot de passe'],
                ],
                'invalid_message' => 'Les mots de passe doivent correspondre.',
            ]);

            // 2) ✅ Un seul champ rôle : accountType
            $builder->add('accountType', ChoiceType::class, [
                'mapped' => false,
                'label' => false,
                'required' => true,
                'placeholder' => 'Sélectionnez votre profil',
                'choices' => [
                    'Patient' => 'ROLE_PATIENT',
                    'Coach' => 'ROLE_COACH',
                    'Nutritionniste' => 'ROLE_NUTRITIONIST',
                    'Administrateur' => 'ROLE_ADMIN',
                ],
                'attr' => ['class' => 'form-select'],
                'constraints' => [
                    new NotBlank(['message' => 'Veuillez choisir un type de compte.']),
                ],
            ]);

        } 
        // ✅ ÉDITION (ADMIN)
        else {
            $builder
                ->add('password', PasswordType::class, [
                    'label' => 'Nouveau mot de passe (optionnel)',
                    'mapped' => false,
                    'required' => false,
                    'attr' => ['class' => 'form-control', 'placeholder' => 'Laisser vide pour ne pas changer'],
                ])
                ->add('roles', ChoiceType::class, [
                    'label' => 'Rôles',
                    'choices' => [
                        'Patient' => 'ROLE_PATIENT',
                        'Nutritionniste' => 'ROLE_NUTRITIONIST',
                        'Coach' => 'ROLE_COACH',
                        'Administrateur' => 'ROLE_ADMIN',
                    ],
                    'multiple' => true,
                    'expanded' => true,
                ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'is_edit' => false,
        ]);
    }
}
