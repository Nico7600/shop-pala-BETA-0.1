<?php
if(isset($_SESSION['user_id']) && isset($pdo)) {
    try {
        $stmt = $pdo->prepare("UPDATE users SET last_activity = NOW() WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
    } catch(PDOException $e) {
        error_log("Erreur mise à jour last_activity: " . $e->getMessage());
    }
}
?>
