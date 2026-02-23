<?php

namespace App\EventSubscriber;

use App\Entity\Commande;
use App\Service\CommandeEventStore;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Workflow\Event\CompletedEvent;
use Symfony\Component\Workflow\Event\GuardEvent;
use Symfony\Component\Workflow\Event\TransitionEvent;
use Psr\Log\LoggerInterface;

/**
 * Subscriber qui écoute tous les événements du Workflow de Commande
 * Capture automatiquement les transitions et enregistre dans l'Event Store
 */
class CommandeWorkflowSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly CommandeEventStore $eventStore,
        private readonly ?Security $security = null,
        private readonly ?LoggerInterface $logger = null
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // Avant la transition (validation)
            'workflow.commande_workflow.guard' => 'onGuard',
            
            // Pendant la transition
            'workflow.commande_workflow.transition' => 'onTransition',
            
            // Après la transition réussie
            'workflow.commande_workflow.completed' => 'onCompleted',
            
            // Événements spécifiques par transition
            'workflow.commande_workflow.completed.valider' => 'onValider',
            'workflow.commande_workflow.completed.expedier' => 'onExpedier',
            'workflow.commande_workflow.completed.livrer' => 'onLivrer',
            'workflow.commande_workflow.completed.annuler_depuis_attente' => 'onAnnuler',
            'workflow.commande_workflow.completed.annuler_depuis_validee' => 'onAnnuler',
        ];
    }

    /**
     * Validation avant chaque transition (Guards)
     */
    public function onGuard(GuardEvent $event): void
    {
        /** @var Commande $commande */
        $commande = $event->getSubject();
        $transition = $event->getTransition()->getName();

        $this->logger?->info('Workflow Guard: Validation transition', [
            'commande_id' => $commande->getId(),
            'transition' => $transition,
            'from' => $commande->getStatut(),
        ]);

        // Exemple de règles métier
        match ($transition) {
            'valider' => $this->guardValider($event, $commande),
            'expedier' => $this->guardExpedier($event, $commande),
            'livrer' => $this->guardLivrer($event, $commande),
            default => null,
        };
    }

    private function guardValider(GuardEvent $event, Commande $commande): void
    {
        // Vérifier que la commande a au moins une ligne
        if ($commande->getLigneCommandes()->isEmpty()) {
            $event->setBlocked(true, 'La commande doit contenir au moins un produit');
            return;
        }

        // Vérifier que le total est correct
        if ((float) $commande->getTotal() <= 0) {
            $event->setBlocked(true, 'Le montant de la commande doit être supérieur à 0');
        }
    }

    private function guardExpedier(GuardEvent $event, Commande $commande): void
    {
        // Vérifier qu'une adresse de livraison est renseignée
        if (empty($commande->getAdresse())) {
            $event->setBlocked(true, 'Une adresse de livraison est requise pour expédier');
        }
    }

    private function guardLivrer(GuardEvent $event, Commande $commande): void
    {
        // Règles métier : ne peut livrer que si expédiée depuis au moins X heures
        // (exemple simple)
    }

    /**
     * Pendant la transition
     */
    public function onTransition(TransitionEvent $event): void
    {
        /** @var Commande $commande */
        $commande = $event->getSubject();
        
        $this->logger?->info('Workflow Transition: En cours', [
            'commande_id' => $commande->getId(),
            'transition' => $event->getTransition()->getName(),
            'marking' => $event->getMarking()->getPlaces(),
        ]);
    }

    /**
     * Après chaque transition réussie - Event Sourcing
     */
    public function onCompleted(CompletedEvent $event): void
    {
        /** @var Commande $commande */
        $commande = $event->getSubject();
        $transition = $event->getTransition()->getName();
        $marking = $event->getMarking();
        
        // Récupérer l'utilisateur connecté
        $user = $this->security?->getUser();

        // Déterminer ancien et nouveau statut
        $newStatus = array_key_first($marking->getPlaces());

        $this->logger?->info('Workflow Completed: Transition terminée', [
            'commande_id' => $commande->getId(),
            'transition' => $transition,
            'new_status' => $newStatus,
        ]);

        // 🔥 ENREGISTREMENT AUTOMATIQUE DANS L'EVENT STORE
        $this->eventStore->recordStatusChanged(
            commande: $commande,
            oldStatus: $event->getTransition()->getFroms()[0] ?? 'unknown',
            newStatus: $newStatus,
            user: $user,
            reason: sprintf('Transition: %s', $transition)
        );
    }

    /**
     * Actions spécifiques après validation
     */
    public function onValider(CompletedEvent $event): void
    {
        /** @var Commande $commande */
        $commande = $event->getSubject();
        $user = $this->security?->getUser();

        // Enregistrer le paiement
        $this->eventStore->recordPaymentProcessed(
            commande: $commande,
            user: $user,
            paymentDetails: [
                'method' => 'card',
                'amount' => $commande->getTotal(),
            ]
        );

        $this->logger?->info('Commande validée avec succès', [
            'commande_id' => $commande->getId(),
        ]);

        // Ici : Envoyer email de confirmation
        // Ici : Déclencher préparation logistique
    }

    /**
     * Actions spécifiques après expédition
     */
    public function onExpedier(CompletedEvent $event): void
    {
        /** @var Commande $commande */
        $commande = $event->getSubject();
        $user = $this->security?->getUser();

        // Générer un numéro de tracking (exemple)
        $trackingNumber = 'TRACK-' . strtoupper(substr(md5($commande->getNumeroCommande()), 0, 10));

        $this->eventStore->recordShipped(
            commande: $commande,
            user: $user,
            trackingNumber: $trackingNumber
        );

        $this->logger?->info('Commande expédiée', [
            'commande_id' => $commande->getId(),
            'tracking' => $trackingNumber,
        ]);

        // Ici : Envoyer email avec lien de tracking
        // Ici : Notifier le transporteur
    }

    /**
     * Actions spécifiques après livraison
     */
    public function onLivrer(CompletedEvent $event): void
    {
        /** @var Commande $commande */
        $commande = $event->getSubject();
        $user = $this->security?->getUser();

        $this->eventStore->recordDelivered(
            commande: $commande,
            user: $user
        );

        $this->logger?->info('Commande livrée', [
            'commande_id' => $commande->getId(),
        ]);

        // Ici : Envoyer email de satisfaction
        // Ici : Demander un avis client
    }

    /**
     * Actions spécifiques après annulation
     */
    public function onAnnuler(CompletedEvent $event): void
    {
        /** @var Commande $commande */
        $commande = $event->getSubject();
        $user = $this->security?->getUser();

        $this->eventStore->recordCancelled(
            commande: $commande,
            user: $user,
            reason: 'Annulation utilisateur'
        );

        $this->logger?->warning('Commande annulée', [
            'commande_id' => $commande->getId(),
        ]);

        // Ici : Rembourser si nécessaire
        // Ici : Restaurer le stock
        // Ici : Envoyer email de confirmation d'annulation
    }
}
