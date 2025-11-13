<?php
session_start();
require_once 'config.php';

// Vérifier que l'utilisateur est admin
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter un produit</title>
    <link rel="stylesheet" href="style.css">
    <!-- Ajout Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        .image-preview {
            margin-top: 10px;
            max-width: 300px;
        }
        .image-preview img {
            max-width: 100%;
            height: auto;
            border: 2px solid #ddd;
            border-radius: 4px;
            padding: 5px;
        }
        .drop-zone {
            border: 2px dashed #4CAF50;
            border-radius: 8px;
            padding: 30px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            background-color: #f9f9f9;
        }
        .drop-zone:hover, .drop-zone.active {
            border-color: #45a049;
            background-color: #e8f5e9;
            transform: scale(1.02);
        }
        .drop-zone p {
            margin: 0;
            font-size: 16px;
            color: #333;
            font-weight: 500;
        }
        .drop-zone .icon {
            font-size: 48px;
            margin-bottom: 10px;
            color: #4CAF50;
        }
        .import-btn {
            display: inline-block;
            margin-top: 10px;
            padding: 10px 20px;
            background-color: #4CAF50;
            color: white;
            border-radius: 4px;
            font-weight: bold;
            text-transform: uppercase;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Ajouter un nouveau produit</h1>
        
        <?php if(isset($_SESSION['error'])): ?>
            <div class="alert alert-error"><?= htmlspecialchars($_SESSION['error']) ?></div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
        
        <form action="save_product.php" method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label for="name">Nom du produit *</label>
                <input type="text" id="name" name="name" required>
            </div>
            
            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description" rows="4"></textarea>
            </div>
            
            <div class="form-group">
                <label for="price">Prix ($) *</label>
                <input type="number" id="price" name="price" step="0.01" min="0" required>
            </div>
            
            <div class="form-group">
                <label for="stock">Stock *</label>
                <input type="number" id="stock" name="stock" min="0" required>
            </div>
            
            <div class="form-group">
                <label for="image">Image du produit *</label>
                <div class="drop-zone" id="dropZone">
                    <div class="icon"><i class="fa-solid fa-folder-open"></i></div>
                    <p>Glissez-déposez une image ici</p>
                    <span class="import-btn">ou cliquez pour IMPORTER</span>
                    <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/jpg,image/gif" required style="display: none;">
                </div>
                <small>Formats acceptés : JPG, JPEG, PNG, GIF (max 5 Mo)</small>
                <div class="image-preview" id="imagePreview"></div>
            </div>
            
            <button type="submit" class="btn">Ajouter le produit</button>
            <a href="admin.php" class="btn btn-secondary">Annuler</a>
        </form>
    </div>
    
    <script>
        const dropZone = document.getElementById('dropZone');
        const fileInput = document.getElementById('image');
        const imagePreview = document.getElementById('imagePreview');
        
        // Click sur la zone pour ouvrir le sélecteur
        dropZone.addEventListener('click', (e) => {
            // Ne pas déclencher si on clique sur l'input lui-même
            if(e.target !== fileInput) {
                fileInput.click();
            }
        });
        
        // Empêcher le comportement par défaut du drag & drop
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, (e) => {
                e.preventDefault();
                e.stopPropagation();
            });
        });
        
        // Ajouter une classe lors du survol
        ['dragenter', 'dragover'].forEach(eventName => {
            dropZone.addEventListener(eventName, () => dropZone.classList.add('active'));
        });
        
        ['dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, () => dropZone.classList.remove('active'));
        });
        
        // Gérer le drop
        dropZone.addEventListener('drop', (e) => {
            const files = e.dataTransfer.files;
            if(files.length) {
                fileInput.files = files;
                previewImage(files[0]);
            }
        });
        
        // Prévisualisation lors de la sélection
        fileInput.addEventListener('change', (e) => {
            if(e.target.files.length) {
                previewImage(e.target.files[0]);
            }
        });
        
        function previewImage(file) {
            const reader = new FileReader();
            reader.onload = (e) => {
                imagePreview.innerHTML = `<img src="${e.target.result}" alt="Aperçu">`;
                // Mettre à jour le texte mais garder l'input
                const pTag = dropZone.querySelector('p');
                if(pTag) {
                    pTag.textContent = 'Image importée : ' + file.name;
                }
                const icon = dropZone.querySelector('.icon');
                if(icon) {
                    icon.innerHTML = '<i class="fa-solid fa-circle-check"></i>';
                }
                const btn = dropZone.querySelector('.import-btn');
                if(btn) {
                    btn.textContent = 'Cliquez pour changer l\'image';
                }
            };
            reader.readAsDataURL(file);
        }
    </script>
</body>
</html>
