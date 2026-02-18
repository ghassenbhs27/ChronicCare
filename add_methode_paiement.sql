-- Ajouter la colonne methode_paiement à la table commande
ALTER TABLE commande ADD COLUMN methode_paiement VARCHAR(30) NOT NULL DEFAULT 'card';
