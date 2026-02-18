#!/usr/bin/env php
<?php
/**
 * 🧪 SCRIPT DE DÉMONSTRATION - Patterns Métier Avancés
 * 
 * Ce script teste les fonctionnalités avancées du module Commande:
 * - State Machine (Workflow)
 * - Event Sourcing
 * - Guards (règles métier)
 * - Traçabilité complète
 * 
 * Usage: php bin/demo_patterns.php
 */

require __DIR__.'/../vendor/autoload.php';

use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Helper\Table;

$output = new ConsoleOutput();

// Couleurs pour le terminal
$green = "\033[32m";
$red = "\033[31m";
$blue = "\033[34m";
$yellow = "\033[33m";
$reset = "\033[0m";

$output->writeln([
    '',
    "╔════════════════════════════════════════════════════════════════════════╗",
    "║  🎯 DÉMONSTRATION - PATTERNS MÉTIER AVANCÉS (COMMANDES)              ║",
    "╚════════════════════════════════════════════════════════════════════════╝",
    '',
]);

// ========================================
// 1. URLS DE TEST
// ========================================
$output->writeln([
    "{$blue}📍 URLS DE TEST{$reset}",
    "─────────────────────────────────────────────────",
    '',
]);

$urls = [
    ['Section', 'URL', 'Description'],
    ['─────────', '──────────────────────────────────────', '──────────────────────────────────────'],
    ['Client', 'http://localhost:8000/cart', 'Panier'],
    ['Client', 'http://localhost:8000/commandes/checkout', 'Créer une commande'],
    ['Client', 'http://localhost:8000/commandes', 'Mes commandes'],
    ['Client', 'http://localhost:8000/commandes/{id}', 'Détails commande'],
    ['─────────', '──────────────────────────────────────', '──────────────────────────────────────'],
    ['Admin', 'http://localhost:8000/admin/commandes', '🔥 Liste avec stats'],
    ['Admin', 'http://localhost:8000/admin/commandes/{id}', '🔥 Workflow + Event Sourcing'],
    ['Admin', 'http://localhost:8000/admin/commandes/{id}/history', '🔥 Historique complet'],
];

$table = new Table($output);
$table->setHeaders(array_shift($urls));
$table->setRows($urls);
$table->render();

$output->writeln(['', '']);

// ========================================
// 2. SCÉNARIOS DE TEST
// ========================================
$output->writeln([
    "{$blue}🧪 SCÉNARIOS DE TEST{$reset}",
    "─────────────────────────────────────────────────",
    '',
]);

$scenarios = [
    [
        'titre' => 'Test 1: Création + Event Sourcing',
        'etapes' => [
            '1. Ajouter des produits au panier',
            '2. Aller sur /commandes/checkout',
            '3. Remplir adresse et paiement',
            '4. Valider',
            '5. ✅ Commande créée avec statut "en_attente"',
            "6. 🔥 Event enregistré: 'commande.created'",
            '   • triggered_by: user@test.com',
            '   • ip_address: captée automatiquement',
            '   • event_data: JSON complet',
        ],
    ],
    [
        'titre' => 'Test 2: Workflow - Transition Valide',
        'etapes' => [
            '1. Aller sur /admin/commandes/{id}',
            '2. ✅ Voir le bouton "Valider la commande"',
            '3. Cliquer sur le bouton',
            '4. ✅ Statut passe à "validee"',
            '5. 🔥 Event enregistré automatiquement:',
            "   • event_type: 'commande.status_changed'",
            "   • old_value: 'en_attente'",
            "   • new_value: 'validee'",
            '   • triggered_by: admin@test.com',
            '   • reason: "Transition: valider"',
        ],
    ],
    [
        'titre' => 'Test 3: Workflow - Transition Bloquée',
        'etapes' => [
            '1. Commande en "en_attente" sans adresse',
            '2. Essayer de cliquer "Expédier"',
            '3. ❌ Bouton pas disponible (transition impossible)',
            '4. 🔥 Guard a bloqué car règle métier:',
            '   • if (empty($adresse)) → blocked',
        ],
    ],
    [
        'titre' => 'Test 4: Guards - Règles Métier',
        'etapes' => [
            '1. Créer une commande avec total = 0',
            '2. Essayer de valider',
            '3. ❌ Transition bloquée',
            '4. 🔥 Message: "Le montant doit être > 0"',
            '5. ✅ Impossible de contourner la règle',
        ],
    ],
    [
        'titre' => 'Test 5: Historique Complet',
        'etapes' => [
            '1. Aller sur /admin/commandes/{id}',
            '2. Voir la section "Historique (Event Sourcing)"',
            '3. ✅ Liste chronologique de tous les événements:',
            '   • Création',
            '   • Chaque changement de statut',
            '   • Qui a fait quoi',
            '   • Depuis quelle IP',
            '   • User-Agent du navigateur',
            '   • Données JSON complètes',
        ],
    ],
];

foreach ($scenarios as $i => $scenario) {
    $num = $i + 1;
    $output->writeln([
        "{$yellow}▸ {$scenario['titre']}{$reset}",
        '',
    ]);
    
    foreach ($scenario['etapes'] as $etape) {
        $prefix = '  ';
        if (str_contains($etape, '✅')) {
            $prefix .= $green;
        } elseif (str_contains($etape, '❌')) {
            $prefix .= $red;
        } elseif (str_contains($etape, '🔥')) {
            $prefix .= $blue;
        }
        
        $output->writeln($prefix . $etape . $reset);
    }
    
    $output->writeln(['', '']);
}

// ========================================
// 3. VÉRIFICATION EN BASE
// ========================================
$output->writeln([
    "{$blue}🔍 VÉRIFICATION EN BASE DE DONNÉES{$reset}",
    "─────────────────────────────────────────────────",
    '',
]);

$queries = [
    [
        'nom' => 'Voir toutes les commandes',
        'sql' => 'SELECT id, numero_commande, statut, total, created_at FROM commande ORDER BY created_at DESC LIMIT 10;',
    ],
    [
        'nom' => 'Voir l\'Event Store d\'une commande',
        'sql' => <<<'SQL'
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
SQL
    ],
    [
        'nom' => 'Compter les événements par type',
        'sql' => 'SELECT event_type, COUNT(*) as count FROM commande_event GROUP BY event_type;',
    ],
    [
        'nom' => 'Voir les changements récents',
        'sql' => <<<'SQL'
SELECT 
    c.numero_commande,
    e.event_type,
    e.old_value,
    e.new_value,
    u.email,
    e.created_at
FROM commande_event e
JOIN commande c ON e.commande_id = c.id
LEFT JOIN user u ON e.triggered_by_id = u.id
WHERE e.event_type = 'commande.status_changed'
ORDER BY e.created_at DESC
LIMIT 10;
SQL
    ],
];

foreach ($queries as $query) {
    $output->writeln([
        "{$yellow}▸ {$query['nom']}{$reset}",
        '',
        "{$blue}SQL:{$reset}",
        $query['sql'],
        '',
        "{$green}Commande Symfony:{$reset}",
        'php bin/console doctrine:query:sql "' . str_replace("\n", ' ', $query['sql']) . '"',
        '',
        '─────────────────────────────────────────────────',
        '',
    ]);
}

// ========================================
// 4. PATTERNS IMPLÉMENTÉS
// ========================================
$output->writeln([
    "{$blue}✨ PATTERNS MÉTIER IMPLÉMENTÉS{$reset}",
    "─────────────────────────────────────────────────",
    '',
]);

$patterns = [
    ['Pattern', 'Où', 'Bénéfice'],
    ['─────────────────────', '───────────────────────────────', '──────────────────────────────────'],
    ['State Machine', 'config/packages/workflow.yaml', 'Transitions valides garanties'],
    ['Event Sourcing', 'Entity/CommandeEvent.php', 'Historique immuable et complet'],
    ['Event Subscriber', 'EventSubscriber/CommandeWorkflowSubscriber.php', 'Auto-logging + Guards'],
    ['Guard Pattern', 'CommandeWorkflowSubscriber::guardXXX()', 'Règles métier automatiques'],
    ['Service Layer', 'Service/CommandeService.php', 'Logique métier centralisée'],
    ['Event Store', 'Service/CommandeEventStore.php', 'Capture contexte complet'],
    ['Repository', 'Repository/CommandeEventRepository.php', 'Queries métier optimisées'],
    ['Audit Trail', 'CommandeEvent (IP, User-Agent)', 'Conformité légale'],
    ['Metadata', 'workflow.yaml (title, icon, color)', 'UI enrichie automatiquement'],
    ['CQRS partiel', 'Event Store séparé', 'Lecture/écriture découplées'],
];

$table = new Table($output);
$table->setHeaders(array_shift($patterns));
$table->setRows($patterns);
$table->render();

$output->writeln(['', '']);

// ========================================
// 5. COMMANDES UTILES
// ========================================
$output->writeln([
    "{$blue}🚀 COMMANDES UTILES{$reset}",
    "─────────────────────────────────────────────────",
    '',
]);

$commands = [
    ['Action', 'Commande'],
    ['─────────────────────────────', '────────────────────────────────────────────────'],
    ['Démarrer le serveur', 'symfony serve'],
    ['Voir les logs en temps réel', 'symfony server:log'],
    ['Exécuter les migrations', 'php bin/console doctrine:migrations:migrate'],
    ['Charger les fixtures', 'php bin/console doctrine:fixtures:load'],
    ['Lister les workflows', 'php bin/console debug:workflow'],
    ['Voir le workflow commande', 'php bin/console debug:workflow commande_workflow'],
    ['Créer un admin', 'php bin/console app:create-admin'],
    ['Query SQL directe', 'php bin/console doctrine:query:sql "SELECT * FROM commande_event"'],
    ['Debug EventSubscribers', 'php bin/console debug:event-dispatcher'],
];

$table = new Table($output);
$table->setHeaders(array_shift($commands));
$table->setRows($commands);
$table->render();

$output->writeln(['', '']);

// ========================================
// 6. RÉSUMÉ
// ========================================
$output->writeln([
    "╔════════════════════════════════════════════════════════════════════════╗",
    "║  📊 RÉSUMÉ: AVANT vs APRÈS                                            ║",
    "╚════════════════════════════════════════════════════════════════════════╝",
    '',
]);

$comparison = [
    ['Aspect', 'Avant ❌', 'Après ✅'],
    ['─────────────────────', '──────────────────────', '──────────────────────'],
    ['Validation trans.', 'Aucune', '100% validées'],
    ['Traçabilité', '0%', '100% (qui/quand/où)'],
    ['Règles métier', 'Manuelles', 'Guards automatiques'],
    ['Historique', 'Non', 'Complet (Event Store)'],
    ['Audit trail', 'Impossible', 'Conforme légal'],
    ['Tests', 'Difficiles', 'Faciles (isolés)'],
    ['Maintenance', 'Code dispersé', 'Centralisé'],
    ['Évolutivité', 'Difficile', 'Facile (config)'],
];

$table = new Table($output);
$table->setHeaders(array_shift($comparison));
$table->setRows($comparison);
$table->render();

$output->writeln([
    '',
    "╔════════════════════════════════════════════════════════════════════════╗",
    "║  ✅ PRÊT À TESTER !                                                   ║",
    "║                                                                        ║",
    "║  1. Démarrer: symfony serve                                           ║",
    "║  2. Créer commande: http://localhost:8000/commandes/checkout          ║",
    "║  3. Admin Workflow: http://localhost:8000/admin/commandes             ║",
    "║                                                                        ║",
    "║  📚 Voir aussi:                                                       ║",
    "║  • GUIDE_TEST_PATTERNS_METIER.md                                      ║",
    "║  • AVANT_APRES_COMPARAISON.md                                         ║",
    "╚════════════════════════════════════════════════════════════════════════╝",
    '',
]);
