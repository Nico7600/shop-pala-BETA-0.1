<?php
session_start();
require_once 'config.php';

if(!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cart_id'])) {
    try {
        // Supprimer l'article du panier en vérifiant que c'est bien l'utilisateur connecté
        $stmt = $pdo->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?");
        $stmt->execute([$_POST['cart_id'], $_SESSION['user_id']]);
        
        if($stmt->rowCount() > 0) {
            $_SESSION['success'] = "Article retiré du panier avec succès";
        } else {
            $_SESSION['error'] = "Impossible de retirer cet article";
        }
    } catch(PDOException $e) {
        error_log("Erreur suppression panier : " . $e->getMessage());
        $_SESSION['error'] = "Une erreur est survenue";
    }
}

header('Location: cart.php');
exit();
?>
