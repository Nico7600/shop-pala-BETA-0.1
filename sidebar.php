<!-- Sidebar -->
<aside id="sidebar" class="sidebar">
    <div class="sidebar-header">
        <h1 class="sidebar-title">
            <i class="fas fa-shield-alt"></i>CrazySouls Shop
        </h1>
    </div>
    
    <nav class="sidebar-nav">
        <a href="index.php" class="nav-link home-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
            <i class="fas fa-home"></i>
            <span>Accueil</span>
        </a>
        
        <a href="catalog.php" class="nav-link catalog-link <?php echo basename($_SERVER['PHP_SELF']) == 'catalog.php' ? 'active' : ''; ?>">
            <i class="fas fa-store"></i>
            <span>Catalogue</span>
        </a>
        
        <?php if(isset($_SESSION['user_id']) && $_SESSION['role'] === 'admin'): ?>
        <a href="admin/dashboard.php" class="nav-link admin-link">
            <i class="fas fa-crown"></i>
            <span>Staff</span>
        </a>
        <?php endif; ?>
        
        <a href="cart.php" class="nav-link cart-link <?php echo basename($_SERVER['PHP_SELF']) == 'cart.php' ? 'active' : ''; ?>">
            <i class="fas fa-shopping-cart"></i>
            <span>Panier</span>
            <span id="sidebarCartCount" class="cart-badge" style="display: none;">0</span>
        </a>
    </nav>
    
    <!-- Profile Section -->
    <div class="profile-section">
        <?php if(isset($_SESSION['user_id'])): ?>
            <div class="profile-toggle" id="profileToggle" onclick="toggleProfile()">
                <div class="profile-avatar">
                    <i class="fas fa-user"></i>
                </div>
                <div class="profile-info">
                    <div class="profile-name"><?php echo htmlspecialchars($_SESSION['username']); ?></div>
                    <div class="profile-role"><?php echo ucfirst($_SESSION['role']); ?></div>
                </div>
                <i class="fas fa-chevron-up profile-chevron"></i>
            </div>
            <div class="profile-menu" id="profileMenu">
                <a href="profile.php" class="profile-menu-item">
                    <i class="fas fa-user-circle"></i>
                    <span>Mon Profil</span>
                </a>
                <a href="orders.php" class="profile-menu-item">
                    <i class="fas fa-box"></i>
                    <span>Mes Commandes</span>
                </a>
                <a href="logout.php" class="profile-menu-item logout">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Déconnexion</span>
                </a>
            </div>
        <?php else: ?>
            <a href="login.php" class="profile-toggle" style="justify-content: center;">
                <i class="fas fa-sign-in-alt" style="margin-right: 0.5rem;"></i>
                <span>Connexion</span>
            </a>
        <?php endif; ?>
    </div>
</aside>

<!-- Overlay pour mobile -->
<div id="sidebarOverlay" class="sidebar-overlay" onclick="toggleSidebar()"></div>

<!-- Bouton toggle mobile -->
<button onclick="toggleSidebar()" class="toggle-btn lg:hidden fixed top-4 left-4 z-[1001] text-white p-3 rounded-lg">
    <i class="fas fa-bars text-lg"></i>
</button>

<script>
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        sidebar.classList.toggle('active');
        overlay.classList.toggle('active');
        document.body.style.overflow = overlay.classList.contains('active') ? 'hidden' : '';
    }
    
    function toggleProfile() {
        const toggle = document.getElementById('profileToggle');
        const menu = document.getElementById('profileMenu');
        toggle.classList.toggle('expanded');
        menu.classList.toggle('expanded');
    }
    
    // Mettre à jour le compteur du panier dans la sidebar
    function updateSidebarCartCount() {
        const cart = JSON.parse(localStorage.getItem('cart') || '[]');
        const count = cart.length;
        const badge = document.getElementById('sidebarCartCount');
        if (badge) {
            badge.textContent = count;
            badge.style.display = count > 0 ? 'inline-block' : 'none';
        }
    }
    
    // Fermer la sidebar en cliquant sur un lien (mobile)
    document.querySelectorAll('.nav-link, .profile-menu-item').forEach(link => {
        link.addEventListener('click', () => {
            if (window.innerWidth < 1024) {
                toggleSidebar();
            }
        });
    });
    
    window.addEventListener('load', updateSidebarCartCount);
    window.addEventListener('storage', updateSidebarCartCount);
</script>
