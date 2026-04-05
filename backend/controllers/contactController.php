<?php
require_once __DIR__ . '/../config/db.php';

class contactController {

    /** POST — Enregistrer un message de contact */
    public static function send() {
        $data      = json_decode(file_get_contents('php://input'), true);
        $nom       = trim($data['nom']       ?? '');
        $email     = trim($data['email']     ?? '');
        $telephone = trim($data['telephone'] ?? '');
        $sujet     = trim($data['sujet']     ?? '');
        $message   = trim($data['message']   ?? '');

        if (!$nom || !$email || !$message) {
            echo json_encode(['error' => 'Nom, email et message sont requis']);
            return;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['error' => 'Adresse email invalide']);
            return;
        }

        $db   = Database::connect();
        $stmt = $db->prepare("
            INSERT INTO contact (nom, email, telephone, sujet, message)
            VALUES (?, ?, ?, ?, ?)
        ");
        $ok = $stmt->execute([$nom, $email, $telephone, $sujet, $message]);

        if ($ok) {
            echo json_encode(['success' => 'Message envoyé ! Nous vous répondrons sous 24h.']);
        } else {
            echo json_encode(['error' => 'Erreur lors de l\'envoi']);
        }
    }

    /** GET (admin) — Lister tous les messages */
    public static function getAll() {
        $db   = Database::connect();
        $stmt = $db->query("SELECT * FROM contact ORDER BY date_envoi DESC");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** PUT (admin) — Changer le statut d'un message */
    public static function updateStatus() {
        $data   = json_decode(file_get_contents('php://input'), true);
        $id     = $data['id']     ?? null;
        $statut = $data['statut'] ?? null;

        $allowed = ['non_lu', 'lu', 'repondu'];
        if (!$id || !in_array($statut, $allowed)) {
            echo json_encode(['error' => 'Données invalides']);
            return;
        }

        $db   = Database::connect();
        $stmt = $db->prepare("UPDATE contact SET statut = ? WHERE id = ?");
        $ok   = $stmt->execute([$statut, $id]);

        echo $ok
            ? json_encode(['success' => 'Statut mis à jour'])
            : json_encode(['error'   => 'Erreur mise à jour']);
    }

    /** DELETE (admin) — Supprimer un message */
    public static function delete() {
        $data = json_decode(file_get_contents('php://input'), true);
        $id   = $data['id'] ?? null;

        if (!$id) {
            echo json_encode(['error' => 'id manquant']);
            return;
        }

        $db   = Database::connect();
        $stmt = $db->prepare("DELETE FROM contact WHERE id = ?");
        $ok   = $stmt->execute([$id]);

        echo $ok
            ? json_encode(['success' => 'Message supprimé'])
            : json_encode(['error'   => 'Erreur suppression']);
    }
}