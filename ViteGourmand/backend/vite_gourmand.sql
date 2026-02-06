-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1
-- Généré le : ven. 06 fév. 2026 à 12:23
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
-- Base de données : `vite_gourmand`
--

-- --------------------------------------------------------

--
-- Structure de la table `commandes`
--

CREATE TABLE `commandes` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `menu_nom` varchar(255) NOT NULL,
  `quantite` int(11) NOT NULL DEFAULT 1,
  `total` decimal(10,2) NOT NULL,
  `statut` enum('en_attente','accepte','en_preparation','pret','livre','annule') DEFAULT 'en_attente',
  `notes` text DEFAULT NULL,
  `date_commande` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `used` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `password_reset_tokens`
--

INSERT INTO `password_reset_tokens` (`id`, `user_id`, `token`, `expires_at`, `created_at`, `used`) VALUES
(14, 2, 'f85d543ec7789224b2ee4ff3e3876b354afb2bcc66b5363dbc2003180247c059', '2026-02-02 17:44:01', '2026-02-02 15:44:01', 1),
(15, 3, '8dad5c0d7be1c983b80b44fd2d70ab2035ae4cfe57dcbfe1078c2052baeabe7c', '2026-02-02 18:56:21', '2026-02-02 17:46:21', 1),
(16, 3, '0095b35abd430bd81a802b7fded87de4000e659574ec02136007411ccf036774', '2026-02-03 07:57:54', '2026-02-03 06:47:54', 1),
(17, 3, '2fc3612741eea910075ded2a1fa62ffacd614b5e716fe41dc1c82a53523cc09f', '2026-02-03 08:09:11', '2026-02-03 06:59:11', 1),
(18, 3, '35cfb2c6e02014ede833255de996c32fd1247e89f30a7c8e7b622bb0405971db', '2026-02-03 08:26:23', '2026-02-03 07:16:23', 1),
(19, 3, '52df9d84676f700f357c8e0954ab1d0cd6e9d4fab58278f010e214b9eeaf1beb', '2026-02-03 08:26:49', '2026-02-03 07:16:49', 1),
(20, 3, '56019f3a9f230c8923ea5c64e4f5a84eb4a3d3145c70381680a78abbd7bd8361', '2026-02-03 08:27:26', '2026-02-03 07:17:26', 1),
(21, 3, 'f4d8cbe6f8d81371147f3772008bd858ae57945a4412561e7fc18941b63158e3', '2026-02-03 09:15:28', '2026-02-03 08:05:28', 1),
(22, 3, '3ea03bfa4a0ebc43d1e834f8bbce4566551160fe3bb050cd81887cd23d215193', '2026-02-03 09:17:46', '2026-02-03 08:07:46', 1),
(23, 3, 'f5ba58dba1cfa212c8f7e5cfd26ad02ad94f835827a541bc520db3ba9af146d7', '2026-02-03 09:17:55', '2026-02-03 08:07:55', 1),
(24, 3, '1f3b6613c70d69522da8bdbaa7b871a4bc6da9f191fac43405c8a122a104d98f', '2026-02-03 09:18:39', '2026-02-03 08:08:39', 1),
(25, 3, '81caa9a87c2cf6664e51ca5a58f75a4803fcd4e7e99027f68a856166df937c67', '2026-02-03 09:18:46', '2026-02-03 08:08:46', 1),
(26, 3, '04abf449cf2eaf85a618473d411a36353b46fab29bcfb2808f8b895a6b186fae', '2026-02-03 14:43:26', '2026-02-03 13:33:26', 1),
(27, 3, '05453b3ea79ac6f5ea56e7eb28078bcd52f02cf933fb95930871003e77fd8881', '2026-02-03 14:57:58', '2026-02-03 13:47:58', 1),
(28, 3, '8aa34080ec92eea30eaf7de119c105d861f30e988d1228727cf534111b5a2e1b', '2026-02-03 14:58:23', '2026-02-03 13:48:23', 1),
(29, 3, 'c9a8836850c67a43b3033819f582f5946f24f54ca7db75e976fed627ccf4f657', '2026-02-03 15:23:34', '2026-02-03 14:13:34', 1),
(30, 3, 'c71579628164817abfaf640ba048afd977800d4d806477a5faeec78ad9d7114b', '2026-02-03 15:24:19', '2026-02-03 14:14:19', 1),
(31, 3, '58ed4139dccba2d18134b8bacb6323a4900b68e5fb3124969841073441e918c0', '2026-02-03 15:36:47', '2026-02-03 14:26:47', 1),
(32, 3, '612ba761b2da98075a68569ed34841718a4000bd01f4858cb4402e9c550afa92', '2026-02-03 15:39:34', '2026-02-03 14:29:34', 1),
(33, 3, '7f4bdb2d1e4cc11e2888e53d41f3c26d53da67249219e21b60b488b7a273dcbb', '2026-02-03 15:49:30', '2026-02-03 14:39:30', 1),
(34, 3, '4a969926681d5bcf5196e0795541307b1d113513fc7e25428ff50c78385e0313', '2026-02-03 15:49:39', '2026-02-03 14:39:39', 1),
(35, 3, 'fd98111b4e6c968e80ce194c564b57b5771bce382863a9e2bdaaea687cd535a8', '2026-02-03 15:49:40', '2026-02-03 14:39:40', 1),
(36, 3, '084d58c9c3e9683979787933141e9e5e7fd56252fa9b7052483d2973bcc0f693', '2026-02-03 15:49:41', '2026-02-03 14:39:41', 1),
(37, 3, 'ebfaf12d93fca7b1347f6f37c593fa52271413d0a1587443ca313057a5fd69fc', '2026-02-03 15:49:41', '2026-02-03 14:39:41', 1),
(38, 3, '662ed87055255a8e116630538a0df4bf7def4b9827beb5cc5a56f324c857714d', '2026-02-03 17:23:32', '2026-02-03 16:13:32', 1),
(39, 3, 'a2561ff34d2fc5a367f947a7c9c5a01494fecb6aca48f84e20166657e9ff82c8', '2026-02-03 17:28:20', '2026-02-03 16:18:20', 1),
(40, 3, '0e916a579c4097b28cc08b756062e1ef99fcc16171ccf4909df4df7f42612e7c', '2026-02-03 17:31:14', '2026-02-03 16:21:14', 1),
(41, 3, '7df0d9ba81b44f7b3744a686b529c12ebcc135e34468ed135b5093f89fa1094c', '2026-02-04 01:39:41', '2026-02-04 00:29:41', 1);

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
  `address` text NOT NULL,
  `role` enum('utilisateur','employe','administrateur') DEFAULT 'utilisateur',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Déchargement des données de la table `users`
--

INSERT INTO `users` (`id`, `email`, `password_hash`, `first_name`, `last_name`, `phone`, `address`, `role`, `created_at`, `updated_at`) VALUES
(2, 'test@test.com', '$2y$10$hr0YTjRqnbie59/Mo0i8Q.qsCNUJjyNt0HWsm/6.efYSR169O3Y1.', 'Test', 'Test', '0123456789', '1 rue de la rue', 'utilisateur', '2026-02-02 15:43:25', '2026-02-02 15:44:29'),
(3, 'lorenzoflener14@gmail.com', '$2y$10$eFiIJSQmWmerz2R1CsERQuJFVo3xrcbuUY/oUFYppVho03MNDNfD.', 'Flener', 'Lorenzo', '0676936832', '7 rue de le rue', 'utilisateur', '2026-02-02 17:45:48', '2026-02-04 00:30:35'),
(4, 'maxime.brouazin03@gmail.com', '$2y$10$LOqyVW3WQ3bc1QZy0W6LbOKCdPJVtc/iu1Ke7qdp/ixsKYv5yFk4u', 'Maxime', 'Brouazin', '0676936832', '7 rue de folleville 35133 lecousse', 'utilisateur', '2026-02-03 16:24:31', '2026-02-03 16:24:31');

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `commandes`
--
ALTER TABLE `commandes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Index pour la table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

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
-- AUTO_INCREMENT pour la table `commandes`
--
ALTER TABLE `commandes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT pour la table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `commandes`
--
ALTER TABLE `commandes`
  ADD CONSTRAINT `commandes_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD CONSTRAINT `password_reset_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
