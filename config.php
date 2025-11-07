<?php
define('DB_HOST', 'nicolavpaladium.mysql.db');
define('DB_USER', 'nicolavpaladium');
define('DB_PASS', 'Panpan220405');
define('DB_NAME', 'nicolavpaladium');

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Erreur de connexion à la base de données : " . $e->getMessage());
}

session_start();

// Mettre à jour la dernière activité de l'utilisateur connecté
if(isset($_SESSION['user_id'])) {
    try {
        // Vérifier si la colonne last_activity existe
        $check_column = $pdo->query("SHOW COLUMNS FROM users LIKE 'last_activity'");
        if ($check_column->rowCount() > 0) {
            $update_activity = $pdo->prepare("UPDATE users SET last_activity = NOW() WHERE id = ?");
            $update_activity->execute([$_SESSION['user_id']]);
        }
    } catch(PDOException $e) {
        // Ignorer l'erreur silencieusement
    }
}

if(isset($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->prepare("UPDATE users SET last_activity = NOW() WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
    } catch(PDOException $e) {
        // Ignorer les erreurs pour ne pas bloquer l'application
        error_log("Erreur mise à jour last_activity: " . $e->getMessage());
    }
}

// Inclure la mise à jour de l'activité
require_once __DIR__ . '/includes/update_activity.php';
?>
