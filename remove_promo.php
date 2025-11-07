<?php
session_start();

header('Content-Type: application/json');

if(isset($_SESSION['promo_code'])) {
    unset($_SESSION['promo_code']);
    unset($_SESSION['promo_id']);
    unset($_SESSION['discount_amount']);
}

echo json_encode(['success' => true]);
?>
