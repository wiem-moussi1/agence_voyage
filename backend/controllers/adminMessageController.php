<?php
require_once __DIR__ . '/../config/db.php';

class adminMessageController {
    private static function checkAdmin($user_id) {
        $db = Database::connect();
        $stmt = $db->prepare("SELECT role FROM user WHERE id = ?");
        $stmt->execute([$user_id]);
        $role = $stmt->fetchColumn();
        return $role === 'Admin';
    }

    public static function getAll() {
        $user_id = $_GET['admin_id'] ?? null;
        if (!$user_id || !self::checkAdmin($user_id)) {
            http_response_code(403);
            echo json_encode(['error' => 'Accès non autorisé']);
            return;
        }

        $db = Database::connect();
        // Récupérer tous les messages, avec les noms des expéditeurs et destinataires
        $stmt = $db->query("
            SELECT m.*, 
                   sender.nom AS sender_nom, sender.prenom AS sender_prenom,
                   receiver.nom AS receiver_nom, receiver.prenom AS receiver_prenom
            FROM message m
            JOIN user sender ON m.sender_id = sender.id
            JOIN user receiver ON m.receiver_id = receiver.id
            ORDER BY m.date_envoi DESC
        ");
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($messages);
    }

    public static function reply() {
        $user_id = $_GET['admin_id'] ?? null;
        if (!$user_id || !self::checkAdmin($user_id)) {
            http_response_code(403);
            echo json_encode(['error' => 'Accès non autorisé']);
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $receiver_id = $data['receiver_id'] ?? null; // le client à qui répondre
        $message = $data['message'] ?? '';

        if (!$receiver_id || empty($message)) {
            echo json_encode(['error' => 'Données incomplètes']);
            return;
        }

        $db = Database::connect();
        $stmt = $db->prepare("
            INSERT INTO message (sender_id, receiver_id, message, lu)
            VALUES (?, ?, ?, 0)
        ");
        $success = $stmt->execute([$user_id, $receiver_id, $message]);

        if ($success) {
            echo json_encode(['success' => 'Réponse envoyée']);
        } else {
            echo json_encode(['error' => 'Erreur lors de l\'envoi']);
        }
    }
}