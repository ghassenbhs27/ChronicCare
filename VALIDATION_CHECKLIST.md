# ✅ Checklist : Validation Côté Serveur - ChronicCare

## 📋 État actuel

### ✅ Déjà fait
- [x] Validateurs personnalisés créés (`ValidAddress`)
- [x] Entités améliorées avec @Assert
  - [x] Produit.php - Validations complètes
  - [x] Commande.php - Validations avec validateur personnalisé
  - [x] User.php - Téléphone validé
- [x] FormTypes avancés créés
  - [x] AdvancedProduitFormType.php
  - [x] AdvancedCommandeFormType.php
  - [x] AdvancedUserFormType.php
- [x] Templates Twig créés
  - [x] form_errors.html.twig - Affichage erreurs globales
  - [x] form_field.html.twig - Champs avec erreurs
  - [x] produit/create.html.twig - Exemple
- [x] Tests de validation créés
- [x] Documentation complète

## 🔄 À appliquer

### 1. Améliorer les autres entités

- [ ] **Activite.php**
  - [ ] Ajouter @Assert sur tous les champs
  - [ ] Créer ActiviteFormType si nécessaire
  
- [ ] **Commentaire.php**
  - [ ] Ajouter validation sur contenu
  - [ ] Ajouter validation sur note
  - [ ] Créer CommentaireFormType
  
- [ ] **Consultation.php**
  - [ ] Ajouter validations
  - [ ] Créer ConsultationFormType
  
- [ ] **Etat.php**
  - [ ] Ajouter validations
  - [ ] Créer EtatFormType complet
  
- [ ] **Evenement.php**
  - [ ] Ajouter validations dates
  - [ ] Ajouter validations descriptions
  - [ ] Créer EvenementFormType complet
  
- [ ] **Exercice.php**
  - [ ] Ajouter validations
  - [ ] Créer ExerciceFormType complet
  
- [ ] **InscriptionEvenement.php**
  - [ ] Ajouter validations
  - [ ] Créer InscriptionEvenementFormType complet
  
- [ ] **LigneCommande.php**
  - [ ] Ajouter validations quantité
  - [ ] Ajouter validations prix
  - [ ] Créer LigneCommandeFormType
  
- [ ] **Publication.php**
  - [ ] Ajouter validations contenu
  - [ ] Ajouter validations titre
  - [ ] Créer PublicationFormType
  
- [ ] **RDV.php**
  - [ ] Ajouter validations dates/heures
  - [ ] Ajouter validations statut
  - [ ] Créer RDVFormType complet

### 2. Créer FormTypes manquants

**FormTypes prioritaires:**
- [ ] CommandeFormType.php (complet avec LigneCommandes)
- [ ] LigneCommandeFormType.php
- [ ] EvenementFormType.php (complet)
- [ ] EvenementProfType.php (si custom)
- [ ] ExerciceFormType.php (complet)
- [ ] ActiviteFormType.php (complet)

**FormTypes avec moins de priorité:**
- [ ] CommentaireFormType.php
- [ ] ConsultationFormType.php
- [ ] EtatFormType.php (complet)
- [ ] PublicationFormType.php

### 3. Mettre à jour les Contrôleurs

**Contrôleurs existants à modifier:**
- [ ] CommandeController.php
- [ ] ProduitController.php
- [ ] UserController.php
- [ ] EvenementController.php
- [ ] ExerciceController.php
- [ ] ActiviteController.php
- [ ] CommentaireController.php
- [ ] PublicationController.php
- [ ] RDVController.php

**Pattern à appliquer:**
```php
$form = $this->createForm(AdvancedXxxxFormType::class, $entity);
$form->handleRequest($request);

if ($form->isSubmitted() && $form->isValid()) {
    // Persister
    $em->persist($entity);
    $em->flush();
    // Rediriger
}

// Afficher le formulaire avec erreurs
return $this->render('xxx/create.html.twig', [
    'form' => $form->createView(),
]);
```

### 4. Créer/Adapter les Templates

**Templates à créer/adapter:**
- [ ] commande/new.html.twig
- [ ] commande/edit.html.twig
- [ ] produit/new.html.twig (utilisé dans create.html.twig)
- [ ] produit/edit.html.twig
- [ ] user/profile.html.twig
- [ ] user/edit.html.twig
- [ ] evenement/new.html.twig
- [ ] evenement/edit.html.twig
- [ ] exercice/new.html.twig
- [ ] exercice/edit.html.twig
- [ ] activite/new.html.twig
- [ ] publication/new.html.twig
- [ ] commentaire/edit.html.twig
- [ ] rdv/new.html.twig
- [ ] rdv/reschedule.html.twig

**Pattern à utiliser:**
```twig
{% include 'partials/form_errors.html.twig' %}

<form method="post">
    {{ form_row(form._token) }}
    {% include 'partials/form_field.html.twig' with {'field': form.fieldName} %}
    <button type="submit">Valider</button>
</form>
```

### 5. Tests

- [ ] Tester validation Produit
- [ ] Tester validation Commande
- [ ] Tester validation User
- [ ] Tester validation Activite
- [ ] Tester validation Evenement
- [ ] Tester validation Exercice
- [ ] Tester tous les contrôleurs

**Commande pour lancer les tests:**
```bash
php bin/phpunit tests/ValidationTest.php
```

### 6. Documentation

- [ ] Documenter les règles métier de validation
- [ ] Créer des exemples pour chaque entité
- [ ] Former l'équipe aux validations Symfony

## 🎯 Priorité d'implémentation

### 🔴 HAUTE PRIORITÉ
1. **Commande & LigneCommande** - Cœur du système e-commerce
2. **User & Profil** - Données critiques
3. **Produit** - Déjà fait ✅

### 🟡 MOYENNE PRIORITÉ
4. **Evenement** - Important pour gestion
5. **RDV** - Important pour scheduling
6. **Exercice** - Important pour coaching

### 🟢 BASSE PRIORITÉ
7. **Activite** - Support/tracking
8. **Publication** - Contenu
9. **Commentaire** - Engagement
10. **Consultation** - Optionnel

## 📊 Progression

```
Total entités: 15
Entités complètes: 3 (Produit, Commande, User)
Entités en cours: 0
Entités à faire: 12

Progression: 20%
```

## 🚀 Prochaines étapes

1. **Cette semaine:**
   - [ ] Améliorer Evenement
   - [ ] Améliorer RDV
   - [ ] Améliorer Exercice
   - [ ] Créer les FormTypes

2. **Semaine prochaine:**
   - [ ] Adapter tous les contrôleurs
   - [ ] Créer/adapter tous les templates
   - [ ] Tester complètement

3. **Semaine d'après:**
   - [ ] Former l'équipe
   - [ ] Déployer en production
   - [ ] Monitorer les erreurs

## 💾 Fichiers créés/modifiés

### Créés ✨
- `src/Validator/ValidAddress.php`
- `src/Validator/ValidAddressValidator.php`
- `src/Form/AdvancedProduitFormType.php`
- `src/Form/AdvancedCommandeFormType.php`
- `src/Form/AdvancedUserFormType.php`
- `src/Controller/ValidationExampleController.php`
- `templates/partials/form_errors.html.twig`
- `templates/partials/form_field.html.twig`
- `templates/produit/create.html.twig`
- `tests/ValidationTest.php`
- `VALIDATION_GUIDE.md`

### Modifiés 📝
- `src/Entity/Produit.php` → Validations complètes
- `src/Entity/Commande.php` → Validations améliorées + ValidAddress
- `src/Entity/User.php` → Téléphone validé

## 📖 Documentation

- Voir `VALIDATION_GUIDE.md` pour le guide complet
- Voir `src/Controller/ValidationExampleController.php` pour les exemples
- Voir `tests/ValidationTest.php` pour les tests

## 🤝 Support

Pour toute question sur l'implémentation:
1. Consulter `VALIDATION_GUIDE.md`
2. Regarder les exemples dans `ValidationExampleController.php`
3. Lancer les tests: `php bin/phpunit`

---

**Dernière mise à jour:** 12 février 2026
**Responsable:** Rayan
**Statut:** En cours
