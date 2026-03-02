<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260218184000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add temperature/hydration to etat and distance/rest to activite';
    }

    public function up(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();

        $etat = $sm->introspectTable('etat');
        if (!$etat->hasColumn('temperature_corporelle')) {
            $this->addSql('ALTER TABLE etat ADD temperature_corporelle VARCHAR(10) DEFAULT NULL');
        }
        if (!$etat->hasColumn('niveau_hydratation')) {
            $this->addSql('ALTER TABLE etat ADD niveau_hydratation VARCHAR(10) DEFAULT NULL');
        }

        $activite = $sm->introspectTable('activite');
        if (!$activite->hasColumn('distance_km')) {
            $this->addSql('ALTER TABLE activite ADD distance_km DOUBLE PRECISION DEFAULT NULL');
        }
        if (!$activite->hasColumn('heures_repos')) {
            $this->addSql('ALTER TABLE activite ADD heures_repos INT DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        $sm = $this->connection->createSchemaManager();

        $etat = $sm->introspectTable('etat');
        if ($etat->hasColumn('temperature_corporelle')) {
            $this->addSql('ALTER TABLE etat DROP temperature_corporelle');
        }
        if ($etat->hasColumn('niveau_hydratation')) {
            $this->addSql('ALTER TABLE etat DROP niveau_hydratation');
        }

        $activite = $sm->introspectTable('activite');
        if ($activite->hasColumn('distance_km')) {
            $this->addSql('ALTER TABLE activite DROP distance_km');
        }
        if ($activite->hasColumn('heures_repos')) {
            $this->addSql('ALTER TABLE activite DROP heures_repos');
        }
    }
}
