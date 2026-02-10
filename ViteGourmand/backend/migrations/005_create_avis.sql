-- Migration 005 : Création de la table avis
-- Gestion des avis clients (note 1-5 + commentaire)
-- Un avis doit être validé par un employé/admin avant d'apparaître en page d'accueil

CREATE TABLE IF NOT EXISTS avis (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL COMMENT 'Utilisateur qui a laissé l''avis',
    commande_id INT NOT NULL COMMENT 'Commande associée à l''avis',
    note TINYINT NOT NULL COMMENT 'Note de 1 à 5',
    commentaire TEXT NOT NULL COMMENT 'Commentaire de l''utilisateur',
    valide TINYINT(1) NOT NULL DEFAULT 0 COMMENT '0 = en attente, 1 = validé, 2 = refusé',
    validated_by INT DEFAULT NULL COMMENT 'ID de l''employé/admin qui a validé ou refusé',
    validated_at DATETIME DEFAULT NULL COMMENT 'Date de validation ou refus',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_avis_commande (commande_id) COMMENT 'Un seul avis par commande',
    CONSTRAINT fk_avis_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_avis_commande FOREIGN KEY (commande_id) REFERENCES commandes(id) ON DELETE CASCADE,
    CONSTRAINT fk_avis_validator FOREIGN KEY (validated_by) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT chk_note CHECK (note BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
