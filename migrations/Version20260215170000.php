<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260215170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add relation between activite and etat (activite.etat_id)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activite ADD etat_id INT DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_ACTIVITE_ETAT_ID ON activite (etat_id)');
        $this->addSql('ALTER TABLE activite ADD CONSTRAINT FK_ACTIVITE_ETAT_ID FOREIGN KEY (etat_id) REFERENCES etat (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activite DROP FOREIGN KEY FK_ACTIVITE_ETAT_ID');
        $this->addSql('DROP INDEX IDX_ACTIVITE_ETAT_ID ON activite');
        $this->addSql('ALTER TABLE activite DROP etat_id');
    }
}
