<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260221133000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create discussion_message table for patient/pro messaging';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE discussion_message (id INT AUTO_INCREMENT NOT NULL, sender_id INT NOT NULL, recipient_id INT NOT NULL, content LONGTEXT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', read_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_C6D8CD8CF624B39D (sender_id), INDEX IDX_C6D8CD8CE92F8F78 (recipient_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE discussion_message ADD CONSTRAINT FK_C6D8CD8CF624B39D FOREIGN KEY (sender_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE discussion_message ADD CONSTRAINT FK_C6D8CD8CE92F8F78 FOREIGN KEY (recipient_id) REFERENCES users (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE discussion_message DROP FOREIGN KEY FK_C6D8CD8CF624B39D');
        $this->addSql('ALTER TABLE discussion_message DROP FOREIGN KEY FK_C6D8CD8CE92F8F78');
        $this->addSql('DROP TABLE discussion_message');
    }
}
