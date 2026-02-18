<?php

namespace App\Service;

use App\Entity\RDV;
use App\Entity\User;
use App\Repository\RDVRepository;
use Doctrine\ORM\EntityManagerInterface;

class RDVService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RDVRepository $rdvRepository
    ) {
    }

    public function createRDV(User $patient, User $professionnel, \DateTimeInterface $dateRdv, ?string $motif = null): RDV
    {
        if ($this->rdvRepository->isSlotTaken($professionnel->getId(), $dateRdv)) {
            throw new \RuntimeException('Le créneau est déjà pris.');
        }

        $rdv = new RDV();
        $rdv->setPatient($patient);
        $rdv->setProfessionnel($professionnel);
        $rdv->setDateRdv($dateRdv);
        $rdv->setMotif($motif);
        $rdv->setStatut(RDV::STATUT_EN_ATTENTE);

        $this->entityManager->persist($rdv);
        $this->entityManager->flush();

        return $rdv;
    }

    public function cancel(RDV $rdv): void
    {
        $rdv->setStatut(RDV::STATUT_ANNULE);
        $this->entityManager->flush();
    }

    public function accept(RDV $rdv): void
    {
        $rdv->setStatut(RDV::STATUT_CONFIRME);
        $this->entityManager->flush();
    }

    public function reschedule(RDV $rdv, \DateTimeInterface $dateRdv): void
    {
        if ($this->rdvRepository->isSlotTaken($rdv->getProfessionnel()->getId(), $dateRdv)) {
            throw new \RuntimeException('Le créneau est déjà pris.');
        }

        $rdv->setDateRdv($dateRdv);
        $rdv->setStatut(RDV::STATUT_EN_ATTENTE);
        $this->entityManager->flush();
    }
}
