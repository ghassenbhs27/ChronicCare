<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260220120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add reset password 6-digit code storage table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE reset_password_code (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, code_hash VARCHAR(64) NOT NULL, reset_token VARCHAR(255) NOT NULL, expires_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', used_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX idx_reset_password_code_email (email), INDEX idx_reset_password_code_expires_at (expires_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE reset_password_code');
    }
}
