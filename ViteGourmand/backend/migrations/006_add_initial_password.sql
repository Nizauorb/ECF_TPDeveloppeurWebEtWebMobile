-- Migration: Ajout de la colonne initial_password à la table users
-- Date: 2026-02-14
-- Description: Permet de stocker le mot de passe initial des employés pour consultation par l'admin

ALTER TABLE users ADD COLUMN initial_password VARCHAR(255) DEFAULT NULL;

-- Index optionnel pour les performances (si nécessaire)
-- CREATE INDEX idx_users_initial_password ON users(initial_password);
