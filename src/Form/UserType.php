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
            ->add('email', EmailType::class, [
                'label' => 'Adresse email',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'nom@exemple.com'
                ],
            ])
            ->add('nom', TextType::class, [
                'label' => 'Nom',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Dupont'
                ],
            ])
            ->add('prenom', TextType::class, [
                'label' => 'Prénom',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Jean'
                ],
            ])
            ->add('genre', ChoiceType::class, [
                'label' => 'Genre',
                'choices' => [
                    'Sélectionnez' => '',
                    'Homme' => 'Homme',
                    'Femme' => 'Femme',
                ],
                'attr' => [
                    'class' => 'form-select'
                ],
            ]);

        // ✅ Champ password pour l'inscription (obligatoire avec validation)
        if (!$isEdit) {
            $builder->add('password', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false, // ✅ Ne remplit pas automatiquement l'entité
                'first_options' => [
                    'label' => 'Mot de passe',
                    'attr' => [
                        'class' => 'form-control',
                        'placeholder' => 'Minimum 8 caractères'
                    ],
                    'constraints' => [
                        // ✅ VALIDATION ICI (dans le formulaire)
                        new NotBlank([
                            'message' => 'Le mot de passe est obligatoire.',
                        ]),
                        new Length([
                            'min' => 8,
                            'minMessage' => 'Le mot de passe doit contenir au moins {{ limit }} caractères.',
                            'max' => 4096,
                        ]),
                    ],
                ],
                'second_options' => [
                    'label' => 'Confirmer le mot de passe',
                    'attr' => [
                        'class' => 'form-control',
                        'placeholder' => 'Confirmez votre mot de passe'
                    ],
                ],
                'invalid_message' => 'Les mots de passe doivent correspondre.',
            ]);
        } else {
            // Champ password pour l'édition (optionnel)
            $builder->add('password', PasswordType::class, [
                'label' => 'Nouveau mot de passe (optionnel)',
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Laisser vide pour ne pas changer'
                ],
            ]);

            // Champ roles pour l'édition (admin)
            $builder->add('roles', ChoiceType::class, [
                'label' => 'Rôles',
                'choices' => [
                    'Patient' => 'ROLE_PATIENT',
                    'Nutritionniste' => 'ROLE_NUTRITIONIST',
                    'Coach' => 'ROLE_COACH',
                    'Administrateur' => 'ROLE_ADMIN',
                ],
                'multiple' => true,
                'expanded' => true,
                'attr' => [
                    'class' => 'form-check-input'
                ],
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