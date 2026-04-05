<?php
require_once __DIR__ . '/../config/db.php';

class messageController {
    // Récupérer la conversation entre un client et l'admin
    public static function getConversation() {
        $user_id = $_GET['user_id'] ?? null;
        if (!$user_id) {
            echo json_encode(['error' => 'user_id manquant']);
            return;
        }

        // Récupérer l'ID de l'admin (role = Admin)
        $db = Database::connect();
        $stmt = $db->prepare("SELECT id FROM user WHERE role = 'Admin' LIMIT 1");
        $stmt->execute();
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$admin) {
            echo json_encode(['error' => 'Admin introuvable']);
            return;
        }
        $admin_id = $admin['id'];

        // Récupérer les messages entre ces deux utilisateurs
        $stmt = $db->prepare("
            SELECT * FROM message
            WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)
            ORDER BY date_envoi ASC
        ");
        $stmt->execute([$user_id, $admin_id, $admin_id, $user_id]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Marquer les messages reçus par l'utilisateur comme lus
        $stmt = $db->prepare("UPDATE message SET lu = 1 WHERE receiver_id = ? AND sender_id = ?");
        $stmt->execute([$user_id, $admin_id]);

        echo json_encode(['admin_id' => $admin_id, 'messages' => $messages]);
    }

    public static function send() {
        $data = json_decode(file_get_contents('php://input'), true);
        $sender_id = $data['sender_id'] ?? null;
        $receiver_id = $data['receiver_id'] ?? null;
        $message = $data['message'] ?? '';

        if (!$sender_id || !$receiver_id || empty($message)) {
            echo json_encode(['error' => 'Données incomplètes']);
            return;
        }

        $db = Database::connect();
        $stmt = $db->prepare("
            INSERT INTO message (sender_id, receiver_id, message, lu)
            VALUES (?, ?, ?, 0)
        ");
        $success = $stmt->execute([$sender_id, $receiver_id, $message]);

        if ($success) {
            echo json_encode(['success' => 'Message envoyé']);
        } else {
            echo json_encode(['error' => 'Erreur lors de l\'envoi']);
        }
    }
}