<?php
header("Content-Type: application/json"); // Définit le type de réponse HTTP que le serveur va renvoyer.
header("Access-Control-Allow-Origin: *"); // Permet à n’importe quel domaine d’accéder à ton API (CORS)

require_once 'controllers/VoyageController.php';

$uri = $_GET['uri'] ?? ''; // Récupère la valeur du paramètre uri dans l’URL (ex: ?uri=voyages).

if ($uri === 'voyages') {
    VoyageController::getAll();
} else {
    echo json_encode(["message" => "API OK"]);
}