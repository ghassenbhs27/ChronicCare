<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260210014317 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE activite ADD CONSTRAINT FK_B8755515FB88E14F FOREIGN KEY (utilisateur_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE commande ADD CONSTRAINT FK_6EEAA67DFB88E14F FOREIGN KEY (utilisateur_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE commentaire CHANGE auteur_id auteur_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE commentaire ADD CONSTRAINT FK_67F068BC38B217A7 FOREIGN KEY (publication_id) REFERENCES publication (id)');
        $this->addSql('ALTER TABLE commentaire ADD CONSTRAINT FK_67F068BC60BB6FE6 FOREIGN KEY (auteur_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE consultation ADD CONSTRAINT FK_964685A64CCE3F86 FOREIGN KEY (rdv_id) REFERENCES rdv (id)');
        $this->addSql('ALTER TABLE etat ADD CONSTRAINT FK_55CAF762FB88E14F FOREIGN KEY (utilisateur_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE evenement ADD CONSTRAINT FK_B26681E3C105691 FOREIGN KEY (coach_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE exercice ADD CONSTRAINT FK_E418C74DFD02F13 FOREIGN KEY (evenement_id) REFERENCES evenement (id)');
        $this->addSql('ALTER TABLE inscription_evenement ADD CONSTRAINT FK_AD33AA06FD02F13 FOREIGN KEY (evenement_id) REFERENCES evenement (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE ligne_commande ADD CONSTRAINT FK_3170B74B82EA2E54 FOREIGN KEY (commande_id) REFERENCES commande (id)');
        $this->addSql('ALTER TABLE ligne_commande ADD CONSTRAINT FK_3170B74BF347EFB FOREIGN KEY (produit_id) REFERENCES produit (id)');
        $this->addSql('ALTER TABLE publication CHANGE auteur_id auteur_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE publication ADD CONSTRAINT FK_AF3C677960BB6FE6 FOREIGN KEY (auteur_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE rdv ADD CONSTRAINT FK_10C31F866B899279 FOREIGN KEY (patient_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE rdv ADD CONSTRAINT FK_10C31F868A49CC82 FOREIGN KEY (professionnel_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE reset_password_request CHANGE requested_at requested_at DATETIME NOT NULL, CHANGE expires_at expires_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE reset_password_request ADD CONSTRAINT FK_7CE748AA76ED395 FOREIGN KEY (user_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE users DROP date_naissance, DROP adresse, DROP maladie_chronique, CHANGE nom nom VARCHAR(50) NOT NULL, CHANGE prenom prenom VARCHAR(50) NOT NULL, CHANGE is_active is_active TINYINT DEFAULT 1 NOT NULL, CHANGE genre genre VARCHAR(10) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE activite DROP FOREIGN KEY FK_B8755515FB88E14F');
        $this->addSql('ALTER TABLE commande DROP FOREIGN KEY FK_6EEAA67DFB88E14F');
        $this->addSql('ALTER TABLE commentaire DROP FOREIGN KEY FK_67F068BC38B217A7');
        $this->addSql('ALTER TABLE commentaire DROP FOREIGN KEY FK_67F068BC60BB6FE6');
        $this->addSql('ALTER TABLE commentaire CHANGE auteur_id auteur_id INT NOT NULL');
        $this->addSql('ALTER TABLE consultation DROP FOREIGN KEY FK_964685A64CCE3F86');
        $this->addSql('ALTER TABLE etat DROP FOREIGN KEY FK_55CAF762FB88E14F');
        $this->addSql('ALTER TABLE evenement DROP FOREIGN KEY FK_B26681E3C105691');
        $this->addSql('ALTER TABLE exercice DROP FOREIGN KEY FK_E418C74DFD02F13');
        $this->addSql('ALTER TABLE inscription_evenement DROP FOREIGN KEY FK_AD33AA06FD02F13');
        $this->addSql('ALTER TABLE ligne_commande DROP FOREIGN KEY FK_3170B74B82EA2E54');
        $this->addSql('ALTER TABLE ligne_commande DROP FOREIGN KEY FK_3170B74BF347EFB');
        $this->addSql('ALTER TABLE publication DROP FOREIGN KEY FK_AF3C677960BB6FE6');
        $this->addSql('ALTER TABLE publication CHANGE auteur_id auteur_id INT NOT NULL');
        $this->addSql('ALTER TABLE rdv DROP FOREIGN KEY FK_10C31F866B899279');
        $this->addSql('ALTER TABLE rdv DROP FOREIGN KEY FK_10C31F868A49CC82');
        $this->addSql('ALTER TABLE reset_password_request DROP FOREIGN KEY FK_7CE748AA76ED395');
        $this->addSql('ALTER TABLE reset_password_request CHANGE requested_at requested_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE expires_at expires_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE users ADD date_naissance DATE DEFAULT NULL, ADD adresse LONGTEXT DEFAULT NULL, ADD maladie_chronique VARCHAR(255) DEFAULT NULL, CHANGE nom nom VARCHAR(100) NOT NULL, CHANGE prenom prenom VARCHAR(100) NOT NULL, CHANGE genre genre VARCHAR(10) DEFAULT \'Homme\' NOT NULL, CHANGE is_active is_active TINYINT NOT NULL');
    }
}
