# 🩺 ChronicCare Symfony

![Symfony](https://img.shields.io/badge/Symfony-Framework-black?style=for-the-badge)
![PHP](https://img.shields.io/badge/PHP-8.x-purple?style=for-the-badge)
![MySQL](https://img.shields.io/badge/MySQL-Database-lightblue?style=for-the-badge)
![Twig](https://img.shields.io/badge/Twig-Templates-green?style=for-the-badge)
![Doctrine](https://img.shields.io/badge/Doctrine-ORM-orange?style=for-the-badge)
![Status](https://img.shields.io/badge/Status-En%20développement-yellow?style=for-the-badge)

---

## 📌 Présentation

**ChronicCare** est une application web développée avec **Symfony** dédiée au suivi médical, au bien-être, à la nutrition et au coaching.

Le projet regroupe plusieurs modules permettant de gérer les utilisateurs, les rendez-vous, les consultations nutritionnelles, les événements, les exercices, les publications, les notifications, les paiements et l’assistance par intelligence artificielle.

---

## 👥 Rôles dans l’application

L’application prend en charge plusieurs types d’utilisateurs :

| Rôle | Description |
|---|---|
| Admin | Gère les utilisateurs, les statistiques et les données globales de la plateforme |
| Patient | Consulte son profil, prend des rendez-vous, participe aux événements et suit ses activités |
| Coach | Gère les événements, les exercices et accompagne les patients |
| Nutritionniste | Gère les rendez-vous nutritionnels et les consultations |

---

## ✨ Fonctionnalités principales

### 🔐 Authentification et utilisateurs

- Inscription et connexion des utilisateurs
- Gestion des rôles
- Gestion des profils
- Sécurisation des accès selon le rôle
- Administration des utilisateurs

---

### 🧑‍⚕️ Module Patient

- Consultation du profil patient
- Suivi de l’état du patient
- Suivi des activités
- Historique personnel
- Recommandations personnalisées
- Participation aux événements
- Accès aux consultations et rendez-vous

---

### 🥗 Module Nutrition

- Prise de rendez-vous avec un nutritionniste
- Validation ou refus des rendez-vous
- Replanification des rendez-vous
- Création de consultations nutritionnelles
- Génération d’un brouillon de consultation avec l’IA
- Intégration possible avec Open Food Facts

---

### 🏃 Module Coach

- Création d’événements
- Modification et suppression d’événements
- Gestion des exercices
- Suggestions de descriptions avec l’IA
- Suivi des participations des patients

---

### 📅 Module Événement

- Création d’événements
- Modification d’événements
- Suppression d’événements
- Consultation des événements disponibles
- Participation des patients
- Gestion des places disponibles
- Upload d’images
- Génération de QR Codes
- Affichage de la météo liée aux événements

---

### 💪 Module Exercice

- Création d’exercices
- Modification d’exercices
- Suppression d’exercices
- Consultation des exercices
- Génération de descriptions avec l’IA
- Classification des exercices selon les besoins du patient

---

### 💬 Forum et publications

- Création de publications
- Consultation des publications
- Interaction entre utilisateurs
- Espace d’échange autour de la santé, du bien-être et du coaching

---

### 🔔 Notifications

- Notifications liées aux rendez-vous
- Notifications liées aux événements
- Notifications liées aux consultations
- Amélioration du suivi utilisateur

---

### 💳 Paiement

- Intégration du paiement en ligne
- Gestion des transactions
- Utilisation possible de Stripe API

---

### 🤖 Assistance IA

L’intelligence artificielle est utilisée pour faciliter certaines tâches dans l’application.

| IA | Utilisation |
|---|---|
| OpenAI | Suggestions de descriptions d’événements et d’exercices |
| Gemini | Génération de brouillons pour les consultations nutritionnelles |

---

## 🚀 Fonctionnalités avancées

| Fonctionnalité | Description |
|---|---|
| Authentification sécurisée | Connexion et gestion des accès selon les rôles |
| Dashboard Admin | Gestion globale de la plateforme |
| IA intégrée | Assistance à la rédaction et à la génération de contenu |
| Upload d’images | Ajout d’images pour les événements ou autres contenus |
| QR Code | Génération de QR Codes pour les événements |
| Météo | Informations météo associées aux événements |
| Paiement en ligne | Intégration Stripe |
| Email / SMTP | Envoi d’emails depuis l’application |
| API externes | OpenAI, Gemini, Open Food Facts, météo, Stripe |

---

## 🛠️ Technologies utilisées

| Technologie | Utilisation |
|---|---|
| Symfony | Framework backend principal |
| PHP 8.x | Langage backend |
| MySQL | Base de données |
| Doctrine ORM | Gestion des entités et des requêtes |
| Twig | Moteur de templates |
| Bootstrap / CSS | Interface utilisateur |
| Composer | Gestion des dépendances |
| Symfony Security | Authentification et autorisation |
| Symfony Forms | Gestion des formulaires |
| Symfony Validator | Validation des données |
| OpenAI API | Suggestions IA |
| Gemini API | Génération de contenu nutritionnel |
| Open Food Facts API | Données nutritionnelles |
| Stripe API | Paiement en ligne |
| SMTP | Envoi d’emails |

---

## 📁 Structure du projet

```text
src/
├── Controller/        Contrôleurs Symfony
├── Entity/            Entités Doctrine
├── Repository/        Requêtes vers la base de données
├── Form/              Formulaires Symfony
├── Service/           Services métier
└── Security/          Sécurité et authentification

templates/             Pages Twig
public/                Fichiers publics
public/uploads/        Fichiers uploadés
config/                Configuration Symfony
migrations/            Migrations de base de données
var/                   Cache et logs
vendor/                Dépendances Composer
```

---

## ▶️ Lancement du projet

### Prérequis

Avant de lancer le projet, il faut avoir :

- PHP 8.x
- Composer
- Symfony CLI
- MySQL actif
- Une base de données configurée

---

### Installation des dépendances

```bash
composer install
```

---

### Configuration de l’environnement

Créer ou modifier le fichier :

```text
.env.local
```

Exemple de configuration :

```env
APP_ENV=dev
APP_SECRET=VOTRE_APP_SECRET

DATABASE_URL="mysql://root:@127.0.0.1:3306/chroniccare?serverVersion=8.0"
```

---

### Création de la base de données

```bash
php bin/console doctrine:database:create
```

---

### Exécution des migrations

```bash
php bin/console doctrine:migrations:migrate
```

---

### Lancement du serveur

Avec Symfony CLI :

```bash
symfony server:start
```

Ou avec PHP :

```bash
php -S localhost:8000 -t public
```

---

## ⚙️ Configuration des services externes

### Base de données

La configuration de la base de données se fait dans :

```text
.env.local
```

Exemple :

```env
DATABASE_URL="mysql://root:@127.0.0.1:3306/chroniccare?serverVersion=8.0"
```

---

### OpenAI

OpenAI peut être utilisé pour générer des suggestions de descriptions pour les événements et les exercices.

Exemple :

```env
OPENAI_API_KEY=VOTRE_CLE_OPENAI
OPENAI_MODEL=gpt-5.4-mini
```

---

### Gemini

Gemini peut être utilisé pour générer un brouillon de consultation nutritionnelle.

Exemple :

```env
GEMINI_API_KEY=VOTRE_CLE_GEMINI
GEMINI_MODEL=gemini-2.5-flash
```

---

### Stripe

Stripe peut être utilisé pour la gestion du paiement en ligne.

Exemple :

```env
STRIPE_SECRET_KEY=VOTRE_CLE_STRIPE
STRIPE_PUBLIC_KEY=VOTRE_CLE_PUBLIQUE_STRIPE
```

---

### Email / SMTP

Configuration possible pour l’envoi d’emails :

```env
MAILER_DSN=smtp://votre_email@gmail.com:mot_de_passe_application@smtp.gmail.com:587
```

Pour Gmail, il est recommandé d’utiliser un **mot de passe d’application** et non le mot de passe principal du compte.

---

## 🧩 Entités principales

| Entité | Rôle |
|---|---|
| User | Représente les utilisateurs de l’application |
| Patient | Contient les informations liées au patient |
| Coach | Contient les informations liées au coach |
| Nutritionniste | Contient les informations liées au nutritionniste |
| RendezVous | Gère les rendez-vous |
| Consultation | Gère les consultations nutritionnelles |
| Event | Représente les événements |
| Participation | Gère la participation des patients aux événements |
| Exercice | Représente les exercices proposés |
| Publication | Représente les publications du forum |
| Notification | Gère les notifications |
| Paiement | Gère les paiements |

---

## 🔄 Workflow général

1. L’utilisateur crée un compte ou se connecte.
2. Le système identifie son rôle.
3. Chaque utilisateur accède aux fonctionnalités liées à son rôle.
4. Le patient peut prendre des rendez-vous, consulter les événements et suivre ses activités.
5. Le coach peut gérer les événements et les exercices.
6. Le nutritionniste peut traiter les demandes de rendez-vous et créer des consultations.
7. L’admin peut superviser la plateforme.
8. Les services externes comme l’IA, la météo, l’email ou le paiement enrichissent l’expérience utilisateur.

---

## 🔐 Sécurité

- Les accès sont limités selon les rôles.
- Les données sensibles doivent rester dans `.env.local`.
- Les clés API ne doivent jamais être publiées sur GitHub.
- Les mots de passe doivent être sécurisés.
- Les fichiers de configuration privés doivent être ignorés avec `.gitignore`.

---

## 📌 Remarques importantes

- Le fichier `.env.local` ne doit pas être envoyé sur GitHub.
- Le dossier `vendor/` peut être régénéré avec Composer.
- Le dossier `var/` contient le cache et les logs.
- Les migrations doivent être exécutées après modification des entités.
- Les fichiers uploadés doivent être stockés dans `public/uploads/`.
- Les clés API doivent rester locales.

---

## 👤 Auteur

Projet **ChronicCare Symfony** réalisé comme application web multi-modules dédiée au suivi santé, à la nutrition, au coaching et au bien-être.
