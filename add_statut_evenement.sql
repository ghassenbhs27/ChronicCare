-- Corrige l'erreur : Champ 'e1_.statut' inconnu dans field list
-- Exécuter dans phpMyAdmin ou MySQL (une seule des deux options ci-dessous).

-- Option A (recommandé) :
ALTER TABLE evenement ADD statut VARCHAR(20) NOT NULL DEFAULT 'valide';

-- Option B si Option A échoue (exécuter les 3 lignes) :
-- ALTER TABLE evenement ADD statut VARCHAR(20) NULL;
-- UPDATE evenement SET statut = 'valide';
-- ALTER TABLE evenement MODIFY statut VARCHAR(20) NOT NULL;
