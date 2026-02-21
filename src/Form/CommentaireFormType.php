<?php

namespace App\Form;

use App\Entity\Commentaire;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\ColorType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

class CommentaireFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('contenu', TextareaType::class, [
                'label' => 'Votre commentaire',
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 5,
                    'placeholder' => 'Partagez votre avis...',
                ],
                'constraints' => [
                    new NotBlank(),
                    new Length(['min' => 3, 'max' => 3000]),
                ],
            ])
            ->add('fontStyle', ChoiceType::class, [
                'label' => 'Style de texte',
                'choices' => [
                    'Normal' => Commentaire::FONT_STYLE_NORMAL,
                    'Gras' => Commentaire::FONT_STYLE_BOLD,
                    'Italique' => Commentaire::FONT_STYLE_ITALIC,
                    'Souligné' => Commentaire::FONT_STYLE_UNDERLINE,
                    'Barré' => Commentaire::FONT_STYLE_STRIKETHROUGH,
                ],
                'required' => false,
                'attr' => ['class' => 'form-select'],
                'constraints' => [
                    new Choice(['choices' => Commentaire::FONT_STYLES]),
                ],
            ])
            ->add('fontSize', ChoiceType::class, [
                'label' => 'Taille de police',
                'choices' => [
                    'Petit' => Commentaire::FONT_SIZE_SMALL,
                    'Moyen' => Commentaire::FONT_SIZE_MEDIUM,
                    'Grand' => Commentaire::FONT_SIZE_LARGE,
                ],
                'required' => false,
                'attr' => ['class' => 'form-select'],
                'constraints' => [
                    new Choice(['choices' => Commentaire::FONT_SIZES]),
                ],
            ])
            ->add('textColor', ColorType::class, [
                'label' => 'Couleur du texte',
                'required' => false,
                'attr' => ['class' => 'form-control', 'type' => 'color'],
                'constraints' => [
                    new Regex([
                        'pattern' => '/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/',
                        'message' => 'Couleur invalide',
                    ]),
                ],
            ])
            ->add('backgroundColor', ColorType::class, [
                'label' => 'Couleur de fond',
                'required' => false,
                'attr' => ['class' => 'form-control', 'type' => 'color'],
                'constraints' => [
                    new Regex([
                        'pattern' => '/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/',
                        'message' => 'Couleur invalide',
                    ]),
                ],
            ])
            ->add('formattedText', TextareaType::class, [
                'label' => 'Texte formaté',
                'required' => false,
                'attr' => ['class' => 'form-control d-none'],
            ])
            ->add('emojis', HiddenType::class, [
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Commentaire::class,
        ]);
    }
}
