<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260206014843 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE activite (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(100) NOT NULL, duree INT NOT NULL, calories INT NOT NULL, date_activite DATETIME NOT NULL, notes LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, utilisateur_id INT NOT NULL, INDEX IDX_B8755515FB88E14F (utilisateur_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE commande (id INT AUTO_INCREMENT NOT NULL, numero_commande VARCHAR(50) NOT NULL, statut VARCHAR(50) NOT NULL, total NUMERIC(10, 2) NOT NULL, created_at DATETIME NOT NULL, utilisateur_id INT NOT NULL, UNIQUE INDEX UNIQ_6EEAA67DCFFD611D (numero_commande), INDEX IDX_6EEAA67DFB88E14F (utilisateur_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE commentaire (id INT AUTO_INCREMENT NOT NULL, contenu LONGTEXT NOT NULL, is_anonymous TINYINT NOT NULL, like_count INT NOT NULL, created_at DATETIME NOT NULL, publication_id INT NOT NULL, auteur_id INT NOT NULL, INDEX IDX_67F068BC38B217A7 (publication_id), INDEX IDX_67F068BC60BB6FE6 (auteur_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE consultation (id INT AUTO_INCREMENT NOT NULL, notes LONGTEXT NOT NULL, diagnostic LONGTEXT DEFAULT NULL, date_consultation DATETIME NOT NULL, rdv_id INT NOT NULL, UNIQUE INDEX UNIQ_964685A64CCE3F86 (rdv_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE etat (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(100) NOT NULL, valeur DOUBLE PRECISION NOT NULL, unite VARCHAR(50) NOT NULL, date_releve DATETIME NOT NULL, utilisateur_id INT NOT NULL, INDEX IDX_55CAF762FB88E14F (utilisateur_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE evenement (id INT AUTO_INCREMENT NOT NULL, titre VARCHAR(200) NOT NULL, description LONGTEXT NOT NULL, date_debut DATETIME NOT NULL, date_fin DATETIME NOT NULL, lieu VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, coach_id INT NOT NULL, INDEX IDX_B26681E3C105691 (coach_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE exercice (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(200) NOT NULL, description LONGTEXT NOT NULL, duree INT NOT NULL, repetitions INT DEFAULT NULL, evenement_id INT DEFAULT NULL, INDEX IDX_E418C74DFD02F13 (evenement_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE ligne_commande (id INT AUTO_INCREMENT NOT NULL, quantite INT NOT NULL, prix_unitaire NUMERIC(10, 2) NOT NULL, sous_total NUMERIC(10, 2) NOT NULL, remise NUMERIC(5, 2) DEFAULT NULL, commande_id INT NOT NULL, produit_id INT NOT NULL, INDEX IDX_3170B74B82EA2E54 (commande_id), INDEX IDX_3170B74BF347EFB (produit_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE produit (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(200) NOT NULL, description LONGTEXT NOT NULL, prix NUMERIC(10, 2) NOT NULL, stock INT NOT NULL, categorie VARCHAR(100) NOT NULL, is_active TINYINT NOT NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE publication (id INT AUTO_INCREMENT NOT NULL, titre VARCHAR(255) NOT NULL, contenu LONGTEXT NOT NULL, categorie VARCHAR(100) DEFAULT NULL, nb_vues INT NOT NULL, nb_likes INT NOT NULL, statut VARCHAR(50) NOT NULL, created_at DATETIME NOT NULL, auteur_id INT NOT NULL, INDEX IDX_AF3C677960BB6FE6 (auteur_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE rdv (id INT AUTO_INCREMENT NOT NULL, date_rdv DATETIME NOT NULL, motif LONGTEXT DEFAULT NULL, statut VARCHAR(50) NOT NULL, created_at DATETIME NOT NULL, patient_id INT NOT NULL, professionnel_id INT NOT NULL, INDEX IDX_10C31F866B899279 (patient_id), INDEX IDX_10C31F868A49CC82 (professionnel_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE users (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(100) NOT NULL, prenom VARCHAR(100) NOT NULL, email VARCHAR(180) NOT NULL, password VARCHAR(255) NOT NULL, roles JSON NOT NULL, telephone VARCHAR(20) DEFAULT NULL, date_naissance DATE DEFAULT NULL, adresse LONGTEXT DEFAULT NULL, maladie_chronique VARCHAR(255) DEFAULT NULL, is_active TINYINT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, UNIQUE INDEX UNIQ_1483A5E9E7927C74 (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE activite ADD CONSTRAINT FK_B8755515FB88E14F FOREIGN KEY (utilisateur_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE commande ADD CONSTRAINT FK_6EEAA67DFB88E14F FOREIGN KEY (utilisateur_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE commentaire ADD CONSTRAINT FK_67F068BC38B217A7 FOREIGN KEY (publication_id) REFERENCES publication (id)');
        $this->addSql('ALTER TABLE commentaire ADD CONSTRAINT FK_67F068BC60BB6FE6 FOREIGN KEY (auteur_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE consultation ADD CONSTRAINT FK_964685A64CCE3F86 FOREIGN KEY (rdv_id) REFERENCES rdv (id)');
        $this->addSql('ALTER TABLE etat ADD CONSTRAINT FK_55CAF762FB88E14F FOREIGN KEY (utilisateur_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE evenement ADD CONSTRAINT FK_B26681E3C105691 FOREIGN KEY (coach_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE exercice ADD CONSTRAINT FK_E418C74DFD02F13 FOREIGN KEY (evenement_id) REFERENCES evenement (id)');
        $this->addSql('ALTER TABLE ligne_commande ADD CONSTRAINT FK_3170B74B82EA2E54 FOREIGN KEY (commande_id) REFERENCES commande (id)');
        $this->addSql('ALTER TABLE ligne_commande ADD CONSTRAINT FK_3170B74BF347EFB FOREIGN KEY (produit_id) REFERENCES produit (id)');
        $this->addSql('ALTER TABLE publication ADD CONSTRAINT FK_AF3C677960BB6FE6 FOREIGN KEY (auteur_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE rdv ADD CONSTRAINT FK_10C31F866B899279 FOREIGN KEY (patient_id) REFERENCES users (id)');
        $this->addSql('ALTER TABLE rdv ADD CONSTRAINT FK_10C31F868A49CC82 FOREIGN KEY (professionnel_id) REFERENCES users (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE activite DROP FOREIGN KEY FK_B8755515FB88E14F');
        $this->addSql('ALTER TABLE commande DROP FOREIGN KEY FK_6EEAA67DFB88E14F');
        $this->addSql('ALTER TABLE commentaire DROP FOREIGN KEY FK_67F068BC38B217A7');
        $this->addSql('ALTER TABLE commentaire DROP FOREIGN KEY FK_67F068BC60BB6FE6');
        $this->addSql('ALTER TABLE consultation DROP FOREIGN KEY FK_964685A64CCE3F86');
        $this->addSql('ALTER TABLE etat DROP FOREIGN KEY FK_55CAF762FB88E14F');
        $this->addSql('ALTER TABLE evenement DROP FOREIGN KEY FK_B26681E3C105691');
        $this->addSql('ALTER TABLE exercice DROP FOREIGN KEY FK_E418C74DFD02F13');
        $this->addSql('ALTER TABLE ligne_commande DROP FOREIGN KEY FK_3170B74B82EA2E54');
        $this->addSql('ALTER TABLE ligne_commande DROP FOREIGN KEY FK_3170B74BF347EFB');
        $this->addSql('ALTER TABLE publication DROP FOREIGN KEY FK_AF3C677960BB6FE6');
        $this->addSql('ALTER TABLE rdv DROP FOREIGN KEY FK_10C31F866B899279');
        $this->addSql('ALTER TABLE rdv DROP FOREIGN KEY FK_10C31F868A49CC82');
        $this->addSql('DROP TABLE activite');
        $this->addSql('DROP TABLE commande');
        $this->addSql('DROP TABLE commentaire');
        $this->addSql('DROP TABLE consultation');
        $this->addSql('DROP TABLE etat');
        $this->addSql('DROP TABLE evenement');
        $this->addSql('DROP TABLE exercice');
        $this->addSql('DROP TABLE ligne_commande');
        $this->addSql('DROP TABLE produit');
        $this->addSql('DROP TABLE publication');
        $this->addSql('DROP TABLE rdv');
        $this->addSql('DROP TABLE users');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
