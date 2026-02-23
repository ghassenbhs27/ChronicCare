<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260218202000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop type and valeur columns from etat table';
    }

    public function up(Schema $schema): void
    {
        $table = $this->connection->createSchemaManager()->introspectTable('etat');

        if ($table->hasColumn('type')) {
            $this->addSql('ALTER TABLE etat DROP type');
        }
        if ($table->hasColumn('valeur')) {
            $this->addSql('ALTER TABLE etat DROP valeur');
        }
    }

    public function down(Schema $schema): void
    {
        $table = $this->connection->createSchemaManager()->introspectTable('etat');

        if (!$table->hasColumn('type')) {
            $this->addSql('ALTER TABLE etat ADD type VARCHAR(100) DEFAULT NULL');
        }
        if (!$table->hasColumn('valeur')) {
            $this->addSql('ALTER TABLE etat ADD valeur DOUBLE PRECISION DEFAULT NULL');
        }
    }
}
