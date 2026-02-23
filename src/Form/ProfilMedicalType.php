<?php

namespace App\Form;

use App\Entity\ProfilMedical;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProfilMedicalType extends AbstractType
{
    // Conditions chroniques courantes
    private const CONDITIONS = [
        'Diabète type 1' => 'diabète type 1',
        'Diabète type 2' => 'diabète type 2',
        'Hypertension' => 'hypertension',
        'Cholestérol' => 'cholestérol',
        'Asthme' => 'asthme',
        'Insuffisance rénale' => 'insuffisance rénale',
        'Insuffisance cardiaque' => 'insuffisance cardiaque',
        'Arthrose' => 'arthrose',
        'Ostéoporose' => 'ostéoporose',
        'Thyroïde' => 'thyroïde',
        'BPCO' => 'bpco',
        'Fibromyalgie' => 'fibromyalgie',
        'Maladie de Crohn' => 'crohn',
        'Sclérose en plaques' => 'sclérose en plaques',
    ];

    // Allergies courantes
    private const ALLERGIES = [
        'Lactose' => 'lactose',
        'Gluten' => 'gluten',
        'Arachide' => 'arachide',
        'Fruits à coque' => 'fruits à coque',
        'Soja' => 'soja',
        'Œuf' => 'œuf',
        'Poisson' => 'poisson',
        'Crustacés' => 'crustacés',
        'Sulfites' => 'sulfites',
        'Pénicilline' => 'pénicilline',
        'Aspirine' => 'aspirine',
        'Ibuprofène' => 'ibuprofène',
    ];

    // Objectifs santé
    private const OBJECTIFS = [
        'Perte de poids' => 'perte_poids',
        'Prise de masse' => 'prise_masse',
        'Plus d\'énergie' => 'energie',
        'Meilleur sommeil' => 'sommeil',
        'Renforcer l\'immunité' => 'immunite',
        'Réduire le stress' => 'stress',
        'Améliorer la digestion' => 'digestion',
        'Santé cardiovasculaire' => 'cardiovasculaire',
        'Santé articulaire' => 'articulaire',
        'Concentration/Mémoire' => 'concentration',
    ];

    // Régimes alimentaires
    private const REGIMES = [
        'Aucun régime particulier' => null,
        'Végétarien' => 'vegetarien',
        'Végan' => 'vegan',
        'Sans gluten' => 'sans_gluten',
        'Sans lactose' => 'sans_lactose',
        'Halal' => 'halal',
        'Casher' => 'casher',
        'Cétogène/Keto' => 'cetogene',
        'Paléo' => 'paleo',
    ];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('conditionsChroniques', ChoiceType::class, [
                'label' => 'Conditions chroniques',
                'choices' => self::CONDITIONS,
                'multiple' => true,
                'expanded' => true,
                'required' => false,
                'help' => 'Sélectionnez toutes les conditions qui vous concernent',
                'attr' => ['class' => 'checkbox-grid']
            ])
            ->add('allergies', ChoiceType::class, [
                'label' => 'Allergies connues',
                'choices' => self::ALLERGIES,
                'multiple' => true,
                'expanded' => true,
                'required' => false,
                'help' => 'Sélectionnez vos allergies pour éviter les produits incompatibles',
                'attr' => ['class' => 'checkbox-grid']
            ])
            ->add('medicamentsActuels', ChoiceType::class, [
                'label' => 'Médicaments actuels',
                'choices' => [
                    'Metformine' => 'metformine',
                    'Insuline' => 'insuline',
                    'Aspirine' => 'aspirine',
                    'Ibuprofène' => 'ibuprofène',
                    'Paracétamol' => 'paracétamol',
                    'Oméprazole' => 'oméprazole',
                    'Simvastatine' => 'simvastatine',
                    'Amlodipine' => 'amlodipine',
                    'Lisinopril' => 'lisinopril',
                    'Warfarine' => 'warfarine',
                    'Levothyrox' => 'levothyrox',
                    'Ventoline' => 'ventoline',
                ],
                'multiple' => true,
                'expanded' => true,
                'required' => false,
                'help' => 'Nous vérifierons les interactions médicamenteuses',
                'attr' => ['class' => 'checkbox-grid']
            ])
            ->add('objectifsSante', ChoiceType::class, [
                'label' => 'Vos objectifs santé',
                'choices' => self::OBJECTIFS,
                'multiple' => true,
                'expanded' => true,
                'required' => false,
                'help' => 'Nous vous recommanderons des produits adaptés',
                'attr' => ['class' => 'checkbox-grid']
            ])
            ->add('regimeAlimentaire', ChoiceType::class, [
                'label' => 'Régime alimentaire',
                'choices' => self::REGIMES,
                'required' => false,
                'placeholder' => 'Sélectionnez...',
            ])
            ->add('trancheAge', ChoiceType::class, [
                'label' => 'Tranche d\'âge',
                'choices' => [
                    '18-30 ans' => '18-30',
                    '31-45 ans' => '31-45',
                    '46-60 ans' => '46-60',
                    '60 ans et plus' => '60+',
                ],
                'required' => false,
                'placeholder' => 'Sélectionnez...',
            ])
            ->add('niveauActivite', ChoiceType::class, [
                'label' => 'Niveau d\'activité physique',
                'choices' => [
                    'Sédentaire (peu ou pas d\'exercice)' => 'sedentaire',
                    'Léger (1-2 séances/semaine)' => 'leger',
                    'Modéré (3-4 séances/semaine)' => 'modere',
                    'Actif (5+ séances/semaine)' => 'actif',
                    'Sportif (entraînement intensif)' => 'sportif',
                ],
                'required' => false,
                'placeholder' => 'Sélectionnez...',
            ])
            ->add('notesMedicales', TextareaType::class, [
                'label' => 'Notes additionnelles',
                'required' => false,
                'attr' => [
                    'rows' => 4,
                    'placeholder' => 'Informations complémentaires sur votre état de santé...'
                ],
                'help' => 'Ces informations aideront à personnaliser vos recommandations'
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ProfilMedical::class,
        ]);
    }
}
