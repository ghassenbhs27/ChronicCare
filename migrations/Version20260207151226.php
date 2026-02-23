<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260207151226 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE activite CHANGE type type VARCHAR(80) NOT NULL, CHANGE date_activite date_activite DATE NOT NULL');
        $this->addSql('ALTER TABLE etat CHANGE type type VARCHAR(80) NOT NULL, CHANGE unite unite VARCHAR(30) NOT NULL, CHANGE date_releve date_releve DATE NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE activite CHANGE type type VARCHAR(100) NOT NULL, CHANGE date_activite date_activite DATETIME NOT NULL');
        $this->addSql('ALTER TABLE etat CHANGE type type VARCHAR(100) NOT NULL, CHANGE unite unite VARCHAR(50) NOT NULL, CHANGE date_releve date_releve DATETIME NOT NULL');
    }
}
