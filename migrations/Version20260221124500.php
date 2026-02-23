<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260221124500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add etat_snapshot JSON on suivi_analyse';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE suivi_analyse ADD etat_snapshot JSON NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE suivi_analyse DROP etat_snapshot');
    }
}
