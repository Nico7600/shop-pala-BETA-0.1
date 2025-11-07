<?php
session_start();
require_once '../config.php';
require_once 'check_seller.php';

if($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: orders.php');
    exit();
}

$order_id = $_POST['order_id'] ?? null;
$new_status = $_POST['status'] ?? null;

if(!$order_id || !$new_status) {
    $_SESSION['error'] = "Données invalides";
    header('Location: orders.php');
    exit();
}

try {
    $pdo->beginTransaction();
    
    // Récupérer la commande actuelle
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch();
    
    if(!$order) {
        throw new Exception("Commande introuvable");
    }
    
    // Si on annule une commande, restaurer le stock
    if($new_status === 'cancelled' && $order['status'] !== 'cancelled') {
        $stmt = $pdo->prepare("
            SELECT item_id, quantity 
            FROM order_items 
            WHERE order_id = ?
        ");
        $stmt->execute([$order_id]);
        $items = $stmt->fetchAll();
        
        $stmt_restore = $pdo->prepare("UPDATE items SET stock = stock + ? WHERE id = ?");
        foreach($items as $item) {
            $stmt_restore->execute([$item['quantity'], $item['item_id']]);
        }
    }
    
    // Mettre à jour le statut
    $stmt = $pdo->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$new_status, $order_id]);
    
    $pdo->commit();
    
    $_SESSION['success'] = "Statut de la commande mis à jour";
    
} catch(Exception $e) {
    $pdo->rollBack();
    $_SESSION['error'] = "Erreur : " . $e->getMessage();
}

header('Location: orders.php');
exit();
?>
