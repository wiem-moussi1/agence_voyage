<?php
$host = "localhost";
$dbname = "agence_voyage";
$user = "root";
$pass = "";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die(json_encode(["error" => $e->getMessage()]));
}

class Database {
    public static function connect() {
        global $pdo;
        return $pdo;
    }
}