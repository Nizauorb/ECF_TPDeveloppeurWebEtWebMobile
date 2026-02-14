-- Migration 002 : Séparer le champ address en champs distincts (conformité MCD)
-- Exécuter ce script sur la base de données locale et production

-- 1. Ajouter les nouvelles colonnes
ALTER TABLE users
    ADD COLUMN adresse VARCHAR(255) NOT NULL DEFAULT '' AFTER phone,
    ADD COLUMN code_postal VARCHAR(10) NOT NULL DEFAULT '' AFTER adresse,
    ADD COLUMN ville VARCHAR(100) NOT NULL DEFAULT '' AFTER code_postal,
    ADD COLUMN pays VARCHAR(100) NOT NULL DEFAULT 'France' AFTER ville;

-- 2. Migrer les données existantes : copier address vers adresse
-- (code_postal et ville devront être renseignés manuellement par les utilisateurs)
UPDATE users SET adresse = address WHERE address IS NOT NULL AND address != '';

-- 3. Supprimer l'ancienne colonne
ALTER TABLE users DROP COLUMN address;
