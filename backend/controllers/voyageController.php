<?php
require_once __DIR__ . '/../config/db.php';

class VoyageController {
    public static function getAll() {
        global $pdo;
        $stmt = $pdo->query("SELECT * FROM voyages");
        $voyages = $stmt->fetchAll(PDO::FETCH_ASSOC); // Récupère toutes les lignes et les transforme en tableau associatif PHP (['title' => 'Séjour', 'price' => 1200]).
        echo json_encode($voyages); // Transforme le tableau PHP en JSON et l’envoie au frontend.
    }
}