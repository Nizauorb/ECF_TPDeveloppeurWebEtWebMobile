-- Migration 001 : Refonte de la table commandes pour le cahier des charges
-- Exécuter ce script sur la base de données locale et production

-- Sauvegarder les données existantes si nécessaire
-- DROP TABLE IF EXISTS commandes_backup;
-- CREATE TABLE commandes_backup AS SELECT * FROM commandes;

-- Supprimer l'ancienne table et recréer avec la nouvelle structure
DROP TABLE IF EXISTS commandes;

CREATE TABLE commandes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    
    -- Informations client (snapshot au moment de la commande)
    client_nom VARCHAR(100) NOT NULL,
    client_prenom VARCHAR(100) NOT NULL,
    client_email VARCHAR(254) NOT NULL,
    client_telephone VARCHAR(20) NOT NULL,
    
    -- Menu commandé
    menu_key VARCHAR(50) NOT NULL,
    menu_nom VARCHAR(150) NOT NULL,
    prix_unitaire DECIMAL(10,2) NOT NULL,
    nombre_personnes INT NOT NULL,
    nombre_personnes_min INT NOT NULL,
    
    -- Livraison
    adresse_livraison TEXT NOT NULL,
    ville_livraison VARCHAR(150) NOT NULL,
    code_postal_livraison VARCHAR(10) NOT NULL,
    date_prestation DATE NOT NULL,
    heure_prestation TIME NOT NULL,
    frais_livraison DECIMAL(10,2) NOT NULL DEFAULT 5.00,
    distance_km DECIMAL(6,2) DEFAULT NULL,
    
    -- Prix
    sous_total DECIMAL(10,2) NOT NULL,
    reduction_pourcent DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    reduction_montant DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total DECIMAL(10,2) NOT NULL,
    
    -- Notes et statut
    notes TEXT DEFAULT NULL,
    statut ENUM(
        'en_attente',
        'acceptee',
        'en_preparation',
        'en_livraison',
        'livree',
        'attente_retour_materiel',
        'terminee',
        'annulee'
    ) NOT NULL DEFAULT 'en_attente',
    motif_annulation TEXT DEFAULT NULL,
    mode_contact_annulation VARCHAR(50) DEFAULT NULL,
    
    -- Timestamps
    date_commande DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    
    -- Contraintes
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_statut (statut),
    INDEX idx_date_prestation (date_prestation),
    INDEX idx_date_commande (date_commande)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
