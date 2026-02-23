<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\InscriptionEvenement;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

class InscriptionEvenementMailer
{
    public function __construct(
        private MailerInterface $mailer,
        #[Autowire('%mailer_from_address%')]
        private string $fromAddress,
        #[Autowire('%mailer_from_name%')]
        private string $fromName,
        private ?LoggerInterface $logger = null
    ) {
    }

    public function sendConfirmation(InscriptionEvenement $inscription, string $ticketUrl): void
    {
        $evenement = $inscription->getEvenement();
        if ($evenement === null) {
            return;
        }

        $email = (new TemplatedEmail())
            ->from(new Address($this->fromAddress, $this->fromName))
            ->to($inscription->getEmail())
            ->subject(sprintf('Confirmation inscription - %s', $evenement->getTitre()))
            ->htmlTemplate('email/inscription_evenement_confirmation.html.twig')
            ->context([
                'inscription' => $inscription,
                'evenement' => $evenement,
                'ticket_url' => $ticketUrl,
            ]);

        try {
            $this->mailer->send($email);
        } catch (\Throwable $exception) {
            if ($this->logger) {
                $this->logger->warning('Unable to send event registration confirmation email', [
                    'error' => $exception->getMessage(),
                    'email' => $inscription->getEmail(),
                    'evenement_id' => $evenement->getId(),
                ]);
            }
        }
    }
}
