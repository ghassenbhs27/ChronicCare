<?php

namespace App\Service;

use App\Entity\RDV;
use App\Entity\User;
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

    public function sendUserApprovalDecision(User $user, bool $approved): void
    {
        $requestedRole = $user->getRequestedRole() ?? 'ROLE_PATIENT';
        $roleLabel = match ($requestedRole) {
            'ROLE_COACH' => 'Coach',
            'ROLE_NUTRITIONNISTE' => 'Nutritionniste',
            'ROLE_ADMIN' => 'Administrateur',
            default => 'Patient',
        };

        $subject = $approved
            ? 'Votre compte Chronic Care est approuve'
            : 'Mise a jour de votre demande d inscription';

        $text = $approved
            ? sprintf(
                "Bonjour %s,\n\nVotre compte a ete approuve par un administrateur.\nRole accorde: %s.\nVous pouvez maintenant vous connecter.",
                $user->getFullName(),
                $roleLabel
            )
            : sprintf(
                "Bonjour %s,\n\nVotre demande d inscription a ete refusee par un administrateur.\nVous pouvez contacter le support pour plus d informations.",
                $user->getFullName()
            );

        $email = (new Email())
            ->from('no-reply@chroniccare.local')
            ->to((string) $user->getEmail())
            ->subject($subject)
            ->text($text);

        try {
            $this->mailer->send($email);
        } catch (TransportExceptionInterface) {
            // Ne pas bloquer le flux admin en cas d'erreur mail.
        }
    }

    /**
     * @param string[] $adminEmails
     */
    public function sendAdminApprovalRequest(User $user, array $adminEmails): void
    {
        $adminEmails = array_values(array_unique(array_filter($adminEmails)));
        if ($adminEmails === []) {
            return;
        }

        $requestedRole = $user->getRequestedRole() ?? 'ROLE_PATIENT';
        $roleLabel = match ($requestedRole) {
            'ROLE_COACH' => 'Coach',
            'ROLE_NUTRITIONNISTE' => 'Nutritionniste',
            'ROLE_ADMIN' => 'Administrateur',
            default => 'Patient',
        };

        $email = (new Email())
            ->from('no-reply@chroniccare.local')
            ->to(...$adminEmails)
            ->subject('Nouvelle demande d acces professionnel')
            ->text(sprintf(
                "Nouvelle demande d inscription a valider.\n\nNom: %s\nEmail: %s\nRole demande: %s\n\nAction: connectez-vous au back-office puis approuvez ou refusez dans Gestion des utilisateurs.",
                $user->getFullName(),
                (string) $user->getEmail(),
                $roleLabel
            ));

        try {
            $this->mailer->send($email);
        } catch (TransportExceptionInterface) {
            // Ne pas bloquer l'inscription si l'email admin echoue.
        }
    }
}
