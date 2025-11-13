<?php
require_once '../config.php';
require_once 'check_seller.php';

// Vérifier le niveau d'accès
if (!in_array($_SESSION['role'], ['vendeur_test', 'vendeur', 'vendeur_confirme', 'vendeur_senior', 'resp_vendeur', 'fondateur'])) {
    header('Location: index.php');
    exit;
}

// Traitement de la suppression
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_product'])) {
    try {
        // Récupérer les infos du produit avant suppression et vérifier qu'il appartient au vendeur
        $stmt = $pdo->prepare("SELECT name FROM items WHERE id = ? AND seller_id = ?");
        $stmt->execute([$_POST['product_id'], $_SESSION['user_id']]);
        $product_name = $stmt->fetchColumn();
        
        if($product_name) {
            // Supprimer le produit
            $stmt = $pdo->prepare("DELETE FROM items WHERE id = ? AND seller_id = ?");
            $stmt->execute([$_POST['product_id'], $_SESSION['user_id']]);
            
            // Logger l'action
            $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, details, created_at) VALUES (?, ?, ?, NOW())");
            $stmt->execute([
                $_SESSION['user_id'],
                'delete_product',
                "Suppression du produit: " . $product_name . " (ID: " . $_POST['product_id'] . ")"
            ]);
            
            $_SESSION['success'] = "Produit supprimé avec succès!";
        } else {
            $_SESSION['error'] = "Produit introuvable ou vous n'avez pas les droits.";
        }
    } catch(PDOException $e) {
        $_SESSION['error'] = "Erreur de suppression : " . $e->getMessage();
    }
    
    header('Location: my_products.php');
    exit;
}

// Traitement de la modification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_product'])) {
    try {
        $stmt = $pdo->prepare("UPDATE items SET name = ?, description = ?, price = ?, stock = ? WHERE id = ? AND seller_id = ?");
        $stmt->execute([
            $_POST['name'],
            $_POST['description'],
            $_POST['price'],
            $_POST['stock'],
            $_POST['product_id'],
            $_SESSION['user_id']
        ]);
        
        // Logger l'action
        $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, details, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([
            $_SESSION['user_id'],
            'edit_product',
            "Modification du produit: " . $_POST['name'] . " (ID: " . $_POST['product_id'] . ")"
        ]);
        
        $_SESSION['success'] = "Produit modifié avec succès!";
    } catch(PDOException $e) {
        $_SESSION['error'] = "Erreur de modification : " . $e->getMessage();
    }
    
    header('Location: my_products.php');
    exit;
}

// Récupérer les messages de session
$success = isset($_SESSION['success']) ? $_SESSION['success'] : null;
$error = isset($_SESSION['error']) ? $_SESSION['error'] : null;
unset($_SESSION['success'], $_SESSION['error']);

// Récupérer les produits du vendeur connecté uniquement
try {
    $stmt = $pdo->prepare("SELECT * FROM items WHERE seller_id = ? ORDER BY created_at DESC");
    $stmt->execute([$_SESSION['user_id']]);
    $products = $stmt->fetchAll();
} catch(PDOException $e) {
    $products = [];
    if (!$error) {
        $error = "Erreur de chargement : " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Produits - Vendeur</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="bg-gray-900 text-gray-100">

    <div class="flex flex-col md:flex-row min-h-screen">
        <?php include 'sidebar.php'; ?>

        <main class="flex-1 p-2 sm:p-8 w-full">
            <div class="container mx-auto">
                <div class="mb-4 sm:mb-8 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2 sm:gap-0">
                    <div>
                        <h1 class="text-2xl sm:text-4xl font-bold mb-2">
                            <i class="fas fa-box text-blue-500 mr-2 sm:mr-3"></i>
                            Mes Produits
                        </h1>
                        <p class="text-gray-400 text-xs sm:text-base">Gérez votre inventaire de produits</p>
                    </div>
                    <a href="add_product.php" class="bg-gradient-to-r from-green-600 to-teal-600 hover:from-green-700 hover:to-teal-700 px-4 sm:px-6 py-2 sm:py-3 rounded-lg font-bold transition-all transform hover:scale-105 shadow-lg text-xs sm:text-base">
                        <i class="fas fa-plus mr-2"></i>Nouveau Produit
                    </a>
                </div>

                <?php if($success): ?>
                <div class="bg-green-500/20 border border-green-500 text-green-400 px-2 sm:px-6 py-2 sm:py-4 rounded-lg mb-4 sm:mb-6 text-xs sm:text-base">
                    <i class="fas fa-check-circle mr-2"></i><?php echo htmlspecialchars($success); ?>
                </div>
                <?php endif; ?>

                <?php if($error): ?>
                <div class="bg-red-500/20 border border-red-500 text-red-400 px-2 sm:px-6 py-2 sm:py-4 rounded-lg mb-4 sm:mb-6 text-xs sm:text-base">
                    <i class="fas fa-exclamation-circle mr-2"></i><?php echo htmlspecialchars($error); ?>
                </div>
                <?php endif; ?>

                <!-- Liste des produits -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2 sm:gap-6">
                    <?php foreach($products as $product): ?>
                    <div class="bg-gray-800 rounded-xl overflow-hidden hover:transform hover:scale-105 transition-all shadow-lg">
                        <div class="flex items-center justify-center" style="height:256px;">
                            <?php if($product['image']): ?>
                            <img src="../images/<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" class="object-contain" style="width:256px;height:256px;">
                            <?php else: ?>
                            <i class="fas fa-box text-6xl text-white/50"></i>
                            <?php endif; ?>
                        </div>
                        <div class="p-2 sm:p-4">
                            <div class="flex justify-between items-start mb-1 sm:mb-2">
                                <h3 class="text-base sm:text-lg font-bold"><?php echo htmlspecialchars($product['name']); ?></h3>
                                <span class="text-lg sm:text-2xl font-bold text-purple-400"><?php echo number_format($product['price'], 2); ?>€</span>
                            </div>
                            <p class="text-xs sm:text-sm text-gray-400 mb-1 sm:mb-2"><?php echo htmlspecialchars(substr($product['description'], 0, 100)); ?>...</p>
                            <div class="flex items-center gap-1 sm:gap-2 mb-2 sm:mb-3 text-xs">
                                <span class="bg-gray-700 px-2 py-1 rounded">
                                    <i class="fas fa-box mr-1"></i>Stock: <?php echo $product['stock']; ?>
                                </span>
                                <?php if($product['rarity']): ?>
                                <span class="bg-purple-600/30 text-purple-400 px-2 py-1 rounded">
                                    <i class="fas fa-star mr-1"></i><?php echo htmlspecialchars($product['rarity']); ?>
                                </span>
                                <?php endif; ?>
                            </div>
                            <div class="flex gap-1 sm:gap-2">
                                <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode($product)); ?>)" class="flex-1 bg-blue-600 hover:bg-blue-700 px-2 sm:px-3 py-1 sm:py-2 rounded text-center text-xs sm:text-sm font-bold transition-colors">
                                    <i class="fas fa-edit mr-1"></i>Modifier
                                </button>
                                <!-- Bouton supprimer retiré -->
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>

                    <?php if(empty($products)): ?>
                    <div class="col-span-full bg-gray-800 rounded-xl p-6 sm:p-12 text-center">
                        <i class="fas fa-box-open text-3xl sm:text-6xl text-gray-600 mb-2 sm:mb-4"></i>
                        <p class="text-base sm:text-xl font-semibold text-gray-400">Aucun produit trouvé</p>
                        <p class="text-xs sm:text-sm text-gray-500 mt-1 sm:mt-2">Commencez par ajouter votre premier produit</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Modal de modification -->
    <div id="editModal" class="hidden fixed inset-0 bg-black/70 flex items-center justify-center z-50 p-2 sm:p-4">
        <div class="bg-gray-800 rounded-xl max-w-full sm:max-w-2xl w-full max-h-[90vh] overflow-y-auto shadow-2xl">
            <div class="sticky top-0 bg-gray-800 border-b border-gray-700 p-2 sm:p-6 flex justify-between items-center">
                <h2 class="text-lg sm:text-2xl font-bold">
                    <i class="fas fa-edit text-blue-500 mr-2"></i>Modifier le produit
                </h2>
                <button onclick="closeEditModal()" class="text-gray-400 hover:text-white text-xl sm:text-2xl">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form method="POST" class="p-2 sm:p-6 space-y-2 sm:space-y-4 text-xs sm:text-base">
                <input type="hidden" name="product_id" id="edit_product_id">
                <input type="hidden" name="edit_product" value="1">
                
                <div>
                    <label class="block text-xs sm:text-sm font-bold mb-1 sm:mb-2">Nom du produit</label>
                    <input type="text" name="name" id="edit_name" required class="w-full bg-gray-700 border border-gray-600 rounded-lg px-2 sm:px-4 py-2 sm:py-3 focus:outline-none focus:border-blue-500">
                </div>
                
                <div>
                    <label class="block text-xs sm:text-sm font-bold mb-1 sm:mb-2">Description</label>
                    <textarea name="description" id="edit_description" required rows="4" class="w-full bg-gray-700 border border-gray-600 rounded-lg px-2 sm:px-4 py-2 sm:py-3 focus:outline-none focus:border-blue-500"></textarea>
                </div>
                
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 sm:gap-4">
                    <div>
                        <label class="block text-xs sm:text-sm font-bold mb-1 sm:mb-2">Prix (€)</label>
                        <input type="number" name="price" id="edit_price" step="0.01" required class="w-full bg-gray-700 border border-gray-600 rounded-lg px-2 sm:px-4 py-2 sm:py-3 focus:outline-none focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-xs sm:text-sm font-bold mb-1 sm:mb-2">Stock</label>
                        <input type="number" name="stock" id="edit_stock" required class="w-full bg-gray-700 border border-gray-600 rounded-lg px-2 sm:px-4 py-2 sm:py-3 focus:outline-none focus:border-blue-500">
                    </div>
                </div>
                
                <div class="flex flex-col sm:flex-row gap-2 sm:gap-3 pt-2 sm:pt-4">
                    <button type="submit" class="flex-1 bg-gradient-to-r from-blue-600 to-cyan-600 hover:from-blue-700 hover:to-cyan-700 px-4 sm:px-6 py-2 sm:py-3 rounded-lg font-bold transition-all text-xs sm:text-base">
                        <i class="fas fa-save mr-2"></i>Enregistrer
                    </button>
                    <button type="button" onclick="closeEditModal()" class="px-4 sm:px-6 py-2 sm:py-3 bg-gray-700 hover:bg-gray-600 rounded-lg font-bold transition-all text-xs sm:text-base">
                        Annuler
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openEditModal(product) {
            document.getElementById('edit_product_id').value = product.id;
            document.getElementById('edit_name').value = product.name;
            document.getElementById('edit_description').value = product.description;
            document.getElementById('edit_price').value = product.price;
            document.getElementById('edit_stock').value = product.stock;
            document.getElementById('editModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.add('hidden');
            document.body.style.overflow = 'auto';
        }

        // Fermer avec Escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeEditModal();
            }
        });
    </script>
</body>
</html>
