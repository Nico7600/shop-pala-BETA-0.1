<?php
session_start();
require_once '../config.php';
require_once 'check_seller.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $product_name = trim($_POST['product_name']);
        $description = trim($_POST['description']);
        $price = (float)$_POST['price'];
        $category = $_POST['category'];
        $platform = $_POST['platform'];
        // Stock illimité si vide
        $stock = (isset($_POST['stock']) && $_POST['stock'] !== '') ? (int)$_POST['stock'] : null;
        
        // Gestion de l'upload d'image
        $image_url = 'placeholder.png';
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../images/';
            $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif'];
            $max_size = 5 * 1024 * 1024; // 5 Mo
            
            if (!in_array($_FILES['image']['type'], $allowed_types)) {
                throw new Exception("Format d'image non autorisé. Utilisez JPG, PNG ou GIF.");
            }
            
            if ($_FILES['image']['size'] > $max_size) {
                throw new Exception("L'image est trop volumineuse (max 5 Mo).");
            }
            
            $extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
            $image_url = uniqid('product_') . '.' . $extension;
            $upload_path = $upload_dir . $image_url;
            
            if (!move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
                throw new Exception("Erreur lors de l'upload de l'image.");
            }
        }
        
        // Récupérer l'ID de la catégorie depuis son nom
        $cat_stmt = $pdo->prepare("SELECT id FROM categories WHERE name = ? LIMIT 1");
        $cat_stmt->execute([$category]);
        $category_id = $cat_stmt->fetchColumn();
        
        if (!$category_id) {
            throw new Exception("Catégorie non trouvée");
        }
        
        // Définir une rareté par défaut
        $rarity = 'common';
        
        // Insérer directement dans la table items UNIQUEMENT
        $stmt = $pdo->prepare("
            INSERT INTO items (name, description, price, image, category_id, stock, rarity, platform, seller_id, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $product_name, 
            $description, 
            $price, 
            $image_url, 
            $category_id, 
            $stock, // null = illimité
            $rarity, 
            $platform,
            $_SESSION['user_id']
        ]);
        
        $product_id = $pdo->lastInsertId();
        
        // Log de la soumission
        $log_stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, details) VALUES (?, ?, ?)");
        $log_stmt->execute([
            $_SESSION['user_id'], 
            'add_product', 
            "Produit ajouté ID:{$product_id} - '{$product_name}' - Prix: {$price}€ - Catégorie: {$category} - Serveur: {$platform}"
        ]);
        
        $success = "Produit ajouté avec succès au catalogue !";
    } catch(Exception $e) {
        $error = "Erreur : " . $e->getMessage();
        
        // Log de l'erreur
        try {
            $log_stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, details) VALUES (?, ?, ?)");
            $log_stmt->execute([
                $_SESSION['user_id'], 
                'product_add_error', 
                "Erreur lors de l'ajout: " . $e->getMessage()
            ]);
        } catch(PDOException $log_error) {
            error_log("Erreur log: " . $log_error->getMessage());
        }
    }
}

// Récupérer les produits récemment ajoutés par ce vendeur (via activity_logs)
try {
    $stmt = $pdo->prepare("
        SELECT i.*, c.name as category_name 
        FROM items i 
        LEFT JOIN categories c ON i.category_id = c.id 
        WHERE i.id IN (
            SELECT CAST(REGEXP_REPLACE(SUBSTRING_INDEX(details, '-', 1), '[^0-9]', '') AS UNSIGNED) 
            FROM activity_logs 
            WHERE user_id = ? AND action = 'add_product' AND details LIKE 'Produit ajouté ID:%'
        )
        ORDER BY i.created_at DESC 
        LIMIT 20
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $my_products = $stmt->fetchAll();
} catch(PDOException $e) {
    // Si la regex ne fonctionne pas, utiliser une approche plus simple
    try {
        $stmt = $pdo->prepare("
            SELECT i.*, c.name as category_name 
            FROM items i 
            LEFT JOIN categories c ON i.category_id = c.id 
            ORDER BY i.created_at DESC 
            LIMIT 20
        ");
        $stmt->execute();
        $my_products = $stmt->fetchAll();
    } catch(PDOException $e2) {
        $my_products = [];
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter un Produit - Vendeur</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        .drop-zone {
            border: 2px dashed #10b981;
            transition: all 0.3s;
            cursor: pointer;
        }
        .drop-zone:hover, .drop-zone.active {
            border-color: #059669;
            background-color: rgba(16, 185, 129, 0.1);
            transform: scale(1.02);
        }
        .image-preview img {
            max-height: 200px;
            object-fit: contain;
        }
    </style>
</head>
<body class="bg-gray-900 text-gray-100">

    <div class="flex flex-col md:flex-row min-h-screen">
        <?php include 'sidebar.php'; ?>

        <main class="flex-1 p-2 sm:p-8 w-full">
            <div class="container mx-auto max-w-4xl">
                <div class="mb-4 sm:mb-8">
                    <h1 class="text-2xl sm:text-4xl font-bold mb-2">
                        <i class="fas fa-plus-circle text-green-500 mr-2 sm:mr-3"></i>
                        Ajouter un Produit
                    </h1>
                    <p class="text-gray-400 text-xs sm:text-base">Ajoutez un nouveau produit au catalogue</p>
                </div>

                <?php if(isset($success)): ?>
                <div class="bg-green-500/20 border border-green-500 text-green-400 px-2 sm:px-6 py-2 sm:py-4 rounded-lg mb-4 sm:mb-6 text-xs sm:text-base">
                    <i class="fas fa-check-circle mr-2"></i><?php echo htmlspecialchars($success); ?>
                </div>
                <?php endif; ?>

                <?php if(isset($error)): ?>
                <div class="bg-red-500/20 border border-red-500 text-red-400 px-2 sm:px-6 py-2 sm:py-4 rounded-lg mb-4 sm:mb-6 text-xs sm:text-base">
                    <i class="fas fa-exclamation-circle mr-2"></i><?php echo htmlspecialchars($error); ?>
                </div>
                <?php endif; ?>

                <!-- Formulaire -->
                <div class="bg-gray-800 rounded-xl p-2 sm:p-6 mb-4 sm:mb-8">
                    <h2 class="text-lg sm:text-2xl font-bold mb-4 sm:mb-6 flex items-center gap-2">
                        <i class="fas fa-file-alt text-green-500"></i>
                        Nouveau Produit
                    </h2>
                    
                    <form method="POST" enctype="multipart/form-data" class="space-y-4 sm:space-y-6 text-xs sm:text-base">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 sm:gap-6">
                            <div>
                                <label class="block text-sm font-medium mb-2">
                                    <i class="fas fa-tag mr-1"></i>Nom du produit *
                                </label>
                                <input type="text" name="product_name" required 
                                       class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-3 focus:border-green-500 focus:outline-none"
                                       placeholder="Ex: Épée Enchantée Diamant">
                            </div>

                            <div>
                                <label class="block text-sm font-medium mb-2">
                                    <i class="fas fa-euro-sign mr-1"></i>Prix (€) *
                                </label>
                                <input type="number" name="price" step="0.01" min="0" required 
                                       class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-3 focus:border-green-500 focus:outline-none"
                                       placeholder="9.99">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 sm:gap-6">
                            <div>
                                <label class="block text-sm font-medium mb-2">
                                    <i class="fas fa-folder mr-1"></i>Catégorie *
                                </label>
                                <select name="category" required 
                                        class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-3 focus:border-green-500 focus:outline-none">
                                    <option value="">-- Sélectionner --</option>
                                    <?php
                                    $categories = $pdo->query("SELECT name FROM categories ORDER BY name")->fetchAll();
                                    foreach($categories as $cat):
                                    ?>
                                    <option value="<?php echo htmlspecialchars($cat['name']); ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-medium mb-2">
                                    <i class="fas fa-box mr-1"></i>Stock *
                                </label>
                                <input type="number" name="stock" min="1" value="100" 
                                       class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-3 focus:border-green-500 focus:outline-none"
                                       placeholder="100">
                                <p class="text-xs text-gray-500 mt-1">(Si vide &rarr; pas de limite de stock)</p>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-2">
                                <i class="fas fa-align-left mr-1"></i>Description *
                            </label>
                            <textarea name="description" rows="4" required 
                                      class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-3 focus:border-green-500 focus:outline-none"
                                      placeholder="Décrivez le produit en détail..."></textarea>
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-2">
                                <i class="fas fa-image mr-1"></i>Image du produit *
                            </label>
                            <div class="drop-zone bg-gray-700 border-gray-600 rounded-lg p-8 text-center" id="dropZone">
                                <div class="icon text-5xl mb-3">📁</div>
                                <p class="text-gray-300 mb-2">Glissez-déposez ici l'image du produit</p>
                                <span class="inline-block mt-2 px-4 py-2 bg-green-600 hover:bg-green-700 rounded-lg font-bold transition-all">
                                    ou cliquez pour IMPORTER
                                </span>
                                <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/jpg,image/gif" required style="display: none;">
                            </div>
                            <p class="text-xs text-gray-500 mt-2">Formats acceptés : JPG, JPEG, PNG, GIF (max 5 Mo)</p>
                            <div class="image-preview mt-4 text-center" id="imagePreview"></div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium mb-2">
                                <i class="fas fa-gamepad mr-1"></i>Serveur *
                            </label>
                            <select name="platform" required 
                                    class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-3 focus:border-green-500 focus:outline-none">
                                <option value="">-- Sélectionner le serveur --</option>
                                <option value="java">Java</option>
                                <option value="bedrock">Bedrock</option>
                            </select>
                        </div>

                        <button type="submit" class="w-full bg-gradient-to-r from-green-600 to-teal-600 hover:from-green-700 hover:to-teal-700 px-4 sm:px-6 py-3 sm:py-4 rounded-lg font-bold text-base sm:text-lg transition-all transform hover:scale-105 shadow-lg">
                            <i class="fas fa-plus mr-2"></i>Ajouter le produit
                        </button>
                    </form>
                </div>

                <!-- Mes produits récents -->
                <?php if(!empty($my_products)): ?>
                <div class="bg-gray-800 rounded-xl p-2 sm:p-6">
                    <h2 class="text-lg sm:text-2xl font-bold mb-4 sm:mb-6 flex items-center gap-2">
                        <i class="fas fa-history text-green-500"></i>
                        Mes Produits Récents
                    </h2>
                    
                    <div class="space-y-2 sm:space-y-4">
                        <?php foreach($my_products as $product): ?>
                        <div class="bg-gray-700 rounded-lg p-2 sm:p-4 border-l-4 border-green-500">
                            <div class="flex flex-col sm:flex-row justify-between items-start mb-1 sm:mb-2 gap-2">
                                <div>
                                    <h3 class="text-base sm:text-lg font-bold"><?php echo htmlspecialchars($product['name']); ?></h3>
                                    <p class="text-xs sm:text-sm text-gray-400"><?php echo htmlspecialchars($product['category_name']); ?> • <?php echo number_format($product['price'], 2); ?>€</p>
                                </div>
                                <span class="px-2 sm:px-3 py-1 bg-green-500/20 text-green-400 rounded-full text-xs font-bold">
                                    <i class="fas fa-box mr-1"></i>
                                    <?php echo ($product['stock'] === null) ? 'illimité' : $product['stock']; ?> en stock
                                </span>
                            </div>
                            <p class="text-xs sm:text-sm text-gray-300 mb-1 sm:mb-2"><?php echo htmlspecialchars(substr($product['description'], 0, 150)); ?>...</p>
                            <p class="text-xs text-gray-500">Ajouté le <?php echo date('d/m/Y à H:i', strtotime($product['created_at'])); ?></p>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        const dropZone = document.getElementById('dropZone');
        const fileInput = document.getElementById('image');
        const imagePreview = document.getElementById('imagePreview');
        
        // Click sur la zone pour ouvrir le sélecteur
        dropZone.addEventListener('click', (e) => {
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
                imagePreview.innerHTML = `<img src="${e.target.result}" alt="Aperçu" class="rounded-lg border-2 border-green-500 inline-block">`;
                const pTag = dropZone.querySelector('p');
                if(pTag) {
                    pTag.textContent = 'Image importée : ' + file.name;
                    pTag.classList.add('text-green-400');
                }
                const icon = dropZone.querySelector('.icon');
                if(icon) {
                    icon.textContent = '✅';
                }
                const btn = dropZone.querySelector('span');
                if(btn) {
                    btn.textContent = 'Cliquez pour changer l\'image';
                }
            };
            reader.readAsDataURL(file);
        }
    </script>
</body>
</html>
