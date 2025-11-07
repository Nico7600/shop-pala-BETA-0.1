<?php
session_start();
require_once 'config.php';

// Logger la déconnexion avant de détruire la session
if (isset($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, details, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([
            $_SESSION['user_id'],
            'logout',
            "Déconnexion de " . $_SESSION['username'] . " (IP: " . $_SERVER['REMOTE_ADDR'] . ")"
        ]);
    } catch(PDOException $e) {
        // Ignorer l'erreur de log
    }
}

session_destroy();
header('Location: login.php');
exit;
?>
