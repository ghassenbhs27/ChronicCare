<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajout des colonnes verification_token et verification_token_expires_at
 * pour la vérification d'email à l'inscription.
 */
final class Version20260210130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add verification_token and verification_token_expires_at to users for email verification';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD verification_token VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD verification_token_expires_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_users_verification_token ON users (verification_token)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_users_verification_token ON users');
        $this->addSql('ALTER TABLE users DROP verification_token, DROP verification_token_expires_at');
    }
}
