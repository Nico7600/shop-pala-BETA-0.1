<?php
session_start();
require_once 'config.php';

if(!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Fonction de traduction des statuts
function translateStatus($status) {
    $translations = [
        'pending' => 'En attente',
        'processing' => 'En cours',
        'shipped' => 'Expédiée',
        'delivered' => 'Livrée',
        'cancelled' => 'Annulée',
        'refunded' => 'Remboursée'
    ];
    return $translations[$status] ?? ucfirst($status);
}

// Fonction pour la couleur du badge selon le statut
function getStatusColor($status) {
    $colors = [
        'pending' => 'bg-yellow-500/20 text-yellow-400',
        'processing' => 'bg-blue-500/20 text-blue-400',
        'shipped' => 'bg-purple-500/20 text-purple-400',
        'delivered' => 'bg-green-500/20 text-green-400',
        'cancelled' => 'bg-red-500/20 text-red-400',
        'refunded' => 'bg-gray-500/20 text-gray-400'
    ];
    return $colors[$status] ?? 'bg-gray-500/20 text-gray-400';
}

try {
    $stmt = $pdo->prepare("
        SELECT o.*, COUNT(oi.id) as item_count
        FROM orders o
        LEFT JOIN order_items oi ON o.id = oi.order_id
        WHERE o.user_id = ?
        GROUP BY o.id
        ORDER BY o.created_at DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Erreur commandes : " . $e->getMessage());
    $orders = [];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mes Commandes - CrazySouls Shop</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="bg-gray-900 text-gray-100 flex flex-col min-h-screen">
    <?php include 'includes/header.php'; ?>

    <div class="container mx-auto px-4 py-8 flex-grow">
        <h1 class="text-4xl font-bold mb-8">
            <i class="fas fa-box text-purple-500 mr-3"></i>
            Mes Commandes
        </h1>

        <?php if(isset($_SESSION['success'])): ?>
            <div class="bg-green-500/20 border border-green-500 text-green-400 px-4 py-3 rounded mb-6 flex items-center gap-3">
                <i class="fas fa-check-circle text-xl"></i>
                <span><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></span>
            </div>
        <?php endif; ?>

        <?php if(empty($orders)): ?>
            <div class="bg-gray-800 rounded-xl p-12 text-center">
                <i class="fas fa-box-open text-6xl text-gray-600 mb-4"></i>
                <h2 class="text-2xl font-bold mb-2">Aucune commande</h2>
                <p class="text-gray-400 mb-6">Vous n'avez pas encore passé de commande</p>
                <a href="index.php" class="inline-block bg-purple-600 hover:bg-purple-700 px-6 py-3 rounded-lg font-bold transition">
                    <i class="fas fa-shopping-bag mr-2"></i>
                    Voir les produits
                </a>
            </div>
        <?php else: ?>
            <div class="space-y-4">
                <?php foreach($orders as $order): ?>
                    <?php
                        $statusColor = getStatusColor($order['status']);
                        $borderColor = '';
                        if(strpos($statusColor, 'yellow') !== false) $borderColor = 'border-yellow-500';
                        elseif(strpos($statusColor, 'blue') !== false) $borderColor = 'border-blue-500';
                        elseif(strpos($statusColor, 'purple') !== false) $borderColor = 'border-purple-500';
                        elseif(strpos($statusColor, 'green') !== false) $borderColor = 'border-green-500';
                        elseif(strpos($statusColor, 'red') !== false) $borderColor = 'border-red-500';
                        else $borderColor = 'border-gray-700';

                        $discount_amount = $order['discount_amount'] ?? 0;
                        $permission_discount = $order['permission_discount'] ?? 0;
                        $total_reduction = $discount_amount + $permission_discount;
                    ?>
                    <div class="bg-gradient-to-br from-gray-800 via-gray-900 to-gray-800 rounded-2xl p-6 shadow-lg hover:shadow-2xl transition border-2 <?php echo $borderColor; ?> relative group">
                        <!-- Header statut + date -->
                        <div class="flex justify-between items-center mb-4 px-2 py-2 rounded-t-xl bg-gradient-to-r from-gray-900/80 to-transparent">
                            <span class="inline-block px-4 py-2 rounded-full text-base font-bold <?php echo $statusColor; ?> group-hover:scale-105 transition">
                                <i class="fas fa-circle text-base mr-2"></i>
                                <?php echo translateStatus($order['status']); ?>
                            </span>
                            <span class="text-xs text-gray-400 font-mono">
                                <i class="far fa-clock mr-1"></i>
                                <?php echo date('d/m/Y à H:i', strtotime($order['created_at'])); ?>
                            </span>
                        </div>
                        <div class="border-b border-gray-700 mb-3"></div>
                        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-3">
                            <div>
                                <h3 class="text-xl font-bold mb-2 flex items-center gap-2 text-purple-400">
                                    <i class="fas fa-hashtag"></i>
                                    Commande #<?php echo $order['id']; ?>
                                </h3>
                                <div class="flex items-center gap-4 text-gray-400 text-base mb-2">
                                    <span>
                                        <i class="fas fa-box mr-2"></i>
                                        <?php echo $order['item_count']; ?> article(s)
                                    </span>
                                </div>
                            </div>
                            <div class="flex flex-col items-end gap-2">
                                <span class="text-2xl font-bold text-green-400 flex items-center gap-2 drop-shadow">
                                    <i class="fas fa-dollar-sign"></i>
                                    <?php echo number_format($order['total'], 2); ?>$
                                </span>
                            </div>
                        </div>
                        <?php if($total_reduction > 0): ?>
                            <div class="flex justify-between items-center text-base font-bold mb-2 px-2 py-2 rounded bg-gray-900/60 border border-gray-700">
                                <span class="flex items-center gap-2 text-green-400">
                                    <i class="fas fa-tag"></i>
                                    Réduction(s)
                                </span>
                                <span class="text-red-400 font-bold">-<?php echo number_format($total_reduction, 2); ?>$</span>
                            </div>
                            <?php if($permission_discount > 0): ?>
                                <div class="text-base text-gray-400 mb-2 px-2">
                                    Grade/Faction : <span class="text-red-400 font-bold">-<?php echo number_format($permission_discount, 2); ?>$</span>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                        <div class="border-t border-gray-700 mt-4 mb-2"></div>
                        <div class="flex justify-between items-center text-2xl font-bold mt-2 px-2">
                            <span class="flex items-center gap-2 text-green-300 drop-shadow">
                                <i class="fas fa-money-check-alt"></i>
                                Total a payé
                            </span>
                            <span class="text-green-400 drop-shadow">
                                <?php
                                $total_paye = $order['total'] - $total_reduction;
                                echo number_format($total_paye, 2) . "$";
                                ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php include 'includes/footer.php'; ?>
</body>
</html>
