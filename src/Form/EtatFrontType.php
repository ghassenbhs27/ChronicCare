<?php

namespace App\Form;

use App\Entity\Etat;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class EtatFrontType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $etat = $options['data'] ?? null;
        $id = $etat instanceof Etat ? $etat->getId() : null;

        $builder
            ->add('id', IntegerType::class, [
                'label' => 'ID',
                'mapped' => false,
                'required' => false,
                'disabled' => true,
                'data' => $id,
                'attr' => ['class' => 'form-control'],
                'label_attr' => ['class' => 'form-label fw-semibold'],
            ])
            ->add('traitementEnCours', TextType::class, [
                'trim' => true,
                'label' => 'Traitement en cours',
                'required' => false,
                'attr' => ['class' => 'form-control'],
                'label_attr' => ['class' => 'form-label fw-semibold'],
            ])
            ->add('remarquesCliniques', TextareaType::class, [
                'label' => 'Remarques cliniques',
                'required' => false,
                'attr' => ['class' => 'form-control', 'rows' => 4],
                'label_attr' => ['class' => 'form-label fw-semibold'],
            ])
            ->add('temperatureCorporelle', ChoiceType::class, [
                'label' => 'Temperature corporelle (deg C)',
                'required' => false,
                'placeholder' => 'Choisir une temperature',
                'choices' => array_combine(Etat::TEMPERATURE_CORPORELLE_CHOICES, Etat::TEMPERATURE_CORPORELLE_CHOICES),
                'attr' => ['class' => 'form-select'],
                'label_attr' => ['class' => 'form-label fw-semibold'],
            ])
            ->add('niveauHydratation', ChoiceType::class, [
                'label' => 'Hydratation (L/jour)',
                'required' => false,
                'placeholder' => 'Choisir un niveau',
                'choices' => array_combine(Etat::NIVEAU_HYDRATATION_CHOICES, Etat::NIVEAU_HYDRATATION_CHOICES),
                'attr' => ['class' => 'form-select'],
                'label_attr' => ['class' => 'form-label fw-semibold'],
            ])
            ->add('dateReleve', DateType::class, [
                'label' => 'Date relevé',
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control'],
                'label_attr' => ['class' => 'form-label fw-semibold'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Etat::class]);
    }
}


