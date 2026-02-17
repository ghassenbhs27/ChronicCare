<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260215193000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add requested_role and approval_status to users for admin account approval workflow';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE users ADD requested_role VARCHAR(30) DEFAULT NULL, ADD approval_status VARCHAR(20) NOT NULL DEFAULT 'pending'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP requested_role, DROP approval_status');
    }
}
