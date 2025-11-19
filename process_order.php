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
    
    // Récupérer les montants envoyés par le formulaire
    $discount_amount = isset($_POST['discount_amount']) ? floatval($_POST['discount_amount']) : 0;
    $permission_discount = isset($_POST['permission_discount']) ? floatval($_POST['permission_discount']) : 0;
    $final_total = isset($_POST['final_total']) ? floatval($_POST['final_total']) : $total;
    $promo_id = isset($_SESSION['promo_id']) ? $_SESSION['promo_id'] : null;
    
    // Pour chaque article du panier, créer une commande séparée
    $order_ids = [];
    foreach($cart_items as $item) {
        // Calculer le total pour cet article
        $item_total = $item['price'] * $item['quantity'];
        // Calculer la réduction code promo sur la valeur de base
        $promo_discount = 0;
        if ($total > 0 && $discount_amount > 0) {
            $promo_discount = round($discount_amount * ($item_total / $total), 2);
        }
        // Calculer la réduction permission sur la valeur de base
        $permission_discount_item = 0;
        if ($total > 0 && $permission_discount > 0) {
            $permission_discount_item = round($permission_discount * ($item_total / $total), 2);
        }
        // Additionner les deux réductions
        $item_discount = $promo_discount + $permission_discount_item;
        $item_final_total = $item_total - $item_discount;

        // Créer la commande pour cet article
        $stmt = $pdo->prepare("
            INSERT INTO orders (user_id, total, promo_code_id, discount_amount, status, payment_status, created_at, item_name)
            VALUES (?, ?, ?, ?, 'pending', 'pending', NOW(), ?)
        ");
        $stmt->execute([
            $_SESSION['user_id'],
            $item_final_total, // prix payé après réduction
            $promo_id,
            $item_discount,
            $item['name']
        ]);
        $order_id = $pdo->lastInsertId();
        $order_ids[] = $order_id;
    
        // Insérer le détail de la commande
        $stmt = $pdo->prepare("
            INSERT INTO order_items (order_id, product_id, quantity, price)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$order_id, $item['product_id'], $item['quantity'], $item['price']]);
    
        // Mettre à jour le stock
        $stmt = $pdo->prepare("UPDATE items SET stock = stock - ? WHERE id = ?");
        $stmt->execute([$item['quantity'], $item['product_id']]);
    
        // Incrémenter current_uses du code promo si utilisé
        if($promo_id) {
            $stmt = $pdo->prepare("
                UPDATE promo_codes
                SET current_uses = current_uses + 1
                WHERE id = ?
            ");
            $stmt->execute([$promo_id]);
        }
    }
    
    // Vider le panier
    $stmt = $pdo->prepare("DELETE FROM cart WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    
    // Nettoyer les informations de promo de la session
    unset($_SESSION['promo_code']);
    unset($_SESSION['promo_id']);
    unset($_SESSION['discount_amount']);
    
    $pdo->commit();

    $_SESSION['success'] = "Commande(s) passée(s) avec succès ! Numéros : #" . implode(', #', $order_ids);
    header('Location: orders.php');
    
} catch(Exception $e) {
    $pdo->rollBack();
    error_log("Erreur commande : " . $e->getMessage());
    $_SESSION['error'] = "Erreur lors du traitement de la commande : " . $e->getMessage();
    header('Location: cart.php');
}
exit();
?>
