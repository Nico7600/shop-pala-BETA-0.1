<?php
session_start();
require_once 'config.php';

if(!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

try {
    $pdo->beginTransaction();
    
    // Récupérer les articles du panier
    $stmt = $pdo->prepare("
        SELECT c.*, i.price, i.stock, i.name, i.id as product_id
        FROM cart c
        INNER JOIN items i ON c.item_id = i.id
        WHERE c.user_id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if(empty($cart_items)) {
        throw new Exception("Panier vide");
    }
    
    // Calculer le total et vérifier le stock
    $total = 0;
    foreach($cart_items as $item) {
        if($item['quantity'] > $item['stock']) {
            throw new Exception("Stock insuffisant pour " . $item['name']);
        }
        $total += $item['price'] * $item['quantity'];
    }
    
    // Appliquer la réduction si un code promo est utilisé
    $discount_amount = isset($_SESSION['discount_amount']) ? $_SESSION['discount_amount'] : 0;
    $final_total = $total - $discount_amount;
    $promo_id = isset($_SESSION['promo_id']) ? $_SESSION['promo_id'] : null;
    
    // Créer la commande
    $stmt = $pdo->prepare("
        INSERT INTO orders (user_id, total, promo_code_id, discount_amount, status, payment_status, created_at) 
        VALUES (?, ?, ?, ?, 'pending', 'pending', NOW())
    ");
    $stmt->execute([$_SESSION['user_id'], $final_total, $promo_id, $discount_amount]);
    $order_id = $pdo->lastInsertId();
    
    // Insérer les détails de la commande et mettre à jour le stock
    foreach($cart_items as $item) {
        // Utiliser product_id au lieu de item_id
        $stmt = $pdo->prepare("
            INSERT INTO order_items (order_id, product_id, quantity, price) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$order_id, $item['product_id'], $item['quantity'], $item['price']]);
        
        $stmt = $pdo->prepare("UPDATE items SET stock = stock - ? WHERE id = ?");
        $stmt->execute([$item['quantity'], $item['product_id']]);
    }
    
    // Incrémenter current_uses du code promo si utilisé
    if($promo_id) {
        $stmt = $pdo->prepare("
            UPDATE promo_codes 
            SET current_uses = current_uses + 1 
            WHERE id = ?
        ");
        $stmt->execute([$promo_id]);
    }
    
    // Vider le panier
    $stmt = $pdo->prepare("DELETE FROM cart WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    
    // Nettoyer les informations de promo de la session
    unset($_SESSION['promo_code']);
    unset($_SESSION['promo_id']);
    unset($_SESSION['discount_amount']);
    
    $pdo->commit();
    
    $_SESSION['success'] = "Commande passée avec succès ! Numéro de commande : #" . $order_id;
    header('Location: orders.php');
    
} catch(Exception $e) {
    $pdo->rollBack();
    error_log("Erreur commande : " . $e->getMessage());
    $_SESSION['error'] = "Erreur lors du traitement de la commande : " . $e->getMessage();
    header('Location: cart.php');
}
exit();
?>
