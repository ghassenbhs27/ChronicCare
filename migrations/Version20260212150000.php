<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration : Création de la table commande_event pour l'Event Sourcing
 */
final class Version20260212150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Création de la table commande_event pour le système d\'Event Sourcing et historique des commandes';
    }

    public function up(Schema $schema): void
    {
        // Création de la table commande_event
        $this->addSql('CREATE TABLE commande_event (
            id INT AUTO_INCREMENT NOT NULL,
            commande_id INT NOT NULL,
            triggered_by_id INT DEFAULT NULL,
            event_type VARCHAR(100) NOT NULL,
            event_data JSON NOT NULL,
            old_value VARCHAR(50) DEFAULT NULL,
            new_value VARCHAR(50) DEFAULT NULL,
            reason TEXT DEFAULT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            user_agent VARCHAR(255) DEFAULT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX idx_commande_date (commande_id, created_at),
            INDEX idx_event_type (event_type),
            INDEX IDX_commande_id (commande_id),
            INDEX IDX_triggered_by_id (triggered_by_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Clés étrangères
        $this->addSql('ALTER TABLE commande_event 
            ADD CONSTRAINT FK_commande_event_commande 
            FOREIGN KEY (commande_id) REFERENCES commande (id) ON DELETE CASCADE');

        $this->addSql('ALTER TABLE commande_event 
            ADD CONSTRAINT FK_commande_event_user 
            FOREIGN KEY (triggered_by_id) REFERENCES users (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE commande_event');
    }
}
