-- Exécuter ce script si la colonne verification_token n'existe pas
-- Via phpMyAdmin (onglet SQL) ou : mysql -u root chroniccare < add_verification_columns.sql

ALTER TABLE users ADD verification_token VARCHAR(64) DEFAULT NULL;
ALTER TABLE users ADD verification_token_expires_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)';
CREATE UNIQUE INDEX UNIQ_users_verification_token ON users (verification_token);
