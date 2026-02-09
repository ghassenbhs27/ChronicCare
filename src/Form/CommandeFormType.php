<?php

namespace App\Form;

use App\Entity\Commande;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class CommandeFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('numeroCommande', TextType::class, [
                'label' => 'Numéro de commande',
                'attr' => [
                    'class' => 'form-control',
                    'readonly' => true,
                ],
                'disabled' => true,
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
                'attr' => [
                    'class' => 'form-control',
                ],
                'constraints' => [
                    new Assert\Choice([
                        'choices' => ['en_attente', 'validée', 'expédiée', 'livrée', 'annulée'],
                        'message' => 'Le statut sélectionné est invalide',
                    ]),
                ],
            ])
            ->add('total', TextType::class, [
                'label' => 'Total (€)',
                'attr' => [
                    'class' => 'form-control',
                    'readonly' => true,
                ],
                'disabled' => true,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Commande::class,
        ]);
    }
}
