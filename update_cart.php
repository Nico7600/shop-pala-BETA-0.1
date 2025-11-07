<?php
session_start();
require_once 'config.php';

if(!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['product_id']) && isset($_POST['action'])) {
    $product_id = intval($_POST['product_id']);
    $action = $_POST['action'];
    
    if(isset($_SESSION['cart'][$product_id])) {
        // Récupérer le stock du produit
        try {
            $stmt = $pdo->prepare("SELECT stock FROM products WHERE id = ?");
            $stmt->execute([$product_id]);
            $product = $stmt->fetch();
            
            if($action === 'increase') {
                if($_SESSION['cart'][$product_id] < $product['stock']) {
                    $_SESSION['cart'][$product_id]++;
                    $_SESSION['success'] = "Quantité augmentée";
                } else {
                    $_SESSION['error'] = "Stock maximum atteint";
                }
            } elseif($action === 'decrease') {
                if($_SESSION['cart'][$product_id] > 1) {
                    $_SESSION['cart'][$product_id]--;
                    $_SESSION['success'] = "Quantité diminuée";
                } else {
                    $_SESSION['error'] = "Quantité minimum atteinte";
                }
            }
        } catch(PDOException $e) {
            error_log("Erreur update cart : " . $e->getMessage());
        }
    }
}

header('Location: cart.php');
exit();
