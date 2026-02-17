<?php

namespace App\Service;

use App\Entity\Evenement;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;

class EvenementService
{
    public function __construct(
        private EntityManagerInterface $em
    ) {}

    public function createEvenement(Evenement $evenement, User $coach): Evenement
    {
        if ($evenement->getDateFin() <= $evenement->getDateDebut()) {
            throw new InvalidArgumentException('La date de fin doit etre apres la date de debut.');
        }

        $evenement->setCoach($coach);
        $evenement->setStatut(Evenement::STATUT_EN_ATTENTE);

        $this->em->persist($evenement);
        $this->em->flush();

        return $evenement;
    }

    public function updateEvenement(Evenement $evenement): Evenement
    {
        if ($evenement->getDateFin() <= $evenement->getDateDebut()) {
            throw new InvalidArgumentException('La date de fin doit etre apres la date de debut.');
        }

        $this->em->flush();

        return $evenement;
    }

    public function deleteEvenement(Evenement $evenement): void
    {
        $this->em->remove($evenement);
        $this->em->flush();
    }
}
