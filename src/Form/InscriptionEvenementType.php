<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\InscriptionEvenement;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class InscriptionEvenementType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $withIdentityFields = $options['with_identity_fields'];

        if ($withIdentityFields) {
            $builder
                ->add('prenom', TextType::class, [
                    'label' => 'Prénom',
                ])
                ->add('nom', TextType::class, [
                    'label' => 'Nom',
                ])
                ->add('email', EmailType::class, [
                    'label' => 'Adresse email',
                ])
            ;
        }

        $builder
            ->add('telephone', TextType::class, [
                'label' => 'Téléphone',
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => InscriptionEvenement::class,
            'with_identity_fields' => true,
        ]);
    }
}
