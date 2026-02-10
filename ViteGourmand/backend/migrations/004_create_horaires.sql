-- Migration 004 : Création de la table horaires
-- Gestion des horaires d'ouverture du restaurant (Lundi → Dimanche)
-- Deux créneaux par jour : matin (déjeuner) et soir (dîner)

CREATE TABLE IF NOT EXISTS horaires (
    id INT AUTO_INCREMENT PRIMARY KEY,
    jour ENUM('lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche') NOT NULL UNIQUE,
    jour_order TINYINT NOT NULL COMMENT 'Ordre du jour (1=lundi, 7=dimanche) pour le tri',
    ouvert TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 = ouvert, 0 = fermé',
    matin_ouverture TIME DEFAULT NULL COMMENT 'Heure ouverture créneau midi',
    matin_fermeture TIME DEFAULT NULL COMMENT 'Heure fermeture créneau midi',
    soir_ouverture TIME DEFAULT NULL COMMENT 'Heure ouverture créneau soir',
    soir_fermeture TIME DEFAULT NULL COMMENT 'Heure fermeture créneau soir',
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed : horaires actuels (11:00-15:00 / 18:30-23:00 tous les jours)
INSERT INTO horaires (jour, jour_order, ouvert, matin_ouverture, matin_fermeture, soir_ouverture, soir_fermeture) VALUES
('lundi',    1, 1, '11:00:00', '15:00:00', '18:30:00', '23:00:00'),
('mardi',    2, 1, '11:00:00', '15:00:00', '18:30:00', '23:00:00'),
('mercredi', 3, 1, '11:00:00', '15:00:00', '18:30:00', '23:00:00'),
('jeudi',    4, 1, '11:00:00', '15:00:00', '18:30:00', '23:00:00'),
('vendredi', 5, 1, '11:00:00', '15:00:00', '18:30:00', '23:00:00'),
('samedi',   6, 1, '11:00:00', '15:00:00', '18:30:00', '23:00:00'),
('dimanche', 7, 1, '11:00:00', '15:00:00', '18:30:00', '23:00:00');
