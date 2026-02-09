<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SearchFormType extends AbstractType
{
    private $categories = [];

    public function __construct(array $categories = [])
    {
        $this->categories = $categories;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('keyword', TextType::class, [
                'label' => 'Rechercher un produit',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Nom ou description...',
                ],
            ])
            ->add('categorie', ChoiceType::class, [
                'label' => 'Catégorie',
                'required' => false,
                'placeholder' => '-- Toutes les catégories --',
                'choices' => $options['categories_choices'] ?? [],
                'attr' => [
                    'class' => 'form-control',
                ],
            ])
            ->add('priceMin', MoneyType::class, [
                'label' => 'Prix minimum (€)',
                'currency' => 'EUR',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => '0.00',
                ],
            ])
            ->add('priceMax', MoneyType::class, [
                'label' => 'Prix maximum (€)',
                'currency' => 'EUR',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => '999.99',
                ],
            ])
            ->add('sort', ChoiceType::class, [
                'label' => 'Trier par',
                'required' => false,
                'placeholder' => '-- Sélectionner le tri --',
                'choices' => [
                    'Nom (A-Z)' => 'nom_asc',
                    'Nom (Z-A)' => 'nom_desc',
                    'Prix (croissant)' => 'prix_asc',
                    'Prix (décroissant)' => 'prix_desc',
                    'Plus récent' => 'recent',
                ],
                'attr' => [
                    'class' => 'form-control',
                ],
            ])
            ->add('search', SubmitType::class, [
                'label' => 'Rechercher',
                'attr' => [
                    'class' => 'btn btn-primary',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'categories_choices' => [],
            'csrf_protection' => true,
            'csrf_field_name' => '_token',
            'csrf_token_id' => 'search_form',
        ]);
    }
}
