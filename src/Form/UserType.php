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
    /** Rôles proposés en admin : Patient, Coach, Administrateur uniquement. */
    private const ROLE_CHOICES = [
        'Patient' => 'ROLE_PATIENT',
        'Coach' => 'ROLE_COACH',
        'Administrateur' => 'ROLE_ADMIN',
    ];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isEdit = $options['is_edit'];

        $builder
            ->add('prenom', TextType::class, [
                'label' => 'First Name',
                'attr' => ['class' => 'form-control', 'placeholder' => ''],
            ])
            ->add('nom', TextType::class, [
                'label' => 'Last Name',
                'attr' => ['class' => 'form-control', 'placeholder' => ''],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'attr' => ['class' => 'form-control', 'placeholder' => ''],
            ])
            ->add('telephone', TextType::class, [
                'label' => 'Phone',
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => ''],
            ])
            ->add('genre', ChoiceType::class, [
                'label' => 'Genre',
                'choices' => [
                    'Homme' => 'Homme',
                    'Femme' => 'Femme',
                ],
                'placeholder' => 'Sélectionnez',
                'attr' => ['class' => 'form-select'],
            ])
            ->add('isActive', ChoiceType::class, [
                'label' => 'Status',
                'choices' => [
                    'Active' => true,
                    'Inactive' => false,
                ],
                'attr' => ['class' => 'form-select'],
            ]);
        // Role: en édition = champ unique (mapped false), en création = accountType
        if ($isEdit) {
            $builder->add('role', ChoiceType::class, [
                'label' => 'Role',
                'mapped' => false,
                'choices' => self::ROLE_CHOICES,
                'attr' => ['class' => 'form-select'],
            ]);
        } else {
            $builder->add('accountType', ChoiceType::class, [
                'label' => 'Role',
                'mapped' => false,
                'required' => true,
                'choices' => self::ROLE_CHOICES,
                'placeholder' => 'Sélectionnez',
                'attr' => ['class' => 'form-select'],
                'constraints' => [new NotBlank(['message' => 'Veuillez choisir un rôle.'])],
            ]);
        }

        if (!$isEdit) {
            $builder->add('password', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'first_options' => [
                    'label' => 'Password',
                    'attr' => ['class' => 'form-control', 'placeholder' => ''],
                    'constraints' => [
                        new NotBlank(['message' => 'Le mot de passe est obligatoire.']),
                        new Length(['min' => 8, 'minMessage' => 'Le mot de passe doit contenir au moins {{ limit }} caractères.', 'max' => 4096]),
                    ],
                ],
                'second_options' => [
                    'label' => 'Confirm password',
                    'attr' => ['class' => 'form-control', 'placeholder' => ''],
                ],
                'invalid_message' => 'Les mots de passe doivent correspondre.',
            ]);
        } else {
            $builder->add('password', PasswordType::class, [
                'label' => 'New password (optional)',
                'mapped' => false,
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => 'Leave blank to keep current'],
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
