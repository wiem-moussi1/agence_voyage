<?php
require_once __DIR__ . '/../config/db.php';

class reservationController {
    public static function getUserReservations() {
        $user_id = $_GET['user_id'] ?? null;
        if (!$user_id) {
            echo json_encode(['error' => 'user_id manquant']);
            return;
        }
        global $pdo;
$db = $pdo;
        $stmt = $db->prepare("
            SELECT r.*, v.titre, v.date_depart, v.date_retour, v.image 
            FROM reservation r 
            JOIN voyage v ON r.voyage_id = v.id 
            WHERE r.user_id = ?
            ORDER BY r.date_reserv DESC
        ");
        $stmt->execute([$user_id]);
        $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($reservations);
    }

    public static function create() {
        $data = json_decode(file_get_contents('php://input'), true);
        $user_id = $data['user_id'] ?? null;
        $voyage_id = $data['voyage_id'] ?? null;
        $nb_personnes = $data['nb_personnes'] ?? 1;
        $mode_paiement = $data['mode_paiement'] ?? 'carte';

        if (!$user_id || !$voyage_id) {
            echo json_encode(['error' => 'Données incomplètes']);
            return;
        }

        global $pdo;
        $db = $pdo;

        // Récupérer le prix du voyage
        $stmt = $db->prepare("SELECT prix FROM voyage WHERE id = ?");
        $stmt->execute([$voyage_id]);
        $voyage = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$voyage) {
            echo json_encode(['error' => 'Voyage introuvable']);
            return;
        }
        $prix_total = $voyage['prix'] * $nb_personnes;

        // Vérifier les places disponibles
        $stmt = $db->prepare("SELECT places_dispo FROM voyage WHERE id = ?");
        $stmt->execute([$voyage_id]);
        $places = $stmt->fetchColumn();
        if ($places < $nb_personnes) {
            echo json_encode(['error' => 'Pas assez de places disponibles']);
            return;
        }

        // Insérer la réservation
        $stmt = $db->prepare("
            INSERT INTO reservation (user_id, voyage_id, nb_personnes, prix_total, mode_paiement, statut)
            VALUES (?, ?, ?, ?, ?, 'en_attente')
        ");
        $success = $stmt->execute([$user_id, $voyage_id, $nb_personnes, $prix_total, $mode_paiement]);

        if ($success) {
            // Réduire les places disponibles
            $new_places = $places - $nb_personnes;
            $stmt = $db->prepare("UPDATE voyage SET places_dispo = ? WHERE id = ?");
            $stmt->execute([$new_places, $voyage_id]);

            echo json_encode(['success' => 'Réservation créée avec succès', 'id' => $db->lastInsertId()]);
        } else {
            echo json_encode(['error' => 'Erreur lors de la réservation']);
        }
    }

    public static function cancel() {
        $id = $_GET['id'] ?? null;
        $user_id = $_GET['user_id'] ?? null; // pour vérifier que c'est bien le propriétaire
        if (!$id || !$user_id) {
            echo json_encode(['error' => 'Paramètres manquants']);
            return;
        }

        global $pdo;
        $db = $pdo;

        // Vérifier propriétaire
        $stmt = $db->prepare("SELECT user_id, voyage_id, nb_personnes FROM reservation WHERE id = ?");
        $stmt->execute([$id]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$res || $res['user_id'] != $user_id) {
            echo json_encode(['error' => 'Réservation non trouvée ou non autorisée']);
            return;
        }

        // Annuler la réservation
        $stmt = $db->prepare("UPDATE reservation SET statut = 'annulee' WHERE id = ?");
        $stmt->execute([$id]);

        // Remettre les places
        $stmt = $db->prepare("UPDATE voyage SET places_dispo = places_dispo + ? WHERE id = ?");
        $stmt->execute([$res['nb_personnes'], $res['voyage_id']]);

        echo json_encode(['success' => 'Réservation annulée']);
    }
}