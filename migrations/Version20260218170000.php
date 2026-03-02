<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260218170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove unite column from etat table';
    }

    public function up(Schema $schema): void
    {
        $table = $this->connection->createSchemaManager()->introspectTable('etat');
        if (!$table->hasColumn('unite')) {
            return;
        }

        $this->addSql('ALTER TABLE etat DROP unite');
    }

    public function down(Schema $schema): void
    {
        $table = $this->connection->createSchemaManager()->introspectTable('etat');
        if ($table->hasColumn('unite')) {
            return;
        }

        $this->addSql('ALTER TABLE etat ADD unite VARCHAR(50) NOT NULL');
    }
}
