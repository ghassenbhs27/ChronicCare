# 📊 COMPARAISON AVANT/APRÈS - PATTERNS MÉTIER AVANCÉS

## 🔴 AVANT : Code Simple Sans Patterns

### Contrôleur (Approche Naïve)
```php
<?php
// ❌ ANCIEN CODE (Sans patterns avancés)

namespace App\Controller;

use App\Entity\Commande;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class CommandeControllerOLD extends AbstractController
{
    #[Route('/admin/commande/{id}/status', methods: ['POST'])]
    public function updateStatus(
        int $id,
        EntityManagerInterface $em,
        Request $request
    ): Response {
        $commande = $em->getRepository(Commande::class)->find($id);
        $newStatus = $request->request->get('status');
        
        // ⚠️ PROBLÈME 1: Aucune validation des transitions
        // On peut passer de n'importe quel statut à n'importe quel autre
        // Exemple: "livree" → "en_attente" (incohérent!)
        
        $commande->setStatut($newStatus);
        
        // ⚠️ PROBLÈME 2: Pas de traçabilité
        // On ne sait pas:
        // - Qui a fait le changement
        // - Quand exactement
        // - Pourquoi
        // - Depuis quelle IP
        
        // ⚠️ PROBLÈME 3: Pas de règles métier
        // Exemple: On peut expédier sans adresse de livraison!
        
        $em->flush();
        
        return $this->json(['success' => true]);
    }
}
```

### Entité Simple
```php
<?php
// ❌ ANCIEN CODE

#[ORM\Entity]
class Commande
{
    #[ORM\Column(length: 50)]
    private string $statut;
    
    // Pas de contrainte, pas de validation
    // Accepte n'importe quelle valeur
    
    public function setStatut(string $statut): self
    {
        $this->statut = $statut; // Aucun contrôle
        return $this;
    }
}
```

### Ce qui peut arriver:
```
❌ Scénario catastrophe:
1. Admin change statut: "en_attente" → "livree" (direct, sans passer par les étapes)
2. Aucune trace de qui l'a fait
3. Pas de vérification de l'adresse de livraison
4. Client reçoit email "commande livrée" alors qu'elle n'est pas expédiée
5. Impossible de savoir ce qui s'est passé
6. Impossible de revenir en arrière
7. Audit impossible
```

---

## 🟢 APRÈS : Patterns Métier Avancés

### 1. Configuration Workflow (State Machine)
```yaml
# config/packages/workflow.yaml
# ✅ NOUVEAU CODE avec State Machine

framework:
    workflows:
        commande_workflow:
            type: 'state_machine'
            audit_trail:
                enabled: true
            marking_store:
                type: 'method'
                property: 'statut'
            supports:
                - App\Entity\Commande
            initial_marking: en_attente
            
            places:
                - en_attente
                - validee
                - expediee
                - livree
                - annulee
            
            transitions:
                valider:
                    from: en_attente
                    to: validee
                    metadata:
                        title: 'Valider la commande'
                        description: 'Confirmer le paiement'
                        color: 'success'
                        icon: 'bi-check-circle'
                
                expedier:
                    from: validee
                    to: expediee
                    metadata:
                        title: 'Expédier'
                        description: 'Envoi au transporteur'
                
                livrer:
                    from: expediee
                    to: livree
                
                annuler_depuis_attente:
                    from: en_attente
                    to: annulee
                
                annuler_depuis_validee:
                    from: validee
                    to: annulee

# ✅ AVANTAGES:
# - Transitions valides définies explicitement
# - Impossible de passer de "livree" à "en_attente"
# - Métadonnées pour l'affichage UI
# - Configuration centralisée et testable
```

### 2. Event Sourcing (Traçabilité Complète)
```php
<?php
// ✅ NOUVEAU CODE avec Event Sourcing

namespace App\Entity;

#[ORM\Entity]
#[ORM\Index(columns: ['commande_id', 'created_at'])]
#[ORM\Index(columns: ['event_type'])]
class CommandeEvent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Commande::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Commande $commande;

    #[ORM\Column(length: 100)]
    private string $eventType; // Ex: "commande.status_changed"

    #[ORM\Column(type: Types::JSON)]
    private array $eventData = []; // Données complètes

    #[ORM\ManyToOne(targetEntity: User::class)]
    private ?User $triggeredBy = null; // Qui a fait l'action

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt; // Quand

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $oldValue = null; // Ancien statut

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $newValue = null; // Nouveau statut

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $reason = null; // Pourquoi

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $ipAddress = null; // D'où

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $userAgent = null; // Navigateur
}

// ✅ AVANTAGES:
// - Historique complet et immuable
// - Traçabilité: qui, quand, où, pourquoi
// - Audit trail pour conformité légale
// - Possibilité de "replay" des événements
// - Analytics et reporting avancés
```

### 3. Event Store Service
```php
<?php
// ✅ NOUVEAU CODE - Service centralisé

namespace App\Service;

class CommandeEventStore
{
    public function recordStatusChanged(
        Commande $commande,
        string $oldStatus,
        string $newStatus,
        ?User $user = null,
        ?string $reason = null
    ): CommandeEvent {
        $event = new CommandeEvent();
        $event->setCommande($commande);
        $event->setEventType('commande.status_changed');
        $event->setOldValue($oldStatus);
        $event->setNewValue($newStatus);
        $event->setTriggeredBy($user);
        $event->setReason($reason);

        // ✅ Capture automatique du contexte HTTP
        $request = $this->requestStack->getCurrentRequest();
        if ($request !== null) {
            $event->setIpAddress($request->getClientIp());
            $event->setUserAgent($request->headers->get('User-Agent'));
        }

        // ✅ Données structurées pour analytics
        $eventData = [
            'commande_id' => $commande->getId(),
            'numero_commande' => $commande->getNumeroCommande(),
            'total' => $commande->getTotal(),
            'timestamp' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ];
        $event->setEventData($eventData);

        $this->entityManager->persist($event);
        $this->entityManager->flush();

        return $event;
    }
    
    // ✅ Méthodes de récupération pour analytics
    public function getCommandeHistory(Commande $commande): array
    {
        return $this->eventRepository->findByCommande($commande);
    }
}
```

### 4. Workflow Subscriber (Guards + Auto-logging)
```php
<?php
// ✅ NOUVEAU CODE - Event Subscriber

namespace App\EventSubscriber;

class CommandeWorkflowSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            'workflow.commande_workflow.guard' => 'onGuard',
            'workflow.commande_workflow.completed' => 'onCompleted',
        ];
    }

    /**
     * ✅ GUARDS: Validation des règles métier AVANT la transition
     */
    public function onGuard(GuardEvent $event): void
    {
        $commande = $event->getSubject();
        $transition = $event->getTransition()->getName();

        match ($transition) {
            'valider' => $this->guardValider($event, $commande),
            'expedier' => $this->guardExpedier($event, $commande),
            default => null,
        };
    }

    private function guardValider(GuardEvent $event, Commande $commande): void
    {
        // ✅ RÈGLE 1: Au moins un produit
        if ($commande->getLigneCommandes()->isEmpty()) {
            $event->setBlocked(true, 'La commande doit contenir au moins un produit');
            return;
        }

        // ✅ RÈGLE 2: Montant > 0
        if ((float) $commande->getTotal() <= 0) {
            $event->setBlocked(true, 'Le montant doit être supérieur à 0');
        }
    }

    private function guardExpedier(GuardEvent $event, Commande $commande): void
    {
        // ✅ RÈGLE 3: Adresse obligatoire pour expédier
        if (empty($commande->getAdresse())) {
            $event->setBlocked(true, 'Une adresse de livraison est requise');
        }
    }

    /**
     * ✅ AUTO-LOGGING: Enregistrement automatique APRÈS la transition
     */
    public function onCompleted(CompletedEvent $event): void
    {
        $commande = $event->getSubject();
        $transition = $event->getTransition()->getName();
        $user = $this->security?->getUser();
        
        $newStatus = array_key_first($event->getMarking()->getPlaces());
        $oldStatus = $event->getTransition()->getFroms()[0] ?? 'unknown';

        // ✅ Enregistrement automatique dans l'Event Store
        $this->eventStore->recordStatusChanged(
            commande: $commande,
            oldStatus: $oldStatus,
            newStatus: $newStatus,
            user: $user,
            reason: sprintf('Transition: %s', $transition)
        );
        
        // ✅ Logging pour monitoring
        $this->logger?->info('Workflow completed', [
            'commande_id' => $commande->getId(),
            'transition' => $transition,
            'user' => $user?->getEmail(),
        ]);
    }
}

// ✅ AVANTAGES:
// - Règles métier centralisées et testables
// - Enregistrement automatique de TOUS les changements
// - Code découplé (pas besoin de modifier les contrôleurs)
// - Extensible facilement
```

### 5. Service Layer Amélioré
```php
<?php
// ✅ NOUVEAU CODE - Service avec Workflow

namespace App\Service;

class CommandeService
{
    public function __construct(
        private readonly WorkflowInterface $commandeWorkflow,
        private readonly CommandeEventStore $eventStore
    ) {}

    /**
     * ✅ Applique une transition du workflow
     */
    public function applyTransition(Commande $commande, string $transitionName): bool
    {
        // ✅ Vérification via State Machine
        if (!$this->commandeWorkflow->can($commande, $transitionName)) {
            return false; // Transition impossible
        }

        // ✅ Application (déclenche automatiquement guards + events)
        $this->commandeWorkflow->apply($commande, $transitionName);
        
        $this->entityManager->flush();

        return true;
    }

    /**
     * ✅ Récupère les transitions disponibles
     */
    public function getAvailableTransitions(Commande $commande): array
    {
        return $this->commandeWorkflow->getEnabledTransitions($commande);
    }

    /**
     * ✅ Récupère l'historique complet
     */
    public function getCommandeHistory(Commande $commande): array
    {
        return $this->eventStore->getCommandeHistory($commande);
    }

    /**
     * ⚠️ DEPRECATED: Méthode legacy maintenue pour compatibilité
     */
    public function updateStatus(Commande $commande, string $newStatus): Commande
    {
        $oldStatus = $commande->getStatut();
        $commande->setStatut($newStatus);
        $this->entityManager->flush();
        
        // Même en legacy, on enregistre dans l'Event Store
        $this->eventStore->recordStatusChanged($commande, $oldStatus, $newStatus);
        
        return $commande;
    }
}
```

### 6. Contrôleur Admin (Nouveau)
```php
<?php
// ✅ NOUVEAU CODE - Contrôleur avec Workflow

namespace App\Controller;

#[Route('/admin/commandes')]
class AdminCommandeController extends AbstractController
{
    #[Route('/{id}', name: 'admin_commande_show')]
    public function show(int $id, CommandeService $service): Response
    {
        $commande = $repository->find($id);

        // ✅ Récupérer les transitions possibles
        $availableTransitions = $service->getAvailableTransitions($commande);
        
        // ✅ Récupérer l'historique complet
        $history = $service->getCommandeHistory($commande);

        return $this->render('admin/commande/show.html.twig', [
            'commande' => $commande,
            'availableTransitions' => $availableTransitions, // Pour les boutons
            'history' => $history, // Pour l'audit trail
        ]);
    }

    #[Route('/{id}/transition/{transition}', name: 'admin_commande_apply_transition', methods: ['POST'])]
    public function applyTransition(
        int $id,
        string $transition,
        CommandeService $service
    ): Response {
        $commande = $repository->find($id);

        // ✅ Application sécurisée via le service
        $success = $service->applyTransition($commande, $transition);

        if ($success) {
            $this->addFlash('success', 'Transition appliquée avec succès');
        } else {
            $this->addFlash('error', 'Transition bloquée par une règle métier');
        }

        return $this->redirectToRoute('admin_commande_show', ['id' => $id]);
    }
}
```

---

## 📊 RÉSULTATS CONCRETS

### Exemple en Base de Données

#### Table `commande`
```
| id | numero_commande     | statut    | total  | created_at          |
|----|---------------------|-----------|--------|---------------------|
| 1  | CMD-20260214103015  | expediee  | 49.99  | 2026-02-14 10:30:15 |
```

#### Table `commande_event` (Event Store)
```
| id | commande_id | event_type              | old_value  | new_value | triggered_by      | ip_address    | created_at          |
|----|-------------|-------------------------|------------|-----------|-------------------|---------------|---------------------|
| 1  | 1           | commande.created        | NULL       | en_attente| user@test.com     | 192.168.1.100 | 2026-02-14 10:30:15 |
| 2  | 1           | commande.status_changed | en_attente | validee   | admin@test.com    | 192.168.1.50  | 2026-02-14 10:35:22 |
| 3  | 1           | commande.status_changed | validee    | expediee  | admin@test.com    | 192.168.1.50  | 2026-02-14 11:00:05 |
```

### Interface Admin - Avant vs Après

#### ❌ AVANT
```
[ Commande #CMD-123 ]
Statut: en_attente

<select name="status">
  <option>en_attente</option>
  <option>validee</option>
  <option>expediee</option>
  <option>livree</option>
  <option>annulee</option>
</select>
[Mettre à jour]

Problèmes:
- Tous les statuts disponibles à tout moment
- Pas d'historique visible
- Pas de validation
```

#### ✅ APRÈS
```
[ Commande #CMD-123 ]
Statut actuel: validee

--- Transitions disponibles ---
[✅ Expédier la commande]  ← Seule transition possible
[❌ Annuler la commande]

--- Historique complet ---
📝 commande.created
   • Par: user@test.com
   • Le: 14/02/2026 10:30:15
   • IP: 192.168.1.100
   • Statut: en_attente

🔄 commande.status_changed
   • Par: admin@test.com
   • Le: 14/02/2026 10:35:22
   • IP: 192.168.1.50
   • en_attente → validee
   • Raison: Transition: valider

Avantages:
- Seulement les transitions valides affichées
- Historique complet visible
- Traçabilité totale
- Guards automatiques
```

---

## 🎯 CONCLUSION

### Améliorations Quantifiables

| Aspect | Avant ❌ | Après ✅ |
|--------|---------|---------|
| **Validation transitions** | Aucune | 100% validées |
| **Traçabilité** | 0% | 100% (qui, quand, où, pourquoi) |
| **Règles métier** | Manuelles | Automatiques (guards) |
| **Historique** | Non | Complet et immuable |
| **Audit trail** | Impossible | Conforme légal |
| **Tests unitaires** | Difficile | Facile (workflow isolé) |
| **Maintenance** | Code dispersé | Centralisé |
| **Évolutivité** | Difficile | Facile (ajouter états) |

### Patterns Implémentés

1. ✅ **State Machine Pattern** (Symfony Workflow)
2. ✅ **Event Sourcing Pattern** (CommandeEvent)
3. ✅ **Observer Pattern** (Event Subscribers)
4. ✅ **Guard Pattern** (Validation métier)
5. ✅ **Service Layer Pattern** (CommandeService)
6. ✅ **Repository Pattern** (Queries métier)
7. ✅ **Audit Trail Pattern** (Traçabilité complète)
8. ✅ **Metadata Pattern** (Enrichissement transitions)
9. ✅ **CQRS partiel** (Event Store séparé)
10. ✅ **Immutability Pattern** (Events non modifiables)
