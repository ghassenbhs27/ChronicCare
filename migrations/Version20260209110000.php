<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260209110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add adresse column to commande table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE commande ADD adresse LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE commande DROP adresse');
    }
}
