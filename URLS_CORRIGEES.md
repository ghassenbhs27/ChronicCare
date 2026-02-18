# 🎯 URLS CORRIGÉES - PATTERNS MÉTIER AVANCÉS

## ⚠️ URLS MISES À JOUR (Problème résolu)

Les routes ont été renommées pour éviter les conflits avec l'ancien système de gestion des commandes.

---

## 🔥 NOUVELLES URLS - PATTERNS AVANCÉS

### 1️⃣ Liste des commandes avec Workflow & Stats
**URL:** http://localhost:8000/admin/commandes
**Route:** `admin_commande_workflow_list`

**Ce que vous voyez:**
- ✅ Cartes de statistiques par statut (en_attente, validee, expediee, livree, annulee)
- ✅ Liste complète des commandes
- ✅ Bouton "Workflow & Histoire" pour chaque commande
- ✅ Indicateurs visuels des patterns avancés

---

### 2️⃣ Détails commande avec Workflow + Event Sourcing
**URL:** http://localhost:8000/admin/commandes/{id}/workflow
**Route:** `admin_commande_workflow_show`
**Exemple:** http://localhost:8000/admin/commandes/1/workflow

**🔥 SECTION 1 - Workflow (State Machine):**
- ✅ Statut actuel de la commande
- ✅ **Transitions disponibles** (boutons dynamiques selon l'état)
  - État `en_attente` → Boutons: "Valider" et "Annuler"
  - État `validee` → Boutons: "Expédier" et "Annuler"
  - État `expediee` → Bouton: "Livrer"
  - État `livree` ou `annulee` → Aucun bouton (état final)
- ✅ Diagramme visuel du workflow complet
- ✅ Métadonnées (titre, description, icône, couleur) sur chaque bouton

**🔥 SECTION 2 - Historique (Event Sourcing):**
- ✅ Timeline chronologique de **tous les événements**
- Pour chaque événement:
  - Type: `commande.created`, `commande.status_changed`
  - Ancien statut → Nouveau statut (badges colorés)
  - Utilisateur qui a déclenché l'action
  - Date et heure exacte (avec secondes)
  - Adresse IP de l'utilisateur
  - User-Agent (navigateur)
  - Raison du changement
  - Bouton "Voir les données JSON complètes" (cliquable)

---

### 3️⃣ Appliquer une transition (POST)
**URL:** http://localhost:8000/admin/commandes/{id}/transition/{transitionName}
**Route:** `admin_commande_workflow_apply_transition`
**Méthode:** POST (via formulaire)

**Exemples:**
- Valider: `/admin/commandes/1/transition/valider`
- Expédier: `/admin/commandes/1/transition/expedier`
- Livrer: `/admin/commandes/1/transition/livrer`
- Annuler: `/admin/commandes/1/transition/annuler_depuis_attente`

**Process:**
1. Aller sur http://localhost:8000/admin/commandes/1/workflow
2. Cliquer sur un bouton de transition (ex: "✅ Valider la commande")
3. Confirmer dans la popup
4. ✅ Transition appliquée si règles métier respectées
5. ❌ Bloquée avec message d'erreur si guards ne valident pas
6. 🔥 Event enregistré automatiquement dans `commande_event`
7. Redirection vers la page détails avec message de succès

---

### 4️⃣ Historique complet (page dédiée)
**URL:** http://localhost:8000/admin/commandes/{id}/workflow/history
**Route:** `admin_commande_workflow_history`
**Exemple:** http://localhost:8000/admin/commandes/1/workflow/history

**Affiche:**
- Timeline complète de tous les événements
- Filtres et recherche (à implémenter)
- Export possible (à implémenter)

---

## 🧪 SCÉNARIO DE TEST COMPLET

### Étape 1: Créer une commande (Client)

1. **Ajouter des produits au panier:**
   ```
   http://localhost:8000/produits
   ```
   - Cliquer sur "Ajouter au panier" pour 2-3 produits

2. **Voir le panier:**
   ```
   http://localhost:8000/cart
   ```
   - Vérifier les produits ajoutés

3. **Passer commande (Checkout):**
   ```
   http://localhost:8000/commandes/checkout
   ```
   - Remplir l'adresse: `123 rue de Test, 75001 Paris`
   - Remplir les infos paiement (peu importe en dev)
   - Cliquer "Valider la commande"

4. **Résultat:**
   - ✅ Commande créée avec statut `en_attente`
   - Note le numéro: `CMD-20260214103015`
   - 🔥 Event `commande.created` enregistré automatiquement

---

### Étape 2: Tester le Workflow (Admin)

1. **Aller sur la liste:**
   ```
   http://localhost:8000/admin/commandes
   ```
   - Voir les statistiques par statut
   - Trouver votre commande dans le tableau

2. **Cliquer sur "Workflow & Histoire":**
   ```
   http://localhost:8000/admin/commandes/1/workflow
   ```

3. **Observer la section Workflow:**
   - Statut actuel: `en_attente` (badge orange)
   - Boutons disponibles:
     - ✅ "Valider la commande" (vert)
     - ❌ "Annuler la commande" (rouge)
   - ❌ Pas de bouton "Expédier" (car transition invalide depuis en_attente)

4. **Observer la section Historique:**
   - 1 événement visible:
     ```
     commande.created
     • Par: user@test.com
     • Le: 14/02/2026 10:30:15
     • IP: 127.0.0.1
     • Nouveau statut: en_attente
     ```

---

### Étape 3: Appliquer la transition "Valider"

1. **Cliquer sur "✅ Valider la commande"**
2. **Confirmer dans la popup**
3. **Résultat:**
   - ✅ Page recharge
   - Statut = `validee` (badge bleu)
   - Message flash: "Transition 'valider' appliquée avec succès"
   - 🔥 Nouvel event dans l'historique:
     ```
     commande.status_changed
     • en_attente → validee
     • Par: admin@test.com
     • Le: 14/02/2026 10:35:22
     • IP: 127.0.0.1
     • Raison: Transition: valider
     ```

4. **Nouveaux boutons disponibles:**
   - ✅ "Expédier la commande" (bleu)
   - ❌ "Annuler la commande" (rouge)
   - ❌ Plus de bouton "Valider" (car déjà validée)

---

### Étape 4: Tester un Guard (Règle métier)

**Scénario A: Expédier sans adresse**

1. Créer une commande SANS adresse (modifier en base ou ne pas remplir)
2. Valider la commande (passe à `validee`)
3. Essayer de cliquer "Expédier"
4. ❌ **Résultat:** Transition bloquée
   - Message: "La transition 'expedier' n'est pas possible"
   - Raison: Guard vérifie `if (empty($adresse)) → blocked`

**Scénario B: Valider avec total = 0**

1. Créer une commande
2. Modifier le total en base: `UPDATE commande SET total = 0 WHERE id = 1`
3. Essayer de valider
4. ❌ **Résultat:** Bloqué par le guard
   - Message: "Le montant doit être supérieur à 0"

---

### Étape 5: Compléter le workflow

1. **Expédier** (depuis `validee`):
   - Cliquer "📦 Expédier la commande"
   - Statut → `expediee` (badge bleu foncé)
   - Nouvel event enregistré

2. **Livrer** (depuis `expediee`):
   - Cliquer "✅ Marquer comme livrée"
   - Statut → `livree` (badge vert)
   - Dernier event enregistré

3. **État final:**
   - Aucune transition disponible
   - Message: "Cette commande est dans un état final"

4. **Historique complet visible:**
   ```
   4. commande.status_changed (expediee → livree)
   3. commande.status_changed (validee → expediee)  
   2. commande.status_changed (en_attente → validee)
   1. commande.created
   ```

---

### Étape 6: Vérifier en base de données

#### Via Symfony Console

```powershell
cd C:\Users\rayen\ChronicCareIntegrated\ChronicCare

# Voir toutes les commandes
php bin/console doctrine:query:sql "SELECT id, numero_commande, statut, total FROM commande"

# Voir l'Event Store complet
php bin/console doctrine:query:sql "SELECT e.id, e.event_type, e.old_value, e.new_value, e.created_at, u.email FROM commande_event e LEFT JOIN user u ON e.triggered_by_id = u.id WHERE e.commande_id = 1 ORDER BY e.created_at"
```

#### Via Adminer (Interface Web)

**URL:** http://localhost:8000/adminer-5.4.2.php

1. Se connecter à MySQL
2. **Table `commande`:**
   ```
   id | numero_commande     | statut  | total | created_at
   1  | CMD-20260214103015  | livree  | 49.99 | 2026-02-14 10:30:15
   ```

3. **Table `commande_event`:**
   ```
   id | commande_id | event_type              | old_value  | new_value | triggered_by_id | ip_address  | created_at
   1  | 1           | commande.created        | NULL       | en_attente| 2               | 127.0.0.1   | 2026-02-14 10:30:15
   2  | 1           | commande.status_changed | en_attente | validee   | 1               | 127.0.0.1   | 2026-02-14 10:35:22
   3  | 1           | commande.status_changed | validee    | expediee  | 1               | 127.0.0.1   | 2026-02-14 11:00:05
   4  | 1           | commande.status_changed | expediee   | livree    | 1               | 127.0.0.1   | 2026-02-14 11:30:42
   ```

---

## ✅ CHECKLIST DE VALIDATION

- [ ] Démarrer l'app: `symfony serve`
- [ ] Créer une commande depuis `/cart` → `/commandes/checkout`
- [ ] Se connecter en admin
- [ ] Aller sur http://localhost:8000/admin/commandes
- [ ] Voir les statistiques par statut (cartes colorées)
- [ ] Cliquer "Workflow & Histoire" sur une commande
- [ ] URL correcte: `/admin/commandes/{id}/workflow`
- [ ] Voir section "Workflow - Transitions" avec boutons
- [ ] Voir section "Historique (Event Sourcing)" avec timeline
- [ ] Cliquer sur un bouton de transition valide
- [ ] Vérifier le message de succès
- [ ] Vérifier le nouvel event dans l'historique
- [ ] Vérifier les infos: user, IP, date, raison
- [ ] Cliquer "Voir les données JSON complètes"
- [ ] Appliquer toutes les transitions jusqu'à "livree"
- [ ] Vérifier qu'aucun bouton n'est disponible (état final)
- [ ] Vérifier en base: `SELECT * FROM commande_event`

---

## 🚀 COMMANDES RAPIDES

```powershell
# Démarrer
cd C:\Users\rayen\ChronicCareIntegrated\ChronicCare
symfony serve

# Ouvrir dans le navigateur
start http://localhost:8000/admin/commandes

# Voir les logs en temps réel
symfony server:log

# Debug du workflow
php bin/console debug:workflow commande_workflow

# Voir les events récents
php bin/console doctrine:query:sql "SELECT * FROM commande_event ORDER BY created_at DESC LIMIT 10"

# Créer un admin (si besoin)
php bin/console doctrine:fixtures:load
```

---

## 📊 RÉSUMÉ DES CHANGEMENTS

### Routes renommées:

| Avant (Erreur) | Après (Corrigé) |
|----------------|-----------------|
| `admin_commande_list` | `admin_commande_workflow_list` |
| `admin_commande_show` | `admin_commande_workflow_show` |
| `admin_commande_apply_transition` | `admin_commande_workflow_apply_transition` |
| `admin_commande_history` | `admin_commande_workflow_history` |

### Templates créés:

- `templates/admin/commande/workflow_list.html.twig` (nouveau)
- `templates/admin/commande/workflow_show.html.twig` (nouveau)

### Contrôleur:

- `src/Controller/AdminCommandeController.php` (mis à jour avec préfixe `workflow_`)

---

## 🎉 PRÊT À TESTER !

**URL principale:** http://localhost:8000/admin/commandes

Le problème de la variable `statut` est résolu. Les nouveaux templates utilisent uniquement les variables passées par le contrôleur (`commandes`, `stats`, `availableTransitions`, `history`).
