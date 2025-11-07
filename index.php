<?php
require_once 'config.php';

$announcements = [];
try {
    $announcements = $pdo->query("SELECT * FROM announcements 
                                  WHERE is_active = 1 
                                  AND show_in_banner = 1 
                                  ORDER BY created_at DESC 
                                  LIMIT 5")->fetchAll();
} catch(PDOException $e) {}

$active_promos = [];
try {
    $active_promos = $pdo->query("SELECT * FROM promo_codes 
                                  WHERE is_active = 1 
                                  AND show_in_banner = 1
                                  AND (expires_at IS NULL OR expires_at > NOW())
                                  AND (max_uses IS NULL OR current_uses < max_uses)
                                  LIMIT 5")->fetchAll();
} catch(PDOException $e) {}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CrazySouls Shop - Boutique Premium</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        .banner-slide {
            display: none;
        }
        .banner-slide.active {
            display: flex;
        }
    </style>
</head>
<body class="bg-gray-900 text-gray-100">
    <?php include 'includes/header.php'; ?>

    <!-- Bannière unifiée avec slider -->
    <?php if(!empty($announcements) || !empty($active_promos)): ?>
    <div class="bg-gray-800/50 border-b border-gray-700">
        <div class="container mx-auto px-4 py-3">
            <div class="flex flex-col gap-3">
                <!-- Contenu principal avec boutons de navigation -->
                <div class="flex items-center gap-3">
                    <!-- Bouton précédent -->
                    <button onclick="prevSlide()" class="flex-shrink-0 w-10 h-10 bg-gray-700 hover:bg-gray-600 rounded-lg flex items-center justify-center transition-colors">
                        <i class="fas fa-chevron-left text-gray-300"></i>
                    </button>

                    <!-- Container des slides -->
                    <div class="flex-1 overflow-hidden">
                        <div id="bannerSlider" class="relative">
                            <!-- Annonces -->
                            <?php 
                            $colors = [
                                'info' => ['bg' => 'from-blue-500/20 to-blue-600/20', 'border' => 'border-blue-500', 'text' => 'text-blue-400', 'icon' => 'fa-info-circle'],
                                'success' => ['bg' => 'from-green-500/20 to-green-600/20', 'border' => 'border-green-500', 'text' => 'text-green-400', 'icon' => 'fa-check-circle'],
                                'warning' => ['bg' => 'from-yellow-500/20 to-yellow-600/20', 'border' => 'border-yellow-500', 'text' => 'text-yellow-400', 'icon' => 'fa-exclamation-triangle'],
                                'danger' => ['bg' => 'from-red-500/20 to-red-600/20', 'border' => 'border-red-500', 'text' => 'text-red-400', 'icon' => 'fa-exclamation-circle']
                            ];
                            
                            foreach($announcements as $announcement): 
                                $color = $colors[$announcement['type']];
                            ?>
                            <div class="banner-slide items-start gap-4 bg-gradient-to-r <?php echo $color['bg']; ?> border-l-4 <?php echo $color['border']; ?> rounded-r-xl px-5 py-4">
                                <div class="flex-shrink-0">
                                    <div class="w-12 h-12 rounded-full bg-gray-800/80 flex items-center justify-center <?php echo $color['border']; ?> border-2">
                                        <i class="fas <?php echo $color['icon']; ?> <?php echo $color['text']; ?> text-2xl"></i>
                                    </div>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 mb-2">
                                        <i class="fas fa-bullhorn <?php echo $color['text']; ?> text-sm"></i>
                                        <h4 class="font-bold <?php echo $color['text']; ?> text-lg">
                                            <?php echo htmlspecialchars($announcement['title']); ?>
                                        </h4>
                                        <span class="px-2 py-0.5 bg-gray-800/50 <?php echo $color['text']; ?> text-xs rounded-full border <?php echo $color['border']; ?>">
                                            Annonce
                                        </span>
                                    </div>
                                    <p class="text-gray-300 leading-relaxed"><?php echo nl2br(htmlspecialchars($announcement['content'])); ?></p>
                                </div>
                            </div>
                            <?php endforeach; ?>

                            <!-- Codes promo -->
                            <?php foreach($active_promos as $promo): ?>
                            <div class="banner-slide items-center gap-4 bg-gradient-to-r from-purple-500/20 to-pink-500/20 border-l-4 border-purple-500 rounded-r-xl px-5 py-4">
                                <div class="flex-shrink-0">
                                    <div class="w-12 h-12 rounded-full bg-gradient-to-br from-purple-600 to-pink-600 flex items-center justify-center border-2 border-purple-400">
                                        <i class="fas fa-tags text-white text-2xl"></i>
                                    </div>
                                </div>
                                <div class="flex-1 flex items-center gap-4">
                                    <div>
                                        <div class="flex items-center gap-2 mb-1">
                                            <i class="fas fa-gift text-purple-400 text-sm"></i>
                                            <span class="text-xs text-purple-300 font-semibold">Code Promo</span>
                                        </div>
                                        <div class="flex items-center gap-3">
                                            <span class="font-mono font-black text-purple-400 text-2xl tracking-wider">
                                                <?php echo htmlspecialchars($promo['code']); ?>
                                            </span>
                                            <span class="font-bold text-green-400 text-lg">
                                                <?php if($promo['discount_type'] == 'percentage'): ?>
                                                    -<?php echo $promo['discount_value']; ?>%
                                                <?php else: ?>
                                                    -<?php echo number_format($promo['discount_value'], 2); ?>$
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                    </div>
                                    <button onclick="copyPromoCode('<?php echo $promo['code']; ?>')" 
                                        class="ml-auto bg-purple-600 hover:bg-purple-700 text-white px-6 py-2 rounded-lg font-bold transition-all flex items-center gap-2">
                                        <i class="fas fa-copy"></i>
                                        <span>Copier</span>
                                    </button>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Bouton suivant -->
                    <button onclick="nextSlide()" class="flex-shrink-0 w-10 h-10 bg-gray-700 hover:bg-gray-600 rounded-lg flex items-center justify-center transition-colors">
                        <i class="fas fa-chevron-right text-gray-300"></i>
                    </button>
                </div>

                <!-- Indicateurs de progression (en dessous) -->
                <div class="flex justify-center">
                    <div class="flex gap-2 bg-gray-800/80 px-4 py-2 rounded-full backdrop-blur-sm" id="indicators">
                        <!-- Générés par JavaScript -->
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Contenu principal -->
    <main class="container mx-auto px-4 py-8">
        <!-- Hero Section -->
        <section class="relative overflow-hidden">
            <div class="absolute inset-0 bg-gradient-to-br from-purple-900/50 via-gray-900 to-blue-900/50"></div>
            <div class="container mx-auto px-4 py-20 relative z-10">
                <div class="text-center max-w-4xl mx-auto">
                    <h2 class="text-5xl md:text-6xl font-bold mb-6 bg-gradient-to-r from-purple-400 via-pink-400 to-blue-400 bg-clip-text text-transparent">
                       
                    </h2>
                </div>
            </div>
            <!-- Particules décoratives -->
            <div class="absolute top-20 left-10 w-20 h-20 bg-purple-500/20 rounded-full blur-xl animate-pulse"></div>
            <div class="absolute bottom-20 right-10 w-32 h-32 bg-blue-500/20 rounded-full blur-xl animate-pulse delay-300"></div>
        </section>

        <!-- Catégories -->
        <section class="py-16 bg-gray-800/50">
            <div class="container mx-auto px-4">
                <h3 class="text-3xl font-bold text-center mb-12">
                    <i class="fas fa-layer-group mr-3 text-purple-500"></i>
                    Catégories Populaires
                </h3>
                <div class="grid grid-cols-2 md:grid-cols-5 gap-6">
                    <?php
                    $categories = $pdo->query("SELECT * FROM categories")->fetchAll();
                    foreach($categories as $cat):
                    ?>
                    <a href="catalog.php?category=<?php echo $cat['id']; ?>" 
                       class="bg-gray-800 hover:bg-gray-700 rounded-xl p-6 text-center transition duration-300 transform hover:scale-105 border-2 border-transparent hover:border-purple-500">
                        <div class="text-5xl mb-3"><?php echo $cat['icon']; ?></div>
                        <h4 class="font-bold text-lg"><?php echo htmlspecialchars($cat['name']); ?></h4>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <!-- Items en vedette -->
        <section id="featured" class="py-16">
            <div class="container mx-auto px-4">
                <div class="text-center mb-12">
                    <h3 class="text-4xl font-bold mb-4">
                        <i class="fas fa-info-circle mr-3 text-blue-500"></i>
                        À Propos du Shop
                    </h3>
                </div>
                
                <div class="max-w-4xl mx-auto">
                    <!-- Avertissement -->
                    <div class="bg-gradient-to-r from-red-500/20 to-orange-500/20 border-l-4 border-red-500 rounded-r-xl p-8 mb-8">
                        <div class="flex items-start gap-4">
                            <div class="flex-shrink-0">
                                <div class="w-16 h-16 rounded-full bg-red-500/20 flex items-center justify-center border-2 border-red-500">
                                    <i class="fas fa-exclamation-triangle text-red-400 text-3xl"></i>
                                </div>
                            </div>
                            <div>
                                <h4 class="text-2xl font-bold text-red-400 mb-3">
                                    <i class="fas fa-shield-alt mr-2"></i>Information Importante
                                </h4>
                                <p class="text-gray-300 text-lg leading-relaxed mb-3">
                                    Ce shop <strong class="text-white">n'est pas affilié</strong> à Paladium Groupe, Recube ou à son staff.
                                </p>
                                <p class="text-gray-300 text-lg leading-relaxed">
                                    Il s'agit d'une boutique <strong class="text-white">indépendante</strong> gérée par des joueurs.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Information sur le créateur -->
                    <div class="bg-gradient-to-r from-purple-500/20 to-blue-500/20 border-l-4 border-purple-500 rounded-r-xl p-8 mb-8">
                        <div class="flex items-start gap-4">
                            <div class="flex-shrink-0">
                                <div class="w-16 h-16 rounded-full bg-gradient-to-br from-purple-600 to-blue-600 flex items-center justify-center border-2 border-purple-400">
                                    <i class="fas fa-code text-white text-3xl"></i>
                                </div>
                            </div>
                            <div>
                                <h4 class="text-2xl font-bold text-purple-400 mb-3">
                                    <i class="fas fa-user-tie mr-2"></i>Créateur
                                </h4>
                                <p class="text-gray-300 text-lg leading-relaxed mb-2">
                                    Ce shop a été développé par <strong class="text-white text-xl">Nico7600</strong>
                                </p>
                                <p class="text-gray-400 text-base">
                                    <i class="fas fa-laptop-code mr-2"></i>Développeur et créateur de cette plateforme
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Information sur la faction -->
                    <div class="bg-gradient-to-r from-green-500/20 to-emerald-500/20 border-l-4 border-green-500 rounded-r-xl p-8">
                        <div class="flex items-start gap-4">
                            <div class="flex-shrink-0">
                                <div class="w-16 h-16 rounded-full bg-gradient-to-br from-green-600 to-emerald-600 flex items-center justify-center border-2 border-green-400">
                                    <i class="fas fa-flag text-white text-3xl"></i>
                                </div>
                            </div>
                            <div class="flex-1">
                                <h4 class="text-2xl font-bold text-green-400 mb-3">
                                    <i class="fas fa-users mr-2"></i>Faction Astoria
                                </h4>
                                <p class="text-gray-300 text-lg leading-relaxed mb-3">
                                    Cette boutique est le <strong class="text-white">shop officiel de la faction Astoria</strong>
                                </p>
                                <div class="flex items-center gap-3 text-gray-400">
                                    <span class="flex items-center gap-2">
                                        <i class="fas fa-shield-alt text-green-400"></i>
                                        <span>Shop de faction</span>
                                    </span>
                                    <span class="text-gray-600">•</span>
                                    <span class="flex items-center gap-2">
                                        <i class="fas fa-store text-green-400"></i>
                                        <span>Géré par les membres</span>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Bouton pour accéder au catalogue -->
                    <div class="text-center mt-12">
                        <a href="catalog.php" class="inline-block bg-gradient-to-r from-purple-600 to-blue-600 hover:from-purple-700 hover:to-blue-700 px-12 py-5 rounded-xl font-bold text-xl transition duration-300 transform hover:scale-105 shadow-lg">
                            <i class="fas fa-shopping-bag mr-3"></i>Découvrir le Catalogue
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <!-- Section Avantages -->
        <section class="py-16 bg-gray-800/50">
            <div class="container mx-auto px-4">
                <div class="grid md:grid-cols-3 gap-8">
                    <div class="text-center p-8 bg-gray-800 rounded-xl">
                        <i class="fas fa-shipping-fast text-5xl text-purple-500 mb-4"></i>
                        <h4 class="text-xl font-bold mb-3">Livraison Rapide</h4>
                        <p class="text-gray-400">Recevez vos items rapidement après l'achat</p>
                    </div>
                    <div class="text-center p-8 bg-gray-800 rounded-xl">
                        <i class="fas fa-shield-alt text-5xl text-green-500 mb-4"></i>
                        <h4 class="text-xl font-bold mb-3">Paiement Sécurisé</h4>
                        <p class="text-gray-400">Transactions 100% sécurisées par des vendeur de confiance</p>
                    </div>
                    <div class="text-center p-8 bg-gray-800 rounded-xl">
                        <i class="fas fa-headset text-5xl text-blue-500 mb-4"></i>
                        <h4 class="text-xl font-bold mb-3">Support 24/7</h4>
                        <p class="text-gray-400">Une équipe disponible pour vous aider</p>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <?php include 'includes/footer.php'; ?>

    <script>
        let currentSlide = 0;
        const slides = document.querySelectorAll('.banner-slide');
        const totalSlides = slides.length;

        // Créer les indicateurs
        const indicatorsContainer = document.getElementById('indicators');
        if(indicatorsContainer && totalSlides > 0) {
            for(let i = 0; i < totalSlides; i++) {
                const indicator = document.createElement('div');
                indicator.className = 'w-2 h-2 rounded-full bg-gray-600 cursor-pointer transition-all';
                indicator.onclick = () => goToSlide(i);
                indicatorsContainer.appendChild(indicator);
            }
        }

        function showSlide(index) {
            if(totalSlides === 0) return;
            
            slides.forEach(slide => slide.classList.remove('active'));
            slides[index].classList.add('active');
            
            // Mettre à jour les indicateurs
            const indicators = indicatorsContainer.children;
            for(let i = 0; i < indicators.length; i++) {
                if(i === index) {
                    indicators[i].className = 'w-8 h-2 rounded-full bg-purple-500 cursor-pointer transition-all';
                } else {
                    indicators[i].className = 'w-2 h-2 rounded-full bg-gray-600 cursor-pointer transition-all';
                }
            }
        }

        function nextSlide() {
            if(totalSlides === 0) return;
            currentSlide = (currentSlide + 1) % totalSlides;
            showSlide(currentSlide);
        }

        function prevSlide() {
            if(totalSlides === 0) return;
            currentSlide = (currentSlide - 1 + totalSlides) % totalSlides;
            showSlide(currentSlide);
        }

        function goToSlide(index) {
            currentSlide = index;
            showSlide(currentSlide);
        }

        function copyPromoCode(code) {
            navigator.clipboard.writeText(code).then(() => {
                const notification = document.createElement('div');
                notification.className = 'fixed top-24 right-4 bg-gradient-to-r from-green-500 to-green-600 text-white px-6 py-4 rounded-xl shadow-2xl z-50 flex items-center gap-3 border-2 border-green-400';
                notification.innerHTML = `
                    <div class="w-10 h-10 bg-white/20 rounded-full flex items-center justify-center">
                        <i class="fas fa-check-circle text-2xl"></i>
                    </div>
                    <div>
                        <p class="font-bold">Code copié !</p>
                        <p class="text-sm text-green-100">Code: <strong>${code}</strong></p>
                    </div>
                `;
                document.body.appendChild(notification);
                
                setTimeout(() => notification.remove(), 3000);
            });
        }

        // Démarrer le slider
        if(totalSlides > 0) {
            showSlide(0);
            
            // Auto-play seulement s'il y a plus d'une slide
            if(totalSlides > 1) {
                setInterval(nextSlide, 5000);
            }
        }

        // Fonction pour ajouter au panier
        function addToCart(productId) {
            // Vérifier si l'utilisateur est connecté
            <?php if(!isset($_SESSION['user_id'])): ?>
                window.location.href = 'login.php';
                return;
            <?php endif; ?>

            // Désactiver le bouton pendant la requête
            const buttons = document.querySelectorAll(`button[onclick*="addToCart(${productId})"]`);
            buttons.forEach(btn => {
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Ajout...';
            });

            // Utiliser fetch pour envoyer la requête
            fetch('add_to_cart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `product_id=${productId}&quantity=1`
            })
            .then(response => {
                // Vérifier que la réponse est bien du JSON
                if (!response.ok) {
                    throw new Error('Erreur serveur');
                }
                return response.json();
            })
            .then(data => {
                // Réactiver les boutons
                buttons.forEach(btn => {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-cart-plus mr-2"></i>Ajouter au Panier';
                });

                if(data.success) {
                    showNotification('success', 'Produit ajouté !', data.product_name || '');
                    
                    // Mettre à jour le compteur du panier
                    const cartCount = document.querySelector('.cart-count');
                    if(cartCount && data.cart_total) {
                        cartCount.textContent = data.cart_total;
                    }
                } else {
                    showNotification('error', 'Erreur', data.message || 'Impossible d\'ajouter le produit');
                }
            })
            .catch(error => {
                // Réactiver les boutons en cas d'erreur
                buttons.forEach(btn => {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-cart-plus mr-2"></i>Ajouter au Panier';
                });
                
                console.error('Erreur:', error);
                showNotification('error', 'Erreur', 'Une erreur est survenue lors de l\'ajout au panier');
            });
        }

        // Fonction pour afficher les notifications
        function showNotification(type, title, message) {
            const bgColor = type === 'success' ? 'from-green-500 to-green-600' : 'from-red-500 to-red-600';
            const borderColor = type === 'success' ? 'border-green-400' : 'border-red-400';
            const icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
            
            const notification = document.createElement('div');
            notification.className = `fixed top-24 right-4 bg-gradient-to-r ${bgColor} text-white px-6 py-4 rounded-xl shadow-2xl z-50 flex items-center gap-3 border-2 ${borderColor} animate-slideIn`;
            notification.innerHTML = `
                <div class="w-10 h-10 bg-white/20 rounded-full flex items-center justify-center">
                    <i class="fas ${icon} text-2xl"></i>
                </div>
                <div>
                    <p class="font-bold">${title}</p>
                    ${message ? `<p class="text-sm">${message}</p>` : ''}
                </div>
            `;
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.style.opacity = '0';
                notification.style.transform = 'translateX(100%)';
                setTimeout(() => notification.remove(), 300);
            }, 3000);
        }
    </script>
</body>
</html>
