<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260224013440 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // Add missing columns to support RDV and Consultation features from module-rdv
        $this->addSql('ALTER TABLE consultation ADD resume LONGTEXT DEFAULT NULL, ADD conseils LONGTEXT DEFAULT NULL, ADD document_name VARCHAR(255) DEFAULT NULL, ADD document_original_name VARCHAR(255) DEFAULT NULL, ADD document_mime_type VARCHAR(100) DEFAULT NULL, ADD document_size INT DEFAULT NULL, ADD updated_at DATETIME DEFAULT NULL, ADD ai_generated_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE rdv ADD conseils_preconsultation LONGTEXT DEFAULT NULL, ADD reminder_sent_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE rdv DROP conseils_preconsultation, DROP reminder_sent_at');
        $this->addSql('ALTER TABLE consultation DROP resume, DROP conseils, DROP document_name, DROP document_original_name, DROP document_mime_type, DROP document_size, DROP updated_at, DROP ai_generated_at');
    }
}
