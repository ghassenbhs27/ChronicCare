<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260221123000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add medical condition to users and create medical_dossier table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD medical_condition VARCHAR(120) DEFAULT NULL');
        $this->addSql("CREATE TABLE medical_dossier (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, disease VARCHAR(120) NOT NULL, questionnaire JSON NOT NULL, answers JSON NOT NULL, ai_summary LONGTEXT DEFAULT NULL, emergency_risk_level VARCHAR(20) NOT NULL DEFAULT 'unknown', emergency_possible TINYINT(1) NOT NULL DEFAULT 0, emergency_reason LONGTEXT DEFAULT NULL, recommended_actions JSON NOT NULL, ai_mode VARCHAR(40) NOT NULL DEFAULT 'FALLBACK', created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', updated_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', UNIQUE INDEX uniq_medical_dossier_user (user_id), INDEX IDX_8EA47E9BA76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql('ALTER TABLE medical_dossier ADD CONSTRAINT FK_8EA47E9BA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE medical_dossier DROP FOREIGN KEY FK_8EA47E9BA76ED395');
        $this->addSql('DROP TABLE medical_dossier');
        $this->addSql('ALTER TABLE users DROP medical_condition');
    }
}
