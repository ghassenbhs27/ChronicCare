<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260508001000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Hotfix login: ensure users.last_seen_at exists for User entity mapping';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD COLUMN IF NOT EXISTS last_seen_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP COLUMN IF EXISTS last_seen_at');
    }
}
