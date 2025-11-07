<?php
require_once '../config.php';

if(!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['vendeur_confirme', 'vendeur_senior', 'resp_vendeur', 'fondateur'])) {
    header('Location: ../login.php');
    exit;
}

$can_delete = in_array($_SESSION['role'], ['resp_vendeur', 'fondateur']);

// Ajout/Modification de produit
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if($_POST['action'] === 'add') {
        try {
            // Insertion UNIQUEMENT dans la table items
            $stmt = $pdo->prepare("INSERT INTO items (name, description, price, image, category_id, stock, rarity, platform) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $_POST['name'],
                $_POST['description'],
                $_POST['price'],
                $_POST['image'],
                $_POST['category_id'],
                $_POST['stock'],
                $_POST['rarity'],
                $_POST['platform']
            ]);
            
            $product_id = $pdo->lastInsertId();
            
            // Log d'activité
            $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, details) VALUES (?, 'add_product', ?)");
            $stmt->execute([$_SESSION['user_id'], "Produit ajouté ID:{$product_id} - {$_POST['name']}"]);
            
            $success = "Produit ajouté avec succès !";
        } catch(PDOException $e) {
            $error = "Erreur lors de l'ajout : " . $e->getMessage();
        }
    } elseif($_POST['action'] === 'delete' && $can_delete) {
        try {
            $item_id = (int)$_POST['id'];
            
            // Récupérer les infos du produit avant suppression pour le log
            $stmt = $pdo->prepare("SELECT name, price FROM items WHERE id = ?");
            $stmt->execute([$item_id]);
            $item = $stmt->fetch();
            
            // Supprimer UNIQUEMENT de la table items
            $stmt = $pdo->prepare("DELETE FROM items WHERE id = ?");
            $stmt->execute([$item_id]);
            
            // Log d'activité
            if($item) {
                $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, details) VALUES (?, 'delete_product', ?)");
                $stmt->execute([
                    $_SESSION['user_id'], 
                    "Produit supprimé ID:{$item_id} - {$item['name']} ({$item['price']}€)"
                ]);
            }
            
            $success = "Produit supprimé !";
        } catch(PDOException $e) {
            $error = "Erreur lors de la suppression : " . $e->getMessage();
        }
    }
}

// Récupérer tous les produits de la table items
$items = $pdo->query("SELECT i.*, c.name as category_name FROM items i LEFT JOIN categories c ON i.category_id = c.id ORDER BY i.created_at DESC")->fetchAll();
$categories = $pdo->query("SELECT * FROM categories")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Produits - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="bg-gray-900 text-gray-100">
    <div class="flex flex-col md:flex-row min-h-screen">
        <?php include 'sidebar.php'; ?>

        <main class="flex-1 p-2 sm:p-8 w-full">
            <div class="mb-4 sm:mb-8">
                <h2 class="text-2xl sm:text-3xl font-bold mb-2">Gestion des Produits</h2>
                <p class="text-gray-400 text-sm sm:text-base">Gérez tous les produits du catalogue (table: items)</p>
            </div>

            <?php if(isset($success)): ?>
            <div class="bg-green-500/20 border border-green-500 text-green-400 px-2 sm:px-4 py-2 sm:py-3 rounded-lg mb-4 sm:mb-6 text-xs sm:text-base">
                <i class="fas fa-check-circle mr-2"></i><?php echo $success; ?>
            </div>
            <?php endif; ?>

            <?php if(isset($error)): ?>
            <div class="bg-red-500/20 border border-red-500 text-red-400 px-2 sm:px-4 py-2 sm:py-3 rounded-lg mb-4 sm:mb-6 text-xs sm:text-base">
                <i class="fas fa-exclamation-circle mr-2"></i><?php echo $error; ?>
            </div>
            <?php endif; ?>

            <!-- Liste des produits -->
            <section class="bg-gray-800 rounded-xl p-2 sm:p-6">
                <h3 class="text-lg sm:text-xl font-bold mb-2 sm:mb-4">Produits actuels (<?php echo count($items); ?>)</h3>
                
                <?php if (empty($items)): ?>
                    <p class="text-gray-400 text-xs sm:text-base">Aucun produit dans la base de données.</p>
                <?php else: ?>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2 sm:gap-6">
                        <?php foreach ($items as $item): ?>
                            <div class="bg-gray-700 rounded-lg p-2 sm:p-4 border-2 border-purple-500">
                                <div class="flex justify-between items-start mb-1 sm:mb-2">
                                    <h4 class="text-base sm:text-lg font-bold"><?php echo htmlspecialchars($item['name']); ?></h4>
                                    <span class="px-2 py-1 rounded-full text-xs font-bold uppercase bg-<?php 
                                        echo $item['platform'] === 'java' ? 'orange' : 'green'; 
                                    ?>-600">
                                        <?php echo strtoupper($item['platform']); ?>
                                    </span>
                                </div>
                                
                                <p class="text-xs sm:text-sm text-gray-400 mb-1 sm:mb-2"><?php echo htmlspecialchars($item['category_name']); ?></p>
                                <p class="text-base sm:text-xl font-bold text-green-400 mb-1 sm:mb-2"><?php echo number_format($item['price'], 2); ?> $</p>
                                <p class="text-xs sm:text-sm text-gray-300 mb-1 sm:mb-2">Stock: <?php echo $item['stock']; ?></p>
                                <p class="text-xs text-gray-500 mb-2 sm:mb-4">Rareté: <?php echo $item['rarity']; ?></p>
                                
                                <?php if ($can_delete): ?>
                                <form method="POST" onsubmit="return confirm('Supprimer ce produit ?')">
                                    <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <button type="submit" class="w-full bg-red-600 hover:bg-red-700 text-white py-2 rounded-lg transition text-xs sm:text-base">
                                        <i class="fas fa-trash mr-2"></i>Supprimer
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </main>
    </div>
</body>
</html>
