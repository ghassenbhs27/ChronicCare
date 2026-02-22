<?php

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration to add nested replies support to comments
 */
final class Version20260222_AddCommentReplies extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add parent_id field to commentaire table for nested replies';
    }

    public function up(Schema $schema): void
    {
        // Add parent_id column (nullable, self-referencing)
        $this->addSql('ALTER TABLE commentaire ADD parent_id INT DEFAULT NULL');
        
        // Add foreign key constraint
        $this->addSql('ALTER TABLE commentaire ADD CONSTRAINT FK_67F068BC727ACA70 FOREIGN KEY (parent_id) REFERENCES commentaire (id) ON DELETE CASCADE');
        
        // Add index for performance
        $this->addSql('CREATE INDEX IDX_67F068BC727ACA70 ON commentaire (parent_id)');
    }

    public function down(Schema $schema): void
    {
        // Remove foreign key and column
        $this->addSql('ALTER TABLE commentaire DROP FOREIGN KEY FK_67F068BC727ACA70');
        $this->addSql('DROP INDEX IDX_67F068BC727ACA70 ON commentaire');
        $this->addSql('ALTER TABLE commentaire DROP parent_id');
    }
}