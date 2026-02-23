-- Script SQL pour ajouter les colonnes de géolocalisation à la table commande
-- Exécuter ce script si la migration a échoué

-- Ajouter les colonnes de géolocalisation
ALTER TABLE commande
ADD COLUMN ip_address VARCHAR(45) NULL,
ADD COLUMN country VARCHAR(50) NULL,
ADD COLUMN city VARCHAR(50) NULL,
ADD COLUMN region VARCHAR(50) NULL,
ADD COLUMN latitude DOUBLE NULL,
ADD COLUMN longitude DOUBLE NULL,
ADD COLUMN timezone VARCHAR(50) NULL,
ADD COLUMN isp VARCHAR(100) NULL;

-- Si la colonne 'adresse' existe encore, on peut la supprimer (optionnel)
-- ALTER TABLE commande DROP COLUMN adresse;

-- Afficher la structure mise à jour
DESCRIBE commande;
