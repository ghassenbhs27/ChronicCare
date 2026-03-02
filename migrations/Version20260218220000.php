<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260218220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add presence and urgence alert support';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD last_seen_at DATETIME DEFAULT NULL');
        $this->addSql('CREATE TABLE urgence_alerte (id INT AUTO_INCREMENT NOT NULL, patient_id INT NOT NULL, professionnel_id INT NOT NULL, target_role VARCHAR(32) NOT NULL, meeting_url VARCHAR(255) NOT NULL, status VARCHAR(16) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', viewed_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_9F9CB3081D7F7B2 (patient_id), INDEX IDX_9F9CB30AB65E53F (professionnel_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE urgence_alerte ADD CONSTRAINT FK_9F9CB3081D7F7B2 FOREIGN KEY (patient_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE urgence_alerte ADD CONSTRAINT FK_9F9CB30AB65E53F FOREIGN KEY (professionnel_id) REFERENCES users (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE urgence_alerte DROP FOREIGN KEY FK_9F9CB3081D7F7B2');
        $this->addSql('ALTER TABLE urgence_alerte DROP FOREIGN KEY FK_9F9CB30AB65E53F');
        $this->addSql('DROP TABLE urgence_alerte');
        $this->addSql('ALTER TABLE users DROP last_seen_at');
    }
}

