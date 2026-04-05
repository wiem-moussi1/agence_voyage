<?php
/**
 * Contrôleur pour la gestion des messages
 *
 * Ce contrôleur gère le système de messagerie entre clients et administrateurs :
 * - Récupération des conversations client-admin
 * - Envoi de messages par les clients
 * - Gestion administrative des messages (consultation et réponse)
 * - Marquage automatique des messages comme lus
 *
 * @author Système de gestion d'agence de voyage
 * @version 1.0
 */
require_once __DIR__ . '/../config/db.php';

class messageController {

    /**
     * Vérifie si un utilisateur a le rôle d'administrateur
     *
     * @param int $user_id L'identifiant de l'utilisateur à vérifier
     * @return bool True si l'utilisateur est admin, false sinon
     */
    private static function checkAdmin($user_id) {
        try {
            $db = Database::connect();
            $stmt = $db->prepare("SELECT role FROM user WHERE id = ?");
            $stmt->execute([$user_id]);
            $role = $stmt->fetchColumn();
            return $role === 'Admin';
        } catch (Exception $e) {
            error_log("Erreur lors de la vérification du rôle admin: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Récupère l'ID de l'administrateur principal
     *
     * @return int|null L'ID de l'admin ou null si aucun admin trouvé
     */
    private static function getAdminId() {
        try {
            $db = Database::connect();
            $stmt = $db->prepare("SELECT id FROM user WHERE role = 'Admin' LIMIT 1");
            $stmt->execute();
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);
            return $admin ? $admin['id'] : null;
        } catch (Exception $e) {
            error_log("Erreur lors de la récupération de l'ID admin: " . $e->getMessage());
            return null;
        }
    }

    // ===========================================
    // MÉTHODES CLIENT
    // ===========================================

    /**
     * Récupère la conversation entre un client et l'administrateur
     *
     * Retourne tous les messages échangés entre le client spécifié
     * et l'administrateur, puis marque les messages reçus comme lus.
     *
     * @return void Retourne une réponse JSON avec l'ID admin et les messages
     */
    public static function getConversation() {
        try {
            $user_id = $_GET['user_id'] ?? null;

            if (!$user_id) {
                echo json_encode(['error' => 'Paramètre user_id manquant']);
                return;
            }

            // Validation de user_id
            if (!is_numeric($user_id)) {
                echo json_encode(['error' => 'user_id doit être un nombre valide']);
                return;
            }

            // Récupération de l'ID admin
            $admin_id = self::getAdminId();
            if (!$admin_id) {
                echo json_encode(['error' => 'Administrateur introuvable - contactez le support']);
                return;
            }

            $db = Database::connect();

            // Récupération des messages de la conversation
            $stmt = $db->prepare("
                SELECT m.*, u.nom as sender_nom, u.prenom as sender_prenom
                FROM message m
                LEFT JOIN user u ON m.sender_id = u.id
                WHERE (m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?)
                ORDER BY m.date_envoi ASC
            ");
            $stmt->execute([$user_id, $admin_id, $admin_id, $user_id]);
            $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Marquage des messages reçus par l'utilisateur comme lus
            $stmt = $db->prepare("
                UPDATE message
                SET lu = 1
                WHERE receiver_id = ? AND sender_id = ? AND lu = 0
            ");
            $stmt->execute([$user_id, $admin_id]);

            echo json_encode([
                'admin_id' => $admin_id,
                'messages' => $messages,
                'unread_count' => count(array_filter($messages, fn($m) => $m['receiver_id'] == $user_id && !$m['lu']))
            ]);

        } catch (Exception $e) {
            error_log("Erreur dans messageController::getConversation(): " . $e->getMessage());
            echo json_encode(['error' => 'Erreur interne du serveur']);
        }
    }

    /**
     * Envoie un message d'un utilisateur à un destinataire
     *
     * Permet à un utilisateur d'envoyer un message à un autre utilisateur.
     * Le message est automatiquement marqué comme non lu pour le destinataire.
     *
     * @return void Retourne une réponse JSON avec le statut d'envoi
     */
    public static function send() {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $sender_id = $data['sender_id'] ?? null;
            $receiver_id = $data['receiver_id'] ?? null;
            $message = trim($data['message'] ?? '');

            // Validation des données
            if (!$sender_id || !$receiver_id) {
                echo json_encode(['error' => 'IDs des utilisateurs manquants']);
                return;
            }

            if (empty($message)) {
                echo json_encode(['error' => 'Le message ne peut pas être vide']);
                return;
            }

            if (strlen($message) > 1000) {
                echo json_encode(['error' => 'Le message est trop long (maximum 1000 caractères)']);
                return;
            }

            // Validation des IDs numériques
            if (!is_numeric($sender_id) || !is_numeric($receiver_id)) {
                echo json_encode(['error' => 'Les IDs doivent être des nombres valides']);
                return;
            }

            // Vérification que les utilisateurs existent
            $db = Database::connect();
            $stmt = $db->prepare("SELECT COUNT(*) FROM user WHERE id IN (?, ?)");
            $stmt->execute([$sender_id, $receiver_id]);
            if ($stmt->fetchColumn() != 2) {
                echo json_encode(['error' => 'Un des utilisateurs n\'existe pas']);
                return;
            }

            // Insertion du message
            $stmt = $db->prepare("
                INSERT INTO message (sender_id, receiver_id, message, lu, date_envoi)
                VALUES (?, ?, ?, 0, NOW())
            ");
            $success = $stmt->execute([$sender_id, $receiver_id, $message]);

            if ($success) {
                echo json_encode([
                    'success' => 'Message envoyé avec succès',
                    'message_id' => $db->lastInsertId()
                ]);
            } else {
                echo json_encode(['error' => 'Erreur lors de l\'envoi du message']);
            }

        } catch (Exception $e) {
            error_log("Erreur dans messageController::send(): " . $e->getMessage());
            echo json_encode(['error' => 'Erreur interne du serveur']);
        }
    }

    // ===========================================
    // MÉTHODES ADMIN
    // ===========================================

    /**
     * Récupère tous les messages pour l'administration
     *
     * Accessible uniquement aux administrateurs. Retourne tous les messages
     * avec les informations complètes des expéditeurs et destinataires.
     *
     * @return void Retourne une réponse JSON avec la liste complète des messages
     */
    public static function adminGetAll() {
        try {
            $user_id = $_GET['admin_id'] ?? null;

            if (!$user_id || !self::checkAdmin($user_id)) {
                http_response_code(403);
                echo json_encode(['error' => 'Accès non autorisé - droits administrateur requis']);
                return;
            }

            $db = Database::connect();

            // Récupération de tous les messages avec informations des utilisateurs
            $stmt = $db->query("
                SELECT m.*,
                       sender.nom AS sender_nom, sender.prenom AS sender_prenom,
                       receiver.nom AS receiver_nom, receiver.prenom AS receiver_prenom
                FROM message m
                JOIN user sender ON m.sender_id = sender.id
                JOIN user receiver ON m.receiver_id = receiver.id
                ORDER BY m.date_envoi DESC
                LIMIT 1000
            ");
            $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode($messages);

        } catch (Exception $e) {
            error_log("Erreur dans messageController::adminGetAll(): " . $e->getMessage());
            echo json_encode(['error' => 'Erreur interne du serveur']);
        }
    }

    /**
     * Envoie une réponse de l'administrateur à un client
     *
     * Permet à un administrateur d'envoyer un message à un client.
     * L'expéditeur doit obligatoirement être un administrateur.
     *
     * @return void Retourne une réponse JSON avec le statut d'envoi
     */
    public static function adminReply() {
        try {
            $user_id = $_GET['admin_id'] ?? null;

            if (!$user_id || !self::checkAdmin($user_id)) {
                http_response_code(403);
                echo json_encode(['error' => 'Accès non autorisé - droits administrateur requis']);
                return;
            }

            $data = json_decode(file_get_contents('php://input'), true);
            $receiver_id = $data['receiver_id'] ?? null;
            $message = trim($data['message'] ?? '');

            // Validation des données
            if (!$receiver_id) {
                echo json_encode(['error' => 'ID du destinataire manquant']);
                return;
            }

            if (empty($message)) {
                echo json_encode(['error' => 'Le message ne peut pas être vide']);
                return;
            }

            if (strlen($message) > 1000) {
                echo json_encode(['error' => 'Le message est trop long (maximum 1000 caractères)']);
                return;
            }

            // Validation de receiver_id
            if (!is_numeric($receiver_id)) {
                echo json_encode(['error' => 'receiver_id doit être un nombre valide']);
                return;
            }

            $db = Database::connect();

            // Vérification que le destinataire existe
            $stmt = $db->prepare("SELECT id FROM user WHERE id = ?");
            $stmt->execute([$receiver_id]);
            if (!$stmt->fetch()) {
                echo json_encode(['error' => 'Destinataire introuvable']);
                return;
            }

            // Insertion de la réponse admin
            $stmt = $db->prepare("
                INSERT INTO message (sender_id, receiver_id, message, lu, date_envoi)
                VALUES (?, ?, ?, 0, NOW())
            ");
            $success = $stmt->execute([$user_id, $receiver_id, $message]);

            if ($success) {
                echo json_encode([
                    'success' => 'Réponse envoyée avec succès',
                    'message_id' => $db->lastInsertId()
                ]);
            } else {
                echo json_encode(['error' => 'Erreur lors de l\'envoi de la réponse']);
            }

        } catch (Exception $e) {
            error_log("Erreur dans messageController::adminReply(): " . $e->getMessage());
            echo json_encode(['error' => 'Erreur interne du serveur']);
        }
    }
}