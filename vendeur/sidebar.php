<?php
// Compter les commandes en attente pour ce vendeur uniquement
$pending_orders = 0;
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE status = 'pending' AND seller_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $pending_orders = $stmt->fetchColumn();
    error_log("Sidebar - Commandes en attente pour vendeur {$_SESSION['user_id']}: {$pending_orders}");
    
    // Log dans activity_logs
    $log_stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, details) VALUES (?, ?, ?)");
    $log_stmt->execute([
        $_SESSION['user_id'], 
        'check_pending_orders', 
        "Vérification commandes en attente: {$pending_orders} trouvée(s)"
    ]);
} catch(PDOException $e) {
    error_log("Sidebar - Erreur requête commandes: " . $e->getMessage());
    $pending_orders = 0;
}

// Compter les demandes d'ajout de produits pour ce vendeur (si table existe)
$pending_products = 0;
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM product_requests WHERE status = 'pending' AND user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $pending_products = $stmt->fetchColumn();
    error_log("Sidebar - Demandes produits en attente pour vendeur {$_SESSION['user_id']}: {$pending_products}");
    
    // Log dans activity_logs
    $log_stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, details) VALUES (?, ?, ?)");
    $log_stmt->execute([
        $_SESSION['user_id'], 
        'check_pending_products', 
        "Vérification demandes produits: {$pending_products} trouvée(s)"
    ]);
} catch(PDOException $e) {
    error_log("Sidebar - Erreur requête demandes produits: " . $e->getMessage());
    $pending_products = 0;
}
?>
<!-- Bouton toggle mobile -->
<button onclick="toggleSidebar()" class="lg:hidden fixed top-4 left-4 z-[1001] bg-teal-600 text-white p-3 rounded-lg shadow-lg hover:bg-teal-700 transition">
    <i class="fas fa-bars text-lg"></i>
</button>
<!-- Overlay pour mobile -->
<div id="sidebarOverlay" class="fixed inset-0 bg-black bg-opacity-60 z-40 hidden lg:hidden"></div>
<aside id="sellerSidebar" class="fixed lg:static inset-y-0 left-0 z-50 w-full lg:w-64 bg-gray-800 border-r border-gray-700 flex flex-col h-screen overflow-y-auto transition-transform duration-300 -translate-x-full lg:translate-x-0">
    <div class="p-6">
        <h1 class="text-2xl font-bold text-teal-400">
            <i class="fas fa-store mr-2"></i>Espace Vendeur
        </h1>
    </div>
    <nav class="mt-6 flex-1 flex flex-col">
        <a href="index.php" class="flex items-center justify-between px-6 py-3 text-gray-300 hover:bg-green-600 hover:text-white transition <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'bg-green-600 text-white border-l-4 border-green-400' : ''; ?>">
            <div class="flex items-center">
                <i class="fas fa-tachometer-alt w-6 text-green-400"></i>
                <span>Dashboard</span>
            </div>
        </a>
        
        <a href="orders.php" class="flex items-center justify-between px-6 py-3 text-gray-300 hover:bg-orange-600 hover:text-white transition <?php echo basename($_SERVER['PHP_SELF']) == 'orders.php' ? 'bg-orange-600 text-white border-l-4 border-orange-400' : ''; ?>">
            <div class="flex items-center">
                <i class="fas fa-clock w-6 text-orange-400"></i>
                <span>Commandes en attente</span>
            </div>
            <?php if($pending_orders > 0): ?>
            <span class="bg-red-500 text-white text-xs font-bold rounded-full px-2 py-1 animate-pulse">
                <?php echo $pending_orders; ?>
            </span>
            <?php endif; ?>
        </a>
        
        <a href="add_product.php" class="flex items-center justify-between px-6 py-3 text-gray-300 hover:bg-blue-600 hover:text-white transition <?php echo basename($_SERVER['PHP_SELF']) == 'add_product.php' ? 'bg-blue-600 text-white border-l-4 border-blue-400' : ''; ?>">
            <div class="flex items-center">
                <i class="fas fa-plus-circle w-6 text-blue-400"></i>
                <span>Demande d'ajout produit</span>
            </div>
            <?php if($pending_products > 0): ?>
            <span class="bg-orange-500 text-white text-xs font-bold rounded-full px-2 py-1">
                <?php echo $pending_products; ?>
            </span>
            <?php endif; ?>
        </a>

        <?php if(in_array($_SESSION['role'], ['vendeur_test', 'vendeur', 'vendeur_confirme', 'vendeur_senior', 'resp_vendeur', 'fondateur'])): ?>
        <div class="px-6 py-2 mt-4">
            <p class="text-xs text-gray-500 uppercase font-bold">Gestion</p>
        </div>
        <a href="my_products.php" class="flex items-center px-6 py-3 text-gray-300 hover:bg-purple-600 hover:text-white transition <?php echo basename($_SERVER['PHP_SELF']) == 'my_products.php' ? 'bg-purple-600 text-white border-l-4 border-purple-400' : ''; ?>">
            <i class="fas fa-box w-6 text-purple-400"></i>
            <span>Mes Produits</span>
        </a>
        <a href="statistics.php" class="flex items-center px-6 py-3 text-gray-300 hover:bg-cyan-600 hover:text-white transition <?php echo basename($_SERVER['PHP_SELF']) == 'statistics.php' ? 'bg-cyan-600 text-white border-l-4 border-cyan-400' : ''; ?>">
            <i class="fas fa-chart-line w-6 text-cyan-400"></i>
            <span>Statistiques</span>
        </a>
        <!-- Séparateur visuel -->
        <hr class="border-t border-gray-700 my-2">
        <?php if(in_array($_SESSION['role'], ['resp_vendeur', 'fondateur'])): ?>
        <a href="../admin/index.php" class="flex items-center px-6 py-3 text-gray-300 hover:bg-purple-600 hover:text-white transition border-t border-gray-700">
            <i class="fas fa-shield-alt w-6 text-purple-400"></i>
            <span>Panel Admin</span>
        </a>
        <?php endif; ?>
        <a href="../index.php" class="flex items-center px-6 py-3 text-gray-300 hover:bg-red-600 hover:text-white transition">
            <i class="fas fa-arrow-left w-6 text-red-400"></i>
            <span>Retour au site</span>
        </a>
        <!-- Spacer pour pousser les liens du bas -->
        <div class="flex-1"></div>
        <?php endif; ?>
    </nav>
</aside>
<script>
    function toggleSidebar() {
        const sidebar = document.getElementById('sellerSidebar');
        const overlay = document.getElementById('sidebarOverlay');
        const isActive = sidebar.classList.contains('translate-x-0');
        if (isActive) {
            sidebar.classList.remove('translate-x-0');
            sidebar.classList.add('-translate-x-full');
            overlay.classList.add('hidden');
            document.body.style.overflow = '';
        } else {
            sidebar.classList.remove('-translate-x-full');
            sidebar.classList.add('translate-x-0');
            overlay.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }
    }
    document.getElementById('sidebarOverlay').addEventListener('click', toggleSidebar);
    // Empêcher la sidebar mobile d'être visible sur desktop
    window.addEventListener('resize', function() {
        const sidebar = document.getElementById('sellerSidebar');
        const overlay = document.getElementById('sidebarOverlay');
        if(window.innerWidth >= 1024) {
            sidebar.classList.remove('-translate-x-full');
            sidebar.classList.add('translate-x-0');
            overlay.classList.add('hidden');
            document.body.style.overflow = '';
        }
    });
</script>
<style>
@media (min-width: 1024px) {
    #sellerSidebar {
        position: static !important;
        transform: none !important;
        box-shadow: none !important;
        z-index: 0 !important;
    }
    #sidebarOverlay {
        display: none !important;
    }
}
@media (max-width: 1023px) {
    #sellerSidebar {
        /* S'assurer que la sidebar mobile est masquée par défaut */
        transform: translateX(-100%);
    }
}
</style>
