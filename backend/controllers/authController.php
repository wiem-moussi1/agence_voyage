<?php
require_once __DIR__ . '/../config/db.php';

class authController {

    public static function register() {
        global $pdo;
        $data = json_decode(file_get_contents("php://input"), true);

        $nom            = $data['nom']            ?? '';
        $prenom         = $data['prenom']         ?? '';
        $email          = $data['email']          ?? '';
        $password       = $data['password']       ?? '';
        $telephone      = $data['telephone']      ?? null;
        $date_naissance = $data['date_naissance'] ?? null;

        if (!$nom || !$prenom || !$email || !$password) {
            echo json_encode(["error" => "Champs manquants"]);
            return;
        }

        // Validation basique du format date (YYYY-MM-DD)
        if ($date_naissance && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_naissance)) {
            echo json_encode(["error" => "Format de date invalide"]);
            return;
        }

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        try {
            $stmt = $pdo->prepare(
                "INSERT INTO user (nom, prenom, email, password, telephone, date_naissance, role)
                 VALUES (?, ?, ?, ?, ?, ?, 'client')"
            );
            $stmt->execute([$nom, $prenom, $email, $hashedPassword, $telephone, $date_naissance]);
            echo json_encode(["success" => "Compte créé avec succès"]);
        } catch (Exception $e) {
            echo json_encode(["error" => "Email déjà utilisé"]);
        }
    }

    public static function logout() {
        echo json_encode(["success" => "Déconnexion réussie"]);
    }

    public static function login() {
        global $pdo;
        $data = json_decode(file_get_contents("php://input"), true);

        $email    = $data['email']    ?? '';
        $password = $data['password'] ?? '';

        if (!$email || !$password) {
            echo json_encode(["error" => "Champs manquants"]);
            return;
        }

        $stmt = $pdo->prepare("SELECT * FROM user WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password, $user['password'])) {
            echo json_encode(["error" => "Email ou mot de passe incorrect"]);
            return;
        }

        echo json_encode([
            "success" => "Connexion réussie",
            "user" => [
                "id"             => $user['id'],
                "nom"            => $user['nom'],
                "prenom"         => $user['prenom'],
                "email"          => $user['email'],
                "telephone"      => $user['telephone']      ?? null,
                "date_naissance" => $user['date_naissance'] ?? null,
                "role"           => $user['role']
            ]
        ]);
    }
}