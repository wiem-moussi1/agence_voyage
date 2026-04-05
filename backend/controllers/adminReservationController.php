<?php
require_once __DIR__ . '/../config/db.php';

class adminReservationController {
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
        $stmt = $db->query("
            SELECT r.*, u.nom, u.prenom, u.email, v.titre 
            FROM reservation r
            JOIN user u ON r.user_id = u.id
            JOIN voyage v ON r.voyage_id = v.id
            ORDER BY r.date_reserv DESC
        ");
        $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($reservations);
    }

    public static function updateStatus() {
        $user_id = $_GET['admin_id'] ?? null;
        if (!$user_id || !self::checkAdmin($user_id)) {
            http_response_code(403);
            echo json_encode(['error' => 'Accès non autorisé']);
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $id = $data['id'] ?? null;
        $statut = $data['statut'] ?? null;

        if (!$id || !$statut) {
            echo json_encode(['error' => 'Données incomplètes']);
            return;
        }

        $db = Database::connect();
        $stmt = $db->prepare("UPDATE reservation SET statut = ? WHERE id = ?");
        $success = $stmt->execute([$statut, $id]);

        if ($success) {
            echo json_encode(['success' => 'Statut mis à jour']);
        } else {
            echo json_encode(['error' => 'Erreur lors de la mise à jour']);
        }
    }

    public static function cancel() {
        $user_id = $_GET['admin_id'] ?? null;
        if (!$user_id || !self::checkAdmin($user_id)) {
            http_response_code(403);
            echo json_encode(['error' => 'Accès non autorisé']);
            return;
        }

        $id = $_GET['id'] ?? null;
        if (!$id) {
            echo json_encode(['error' => 'ID manquant']);
            return;
        }

        $db = Database::connect();

        // Récupérer les infos de réservation pour remettre les places
        $stmt = $db->prepare("SELECT voyage_id, nb_personnes FROM reservation WHERE id = ?");
        $stmt->execute([$id]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$res) {
            echo json_encode(['error' => 'Réservation introuvable']);
            return;
        }

        // Annuler
        $stmt = $db->prepare("UPDATE reservation SET statut = 'annulee' WHERE id = ?");
        $stmt->execute([$id]);

        // Remettre les places
        $stmt = $db->prepare("UPDATE voyage SET places_dispo = places_dispo + ? WHERE id = ?");
        $stmt->execute([$res['nb_personnes'], $res['voyage_id']]);

        echo json_encode(['success' => 'Réservation annulée']);
    }
}