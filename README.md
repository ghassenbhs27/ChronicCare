# 🩺 ChronicCare Symfony — Module Événement

![Symfony](https://img.shields.io/badge/Symfony-Framework-black?style=for-the-badge)
![PHP](https://img.shields.io/badge/PHP-8.x-purple?style=for-the-badge)
![MySQL](https://img.shields.io/badge/MySQL-Database-lightblue?style=for-the-badge)
![Twig](https://img.shields.io/badge/Twig-Templates-green?style=for-the-badge)
![Status](https://img.shields.io/badge/Status-En%20développement-yellow?style=for-the-badge)

---

## 📌 Présentation

**ChronicCare** est une application web développée avec **Symfony** pour le suivi médical, le bien-être et le coaching.

Cette version est dédiée au **module Événement**.  
Elle permet aux coachs de créer et gérer des événements liés à la santé, au sport et au bien-être, tandis que les patients peuvent consulter les événements disponibles et y participer.

---

## ✨ Fonctionnalités principales

### 👨‍🏫 Côté Coach

- Créer un événement
- Modifier un événement existant
- Supprimer un événement
- Ajouter une image à un événement
- Gérer les places disponibles
- Consulter les participations des patients
- Générer une description avec l’IA

### 🧑‍⚕️ Côté Patient

- Consulter la liste des événements
- Voir les détails d’un événement
- Participer à un événement
- Consulter les informations importantes :
  - Date
  - Lieu
  - Description
  - Capacité
  - Places disponibles

---

## 🚀 Fonctionnalités avancées

| Fonctionnalité | Description |
|---|---|
| 🤖 Suggestions IA | Génération automatique de descriptions d’événements |
| 🌦️ Météo | Affichage de la météo liée aux événements |
| 🖼️ Upload d’images | Ajout d’images pour illustrer les événements |
| 🔳 QR Code | Génération de QR Codes pour les événements |
| 👥 Participation | Gestion des patients inscrits aux événements |
| 🎨 Interface Web | Pages créées avec Twig, CSS et Bootstrap |

---

## 🛠️ Technologies utilisées

| Technologie | Utilisation |
|---|---|
| Symfony | Framework PHP principal |
| PHP 8.x | Langage backend |
| MySQL | Base de données |
| Doctrine ORM | Gestion des entités et requêtes |
| Twig | Moteur de templates |
| Bootstrap / CSS | Style et interface utilisateur |
| Composer | Gestion des dépendances |
| OpenAI API | Suggestions de descriptions |
| API météo | Informations météorologiques |
| QR Code Bundle | Génération de QR Codes |

---

## 📁 Structure utile du projet

```text
src/Controller        Contrôleurs Symfony
src/Entity            Entités Doctrine
src/Repository        Requêtes personnalisées
src/Form              Formulaires Symfony
templates/            Vues Twig
public/               Fichiers publics
public/uploads        Images uploadées
config/               Configuration Symfony
migrations/           Migrations de base de données
```

---

## ▶️ Lancement du projet

### Prérequis

- PHP 8.x
- Composer
- Symfony CLI
- MySQL actif
- Base de données ChronicCare configurée

### Installation des dépendances

```bash
composer install
```

### Configuration de l’environnement

Créer ou modifier le fichier `.env.local` :

```env
DATABASE_URL="mysql://root:@127.0.0.1:3306/chroniccare?serverVersion=8.0"
```

### Création de la base de données

```bash
php bin/console doctrine:database:create
```

### Exécution des migrations

```bash
php bin/console doctrine:migrations:migrate
```

### Lancement du serveur Symfony

```bash
symfony server:start
```

Ou avec PHP :

```bash
php -S localhost:8000 -t public
```

---

## ⚙️ Configuration

### Base de données

La connexion à la base de données se fait dans le fichier :

```text
.env.local
```

Exemple :

```env
DATABASE_URL="mysql://root:@127.0.0.1:3306/chroniccare?serverVersion=8.0"
```

---

### OpenAI

L’API OpenAI peut être utilisée pour générer automatiquement des descriptions d’événements.

Exemple de configuration dans `.env.local` :

```env
OPENAI_API_KEY=VOTRE_CLE_OPENAI
OPENAI_MODEL=gpt-5.4-mini
```

---

### Uploads

Les images des événements sont stockées dans :

```text
public/uploads
```

---

## 🧩 Entités principales

| Entité | Rôle |
|---|---|
| Event | Représente un événement |
| User | Représente les utilisateurs |
| Participation | Gère l’inscription des patients aux événements |

---

## 🔄 Workflow du module Événement

1. Le coach crée un événement.
2. Il ajoute les informations nécessaires : titre, description, date, lieu, capacité et image.
3. Il peut utiliser l’IA pour générer une description.
4. Le patient consulte les événements disponibles.
5. Le patient participe à un événement.
6. Le système met à jour les places disponibles.
7. Un QR Code peut être généré pour l’événement.

---

## ⚠️ Remarques importantes

- Les clés API doivent rester locales.
- Le fichier `.env.local` ne doit pas être envoyé sur GitHub.
- Les images uploadées doivent être stockées dans `public/uploads`.
- Les migrations doivent être exécutées après toute modification des entités.
- Le dossier `vendor/` peut être régénéré avec Composer.

---

## 👤 Auteur

Module **Événement** développé dans le cadre du projet **ChronicCare Symfony**.
