<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260221113000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add patient rating fields on urgence_alerte';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE urgence_alerte ADD patient_rating INT DEFAULT NULL, ADD rated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE urgence_alerte DROP patient_rating, DROP rated_at');
    }
}
