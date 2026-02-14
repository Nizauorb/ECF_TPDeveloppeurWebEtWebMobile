-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1
-- Généré le : sam. 14 fév. 2026 à 17:13
-- Version du serveur : 10.4.32-MariaDB
-- Version de PHP : 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `vite_gourmand_local`
--

-- --------------------------------------------------------

--
-- Structure de la table `account_deletion_requests`
--

CREATE TABLE `account_deletion_requests` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `verification_code` varchar(6) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `used` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `avis`
--

CREATE TABLE `avis` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL COMMENT 'Utilisateur qui a laissé l''avis',
  `commande_id` int(11) NOT NULL COMMENT 'Commande associée à l''avis',
  `note` tinyint(4) NOT NULL COMMENT 'Note de 1 à 5',
  `commentaire` text NOT NULL COMMENT 'Commentaire de l''utilisateur',
  `valide` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0 = en attente, 1 = validé, 2 = refusé',
  `validated_by` int(11) DEFAULT NULL COMMENT 'ID de l''employé/admin qui a validé ou refusé',
  `validated_at` datetime DEFAULT NULL COMMENT 'Date de validation ou refus',
  `created_at` datetime DEFAULT current_timestamp()
) ;

-- --------------------------------------------------------

--
-- Structure de la table `commandes`
--

CREATE TABLE `commandes` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `client_nom` varchar(100) NOT NULL,
  `client_prenom` varchar(100) NOT NULL,
  `client_email` varchar(254) NOT NULL,
  `client_telephone` varchar(20) NOT NULL,
  `menu_key` varchar(50) NOT NULL,
  `menu_nom` varchar(150) NOT NULL,
  `prix_unitaire` decimal(10,2) NOT NULL,
  `nombre_personnes` int(11) NOT NULL,
  `nombre_personnes_min` int(11) NOT NULL,
  `adresse_livraison` text NOT NULL,
  `ville_livraison` varchar(150) NOT NULL,
  `code_postal_livraison` varchar(10) NOT NULL,
  `date_prestation` date NOT NULL,
  `heure_prestation` time NOT NULL,
  `frais_livraison` decimal(10,2) NOT NULL DEFAULT 5.00,
  `distance_km` decimal(6,2) DEFAULT NULL,
  `sous_total` decimal(10,2) NOT NULL,
  `reduction_pourcent` decimal(5,2) NOT NULL DEFAULT 0.00,
  `reduction_montant` decimal(10,2) NOT NULL DEFAULT 0.00,
  `total` decimal(10,2) NOT NULL,
  `notes` text DEFAULT NULL,
  `statut` enum('en_attente','acceptee','en_preparation','en_livraison','livree','attente_retour_materiel','terminee','annulee') NOT NULL DEFAULT 'en_attente',
  `motif_annulation` text DEFAULT NULL,
  `mode_contact_annulation` varchar(50) DEFAULT NULL,
  `date_commande` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `email_change_requests`
--

CREATE TABLE `email_change_requests` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `new_email` varchar(255) NOT NULL,
  `verification_code` varchar(6) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `used` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `horaires`
--

CREATE TABLE `horaires` (
  `id` int(11) NOT NULL,
  `jour` enum('lundi','mardi','mercredi','jeudi','vendredi','samedi','dimanche') NOT NULL,
  `jour_order` tinyint(4) NOT NULL COMMENT 'Ordre du jour (1=lundi, 7=dimanche) pour le tri',
  `ouvert` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1 = ouvert, 0 = fermé',
  `matin_ouverture` time DEFAULT NULL COMMENT 'Heure ouverture créneau midi',
  `matin_fermeture` time DEFAULT NULL COMMENT 'Heure fermeture créneau midi',
  `soir_ouverture` time DEFAULT NULL COMMENT 'Heure ouverture créneau soir',
  `soir_fermeture` time DEFAULT NULL COMMENT 'Heure fermeture créneau soir',
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `horaires`
--

INSERT INTO `horaires` (`id`, `jour`, `jour_order`, `ouvert`, `matin_ouverture`, `matin_fermeture`, `soir_ouverture`, `soir_fermeture`, `updated_at`, `created_at`) VALUES
(1, 'lundi', 1, 1, '11:00:00', '15:00:00', '18:30:00', '23:00:00', '2026-02-14 13:21:32', '2026-02-14 11:53:22'),
(2, 'mardi', 2, 1, '11:00:00', '15:00:00', '18:30:00', '23:00:00', '2026-02-14 11:53:22', '2026-02-14 11:53:22'),
(3, 'mercredi', 3, 1, '11:00:00', '15:00:00', '18:30:00', '23:00:00', '2026-02-14 11:53:22', '2026-02-14 11:53:22'),
(4, 'jeudi', 4, 1, '11:00:00', '15:00:00', '18:30:00', '23:00:00', '2026-02-14 11:53:22', '2026-02-14 11:53:22'),
(5, 'vendredi', 5, 1, '11:00:00', '15:00:00', '18:30:00', '23:00:00', '2026-02-14 11:53:22', '2026-02-14 11:53:22'),
(6, 'samedi', 6, 1, '11:00:00', '15:00:00', '18:30:00', '23:00:00', '2026-02-14 11:53:22', '2026-02-14 11:53:22'),
(7, 'dimanche', 7, 1, '11:00:00', '15:00:00', '18:30:00', '23:00:00', '2026-02-14 11:53:22', '2026-02-14 11:53:22');

-- --------------------------------------------------------

--
-- Structure de la table `menus`
--

CREATE TABLE `menus` (
  `id` int(11) NOT NULL,
  `menu_key` varchar(50) NOT NULL,
  `titre` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `theme` enum('Classique','Noel','Paques','Event') NOT NULL,
  `regime` enum('Classique','Végétarien','Vegan','Halal') NOT NULL DEFAULT 'Classique',
  `prix_par_personne` decimal(10,2) NOT NULL,
  `nombre_personnes_min` int(11) NOT NULL DEFAULT 2,
  `stock_disponible` int(11) NOT NULL DEFAULT 10,
  `conditions_commande` text DEFAULT NULL,
  `actif` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `menus`
--

INSERT INTO `menus` (`id`, `menu_key`, `titre`, `description`, `image`, `theme`, `regime`, `prix_par_personne`, `nombre_personnes_min`, `stock_disponible`, `conditions_commande`, `actif`, `created_at`, `updated_at`) VALUES
(1, 'classique', 'Menu Classique', 'Un festin convivial et raffiné à partir de 2 convives. Savourez une terrine de campagne généreuse, un filet de bœuf Rossini accompagné d\'un gratin dauphinois crémeux, et une crème brûlée à la vanille parfaitement caramélisée. Une expérience gastronomique élégante, parfaite pour un repas familial ou entre amis.', 'Classique.png', 'Classique', 'Classique', 35.00, 2, 6, 'Commande à effectuer au minimum 3 jours avant la prestation. Conservation au réfrigérateur entre 0°C et 4°C.', 1, '2026-02-14 10:53:15', '2026-02-14 12:05:23'),
(2, 'noel', 'Menu de Noël', 'Un festin de Noël généreux à partir de 6 convives. Dégustez des huîtres fraîches en entrée, un chapon rôti farci aux châtaignes accompagné d\'un gratin de pommes de terre au Reblochon fondant, et une bûche de Noël traditionnelle au chocolat et marron glacé. Une table scintillante aux couleurs de fête pour célébrer ensemble dans une ambiance chaleureuse et raffinée.', 'Noel.png', 'Noel', 'Classique', 55.00, 6, 5, 'Commande à effectuer au minimum 2 semaines avant la prestation en raison de la disponibilité saisonnière des produits (huîtres fraîches, chapon). Les huîtres doivent être conservées vivantes au frais (5-10°C) et consommées rapidement. Le chapon nécessite une préparation spécifique.', 1, '2026-02-14 10:53:15', '2026-02-14 10:53:15'),
(3, 'paques', 'Menu de Pâques', 'Un menu de Pâques automnal et réconfortant à partir de 4 convives. Dégustez un velouté de potimarron onctueux au foie gras poêlé, un jarret de bœuf braisé fondant accompagné d\'une purée de céleri-rave au Comté et de carottes glacées, et une délicate tarte fine aux poires confites. Une ambiance sereine et chaleureuse aux couleurs automnales pour célébrer ce moment festif.', 'Paques.png', 'Paques', 'Classique', 38.00, 4, 4, 'Commande à effectuer au minimum 5 jours avant la prestation. Le jarret de bœuf nécessite un temps de préparation prolongé. Conservation au réfrigérateur entre 0°C et 4°C.', 1, '2026-02-14 10:53:15', '2026-02-14 11:22:42'),
(4, 'event', 'Menu d\'Evénements', 'Un menu événementiel raffiné à partir de 10 convives. Savourez des asperges vertes rôties aux œufs mollets mimosa, un carré d\'agneau rôti aux herbes de Provence accompagné d\'un risotto crémeux aux petits pois et menthe fraîche, et un fraisier revisité à la crème mousseline. Une table élégante et sophistiquée, parfaite pour célébrer vos grands événements dans une ambiance festive et raffinée.', 'Event.png', 'Event', 'Classique', 48.00, 10, 3, 'Commande à effectuer au minimum 3 semaines avant la prestation pour garantir la disponibilité des produits et la préparation soignée. Les asperges fraîches dépendent de la saison. Service traiteur sur place recommandé pour les groupes de plus de 15 personnes.', 1, '2026-02-14 10:53:15', '2026-02-14 10:53:15');

-- --------------------------------------------------------

--
-- Structure de la table `menu_plats`
--

CREATE TABLE `menu_plats` (
  `menu_id` int(11) NOT NULL,
  `plat_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `menu_plats`
--

INSERT INTO `menu_plats` (`menu_id`, `plat_id`) VALUES
(1, 1),
(1, 5),
(1, 9),
(2, 2),
(2, 6),
(2, 10),
(3, 3),
(3, 7),
(3, 11),
(4, 4),
(4, 8),
(4, 12);

-- --------------------------------------------------------

--
-- Structure de la table `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `used` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Structure de la table `plats`
--

CREATE TABLE `plats` (
  `id` int(11) NOT NULL,
  `nom` varchar(255) NOT NULL,
  `type` enum('entree','plat','dessert') NOT NULL,
  `allergenes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`allergenes`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `plats`
--

INSERT INTO `plats` (`id`, `nom`, `type`, `allergenes`, `created_at`, `updated_at`) VALUES
(1, 'Terrine de campagne au lard fumé et au Morbier, cornichons, pain de campagne toasté', 'entree', '[\"Gluten\", \"Lait et produits laitiers\"]', '2026-02-14 10:53:15', '2026-02-14 10:53:15'),
(2, 'Huîtres fines de claire n°2, mignonnette au vinaigre d\'échalote', 'entree', '[\"Mollusques\"]', '2026-02-14 10:53:15', '2026-02-14 10:53:15'),
(3, 'Velouté de potimarron au foie gras poêlé et croûtons de pain aux noix', 'entree', '[\"Gluten\", \"Fruits à coque\"]', '2026-02-14 10:53:15', '2026-02-14 10:53:15'),
(4, 'Asperges vertes rôties aux œufs mollets mimosa', 'entree', '[\"Œufs\"]', '2026-02-14 10:53:15', '2026-02-14 10:53:15'),
(5, 'Filet de bœuf Rossini (foie gras poêlé, sauce madère), gratin dauphinois', 'plat', '[\"Gluten\", \"Lait et produits laitiers\", \"Œufs\"]', '2026-02-14 10:53:15', '2026-02-14 10:53:15'),
(6, 'Chapon rôti farci aux châtaignes, jus au fond brun, gratin de pommes de terre au Reblochon', 'plat', '[\"Lait et produits laitiers\", \"Fruits à coque\"]', '2026-02-14 10:53:15', '2026-02-14 10:53:15'),
(7, 'Jarret de bœuf braisé au vin rouge, purée de céleri-rave au Comté râpé, carottes glacées', 'plat', '[\"Lait et produits laitiers\"]', '2026-02-14 10:53:15', '2026-02-14 10:53:15'),
(8, 'Carré d\'agneau rôti aux herbes de Provence, risotto crémeux aux petits pois et menthe fraîche', 'plat', '[\"Lait et produits laitiers\"]', '2026-02-14 10:53:15', '2026-02-14 10:53:15'),
(9, 'Crème brûlée à la vanille de Madagascar', 'dessert', '[\"Lait et produits laitiers\", \"Œufs\"]', '2026-02-14 10:53:15', '2026-02-14 10:53:15'),
(10, 'Bûche de Noël traditionnelle au chocolat et marron glacé', 'dessert', '[\"Gluten\", \"Lait et produits laitiers\", \"Œufs\"]', '2026-02-14 10:53:15', '2026-02-14 10:53:15'),
(11, 'Tarte fine aux poires confites et crème vanille', 'dessert', '[\"Gluten\", \"Lait et produits laitiers\", \"Œufs\"]', '2026-02-14 10:53:15', '2026-02-14 10:53:15'),
(12, 'Fraisier revisité – biscuit joconde, crème mousseline à la fraise, coulis de fruits rouges', 'dessert', '[\"Gluten\", \"Lait et produits laitiers\", \"Œufs\"]', '2026-02-14 10:53:15', '2026-02-14 10:53:15');

-- --------------------------------------------------------

--
-- Structure de la table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `adresse` varchar(255) NOT NULL DEFAULT '',
  `code_postal` varchar(10) NOT NULL DEFAULT '',
  `ville` varchar(100) NOT NULL DEFAULT '',
  `pays` varchar(100) NOT NULL DEFAULT 'France',
  `role` enum('utilisateur','employe','administrateur') NOT NULL DEFAULT 'utilisateur',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `initial_password` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `users`
--

INSERT INTO `users` (`id`, `email`, `password_hash`, `first_name`, `last_name`, `phone`, `adresse`, `code_postal`, `ville`, `pays`, `role`, `created_at`, `updated_at`, `initial_password`) VALUES
(7, 'jose.martin@vite-gourmand.fr', '$2y$10$bdLgDh8ZzM1g9e1vsYyTI.xsrrBo.umb5shUOF81oZT9ItejCTa22', 'josé', 'Martin', '0612345678', '1 rue de la rue', '33000', 'Bordeaux', 'France', 'administrateur', '2026-02-14 16:10:47', '2026-02-14 16:12:07', NULL),
(8, 'antoine.dupont@vite-gourmand.fr', '$2y$10$nwyFePy4gRw79QIe0iE9aONUjGopZOfEGIlSjTy/WkSwLPg7.J7IO', 'Antoine', 'Dupont', '0612345678', '2 rue de le rue', '33000', 'Bordeaux', 'France', 'employe', '2026-02-14 16:11:18', '2026-02-14 16:12:12', NULL),
(9, 'maxime.brouazin@vite-gourmand.fr', '$2y$10$HYrkRTrcBjHGwN6pUhsKJu96rqw2aMLirjFIgDplpnAMrEKwCFmUu', 'Maxime', 'Brouazin', '0612345678', '3 rue de la rue', '35300', 'Fougeres', 'France', 'utilisateur', '2026-02-14 16:11:56', '2026-02-14 16:11:56', NULL);

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `account_deletion_requests`
--
ALTER TABLE `account_deletion_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Index pour la table `avis`
--
ALTER TABLE `avis`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_avis_commande` (`commande_id`) COMMENT 'Un seul avis par commande',
  ADD KEY `fk_avis_user` (`user_id`),
  ADD KEY `fk_avis_validator` (`validated_by`);

--
-- Index pour la table `commandes`
--
ALTER TABLE `commandes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_statut` (`statut`),
  ADD KEY `idx_date_prestation` (`date_prestation`),
  ADD KEY `idx_date_commande` (`date_commande`);

--
-- Index pour la table `email_change_requests`
--
ALTER TABLE `email_change_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Index pour la table `horaires`
--
ALTER TABLE `horaires`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `jour` (`jour`);

--
-- Index pour la table `menus`
--
ALTER TABLE `menus`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `menu_key` (`menu_key`);

--
-- Index pour la table `menu_plats`
--
ALTER TABLE `menu_plats`
  ADD PRIMARY KEY (`menu_id`,`plat_id`),
  ADD KEY `fk_menu_plats_plat` (`plat_id`);

--
-- Index pour la table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Index pour la table `plats`
--
ALTER TABLE `plats`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `account_deletion_requests`
--
ALTER TABLE `account_deletion_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `avis`
--
ALTER TABLE `avis`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `commandes`
--
ALTER TABLE `commandes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT pour la table `email_change_requests`
--
ALTER TABLE `email_change_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `horaires`
--
ALTER TABLE `horaires`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT pour la table `menus`
--
ALTER TABLE `menus`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT pour la table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `plats`
--
ALTER TABLE `plats`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT pour la table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `account_deletion_requests`
--
ALTER TABLE `account_deletion_requests`
  ADD CONSTRAINT `account_deletion_requests_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `avis`
--
ALTER TABLE `avis`
  ADD CONSTRAINT `fk_avis_commande` FOREIGN KEY (`commande_id`) REFERENCES `commandes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_avis_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_avis_validator` FOREIGN KEY (`validated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Contraintes pour la table `commandes`
--
ALTER TABLE `commandes`
  ADD CONSTRAINT `commandes_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `email_change_requests`
--
ALTER TABLE `email_change_requests`
  ADD CONSTRAINT `email_change_requests_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `menu_plats`
--
ALTER TABLE `menu_plats`
  ADD CONSTRAINT `fk_menu_plats_menu` FOREIGN KEY (`menu_id`) REFERENCES `menus` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_menu_plats_plat` FOREIGN KEY (`plat_id`) REFERENCES `plats` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD CONSTRAINT `password_reset_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
