<?php
require_once '../config.php';
require_once '../includes/notification_helper.php';

header('Content-Type: application/json');

if(!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Non connecté']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$item_id = $data['item_id'] ?? null;

if(!$item_id) {
    echo json_encode(['success' => false, 'message' => 'Item invalide']);
    exit;
}

try {
    // Récupérer le nom de l'item
    $item_stmt = $pdo->prepare("SELECT name, stock FROM items WHERE id = ?");
    $item_stmt->execute([$item_id]);
    $item = $item_stmt->fetch();
    
    if(!$item) {
        echo json_encode(['success' => false, 'message' => 'Item introuvable']);
        exit;
    }
    
    $stmt = $pdo->prepare("INSERT INTO cart (user_id, item_id, quantity) 
                          VALUES (?, ?, 1) 
                          ON DUPLICATE KEY UPDATE quantity = quantity + 1");
    $stmt->execute([$_SESSION['user_id'], $item_id]);
    
    // Créer une notification
    notifyItemAdded($pdo, $_SESSION['user_id'], $item['name']);
    
    // Notification de stock faible
    if($item['stock'] <= 5) {
        notifyLowStock($pdo, $_SESSION['user_id'], $item['name'], $item['stock']);
    }
    
    echo json_encode(['success' => true]);
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur serveur']);
}
?>
