<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

if(!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Non authentifié']);
    exit();
}

if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['promo_code'])) {
    $promo_code = trim($_POST['promo_code']);
    $cart_total = floatval($_POST['cart_total']);
    
    try {
        // Vérifier le code promo
        $stmt = $pdo->prepare("
            SELECT * FROM promo_codes 
            WHERE code = ? 
            AND is_active = 1 
            AND (expires_at IS NULL OR expires_at > NOW())
            AND (max_uses IS NULL OR current_uses < max_uses)
            AND min_purchase <= ?
        ");
        $stmt->execute([$promo_code, $cart_total]);
        $promo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if(!$promo) {
            echo json_encode(['success' => false, 'message' => 'Code promo invalide ou expiré']);
            exit();
        }
        
        // Calculer la réduction
        $discount_amount = 0;
        if($promo['discount_type'] === 'percentage') {
            $discount_amount = ($cart_total * $promo['discount_value']) / 100;
        } else if($promo['discount_type'] === 'fixed') {
            $discount_amount = $promo['discount_value'];
        }
        
        // S'assurer que la réduction ne dépasse pas le total
        $discount_amount = min($discount_amount, $cart_total);
        $new_total = $cart_total - $discount_amount;
        
        // Stocker le code promo en session
        $_SESSION['promo_code'] = $promo['code'];
        $_SESSION['promo_id'] = $promo['id'];
        $_SESSION['discount_amount'] = $discount_amount;
        
        echo json_encode([
            'success' => true,
            'message' => 'Code promo appliqué avec succès !',
            'discount_amount' => number_format($discount_amount, 2),
            'new_total' => number_format($new_total, 2),
            'discount_type' => $promo['discount_type'],
            'discount_value' => $promo['discount_value']
        ]);
        
    } catch(PDOException $e) {
        error_log("Erreur validation promo : " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Erreur lors de la validation']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Requête invalide']);
}
?>
