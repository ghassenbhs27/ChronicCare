<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260209100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add genre column to users table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE users ADD genre VARCHAR(10) DEFAULT 'Homme' NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP genre');
    }
}
