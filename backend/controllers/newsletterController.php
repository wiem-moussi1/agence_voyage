<?php
require_once __DIR__ . '/../config/db.php';

class newsletterController {

    /** POST — Abonner un visiteur */
    public static function subscribe() {
        $data = json_decode(file_get_contents('php://input'), true);
        $nom   = trim($data['nom']   ?? '');
        $email = trim($data['email'] ?? '');

        if (!$nom || !$email) {
            echo json_encode(['error' => 'Nom et email requis']);
            return;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['error' => 'Adresse email invalide']);
            return;
        }

        $db = Database::connect();

        // Vérifier si déjà inscrit
        $stmt = $db->prepare("SELECT id FROM newsletter WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            echo json_encode(['error' => 'Cette adresse est déjà inscrite à la newsletter']);
            return;
        }

        $stmt = $db->prepare("INSERT INTO newsletter (nom, email) VALUES (?, ?)");
        $ok   = $stmt->execute([$nom, $email]);

        if ($ok) {
            echo json_encode(['success' => 'Inscription réussie !']);
        } else {
            echo json_encode(['error' => 'Erreur lors de l\'inscription']);
        }
    }

    /** GET (admin) — Lister tous les abonnés */
    public static function getAll() {
        $db   = Database::connect();
        $stmt = $db->query("SELECT * FROM newsletter ORDER BY date_inscription DESC");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** DELETE (admin) — Supprimer un abonné */
    public static function delete() {
        $data = json_decode(file_get_contents('php://input'), true);
        $id   = $data['id'] ?? null;

        if (!$id) {
            echo json_encode(['error' => 'id manquant']);
            return;
        }

        $db   = Database::connect();
        $stmt = $db->prepare("DELETE FROM newsletter WHERE id = ?");
        $ok   = $stmt->execute([$id]);

        echo $ok
            ? json_encode(['success' => 'Abonné supprimé'])
            : json_encode(['error'   => 'Erreur suppression']);
    }
}