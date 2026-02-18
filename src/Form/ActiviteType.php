<?php

namespace App\Form;

use App\Entity\Activite;
use App\Entity\Etat;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ActiviteType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $activite = $options['data'] ?? null;
        $id = $activite instanceof Activite ? $activite->getId() : null;

        $builder
            ->add('id', IntegerType::class, [
                'label' => 'ID',
                'mapped' => false,
                'required' => false,
                'disabled' => true,
                'data' => $id,
                'attr' => ['class' => 'form-control'],
            ])
            ->add('type', ChoiceType::class, [
                'placeholder' => 'Choisir une activite',
                'choices' => [
                    'Marche' => 'marche',
                    'Course' => 'course',
                    'Velo' => 'velo',
                ],
                'attr' => ['class' => 'form-select'],
            ])
            ->add('duree', IntegerType::class, [
                'attr' => ['class' => 'form-control'],
            ])
            ->add('calories', IntegerType::class, [
                'attr' => ['class' => 'form-control'],
            ])
            ->add('distanceKm', NumberType::class, [
                'required' => false,
                'scale' => 2,
                'attr' => ['class' => 'form-control', 'step' => '0.1'],
            ])
            ->add('heuresRepos', IntegerType::class, [
                'required' => false,
                'attr' => ['class' => 'form-control', 'min' => 0],
            ])
            ->add('dateActivite', DateType::class, [
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('etat', EntityType::class, [
                'class' => Etat::class,
                'choices' => $options['etats'],
                'required' => false,
                'placeholder' => 'Selectionner un etat (optionnel)',
                'choice_label' => static function (Etat $etat): string {
                    $date = $etat->getDateReleve()?->format('d/m/Y') ?? '';
                    $temperature = $etat->getTemperatureCorporelle();
                    $hydration = $etat->getNiveauHydratation();
                    $traitement = trim((string) ($etat->getTraitementEnCours() ?? ''));
                    $remarques = trim((string) ($etat->getRemarquesCliniques() ?? ''));

                    $base = 'Etat';
                    if ($traitement !== '') {
                        $base .= sprintf(' | Trait.: %s', $traitement);
                    }
                    if ($remarques !== '') {
                        $base .= sprintf(' | Rem.: %s', mb_substr($remarques, 0, 40));
                    }
                    if ($temperature !== null && $temperature !== '') {
                        $base .= sprintf(' | Temp: %s deg C', $temperature);
                    }
                    if ($hydration !== null && $hydration !== '') {
                        $base .= sprintf(' | Hydrat.: %s L/j', $hydration);
                    }
                    if ($date !== '') {
                        $base .= sprintf(' | Date: %s', $date);
                    }

                    return $base;
                },
                'attr' => ['class' => 'form-select'],
            ])
            ->add('notes', TextareaType::class, [
                'required' => false,
                'trim' => true,
                'attr' => ['class' => 'form-control', 'rows' => 4],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Activite::class,
            'etats' => [],
        ]);

        $resolver->setAllowedTypes('etats', 'array');
    }
}
