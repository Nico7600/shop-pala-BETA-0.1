<?php
// Connexion à la base de données (à adapter selon votre config)
require_once '../config.php'; // ou le chemin vers votre fichier de connexion

// Nombre de demandes d'abonnement "En attente de paiement" dans permissions
$nbDemandesAbos = 0;
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM permissions WHERE statut = 'En attente de paiement'");
    $stmt->execute();
    $nbDemandesAbos = $stmt->fetchColumn();
} catch (Exception $e) {
    $nbDemandesAbos = 0;
}

// Nombre de demandes d'échange "attente" dans demandes_echanges
$nbDemandesEchanges = 0;
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM demandes_echanges WHERE statut = 'attente'");
    $stmt->execute();
    $nbDemandesEchanges = $stmt->fetchColumn();
} catch (Exception $e) {
    $nbDemandesEchanges = 0;
}
?>
<!-- Bouton mobile pour ouvrir la sidebar -->
<button id="sidebarToggle" class="md:hidden fixed top-4 left-4 z-50 bg-purple-600 text-white p-2 rounded-lg shadow-lg">
    <i class="fas fa-bars"></i>
</button>
<aside id="adminSidebar" class="md:static fixed inset-y-0 top-0 left-0 z-40 w-full md:w-64 bg-gray-800 border-r border-gray-700 flex flex-col overflow-y-auto transition-transform duration-300 -translate-x-full md:translate-x-0 min-h-screen">
    <div class="p-6">
        <h1 class="text-2xl font-bold text-purple-500">
            <i class="fas fa-shield-alt mr-2"></i>Admin Panel
        </h1>
    </div>
    <nav class="mt-6 flex-1 flex flex-col">
        <a href="index.php" class="flex items-center px-6 py-3 text-gray-300 hover:bg-purple-600 hover:text-white transition <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'bg-purple-600 text-white border-l-4 border-purple-400 font-semibold shadow-lg' : ''; ?>">
            <i class="fas fa-tachometer-alt w-6 text-purple-400"></i>
            <span>Dashboard</span>
        </a>
        <a href="products.php" class="flex items-center px-6 py-3 text-gray-300 hover:bg-green-600 hover:text-white transition <?php echo basename($_SERVER['PHP_SELF']) == 'products.php' ? 'bg-green-600 text-white border-l-4 border-green-400' : ''; ?>">
            <i class="fas fa-box w-6 text-green-400"></i>
            <span>Produits</span>
        </a>
        <a href="users.php" class="flex items-center px-6 py-3 text-gray-300 hover:bg-blue-600 hover:text-white transition <?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'bg-blue-600 text-white border-l-4 border-blue-400' : ''; ?>">
            <i class="fas fa-users w-6 text-blue-400"></i>
            <span>Utilisateurs</span>
        </a>
        <a href="orders.php" class="flex items-center px-6 py-3 text-gray-300 hover:bg-orange-600 hover:text-white transition <?php echo basename($_SERVER['PHP_SELF']) == 'orders.php' ? 'bg-orange-600 text-white border-l-4 border-orange-400' : ''; ?>">
            <i class="fas fa-shopping-cart w-6 text-orange-400"></i>
            <span>Commandes</span>
        </a>
        <a href="announcements.php" class="flex items-center px-6 py-3 text-gray-300 hover:bg-yellow-600 hover:text-white transition <?php echo basename($_SERVER['PHP_SELF']) == 'announcements.php' ? 'bg-yellow-600 text-white border-l-4 border-yellow-400' : ''; ?>">
            <i class="fas fa-bullhorn w-6 text-yellow-400"></i>
            <span>Annonces</span>
        </a>
        <a href="promo_codes.php" class="flex items-center px-6 py-3 text-gray-300 hover:bg-pink-600 hover:text-white transition <?php echo basename($_SERVER['PHP_SELF']) == 'promo_codes.php' ? 'bg-pink-600 text-white border-l-4 border-pink-400' : ''; ?>">
            <i class="fas fa-tags w-6 text-pink-400"></i>
            <span>Codes Promo</span>
        </a>
        <a href="gestion_abos.php" class="flex items-center px-6 py-3 text-gray-300 hover:bg-purple-700 hover:text-white transition <?php echo basename($_SERVER['PHP_SELF']) == 'gestion_abos.php' ? 'bg-purple-700 text-white border-l-4 border-purple-400' : ''; ?>">
            <i class="fas fa-id-card w-6 text-purple-400"></i>
            <span>Gestion Abonnements</span>
            <?php if ($nbDemandesAbos > 0): ?>
                <span class="ml-2 inline-block bg-red-500 text-white text-xs px-2 py-1 rounded-full align-middle"><?php echo $nbDemandesAbos; ?></span>
            <?php endif; ?>
        </a>
        <a href="logs.php" class="flex items-center px-6 py-3 text-gray-300 hover:bg-cyan-600 hover:text-white transition <?php echo basename($_SERVER['PHP_SELF']) == 'logs.php' ? 'bg-cyan-600 text-white border-l-4 border-cyan-400' : ''; ?>">
            <i class="fas fa-history w-6 text-cyan-400"></i>
            <span>Logs d'activité</span>
        </a>
        <a href="manage_patchnotes.php" class="flex items-center px-6 py-3 text-gray-300 hover:bg-indigo-600 hover:text-white transition <?php echo basename($_SERVER['PHP_SELF']) == 'manage_patchnotes.php' ? 'bg-indigo-600 text-white border-l-4 border-indigo-400' : ''; ?>">
            <i class="fas fa-code-branch w-6 text-indigo-400"></i>
            <span>Gestion des Patches</span>
        </a>
        <a href="echanges.php" class="flex items-center px-6 py-3 text-gray-300 hover:bg-blue-600 hover:text-white transition <?php echo basename($_SERVER['PHP_SELF']) == 'echanges.php' ? 'bg-blue-600 text-white border-l-4 border-blue-400' : ''; ?>">
            <i class="fas fa-exchange-alt w-6 text-blue-400"></i>
            <span>Échanges</span>
            <?php if ($nbDemandesEchanges > 0): ?>
                <span class="ml-2 inline-block bg-red-500 text-white text-xs px-2 py-1 rounded-full align-middle"><?php echo $nbDemandesEchanges; ?></span>
            <?php endif; ?>
        </a>
        <a href="send_message.php" class="flex items-center px-6 py-3 text-gray-300 hover:bg-fuchsia-600 hover:text-white transition <?php echo basename($_SERVER['PHP_SELF']) == 'send_message.php' ? 'bg-fuchsia-600 text-white border-l-4 border-fuchsia-400' : ''; ?>">
            <i class="fas fa-envelope w-6 text-fuchsia-400"></i>
            <span>Message privé</span>
        </a>
        <a href="admin_images.php" class="flex items-center px-6 py-3 text-gray-300 hover:bg-purple-700 hover:text-white transition <?php echo basename($_SERVER['PHP_SELF']) == 'admin_images.php' ? 'bg-purple-700 text-white border-l-4 border-purple-400' : ''; ?>">
            <i class="fas fa-upload w-6 text-purple-400"></i>
            <span>Add image</span>
        </a>
        <a href="gestion_badges.php" class="flex items-center px-6 py-3 text-gray-300 hover:bg-yellow-700 hover:text-white transition <?php echo basename($_SERVER['PHP_SELF']) == 'gestion_badges.php' ? 'bg-yellow-700 text-white border-l-4 border-yellow-400' : ''; ?>">
            <i class="fas fa-medal w-6 text-yellow-400"></i>
            <span>Gestion Badges</span>
        </a>
        <!-- Séparateur visuel unique -->
        <hr class="border-t border-gray-700 my-2">
        <a href="../vendeur/index.php" class="flex items-center px-6 py-3 text-gray-300 hover:bg-teal-600 hover:text-white transition">
            <i class="fas fa-store w-6 text-teal-400"></i>
            <span>Espace Vendeur</span>
        </a>
        <a href="../index.php" class="flex items-center px-6 py-3 text-gray-300 hover:bg-red-600 hover:text-white transition">
            <i class="fas fa-arrow-left w-6 text-red-400"></i>
            <span>Retour au site</span>
        </a>
    </nav>
</aside>
<script>
    // Responsive sidebar toggle
    const sidebar = document.getElementById('adminSidebar');
    const toggleBtn = document.getElementById('sidebarToggle');

    function openSidebar() {
        sidebar.classList.remove('-translate-x-full');
        document.body.style.overflow = 'hidden';
    }
    function closeSidebar() {
        sidebar.classList.add('-translate-x-full');
        document.body.style.overflow = '';
    }

    toggleBtn.addEventListener('click', openSidebar);

    // Fermer la sidebar sur mobile en cliquant en dehors de la sidebar
    document.addEventListener('click', function(e) {
        if (window.innerWidth < 768) {
            // Si la sidebar est ouverte et qu'on clique en dehors
            if (!sidebar.contains(e.target) && !toggleBtn.contains(e.target)) {
                closeSidebar();
            }
        }
    });

    // Fermer la sidebar sur resize si on passe en desktop
    window.addEventListener('resize', function() {
        if (window.innerWidth >= 768) {
            closeSidebar();
        }
    });

    // Fermer avec ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeSidebar();
    });

    // S'assurer que la sidebar est fermée par défaut sur mobile au chargement
    window.addEventListener('DOMContentLoaded', function() {
        if (window.innerWidth < 768) {
            closeSidebar();
        }
    });
</script>
<style>
@media (max-width: 767px) {
    #adminSidebar {
        box-shadow: 0 0 0 9999px rgba(0,0,0,0.7);
        height: 100vh;
        min-height: 100vh;
        max-height: 100vh;
        top: 0;
        bottom: 0;
        position: fixed;
    }
}
@media (min-width: 768px) {
    #adminSidebar {
        position: static;
        height: auto;
        min-height: 0;
        max-height: none;
        box-shadow: none;
    }
}
</style>
