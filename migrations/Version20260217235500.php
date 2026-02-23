<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260217235500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add security monitoring logs, security alerts, and temporary user block column';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE users ADD security_blocked_until DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'");
        $this->addSql('CREATE TABLE security_event_log (id INT AUTO_INCREMENT NOT NULL, user_id INT DEFAULT NULL, event_type VARCHAR(64) NOT NULL, identifier VARCHAR(180) DEFAULT NULL, ip_address VARCHAR(45) DEFAULT NULL, route VARCHAR(120) DEFAULT NULL, occurred_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', context JSON DEFAULT NULL, INDEX idx_security_event_type_time (event_type, occurred_at), INDEX idx_security_event_ip_time (ip_address, occurred_at), INDEX IDX_46E6AB9AA76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE security_alert (id INT AUTO_INCREMENT NOT NULL, user_id INT DEFAULT NULL, event_type VARCHAR(64) NOT NULL, severity VARCHAR(20) NOT NULL, risk_score INT NOT NULL DEFAULT 0, message LONGTEXT NOT NULL, ip_address VARCHAR(45) DEFAULT NULL, context JSON DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', resolved_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', is_resolved TINYINT(1) NOT NULL DEFAULT 0, INDEX idx_security_alert_type_time (event_type, created_at), INDEX idx_security_alert_resolved_time (is_resolved, created_at), INDEX IDX_75A23A57A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE security_event_log ADD CONSTRAINT FK_46E6AB9AA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE security_alert ADD CONSTRAINT FK_75A23A57A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE security_event_log DROP FOREIGN KEY FK_46E6AB9AA76ED395');
        $this->addSql('ALTER TABLE security_alert DROP FOREIGN KEY FK_75A23A57A76ED395');
        $this->addSql('DROP TABLE security_event_log');
        $this->addSql('DROP TABLE security_alert');
        $this->addSql('ALTER TABLE users DROP security_blocked_until');
    }
}
