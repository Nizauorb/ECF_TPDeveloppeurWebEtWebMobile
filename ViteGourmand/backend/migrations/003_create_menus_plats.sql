-- Migration 003 : Création des tables menus, plats et menu_plats
-- + Seed des données existantes (actuellement hardcodées dans menu.js)

-- ============================================
-- Table des plats (réutilisables entre menus)
-- ============================================
CREATE TABLE IF NOT EXISTS `plats` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `nom` VARCHAR(255) NOT NULL,
    `type` ENUM('entree', 'plat', 'dessert') NOT NULL,
    `allergenes` JSON DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table des menus
-- ============================================
CREATE TABLE IF NOT EXISTS `menus` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `menu_key` VARCHAR(50) NOT NULL,
    `titre` VARCHAR(255) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `image` VARCHAR(255) DEFAULT NULL,
    `theme` ENUM('Classique', 'Noel', 'Paques', 'Event') NOT NULL,
    `regime` ENUM('Classique', 'Végétarien', 'Vegan', 'Halal') NOT NULL DEFAULT 'Classique',
    `prix_par_personne` DECIMAL(10,2) NOT NULL,
    `nombre_personnes_min` INT(11) NOT NULL DEFAULT 2,
    `stock_disponible` INT(11) NOT NULL DEFAULT 10,
    `conditions_commande` TEXT DEFAULT NULL,
    `actif` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `menu_key` (`menu_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Table pivot menus <-> plats
-- ============================================
CREATE TABLE IF NOT EXISTS `menu_plats` (
    `menu_id` INT(11) NOT NULL,
    `plat_id` INT(11) NOT NULL,
    PRIMARY KEY (`menu_id`, `plat_id`),
    CONSTRAINT `fk_menu_plats_menu` FOREIGN KEY (`menu_id`) REFERENCES `menus` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_menu_plats_plat` FOREIGN KEY (`plat_id`) REFERENCES `plats` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- SEED : Insertion des plats existants
-- ============================================

-- Entrées
INSERT INTO `plats` (`id`, `nom`, `type`, `allergenes`) VALUES
(1, 'Terrine de campagne au lard fumé et au Morbier, cornichons, pain de campagne toasté', 'entree', '["Gluten", "Lait et produits laitiers"]'),
(2, 'Huîtres fines de claire n°2, mignonnette au vinaigre d''échalote', 'entree', '["Mollusques"]'),
(3, 'Velouté de potimarron au foie gras poêlé et croûtons de pain aux noix', 'entree', '["Gluten", "Fruits à coque"]'),
(4, 'Asperges vertes rôties aux œufs mollets mimosa', 'entree', '["Œufs"]');

-- Plats principaux
INSERT INTO `plats` (`id`, `nom`, `type`, `allergenes`) VALUES
(5, 'Filet de bœuf Rossini (foie gras poêlé, sauce madère), gratin dauphinois', 'plat', '["Gluten", "Lait et produits laitiers", "Œufs"]'),
(6, 'Chapon rôti farci aux châtaignes, jus au fond brun, gratin de pommes de terre au Reblochon', 'plat', '["Lait et produits laitiers", "Fruits à coque"]'),
(7, 'Jarret de bœuf braisé au vin rouge, purée de céleri-rave au Comté râpé, carottes glacées', 'plat', '["Lait et produits laitiers"]'),
(8, 'Carré d''agneau rôti aux herbes de Provence, risotto crémeux aux petits pois et menthe fraîche', 'plat', '["Lait et produits laitiers"]');

-- Desserts
INSERT INTO `plats` (`id`, `nom`, `type`, `allergenes`) VALUES
(9, 'Crème brûlée à la vanille de Madagascar', 'dessert', '["Lait et produits laitiers", "Œufs"]'),
(10, 'Bûche de Noël traditionnelle au chocolat et marron glacé', 'dessert', '["Gluten", "Lait et produits laitiers", "Œufs"]'),
(11, 'Tarte fine aux poires confites et crème vanille', 'dessert', '["Gluten", "Lait et produits laitiers", "Œufs"]'),
(12, 'Fraisier revisité – biscuit joconde, crème mousseline à la fraise, coulis de fruits rouges', 'dessert', '["Gluten", "Lait et produits laitiers", "Œufs"]');

-- ============================================
-- SEED : Insertion des menus existants
-- ============================================
INSERT INTO `menus` (`id`, `menu_key`, `titre`, `description`, `image`, `theme`, `regime`, `prix_par_personne`, `nombre_personnes_min`, `stock_disponible`, `conditions_commande`) VALUES
(1, 'classique', 'Menu Classique',
 'Un festin convivial et raffiné à partir de 2 convives. Savourez une terrine de campagne généreuse, un filet de bœuf Rossini accompagné d''un gratin dauphinois crémeux, et une crème brûlée à la vanille parfaitement caramélisée. Une expérience gastronomique élégante, parfaite pour un repas familial ou entre amis.',
 'Classique.png', 'Classique', 'Classique', 35.00, 2, 12,
 'Commande à effectuer au minimum 3 jours avant la prestation. Conservation au réfrigérateur entre 0°C et 4°C.'),

(2, 'noel', 'Menu de Noël',
 'Un festin de Noël généreux à partir de 6 convives. Dégustez des huîtres fraîches en entrée, un chapon rôti farci aux châtaignes accompagné d''un gratin de pommes de terre au Reblochon fondant, et une bûche de Noël traditionnelle au chocolat et marron glacé. Une table scintillante aux couleurs de fête pour célébrer ensemble dans une ambiance chaleureuse et raffinée.',
 'Noel.png', 'Noel', 'Classique', 55.00, 6, 5,
 'Commande à effectuer au minimum 2 semaines avant la prestation en raison de la disponibilité saisonnière des produits (huîtres fraîches, chapon). Les huîtres doivent être conservées vivantes au frais (5-10°C) et consommées rapidement. Le chapon nécessite une préparation spécifique.'),

(3, 'paques', 'Menu de Pâques',
 'Un menu de Pâques automnal et réconfortant à partir de 4 convives. Dégustez un velouté de potimarron onctueux au foie gras poêlé, un jarret de bœuf braisé fondant accompagné d''une purée de céleri-rave au Comté et de carottes glacées, et une délicate tarte fine aux poires confites. Une ambiance sereine et chaleureuse aux couleurs automnales pour célébrer ce moment festif.',
 'Paques.png', 'Paques', 'Classique', 38.00, 4, 8,
 'Commande à effectuer au minimum 5 jours avant la prestation. Le jarret de bœuf nécessite un temps de préparation prolongé. Conservation au réfrigérateur entre 0°C et 4°C.'),

(4, 'event', 'Menu d''Evénements',
 'Un menu événementiel raffiné à partir de 10 convives. Savourez des asperges vertes rôties aux œufs mollets mimosa, un carré d''agneau rôti aux herbes de Provence accompagné d''un risotto crémeux aux petits pois et menthe fraîche, et un fraisier revisité à la crème mousseline. Une table élégante et sophistiquée, parfaite pour célébrer vos grands événements dans une ambiance festive et raffinée.',
 'Event.png', 'Event', 'Classique', 48.00, 10, 3,
 'Commande à effectuer au minimum 3 semaines avant la prestation pour garantir la disponibilité des produits et la préparation soignée. Les asperges fraîches dépendent de la saison. Service traiteur sur place recommandé pour les groupes de plus de 15 personnes.');

-- ============================================
-- SEED : Associations menus <-> plats
-- ============================================
INSERT INTO `menu_plats` (`menu_id`, `plat_id`) VALUES
-- Menu Classique : terrine (1) + bœuf Rossini (5) + crème brûlée (9)
(1, 1), (1, 5), (1, 9),
-- Menu Noël : huîtres (2) + chapon (6) + bûche (10)
(2, 2), (2, 6), (2, 10),
-- Menu Pâques : velouté (3) + jarret (7) + tarte poires (11)
(3, 3), (3, 7), (3, 11),
-- Menu Event : asperges (4) + agneau (8) + fraisier (12)
(4, 4), (4, 8), (4, 12);