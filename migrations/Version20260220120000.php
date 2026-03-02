<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260220120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add reset_password_code table for 6-digit OTP verification';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE reset_password_code (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, code_hash VARCHAR(128) NOT NULL, reset_token VARCHAR(255) NOT NULL, expires_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', used_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX idx_reset_password_code_user_expires (user_id, expires_at), INDEX idx_reset_password_code_used (used_at), INDEX IDX_4AB78299A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql('ALTER TABLE reset_password_code ADD CONSTRAINT FK_4AB78299A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reset_password_code DROP FOREIGN KEY FK_4AB78299A76ED395');
        $this->addSql('DROP TABLE reset_password_code');
    }
}
