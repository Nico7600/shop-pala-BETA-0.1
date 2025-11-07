<?php
session_start();

// Configuration de la base de données
$host = 'localhost';
$dbname = 'votre_base_de_donnees';
$username = 'votre_utilisateur';
$password = 'votre_mot_de_passe';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erreur de connexion à la base de données : " . $e->getMessage());
}

$current_file = basename($_SERVER['PHP_SELF']);
$allowed = ['login.php', 'register.php'];

if (!isset($_SESSION['user_id']) && !in_array($current_file, $allowed)) {
    header('Location: login.php');
    exit;
}
?>
