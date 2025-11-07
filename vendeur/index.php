<?php
require_once '../config.php';
require_once 'check_seller.php';

// Récupérer les statistiques du vendeur
try {
    $stats = [
        'total_sales' => 0,
        'pending_orders' => 0,
        'shipping_orders' => 0,
        'completed_orders' => 0,
        'total_revenue' => 0
    ];

    // Total des ventes (toutes commandes du vendeur, tous statuts)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE seller_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $stats['total_sales'] = $stmt->fetchColumn();

    // Commandes en attente (status = 'pending' ou 'processing')
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE seller_id = ? AND status IN ('pending', 'processing')");
    $stmt->execute([$_SESSION['user_id']]);
    $stats['pending_orders'] = $stmt->fetchColumn();

    // Commandes en cours de livraison (status = 'shipped')
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE seller_id = ? AND status = 'shipped'");
    $stmt->execute([$_SESSION['user_id']]);
    $stats['shipping_orders'] = $stmt->fetchColumn();

    // Commandes complétées (status = 'completed' ou 'delivered')
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE seller_id = ? AND status IN ('completed', 'delivered')");
    $stmt->execute([$_SESSION['user_id']]);
    $stats['completed_orders'] = $stmt->fetchColumn();

    // Revenu total (toutes commandes du vendeur, tous statuts et paiements)
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(total - discount_amount), 0) FROM orders WHERE seller_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $stats['total_revenue'] = $stmt->fetchColumn();

} catch(PDOException $e) {
    error_log("Erreur stats vendeur : " . $e->getMessage());
}

// Récupérer les dernières commandes du vendeur
try {
    $stmt = $pdo->prepare("
        SELECT o.*, u.username, u.minecraft_username 
        FROM orders o 
        JOIN users u ON o.user_id = u.id 
        WHERE o.seller_id = ?
        ORDER BY o.created_at DESC 
        LIMIT 10
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $recent_orders = $stmt->fetchAll();
} catch(PDOException $e) {
    $recent_orders = [];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Espace Vendeur - Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="bg-gray-900 text-gray-100">

    <div class="flex flex-col md:flex-row min-h-screen">
        <!-- Sidebar -->
        <?php include 'sidebar.php'; ?>

        <!-- Contenu principal -->
        <main class="flex-1 p-2 sm:p-8 w-full">
            <div class="container mx-auto">
                <!-- En-tête -->
                <div class="mb-4 sm:mb-8">
                    <h1 class="text-2xl sm:text-4xl font-bold mb-2">
                        <i class="fas fa-tachometer-alt text-green-500 mr-2 sm:mr-3"></i>
                        Dashboard Vendeur
                    </h1>
                    <p class="text-gray-400 text-xs sm:text-base">Bienvenue, <?php echo htmlspecialchars($_SESSION['username']); ?> !</p>
                </div>

                <!-- Statistiques -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-2 sm:gap-6 mb-4 sm:mb-8">
                    <div class="bg-gray-800 rounded-xl p-6 border-2 border-blue-500/30 hover:border-blue-500/50 transition-all">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-400 text-sm mb-1">Total Ventes</p>
                                <p class="text-3xl font-bold text-blue-400"><?php echo $stats['total_sales']; ?></p>
                            </div>
                            <div class="w-16 h-16 bg-blue-500/20 rounded-full flex items-center justify-center">
                                <i class="fas fa-shopping-bag text-3xl text-blue-500"></i>
                            </div>
                        </div>
                    </div>

                    <div class="bg-gray-800 rounded-xl p-6 border-2 border-orange-500/30 hover:border-orange-500/50 transition-all">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-400 text-sm mb-1">En Attente</p>
                                <p class="text-3xl font-bold text-orange-400"><?php echo $stats['pending_orders']; ?></p>
                            </div>
                            <div class="w-16 h-16 bg-orange-500/20 rounded-full flex items-center justify-center">
                                <i class="fas fa-clock text-3xl text-orange-500"></i>
                            </div>
                        </div>
                    </div>

                    <div class="bg-gray-800 rounded-xl p-6 border-2 border-cyan-500/30 hover:border-cyan-500/50 transition-all">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-400 text-sm mb-1">En cours de livraison</p>
                                <p class="text-3xl font-bold text-cyan-400"><?php echo $stats['shipping_orders']; ?></p>
                            </div>
                            <div class="w-16 h-16 bg-cyan-500/20 rounded-full flex items-center justify-center">
                                <i class="fas fa-truck text-3xl text-cyan-500"></i>
                            </div>
                        </div>
                    </div>

                    <div class="bg-gray-800 rounded-xl p-6 border-2 border-green-500/30 hover:border-green-500/50 transition-all">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-400 text-sm mb-1">Complétées</p>
                                <p class="text-3xl font-bold text-green-400"><?php echo $stats['completed_orders']; ?></p>
                            </div>
                            <div class="w-16 h-16 bg-green-500/20 rounded-full flex items-center justify-center">
                                <i class="fas fa-check-circle text-3xl text-green-500"></i>
                            </div>
                        </div>
                    </div>

                    <div class="bg-gray-800 rounded-xl p-6 border-2 border-purple-500/30 hover:border-purple-500/50 transition-all">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-400 text-sm mb-1">Revenu Total</p>
                                <p class="text-3xl font-bold text-purple-400"><?php echo number_format($stats['total_revenue'], 2); ?>$</p>
                            </div>
                            <div class="w-16 h-16 bg-purple-500/20 rounded-full flex items-center justify-center">
                                <i class="fas fa-dollar-sign text-3xl text-purple-500"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Actions rapides -->
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-2 sm:gap-6 mb-4 sm:mb-8">
                    <a href="orders.php" class="bg-gradient-to-br from-orange-600 to-red-600 rounded-xl p-6 hover:from-orange-700 hover:to-red-700 transition-all transform hover:scale-105 shadow-lg">
                        <div class="flex items-center gap-4">
                            <div class="w-14 h-14 bg-white/20 rounded-full flex items-center justify-center">
                                <i class="fas fa-clock text-3xl"></i>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold">Commandes en attente</h3>
                                <p class="text-sm opacity-80">Gérer les nouvelles commandes</p>
                            </div>
                        </div>
                    </a>

                    <a href="add_product.php" class="bg-gradient-to-br from-green-600 to-teal-600 rounded-xl p-6 hover:from-green-700 hover:to-teal-700 transition-all transform hover:scale-105 shadow-lg">
                        <div class="flex items-center gap-4">
                            <div class="w-14 h-14 bg-white/20 rounded-full flex items-center justify-center">
                                <i class="fas fa-plus-circle text-3xl"></i>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold">Ajouter un produit</h3>
                                <p class="text-sm opacity-80">Demander l'ajout d'un item</p>
                            </div>
                        </div>
                    </a>

                    <?php if(in_array($_SESSION['role'], ['vendeur_test', 'vendeur', 'vendeur_confirme', 'vendeur_senior', 'resp_vendeur', 'fondateur'])): ?>
                    <a href="my_products.php" class="bg-gradient-to-br from-blue-600 to-purple-600 rounded-xl p-6 hover:from-blue-700 hover:to-purple-700 transition-all transform hover:scale-105 shadow-lg">
                        <div class="flex items-center gap-4">
                            <div class="w-14 h-14 bg-white/20 rounded-full flex items-center justify-center">
                                <i class="fas fa-box text-3xl"></i>
                            </div>
                            <div>
                                <h3 class="text-xl font-bold">Mes Produits</h3>
                                <p class="text-sm opacity-80">Gérer mon inventaire</p>
                            </div>
                        </div>
                    </a>
                    <?php endif; ?>
                </div>

                <!-- Dernières commandes -->
                <div class="bg-gray-800 rounded-xl p-2 sm:p-6">
                    <h2 class="text-lg sm:text-2xl font-bold mb-2 sm:mb-4 flex items-center gap-2">
                        <i class="fas fa-list text-green-500"></i>
                        Dernières Commandes Terminées
                    </h2>
                    
                    <div class="overflow-x-auto">
                        <!-- Tableau commandes -->
                        <table class="w-full text-xs sm:text-base">
                            <thead class="bg-gray-900">
                                <tr>
                                    <th class="px-2 sm:px-4 py-2 sm:py-3 text-left">ID</th>
                                    <th class="px-2 sm:px-4 py-2 sm:py-3 text-left">Client</th>
                                    <th class="px-2 sm:px-4 py-2 sm:py-3 text-left">Pseudo Minecraft</th>
                                    <th class="px-2 sm:px-4 py-2 sm:py-3 text-center">Montant</th>
                                    <th class="px-2 sm:px-4 py-2 sm:py-3 text-center hidden sm:table-cell">Code promo</th>
                                    <th class="px-2 sm:px-4 py-2 sm:py-3 text-center">Articles</th>
                                    <th class="px-2 sm:px-4 py-2 sm:py-3 text-center hidden sm:table-cell">Grade abo</th>
                                    <th class="px-2 sm:px-4 py-2 sm:py-3 text-center">Statut</th>
                                    <th class="px-2 sm:px-4 py-2 sm:py-3 text-center hidden sm:table-cell">Date</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-700">
                                <?php foreach($recent_orders as $order): ?>
                                <tr class="hover:bg-gray-700/50">
                                    <td class="px-2 sm:px-4 py-2 sm:py-3 font-mono text-xs sm:text-sm">#<?php echo $order['id']; ?></td>
                                    <td class="px-2 sm:px-4 py-2 sm:py-3"><?php echo htmlspecialchars($order['username']); ?></td>
                                    <td class="px-2 sm:px-4 py-2 sm:py-3">
                                        <code class="bg-gray-900 px-2 py-1 rounded text-xs">
                                            <?php echo htmlspecialchars($order['minecraft_username'] ?? 'N/A'); ?>
                                        </code>
                                    </td>
                                    <td class="px-2 sm:px-4 py-2 sm:py-3 text-center">
                                        <span class="inline-block px-2 sm:px-3 py-1 rounded-full text-xs font-bold border bg-purple-500/20 text-purple-400 border-purple-500/30">
                                            <?php
                                            $discount_amount = $order['discount_amount'] ?? 0;
                                            $montant = $order['total'] - $discount_amount;
                                            echo number_format($montant, 2) . "$";
                                            ?>
                                        </span>
                                    </td>
                                    <td class="px-2 sm:px-4 py-2 sm:py-3 text-center hidden sm:table-cell">
                                        <span class="inline-block px-2 sm:px-3 py-1 rounded-full text-xs font-bold border bg-indigo-500/20 text-indigo-400 border-indigo-500/30">
                                            <?php
                                            echo ($discount_amount > 0) ? number_format($discount_amount, 2) . "$" : "-";
                                            ?>
                                        </span>
                                    </td>
                                    <td class="px-2 sm:px-4 py-2 sm:py-3 text-center">
                                        <span class="inline-block px-2 sm:px-3 py-1 rounded-full text-xs font-bold border bg-yellow-500/20 text-yellow-400 border-yellow-500/30">
                                            <?php
                                            // Compte le nombre d'articles pour la commande
                                            $stmt = $pdo->prepare("SELECT SUM(quantity) FROM order_items WHERE order_id = ?");
                                            $stmt->execute([$order['id']]);
                                            $nb_articles = $stmt->fetchColumn();
                                            echo $nb_articles ?: "-";
                                            ?>
                                        </span>
                                    </td>
                                    <td class="px-2 sm:px-4 py-2 sm:py-3 text-center hidden sm:table-cell">
                                        <span class="inline-block px-2 sm:px-3 py-1 rounded-full text-xs font-bold border bg-blue-500/20 text-blue-400 border-blue-500/30">
                                            <?php
                                            // Récupère le grade d'abonnement du client si permissions = 'actif'
                                            $stmt = $pdo->prepare("SELECT type FROM abofac WHERE user_id = ? AND permissions = 'actif' LIMIT 1");
                                            $stmt->execute([$order['user_id']]);
                                            $client_abo = $stmt->fetchColumn();
                                            echo $client_abo ? htmlspecialchars($client_abo) : "Non";
                                            ?>
                                        </span>
                                    </td>
                                    <td class="px-2 sm:px-4 py-2 sm:py-3 text-center">
                                        <?php
                                        // Couleurs et labels adaptés au statut
                                        $status_colors = [
                                            'pending'    => 'bg-orange-500/20 text-orange-400 border-orange-500/30',
                                            'processing' => 'bg-blue-500/20 text-blue-400 border-blue-500/30',
                                            'completed'  => 'bg-green-500/20 text-green-400 border-green-500/30',
                                            'cancelled'  => 'bg-red-500/20 text-red-400 border-red-500/30',
                                            'delivered'  => 'bg-purple-500/20 text-purple-400 border-purple-500/30',
                                            'clos'       => 'bg-gray-500/20 text-gray-400 border-gray-500/30',
                                            'shipped'    => 'bg-cyan-500/20 text-cyan-400 border-cyan-500/30'
                                        ];
                                        $status_labels = [
                                            'pending'    => 'En attente',
                                            'processing' => 'En cours',
                                            'completed'  => 'Complétée',
                                            'cancelled'  => 'Annulée',
                                            'delivered'  => 'Livrée',
                                            'clos'       => 'Clôturée',
                                            'shipped'    => 'Expédiée'
                                        ];
                                        $statut = $order['status'];
                                        $color = $status_colors[$statut] ?? 'bg-gray-700 text-gray-400 border-gray-700';
                                        $label = $status_labels[$statut] ?? ucfirst($statut);
                                        ?>
                                        <span class="inline-block px-2 sm:px-3 py-1 rounded-full text-xs font-bold border <?php echo $color; ?>">
                                            <?php echo $label; ?>
                                        </span>
                                    </td>
                                    <td class="px-2 sm:px-4 py-2 sm:py-3 text-center text-xs sm:text-sm text-gray-400 hidden sm:table-cell">
                                        <?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                
                                <?php if(empty($recent_orders)): ?>
                                <tr>
                                    <td colspan="9" class="px-2 sm:px-4 py-6 sm:py-8 text-center text-gray-500">
                                        <i class="fas fa-inbox text-2xl sm:text-4xl mb-2 opacity-20"></i>
                                        <p class="text-xs sm:text-base">Aucune commande récente</p>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
