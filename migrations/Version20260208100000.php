<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260208100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Créer les tables pour Produit, Commande et LigneCommande';
    }

    public function up(Schema $schema): void
    {
        // Table PRODUIT
        $this->addSql('CREATE TABLE produit (
            id INT AUTO_INCREMENT NOT NULL,
            nom VARCHAR(200) NOT NULL,
            description LONGTEXT NOT NULL,
            prix NUMERIC(10, 2) NOT NULL,
            stock INT NOT NULL,
            categorie VARCHAR(100) NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Table COMMANDE
        $this->addSql('CREATE TABLE commande (
            id INT AUTO_INCREMENT NOT NULL,
            utilisateur_id INT NOT NULL,
            numero_commande VARCHAR(50) NOT NULL UNIQUE,
            statut VARCHAR(50) NOT NULL,
            total NUMERIC(10, 2) NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            FOREIGN KEY (utilisateur_id) REFERENCES users(id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Table LIGNE_COMMANDE
        $this->addSql('CREATE TABLE ligne_commande (
            id INT AUTO_INCREMENT NOT NULL,
            commande_id INT NOT NULL,
            produit_id INT NOT NULL,
            quantite INT NOT NULL,
            prix_unitaire NUMERIC(10, 2) NOT NULL,
            sous_total NUMERIC(10, 2) NOT NULL,
            remise NUMERIC(5, 2) NULL DEFAULT NULL,
            PRIMARY KEY (id),
            FOREIGN KEY (commande_id) REFERENCES commande(id) ON DELETE CASCADE,
            FOREIGN KEY (produit_id) REFERENCES produit(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        // Index pour les recherches
        $this->addSql('CREATE INDEX idx_produit_categorie ON produit(categorie)');
        $this->addSql('CREATE INDEX idx_produit_is_active ON produit(is_active)');
        $this->addSql('CREATE INDEX idx_commande_utilisateur ON commande(utilisateur_id)');
        $this->addSql('CREATE INDEX idx_commande_statut ON commande(statut)');
        $this->addSql('CREATE INDEX idx_commande_numero ON commande(numero_commande)');
        $this->addSql('CREATE INDEX idx_ligne_commande_commande ON ligne_commande(commande_id)');
        $this->addSql('CREATE INDEX idx_ligne_commande_produit ON ligne_commande(produit_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS ligne_commande');
        $this->addSql('DROP TABLE IF EXISTS commande');
        $this->addSql('DROP TABLE IF EXISTS produit');
    }
}
