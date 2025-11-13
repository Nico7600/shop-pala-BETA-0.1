<?php
require_once 'config.php';

$announcements = [];
try {
    $announcements = $pdo->query("SELECT * FROM announcements 
                                  WHERE is_active = 1 
                                  ORDER BY created_at DESC 
                                  LIMIT 5")->fetchAll();
} catch(PDOException $e) {
    echo '<div style="color:red;">Erreur SQL annonces : ' . htmlspecialchars($e->getMessage()) . '</div>';
}

$active_promos = [];
try {
    $active_promos = $pdo->query("SELECT * FROM promo_codes 
                                  WHERE is_active = 1 
                                  AND show_in_banner = 1
                                  AND (expires_at IS NULL OR expires_at > NOW())
                                  AND (max_uses IS NULL OR current_uses < max_uses)
                                  LIMIT 5")->fetchAll();
} catch(PDOException $e) {
    echo '<div style="color:red;">Erreur SQL promos : ' . htmlspecialchars($e->getMessage()) . '</div>';
}

// Fusionner les codes promo dans les annonces
foreach($active_promos as $promo) {
    // Afficher tous les codes promo, même "TEST"
    $announcements[] = [
        'type' => 'success',
        'title' => 'Code Promo : ' . $promo['code'],
        'content' => ($promo['discount_type'] == 'percentage'
            ? "-{$promo['discount_value']}%"
            : "-".number_format($promo['discount_value'], 2)."€")
            . " | Utilisez ce code lors de votre achat !"
            . (isset($promo['description']) && $promo['description'] ? "\n" . $promo['description'] : ''),
        'min_purchase' => isset($promo['min_purchase']) ? $promo['min_purchase'] : null
    ];
}

// Récupérer les utilisateurs en ligne (présents depuis moins de 5 minutes)
$online_users = [];
try {
    $online_users = $pdo->query(
        "SELECT username, role, minecraft_username 
         FROM users 
         WHERE last_activity >= (NOW() - INTERVAL 5 MINUTE)"
    )->fetchAll();
} catch(PDOException $e) {
    echo '<div style="color:red;">Erreur SQL utilisateurs en ligne : ' . htmlspecialchars($e->getMessage()) . '</div>';
}

// Mapping des rôles pour l'affichage
$roles = [
    'fondateur' => ['label' => 'FONDATEUR', 'color' => 'from-red-500 to-red-700', 'bg' => 'bg-red-500/10', 'icon' => 'fa-crown', 'border' => 'border-red-500'],
    'admin' => ['label' => 'ADMIN', 'color' => 'from-orange-500 to-orange-700', 'bg' => 'bg-orange-500/10', 'icon' => 'fa-shield-alt', 'border' => 'border-orange-500'],
    'resp_vendeur' => ['label' => 'RESPONSABLE VENDEUR', 'color' => 'from-yellow-500 to-yellow-700', 'bg' => 'bg-yellow-500/10', 'icon' => 'fa-user-tie', 'border' => 'border-yellow-500'],
    'vendeur_senior' => ['label' => 'VENDEUR SENIOR', 'color' => 'from-green-500 to-green-700', 'bg' => 'bg-green-500/10', 'icon' => 'fa-star', 'border' => 'border-green-500'],
    'vendeur_confirme' => ['label' => 'VENDEUR CONFIRMÉ', 'color' => 'from-blue-500 to-blue-700', 'bg' => 'bg-blue-500/10', 'icon' => 'fa-check-circle', 'border' => 'border-blue-500'],
    'vendeur' => ['label' => 'VENDEUR', 'color' => 'from-indigo-500 to-indigo-700', 'bg' => 'bg-indigo-500/10', 'icon' => 'fa-shopping-bag', 'border' => 'border-indigo-500'],
    'vendeur_test' => ['label' => 'VENDEUR TEST', 'color' => 'from-gray-500 to-gray-700', 'bg' => 'bg-gray-500/10', 'icon' => 'fa-flask', 'border' => 'border-gray-500'],
    'partenaire' => ['label' => 'PARTENAIRE', 'color' => 'from-pink-500 to-pink-700', 'bg' => 'bg-pink-500/10', 'icon' => 'fa-handshake', 'border' => 'border-pink-500'],
];

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
        .announcement-card {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        /* Animation cadeau : shake au hover */
        .promo-gift-anim {
            transition: transform 0.2s;
        }
        .promo-gift-circle:hover .promo-gift-anim {
            animation: shakeGift 0.5s;
        }
        @keyframes shakeGift {
            0% { transform: rotate(0deg);}
            20% { transform: rotate(-15deg);}
            40% { transform: rotate(15deg);}
            60% { transform: rotate(-10deg);}
            80% { transform: rotate(10deg);}
            100% { transform: rotate(0deg);}
        }
        /* Confetti style */
        .confetti {
            position: absolute;
            pointer-events: none;
            z-index: 50;
            width: 8px;
            height: 16px;
            border-radius: 2px;
            opacity: 0.85;
            animation: confetti-fall 1.1s linear forwards;
        }
        @keyframes confetti-fall {
            to {
                opacity: 0;
            }
        }
    </style>
</head>
<body class="bg-gray-900 text-gray-100">
    <?php include 'includes/header.php'; ?>

    <!-- Section annonces (tout en haut, juste après le header) -->
    <?php if(!empty($announcements)): ?>
    <div class="container mx-auto px-2 sm:px-4 py-4">
        <div class="flex flex-col gap-4">
        <?php 
        $colors = [
            'info' => ['bg' => 'from-blue-500/20 to-blue-600/20', 'border' => 'border-blue-500', 'text' => 'text-blue-400', 'icon' => 'fa-info-circle'],
            'success' => ['bg' => 'from-green-500/20 to-green-600/20', 'border' => 'border-green-500', 'text' => 'text-green-400', 'icon' => 'fa-check-circle'],
            'warning' => ['bg' => 'from-yellow-500/20 to-yellow-600/20', 'border' => 'border-yellow-500', 'text' => 'text-yellow-400', 'icon' => 'fa-exclamation-triangle'],
            'danger' => ['bg' => 'from-red-500/20 to-red-600/20', 'border' => 'border-red-500', 'text' => 'text-red-400', 'icon' => 'fa-exclamation-circle']
        ];
        foreach($announcements as $announcement): 
            $isPromo = isset($announcement['title']) && strpos($announcement['title'], 'Code Promo : ') === 0;
            if($isPromo) {
                // Affichage spécial pour les annonces promo
                preg_match('/Code Promo : ([^\s]+)/', $announcement['title'], $codeMatch);
                $promoCode = $codeMatch[1] ?? '';
                $promoContent = htmlspecialchars($announcement['content']);
                $promoParts = explode('|', $promoContent);
                $reduction = trim($promoParts[0]);
                $desc = isset($promoParts[1]) ? trim($promoParts[1]) : '';
                $minPurchase = isset($announcement['min_purchase']) ? $announcement['min_purchase'] : null;
        ?>
        <div class="announcement-card bg-gradient-to-r from-blue-600/80 to-blue-400/80 border-l-4 border-blue-500 rounded-xl px-6 py-5 shadow-xl flex flex-col sm:flex-row items-center gap-4"><!-- gap réduit -->
            <div class="flex-shrink-0 flex items-center justify-center promo-gift-circle" style="position:relative;">
                <div class="w-20 h-20 rounded-full bg-gradient-to-br from-blue-700 to-blue-400 flex items-center justify-center border-4 border-blue-400 shadow-lg" style="position:relative;">
                    <i class="fas fa-gift text-white text-4xl promo-gift-anim"></i>
                </div>
            </div>
            <div class="flex-1 min-w-0">
                <div class="flex flex-col gap-0.5 mb-1"><!-- gap et mb réduits -->
                    <div class="flex flex-col sm:flex-row sm:items-center gap-0.5"><!-- gap réduit -->
                        <h4 class="font-bold text-white text-2xl text-center sm:text-left mb-0.5">
                            Offre spéciale en cours !
                        </h4>
                        <span class="px-3 py-1 rounded-full bg-gradient-to-r from-blue-700 to-blue-400 text-white text-xs font-semibold flex items-center gap-1 border border-blue-400 shadow self-center sm:self-auto">
                            <i class="fas fa-gift"></i> Promo
                        </span>
                    </div>
                </div>
                <div class="flex flex-col items-center sm:items-start gap-0.5 mb-1"><!-- gap et mb réduits -->
                    <span class="block font-mono text-base sm:text-xl font-bold text-white mb-0.5 select-all">
                        <i class="fas fa-gift text-white mr-2"></i>
                        Code promo <code class="bg-transparent text-white font-bold px-1"><?php echo $promoCode; ?></code>
                    </span>
                </div>
                <div class="flex items-center gap-1 mb-0.5 flex-wrap"><!-- gap et mb réduits -->
                    <?php if($minPurchase && floatval($minPurchase) > 0): ?>
                        <span class="font-semibold text-blue-200 text-lg">
                            Profitez de <?php echo $reduction; ?> dès <?php echo number_format($minPurchase, 2, ',', ' '); ?> $ d'achat sur le shop grace au code <code><?php echo $promoCode; ?></code> !
                        </span>
                    <?php else: ?>
                        <span class="font-semibold text-blue-200 text-lg">
                            Profitez de <?php echo $reduction; ?> de reductions sur le shop grace au code <code><?php echo $promoCode; ?></code> !
                        </span>
                    <?php endif; ?>
                </div>
                <?php if($desc): ?>
                <div class="text-blue-100 text-base mb-0.5 whitespace-pre-line">
                    <?php echo nl2br($desc); ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <script>
            // Confetti effect: all directions, repeat while hovered
            document.querySelectorAll('.promo-gift-circle').forEach(function(circle){
                let confettiInterval;
                function spawnConfetti() {
                    for(let i=0; i<6; i++){
                        const confetti = document.createElement('div');
                        confetti.className = 'confetti';
                        // Angle et distance aléatoires
                        const angle = Math.random() * 2 * Math.PI;
                        const distance = 60 + Math.random()*40;
                        const x = Math.cos(angle) * distance;
                        const y = Math.sin(angle) * distance;
                        confetti.style.left = '50%';
                        confetti.style.top = '50%';
                        confetti.style.background = `hsl(${Math.random()*360},80%,60%)`;
                        confetti.style.transform = `translate(-50%, -50%) translate(${x}px, ${y}px) rotate(${Math.random()*360}deg) scale(${0.7+Math.random()*0.6})`;
                        confetti.style.animationDelay = (Math.random()*0.2)+'s';
                        confetti.style.position = 'absolute';
                        confetti.style.zIndex = 10;
                        confetti.style.pointerEvents = 'none';
                        confetti.style.opacity = 0.9;
                        confetti.style.width = '8px';
                        confetti.style.height = '16px';
                        confetti.style.borderRadius = '2px';
                        confetti.style.transition = 'opacity 0.5s';
                        circle.appendChild(confetti);
                        setTimeout(() => confetti.remove(), 1200);
                    }
                }
                circle.addEventListener('mouseenter', function(){
                    spawnConfetti();
                    confettiInterval = setInterval(spawnConfetti, 250);
                });
                circle.addEventListener('mouseleave', function(){
                    clearInterval(confettiInterval);
                });
            });
        </script>
        <?php
            } else {
                // Affichage classique pour les autres annonces
                $color = $colors[$announcement['type']];
                $content = htmlspecialchars($announcement['content']);
                $content = preg_replace(
                    '/(https?:\/\/[^\s<]+)/i',
                    '<a href="$1" target="_blank" class="underline text-blue-400 hover:text-blue-600 font-bold">$1</a>',
                    $content
                );
        ?>
        <div class="announcement-card bg-gradient-to-r <?php echo $color['bg']; ?> border-l-4 <?php echo $color['border']; ?> rounded-xl px-6 py-5 shadow-lg">
            <div class="flex-shrink-0">
                <div class="w-16 h-16 rounded-full bg-gray-800/80 flex items-center justify-center <?php echo $color['border']; ?> border-2 shadow">
                    <i class="fas <?php echo $color['icon']; ?> <?php echo $color['text']; ?> text-3xl"></i>
                </div>
            </div>
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-3 mb-2">
                    <h4 class="font-bold <?php echo $color['text']; ?> text-xl">
                        <?php echo htmlspecialchars($announcement['title']); ?>
                    </h4>
                    <span class="px-3 py-1 bg-gray-800/60 <?php echo $color['text']; ?> text-xs rounded-full border <?php echo $color['border']; ?> font-semibold">
                        Annonce
                    </span>
                </div>
                <div class="text-gray-300 leading-relaxed text-base whitespace-pre-line"><?php echo $content; ?></div>
            </div>
        </div>
        <?php
            }
        endforeach;
        ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Personnes en ligne (dans un conteneur global) -->
    <div class="container mx-auto px-2 sm:px-4 py-8">
        <div class="bg-gray-800/70 border border-purple-700 rounded-xl p-6 shadow-lg">
            <div class="flex items-center justify-center mb-6">
                <h2 class="text-2xl font-bold text-center text-purple-400 flex items-center gap-4">
                    <span>
                        <i class="fas fa-user-friends mr-2"></i>Personnes en ligne
                    </span>
                    <span class="inline-block px-2 py-1 rounded-full bg-transparent border border-green-400 text-green-300 font-semibold text-sm shadow animate-pulse">
                        <?php echo count($online_users); ?> en ligne
                    </span>
                </h2>
            </div>
            <div class="flex flex-wrap gap-6 justify-center">
                <?php foreach($online_users as $user): 
                    $role_key = $user['role'];
                    $role_info = $roles[$role_key] ?? [
                        'label' => strtoupper($role_key),
                        'color' => 'from-gray-500 to-gray-700',
                        'bg' => 'bg-gray-500/10',
                        'icon' => 'fa-user',
                        'border' => 'border-gray-500'
                    ];
                    $gradeTextColor = '';
                    if (strpos($role_info['color'], 'red') !== false) $gradeTextColor = 'text-red-400';
                    elseif (strpos($role_info['color'], 'orange') !== false) $gradeTextColor = 'text-orange-400';
                    elseif (strpos($role_info['color'], 'yellow') !== false) $gradeTextColor = 'text-yellow-400';
                    elseif (strpos($role_info['color'], 'green') !== false) $gradeTextColor = 'text-green-400';
                    elseif (strpos($role_info['color'], 'blue') !== false) $gradeTextColor = 'text-blue-400';
                    elseif (strpos($role_info['color'], 'indigo') !== false) $gradeTextColor = 'text-indigo-400';
                    elseif (strpos($role_info['color'], 'pink') !== false) $gradeTextColor = 'text-pink-400';
                    elseif (strpos($role_info['color'], 'gray') !== false) $gradeTextColor = 'text-gray-400';
                ?>
                    <div class="flex flex-col items-center gap-2">
                        <img src="https://minotar.net/avatar/<?php echo htmlspecialchars($user['minecraft_username']); ?>/64"
                             alt="Tête de <?php echo htmlspecialchars($user['username']); ?>"
                             class="w-16 h-16 object-cover"
                             style="image-rendering: pixelated;">
                        <span class="font-bold text-white text-lg"><?php echo htmlspecialchars($user['username']); ?></span>
                        <span class="px-3 py-1 rounded-full <?php echo $role_info['bg']; ?> <?php echo $gradeTextColor; ?> text-xs font-semibold flex items-center gap-1 border <?php echo $role_info['border']; ?>">
                            <i class="fas <?php echo $role_info['icon']; ?>"></i> <?php echo $role_info['label']; ?>
                        </span>
                    </div>
                <?php endforeach; ?>
                <?php if(empty($online_users)): ?>
                    <div class="text-center text-gray-400 w-full">Aucune personne en ligne actuellement.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Contenu principal -->
    <main class="container mx-auto px-2 sm:px-4 py-8">
    <!-- Hero Section -->
    <section class="relative overflow-hidden">
        <div class="absolute inset-0 bg-gradient-to-br from-purple-900/50 via-gray-900 to-blue-900/50"></div>
        <!-- SUPPRIMÉ : bloc inutile du hero -->
        <!-- ...existing code particules décoratives... -->
        <div class="absolute top-20 left-10 w-20 h-20 bg-purple-500/20 rounded-full blur-xl animate-pulse"></div>
        <div class="absolute bottom-20 right-10 w-32 h-32 bg-blue-500/20 rounded-full blur-xl animate-pulse delay-300"></div>
    </section>

        <!-- Catégories Populaires (sans dégradé de fond sur le conteneur) -->
        <section class="py-10 sm:py-16 bg-gray-800/50">
            <div class="container mx-auto px-2 sm:px-4">
                <h3 class="text-2xl sm:text-3xl font-bold text-center mb-8 sm:mb-12">
                    <i class="fas fa-layer-group mr-2 sm:mr-3 text-purple-500"></i>
                    Catégories Populaires
                </h3>
                <div class="grid grid-cols-1 xs:grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-4 sm:gap-6">
                    <?php
                    $categories = $pdo->query("SELECT * FROM categories")->fetchAll();
                    $categoryIcons = [
                        'Armes' => 'fa-gavel',
                        'Armures' => 'fa-shield-alt',
                        'Outils' => 'fa-hammer',
                        'Ressources' => 'fa-gem',
                        'Potions' => 'fa-flask',
                    ];
                    foreach($categories as $cat):
                        $iconClass = isset($categoryIcons[$cat['name']]) ? $categoryIcons[$cat['name']] : ($cat['icon'] ?: 'fa-box');
                        $categoryColors = [
                            'Armes' => 'text-red-400',
                            'Armures' => 'text-purple-400',
                            'Outils' => 'text-green-400',
                            'Ressources' => 'text-blue-400',
                            'Potions' => 'text-pink-400',
                        ];
                        $colorClass = isset($categoryColors[$cat['name']]) ? $categoryColors[$cat['name']] : 'text-gray-300';
                    ?>
                    <a href="catalog.php?category=<?php echo $cat['id']; ?>" 
                       class="bg-gray-800 hover:bg-gray-700 rounded-xl p-6 text-center transition duration-300 transform hover:scale-105 border-2 border-transparent hover:border-purple-500">
                        <div class="mb-3">
                            <i class="fas <?php echo htmlspecialchars($iconClass); ?> text-5xl <?php echo $colorClass; ?>"></i>
                        </div>
                        <h4 class="font-bold text-lg flex items-center justify-center gap-2 <?php echo $colorClass; ?>">
                            <?php echo htmlspecialchars($cat['name']); ?>
                        </h4>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <!-- Items en vedette -->
        <section id="featured" class="py-10 sm:py-16">
            <div class="container mx-auto px-2 sm:px-4">
                <div class="text-center mb-8 sm:mb-12">
                    <h3 class="text-2xl sm:text-4xl font-bold mb-4">
                        <i class="fas fa-info-circle mr-2 sm:mr-3 text-blue-500"></i>
                        À Propos du Shop
                    </h3>
                </div>
                <div class="max-w-4xl mx-auto">
                    <!-- Encadré Marketplace Paladium -->
                    <div class="bg-gradient-to-r from-blue-500/20 to-purple-500/20 border-l-4 border-blue-500 rounded-r-xl p-8 mb-8">
                        <div class="flex items-start gap-4">
                            <div class="flex-shrink-0">
                                <div class="w-16 h-16 rounded-full bg-blue-500/20 flex items-center justify-center border-2 border-blue-500">
                                    <i class="fas fa-store text-blue-400 text-3xl"></i>
                                </div>
                            </div>
                            <div>
                                <h4 class="text-2xl font-bold text-blue-400 mb-3">
                                    <i class="fas fa-globe mr-2"></i>Marketplace Paladium
                                </h4>
                                <p class="text-gray-300 text-lg leading-relaxed mb-2">
                                    Ce site est une <span class="font-semibold text-white">marketplace dédiée à Paladium</span> qui permet aux joueurs d’acheter et vendre des items en toute simplicité.
                                </p>
                                <p class="text-gray-300 text-lg leading-relaxed mb-2">
                                    <span class="font-semibold text-red-400">Attention :</span> Nous ne pouvons <span class="font-semibold text-white">garantir</span> la résolution des arnaques, litiges ou autres problèmes entre vendeurs et acheteurs.
                                </p>
                                <p class="text-gray-300 text-lg leading-relaxed">
                                    Les <span class="font-semibold text-white">règles du serveur</span> <span class="text-blue-400">Paladium</span> s’appliquent ici. Merci de les respecter lors de vos transactions.
                                </p>
                            </div>
                        </div>
                    </div>

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
                                    Ce shop <span class="font-semibold text-white">n’est pas affilié</span> à Paladium Groupe, Recube ou à leur staff officiel.
                                </p>
                                <p class="text-gray-300 text-lg leading-relaxed">
                                    Il s’agit d’une boutique <span class="font-semibold text-white">indépendante</span> gérée par des joueurs passionnés.
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
                                    Boutique développée par <span class="font-semibold text-white text-xl">Nico7600</span>
                                </p>
                                <p class="text-gray-400 text-base">
                                    <i class="fas fa-laptop-code mr-2"></i>Développeur et créateur de cette plateforme.
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
                                    Cette boutique est le <span class="font-semibold text-white">shop officiel de la faction Astoria</span>.
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
                    <div class="text-center mt-8 sm:mt-12">
                        <a href="catalog.php" class="inline-block bg-gradient-to-r from-purple-600 to-blue-600 hover:from-purple-700 hover:to-blue-700 px-6 sm:px-12 py-3 sm:py-5 rounded-xl font-bold text-lg sm:text-xl transition duration-300 transform hover:scale-105 shadow-lg">
                            <i class="fas fa-shopping-bag mr-2 sm:mr-3"></i>Découvrir le Catalogue
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <!-- Section Avantages -->
        <section class="py-10 sm:py-16 bg-gray-800/50">
            <div class="container mx-auto px-2 sm:px-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6 sm:gap-8">
                    <div class="text-center p-6 sm:p-8 bg-gray-800 rounded-xl">
                        <i class="fas fa-shipping-fast text-4xl sm:text-5xl text-purple-500 mb-2 sm:mb-4"></i>
                        <h4 class="text-lg sm:text-xl font-bold mb-2 sm:mb-3">Livraison Rapide</h4>
                        <p class="text-gray-400 text-sm sm:text-base">Recevez vos items rapidement après l'achat</p>
                    </div>
                    <div class="text-center p-6 sm:p-8 bg-gray-800 rounded-xl">
                        <i class="fas fa-shield-alt text-4xl sm:text-5xl text-green-500 mb-2 sm:mb-4"></i>
                        <h4 class="text-lg sm:text-xl font-bold mb-2 sm:mb-3">Paiement Sécurisé</h4>
                        <p class="text-gray-400 text-sm sm:text-base">Transactions 100% sécurisées par des vendeur de confiance</p>
                    </div>
                    <div class="text-center p-6 sm:p-8 bg-gray-800 rounded-xl">
                        <i class="fas fa-headset text-4xl sm:text-5xl text-blue-500 mb-2 sm:mb-4"></i>
                        <h4 class="text-lg sm:text-xl font-bold mb-2 sm:mb-3">Support 24/7</h4>
                        <p class="text-gray-400 text-sm sm:text-base">Une équipe disponible pour vous aider</p>
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

        function copyPromoCode(code, btnId) {
            navigator.clipboard.writeText(code).then(() => {
                // Notification
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

                // Changer le bouton en vert et texte "Copié !"
                const btn = document.getElementById(btnId);
                if(btn) {
                    btn.classList.remove('bg-purple-600', 'hover:bg-purple-700', 'border-purple-400');
                    btn.classList.add('bg-green-600', 'hover:bg-green-700', 'border-green-400');
                    btn.innerHTML = '<i class="fas fa-check"></i> Copié !';
                    btn.disabled = true;
                    setTimeout(() => {
                        btn.classList.remove('bg-green-600', 'hover:bg-green-700', 'border-green-400');
                        btn.classList.add('bg-purple-600', 'hover:bg-purple-700', 'border-purple-400');
                        btn.innerHTML = '<i class="fas fa-copy"></i> Copier';
                        btn.disabled = false;
                    }, 2000);
                }
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
            .then data => {
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
