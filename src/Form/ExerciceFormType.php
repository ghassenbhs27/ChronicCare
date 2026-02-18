<?php

namespace App\Form;

use App\Entity\Evenement;
use App\Entity\Exercice;
use App\Entity\User;
use App\Repository\EvenementRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ExerciceFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $coach = $options['coach'];

        $builder
            ->add('nom', null, [
                'label' => 'Nom',
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'attr' => [
                    'rows' => 5,
                ],
            ])
            ->add('duree', IntegerType::class, [
                'label' => 'Durée (minutes)',
            ])
            ->add('repetitions', IntegerType::class, [
                'label' => 'Répétitions',
                'required' => false,
            ])
            ->add('evenement', EntityType::class, [
                'label' => 'Événement',
                'class' => Evenement::class,
                'choice_label' => 'titre',
                'placeholder' => 'Sélectionner un événement',
                'query_builder' => function (EvenementRepository $repo) use ($coach) {
                    $qb = $repo->createQueryBuilder('e')
                        ->orderBy('e.dateDebut', 'DESC');

                    if ($coach instanceof User) {
                        $qb->andWhere('e.coach = :coach')
                            ->setParameter('coach', $coach);
                    }

                    return $qb;
                },
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Exercice::class,
            'coach' => null,
        ]);

        $resolver->setAllowedTypes('coach', ['null', User::class]);
    }
}
