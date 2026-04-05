<?php
require_once __DIR__ . '/../config/db.php';

class adminVoyageController {

    private static function db() {
        global $pdo;
        return $pdo;
    }

    private static function checkAdmin($user_id) {
        $stmt = self::db()->prepare("SELECT role FROM user WHERE id = ?");
        $stmt->execute([$user_id]);
        return $stmt->fetchColumn() === 'Admin';
    }

    private static function parseRequestData() {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (stripos($contentType, 'multipart/form-data') !== false) {
            return $_POST;
        }
        $payload = file_get_contents('php://input');
        return json_decode($payload, true) ?? [];
    }

    private static function saveUploadedImage($currentImage = null, &$error = null) {
        if (!isset($_FILES['image_file']) || $_FILES['image_file']['error'] === UPLOAD_ERR_NO_FILE) {
            return $currentImage;
        }

        if ($_FILES['image_file']['error'] !== UPLOAD_ERR_OK) {
            $error = 'Erreur lors de l’upload de l’image';
            return null;
        }

        $tmpName = $_FILES['image_file']['tmp_name'];
        $info = getimagesize($tmpName);
        if (!$info) {
            $error = 'Le fichier téléchargé n’est pas une image valide';
            return null;
        }

        $mime = $info['mime'];
        $extensions = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
            'image/gif'  => 'gif',
        ];

        if (!isset($extensions[$mime])) {
            $error = 'Type d’image non supporté. Utilisez jpg, png, webp ou gif.';
            return null;
        }

        $folder = __DIR__ . '/../../images/voyages';
        if (!is_dir($folder)) {
            mkdir($folder, 0755, true);
        }

        $filename = 'voyage_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $extensions[$mime];
        $destination = $folder . '/' . $filename;
        if (!move_uploaded_file($tmpName, $destination)) {
            $error = 'Impossible de sauvegarder l’image sur le serveur';
            return null;
        }

        if ($currentImage) {
            $oldPath = $_SERVER['DOCUMENT_ROOT'] . str_replace('/', DIRECTORY_SEPARATOR, $currentImage);
            if (file_exists($oldPath) && is_file($oldPath)) {
                @unlink($oldPath);
            }
        }

        $root = dirname(dirname($_SERVER['SCRIPT_NAME']));
        return $root . '/images/voyages/' . $filename;
    }

    public static function getAll() {
        $user_id = $_GET['admin_id'] ?? null;
        if (!$user_id || !self::checkAdmin($user_id)) {
            http_response_code(403);
            echo json_encode(['error' => 'Accès non autorisé']);
            return;
        }

        $stmt = self::db()->query("
            SELECT v.*, 
                   COUNT(r.id) as total_reservations,
                   SUM(CASE WHEN r.statut = 'confirmee' THEN 1 ELSE 0 END) as reservations_confirmees,
                   SUM(CASE WHEN r.statut = 'en_attente' THEN 1 ELSE 0 END) as reservations_attente,
                   SUM(CASE WHEN r.statut = 'annulee' THEN 1 ELSE 0 END) as reservations_annulees,
                   COALESCE(SUM(CASE WHEN r.statut != 'annulee' THEN r.prix_total ELSE 0 END), 0) as chiffre_affaires
            FROM voyage v
            LEFT JOIN reservation r ON r.voyage_id = v.id
            GROUP BY v.id
            ORDER BY v.id DESC
        ");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public static function getReservationsByVoyage() {
        $user_id = $_GET['admin_id'] ?? null;
        if (!$user_id || !self::checkAdmin($user_id)) {
            http_response_code(403);
            echo json_encode(['error' => 'Accès non autorisé']);
            return;
        }

        $voyage_id = $_GET['voyage_id'] ?? null;
        if (!$voyage_id) {
            echo json_encode(['error' => 'voyage_id manquant']);
            return;
        }

        $stmt = self::db()->prepare("
            SELECT r.*, u.nom, u.prenom, u.email
            FROM reservation r
            JOIN user u ON r.user_id = u.id
            WHERE r.voyage_id = ?
            ORDER BY r.date_reserv DESC
        ");
        $stmt->execute([$voyage_id]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public static function create() {
        $user_id = $_GET['admin_id'] ?? null;
        if (!$user_id || !self::checkAdmin($user_id)) {
            http_response_code(403);
            echo json_encode(['error' => 'Accès non autorisé']);
            return;
        }

        $data        = self::parseRequestData();
        $titre       = trim($data['titre'] ?? '');
        $description = trim($data['description'] ?? '');
        $prix        = $data['prix'] ?? 0;
        $date_depart = $data['date_depart'] ?? null;
        $date_retour = $data['date_retour'] ?? null;
        $image       = trim($data['image'] ?? '');
        $places_dispo = intval($data['places_dispo'] ?? 0);

        $uploadError = null;
        $image = self::saveUploadedImage($image, $uploadError);
        if ($uploadError) {
            echo json_encode(['error' => $uploadError]);
            return;
        }

        if (!$titre || !$prix || !$date_depart) {
            echo json_encode(['error' => 'Titre, prix et date de départ sont obligatoires']);
            return;
        }

        if ($date_retour && $date_retour < $date_depart) {
            echo json_encode(['error' => 'La date de retour doit être après la date de départ']);
            return;
        }

        $db   = self::db();
        $stmt = $db->prepare("
            INSERT INTO voyage (titre, description, prix, date_depart, date_retour, image, places_dispo)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $ok = $stmt->execute([$titre, $description, $prix, $date_depart, $date_retour ?: null, $image, $places_dispo]);

        echo json_encode($ok
            ? ['success' => 'Voyage créé avec succès', 'id' => $db->lastInsertId()]
            : ['error'   => 'Erreur lors de la création']
        );
    }

    public static function update() {
        $user_id = $_GET['admin_id'] ?? null;
        if (!$user_id || !self::checkAdmin($user_id)) {
            http_response_code(403);
            echo json_encode(['error' => 'Accès non autorisé']);
            return;
        }

        $data = self::parseRequestData();
        $id   = $data['id'] ?? null;
        if (!$id) {
            echo json_encode(['error' => 'ID manquant']);
            return;
        }

        $hasImageField = array_key_exists('image', $data);
        $currentImage = trim($data['image'] ?? '');
        $uploadError  = null;
        $uploadedImage = self::saveUploadedImage($currentImage, $uploadError);
        if ($uploadError) {
            echo json_encode(['error' => $uploadError]);
            return;
        }

        $image = $hasImageField ? $currentImage : null;
        if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] !== UPLOAD_ERR_NO_FILE) {
            $image = $uploadedImage;
            $hasImageField = true;
        }

        $fields  = [];
        $params  = [];
        $allowed = ['titre','description','prix','date_depart','date_retour','image','places_dispo'];
        foreach ($allowed as $field) {
            if ($field === 'image') {
                if ($hasImageField) {
                    $fields[] = "image = ?";
                    $params[] = $image === '' ? null : $image;
                }
                continue;
            }
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = ?";
                $params[] = $data[$field] === '' ? null : $data[$field];
            }
        }

        if (empty($fields)) {
            echo json_encode(['error' => 'Aucune donnée à mettre à jour']);
            return;
        }

        $params[] = $id;
        $stmt = self::db()->prepare("UPDATE voyage SET " . implode(', ', $fields) . " WHERE id = ?");
        $ok   = $stmt->execute($params);

        echo json_encode($ok
            ? ['success' => 'Voyage mis à jour avec succès']
            : ['error'   => 'Erreur lors de la mise à jour']
        );
    }

    public static function delete() {
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

        $db   = self::db();
        $stmt = $db->prepare("SELECT COUNT(*) FROM reservation WHERE voyage_id = ? AND statut != 'annulee'");
        $stmt->execute([$id]);
        $count = $stmt->fetchColumn();

        if ($count > 0) {
            echo json_encode(['error' => "Impossible de supprimer : $count réservation(s) active(s) liée(s) à ce voyage"]);
            return;
        }

        $db->prepare("DELETE FROM reservation WHERE voyage_id = ? AND statut = 'annulee'")->execute([$id]);
        $stmt = $db->prepare("DELETE FROM voyage WHERE id = ?");
        $ok   = $stmt->execute([$id]);

        echo json_encode($ok
            ? ['success' => 'Voyage supprimé avec succès']
            : ['error'   => 'Erreur lors de la suppression']
        );
    }
}