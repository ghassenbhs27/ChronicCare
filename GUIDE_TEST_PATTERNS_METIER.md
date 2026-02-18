# 🎯 GUIDE DE TEST - PATTERNS MÉTIER AVANCÉS (COMMANDES)

## 📍 OÙ TESTER LES FONCTIONNALITÉS

### 1️⃣ CRÉER DES COMMANDES (Client)
**URL:** `http://localhost:8000/cart` puis `/commandes/checkout`

**Processus:**
1. Ajouter des produits au panier
2. Aller au checkout
3. Remplir l'adresse de livraison
4. Valider le paiement
5. ✅ La commande est créée avec statut `en_attente`
6. 🔥 **Event automatique enregistré:** `commande.created`

---

### 2️⃣ VOIR VOS COMMANDES (Client)
**URL:** `http://localhost:8000/commandes`

**Ce que vous voyez:**
- Historique de vos commandes
- Numéro de commande
- Statut actuel
- Total

---

### 3️⃣ GESTION AVANCÉE (Admin) 🔥 NOUVEAU
**URL:** `http://localhost:8000/admin/commandes`

**Fonctionnalités démontrées:**

#### A. Liste des commandes
- Vue d'ensemble de toutes les commandes
- Statistiques par statut (en temps réel)
- Compteurs pour chaque état

#### B. Détails d'une commande avec Workflow
**URL:** `http://localhost:8000/admin/commandes/{id}`

**🔥 Ce qui est NOUVEAU:**

1. **SECTION WORKFLOW (State Machine)**
   - Affiche les transitions disponibles selon l'état actuel
   - Boutons pour appliquer les transitions autorisées
   - Validation automatique des règles métier (guards)
   - Diagramme visuel du workflow

2. **SECTION HISTORIQUE (Event Sourcing)**
   - Liste complète de tous les événements
   - Pour chaque événement:
     * Type d'événement (commande.created, commande.status_changed)
     * Ancien et nouveau statut
     * Utilisateur qui a déclenché l'action
     * Date et heure exacte
     * Adresse IP et User-Agent
     * Raison du changement
     * Données JSON complètes

---

## 📊 AVANT vs APRÈS

### ❌ AVANT (Implémentation simple)

```php
// Dans le contrôleur
public function updateStatus(int $id, string $newStatus): Response
{
    $commande = $repository->find($id);
    
    // ⚠️ PROBLÈMES:
    // - Aucune validation des transitions
    // - Peut passer de "livree" à "en_attente" (incohérent!)
    // - Aucune traçabilité (qui? quand? pourquoi?)
    // - Pas de règles métier
    // - Pas d'historique
    
    $commande->setStatut($newStatus);
    $em->flush();
    
    return $this->redirectToRoute('commande_show');
}
```

**Limites:**
- ❌ Aucun contrôle sur les transitions valides
- ❌ Pas d'historique des changements
- ❌ Impossible de savoir qui a fait quoi
- ❌ Pas de règles métier (ex: vérifier l'adresse avant expédition)
- ❌ Pas d'audit trail
- ❌ Code métier dispersé

---

### ✅ APRÈS (Patterns Avancés)

#### 1. **State Machine (Workflow)**

```yaml
# config/packages/workflow.yaml
framework:
    workflows:
        commande_workflow:
            type: 'state_machine'
            audit_trail:
                enabled: true
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
                expedier:
                    from: validee
                    to: expediee
                # ... etc
```

**Avantages:**
- ✅ Transitions valides définies explicitement
- ✅ Impossible de passer de "livree" à "en_attente"
- ✅ Métadonnées sur chaque transition (titre, description, icône)
- ✅ Guards pour valider les règles métier

#### 2. **Event Sourcing**

```php
// Capture automatique de TOUS les événements
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
        
        // Capture du contexte
        $event->setIpAddress($request->getClientIp());
        $event->setUserAgent($request->headers->get('User-Agent'));
        $event->setEventData([...]);
        
        $em->persist($event);
        $em->flush();
        
        return $event;
    }
}
```

**Avantages:**
- ✅ Historique complet et immuable
- ✅ Traçabilité totale (qui, quand, où, pourquoi)
- ✅ Audit trail pour conformité
- ✅ Possibilité de reconstruire l'état à n'importe quel moment
- ✅ Analyse des comportements utilisateurs

#### 3. **Event Subscriber**

```php
class CommandeWorkflowSubscriber implements EventSubscriberInterface
{
    // Avant la transition: validation
    public function onGuard(GuardEvent $event): void
    {
        match ($transition) {
            'valider' => $this->guardValider($event, $commande),
            'expedier' => $this->guardExpedier($event, $commande),
            // Règles métier automatiques
        };
    }
    
    // Après la transition: enregistrement automatique
    public function onCompleted(CompletedEvent $event): void
    {
        $this->eventStore->recordStatusChanged(...);
        // Logging, notifications, etc.
    }
}
```

**Avantages:**
- ✅ Règles métier centralisées
- ✅ Enregistrement automatique dans Event Store
- ✅ Guards pour bloquer les transitions invalides
- ✅ Code découplé et maintenable

#### 4. **Service Layer**

```php
class CommandeService
{
    // 🔥 NOUVELLE API avec Workflow
    public function applyTransition(Commande $commande, string $transitionName): bool
    {
        if (!$this->commandeWorkflow->can($commande, $transitionName)) {
            return false; // Bloqué par le workflow
        }
        
        $this->commandeWorkflow->apply($commande, $transitionName);
        $this->entityManager->flush();
        
        return true;
    }
    
    public function getAvailableTransitions(Commande $commande): array
    {
        return $this->commandeWorkflow->getEnabledTransitions($commande);
    }
    
    public function getCommandeHistory(Commande $commande): array
    {
        return $this->eventStore->getCommandeHistory($commande);
    }
}
```

---

## 🧪 SCÉNARIOS DE TEST

### Test 1: Workflow - Transitions Valides
1. Créer une commande → État: `en_attente`
2. Aller sur `/admin/commandes/{id}`
3. ✅ Voir le bouton "Valider la commande"
4. Cliquer → État passe à `validee`
5. ✅ Voir le bouton "Expédier la commande"
6. ❌ Ne PAS voir "Valider" (car déjà validée)

### Test 2: Workflow - Transitions Bloquées
1. Commande en état `en_attente` SANS adresse
2. Essayer "Expédier" directement
3. ❌ Impossible car guard bloque:
   ```php
   if (empty($commande->getAdresse())) {
       $event->setBlocked(true, 'Adresse requise');
   }
   ```

### Test 3: Event Sourcing - Traçabilité
1. Créer une commande
2. Valider la commande
3. Expédier la commande
4. Voir l'historique:
   ```
   - commande.created (par user@example.com, IP: 127.0.0.1, le 14/02/2026 10:30:15)
   - commande.status_changed: en_attente → validee (par admin@example.com, le 14/02/2026 10:35:22)
   - commande.status_changed: validee → expediee (par admin@example.com, le 14/02/2026 11:00:05)
   ```

### Test 4: Guards - Règles Métier
1. Commande avec total = 0
2. Essayer de valider
3. ❌ Bloqué avec message: "Le montant doit être > 0"

---

## 🔍 VÉRIFICATION EN BASE DE DONNÉES

### Table `commande`
```sql
SELECT id, numero_commande, statut, total, created_at FROM commande;
```

### Table `commande_event` (Event Store)
```sql
SELECT 
    e.id,
    e.event_type,
    e.old_value,
    e.new_value,
    e.created_at,
    u.email AS triggered_by,
    e.ip_address
FROM commande_event e
LEFT JOIN user u ON e.triggered_by_id = u.id
WHERE e.commande_id = 1
ORDER BY e.created_at DESC;
```

**Structure de l'Event Store:**
```
id | commande_id | event_type              | old_value  | new_value | triggered_by | ip_address   | created_at
----|-------------|-------------------------|------------|-----------|--------------|--------------|-------------------
1  | 1           | commande.created        | NULL       | en_attente| user@test.fr | 127.0.0.1    | 2026-02-14 10:30:15
2  | 1           | commande.status_changed | en_attente | validee   | admin@test.fr| 192.168.1.10 | 2026-02-14 10:35:22
3  | 1           | commande.status_changed | validee    | expediee  | admin@test.fr| 192.168.1.10 | 2026-02-14 11:00:05
```

---

## 📈 BÉNÉFICES MÉTIER

### Pour le Business
- 📊 **Audit complet:** Traçabilité de toutes les actions
- 🔒 **Sécurité:** Impossibilité de contourner les règles
- 📈 **Analytics:** Analyse du parcours des commandes
- 🏛️ **Conformité:** Respect RGPD et normes d'audit

### Pour les Développeurs
- 🧪 **Testable:** Workflow et guards isolés
- 🔧 **Maintenable:** Logique centralisée
- 📚 **Documenté:** Workflow = documentation vivante
- 🚀 **Évolutif:** Ajouter des états sans casser l'existant

### Pour les Utilisateurs
- ✅ **Fiable:** États cohérents garantis
- 🔍 **Transparent:** Historique visible
- ⚡ **Rapide:** Transitions validées en temps réel
- 🛡️ **Sécurisé:** Règles métier respectées

---

## 🚀 COMMANDES POUR TESTER

### 1. Démarrer l'application
```powershell
cd ChronicCare
symfony serve
```

### 2. Créer un utilisateur admin
```powershell
php bin/console doctrine:fixtures:load
# Ou créer manuellement un user avec ROLE_ADMIN
```

### 3. Accéder aux URLs
- Client: `http://localhost:8000/commandes`
- Admin: `http://localhost:8000/admin/commandes`

### 4. Vérifier les events en base
```powershell
php bin/console doctrine:query:sql "SELECT * FROM commande_event ORDER BY created_at DESC LIMIT 10"
```

---

## 💡 PATTERNS IMPLÉMENTÉS

1. ✅ **State Machine** (Workflow Symfony)
2. ✅ **Event Sourcing** (CommandeEvent + EventStore)
3. ✅ **Event Subscriber** (CommandeWorkflowSubscriber)
4. ✅ **Service Layer** (CommandeService)
5. ✅ **Repository Pattern** (Requêtes métier)
6. ✅ **Guard Pattern** (Validation des transitions)
7. ✅ **Audit Trail** (Traçabilité complète)
8. ✅ **Metadata Pattern** (Enrichissement des transitions)
9. ✅ **CQRS partiel** (Séparation lecture/écriture events)
10. ✅ **Immutability** (Events immuables)
