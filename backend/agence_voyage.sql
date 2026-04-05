-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Apr 05, 2026 at 02:41 PM
-- Server version: 8.0.30
-- PHP Version: 8.1.10

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `agence_voyage`
--

-- --------------------------------------------------------

--
-- Table structure for table `avis`
--

CREATE TABLE `avis` (
  `id` int NOT NULL,
  `user_id` int DEFAULT NULL,
  `voyage_id` int DEFAULT NULL,
  `note` int DEFAULT NULL,
  `commentaire` text,
  `statut` enum('en_attente','valide','refuse') DEFAULT 'en_attente',
  `date_avis` datetime DEFAULT CURRENT_TIMESTAMP
) ;

-- --------------------------------------------------------

--
-- Table structure for table `contact`
--

CREATE TABLE `contact` (
  `id` int NOT NULL,
  `nom` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `telephone` varchar(30) DEFAULT NULL,
  `sujet` varchar(200) DEFAULT NULL,
  `message` text NOT NULL,
  `statut` enum('non_lu','lu','repondu') NOT NULL DEFAULT 'non_lu',
  `date_envoi` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `contact`
--

INSERT INTO `contact` (`id`, `nom`, `email`, `telephone`, `sujet`, `message`, `statut`, `date_envoi`) VALUES
(1, 'rr', 'rr@rr.l', '111111111', 'Demande de devis', 'rrrrrrrr', 'repondu', '2026-04-04 12:45:48');

-- --------------------------------------------------------

--
-- Table structure for table `message`
--

CREATE TABLE `message` (
  `id` int NOT NULL,
  `sender_id` int DEFAULT NULL,
  `receiver_id` int DEFAULT NULL,
  `message` text,
  `date_envoi` datetime DEFAULT CURRENT_TIMESTAMP,
  `lu` tinyint(1) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `newsletter`
--

CREATE TABLE `newsletter` (
  `id` int NOT NULL,
  `nom` varchar(100) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `date_inscription` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `newsletter`
--

INSERT INTO `newsletter` (`id`, `nom`, `email`, `date_inscription`) VALUES
(1, 'cccc', 'cc@k.n', '2026-04-04 12:42:35');

-- --------------------------------------------------------

--
-- Table structure for table `reservation`
--

CREATE TABLE `reservation` (
  `id` int NOT NULL,
  `user_id` int DEFAULT NULL,
  `voyage_id` int DEFAULT NULL,
  `nb_personnes` int DEFAULT NULL,
  `prix_total` float DEFAULT NULL,
  `statut` enum('en_attente','confirmee','annulee') DEFAULT 'en_attente',
  `date_reserv` datetime DEFAULT CURRENT_TIMESTAMP,
  `mode_paiement` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user`
--

CREATE TABLE `user` (
  `id` int NOT NULL,
  `nom` varchar(100) DEFAULT NULL,
  `prenom` varchar(100) DEFAULT NULL,
  `date_naissance` date DEFAULT NULL,
  `telephone` varchar(20) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `role` enum('Admin','client') DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `user`
--

INSERT INTO `user` (`id`, `nom`, `prenom`, `date_naissance`, `telephone`, `email`, `password`, `role`, `created_at`) VALUES
(4, 'Admin', 'Principal', NULL, NULL, 'admin@horizon.com', '$2y$10$bn64C.YQFMYe2P2j61Oe9.YBwCiGdBt8iu8KAusAnbrkVTy0Joeui', 'Admin', '2026-03-31 17:02:26'),
(5, 'moussi', 'wiem', NULL, NULL, 'moussiiwiem@gmail.com', '$2y$10$htspbY2Fag66zb09cLFA3.7PkeLZxgylTu0mRJWsb3AoLbgbkJWRa', 'client', '2026-03-31 20:09:34'),
(6, 'siwar', 'siwar', '2003-04-21', '21335669', 'siwar@gmail.com', '$2y$10$v8TqawFdCE4oX7MGp7GxletxcVZm61k7FFGPPi8tNfzGmPkCiKcdi', 'client', '2026-04-04 16:23:30'),
(7, 'yosra', 'yosra', '2026-04-04', '44521879', 'yosra@gmail.com', '$2y$10$sfmI.t.k8m7abioCaAa9uuGA9tf3TuNc6k5iVbjxQnI8GRI2LFJsq', 'client', '2026-04-04 16:25:27');

-- --------------------------------------------------------

--
-- Table structure for table `voyage`
--

CREATE TABLE `voyage` (
  `id` int NOT NULL,
  `titre` varchar(150) DEFAULT NULL,
  `description` text,
  `prix` float DEFAULT NULL,
  `date_depart` date DEFAULT NULL,
  `date_retour` date DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `places_dispo` int DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `voyage`
--

INSERT INTO `voyage` (`id`, `titre`, `description`, `prix`, `date_depart`, `date_retour`, `image`, `places_dispo`, `created_at`) VALUES
(3, 'Istanbul magique', 'Jour 1 : Arrivée à Istanbul, accueil à l’aéroport et transfert à l’hôtel. Installation et soirée libre.\nJour 2 : Visite de la Mosquée Bleue, Sainte-Sophie et Palais de Topkapi. Déjeuner inclus.\nJour 3 : Croisière sur le Bosphore, découverte du bazar égyptien et temps libre pour shopping.\nJour 4 : Journée libre ou excursion optionnelle à Bursa.\nJour 5 : Retour en Tunisie après petit déjeuner.', 1850, '2026-06-10', '2026-06-15', '/agence_voyage/images/voyages/voyage_1775312316_af317690f23a.png', 20, '2026-04-04 15:09:32'),
(4, 'Antalya détente & plage', 'Jour 1 : Arrivée et installation dans un hôtel 5 étoiles en All Inclusive.\r\nJour 2 : Journée plage et activités nautiques.\r\nJour 3 : Visite de la vieille ville Kaleiçi et des cascades de Düden.\r\nJour 4 : Excursion en bateau avec déjeuner inclus.\r\nJour 5 : Temps libre et shopping.\r\nJour 6 : Retour en Tunisie.', 2200, '2026-07-05', '2026-07-10', '/agence_voyage/images/voyages/voyage_1775312418_5854ced5fc8b.png', 15, '2026-04-04 15:10:56'),
(5, 'Dubaï luxe et aventure', 'Jour 1 : Arrivée et installation à l’hôtel.\r\nJour 2 : Visite Burj Khalifa, Dubai Mall et fontaines.\r\nJour 3 : Safari dans le désert avec dîner BBQ.\r\nJour 4 : Marina Dubaï et croisière Dhow.\r\nJour 5 : Journée libre (shopping ou plage).\r\nJour 6 : Retour.', 3500, '2026-08-01', '2026-08-06', '/agence_voyage/images/voyages/voyage_1775312552_54b65ae51f19.jpg', 10, '2026-04-04 15:11:11'),
(6, 'Paris romantique', 'Jour 1 : Arrivée à Paris, installation.\r\nJour 2 : Tour Eiffel, Champs-Élysées, Arc de Triomphe.\r\nJour 3 : Musée du Louvre et croisière sur la Seine.\r\nJour 4 : Disneyland Paris (optionnel).\r\nJour 5 : Montmartre et Sacré-Cœur.\r\nJour 6 : Retour.', 2800, '2026-05-15', '2026-05-20', '/agence_voyage/images/voyages/voyage_1775312565_265ba8c49792.jpg', 12, '2026-04-04 15:11:23'),
(7, 'Découverte du Sahara tunisien', 'Jour 1 : Départ vers Tozeur, visite oasis.\nJour 2 : Excursion en 4x4 à Ong Jmel et Star Wars.\nJour 3 : Douz et balade à dos de chameau.\nJour 4 : Nuit dans un camp désert.\nJour 5 : Retour vers Tunis.', 450, '2026-04-20', '2026-04-25', '/agence_voyage/images/voyages/voyage_1775312657_52c8ebcbf25a.jpg', 18, '2026-04-04 15:11:36'),
(8, 'Rome historique', 'Jour 1 : Arrivée à Rome, accueil et transfert à l’hôtel. Installation et promenade libre.\r\nJour 2 : Visite du Colisée, du Forum Romain et du Mont Palatin. Déjeuner libre.\r\nJour 3 : Découverte du Vatican : Basilique Saint-Pierre, Chapelle Sixtine.\r\nJour 4 : Fontaine de Trevi, Place d’Espagne et shopping.\r\nJour 5 : Journée libre ou excursion optionnelle à Florence.\r\nJour 6 : Retour en Tunisie.', 2600, '2026-06-20', '2026-06-25', '/agence_voyage/images/voyages/voyage_1775313072_2fe7acc4c122.png', 14, '2026-04-04 15:26:59'),
(9, 'Égypte antique', 'Jour 1 : Arrivée au Caire, accueil et installation à l’hôtel.\r\nJour 2 : Visite des pyramides de Gizeh et du Sphinx, musée égyptien.\r\nJour 3 : Vol interne vers Louxor, visite des temples de Karnak et Louxor.\r\nJour 4 : Vallée des Rois et Temple d’Hatchepsout.\r\nJour 5 : Retour au Caire, soirée libre.\r\nJour 6 : Temps libre et retour en Tunisie.', 3200, '2026-09-10', '2026-09-15', '/agence_voyage/images/voyages/voyage_1775313085_85fad25f95e5.png', 16, '2026-04-04 15:27:19'),
(10, 'gggggggggg', '', 1111, '2026-04-05', NULL, '/agence_voyage/images/voyages/voyage_1775392850_afe42ae2e6ed.jpg', 0, '2026-04-05 13:40:50');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `avis`
--
ALTER TABLE `avis`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `voyage_id` (`voyage_id`);

--
-- Indexes for table `contact`
--
ALTER TABLE `contact`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `message`
--
ALTER TABLE `message`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `receiver_id` (`receiver_id`);

--
-- Indexes for table `newsletter`
--
ALTER TABLE `newsletter`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `reservation`
--
ALTER TABLE `reservation`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `voyage_id` (`voyage_id`);

--
-- Indexes for table `user`
--
ALTER TABLE `user`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `voyage`
--
ALTER TABLE `voyage`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `avis`
--
ALTER TABLE `avis`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `contact`
--
ALTER TABLE `contact`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `message`
--
ALTER TABLE `message`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `newsletter`
--
ALTER TABLE `newsletter`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `reservation`
--
ALTER TABLE `reservation`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user`
--
ALTER TABLE `user`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `voyage`
--
ALTER TABLE `voyage`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `avis`
--
ALTER TABLE `avis`
  ADD CONSTRAINT `avis_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`),
  ADD CONSTRAINT `avis_ibfk_2` FOREIGN KEY (`voyage_id`) REFERENCES `voyage` (`id`);

--
-- Constraints for table `message`
--
ALTER TABLE `message`
  ADD CONSTRAINT `message_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `user` (`id`),
  ADD CONSTRAINT `message_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `user` (`id`);

--
-- Constraints for table `reservation`
--
ALTER TABLE `reservation`
  ADD CONSTRAINT `reservation_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`),
  ADD CONSTRAINT `reservation_ibfk_2` FOREIGN KEY (`voyage_id`) REFERENCES `voyage` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
