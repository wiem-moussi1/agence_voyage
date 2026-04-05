# agence_voyage
laragon version gratuite : https://github.com/leokhoa/laragon/releases/tag/6.0.0
tester cnx db : http://localhost/agence_voyage/backend/config/db.php

****** tables ******************* 
-- Table User
CREATE TABLE user (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100),
    prenom VARCHAR(100),
    age INT,
    email VARCHAR(150) UNIQUE,
    password VARCHAR(255),
    role ENUM('Admin','client'),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Table Voyage
CREATE TABLE voyage (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titre VARCHAR(150),
    description TEXT,
    prix FLOAT,
    date_depart DATE,
    date_retour DATE,
    image VARCHAR(255),
    places_dispo INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Table Reservation
CREATE TABLE reservation (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    voyage_id INT,
    nb_personnes INT,
    prix_total FLOAT,
    statut ENUM('en_attente', 'confirmee', 'annulee') DEFAULT 'en_attente',
    date_reserv DATETIME DEFAULT CURRENT_TIMESTAMP,
    mode_paiement VARCHAR(50),
    FOREIGN KEY (user_id) REFERENCES user(id),
    FOREIGN KEY (voyage_id) REFERENCES voyage(id)
);

-- Table Avis
CREATE TABLE avis (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    voyage_id INT,
    note INT CHECK (note >= 1 AND note <= 5),
    commentaire TEXT,
    statut ENUM('en_attente', 'valide', 'refuse') DEFAULT 'en_attente',
    date_avis DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES user(id),
    FOREIGN KEY (voyage_id) REFERENCES voyage(id)
);

-- Table Message
CREATE TABLE message (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT,
    receiver_id INT,
    message TEXT,
    date_envoi DATETIME DEFAULT CURRENT_TIMESTAMP,
    lu BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (sender_id) REFERENCES user(id),
    FOREIGN KEY (receiver_id) REFERENCES user(id)
);

-- Table Newsletter
CREATE TABLE newsletter (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100),
    email VARCHAR(150) UNIQUE,
    date_inscription DATETIME DEFAULT CURRENT_TIMESTAMP
);

Admin created: admin@horizon.com / Admin@2026!
moussiiwiem@gmail.com / 11111111


-- Étape 1 : supprimer l'ancienne colonne `age`
ALTER TABLE `user` DROP COLUMN `age`;
 
-- Étape 2 : ajouter `date_naissance` après `prenom`
ALTER TABLE `user`
    ADD COLUMN `date_naissance` DATE NULL DEFAULT NULL AFTER `prenom`;
 
-- Étape 3 : ajouter `telephone` après `date_naissance`
ALTER TABLE `user`
    ADD COLUMN `telephone` VARCHAR(20) NULL DEFAULT NULL AFTER `date_naissance`;

-- Table pour les messages du formulaire de contact
CREATE TABLE IF NOT EXISTS `contact` (
  `id`              INT            NOT NULL AUTO_INCREMENT,
  `nom`             VARCHAR(150)   NOT NULL,
  `email`           VARCHAR(150)   NOT NULL,
  `telephone`       VARCHAR(30)             DEFAULT NULL,
  `sujet`           VARCHAR(200)            DEFAULT NULL,
  `message`         TEXT           NOT NULL,
  `statut`          ENUM('non_lu','lu','repondu') NOT NULL DEFAULT 'non_lu',
  `date_envoi`      DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
 
lien de l'app: 
 http://localhost/agence_voyage/frontend/visitor/home.html