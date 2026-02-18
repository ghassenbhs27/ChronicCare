<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260218195000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add traitement_en_cours and remarques_cliniques to etat, make valeur nullable';
    }

    public function up(Schema $schema): void
    {
        $table = $this->connection->createSchemaManager()->introspectTable('etat');

        if (!$table->hasColumn('traitement_en_cours')) {
            $this->addSql('ALTER TABLE etat ADD traitement_en_cours VARCHAR(255) DEFAULT NULL');
        }
        if (!$table->hasColumn('remarques_cliniques')) {
            $this->addSql('ALTER TABLE etat ADD remarques_cliniques LONGTEXT DEFAULT NULL');
        }
        if ($table->hasColumn('valeur')) {
            $this->addSql('ALTER TABLE etat MODIFY valeur DOUBLE PRECISION DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        $table = $this->connection->createSchemaManager()->introspectTable('etat');

        if ($table->hasColumn('traitement_en_cours')) {
            $this->addSql('ALTER TABLE etat DROP traitement_en_cours');
        }
        if ($table->hasColumn('remarques_cliniques')) {
            $this->addSql('ALTER TABLE etat DROP remarques_cliniques');
        }
        if ($table->hasColumn('valeur')) {
            $this->addSql('ALTER TABLE etat MODIFY valeur DOUBLE PRECISION NOT NULL');
        }
    }
}
