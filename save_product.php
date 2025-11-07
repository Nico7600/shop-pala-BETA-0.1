<?php
session_start();
require_once 'config.php';

// Vérifier que l'utilisateur est admin
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit;
}

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $price = floatval($_POST['price']);
    $stock = intval($_POST['stock']);
    
    // Gestion de l'upload de l'image
    if(isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['image']['name'];
        $filesize = $_FILES['image']['size'];
        
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if(!in_array($ext, $allowed)) {
            $_SESSION['error'] = 'Format de fichier non autorisé';
            header('Location: add_product.php');
            exit;
        }
        
        if($filesize > 5 * 1024 * 1024) { // 5 Mo max
            $_SESSION['error'] = 'Fichier trop volumineux (max 5 Mo)';
            header('Location: add_product.php');
            exit;
        }
        
        // Créer un nom de fichier unique
        $new_filename = uniqid() . '.' . $ext;
        $upload_path = 'images/' . $new_filename;
        
        // Créer le dossier images s'il n'existe pas
        if(!is_dir('images')) {
            mkdir('images', 0755, true);
        }
        
        // Déplacer le fichier
        if(move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
            try {
                // Insérer seulement le produit dans la table items avec le nom de l'image
                $stmt = $pdo->prepare("INSERT INTO items (name, description, price, stock, image) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$name, $description, $price, $stock, $new_filename]);
                
                $_SESSION['success'] = 'Produit ajouté avec succès';
                header('Location: admin.php');
                exit;
            } catch(PDOException $e) {
                unlink($upload_path); // Supprimer l'image en cas d'erreur
                $_SESSION['error'] = 'Erreur lors de l\'ajout du produit: ' . $e->getMessage();
                header('Location: add_product.php');
                exit;
            }
        } else {
            $_SESSION['error'] = 'Erreur lors de l\'upload de l\'image';
            header('Location: add_product.php');
            exit;
        }
    } else {
        $_SESSION['error'] = 'Veuillez sélectionner une image';
        header('Location: add_product.php');
        exit;
    }
}
?>
