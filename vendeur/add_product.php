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
        // Stock illimité si vide ou 0
        $stock = (isset($_POST['stock']) && $_POST['stock'] !== '' && (int)$_POST['stock'] > 0) ? (int)$_POST['stock'] : null;
        
        // Gestion de l'image : uniquement choix depuis la base
        $image_url = 'placeholder.png';
        if (!empty($_POST['existing_image'])) {
            $image_url = $_POST['existing_image'];
        }
        
        // Récupérer l'ID de la catégorie depuis son nom
        $cat_stmt = $pdo->prepare("SELECT id FROM categories WHERE name = ? LIMIT 1");
        $cat_stmt->execute([$category]);
        $category_id = $cat_stmt->fetchColumn();
        
        if (!$category_id) {
            throw new Exception("Catégorie non trouvée");
        }
        
        // Récupérer la rareté
        $rarity = isset($_POST['rarity']) ? $_POST['rarity'] : null;
        
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
                            <!-- Supprimer la drop-zone et l'input file -->
                            <!-- Choix depuis la base d'images uniquement -->
                            <div class="image-preview mt-4 text-center" id="imagePreview"></div>
                            <div class="mt-4 text-center">
                                <button type="button" id="openImageBase" class="px-4 py-2 bg-teal-600 hover:bg-teal-700 rounded-lg font-bold text-white transition-all">
                                    <i class="fas fa-database mr-1"></i>Choisir depuis la base d'images
                                </button>
                                <input type="hidden" name="existing_image" id="existingImageInput" value="">
                            </div>
                            <p class="text-xs text-gray-500 mt-2">Formats acceptés : JPG, JPEG, PNG, GIF (max 5 Mo)</p>
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

                        <div>
                            <label class="block text-xs sm:text-sm font-bold mb-1 sm:mb-2">Rareté</label>
                            <select name="rarity" class="w-full bg-gray-700 border border-gray-600 rounded-lg px-2 py-2 focus:outline-none">
                                <option value="">-- Sélectionner --</option>
                                <option value="common">⚪ Commun</option>
                                <option value="rare">🔵 Rare</option>
                                <option value="epic">🟣 Épique</option>
                                <option value="legendary">🟠 Légendaire</option>
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
                    <!-- Barre de recherche -->
                    <div class="mb-4 flex items-center gap-2">
                        <input type="text" id="productSearch" placeholder="Rechercher un produit..." class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 focus:border-green-500 focus:outline-none text-gray-100" />
                        <span class="text-green-500 text-xl"><i class="fas fa-search"></i></span>
                    </div>
                    <div class="space-y-2 sm:space-y-4" id="productsList">
                        <?php foreach($my_products as $product): ?>
                        <div class="bg-gray-700 rounded-lg p-2 sm:p-4 border-l-4 border-green-500 product-item">
                            <div class="flex flex-col sm:flex-row justify-between items-start mb-1 sm:mb-2 gap-2">
                                <div class="flex items-center gap-3">
                                    <!-- Aperçu image -->
                                    <?php if(!empty($product['image'])): ?>
                                        <img src="../images/<?php echo htmlspecialchars($product['image']); ?>" alt="Aperçu" class="h-12 w-12 object-contain rounded border border-green-500 mr-2" />
                                    <?php endif; ?>
                                    <div>
                                        <h3 class="text-base sm:text-lg font-bold"><?php echo htmlspecialchars($product['name']); ?></h3>
                                        <p class="text-xs sm:text-sm text-gray-400"><?php echo htmlspecialchars($product['category_name']); ?> • <?php echo number_format($product['price'], 2); ?>€</p>
                                    </div>
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

    <!-- Popup base d'images -->
    <div id="imageBaseModal" class="fixed inset-0 bg-black bg-opacity-60 flex items-center justify-center z-50 hidden">
        <div class="bg-gray-800 rounded-xl p-6 max-w-lg w-full relative">
            <button type="button" id="closeImageBase" class="absolute top-2 right-2 text-gray-400 hover:text-red-400 text-2xl">
                <i class="fas fa-times"></i>
            </button>
            <h3 class="text-lg font-bold mb-4 flex items-center gap-2">
                <i class="fas fa-database text-green-500"></i>
                Base d'images
            </h3>
            <div class="flex items-center gap-2 mb-4">
                <input type="text" id="imageBaseSearch" placeholder="Rechercher une image..." class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 focus:border-green-500 focus:outline-none text-gray-100" />
                <span class="text-green-500 text-xl"><i class="fas fa-search"></i></span>
            </div>
            <?php
            $images_dir = '../images/';
            $images = array_diff(scandir($images_dir), ['.', '..', 'placeholder.png']);
            $filtered_images = [];
            foreach($images as $img) {
                if (preg_match('/\.(jpg|jpeg|png|gif)$/i', $img)) {
                    $filtered_images[] = $img;
                }
            }
            ?>
            <?php if(empty($filtered_images)): ?>
                <div class="text-center text-gray-400 py-8" id="imageBaseList">
                    <i class="fas fa-image-slash text-4xl mb-2"></i>
                    <div>Aucune image disponible dans la base.</div>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-3 gap-4 max-h-64 overflow-y-auto" id="imageBaseList">
                    <?php foreach($filtered_images as $img): ?>
                    <div class="cursor-pointer group" data-img="<?php echo htmlspecialchars($img); ?>">
                        <img src="<?php echo $images_dir . htmlspecialchars($img); ?>" alt="<?php echo htmlspecialchars($img); ?>" class="rounded-lg border-2 border-transparent group-hover:border-green-500 transition-all object-contain h-24 w-full mb-2" />
                        <div class="text-xs text-gray-300 truncate"><?php echo htmlspecialchars($img); ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        const imagePreview = document.getElementById('imagePreview');
        const existingImageInput = document.getElementById('existingImageInput');

        // Popup base d'images
        const imageBaseModal = document.getElementById('imageBaseModal');
        const openImageBaseBtn = document.getElementById('openImageBase');
        const closeImageBaseBtn = document.getElementById('closeImageBase');
        const imageBaseList = document.getElementById('imageBaseList');
        const imageBaseSearch = document.getElementById('imageBaseSearch');
        
        openImageBaseBtn.addEventListener('click', () => {
            imageBaseModal.classList.remove('hidden');
            imageBaseSearch.value = '';
            Array.from(imageBaseList.children).forEach(el => el.style.display = '');
        });

        closeImageBaseBtn.addEventListener('click', () => {
            imageBaseModal.classList.add('hidden');
        });
        
        // Sélection d'une image dans la base
        imageBaseList.addEventListener('click', function(e) {
            let target = e.target;
            while(target && !target.dataset.img && target !== imageBaseList) {
                target = target.parentElement;
            }
            if(target && target.dataset.img) {
                const imgName = target.dataset.img;
                existingImageInput.value = imgName;
                imagePreview.innerHTML = `<img src="../images/${imgName}" alt="Aperçu" class="rounded-lg border-2 border-green-500 inline-block">`;
                imageBaseModal.classList.add('hidden');
            }
        });
        
        // Barre de recherche dans la popup
        imageBaseSearch.addEventListener('input', function() {
            const query = this.value.toLowerCase();
            Array.from(imageBaseList.children).forEach(function(item) {
                const name = item.dataset.img.toLowerCase();
                if(name.includes(query)) {
                    item.style.display = '';
                } else {
                    item.style.display = 'none';
                }
            });
        });
        
        // Barre de recherche pour filtrer les produits récents
        document.getElementById('productSearch')?.addEventListener('input', function() {
            const query = this.value.toLowerCase();
            document.querySelectorAll('.product-item').forEach(function(item) {
                const name = item.querySelector('h3')?.textContent.toLowerCase() || '';
                if(name.includes(query)) {
                    item.style.display = '';
                } else {
                    item.style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>
