<?php
// Déterminer si on est dans le dossier admin
$is_admin = strpos($_SERVER['PHP_SELF'], '/admin/') !== false;
$base_path = $is_admin ? '../' : '';

// Vérification du statut de bannissement
if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT is_banned FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $is_banned = $stmt->fetchColumn();
    if ($is_banned) {
        // Déconnexion et redirection
        session_destroy();
        header('Location: banned.php');
        exit;
    }
}
?>
<header class="bg-gradient-to-r from-gray-800 via-gray-900 to-gray-800 border-b-2 border-purple-500 sticky top-0 z-50 shadow-2xl backdrop-blur-sm">
    <div class="container mx-auto px-2 sm:px-4">
        <div class="flex flex-col lg:flex-row items-center justify-between py-2 sm:py-3 gap-2 sm:gap-0">
            <!-- Logo à gauche -->
            <a href="<?php echo $base_path; ?>index.php" class="flex items-center gap-2 group">
                <div class="relative">
                    <i class="fas fa-gem text-xl sm:text-2xl text-purple-500 group-hover:text-purple-400 transition-all duration-300 group-hover:rotate-12"></i>
                    <span class="absolute -top-1 -right-1 w-2 h-2 bg-green-500 rounded-full animate-ping"></span>
                </div>
                <div>
                    <h1 class="text-base sm:text-lg font-bold bg-gradient-to-r from-purple-400 to-pink-400 bg-clip-text text-transparent leading-tight">
                        CrazySouls Shop
                    </h1>
                    <p class="text-[9px] sm:text-[10px] text-gray-400 leading-tight">Boutique d'Items Premium</p>
                </div>
            </a>

            <!-- Navigation Desktop Centrée -->
            <nav class="hidden lg:flex items-center gap-4 sm:gap-8">
                <a href="<?php echo $base_path; ?>index.php" class="group flex items-center gap-2 text-gray-300 hover:text-purple-400 transition-all duration-300 relative px-4 py-2 rounded-lg hover:bg-gray-800/50">
                    <i class="fas fa-home text-lg group-hover:scale-110 transition-transform"></i>
                    <span class="font-semibold text-base">Accueil</span>
                </a>
                <a href="<?php echo $base_path; ?>catalog.php" class="group flex items-center gap-2 text-gray-300 hover:text-purple-400 transition-all duration-300 relative px-4 py-2 rounded-lg hover:bg-gray-800/50">
                    <i class="fas fa-store text-lg group-hover:scale-110 transition-transform"></i>
                    <span class="font-semibold text-base">Catalogue</span>
                </a>
                <a href="<?php echo $base_path; ?>patchnote.php" class="group flex items-center gap-2 text-gray-300 hover:text-purple-400 transition-all duration-300 relative px-4 py-2 rounded-lg hover:bg-gray-800/50">
                    <i class="fas fa-clipboard-list text-lg group-hover:scale-110 transition-transform"></i>
                    <span class="font-semibold text-base">Patch Notes</span>
                </a>
                <a href="<?php echo $base_path; ?>staff.php" class="group flex items-center gap-2 text-gray-300 hover:text-purple-400 transition-all duration-300 relative px-4 py-2 rounded-lg hover:bg-gray-800/50">
                    <i class="fas fa-users text-lg group-hover:scale-110 transition-transform"></i>
                    <span class="font-semibold text-base">Staff</span>
                </a>
                <!-- Bouton Échange ajouté -->
                <a href="<?php echo $base_path; ?>echange.php" class="group flex items-center gap-2 text-gray-300 hover:text-blue-400 transition-all duration-300 relative px-4 py-2 rounded-lg hover:bg-gray-800/50">
                    <i class="fas fa-exchange-alt text-lg group-hover:scale-110 transition-transform"></i>
                    <span class="font-semibold text-base">Échange</span>
                </a>
                <a href="<?php echo $base_path; ?>abonnements.php" class="group flex items-center gap-2 text-gray-300 hover:text-purple-400 transition-all duration-300 relative px-4 py-2 rounded-lg hover:bg-gray-800/50">
                    <i class="fas fa-crown text-lg group-hover:scale-110 transition-transform"></i>
                    <span class="font-semibold text-base">Abonnements</span>
                </a>
                
                <?php if(isset($_SESSION['user_id'])): ?>
                    <!-- Panier avec badge -->
                    <a href="<?php echo $base_path; ?>cart.php" class="group flex items-center gap-2 text-gray-300 hover:text-purple-400 transition-all duration-300 relative px-4 py-2 rounded-lg hover:bg-gray-800/50">
                        <div class="relative">
                            <i class="fas fa-shopping-cart text-lg group-hover:scale-110 transition-transform"></i>
                            <?php
                            try {
                                $cart_count = $pdo->prepare("SELECT SUM(quantity) FROM cart WHERE user_id = ?");
                                $cart_count->execute([$_SESSION['user_id']]);
                                $count = $cart_count->fetchColumn() ?? 0;
                            } catch(PDOException $e) {
                                $count = 0;
                            }
                            if($count > 0):
                            ?>
                            <span class="absolute -top-2 -right-2.5 bg-red-500 text-white text-xs font-bold rounded-full w-5 h-5 flex items-center justify-center animate-pulse">
                                <?php echo $count; ?>
                            </span>
                            <?php endif; ?>
                        </div>
                        <span class="font-semibold text-base">Panier</span>
                    </a>
                <?php endif; ?>
            </nav>

            <!-- Menu utilisateur à droite -->
            <div class="hidden lg:flex items-center gap-2 sm:gap-3">
                <?php if(isset($_SESSION['user_id'])): ?>
                    <?php
                    // Compter les notifications non lues (avec gestion d'erreur)
                    $unread_notif = 0;
                    try {
                        $notif_count = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = FALSE");
                        $notif_count->execute([$_SESSION['user_id']]);
                        $unread_notif = $notif_count->fetchColumn();
                    } catch(PDOException $e) {
                        // Table notifications n'existe pas encore
                        $unread_notif = 0;
                    }
                    ?>
                    <div class="relative group">
                        <button class="flex items-center gap-2.5 bg-gray-800/80 hover:bg-gray-700 px-3 py-2 rounded-lg border border-gray-700 hover:border-purple-500 transition-all duration-300">
                            <div class="w-9 h-9 bg-gradient-to-br from-purple-500 to-pink-500 rounded-full flex items-center justify-center font-bold text-sm shadow-lg relative">
                                <?php echo strtoupper(substr($_SESSION['username'], 0, 1)); ?>
                                <?php if($unread_notif > 0): ?>
                                <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs font-bold rounded-full w-5 h-5 flex items-center justify-center animate-pulse">
                                    <?php echo $unread_notif; ?>
                                </span>
                                <?php endif; ?>
                            </div>
                            <div class="text-left">
                                <p class="text-sm font-bold text-white leading-tight"><?php echo htmlspecialchars($_SESSION['username']); ?></p>
                                <div class="flex items-center gap-2 mt-1">
                                    <span class="px-2.5 py-1 bg-purple-500/20 text-purple-400 rounded-full text-xs font-bold">
                                        <i class="fas fa-award mr-1"></i><?php echo ucfirst(str_replace('_', ' ', $_SESSION['role'])); ?>
                                    </span>
                                </div>
                            </div>
                            <i class="fas fa-chevron-down text-gray-400 text-sm transition-transform group-hover:rotate-180"></i>
                        </button>
                        
                        <!-- Dropdown Menu -->
                        <div class="absolute right-0 mt-2 w-64 bg-gray-800 border border-gray-700 rounded-lg shadow-2xl opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-50">
                            <div class="p-2">
                                <a href="<?php echo $base_path; ?>notifications.php" class="flex items-center gap-3 px-3 py-2.5 hover:bg-gray-750 rounded-lg transition-colors group/item text-sm relative">
                                    <i class="fas fa-bell text-blue-400 text-base group-hover:item:scale-110 transition-transform"></i>
                                    <span>Notifications</span>
                                    <?php if($unread_notif > 0): ?>
                                    <span class="ml-auto bg-red-500 text-white text-xs font-bold rounded-full px-2 py-0.5"><?php echo $unread_notif; ?></span>
                                    <?php endif; ?>
                                </a>
                                <!-- Ajout du lien Profil -->
                                <a href="<?php echo $base_path; ?>profil.php" class="flex items-center gap-3 px-3 py-2.5 hover:bg-gray-750 rounded-lg transition-colors group/item text-sm">
                                    <i class="fas fa-user text-purple-300 text-base group-hover:item:scale-110 transition-transform"></i>
                                    <span>Profil</span>
                                </a>
                                <a href="<?php echo $base_path; ?>cart.php" class="flex items-center gap-3 px-3 py-2.5 hover:bg-gray-750 rounded-lg transition-colors group/item text-sm">
                                    <i class="fas fa-shopping-cart text-purple-400 text-base group-hover:item:scale-110 transition-transform"></i>
                                    <span>Mon Panier</span>
                                    <?php if($count > 0): ?>
                                    <span class="ml-auto bg-red-500 text-white text-xs font-bold rounded-full px-2 py-0.5"><?php echo $count; ?></span>
                                    <?php endif; ?>
                                </a>
                                <?php if(in_array($_SESSION['role'], ['vendeur_test', 'vendeur', 'vendeur_confirme', 'vendeur_senior', 'fondateur', 'resp_vendeur'])): ?>
                                <a href="<?php echo $base_path; ?>vendeur/index.php" class="flex items-center gap-3 px-3 py-2.5 hover:bg-gray-750 rounded-lg transition-colors group/item text-sm">
                                    <i class="fas fa-user-tie text-green-400 text-base group-hover:item:scale-110 transition-transform"></i>
                                    <span>Profil Vendeur</span>
                                </a>
                                <?php endif; ?>
                                <?php if(in_array($_SESSION['role'], ['fondateur', 'resp_vendeur'])): ?>
                                <a href="<?php echo $is_admin ? '' : 'admin/'; ?>index.php" class="flex items-center gap-3 px-3 py-2.5 hover:bg-gray-750 rounded-lg transition-colors group/item text-sm">
                                    <i class="fas fa-shield-alt text-yellow-400 text-base group-hover:item:scale-110 transition-transform"></i>
                                    <span>Panel Admin</span>
                                </a>
                                <?php endif; ?>
                            </div>
                            <div class="p-2 border-t border-gray-700">
                                <a href="<?php echo $base_path; ?>logout.php" class="flex items-center gap-3 px-3 py-2.5 hover:bg-red-900/20 text-red-400 rounded-lg transition-colors group/item text-sm">
                                    <i class="fas fa-sign-out-alt text-base group-hover/item:scale-110 transition-transform"></i>
                                    <span class="font-medium">Déconnexion</span>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <a href="<?php echo $base_path; ?>login.php" class="bg-gradient-to-r from-purple-600 to-pink-600 hover:from-purple-700 hover:to-pink-700 px-5 py-2.5 rounded-lg transition-all duration-300 font-bold transform hover:scale-105 shadow-lg hover:shadow-purple-500/50 text-sm flex items-center gap-2">
                        <i class="fas fa-sign-in-alt"></i>
                        <span>Connexion</span>
                    </a>
                <?php endif; ?>
            </div>

            <!-- Mobile menu button -->
            <button class="lg:hidden text-lg sm:text-xl text-purple-400 hover:text-purple-300 transition-colors p-2 hover:bg-gray-800 rounded-lg ml-auto" onclick="toggleMobileMenu()">
                <i class="fas fa-bars" id="menuIcon"></i>
            </button>
        </div>
    </div>

    <!-- Mobile menu -->
    <div id="mobileMenu" class="hidden lg:hidden bg-gray-800 border-t border-gray-700 shadow-xl">
        <nav class="container mx-auto px-2 sm:px-4 py-2 sm:py-3 flex flex-col gap-1">
            <a href="<?php echo $base_path; ?>index.php" class="flex items-center gap-3 text-gray-300 hover:text-purple-400 hover:bg-gray-750 px-3 py-2.5 rounded-lg transition-all">
                <i class="fas fa-home text-lg w-5"></i>
                <span class="font-medium text-base">Accueil</span>
            </a>
            <a href="<?php echo $base_path; ?>catalog.php" class="flex items-center gap-3 text-gray-300 hover:text-purple-400 hover:bg-gray-750 px-3 py-2.5 rounded-lg transition-all">
                <i class="fas fa-store text-lg w-5"></i>
                <span class="font-medium text-base">Catalogue</span>
            </a>
            <a href="<?php echo $base_path; ?>staff.php" class="flex items-center gap-3 text-gray-300 hover:text-purple-400 hover:bg-gray-750 px-3 py-2.5 rounded-lg transition-all">
                <i class="fas fa-users text-lg w-5"></i>
                <span class="font-medium text-base">Staff</span>
            </a>
            <!-- Bouton Échange ajouté -->
            <a href="<?php echo $base_path; ?>echange.php" class="flex items-center gap-3 text-blue-400 hover:bg-gray-750 px-3 py-2.5 rounded-lg transition-all">
                <i class="fas fa-exchange-alt text-lg w-5"></i>
                <span class="font-medium text-base">Échange</span>
            </a>
            <a href="<?php echo $base_path; ?>patchnote.php" class="flex items-center gap-3 text-gray-300 hover:text-purple-400 hover:bg-gray-750 px-3 py-2.5 rounded-lg transition-all">
                <i class="fas fa-clipboard-list text-lg w-5"></i>
                <span class="font-medium text-base">Patch Notes</span>
            </a>
            <a href="<?php echo $base_path; ?>abonnements.php" class="flex items-center gap-3 text-yellow-400 hover:bg-gray-750 px-3 py-2.5 rounded-lg transition-all">
                <i class="fas fa-crown text-lg w-5"></i>
                <span class="font-medium text-base">Abonnements</span>
            </a>
            
            <?php if(isset($_SESSION['user_id'])): ?>
                <div class="border-t border-gray-700 my-2 pt-2">
                    <div class="flex items-center gap-3 p-3 bg-gray-750 rounded-lg mb-2">
                        <div class="w-10 h-10 bg-gradient-to-br from-purple-500 to-pink-500 rounded-full flex items-center justify-center font-bold shadow-lg">
                            <?php echo strtoupper(substr($_SESSION['username'], 0, 1)); ?>
                        </div>
                        <div>
                            <p class="font-bold text-white text-base leading-tight"><?php echo htmlspecialchars($_SESSION['username']); ?></p>
                            <p class="text-sm text-purple-400 leading-tight"><?php echo ucfirst(str_replace('_', ' ', $_SESSION['role'])); ?></p>
                        </div>
                    </div>
                </div>
                <a href="<?php echo $base_path; ?>cart.php" class="flex items-center gap-3 text-gray-300 hover:text-purple-400 hover:bg-gray-750 px-3 py-2.5 rounded-lg transition-all">
                    <i class="fas fa-shopping-cart text-lg w-5"></i>
                    <span class="font-medium text-base">Panier</span>
                    <?php if($count > 0): ?>
                    <span class="ml-auto bg-red-500 text-white text-xs font-bold rounded-full px-2 py-1"><?php echo $count; ?></span>
                    <?php endif; ?>
                </a>
                <!-- Ajout du lien Profil juste avant Mon Panier -->
                <a href="<?php echo $base_path; ?>profil.php" class="flex items-center gap-3 text-purple-300 hover:bg-gray-750 px-3 py-2.5 rounded-lg transition-all">
                    <i class="fas fa-user text-lg w-5"></i>
                    <span class="font-medium text-base">Profil</span>
                </a>
                <?php if(in_array($_SESSION['role'], ['vendeur_test', 'vendeur', 'vendeur_confirme', 'vendeur_senior', 'fondateur', 'resp_vendeur'])): ?>
                <a href="<?php echo $base_path; ?>vendeur/index.php" class="flex items-center gap-3 text-green-400 hover:bg-gray-750 px-3 py-2.5 rounded-lg transition-all">
                    <i class="fas fa-user-tie text-lg w-5"></i>
                    <span class="font-medium text-base">Profil Vendeur</span>
                </a>
                <?php endif; ?>
                <?php if(in_array($_SESSION['role'], ['fondateur', 'resp_vendeur'])): ?>
                <a href="<?php echo $is_admin ? '' : 'admin/'; ?>index.php" class="flex items-center gap-3 text-yellow-400 hover:bg-gray-750 px-3 py-2.5 rounded-lg transition-all">
                    <i class="fas fa-shield-alt text-lg w-5"></i>
                    <span class="font-medium text-base">Panel Admin</span>
                </a>
                <?php endif; ?>
                <a href="<?php echo $base_path; ?>logout.php" class="flex items-center gap-3 text-red-400 hover:bg-red-900/20 px-3 py-2.5 rounded-lg transition-all mt-2">
                    <i class="fas fa-sign-out-alt text-lg w-5"></i>
                    <span class="font-medium text-base">Déconnexion</span>
                </a>
            <?php else: ?>
                <a href="<?php echo $base_path; ?>login.php" class="bg-gradient-to-r from-purple-600 to-pink-600 text-center text-white font-bold py-3 rounded-lg mt-2 hover:from-purple-700 hover:to-pink-700 transition-all text-base flex items-center justify-center gap-2">
                    <i class="fas fa-sign-in-alt"></i>
                    <span>Connexion</span>
                </a>
            <?php endif; ?>
        </nav>
    </div>
</header>

<script>
function toggleMobileMenu() {
    const menu = document.getElementById('mobileMenu');
    const icon = document.getElementById('menuIcon');
    menu.classList.toggle('hidden');
    icon.classList.toggle('fa-bars');
    icon.classList.toggle('fa-times');
}

// Fermer le menu mobile lors du clic en dehors
document.addEventListener('click', function(event) {
    const menu = document.getElementById('mobileMenu');
    const menuButton = event.target.closest('button[onclick="toggleMobileMenu()"]');
    
    if (!menuButton && !menu.contains(event.target) && !menu.classList.contains('hidden')) {
        toggleMobileMenu();
    }
});
</script>
