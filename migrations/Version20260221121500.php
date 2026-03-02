<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260221121500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create suivi_analyse table for 7-day AI patient analysis';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE suivi_analyse (id INT AUTO_INCREMENT NOT NULL, patient_id INT NOT NULL, auteur_id INT DEFAULT NULL, period_start DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', period_end DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', content LONGTEXT NOT NULL, status VARCHAR(16) NOT NULL, sent_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_D6A627B81D7F7B2 (patient_id), INDEX IDX_D6A627B860BB6FE6 (auteur_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE suivi_analyse ADD CONSTRAINT FK_D6A627B81D7F7B2 FOREIGN KEY (patient_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE suivi_analyse ADD CONSTRAINT FK_D6A627B860BB6FE6 FOREIGN KEY (auteur_id) REFERENCES users (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE suivi_analyse DROP FOREIGN KEY FK_D6A627B81D7F7B2');
        $this->addSql('ALTER TABLE suivi_analyse DROP FOREIGN KEY FK_D6A627B860BB6FE6');
        $this->addSql('DROP TABLE suivi_analyse');
    }
}
