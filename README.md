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