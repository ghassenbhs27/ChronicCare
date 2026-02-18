<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260209000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Integrate modules: inscription_evenement, user.telephone, nullable utilisateur';
    }

    public function up(Schema $schema): void
    {
        $conn = $this->connection;

        // Créer inscription_evenement (sans FK si evenement n'existe pas)
        $conn->executeStatement('CREATE TABLE IF NOT EXISTS inscription_evenement (
            id INT AUTO_INCREMENT NOT NULL,
            evenement_id INT NOT NULL,
            nom VARCHAR(100) NOT NULL,
            prenom VARCHAR(100) NOT NULL,
            email VARCHAR(180) NOT NULL,
            telephone VARCHAR(20) DEFAULT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_AD33AA06FD02F13 (evenement_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');

        // Ajouter la FK seulement si la table evenement existe
        $tables = $conn->createSchemaManager()->listTableNames();
        if (in_array('evenement', $tables, true)) {
            try {
                $conn->executeStatement('ALTER TABLE inscription_evenement ADD CONSTRAINT FK_AD33AA06FD02F13 FOREIGN KEY (evenement_id) REFERENCES evenement (id) ON DELETE CASCADE');
            } catch (\Throwable $e) {
                // La FK existe peut-être déjà, ignorer
            }
        }

        foreach (['activite', 'etat'] as $table) {
            if (in_array($table, $tables, true)) {
                try {
                    $conn->executeStatement("ALTER TABLE {$table} MODIFY utilisateur_id INT DEFAULT NULL");
                } catch (\Throwable $e) {}
            }
        }
    }

    public function down(Schema $schema): void
    {
        $conn = $this->connection;
        try {
            $conn->executeStatement('ALTER TABLE inscription_evenement DROP FOREIGN KEY FK_AD33AA06FD02F13');
        } catch (\Throwable $e) {}
        $conn->executeStatement('DROP TABLE IF EXISTS inscription_evenement');
        $tables = $conn->createSchemaManager()->listTableNames();
        foreach (['activite', 'etat'] as $table) {
            if (in_array($table, $tables, true)) {
                try {
                    $conn->executeStatement("ALTER TABLE {$table} MODIFY utilisateur_id INT NOT NULL");
                } catch (\Throwable $e) {}
            }
        }
    }
}
