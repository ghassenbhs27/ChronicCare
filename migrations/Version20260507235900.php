<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260507235900 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Align order/product schema with desktop baseline: add panier, wishlist, livraison, annulation_commande, and commande.motif_annulation';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE commande ADD motif_annulation VARCHAR(255) DEFAULT NULL');

        $this->addSql('CREATE TABLE annulation_commande (id INT AUTO_INCREMENT NOT NULL, commande_id INT NOT NULL, utilisateur_id INT NOT NULL, raison VARCHAR(500) DEFAULT NULL, statut_avant_annulation VARCHAR(50) DEFAULT NULL, montant_rembourse NUMERIC(10, 2) DEFAULT NULL, stock_restitue TINYINT(1) NOT NULL DEFAULT 1, date_annulation TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, motif_annulation VARCHAR(255) DEFAULT NULL, INDEX idx_commande_id (commande_id), INDEX idx_utilisateur_id (utilisateur_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE annulation_commande ADD CONSTRAINT fk_annulation_commande FOREIGN KEY (commande_id) REFERENCES commande (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE annulation_commande ADD CONSTRAINT fk_annulation_user FOREIGN KEY (utilisateur_id) REFERENCES users (id) ON DELETE CASCADE');

        $this->addSql('CREATE TABLE livraison (id INT AUTO_INCREMENT NOT NULL, commande_id INT NOT NULL, statut VARCHAR(50) NOT NULL, adresse VARCHAR(255) NOT NULL, ville VARCHAR(100) NOT NULL, code_postal VARCHAR(20) DEFAULT NULL, date_prevue DATETIME DEFAULT NULL, date_livraison DATETIME DEFAULT NULL, tracking_code VARCHAR(100) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, INDEX idx_livraison_commande (commande_id), INDEX idx_livraison_statut (statut), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE livraison ADD CONSTRAINT fk_livraison_commande FOREIGN KEY (commande_id) REFERENCES commande (id) ON DELETE CASCADE');

        $this->addSql('CREATE TABLE panier (id INT AUTO_INCREMENT NOT NULL, utilisateur_id INT NOT NULL, produit_id INT NOT NULL, quantite INT NOT NULL DEFAULT 1, prix_unitaire NUMERIC(10, 2) NOT NULL, date_ajout TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, date_modification TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, INDEX idx_user (utilisateur_id), INDEX idx_prod (produit_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE wishlist (id INT AUTO_INCREMENT NOT NULL, utilisateur_id INT NOT NULL, produit_id INT NOT NULL, date_ajout TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, UNIQUE INDEX unique_wishlist (utilisateur_id, produit_id), INDEX idx_utilisateur_id (utilisateur_id), INDEX idx_produit_id (produit_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_general_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE wishlist ADD CONSTRAINT wishlist_ibfk_1 FOREIGN KEY (utilisateur_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE wishlist ADD CONSTRAINT wishlist_ibfk_2 FOREIGN KEY (produit_id) REFERENCES produit (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE annulation_commande DROP FOREIGN KEY fk_annulation_commande');
        $this->addSql('ALTER TABLE annulation_commande DROP FOREIGN KEY fk_annulation_user');
        $this->addSql('ALTER TABLE livraison DROP FOREIGN KEY fk_livraison_commande');
        $this->addSql('ALTER TABLE wishlist DROP FOREIGN KEY wishlist_ibfk_1');
        $this->addSql('ALTER TABLE wishlist DROP FOREIGN KEY wishlist_ibfk_2');

        $this->addSql('DROP TABLE annulation_commande');
        $this->addSql('DROP TABLE livraison');
        $this->addSql('DROP TABLE panier');
        $this->addSql('DROP TABLE wishlist');

        $this->addSql('ALTER TABLE commande DROP motif_annulation');
    }
}
