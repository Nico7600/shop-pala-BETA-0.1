<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Non authentifié.']);
    exit;
}

$code = isset($_POST['coupon_code']) ? trim($_POST['coupon_code']) : '';
if ($code === '') {
    echo json_encode(['success' => false, 'message' => 'Code promo manquant.']);
    exit;
}

// Récupère le total du panier
$total = 0;
try {
    $stmt = $pdo->prepare("
        SELECT SUM(i.price * c.quantity) as total
        FROM cart c
        INNER JOIN items i ON c.item_id = i.id
        WHERE c.user_id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $total = floatval($row['total'] ?? 0);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur panier.']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM promo_codes WHERE code = ? LIMIT 1");
    $stmt->execute([$code]);
    $promo = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$promo) {
        echo json_encode(['success' => false, 'message' => 'Code promo introuvable.']);
        exit;
    }
    if (!$promo['is_active']) {
        echo json_encode(['success' => false, 'message' => 'Ce code promo est désactivé.']);
        exit;
    }
    if ($promo['expires_at'] && strtotime($promo['expires_at']) < time()) {
        echo json_encode(['success' => false, 'message' => 'Ce code promo est expiré.']);
        exit;
    }
    if ($promo['max_uses'] !== null && $promo['current_uses'] >= $promo['max_uses']) {
        echo json_encode(['success' => false, 'message' => 'Ce code promo a atteint sa limite d\'utilisation.']);
        exit;
    }
    if ($promo['min_purchase'] !== null && $total < floatval($promo['min_purchase'])) {
        echo json_encode(['success' => false, 'message' => 'Montant minimum non atteint pour ce code promo.']);
        exit;
    }

    // Calcul de la réduction
    $discount_amount = 0;
    if ($promo['discount_type'] === 'percentage') {
        $discount_amount = round($total * (floatval($promo['discount_value']) / 100), 2);
    } else { // fixed
        $discount_amount = round(floatval($promo['discount_value']), 2);
        if ($discount_amount > $total) $discount_amount = $total;
    }

    // Met à jour le nombre d'utilisations
    $stmt = $pdo->prepare("UPDATE promo_codes SET current_uses = current_uses + 1 WHERE id = ?");
    $stmt->execute([$promo['id']]);

    // Stocke la réduction en session pour le panier
    $_SESSION['discount_amount'] = $discount_amount;

    echo json_encode([
        'success' => true,
        'discount_amount' => $discount_amount,
        'message' => 'Code promo appliqué !'
    ]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur serveur.']);
}
