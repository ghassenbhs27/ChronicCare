<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260221100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add geolocation fields to commande table and remove adresse field';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE commande DROP COLUMN IF EXISTS adresse');
        $this->addSql('ALTER TABLE commande ADD COLUMN ip_address VARCHAR(45) NULL');
        $this->addSql('ALTER TABLE commande ADD COLUMN country VARCHAR(50) NULL');
        $this->addSql('ALTER TABLE commande ADD COLUMN city VARCHAR(50) NULL');
        $this->addSql('ALTER TABLE commande ADD COLUMN region VARCHAR(50) NULL');
        $this->addSql('ALTER TABLE commande ADD COLUMN latitude DOUBLE PRECISION NULL');
        $this->addSql('ALTER TABLE commande ADD COLUMN longitude DOUBLE PRECISION NULL');
        $this->addSql('ALTER TABLE commande ADD COLUMN timezone VARCHAR(50) NULL');
        $this->addSql('ALTER TABLE commande ADD COLUMN isp VARCHAR(100) NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE commande DROP COLUMN IF EXISTS ip_address');
        $this->addSql('ALTER TABLE commande DROP COLUMN IF EXISTS country');
        $this->addSql('ALTER TABLE commande DROP COLUMN IF EXISTS city');
        $this->addSql('ALTER TABLE commande DROP COLUMN IF EXISTS region');
        $this->addSql('ALTER TABLE commande DROP COLUMN IF EXISTS latitude');
        $this->addSql('ALTER TABLE commande DROP COLUMN IF EXISTS longitude');
        $this->addSql('ALTER TABLE commande DROP COLUMN IF EXISTS timezone');
        $this->addSql('ALTER TABLE commande DROP COLUMN IF EXISTS isp');
        $this->addSql('ALTER TABLE commande ADD COLUMN adresse LONGTEXT NULL');
    }
}
