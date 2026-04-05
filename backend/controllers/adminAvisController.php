<?php
require_once __DIR__ . '/../config/db.php';

class adminAvisController {
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
            SELECT a.*, u.nom, u.prenom, u.email, v.titre
            FROM avis a
            JOIN user u ON a.user_id = u.id
            JOIN voyage v ON a.voyage_id = v.id
            ORDER BY a.date_avis DESC
        ");
        $avis = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($avis);
    }

    public static function validate() {
        $user_id = $_GET['admin_id'] ?? null;
        if (!$user_id || !self::checkAdmin($user_id)) {
            http_response_code(403);
            echo json_encode(['error' => 'Accès non autorisé']);
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        $id = $data['id'] ?? null;
        $statut = $data['statut'] ?? null; // 'valide' ou 'refuse'

        if (!$id || !$statut) {
            echo json_encode(['error' => 'Données incomplètes']);
            return;
        }

        $db = Database::connect();
        $stmt = $db->prepare("UPDATE avis SET statut = ? WHERE id = ?");
        $success = $stmt->execute([$statut, $id]);

        if ($success) {
            echo json_encode(['success' => 'Avis mis à jour']);
        } else {
            echo json_encode(['error' => 'Erreur lors de la mise à jour']);
        }
    }
}