<?php
session_start();
require_once 'config.php';

// Vérifier que l'utilisateur est connecté
if (!isset($_SESSION['user_id'])) {
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        // Requête AJAX
        echo json_encode(['success' => false, 'message' => 'Veuillez vous connecter']);
    } else {
        // Requête normale
        $_SESSION['error'] = 'Veuillez vous connecter pour ajouter des produits au panier';
        header('Location: login.php');
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['item_id'])) {
    $item_id = (int)$_POST['item_id'];
    $quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
    
    if($quantity < 1) {
        $quantity = 1;
    }
    
    try {
        // Vérifier si l'article existe déjà dans le panier
        $stmt = $pdo->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND item_id = ?");
        $stmt->execute([$_SESSION['user_id'], $item_id]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if($existing) {
            // Mettre à jour la quantité
            $new_quantity = $existing['quantity'] + $quantity;
            $stmt = $pdo->prepare("UPDATE cart SET quantity = ? WHERE id = ?");
            $stmt->execute([$new_quantity, $existing['id']]);
            $_SESSION['success'] = "Quantité mise à jour dans le panier";
        } else {
            // Ajouter un nouvel article
            $stmt = $pdo->prepare("INSERT INTO cart (user_id, item_id, quantity) VALUES (?, ?, ?)");
            $stmt->execute([$_SESSION['user_id'], $item_id, $quantity]);
            $_SESSION['success'] = "Article ajouté au panier";
        }
    } catch(PDOException $e) {
        error_log("Erreur ajout panier : " . $e->getMessage());
        $_SESSION['error'] = "Une erreur est survenue";
    }
}

header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? 'index.php'));
exit();
?>
