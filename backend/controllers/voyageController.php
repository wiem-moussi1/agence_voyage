<?php
/**
 * Contrôleur pour la gestion des voyages
 *
 * Ce contrôleur gère toutes les opérations liées aux voyages :
 * - Affichage public des voyages disponibles
 * - Gestion administrative complète (CRUD) des voyages
 * - Gestion des images de voyages
 * - Statistiques des réservations par voyage
 *
 * @author Système de gestion d'agence de voyage
 * @version 1.0
 */
require_once __DIR__ . '/../config/db.php';

class VoyageController {

    /**
     * Obtient une connexion à la base de données
     *
     * @return PDO Instance de connexion à la base de données
     */
    private static function db() {
        return Database::connect();
    }

    /**
     * Vérifie si un utilisateur a le rôle d'administrateur
     *
     * @param int $user_id L'identifiant de l'utilisateur à vérifier
     * @return bool True si l'utilisateur est admin, false sinon
     */
    private static function checkAdmin($user_id) {
        try {
            $stmt = self::db()->prepare("SELECT role FROM user WHERE id = ?");
            $stmt->execute([$user_id]);
            return $stmt->fetchColumn() === 'Admin';
        } catch (Exception $e) {
            error_log("Erreur lors de la vérification du rôle admin: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Parse les données de la requête selon le Content-Type
     *
     * Gère à la fois les données JSON et multipart/form-data pour l'upload d'images
     *
     * @return array Données parsées de la requête
     */
    private static function parseRequestData() {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        // Gestion des données multipart/form-data (pour upload d'images)
        if (stripos($contentType, 'multipart/form-data') !== false) {
            return $_POST;
        }

        // Gestion des données JSON standard
        $payload = file_get_contents('php://input');
        return json_decode($payload, true) ?? [];
    }

    /**
     * Sauvegarde une image uploadée sur le serveur
     *
     * Gère l'upload, la validation et la suppression de l'ancienne image.
     * Génère un nom de fichier unique et sécurisé.
     *
     * @param string|null $currentImage Chemin de l'image actuelle (pour suppression)
     * @param string|null &$error Message d'erreur en cas de problème
     * @return string|null Chemin relatif de la nouvelle image ou image actuelle
     */
    private static function saveUploadedImage($currentImage = null, &$error = null) {
        // Vérifier si un fichier a été uploadé
        if (!isset($_FILES['image_file']) || $_FILES['image_file']['error'] === UPLOAD_ERR_NO_FILE) {
            return $currentImage; // Pas de nouvelle image, garder l'actuelle
        }

        // Vérifier les erreurs d'upload
        if ($_FILES['image_file']['error'] !== UPLOAD_ERR_OK) {
            $error = 'Erreur lors de l\'upload de l\'image (code: ' . $_FILES['image_file']['error'] . ')';
            return null;
        }

        $tmpName = $_FILES['image_file']['tmp_name'];

        // Validation du type d'image
        $info = getimagesize($tmpName);
        if (!$info) {
            $error = 'Le fichier téléchargé n\'est pas une image valide';
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
            $error = 'Type d\'image non supporté. Utilisez jpg, png, webp ou gif.';
            return null;
        }

        // Création du dossier de destination si nécessaire
        $folder = __DIR__ . '/../../images/voyages';
        if (!is_dir($folder)) {
            if (!mkdir($folder, 0755, true)) {
                $error = 'Impossible de créer le dossier de destination pour les images';
                return null;
            }
        }

        // Génération d'un nom de fichier unique et sécurisé
        $filename = 'voyage_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $extensions[$mime];
        $destination = $folder . '/' . $filename;

        // Déplacement du fichier uploadé
        if (!move_uploaded_file($tmpName, $destination)) {
            $error = 'Impossible de sauvegarder l\'image sur le serveur';
            return null;
        }

        // Suppression de l'ancienne image si elle existe
        if ($currentImage) {
            $oldPath = $_SERVER['DOCUMENT_ROOT'] . str_replace('/', DIRECTORY_SEPARATOR, $currentImage);
            if (file_exists($oldPath) && is_file($oldPath)) {
                @unlink($oldPath);
            }
        }

        // Retour du chemin relatif pour la base de données
        $root = dirname(dirname($_SERVER['SCRIPT_NAME']));
        return $root . '/images/voyages/' . $filename;
    }

    // ===========================================
    // MÉTHODES CLIENT
    // ===========================================

    /**
     * Récupère tous les voyages disponibles (vue publique)
     *
     * Retourne la liste complète des voyages pour l'affichage public.
     * Accessible à tous les utilisateurs sans authentification.
     *
     * @return void Retourne une réponse JSON avec la liste des voyages
     */
    public static function getAll() {
        try {
            $db = self::db();
            $stmt = $db->query("SELECT * FROM voyage ORDER BY date_depart ASC");
            $voyages = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($voyages);
        } catch (Exception $e) {
            error_log("Erreur dans VoyageController::getAll(): " . $e->getMessage());
            echo json_encode(['error' => 'Erreur interne du serveur']);
        }
    }

    // ===========================================
    // MÉTHODES ADMIN
    // ===========================================

    /**
     * Récupère tous les voyages avec statistiques (vue admin)
     *
     * Accessible uniquement aux administrateurs. Inclut des statistiques
     * détaillées sur les réservations pour chaque voyage.
     *
     * @return void Retourne une réponse JSON avec les voyages et leurs stats
     */
    public static function adminGetAll() {
        try {
            $user_id = $_GET['admin_id'] ?? null;

            if (!$user_id || !self::checkAdmin($user_id)) {
                http_response_code(403);
                echo json_encode(['error' => 'Accès non autorisé - droits administrateur requis']);
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

        } catch (Exception $e) {
            error_log("Erreur dans VoyageController::adminGetAll(): " . $e->getMessage());
            echo json_encode(['error' => 'Erreur interne du serveur']);
        }
    }

    /**
     * Récupère les réservations d'un voyage spécifique (vue admin)
     *
     * Permet à un administrateur de voir toutes les réservations
     * associées à un voyage particulier.
     *
     * @return void Retourne une réponse JSON avec les réservations du voyage
     */
    public static function adminGetReservationsByVoyage() {
        try {
            $user_id = $_GET['admin_id'] ?? null;

            if (!$user_id || !self::checkAdmin($user_id)) {
                http_response_code(403);
                echo json_encode(['error' => 'Accès non autorisé - droits administrateur requis']);
                return;
            }

            $voyage_id = $_GET['voyage_id'] ?? null;
            if (!$voyage_id) {
                echo json_encode(['error' => 'Paramètre voyage_id manquant']);
                return;
            }

            // Validation de voyage_id
            if (!is_numeric($voyage_id)) {
                echo json_encode(['error' => 'voyage_id doit être un nombre valide']);
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

        } catch (Exception $e) {
            error_log("Erreur dans VoyageController::adminGetReservationsByVoyage(): " . $e->getMessage());
            echo json_encode(['error' => 'Erreur interne du serveur']);
        }
    }

    /**
     * Crée un nouveau voyage (admin uniquement)
     *
     * Permet à un administrateur de créer un nouveau voyage avec
     * gestion de l'upload d'image incluse.
     *
     * @return void Retourne une réponse JSON avec le statut de création
     */
    public static function adminCreate() {
        try {
            $user_id = $_GET['admin_id'] ?? null;

            if (!$user_id || !self::checkAdmin($user_id)) {
                http_response_code(403);
                echo json_encode(['error' => 'Accès non autorisé - droits administrateur requis']);
                return;
            }

            // Récupération et validation des données
            $data = self::parseRequestData();
            $titre = trim($data['titre'] ?? '');
            $description = trim($data['description'] ?? '');
            $prix = $data['prix'] ?? 0;
            $date_depart = $data['date_depart'] ?? null;
            $date_retour = $data['date_retour'] ?? null;
            $image = trim($data['image'] ?? '');
            $places_dispo = intval($data['places_dispo'] ?? 0);

            // Gestion de l'upload d'image
            $uploadError = null;
            $image = self::saveUploadedImage($image, $uploadError);
            if ($uploadError) {
                echo json_encode(['error' => $uploadError]);
                return;
            }

            // Validation des champs obligatoires
            if (!$titre || !$prix || !$date_depart) {
                echo json_encode(['error' => 'Titre, prix et date de départ sont obligatoires']);
                return;
            }

            // Validation du prix
            if (!is_numeric($prix) || $prix <= 0) {
                echo json_encode(['error' => 'Le prix doit être un nombre positif']);
                return;
            }

            // Validation des dates
            if ($date_retour && $date_retour < $date_depart) {
                echo json_encode(['error' => 'La date de retour doit être après la date de départ']);
                return;
            }

            // Validation des places disponibles
            if ($places_dispo < 0) {
                echo json_encode(['error' => 'Le nombre de places ne peut pas être négatif']);
                return;
            }

            // Insertion en base de données
            $db = self::db();
            $stmt = $db->prepare("
                INSERT INTO voyage (titre, description, prix, date_depart, date_retour, image, places_dispo)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $ok = $stmt->execute([$titre, $description, $prix, $date_depart, $date_retour ?: null, $image, $places_dispo]);

            if ($ok) {
                echo json_encode([
                    'success' => 'Voyage créé avec succès',
                    'id' => $db->lastInsertId()
                ]);
            } else {
                echo json_encode(['error' => 'Erreur lors de la création du voyage']);
            }

        } catch (Exception $e) {
            error_log("Erreur dans VoyageController::adminCreate(): " . $e->getMessage());
            echo json_encode(['error' => 'Erreur interne du serveur']);
        }
    }

    /**
     * Met à jour un voyage existant (admin uniquement)
     *
     * Permet à un administrateur de modifier un voyage existant.
     * Supporte la mise à jour de l'image et de tous les autres champs.
     *
     * @return void Retourne une réponse JSON avec le statut de mise à jour
     */
    public static function adminUpdate() {
        try {
            $user_id = $_GET['admin_id'] ?? null;

            if (!$user_id || !self::checkAdmin($user_id)) {
                http_response_code(403);
                echo json_encode(['error' => 'Accès non autorisé - droits administrateur requis']);
                return;
            }

            $data = self::parseRequestData();
            $id = $data['id'] ?? null;

            if (!$id) {
                echo json_encode(['error' => 'ID du voyage manquant']);
                return;
            }

            // Vérification que le voyage existe
            $stmt = self::db()->prepare("SELECT id FROM voyage WHERE id = ?");
            $stmt->execute([$id]);
            if (!$stmt->fetch()) {
                echo json_encode(['error' => 'Voyage introuvable']);
                return;
            }

            // Gestion de l'image
            $hasImageField = array_key_exists('image', $data);
            $currentImage = trim($data['image'] ?? '');
            $uploadError = null;
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

            // Construction de la requête de mise à jour dynamique
            $fields = [];
            $params = [];
            $allowed = ['titre', 'description', 'prix', 'date_depart', 'date_retour', 'image', 'places_dispo'];

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
                    $value = $data[$field];

                    // Validation spécifique selon le champ
                    switch ($field) {
                        case 'prix':
                            if (!is_numeric($value) || $value <= 0) {
                                echo json_encode(['error' => 'Le prix doit être un nombre positif']);
                                return;
                            }
                            break;
                        case 'places_dispo':
                            if (!is_numeric($value) || $value < 0) {
                                echo json_encode(['error' => 'Le nombre de places ne peut pas être négatif']);
                                return;
                            }
                            break;
                        case 'titre':
                            $value = trim($value);
                            if (empty($value)) {
                                echo json_encode(['error' => 'Le titre ne peut pas être vide']);
                                return;
                            }
                            break;
                    }

                    $params[] = $value === '' ? null : $value;
                }
            }

            if (empty($fields)) {
                echo json_encode(['error' => 'Aucune donnée à mettre à jour']);
                return;
            }

            // Exécution de la mise à jour
            $params[] = $id;
            $stmt = self::db()->prepare("UPDATE voyage SET " . implode(', ', $fields) . " WHERE id = ?");
            $ok = $stmt->execute($params);

            if ($ok) {
                echo json_encode(['success' => 'Voyage mis à jour avec succès']);
            } else {
                echo json_encode(['error' => 'Erreur lors de la mise à jour du voyage']);
            }

        } catch (Exception $e) {
            error_log("Erreur dans VoyageController::adminUpdate(): " . $e->getMessage());
            echo json_encode(['error' => 'Erreur interne du serveur']);
        }
    }

    /**
     * Supprime un voyage (admin uniquement)
     *
     * Supprime un voyage après vérification qu'il n'y a pas de réservations actives.
     * Les réservations annulées sont automatiquement supprimées.
     *
     * @return void Retourne une réponse JSON avec le statut de suppression
     */
    public static function adminDelete() {
        try {
            $user_id = $_GET['admin_id'] ?? null;

            if (!$user_id || !self::checkAdmin($user_id)) {
                http_response_code(403);
                echo json_encode(['error' => 'Accès non autorisé - droits administrateur requis']);
                return;
            }

            $id = $_GET['id'] ?? null;
            if (!$id) {
                echo json_encode(['error' => 'ID du voyage manquant']);
                return;
            }

            // Validation de l'ID
            if (!is_numeric($id)) {
                echo json_encode(['error' => 'ID doit être un nombre valide']);
                return;
            }

            $db = self::db();

            // Vérification des réservations actives
            $stmt = $db->prepare("SELECT COUNT(*) FROM reservation WHERE voyage_id = ? AND statut != 'annulee'");
            $stmt->execute([$id]);
            $count = $stmt->fetchColumn();

            if ($count > 0) {
                echo json_encode(['error' => "Impossible de supprimer : $count réservation(s) active(s) liée(s) à ce voyage"]);
                return;
            }

            // Suppression des réservations annulées liées au voyage
            $db->prepare("DELETE FROM reservation WHERE voyage_id = ? AND statut = 'annulee'")->execute([$id]);

            // Suppression du voyage
            $stmt = $db->prepare("DELETE FROM voyage WHERE id = ?");
            $ok = $stmt->execute([$id]);

            if ($ok) {
                echo json_encode(['success' => 'Voyage supprimé avec succès']);
            } else {
                echo json_encode(['error' => 'Erreur lors de la suppression du voyage']);
            }

        } catch (Exception $e) {
            error_log("Erreur dans VoyageController::adminDelete(): " . $e->getMessage());
            echo json_encode(['error' => 'Erreur interne du serveur']);
        }
    }
}
