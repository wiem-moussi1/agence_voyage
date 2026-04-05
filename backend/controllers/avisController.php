<?php
/**
 * Contrôleur pour la gestion des avis clients
 *
 * Ce contrôleur gère toutes les opérations liées aux avis sur les voyages :
 * - Soumission d'avis par les clients
 * - Récupération des avis d'un utilisateur
 * - Gestion administrative des avis (validation/refus)
 *
 * @author Système de gestion d'agence de voyage
 * @version 1.0
 */
require_once __DIR__ . '/../config/db.php';

class avisController {

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

    // ===========================================
    // MÉTHODES CLIENT
    // ===========================================

    /**
     * Soumet un nouvel avis pour un voyage
     *
     * Permet à un client de laisser un avis sur un voyage qu'il a réservé.
     * L'avis est mis en attente de validation par un administrateur.
     *
     * @return void Retourne une réponse JSON avec le statut de l'opération
     */
    public static function add() {
        try {
            // Récupération et validation des données d'entrée
            $data = json_decode(file_get_contents('php://input'), true);
            $user_id = $data['user_id'] ?? null;
            $voyage_id = $data['voyage_id'] ?? null;
            $note = $data['note'] ?? null;
            $commentaire = $data['commentaire'] ?? '';

            // Validation des données obligatoires
            if (!$user_id || !$voyage_id || !$note) {
                echo json_encode(['error' => 'Données incomplètes : user_id, voyage_id et note sont requis']);
                return;
            }

            // Validation de la note (doit être entre 1 et 5)
            if (!is_numeric($note) || $note < 1 || $note > 5) {
                echo json_encode(['error' => 'La note doit être un nombre entre 1 et 5']);
                return;
            }

            $db = Database::connect();

            // Vérification que l'utilisateur a bien réservé ce voyage
            $stmt = $db->prepare("SELECT id FROM reservation WHERE user_id = ? AND voyage_id = ? AND statut = 'confirmee'");
            $stmt->execute([$user_id, $voyage_id]);
            if (!$stmt->fetch()) {
                echo json_encode(['error' => 'Vous ne pouvez commenter que des voyages que vous avez réservés et confirmés']);
                return;
            }

            // Vérification si un avis existe déjà pour ce voyage par cet utilisateur
            $stmt = $db->prepare("SELECT id FROM avis WHERE user_id = ? AND voyage_id = ?");
            $stmt->execute([$user_id, $voyage_id]);
            if ($stmt->fetch()) {
                echo json_encode(['error' => 'Vous avez déjà laissé un avis pour ce voyage']);
                return;
            }

            // Insertion du nouvel avis
            $stmt = $db->prepare("
                INSERT INTO avis (user_id, voyage_id, note, commentaire, statut, date_avis)
                VALUES (?, ?, ?, ?, 'en_attente', NOW())
            ");
            $success = $stmt->execute([$user_id, $voyage_id, $note, $commentaire]);

            if ($success) {
                echo json_encode([
                    'success' => 'Avis soumis avec succès (en attente de validation)',
                    'avis_id' => $db->lastInsertId()
                ]);
            } else {
                echo json_encode(['error' => 'Erreur lors de l\'enregistrement de l\'avis']);
            }

        } catch (Exception $e) {
            error_log("Erreur dans avisController::add(): " . $e->getMessage());
            echo json_encode(['error' => 'Erreur interne du serveur']);
        }
    }

    /**
     * Récupère tous les avis d'un utilisateur
     *
     * Retourne la liste des avis laissés par un utilisateur spécifique,
     * avec les informations du voyage associé.
     *
     * @return void Retourne une réponse JSON avec la liste des avis
     */
    public static function getUserAvis() {
        try {
            $user_id = $_GET['user_id'] ?? null;

            if (!$user_id) {
                echo json_encode(['error' => 'Paramètre user_id manquant']);
                return;
            }

            // Validation que user_id est un nombre
            if (!is_numeric($user_id)) {
                echo json_encode(['error' => 'user_id doit être un nombre valide']);
                return;
            }

            $db = Database::connect();

            // Récupération des avis avec les informations du voyage
            $stmt = $db->prepare("
                SELECT a.*, v.titre, v.image
                FROM avis a
                JOIN voyage v ON a.voyage_id = v.id
                WHERE a.user_id = ?
                ORDER BY a.date_avis DESC
            ");
            $stmt->execute([$user_id]);
            $avis = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode($avis);

        } catch (Exception $e) {
            error_log("Erreur dans avisController::getUserAvis(): " . $e->getMessage());
            echo json_encode(['error' => 'Erreur interne du serveur']);
        }
    }

    // ===========================================
    // MÉTHODES ADMIN
    // ===========================================

    /**
     * Récupère tous les avis pour l'administration
     *
     * Accessible uniquement aux administrateurs. Retourne tous les avis
     * avec les informations des utilisateurs et voyages associés.
     *
     * @return void Retourne une réponse JSON avec la liste complète des avis
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

            // Récupération de tous les avis avec informations complètes
            $stmt = $db->query("
                SELECT a.*, u.nom, u.prenom, u.email, v.titre, v.date_depart
                FROM avis a
                JOIN user u ON a.user_id = u.id
                JOIN voyage v ON a.voyage_id = v.id
                ORDER BY a.date_avis DESC
            ");
            $avis = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode($avis);

        } catch (Exception $e) {
            error_log("Erreur dans avisController::adminGetAll(): " . $e->getMessage());
            echo json_encode(['error' => 'Erreur interne du serveur']);
        }
    }

    /**
     * Valide ou refuse un avis par un administrateur
     *
     * Permet à un administrateur de changer le statut d'un avis
     * (valide, refuse, ou remettre en attente).
     *
     * @return void Retourne une réponse JSON avec le statut de l'opération
     */
    public static function adminValidate() {
        try {
            $user_id = $_GET['admin_id'] ?? null;

            if (!$user_id || !self::checkAdmin($user_id)) {
                http_response_code(403);
                echo json_encode(['error' => 'Accès non autorisé - droits administrateur requis']);
                return;
            }

            // Récupération des données de validation
            $data = json_decode(file_get_contents('php://input'), true);
            $id = $data['id'] ?? null;
            $statut = $data['statut'] ?? null; // 'valide', 'refuse', ou 'en_attente'

            // Validation des données
            if (!$id || !$statut) {
                echo json_encode(['error' => 'Données incomplètes : id et statut sont requis']);
                return;
            }

            // Validation du statut
            $statuts_valides = ['valide', 'refuse', 'en_attente'];
            if (!in_array($statut, $statuts_valides)) {
                echo json_encode(['error' => 'Statut invalide. Valeurs acceptées : ' . implode(', ', $statuts_valides)]);
                return;
            }

            $db = Database::connect();

            // Vérification que l'avis existe
            $stmt = $db->prepare("SELECT id FROM avis WHERE id = ?");
            $stmt->execute([$id]);
            if (!$stmt->fetch()) {
                echo json_encode(['error' => 'Avis introuvable']);
                return;
            }

            // Mise à jour du statut
            $stmt = $db->prepare("UPDATE avis SET statut = ? WHERE id = ?");
            $success = $stmt->execute([$statut, $id]);

            if ($success) {
                $message = match($statut) {
                    'valide' => 'Avis validé et publié',
                    'refuse' => 'Avis refusé',
                    'en_attente' => 'Avis remis en attente de validation',
                    default => 'Statut de l\'avis mis à jour'
                };

                echo json_encode(['success' => $message]);
            } else {
                echo json_encode(['error' => 'Erreur lors de la mise à jour du statut']);
            }

        } catch (Exception $e) {
            error_log("Erreur dans avisController::adminValidate(): " . $e->getMessage());
            echo json_encode(['error' => 'Erreur interne du serveur']);
        }
    }
}