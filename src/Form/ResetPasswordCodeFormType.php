<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

class ResetPasswordCodeFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'attr' => [
                    'autocomplete' => 'email',
                    'placeholder' => 'nom@exemple.com',
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Veuillez entrer votre email',
                    ]),
                    new Email([
                        'message' => 'Email invalide',
                    ]),
                ],
            ])
            ->add('code', TextType::class, [
                'label' => 'Code a 6 chiffres',
                'attr' => [
                    'autocomplete' => 'one-time-code',
                    'placeholder' => 'Ex: 123456',
                    'maxlength' => 6,
                    'inputmode' => 'numeric',
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'Veuillez entrer le code recu par email',
                    ]),
                    new Length([
                        'min' => 6,
                        'max' => 6,
                        'exactMessage' => 'Le code doit contenir exactement 6 chiffres',
                    ]),
                    new Regex([
                        'pattern' => '/^\d{6}$/',
                        'message' => 'Le code doit contenir 6 chiffres',
                    ]),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}
