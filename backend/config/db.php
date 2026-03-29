<?php
$host = "localhost";
$dbname = "agence_voyage";
$user = "root";
$pass = "";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Connexion réussie 🎉";
} catch (Exception $e) {
    die(json_encode(["error" => $e->getMessage()]));
}