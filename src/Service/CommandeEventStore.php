<?php

namespace App\Service;

use App\Entity\Commande;
use App\Entity\CommandeEvent;
use App\Entity\User;
use App\Repository\CommandeEventRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Event Store : Service centralisé pour enregistrer tous les événements des commandes
 * Pattern Event Sourcing - Capture toutes les modifications avec contexte complet
 */
class CommandeEventStore
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CommandeEventRepository $eventRepository,
        private readonly RequestStack $requestStack
    ) {
    }

    /**
     * Enregistre un événement dans l'Event Store
     */
    public function recordEvent(
        Commande $commande,
        string $eventType,
        ?User $triggeredBy = null,
        ?string $oldValue = null,
        ?string $newValue = null,
        ?string $reason = null,
        array $additionalData = []
    ): CommandeEvent {
        $event = new CommandeEvent();
        $event->setCommande($commande);
        $event->setEventType($eventType);
        $event->setTriggeredBy($triggeredBy);
        $event->setOldValue($oldValue);
        $event->setNewValue($newValue);
        $event->setReason($reason);

        // Capture du contexte HTTP
        $request = $this->requestStack->getCurrentRequest();
        if ($request !== null) {
            $event->setIpAddress($request->getClientIp());
            $event->setUserAgent($request->headers->get('User-Agent'));
        }

        // Données additionnelles
        $eventData = array_merge([
            'commande_id' => $commande->getId(),
            'numero_commande' => $commande->getNumeroCommande(),
            'total' => $commande->getTotal(),
            'timestamp' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ], $additionalData);

        $event->setEventData($eventData);

        $this->entityManager->persist($event);
        $this->entityManager->flush();

        return $event;
    }

    /**
     * Enregistre la création d'une commande
     */
    public function recordCommandeCreated(Commande $commande, ?User $user = null): CommandeEvent
    {
        return $this->recordEvent(
            commande: $commande,
            eventType: 'commande.created',
            triggeredBy: $user,
            newValue: $commande->getStatut(),
            additionalData: [
                'items_count' => $commande->getLigneCommandes()->count(),
                'adresse' => $commande->getAdresse(),
            ]
        );
    }

    /**
     * Enregistre un changement de statut
     */
    public function recordStatusChanged(
        Commande $commande,
        string $oldStatus,
        string $newStatus,
        ?User $user = null,
        ?string $reason = null
    ): CommandeEvent {
        return $this->recordEvent(
            commande: $commande,
            eventType: 'commande.status_changed',
            triggeredBy: $user,
            oldValue: $oldStatus,
            newValue: $newStatus,
            reason: $reason,
            additionalData: [
                'transition' => sprintf('%s → %s', $oldStatus, $newStatus),
            ]
        );
    }

    /**
     * Enregistre un paiement traité
     */
    public function recordPaymentProcessed(
        Commande $commande,
        ?User $user = null,
        array $paymentDetails = []
    ): CommandeEvent {
        return $this->recordEvent(
            commande: $commande,
            eventType: 'commande.payment_processed',
            triggeredBy: $user,
            additionalData: $paymentDetails
        );
    }

    /**
     * Enregistre une expédition
     */
    public function recordShipped(
        Commande $commande,
        ?User $user = null,
        ?string $trackingNumber = null
    ): CommandeEvent {
        return $this->recordEvent(
            commande: $commande,
            eventType: 'commande.shipped',
            triggeredBy: $user,
            additionalData: [
                'tracking_number' => $trackingNumber,
            ]
        );
    }

    /**
     * Enregistre une livraison
     */
    public function recordDelivered(Commande $commande, ?User $user = null): CommandeEvent
    {
        return $this->recordEvent(
            commande: $commande,
            eventType: 'commande.delivered',
            triggeredBy: $user
        );
    }

    /**
     * Enregistre une annulation
     */
    public function recordCancelled(
        Commande $commande,
        ?User $user = null,
        ?string $reason = null
    ): CommandeEvent {
        return $this->recordEvent(
            commande: $commande,
            eventType: 'commande.cancelled',
            triggeredBy: $user,
            reason: $reason
        );
    }

    /**
     * Enregistre un remboursement
     */
    public function recordRefunded(
        Commande $commande,
        ?User $user = null,
        ?float $amount = null
    ): CommandeEvent {
        return $this->recordEvent(
            commande: $commande,
            eventType: 'commande.refunded',
            triggeredBy: $user,
            additionalData: [
                'refund_amount' => $amount,
            ]
        );
    }

    /**
     * Récupère l'historique complet d'une commande
     * 
     * @return CommandeEvent[]
     */
    public function getCommandeHistory(Commande $commande): array
    {
        return $this->eventRepository->findByCommande($commande);
    }

    /**
     * Reconstruit l'état d'une commande à un moment donné (Event Replay)
     */
    public function replayCommandeState(Commande $commande, \DateTimeInterface $upToDate): array
    {
        $events = $this->eventRepository->createQueryBuilder('e')
            ->andWhere('e.commande = :commande')
            ->andWhere('e.createdAt <= :date')
            ->setParameter('commande', $commande)
            ->setParameter('date', $upToDate)
            ->orderBy('e.createdAt', 'ASC')
            ->getQuery()
            ->getResult();

        $state = [
            'statut' => 'en_attente',
            'changes_count' => 0,
            'users_involved' => [],
        ];

        foreach ($events as $event) {
            if ($event->getEventType() === 'commande.status_changed') {
                $state['statut'] = $event->getNewValue();
                $state['changes_count']++;
            }
            if ($event->getTriggeredBy()) {
                $state['users_involved'][] = $event->getTriggeredBy()->getEmail();
            }
        }

        $state['users_involved'] = array_unique($state['users_involved']);

        return $state;
    }

    /**
     * Statistiques sur les événements
     */
    public function getEventStatistics(\DateTimeInterface $since = null): array
    {
        $since = $since ?? new \DateTime('-30 days');

        return [
            'total_events' => $this->eventRepository->count([]),
            'status_changes' => $this->eventRepository->countStatusChanges($since),
            'recent_events' => $this->eventRepository->findRecentEvents(10),
        ];
    }
}
