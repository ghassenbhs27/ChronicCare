# 🩺 ChronicCare Symfony

![Symfony](https://img.shields.io/badge/Symfony-Framework-black?style=for-the-badge)
![PHP](https://img.shields.io/badge/PHP-8.x-purple?style=for-the-badge)
![MySQL](https://img.shields.io/badge/MySQL-Database-lightblue?style=for-the-badge)
![Twig](https://img.shields.io/badge/Twig-Templates-green?style=for-the-badge)
![Doctrine](https://img.shields.io/badge/Doctrine-ORM-orange?style=for-the-badge)
![Status](https://img.shields.io/badge/Status-In%20Development-yellow?style=for-the-badge)

---

## 📌 Overview

**ChronicCare** is a web application developed with **Symfony** for healthcare monitoring, wellness, nutrition, and coaching.

The project brings together several modules for managing users, appointments, nutrition consultations, events, exercises, posts, notifications, payments, and AI-powered assistance.

---

## 👥 Application Roles

The application supports several types of users:

| Role | Description |
|---|---|
| Admin | Manages users, statistics, and the platform's overall data |
| Patient | Views their profile, books appointments, participates in events, and tracks their activities |
| Coach | Manages events and exercises and supports patients |
| Nutritionist | Manages nutrition appointments and consultations |

---

## ✨ Main Features

### 🔐 Authentication and User Management

- User registration and login
- Role management
- Profile management
- Role-based access control
- User administration

---

### 🧑‍⚕️ Patient Module

- View patient profiles
- Monitor patient health status
- Track activities
- Access personal history
- Receive personalized recommendations
- Participate in events
- Access consultations and appointments

---

### 🥗 Nutrition Module

- Book appointments with a nutritionist
- Approve or reject appointment requests
- Reschedule appointments
- Create nutrition consultations
- Generate consultation drafts using AI
- Potential integration with Open Food Facts

---

### 🏃 Coach Module

- Create events
- Edit and delete events
- Manage exercises
- Generate description suggestions using AI
- Track patient participation

---

### 📅 Event Module

- Create events
- Edit events
- Delete events
- Browse available events
- Manage patient participation
- Manage available seats
- Upload images
- Generate QR codes
- Display weather information related to events

---

### 💪 Exercise Module

- Create exercises
- Edit exercises
- Delete exercises
- Browse exercises
- Generate exercise descriptions using AI
- Classify exercises according to patient needs

---

### 💬 Forum and Posts

- Create posts
- Browse posts
- Enable interactions between users
- Provide a space for discussions about health, wellness, and coaching

---

### 🔔 Notifications

- Appointment-related notifications
- Event-related notifications
- Consultation-related notifications
- Improved user activity tracking

---

### 💳 Payments

- Online payment integration
- Transaction management
- Potential integration with the Stripe API

---

### 🤖 AI Assistance

Artificial intelligence is used to simplify certain tasks within the application.

| AI Service | Use Case |
|---|---|
| OpenAI | Suggestions for event and exercise descriptions |
| Gemini | Generation of nutrition consultation drafts |

---

## 🚀 Advanced Features

| Feature | Description |
|---|---|
| Secure Authentication | Login and role-based access control |
| Admin Dashboard | Centralized platform management |
| AI Integration | Assistance with writing and content generation |
| Image Uploads | Add images to events and other content |
| QR Codes | Generate QR codes for events |
| Weather Integration | Display weather information associated with events |
| Online Payments | Stripe integration |
| Email / SMTP | Send emails from the application |
| External APIs | OpenAI, Gemini, Open Food Facts, weather services, and Stripe |

---

## 🛠️ Technologies Used

| Technology | Purpose |
|---|---|
| Symfony | Main backend framework |
| PHP 8.x | Backend programming language |
| MySQL | Database |
| Doctrine ORM | Entity and database query management |
| Twig | Template engine |
| Bootstrap / CSS | User interface |
| Composer | Dependency management |
| Symfony Security | Authentication and authorization |
| Symfony Forms | Form management |
| Symfony Validator | Data validation |
| OpenAI API | AI-powered suggestions |
| Gemini API | Nutrition-related content generation |
| Open Food Facts API | Nutritional data |
| Stripe API | Online payments |
| SMTP | Email delivery |

---

## 📁 Project Structure

```text
src/
├── Controller/        Symfony controllers
├── Entity/            Doctrine entities
├── Repository/        Database queries
├── Form/              Symfony forms
├── Service/           Business logic services
└── Security/          Security and authentication

templates/             Twig templates
public/                Public files
public/uploads/        Uploaded files
config/                Symfony configuration
migrations/            Database migrations
var/                   Cache and logs
vendor/                Composer dependencies
```

---

## ▶️ Running the Project

### Prerequisites

Before running the project, make sure you have:

- PHP 8.x
- Composer
- Symfony CLI
- MySQL running
- A configured database

---

### Installing Dependencies

```bash
composer install
```

---

### Environment Configuration

Create or edit the following file:

```text
.env.local
```

Example configuration:

```env
APP_ENV=dev
APP_SECRET=YOUR_APP_SECRET

DATABASE_URL="mysql://root:@127.0.0.1:3306/chroniccare?serverVersion=8.0"
```

---

### Creating the Database

```bash
php bin/console doctrine:database:create
```

---

### Running Database Migrations

```bash
php bin/console doctrine:migrations:migrate
```

---

### Starting the Server

Using Symfony CLI:

```bash
symfony server:start
```

Or using PHP:

```bash
php -S localhost:8000 -t public
```

---

## ⚙️ Configuring External Services

### Database

Database configuration is managed in:

```text
.env.local
```

Example:

```env
DATABASE_URL="mysql://root:@127.0.0.1:3306/chroniccare?serverVersion=8.0"
```

---

### OpenAI

OpenAI can be used to generate suggestions for event and exercise descriptions.

Example:

```env
OPENAI_API_KEY=YOUR_OPENAI_API_KEY
OPENAI_MODEL=gpt-5.4-mini
```

---

### Gemini

Gemini can be used to generate nutrition consultation drafts.

Example:

```env
GEMINI_API_KEY=YOUR_GEMINI_API_KEY
GEMINI_MODEL=gemini-2.5-flash
```

---

### Stripe

Stripe can be used to handle online payments.

Example:

```env
STRIPE_SECRET_KEY=YOUR_STRIPE_SECRET_KEY
STRIPE_PUBLIC_KEY=YOUR_STRIPE_PUBLISHABLE_KEY
```

---

### Email / SMTP

Example configuration for sending emails:

```env
MAILER_DSN=smtp://your_email@gmail.com:application_password@smtp.gmail.com:587
```

For Gmail, it is recommended to use an **app password** instead of your main account password.

---

## 🧩 Main Entities

| Entity | Purpose |
|---|---|
| User | Represents application users |
| Patient | Stores patient-related information |
| Coach | Stores coach-related information |
| Nutritionist | Stores nutritionist-related information |
| RendezVous | Manages appointments |
| Consultation | Manages nutrition consultations |
| Event | Represents events |
| Participation | Manages patient participation in events |
| Exercice | Represents available exercises |
| Publication | Represents forum posts |
| Notification | Manages notifications |
| Paiement | Manages payments |

---

## 🔄 General Workflow

1. The user creates an account or logs in.
2. The system identifies the user's role.
3. Each user accesses the features associated with their role.
4. Patients can book appointments, browse events, and track their activities.
5. Coaches can manage events and exercises.
6. Nutritionists can process appointment requests and create consultations.
7. Administrators can oversee the platform.
8. External services such as AI, weather, email, and payment providers enhance the user experience.

---

## 🔐 Security

- Access is restricted according to user roles.
- Sensitive data must be stored in `.env.local`.
- API keys must never be published on GitHub.
- Passwords must be securely stored.
- Private configuration files must be excluded through `.gitignore`.

---

## 📌 Important Notes

- The `.env.local` file must never be uploaded to GitHub.
- The `vendor/` directory can be regenerated using Composer.
- The `var/` directory contains cache files and logs.
- Database migrations must be executed after modifying entities.
- Uploaded files must be stored in `public/uploads/`.
- API keys must remain local and confidential.

---

## 👤 Author

**ChronicCare Symfony** is a multi-module web application developed to support healthcare monitoring, nutrition, coaching, and wellness.
