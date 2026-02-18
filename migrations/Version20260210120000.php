<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajout du champ statut sur evenement (en_attente, valide, refuse) pour la validation admin.
 */
final class Version20260210120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add statut column to evenement for admin validation workflow';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE evenement ADD statut VARCHAR(20) NOT NULL DEFAULT \'valide\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE evenement DROP statut');
    }
}
