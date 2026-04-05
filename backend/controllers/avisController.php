<?php
require_once __DIR__ . '/../config/db.php';

class avisController {
    public static function add() {
        $data = json_decode(file_get_contents('php://input'), true);
        $user_id = $data['user_id'] ?? null;
        $voyage_id = $data['voyage_id'] ?? null;
        $note = $data['note'] ?? null;
        $commentaire = $data['commentaire'] ?? '';

        if (!$user_id || !$voyage_id || !$note) {
            echo json_encode(['error' => 'Données incomplètes']);
            return;
        }

        // Vérifier que l'utilisateur a bien réservé ce voyage (optionnel mais recommandé)
        $db = Database::connect();
        $stmt = $db->prepare("SELECT id FROM reservation WHERE user_id = ? AND voyage_id = ? AND statut = 'confirmee'");
        $stmt->execute([$user_id, $voyage_id]);
        if (!$stmt->fetch()) {
            echo json_encode(['error' => 'Vous ne pouvez commenter que des voyages que vous avez réservés et confirmés']);
            return;
        }

        // Vérifier si un avis existe déjà pour ce voyage par cet utilisateur
        $stmt = $db->prepare("SELECT id FROM avis WHERE user_id = ? AND voyage_id = ?");
        $stmt->execute([$user_id, $voyage_id]);
        if ($stmt->fetch()) {
            echo json_encode(['error' => 'Vous avez déjà laissé un avis pour ce voyage']);
            return;
        }

        $stmt = $db->prepare("
            INSERT INTO avis (user_id, voyage_id, note, commentaire, statut)
            VALUES (?, ?, ?, ?, 'en_attente')
        ");
        $success = $stmt->execute([$user_id, $voyage_id, $note, $commentaire]);

        if ($success) {
            echo json_encode(['success' => 'Avis soumis avec succès (en attente de validation)']);
        } else {
            echo json_encode(['error' => 'Erreur lors de l\'enregistrement']);
        }
    }

    public static function getUserAvis() {
        $user_id = $_GET['user_id'] ?? null;
        if (!$user_id) {
            echo json_encode(['error' => 'user_id manquant']);
            return;
        }

        $db = Database::connect();
        $stmt = $db->prepare("
            SELECT a.*, v.titre
            FROM avis a
            JOIN voyage v ON a.voyage_id = v.id
            WHERE a.user_id = ?
            ORDER BY a.date_avis DESC
        ");
        $stmt->execute([$user_id]);
        $avis = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($avis);
    }
}