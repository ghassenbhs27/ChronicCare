<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260221190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add medical dossier disease history and advanced medical audit logs';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE medical_dossier ADD disease_history JSON DEFAULT NULL');
        $this->addSql("UPDATE medical_dossier SET disease_history = JSON_ARRAY(JSON_OBJECT('disease', COALESCE(disease, 'autre'), 'questionnaire', questionnaire, 'answers', answers, 'analysis', JSON_OBJECT('summary', COALESCE(ai_summary, ''), 'risk_level', COALESCE(emergency_risk_level, 'unknown'), 'emergency_possible', emergency_possible, 'reason', COALESCE(emergency_reason, ''), 'recommended_actions', recommended_actions, 'mode', COALESCE(ai_mode, 'FALLBACK')), 'created_at', DATE_FORMAT(created_at, '%Y-%m-%d %H:%i:%s'), 'updated_at', DATE_FORMAT(COALESCE(updated_at, created_at), '%Y-%m-%d %H:%i:%s'), 'source', 'migration')) WHERE disease_history IS NULL");
        $this->addSql('ALTER TABLE medical_dossier MODIFY disease_history JSON NOT NULL');

        $this->addSql('CREATE TABLE medical_dossier_audit_log (id INT AUTO_INCREMENT NOT NULL, dossier_id INT NOT NULL, actor_id INT DEFAULT NULL, actor_email VARCHAR(180) DEFAULT NULL, actor_role VARCHAR(40) DEFAULT NULL, action VARCHAR(40) NOT NULL, target_disease VARCHAR(120) DEFAULT NULL, changed_fields JSON DEFAULT NULL, context JSON DEFAULT NULL, ip_address VARCHAR(45) DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX idx_medical_audit_action_time (action, created_at), INDEX idx_medical_audit_ip_time (ip_address, created_at), INDEX IDX_C6F645A4A667CFB6 (dossier_id), INDEX IDX_C6F645A4D44D760 (actor_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE medical_dossier_audit_log ADD CONSTRAINT FK_C6F645A4A667CFB6 FOREIGN KEY (dossier_id) REFERENCES medical_dossier (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE medical_dossier_audit_log ADD CONSTRAINT FK_C6F645A4D44D760 FOREIGN KEY (actor_id) REFERENCES users (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE medical_dossier_audit_log DROP FOREIGN KEY FK_C6F645A4A667CFB6');
        $this->addSql('ALTER TABLE medical_dossier_audit_log DROP FOREIGN KEY FK_C6F645A4D44D760');
        $this->addSql('DROP TABLE medical_dossier_audit_log');
        $this->addSql('ALTER TABLE medical_dossier DROP disease_history');
    }
}
