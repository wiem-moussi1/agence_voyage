<?php
/**
 * Contrôleur pour la gestion des réservations
 *
 * Ce contrôleur gère toutes les opérations liées aux réservations de voyages :
 * - Création de réservations par les clients
 * - Consultation des réservations personnelles
 * - Annulation de réservations
 * - Gestion administrative des réservations (validation, annulation)
 * - Gestion automatique des places disponibles
 *
 * @author Système de gestion d'agence de voyage
 * @version 1.0
 */
require_once __DIR__ . '/../config/db.php';

class reservationController {

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
     * Valide les données d'une réservation
     *
     * @param array $data Données de la réservation à valider
     * @return array Tableau avec 'valid' => bool et 'errors' => array des erreurs
     */
    private static function validateReservationData($data) {
        $errors = [];

        // Validation des champs obligatoires
        if (empty($data['user_id'])) {
            $errors[] = 'user_id est obligatoire';
        } elseif (!is_numeric($data['user_id'])) {
            $errors[] = 'user_id doit être un nombre';
        }

        if (empty($data['voyage_id'])) {
            $errors[] = 'voyage_id est obligatoire';
        } elseif (!is_numeric($data['voyage_id'])) {
            $errors[] = 'voyage_id doit être un nombre';
        }

        // Validation du nombre de personnes
        $nb_personnes = $data['nb_personnes'] ?? 1;
        if (!is_numeric($nb_personnes) || $nb_personnes < 1 || $nb_personnes > 20) {
            $errors[] = 'Le nombre de personnes doit être entre 1 et 20';
        }

        // Validation du mode de paiement
        $modes_paiement = ['carte', 'paypal', 'virement', 'especes'];
        $mode_paiement = $data['mode_paiement'] ?? 'carte';
        if (!in_array($mode_paiement, $modes_paiement)) {
            $errors[] = 'Mode de paiement invalide';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'data' => array_merge($data, [
                'nb_personnes' => intval($nb_personnes),
                'mode_paiement' => $mode_paiement
            ])
        ];
    }

    // ===========================================
    // MÉTHODES CLIENT
    // ===========================================

    /**
     * Récupère toutes les réservations d'un utilisateur
     *
     * Retourne la liste complète des réservations d'un client
     * avec les informations détaillées du voyage associé.
     *
     * @return void Retourne une réponse JSON avec les réservations
     */
    public static function getUserReservations() {
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

            $db = Database::connect();

            // Récupération des réservations avec informations du voyage
            $stmt = $db->prepare("
                SELECT r.*, v.titre, v.date_depart, v.date_retour, v.image,
                       v.description, v.prix as prix_unitaire
                FROM reservation r
                JOIN voyage v ON r.voyage_id = v.id
                WHERE r.user_id = ?
                ORDER BY r.date_reserv DESC
            ");
            $stmt->execute([$user_id]);
            $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode($reservations);

        } catch (Exception $e) {
            error_log("Erreur dans reservationController::getUserReservations(): " . $e->getMessage());
            echo json_encode(['error' => 'Erreur interne du serveur']);
        }
    }

    /**
     * Crée une nouvelle réservation
     *
     * Permet à un client de réserver un voyage. Vérifie la disponibilité
     * des places et calcule automatiquement le prix total.
     *
     * @return void Retourne une réponse JSON avec le statut de création
     */
    public static function create() {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            // Validation des données d'entrée
            $validation = self::validateReservationData($data);
            if (!$validation['valid']) {
                echo json_encode(['error' => implode(', ', $validation['errors'])]);
                return;
            }

            $data = $validation['data'];
            $user_id = $data['user_id'];
            $voyage_id = $data['voyage_id'];
            $nb_personnes = $data['nb_personnes'];
            $mode_paiement = $data['mode_paiement'];

            $db = Database::connect();

            // Démarrage d'une transaction pour assurer la cohérence
            $db->beginTransaction();

            try {
                // Vérification que l'utilisateur existe
                $stmt = $db->prepare("SELECT id FROM user WHERE id = ?");
                $stmt->execute([$user_id]);
                if (!$stmt->fetch()) {
                    throw new Exception('Utilisateur introuvable');
                }

                // Récupération des informations du voyage
                $stmt = $db->prepare("SELECT prix, places_dispo, date_depart FROM voyage WHERE id = ?");
                $stmt->execute([$voyage_id]);
                $voyage = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$voyage) {
                    throw new Exception('Voyage introuvable');
                }

                // Vérification que le voyage n'est pas dans le passé
                if (strtotime($voyage['date_depart']) < time()) {
                    throw new Exception('Impossible de réserver un voyage passé');
                }

                // Calcul du prix total
                $prix_total = $voyage['prix'] * $nb_personnes;

                // Vérification de la disponibilité des places
                if ($voyage['places_dispo'] < $nb_personnes) {
                    throw new Exception("Pas assez de places disponibles. Places restantes: {$voyage['places_dispo']}");
                }

                // Vérification qu'une réservation similaire n'existe pas déjà
                $stmt = $db->prepare("
                    SELECT id FROM reservation
                    WHERE user_id = ? AND voyage_id = ? AND statut != 'annulee'
                ");
                $stmt->execute([$user_id, $voyage_id]);
                if ($stmt->fetch()) {
                    throw new Exception('Vous avez déjà une réservation active pour ce voyage');
                }

                // Insertion de la réservation
                $stmt = $db->prepare("
                    INSERT INTO reservation (user_id, voyage_id, nb_personnes, prix_total, mode_paiement, statut, date_reserv)
                    VALUES (?, ?, ?, ?, ?, 'en_attente', NOW())
                ");
                $stmt->execute([$user_id, $voyage_id, $nb_personnes, $prix_total, $mode_paiement]);
                $reservation_id = $db->lastInsertId();

                // Mise à jour des places disponibles
                $new_places = $voyage['places_dispo'] - $nb_personnes;
                $stmt = $db->prepare("UPDATE voyage SET places_dispo = ? WHERE id = ?");
                $stmt->execute([$new_places, $voyage_id]);

                // Validation de la transaction
                $db->commit();

                echo json_encode([
                    'success' => 'Réservation créée avec succès',
                    'id' => $reservation_id,
                    'prix_total' => $prix_total,
                    'places_restantes' => $new_places
                ]);

            } catch (Exception $e) {
                // Annulation de la transaction en cas d'erreur
                $db->rollBack();
                throw $e;
            }

        } catch (Exception $e) {
            error_log("Erreur dans reservationController::create(): " . $e->getMessage());
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * Annule une réservation (client)
     *
     * Permet à un client d'annuler sa propre réservation.
     * Les places sont automatiquement remises à disposition.
     *
     * @return void Retourne une réponse JSON avec le statut d'annulation
     */
    public static function cancel() {
        try {
            $id = $_GET['id'] ?? null;
            $user_id = $_GET['user_id'] ?? null;

            if (!$id || !$user_id) {
                echo json_encode(['error' => 'Paramètres id et user_id requis']);
                return;
            }

            // Validation des paramètres
            if (!is_numeric($id) || !is_numeric($user_id)) {
                echo json_encode(['error' => 'Les paramètres doivent être des nombres valides']);
                return;
            }

            $db = Database::connect();

            // Démarrage d'une transaction
            $db->beginTransaction();

            try {
                // Récupération et vérification de la propriété de la réservation
                $stmt = $db->prepare("
                    SELECT user_id, voyage_id, nb_personnes, statut
                    FROM reservation WHERE id = ?
                ");
                $stmt->execute([$id]);
                $reservation = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$reservation) {
                    throw new Exception('Réservation introuvable');
                }

                if ($reservation['user_id'] != $user_id) {
                    throw new Exception('Accès non autorisé - cette réservation ne vous appartient pas');
                }

                // Vérification que la réservation peut être annulée
                if ($reservation['statut'] === 'annulee') {
                    throw new Exception('Cette réservation est déjà annulée');
                }

                if ($reservation['statut'] === 'confirmee') {
                    throw new Exception('Impossible d\'annuler une réservation confirmée - contactez l\'administrateur');
                }

                // Annulation de la réservation
                $stmt = $db->prepare("UPDATE reservation SET statut = 'annulee' WHERE id = ?");
                $stmt->execute([$id]);

                // Remise à disposition des places
                $stmt = $db->prepare("UPDATE voyage SET places_dispo = places_dispo + ? WHERE id = ?");
                $stmt->execute([$reservation['nb_personnes'], $reservation['voyage_id']]);

                // Validation de la transaction
                $db->commit();

                echo json_encode(['success' => 'Réservation annulée avec succès']);

            } catch (Exception $e) {
                // Annulation de la transaction
                $db->rollBack();
                throw $e;
            }

        } catch (Exception $e) {
            error_log("Erreur dans reservationController::cancel(): " . $e->getMessage());
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    // ===========================================
    // MÉTHODES ADMIN
    // ===========================================

    /**
     * Récupère toutes les réservations pour l'administration
     *
     * Accessible uniquement aux administrateurs. Retourne toutes les réservations
     * avec les informations des clients et voyages associés.
     *
     * @return void Retourne une réponse JSON avec la liste complète des réservations
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

            // Récupération de toutes les réservations avec informations complètes
            $stmt = $db->query("
                SELECT r.*, u.nom, u.prenom, u.email, v.titre, v.date_depart, v.prix
                FROM reservation r
                JOIN user u ON r.user_id = u.id
                JOIN voyage v ON r.voyage_id = v.id
                ORDER BY r.date_reserv DESC
            ");
            $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode($reservations);

        } catch (Exception $e) {
            error_log("Erreur dans reservationController::adminGetAll(): " . $e->getMessage());
            echo json_encode(['error' => 'Erreur interne du serveur']);
        }
    }

    /**
     * Met à jour le statut d'une réservation (admin uniquement)
     *
     * Permet à un administrateur de changer le statut d'une réservation
     * (en_attente, confirmee, annulee, etc.).
     *
     * @return void Retourne une réponse JSON avec le statut de mise à jour
     */
    public static function adminUpdateStatus() {
        try {
            $user_id = $_GET['admin_id'] ?? null;

            if (!$user_id || !self::checkAdmin($user_id)) {
                http_response_code(403);
                echo json_encode(['error' => 'Accès non autorisé - droits administrateur requis']);
                return;
            }

            $data = json_decode(file_get_contents('php://input'), true);
            $id = $data['id'] ?? null;
            $statut = $data['statut'] ?? null;

            // Validation des données
            if (!$id || !$statut) {
                echo json_encode(['error' => 'Paramètres id et statut requis']);
                return;
            }

            // Validation de l'ID
            if (!is_numeric($id)) {
                echo json_encode(['error' => 'id doit être un nombre valide']);
                return;
            }

            // Validation du statut
            $statuts_valides = ['en_attente', 'confirmee', 'annulee', 'remboursee'];
            if (!in_array($statut, $statuts_valides)) {
                echo json_encode(['error' => 'Statut invalide. Valeurs acceptées : ' . implode(', ', $statuts_valides)]);
                return;
            }

            $db = Database::connect();

            // Vérification que la réservation existe
            $stmt = $db->prepare("SELECT id FROM reservation WHERE id = ?");
            $stmt->execute([$id]);
            if (!$stmt->fetch()) {
                echo json_encode(['error' => 'Réservation introuvable']);
                return;
            }

            // Mise à jour du statut
            $stmt = $db->prepare("UPDATE reservation SET statut = ? WHERE id = ?");
            $success = $stmt->execute([$statut, $id]);

            if ($success) {
                $message = match($statut) {
                    'confirmee' => 'Réservation confirmée',
                    'annulee' => 'Réservation annulée',
                    'remboursee' => 'Réservation remboursée',
                    'en_attente' => 'Réservation remise en attente',
                    default => 'Statut de la réservation mis à jour'
                };

                echo json_encode(['success' => $message]);
            } else {
                echo json_encode(['error' => 'Erreur lors de la mise à jour du statut']);
            }

        } catch (Exception $e) {
            error_log("Erreur dans reservationController::adminUpdateStatus(): " . $e->getMessage());
            echo json_encode(['error' => 'Erreur interne du serveur']);
        }
    }

    /**
     * Annule une réservation (admin uniquement)
     *
     * Permet à un administrateur d'annuler n'importe quelle réservation.
     * Les places sont automatiquement remises à disposition.
     *
     * @return void Retourne une réponse JSON avec le statut d'annulation
     */
    public static function adminCancel() {
        try {
            $user_id = $_GET['admin_id'] ?? null;

            if (!$user_id || !self::checkAdmin($user_id)) {
                http_response_code(403);
                echo json_encode(['error' => 'Accès non autorisé - droits administrateur requis']);
                return;
            }

            $id = $_GET['id'] ?? null;

            if (!$id) {
                echo json_encode(['error' => 'Paramètre id requis']);
                return;
            }

            // Validation de l'ID
            if (!is_numeric($id)) {
                echo json_encode(['error' => 'id doit être un nombre valide']);
                return;
            }

            $db = Database::connect();

            // Démarrage d'une transaction
            $db->beginTransaction();

            try {
                // Récupération des informations de la réservation
                $stmt = $db->prepare("SELECT voyage_id, nb_personnes, statut FROM reservation WHERE id = ?");
                $stmt->execute([$id]);
                $reservation = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$reservation) {
                    throw new Exception('Réservation introuvable');
                }

                // Annulation de la réservation
                $stmt = $db->prepare("UPDATE reservation SET statut = 'annulee' WHERE id = ?");
                $stmt->execute([$id]);

                // Remise à disposition des places (seulement si la réservation n'était pas déjà annulée)
                if ($reservation['statut'] !== 'annulee') {
                    $stmt = $db->prepare("UPDATE voyage SET places_dispo = places_dispo + ? WHERE id = ?");
                    $stmt->execute([$reservation['nb_personnes'], $reservation['voyage_id']]);
                }

                // Validation de la transaction
                $db->commit();

                echo json_encode(['success' => 'Réservation annulée par l\'administrateur']);

            } catch (Exception $e) {
                // Annulation de la transaction
                $db->rollBack();
                throw $e;
            }

        } catch (Exception $e) {
            error_log("Erreur dans reservationController::adminCancel(): " . $e->getMessage());
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
}