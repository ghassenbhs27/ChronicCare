<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260221153000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add alert category, business status and status history for security alerts';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE security_alert ADD alert_type VARCHAR(30) NOT NULL DEFAULT 'securitaire', ADD alert_status VARCHAR(20) NOT NULL DEFAULT 'en_cours', ADD status_history JSON DEFAULT NULL");
        $this->addSql("UPDATE security_alert SET alert_type = CASE WHEN event_type IN ('failed_login', 'reset_password_request', 'email_change') THEN 'securitaire' WHEN event_type = 'user_action' THEN 'comportementale' ELSE 'technique' END");
        $this->addSql("UPDATE security_alert SET alert_status = CASE WHEN is_resolved = 1 THEN 'traitee' ELSE 'en_cours' END");
        $this->addSql("UPDATE security_alert SET status_history = JSON_ARRAY(JSON_OBJECT('status', alert_status, 'changed_at', DATE_FORMAT(COALESCE(resolved_at, created_at), '%Y-%m-%d %H:%i:%s'), 'source', 'migration', 'note', 'Statut initialise automatiquement')) WHERE status_history IS NULL");
        $this->addSql('CREATE INDEX idx_security_alert_alert_type_time ON security_alert (alert_type, created_at)');
        $this->addSql('CREATE INDEX idx_security_alert_alert_status_time ON security_alert (alert_status, created_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_security_alert_alert_status_time ON security_alert');
        $this->addSql('DROP INDEX idx_security_alert_alert_type_time ON security_alert');
        $this->addSql('ALTER TABLE security_alert DROP alert_type, DROP alert_status, DROP status_history');
    }
}
