<?php

namespace App\Service;

use App\Entity\RDV;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mime\Email;

class NotificationService
{
    public function __construct(private readonly MailerInterface $mailer)
    {
    }

    public function sendRDVConfirmation(RDV $rdv): void
    {
        $patient = $rdv->getPatient();
        $professionnel = $rdv->getProfessionnel();

        $email = (new Email())
            ->from('no-reply@chroniccare.local')
            ->to($patient->getEmail())
            ->subject('Confirmation de rendez-vous')
            ->text(
                sprintf(
                    "Votre rendez-vous est confirmé avec %s %s le %s.",
                    $professionnel->getPrenom(),
                    $professionnel->getNom(),
                    $rdv->getDateRdv()->format('d/m/Y H:i')
                )
            );

        try {
            $this->mailer->send($email);
        } catch (TransportExceptionInterface) {
            // Ignorer les erreurs pour ne pas bloquer le flux
        }
    }

    public function sendRDVReminder(RDV $rdv): void
    {
        $patient = $rdv->getPatient();
        $professionnel = $rdv->getProfessionnel();

        $email = (new Email())
            ->from('no-reply@chroniccare.local')
            ->to($patient->getEmail())
            ->subject('Rappel de rendez-vous')
            ->text(
                sprintf(
                    "Rappel : rendez-vous avec %s %s le %s.",
                    $professionnel->getPrenom(),
                    $professionnel->getNom(),
                    $rdv->getDateRdv()->format('d/m/Y H:i')
                )
            );

        try {
            $this->mailer->send($email);
        } catch (TransportExceptionInterface) {
            // Ignorer les erreurs
        }
    }
}
