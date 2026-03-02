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
        private readonly RDVRepository $rdvRepository,
        private readonly HolidayService $holidayService
    ) {
    }

    public function createRDV(User $patient, User $professionnel, \DateTimeInterface $dateRdv, ?string $motif = null, ?string $conseilsPreconsultation = null): RDV
    {
        $this->validateSlot($patient, $professionnel, $dateRdv);

        $rdv = new RDV();
        $rdv->setPatient($patient);
        $rdv->setProfessionnel($professionnel);
        $rdv->setDateRdv($dateRdv);
        $rdv->setMotif($motif);
        $rdv->setConseilsPreconsultation($conseilsPreconsultation);
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

    public function complete(RDV $rdv): void
    {
        $rdv->setStatut(RDV::STATUT_TERMINE);
        $this->entityManager->flush();
    }

    public function reschedule(RDV $rdv, \DateTimeInterface $dateRdv): void
    {
        $this->validateSlot($rdv->getPatient(), $rdv->getProfessionnel(), $dateRdv, $rdv->getId());

        $rdv->setDateRdv($dateRdv);
        $rdv->setStatut(RDV::STATUT_EN_ATTENTE);
        $rdv->setReminderSentAt(null);
        $this->entityManager->flush();
    }

    private function validateSlot(User $patient, User $professionnel, \DateTimeInterface $dateRdv, ?int $excludeRdvId = null): void
    {
        $date = \DateTimeImmutable::createFromInterface($dateRdv);
        if ($date < new \DateTimeImmutable()) {
            throw new \RuntimeException('La date du rendez-vous doit être dans le futur.');
        }

        $dayOfWeek = (int) $date->format('N');
        if ($dayOfWeek > 5) {
            throw new \RuntimeException('Les rendez-vous ne sont pas disponibles le week-end.');
        }

        if ($this->holidayService->isHoliday($date)) {
            throw new \RuntimeException('Les rendez-vous ne sont pas disponibles les jours fériés.');
        }

        $hour = (int) $date->format('H');
        $minute = (int) $date->format('i');
        if ($hour < 8 || $hour >= 17) {
            throw new \RuntimeException('Les rendez-vous sont possibles entre 08:00 et 17:00.');
        }

        if ($minute % 30 !== 0) {
            throw new \RuntimeException('Les rendez-vous doivent être planifiés par tranches de 30 minutes.');
        }

        if ($this->rdvRepository->isSlotTaken($professionnel->getId(), $dateRdv, $excludeRdvId)) {
            throw new \RuntimeException('Le créneau est déjà pris.');
        }

        if ($this->rdvRepository->isSlotTakenForPatient($patient->getId(), $dateRdv, $excludeRdvId)) {
            throw new \RuntimeException('Vous avez déjà un rendez-vous sur ce créneau.');
        }
    }
}
