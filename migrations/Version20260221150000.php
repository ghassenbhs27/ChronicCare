<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260221150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add rich text formatting fields to commentaire table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE commentaire ADD IF NOT EXISTS font_style VARCHAR(50) DEFAULT 'normal'");
        $this->addSql("ALTER TABLE commentaire ADD IF NOT EXISTS font_size VARCHAR(20) DEFAULT 'medium'");
        $this->addSql("ALTER TABLE commentaire ADD IF NOT EXISTS text_color VARCHAR(7) DEFAULT NULL");
        $this->addSql("ALTER TABLE commentaire ADD IF NOT EXISTS background_color VARCHAR(7) DEFAULT NULL");
        $this->addSql("ALTER TABLE commentaire ADD IF NOT EXISTS updated_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'");
    }

    public function down(Schema $schema): void
    {
        if ($schema->getTable('commentaire')->hasColumn('font_style')) {
            $this->addSql('ALTER TABLE commentaire DROP font_style');
        }
        if ($schema->getTable('commentaire')->hasColumn('font_size')) {
            $this->addSql('ALTER TABLE commentaire DROP font_size');
        }
        if ($schema->getTable('commentaire')->hasColumn('text_color')) {
            $this->addSql('ALTER TABLE commentaire DROP text_color');
        }
        if ($schema->getTable('commentaire')->hasColumn('background_color')) {
            $this->addSql('ALTER TABLE commentaire DROP background_color');
        }
        if ($schema->getTable('commentaire')->hasColumn('updated_at')) {
            $this->addSql('ALTER TABLE commentaire DROP updated_at');
        }
    }
}
