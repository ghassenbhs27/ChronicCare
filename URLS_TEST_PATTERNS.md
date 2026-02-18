# 🎯 URLS EXACTES POUR TESTER LES PATTERNS AVANCÉS

## 🚀 DÉMARRER L'APPLICATION

```powershell
cd C:\Users\rayen\ChronicCareIntegrated\ChronicCare
symfony serve
```

L'application sera disponible sur: **http://localhost:8000**

---

## 📍 URLS DE TEST - FONCTIONNALITÉS STANDARD

### 1. Créer une commande (Client)
- **Panier:** http://localhost:8000/cart
- **Checkout:** http://localhost:8000/commandes/checkout
- **Mes commandes:** http://localhost:8000/commandes
- **Détails commande:** http://localhost:8000/commandes/1

### 2. Produits (Client)
- **Liste produits:** http://localhost:8000/produits
- **Détail produit:** http://localhost:8000/produits/1

---

## 🔥 URLS DE TEST - PATTERNS AVANCÉS (ADMIN)

### 1. Liste des commandes avec statistiques
**URL:** http://localhost:8000/admin/commandes

**Ce que vous voyez:**
- ✅ Statistiques par statut (en_attente, validee, expediee, livree, annulee)
- ✅ Liste complète des commandes
- ✅ Bouton "Détails & Workflow" pour chaque commande

### 2. Détails commande avec Workflow + Event Sourcing
**URL:** http://localhost:8000/admin/commandes/1

**🔥 NOUVEAU - Section Workflow (State Machine):**
- ✅ Affiche le statut actuel de la commande
- ✅ Liste des **transitions disponibles** selon l'état
  - Si statut = `en_attente` → Boutons: "Valider" et "Annuler"
  - Si statut = `validee` → Boutons: "Expédier" et "Annuler"
  - Si statut = `expediee` → Bouton: "Livrer"
  - Si statut = `livree` ou `annulee` → Aucun bouton (état final)
- ✅ Diagramme visuel du workflow
- ✅ Application des transitions avec validation automatique (guards)

**🔥 NOUVEAU - Section Historique (Event Sourcing):**
- ✅ Timeline chronologique de tous les événements
- Pour chaque événement:
  - Type: `commande.created`, `commande.status_changed`
  - Ancien statut → Nouveau statut
  - Utilisateur qui a fait l'action (email)
  - Date et heure exacte
  - Adresse IP de l'utilisateur
  - User-Agent (navigateur)
  - Raison du changement
  - Données JSON complètes (cliquable)

### 3. Appliquer une transition
**URL:** http://localhost:8000/admin/commandes/1/transition/valider
**Méthode:** POST (via formulaire depuis la page détails)

**Process:**
1. Va sur http://localhost:8000/admin/commandes/1
2. Clique sur un bouton de transition (ex: "Valider la commande")
3. ✅ Transition appliquée si valide
4. ❌ Bloquée si règles métier non respectées
5. 🔥 Event enregistré automatiquement dans `commande_event`

---

## 🧪 SCÉNARIOS DE TEST ÉTAPE PAR ÉTAPE

### Scénario 1: Créer une commande et voir l'Event Store

1. **Ajouter des produits:**
   - Aller sur http://localhost:8000/produits
   - Cliquer sur "Ajouter au panier" pour plusieurs produits

2. **Passer commande:**
   - Aller sur http://localhost:8000/cart
   - Cliquer "Passer la commande"
   - Remplir l'adresse: `123 rue de Test, 75001 Paris`
   - Remplir le paiement (peu importe les données en dev)
   - Valider

3. **Vérifier la création:**
   - Note le numéro de commande (ex: CMD-20260214103015)
   - ✅ Statut = `en_attente`
   - 🔥 Un event `commande.created` a été enregistré automatiquement

4. **Voir l'Event Store:**
   - Se connecter en tant qu'admin
   - Aller sur http://localhost:8000/admin/commandes
   - Cliquer sur "Détails & Workflow" de votre commande
   - **Section "Historique":** Voir l'événement de création avec toutes les infos (IP, user, etc.)

---

### Scénario 2: Tester le Workflow - Transitions valides

1. **État initial: en_attente**
   - Aller sur http://localhost:8000/admin/commandes/1
   - Voir statut: `en_attente`
   - Voir transitions disponibles: "Valider" et "Annuler"

2. **Appliquer transition "Valider":**
   - Cliquer sur "Valider la commande"
   - ✅ Page recharge, statut = `validee`
   - 🔥 Nouvel event `commande.status_changed` dans l'historique:
     ```
     old_value: en_attente
     new_value: validee
     triggered_by: admin@test.com
     reason: Transition: valider
     ```

3. **État après validation: validee**
   - Voir statut: `validee`
   - Voir transitions disponibles: "Expédier" et "Annuler"
   - ❌ Le bouton "Valider" n'est plus disponible (transition impossible)

4. **Appliquer transition "Expédier":**
   - Cliquer sur "Expédier la commande"
   - ✅ Statut = `expediee`
   - 🔥 Nouvel event dans l'historique

5. **État final: expediee**
   - Voir transitions disponibles: "Livrer"
   - Cliquer → Statut = `livree`
   - ❌ Aucune transition disponible (état final)

---

### Scénario 3: Tester les Guards - Règles métier

**Test 1: Expédier sans adresse**

1. Créer une commande SANS remplir l'adresse
2. Admin: Valider la commande (passe à `validee`)
3. Essayer d'expédier:
   - ❌ Le bouton "Expédier" ne sera PAS disponible
   - Raison: Guard vérifie `if (empty($adresse))` → bloque

**Test 2: Valider avec total = 0**

1. Créer une commande (modifier en base pour mettre total = 0)
2. Essayer de valider:
   - ❌ Transition bloquée
   - Message: "Le montant doit être supérieur à 0"
   - Raison: Guard vérifie `if ($total <= 0)` → bloque

---

### Scénario 4: Visualiser l'historique complet

1. **Faire plusieurs actions sur une commande:**
   - Créer → Valider → Expédier → Livrer

2. **Voir l'historique:**
   - http://localhost:8000/admin/commandes/1
   - Section "Historique (Event Sourcing)"
   - ✅ Voir 4 événements:
     ```
     1. commande.created
        • Par: user@test.com
        • IP: 192.168.1.100
        • Le: 14/02/2026 10:30:15
     
     2. commande.status_changed (en_attente → validee)
        • Par: admin@test.com
        • IP: 192.168.1.50
        • Le: 14/02/2026 10:35:22
     
     3. commande.status_changed (validee → expediee)
        • Par: admin@test.com
        • IP: 192.168.1.50
        • Le: 14/02/2026 11:00:05
     
     4. commande.status_changed (expediee → livree)
        • Par: admin@test.com
        • IP: 192.168.1.50
        • Le: 14/02/2026 11:30:42
     ```

3. **Cliquer sur "Voir les données complètes":**
   - JSON avec toutes les infos:
   ```json
   {
     "commande_id": 1,
     "numero_commande": "CMD-20260214103015",
     "total": "49.99",
     "timestamp": "2026-02-14 10:30:15",
     "items_count": 3
   }
   ```

---

## 🔍 VÉRIFIER EN BASE DE DONNÉES

### Via Symfony Console

```powershell
# Voir toutes les commandes
php bin/console doctrine:query:sql "SELECT id, numero_commande, statut, total FROM commande"

# Voir l'Event Store d'une commande
php bin/console doctrine:query:sql "SELECT e.id, e.event_type, e.old_value, e.new_value, e.created_at, u.email FROM commande_event e LEFT JOIN user u ON e.triggered_by_id = u.id WHERE e.commande_id = 1 ORDER BY e.created_at"

# Compter les events par type
php bin/console doctrine:query:sql "SELECT event_type, COUNT(*) as count FROM commande_event GROUP BY event_type"
```

### Via Adminer (GUI)

**URL:** http://localhost:8000/adminer-5.4.2.php

1. Se connecter à la base MySQL
2. Table `commande`: Voir les commandes
3. Table `commande_event`: Voir l'Event Store avec IP, users, timestamps
4. Table `user`: Voir les utilisateurs qui ont fait les actions

---

## 📊 CE QUI EST DIFFÉRENT MAINTENANT

### ❌ AVANT (Sans patterns avancés)

**URL:** http://localhost:8000/commandes/1
- Simple affichage de la commande
- Statut statique
- Aucun historique
- Pas de workflow
- Changement de statut manuel en base

### ✅ APRÈS (Avec patterns avancés)

**URL:** http://localhost:8000/admin/commandes/1
- ✅ Workflow State Machine: transitions dynamiques
- ✅ Guards: règles métier automatiques
- ✅ Event Sourcing: historique complet
- ✅ Traçabilité: qui, quand, où, pourquoi
- ✅ Audit trail: conforme légal
- ✅ UI enrichie: métadonnées, icônes, couleurs
- ✅ Tests faciles: workflow isolé

---

## 🎯 CHECKLIST DE TEST

- [ ] Créer une commande depuis le panier
- [ ] Voir la commande créée dans /commandes
- [ ] Se connecter en admin
- [ ] Aller sur /admin/commandes
- [ ] Voir les statistiques par statut
- [ ] Cliquer sur "Détails & Workflow" d'une commande
- [ ] Voir les transitions disponibles (section Workflow)
- [ ] Appliquer une transition valide (ex: Valider)
- [ ] Voir le nouvel événement dans l'historique
- [ ] Vérifier les infos: utilisateur, IP, date, raison
- [ ] Essayer une transition impossible (bouton absent)
- [ ] Appliquer toutes les transitions jusqu'à "livree"
- [ ] Voir l'historique complet avec tous les events
- [ ] Vérifier en base: table `commande_event`

---

## 🚀 COMMANDES RAPIDES

```powershell
# Démarrer
symfony serve

# Admin URLs
start http://localhost:8000/admin/commandes

# Voir logs en temps réel
symfony server:log

# Debug workflow
php bin/console debug:workflow commande_workflow

# Voir les events
php bin/console doctrine:query:sql "SELECT * FROM commande_event ORDER BY created_at DESC LIMIT 10"
```

---

## 📚 DOCUMENTATION COMPLÈTE

- **[GUIDE_TEST_PATTERNS_METIER.md](GUIDE_TEST_PATTERNS_METIER.md)** - Guide détaillé
- **[AVANT_APRES_COMPARAISON.md](AVANT_APRES_COMPARAISON.md)** - Code avant/après
- **[bin/demo_patterns.php](bin/demo_patterns.php)** - Script de démonstration

---

## ✅ RÉSULTAT ATTENDU

Après avoir testé, vous devriez voir:

1. ✅ **Workflow fonctionnel:** Transitions validées automatiquement
2. ✅ **Event Store rempli:** Historique complet de toutes les actions
3. ✅ **Guards actifs:** Règles métier qui bloquent les transitions invalides
4. ✅ **Traçabilité:** Tous les changements avec utilisateur, IP, date
5. ✅ **UI intuitive:** Boutons dynamiques, diagramme workflow, timeline events

**🎉 Les patterns métier avancés fonctionnent !**
