<?php
if (!isset($_SESSION)) {
    session_start();
}

// Vérifier si l'utilisateur est connecté
if(!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Vérifier si l'utilisateur n'est pas un simple client
if($_SESSION['role'] === 'client') {
    header('Location: ../index.php');
    exit;
}

// L'utilisateur a accès à l'espace vendeur
?>
