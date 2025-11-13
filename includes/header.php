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
<?php
$count = 0;
if(isset($_SESSION['user_id'])) {
    try {
        $cart_count = $pdo->prepare("SELECT SUM(quantity) FROM cart WHERE user_id = ?");
        $cart_count->execute([$_SESSION['user_id']]);
        $count = $cart_count->fetchColumn() ?? 0;
    } catch(PDOException $e) {
        $count = 0;
    }
}
?>
<header class="bg-gray-900 border-b-2 border-gray-700 sticky top-0 z-50 shadow-xl backdrop-blur-xl rounded-b-2xl">
    <div class="container mx-auto px-3">
        <div class="flex items-center justify-between py-2">
            <!-- Logo -->
            <a href="<?php echo $base_path; ?>index.php" class="flex items-center gap-2 group">
                <i class="fas fa-gem text-xl text-purple-500 group-hover:text-purple-400 transition"></i>
                <span class="font-bold text-base bg-gradient-to-r from-purple-400 to-pink-400 bg-clip-text text-transparent">CrazySouls Shop</span>
            </a>
            <!-- Bouton menu mobile -->
            <button class="lg:hidden text-xl text-purple-400 hover:text-purple-300 p-2 rounded-lg" onclick="toggleMobileMenu()">
                <i class="fas fa-bars" id="menuIcon"></i>
            </button>
            <!-- Profil mobile -->
            <div class="flex lg:hidden items-center gap-2">
                <?php if(isset($_SESSION['user_id'])): ?>
                    <a href="<?php echo $base_path; ?>profil.php" class="w-9 h-9 bg-gray-800 rounded-full flex items-center justify-center font-bold text-white">
                        <?php echo strtoupper(substr($_SESSION['username'], 0, 1)); ?>
                    </a>
                <?php else: ?>
                    <a href="<?php echo $base_path; ?>login.php" class="bg-gray-800 px-3 py-2 rounded-lg font-bold text-sm flex items-center gap-2">
                        <i class="fas fa-sign-in-alt"></i>
                    </a>
                <?php endif; ?>
            </div>
            <!-- Navigation desktop -->
            <nav class="hidden lg:flex items-center gap-6">
                <a href="<?php echo $base_path; ?>index.php" class="text-purple-400 hover:text-purple-300 px-3 py-2 rounded-lg transition flex items-center gap-2">
                    <i class="fas fa-home"></i>
                    <span class="font-semibold">Accueil</span>
                </a>
                <a href="<?php echo $base_path; ?>catalog.php" class="text-purple-400 hover:text-purple-300 px-3 py-2 rounded-lg transition flex items-center gap-2">
                    <i class="fas fa-store"></i>
                    <span class="font-semibold">Catalogue</span>
                </a>
                <a href="<?php echo $base_path; ?>patchnote.php" class="text-purple-400 hover:text-purple-300 px-3 py-2 rounded-lg transition flex items-center gap-2">
                    <i class="fas fa-clipboard-list"></i>
                    <span class="font-semibold">Patch Notes</span>
                </a>
                <a href="<?php echo $base_path; ?>staff.php" class="text-purple-400 hover:text-purple-300 px-3 py-2 rounded-lg transition flex items-center gap-2">
                    <i class="fas fa-users"></i>
                    <span class="font-semibold">Staff</span>
                </a>
                <a href="<?php echo $base_path; ?>echange.php" class="text-blue-400 hover:text-blue-300 px-3 py-2 rounded-lg transition flex items-center gap-2">
                    <i class="fas fa-exchange-alt"></i>
                    <span class="font-semibold">Échange</span>
                </a>
                <a href="<?php echo $base_path; ?>abonnements.php" class="text-yellow-400 hover:text-yellow-300 px-3 py-2 rounded-lg transition flex items-center gap-2">
                    <i class="fas fa-crown"></i>
                    <span class="font-semibold">Abonnements</span>
                </a>
                <?php if(isset($_SESSION['user_id'])): ?>
                    <a href="<?php echo $base_path; ?>cart.php" class="text-pink-400 hover:text-pink-300 px-3 py-2 rounded-lg transition relative flex items-center gap-2">
                        <i class="fas fa-shopping-cart"></i>
                        <span class="font-semibold">Panier</span>
                        <?php if($count > 0): ?>
                        <span class="absolute -top-2 -right-2 bg-red-500 text-white text-xs font-bold rounded-full w-5 h-5 flex items-center justify-center"><?php echo $count; ?></span>
                        <?php endif; ?>
                    </a>
                <?php endif; ?>
            </nav>
            <!-- Profil desktop -->
            <div class="hidden lg:flex items-center gap-2">
                <?php if(isset($_SESSION['user_id'])): ?>
                    <?php
                    $unread_notif = 0;
                    try {
                        $notif_count = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = FALSE");
                        $notif_count->execute([$_SESSION['user_id']]);
                        $unread_notif = $notif_count->fetchColumn();
                    } catch(PDOException $e) {
                        $unread_notif = 0;
                    }
                    ?>
                    <div class="relative group">
                        <button class="flex items-center gap-2 px-3 py-2 rounded-lg bg-gray-800 text-white font-bold">
                            <span class="w-9 h-9 bg-gray-700 rounded-full flex items-center justify-center font-bold text-white"><?php echo strtoupper(substr($_SESSION['username'], 0, 1)); ?></span>
                            <span><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                            <i class="fas fa-chevron-down text-gray-400 text-sm"></i>
                        </button>
                        <!-- Dropdown -->
                        <div class="absolute right-0 top-full mt-2 w-64 bg-gray-900 border border-gray-700 rounded-xl shadow-2xl z-50 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200">
                            <div class="flex items-center gap-3 px-5 py-4 border-b border-gray-800">
                                <div class="w-10 h-10 rounded-full bg-gray-800 flex items-center justify-center font-bold text-white text-lg">
                                    <?php echo strtoupper(substr($_SESSION['username'], 0, 1)); ?>
                                </div>
                                <div>
                                    <div class="font-bold text-white text-base"><?php echo htmlspecialchars($_SESSION['username']); ?></div>
                                    <div class="text-xs text-purple-400 font-semibold"><?php echo ucfirst(str_replace('_', ' ', $_SESSION['role'])); ?></div>
                                </div>
                            </div>
                            <div class="flex flex-col py-2">
                                <a href="<?php echo $base_path; ?>notifications.php" class="flex items-center gap-3 px-5 py-3 hover:bg-gray-800 rounded-lg text-sm text-blue-400">
                                    <i class="fas fa-bell text-base"></i>
                                    <span>Notifications</span>
                                    <?php if($unread_notif > 0): ?>
                                    <span class="ml-auto bg-red-500 text-white text-xs font-bold rounded-full px-2 py-0.5"><?php echo $unread_notif; ?></span>
                                    <?php endif; ?>
                                </a>
                                <a href="<?php echo $base_path; ?>profil.php" class="flex items-center gap-3 px-5 py-3 hover:bg-gray-800 rounded-lg text-sm text-purple-400">
                                    <i class="fas fa-user text-base"></i>
                                    <span>Profil</span>
                                </a>
                                <a href="<?php echo $base_path; ?>cart.php" class="flex items-center gap-3 px-5 py-3 hover:bg-gray-800 rounded-lg text-sm text-pink-400">
                                    <i class="fas fa-shopping-cart text-base"></i>
                                    <span>Mon Panier</span>
                                    <?php if($count > 0): ?>
                                    <span class="ml-auto bg-red-500 text-white text-xs font-bold rounded-full px-2 py-0.5"><?php echo $count; ?></span>
                                    <?php endif; ?>
                                </a>
                                <?php if(in_array($_SESSION['role'], ['vendeur_test', 'vendeur', 'vendeur_confirme', 'vendeur_senior', 'fondateur', 'resp_vendeur'])): ?>
                                <a href="<?php echo $base_path; ?>vendeur/index.php" class="flex items-center gap-3 px-5 py-3 hover:bg-gray-800 rounded-lg text-sm text-green-400">
                                    <i class="fas fa-user-tie text-base"></i>
                                    <span>Profil Vendeur</span>
                                </a>
                                <?php endif; ?>
                                <?php if(in_array($_SESSION['role'], ['fondateur', 'partenaire'])): ?>
                                <a href="<?php echo $base_path; ?>partenaire.php" class="flex items-center gap-3 px-5 py-3 hover:bg-gray-800 rounded-lg text-sm text-cyan-400">
                                    <i class="fas fa-handshake text-base"></i>
                                    <span>Partenaire</span>
                                </a>
                                <?php endif; ?>
                                <?php if(in_array($_SESSION['role'], ['fondateur', 'resp_vendeur'])): ?>
                                <a href="<?php echo $is_admin ? '' : 'admin/'; ?>index.php" class="flex items-center gap-3 px-5 py-3 hover:bg-gray-800 rounded-lg text-sm text-yellow-400">
                                    <i class="fas fa-shield-alt text-base"></i>
                                    <span>Panel Admin</span>
                                </a>
                                <?php endif; ?>
                            </div>
                            <div class="border-t border-gray-800"></div>
                            <div class="flex flex-col py-2">
                                <a href="<?php echo $base_path; ?>logout.php" class="flex items-center gap-3 px-5 py-3 hover:bg-red-900/20 rounded-lg text-sm text-red-400 font-bold">
                                    <i class="fas fa-sign-out-alt text-base"></i>
                                    <span>Déconnexion</span>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <a href="<?php echo $base_path; ?>login.php" class="bg-gray-800 px-5 py-2.5 rounded-lg font-bold text-sm flex items-center gap-2">
                        <i class="fas fa-sign-in-alt"></i>
                        <span>Connexion</span>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <!-- Menu mobile latéral -->
    <div id="mobileMenuOverlay" class="fixed inset-0 bg-black bg-opacity-40 z-40 hidden transition-opacity duration-200"></div>
    <div id="mobileMenu" class="fixed top-0 right-0 w-full max-w-xs h-full bg-gray-800 border-l border-gray-700 shadow-xl z-50 hidden transition-transform duration-300 transform translate-x-full lg:hidden rounded-l-xl overflow-y-auto">
        <nav class="px-4 py-4 flex flex-col gap-1">
            <a href="<?php echo $base_path; ?>index.php" class="flex items-center gap-3 text-purple-400 hover:text-purple-300 hover:bg-gray-800 px-3 py-2 rounded-lg transition-all">
                <i class="fas fa-home"></i>
                <span class="font-semibold text-base">Accueil</span>
            </a>
            <a href="<?php echo $base_path; ?>catalog.php" class="flex items-center gap-3 text-purple-400 hover:text-purple-300 hover:bg-gray-800 px-3 py-2 rounded-lg transition-all">
                <i class="fas fa-store"></i>
                <span class="font-semibold text-base">Catalogue</span>
            </a>
            <a href="<?php echo $base_path; ?>patchnote.php" class="flex items-center gap-3 text-purple-400 hover:text-purple-300 hover:bg-gray-800 px-3 py-2 rounded-lg transition-all">
                <i class="fas fa-clipboard-list"></i>
                <span class="font-semibold text-base">Patch Notes</span>
            </a>
            <a href="<?php echo $base_path; ?>staff.php" class="flex items-center gap-3 text-purple-400 hover:text-purple-300 hover:bg-gray-800 px-3 py-2 rounded-lg transition-all">
                <i class="fas fa-users"></i>
                <span class="font-semibold text-base">Staff</span>
            </a>
            <a href="<?php echo $base_path; ?>echange.php" class="flex items-center gap-3 text-blue-400 hover:text-blue-300 hover:bg-gray-800 px-3 py-2 rounded-lg transition-all">
                <i class="fas fa-exchange-alt"></i>
                <span class="font-semibold text-base">Échange</span>
            </a>
            <a href="<?php echo $base_path; ?>abonnements.php" class="flex items-center gap-3 text-yellow-400 hover:text-yellow-300 hover:bg-gray-800 px-3 py-2 rounded-lg transition-all">
                <i class="fas fa-crown"></i>
                <span class="font-semibold text-base">Abonnements</span>
            </a>
            <?php if(isset($_SESSION['user_id'])): ?>
                <a href="<?php echo $base_path; ?>cart.php" class="flex items-center gap-3 text-pink-400 hover:text-pink-300 hover:bg-gray-800 px-3 py-2 rounded-lg transition-all relative">
                    <i class="fas fa-shopping-cart"></i>
                    <span class="font-semibold text-base">Panier</span>
                    <?php if($count > 0): ?>
                    <span class="ml-auto bg-red-500 text-white text-xs font-bold rounded-full px-2 py-1"><?php echo $count; ?></span>
                    <?php endif; ?>
                </a>
                <a href="<?php echo $base_path; ?>profil.php" class="flex items-center gap-3 text-purple-400 hover:bg-gray-800 px-3 py-2 rounded-lg transition-all">
                    <i class="fas fa-user"></i>
                    <span class="font-semibold text-base">Profil</span>
                </a>
                <a href="<?php echo $base_path; ?>notifications.php" class="flex items-center gap-3 text-blue-400 hover:bg-gray-800 px-3 py-2 rounded-lg transition-all">
                    <i class="fas fa-bell"></i>
                    <span class="font-semibold text-base">Notifications</span>
                    <?php if($unread_notiv > 0): ?>
                    <span class="ml-auto bg-red-500 text-white text-xs font-bold rounded-full px-2 py-1"><?php echo $unread_notiv; ?></span>
                    <?php endif; ?>
                </a>
                <?php if(in_array($_SESSION['role'], ['vendeur_test', 'vendeur', 'vendeur_confirme', 'vendeur_senior', 'fondateur', 'resp_vendeur'])): ?>
                <a href="<?php echo $base_path; ?>vendeur/index.php" class="flex items-center gap-3 text-green-400 hover:bg-gray-800 px-3 py-2 rounded-lg transition-all">
                    <i class="fas fa-user-tie"></i>
                    <span class="font-semibold text-base">Profil Vendeur</span>
                </a>
                <?php endif; ?>
                <?php if(in_array($_SESSION['role'], ['fondateur', 'partenaire'])): ?>
                <a href="<?php echo $base_path; ?>partenaire.php" class="flex items-center gap-3 text-cyan-400 hover:bg-gray-800 px-3 py-2 rounded-lg transition-all">
                    <i class="fas fa-handshake"></i>
                    <span class="font-semibold text-base">Partenaire</span>
                </a>
                <?php endif; ?>
                <?php if(in_array($_SESSION['role'], ['fondateur', 'resp_vendeur'])): ?>
                <a href="<?php echo $is_admin ? '' : 'admin/'; ?>index.php" class="flex items-center gap-3 text-yellow-400 hover:bg-gray-800 px-3 py-2 rounded-lg transition-all">
                    <i class="fas fa-shield-alt"></i>
                    <span class="font-semibold text-base">Panel Admin</span>
                </a>
                <?php endif; ?>
                <a href="<?php echo $base_path; ?>logout.php" class="flex items-center gap-3 text-red-400 hover:bg-red-900/20 px-3 py-2 rounded-lg transition-all mt-2">
                    <i class="fas fa-sign-out-alt"></i>
                    <span class="font-semibold text-base">Déconnexion</span>
                </a>
            <?php else: ?>
                <a href="<?php echo $base_path; ?>login.php" class="bg-gray-800 text-center text-white font-bold py-3 rounded-lg mt-2 hover:bg-gray-700 transition-all text-base flex items-center justify-center gap-2">
                    <i class="fas fa-sign-in-alt"></i>
                    <span>Connexion</span>
                </a>
            <?php endif; ?>
        </nav>
    </div>
</header>
<script>
function toggleMobileMenu(forceClose = false) {
    const menu = document.getElementById('mobileMenu');
    const overlay = document.getElementById('mobileMenuOverlay');
    const isOpen = !menu.classList.contains('translate-x-full');

    if (forceClose || isOpen) {
        menu.classList.add('translate-x-full');
        overlay.classList.add('hidden');
        document.body.style.overflow = ''; // Réactive le défilement
    } else {
        menu.classList.remove('translate-x-full');
        overlay.classList.remove('hidden');
        document.body.style.overflow = 'hidden'; // Désactive le défilement
    }
}

// Ferme le menu mobile si l'utilisateur clique en dehors
document.getElementById('mobileMenuOverlay').addEventListener('click', () => {
    toggleMobileMenu(true);
});

// Gère l'ouverture/fermeture du menu mobile avec le bouton
document.querySelector('button[onclick="toggleMobileMenu()"]').addEventListener('click', () => {
    toggleMobileMenu();
});

// Ferme le menu mobile si l'utilisateur redimensionne la fenêtre en mode desktop
window.addEventListener('resize', () => {
    if (window.innerWidth >= 1024) {
        document.getElementById('mobileMenu').classList.add('translate-x-full');
        document.getElementById('mobileMenuOverlay').classList.add('hidden');
        document.body.style.overflow = ''; // Réactive le défilement
    }
});
</script>
