<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260221203000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add email 2FA auth code storage for users';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD email_auth_code VARCHAR(10) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP email_auth_code');
    }
}
