<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260222000000 extends AbstractMigration
{
   public function up(Schema $schema): void
{
    // 1. Supprimer la foreign key liée à l'index
    $this->addSql('ALTER TABLE activite DROP FOREIGN KEY FK_ACTIVITE_ETAT_ID');
    
    // 2. Supprimer l'ancien index
    $this->addSql('ALTER TABLE activite DROP INDEX IDX_ACTIVITE_ETAT_ID');
    
    // 3. Recréer le bon index
    $this->addSql('ALTER TABLE activite ADD INDEX IDX_B8755515D5E86FF (etat_id)');
    
    // 4. Recréer la foreign key
    $this->addSql('ALTER TABLE activite ADD CONSTRAINT FK_ACTIVITE_ETAT_ID FOREIGN KEY (etat_id) REFERENCES etat (id)');

    // Le reste
    $this->addSql('ALTER TABLE commentaire DROP sticker_url, DROP dislike_count, CHANGE font_style font_style VARCHAR(50) DEFAULT NULL, CHANGE font_size font_size VARCHAR(20) DEFAULT NULL, CHANGE text_color text_color VARCHAR(7) DEFAULT NULL, CHANGE updated_at updated_at DATETIME DEFAULT NULL, CHANGE background_color background_color VARCHAR(7) DEFAULT NULL');
    $this->addSql('ALTER TABLE inscription_evenement CHANGE telephone telephone VARCHAR(20) DEFAULT NULL');
    $this->addSql('ALTER TABLE ligne_commande CHANGE remise remise NUMERIC(5, 2) DEFAULT NULL');
    $this->addSql('ALTER TABLE notification CHANGE lien lien VARCHAR(255) DEFAULT NULL');
    $this->addSql('ALTER TABLE publication CHANGE categorie categorie VARCHAR(100) DEFAULT NULL, CHANGE image_url image_url VARCHAR(255) DEFAULT NULL');
    $this->addSql('ALTER TABLE users CHANGE roles roles JSON NOT NULL, CHANGE telephone telephone VARCHAR(20) DEFAULT NULL, CHANGE updated_at updated_at DATETIME DEFAULT NULL, CHANGE verification_token verification_token VARCHAR(64) DEFAULT NULL, CHANGE verification_token_expires_at verification_token_expires_at DATETIME DEFAULT NULL, CHANGE requested_role requested_role VARCHAR(30) DEFAULT NULL, CHANGE approval_status approval_status VARCHAR(20) DEFAULT \'pending\' NOT NULL, CHANGE photo_profil photo_profil VARCHAR(255) DEFAULT NULL');
}

public function down(Schema $schema): void
{
    $this->addSql('ALTER TABLE activite DROP FOREIGN KEY FK_ACTIVITE_ETAT_ID');
    $this->addSql('ALTER TABLE activite DROP INDEX IDX_B8755515D5E86FF');
    $this->addSql('ALTER TABLE activite ADD INDEX IDX_ACTIVITE_ETAT_ID (etat_id)');
    $this->addSql('ALTER TABLE activite ADD CONSTRAINT FK_ACTIVITE_ETAT_ID FOREIGN KEY (etat_id) REFERENCES etat (id)');
}
}