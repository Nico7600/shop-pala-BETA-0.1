<?php
require_once 'config.php';
session_start();

$userId = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($userId <= 0) {
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <title>Erreur - CrazySouls Shop</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
        <style>
            body, html { min-height: 100vh; position: relative; }
            .footer-fixed { position: absolute; left: 0; right: 0; bottom: 0; }
        </style>
    </head>
    <body class="bg-gray-900 text-gray-100" style="min-height:100vh;">
        <?php include 'includes/header.php'; ?>
        <div class="h-8"></div>
        <div class="flex items-center justify-center min-h-[80vh] px-2 sm:px-4 pb-32">
            <div class="w-full max-w-md bg-gray-800/80 border border-red-500 rounded-2xl p-10 shadow-2xl flex flex-col items-center">
                <i class="fas fa-exclamation-triangle text-red-400 text-4xl mb-4"></i>
                <h2 class="text-2xl font-bold text-red-400 mb-2">Erreur</h2>
                <p class="text-white text-lg font-semibold mb-6">Utilisateur invalide.</p>
                <a href="index.php" class="px-6 py-2 rounded bg-red-600 hover:bg-red-700 text-white font-bold shadow">Retour</a>
            </div>
        </div>
        <div class="footer-fixed">
            <?php include 'includes/footer.php'; ?>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Récupérer les infos de l'utilisateur
$stmt = $pdo->prepare("SELECT id, username, role, minecraft_username, created_at, abonnement_id, last_login_date, balance, login_streak FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <title>Erreur - CrazySouls Shop</title>
        <script src="https://cdn.tailwindcss.com"></script>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
        <style>
            body, html { min-height: 100vh; position: relative; }
            .footer-fixed { position: absolute; left: 0; right: 0; bottom: 0; }
        </style>
    </head>
    <body class="bg-gray-900 text-gray-100" style="min-height:100vh;">
        <?php include 'includes/header.php'; ?>
        <div class="h-8"></div>
        <div class="flex items-center justify-center min-h-[80vh] px-2 sm:px-4 pb-32">
            <div class="w-full max-w-md bg-gray-800/80 border border-red-500 rounded-2xl p-10 shadow-2xl flex flex-col items-center">
                <i class="fas fa-exclamation-triangle text-red-400 text-4xl mb-4"></i>
                <h2 class="text-2xl font-bold text-red-400 mb-2">Erreur</h2>
                <p class="text-white text-lg font-semibold mb-6">Utilisateur introuvable.</p>
                <a href="index.php" class="px-6 py-2 rounded bg-red-600 hover:bg-red-700 text-white font-bold shadow">Retour</a>
            </div>
        </div>
        <div class="footer-fixed">
            <?php include 'includes/footer.php'; ?>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Récupérer les badges visibles
$badgeStmtVisible = $pdo->prepare(
    "SELECT b.name, b.image, b.description, ub.date_attrib
     FROM user_badges ub
     JOIN badges b ON ub.badge_id = b.id
     WHERE ub.user_id = ? AND ub.actif = 1
     ORDER BY ub.date_attrib DESC"
);
$badgeStmtVisible->execute([$userId]);
$badgesVisible = $badgeStmtVisible->fetchAll(PDO::FETCH_ASSOC);

// Récupérer les badges non visibles
$badgeStmtNonVisible = $pdo->prepare(
    "SELECT b.name, b.image, b.description, ub.date_attrib
     FROM user_badges ub
     JOIN badges b ON ub.badge_id = b.id
     WHERE ub.user_id = ? AND ub.actif = 0
     ORDER BY ub.date_attrib DESC"
);
$badgeStmtNonVisible->execute([$userId]);
$badgesNonVisible = $badgeStmtNonVisible->fetchAll(PDO::FETCH_ASSOC);

// Statistiques d'achat (seulement les commandes payées)
$statsStmt = $pdo->prepare(
    "SELECT COUNT(*) as nb_achats, COALESCE(SUM(total),0) as total_depense
     FROM orders
     WHERE user_id = ? AND payment_status = 'paid'"
);
$statsStmt->execute([$userId]);
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

// Récupérer l'abonnement factions si présent
$aboFac = null;
if (!empty($user['abonnement_id'])) {
    $aboStmt = $pdo->prepare("SELECT type, duree, date_fin FROM abofac WHERE id = ?");
    $aboStmt->execute([$user['abonnement_id']]);
    $aboFac = $aboStmt->fetch(PDO::FETCH_ASSOC);
}

// Mapping des rôles (copié de index.php)
$roles = [
    'fondateur' => ['label' => 'FONDATEUR', 'color' => 'from-red-500 to-red-700', 'icon' => 'fa-crown'],
    'admin' => ['label' => 'ADMIN', 'color' => 'from-orange-500 to-orange-700', 'icon' => 'fa-shield-alt'],
    'resp_vendeur' => ['label' => 'RESPONSABLE VENDEUR', 'color' => 'from-yellow-500 to-yellow-700', 'icon' => 'fa-user-tie'],
    'vendeur_senior' => ['label' => 'VENDEUR SENIOR', 'color' => 'from-green-500 to-green-700', 'icon' => 'fa-star'],
    'vendeur_confirme' => ['label' => 'VENDEUR CONFIRMÉ', 'color' => 'from-blue-500 to-blue-700', 'icon' => 'fa-check-circle'],
    'vendeur' => ['label' => 'VENDEUR', 'color' => 'from-indigo-500 to-indigo-700', 'icon' => 'fa-shopping-bag'],
    'vendeur_test' => ['label' => 'VENDEUR TEST', 'color' => 'from-gray-500 to-gray-700', 'icon' => 'fa-flask'],
    'partenaire' => ['label' => 'PARTENAIRE', 'color' => 'from-pink-500 to-pink-700', 'icon' => 'fa-handshake'],
];
$role_info = $roles[$user['role']] ?? ['label' => strtoupper($user['role']), 'color' => 'from-gray-500 to-gray-700', 'icon' => 'fa-user'];

// Récupérer des stats utiles
$statsUtiles = [];
$statsUtiles['last_login'] = !empty($user['last_login_date']) ? $user['last_login_date'] : null;
$statsUtiles['balance'] = isset($user['balance']) ? $user['balance'] : 0;
$statsUtiles['nb_badges'] = count($badgesVisible) + count($badgesNonVisible);
$statsUtiles['login_streak'] = isset($user['login_streak']) ? $user['login_streak'] : 0;

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Profil de <?php echo htmlspecialchars($user['username']); ?> - CrazySouls Shop</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body, html {
            min-height: 100vh;
            position: relative;
        }
        .footer-fixed {
            position: absolute;
            left: 0;
            right: 0;
            bottom: 0;
        }
        @keyframes rainbow {
            0%   { color: #e11d48; }
            20%  { color: #f59e42; }
            40%  { color: #fbbf24; }
            60%  { color: #22c55e; }
            80%  { color: #3b82f6; }
            100% { color: #a21caf; }
        }
        .rainbow {
            animation: rainbow 1s linear infinite;
        }
        /* Supprimer le style error-message */
    </style>
</head>
<body class="bg-gray-900 text-gray-100" style="min-height:100vh;">
    <?php include 'includes/header.php'; ?>
    <div class="h-8"></div>
    <div class="flex items-center justify-center min-h-[80vh] px-2 sm:px-4 pb-32">
        <div class="w-full max-w-5xl bg-gray-800/80 border border-purple-700 rounded-2xl p-10 shadow-2xl">
            <div class="flex flex-col items-center gap-6">
                <!-- Avatar + pseudo + grade -->
                <div class="flex flex-col items-center gap-2 w-full">
                    <div class="relative">
                        <img
                            id="avatar-minecraft"
                            src="https://minotar.net/avatar/<?php echo htmlspecialchars($user['minecraft_username']); ?>/128"
                            alt="Tête de <?php echo htmlspecialchars($user['username']); ?>"
                            class="w-24 h-24 object-cover shadow-lg mb-2"
                            style="image-rendering: pixelated; cursor:pointer;"
                        >
                        <span
                            id="avatar-poulpe"
                            class="absolute inset-0 flex items-center justify-center w-24 h-24 rounded-full"
                            style="display:none; font-size:80px; z-index:10;"
                        >
                            <i class="fa-brands fa-octopus-deploy"></i>
                        </span>
                    </div>
                    <h2 id="profile-username" class="text-3xl font-bold text-white flex items-center gap-2 mb-1">
                        <?php echo htmlspecialchars($user['username']); ?>
                    </h2>
                    <span id="role-badge" class="inline-flex items-center gap-2 px-5 py-1 rounded-full border-2 border-red-400 text-red-400 font-bold text-base uppercase bg-transparent shadow-sm">
                        <i class="fas <?php echo $role_info['icon']; ?>"></i>
                        <?php echo $role_info['label']; ?>
                    </span>
                </div>
                <!-- Abonnement factions -->
                <div class="w-full flex justify-center">
                    <?php if ($aboFac): ?>
                        <span class="inline-flex items-center gap-2 px-4 py-1 rounded-full border-2 border-purple-400 text-purple-300 font-bold text-base bg-transparent shadow-sm">
                            <i class="fas fa-gem"></i>
                            Abonnement Factions : 
                            <span class="font-bold"><?php echo htmlspecialchars($aboFac['type']); ?></span>
                            (<?php echo htmlspecialchars($aboFac['duree']); ?>)
                            <?php if ($aboFac['date_fin']): ?>
                                - Fin : <span class="font-bold"><?php echo date('d/m/Y', strtotime($aboFac['date_fin'])); ?></span>
                            <?php endif; ?>
                        </span>
                    <?php else: ?>
                        <span class="inline-flex items-center gap-2 px-4 py-1 rounded-full border-2 border-gray-500 text-gray-300 font-bold text-base bg-transparent shadow-sm">
                            <i class="fas fa-ban"></i>
                            Aucun abonnement factions
                        </span>
                    <?php endif; ?>
                </div>
                <!-- Statistiques principales alignées -->
                <div class="flex flex-nowrap gap-3 justify-center mt-4 mb-2 w-full">
                    <span class="inline-flex items-center gap-2 px-4 py-1 rounded-full border-2 border-purple-400 text-purple-300 font-bold text-xs bg-transparent shadow-sm whitespace-nowrap">
                        <i class="fas fa-user-clock"></i>
                        Arrivé le:
                        <span class="font-bold"><?php echo date('d/m/Y', strtotime($user['created_at'])); ?></span>
                    </span>
                    <span class="inline-flex items-center gap-2 px-4 py-1 rounded-full border-2 border-purple-400 text-purple-300 font-bold text-xs bg-transparent shadow-sm whitespace-nowrap">
                        <i class="fas fa-calendar-day"></i>
                        Dernière connexion:
                        <span class="font-bold"><?php echo $statsUtiles['last_login'] ? date('d/m/Y', strtotime($statsUtiles['last_login'])) : 'N/A'; ?></span>
                    </span>
                    <span class="inline-flex items-center gap-2 px-4 py-1 rounded-full border-2 border-purple-400 text-purple-300 font-bold text-xs bg-transparent shadow-sm whitespace-nowrap">
                        <i class="fas fa-award"></i>
                        Badges:
                        <span class="font-bold"><?php echo $statsUtiles['nb_badges']; ?></span>
                    </span>
                    <span class="inline-flex items-center gap-2 px-4 py-1 rounded-full border-2 border-purple-400 text-purple-300 font-bold text-xs bg-transparent shadow-sm whitespace-nowrap">
                        <i class="fas fa-fire"></i>
                        Streak:
                        <span class="font-bold"><?php echo $statsUtiles['login_streak']; ?> jour(s)</span>
                    </span>
                </div>
            </div>
            <hr class="my-6 border-purple-700">
            <!-- Statistiques d'achat -->
            <div class="mb-6">
                <h3 class="text-xl font-bold text-purple-300 mb-3"><i class="fas fa-shopping-cart mr-2"></i>Statistiques d'achat</h3>
                <div class="flex gap-4 justify-center">
                    <span class="inline-flex items-center gap-2 px-5 py-2 rounded-full border-2 border-purple-400 text-purple-200 font-bold text-base bg-transparent shadow-sm">
                        <i class="fas fa-shopping-basket"></i>
                        <span class="font-semibold text-white"><?php echo $stats['nb_achats']; ?></span>
                        <span class="text-purple-300">achats</span>
                    </span>
                    <span class="inline-flex items-center gap-2 px-5 py-2 rounded-full border-2 border-purple-400 text-purple-200 font-bold text-base bg-transparent shadow-sm">
                        <i class="fas fa-euro-sign"></i>
                        <span class="font-semibold text-white"><?php echo number_format($stats['total_depense'], 2, ',', ' '); ?> €</span>
                        <span class="text-purple-300">dépensés</span>
                    </span>
                </div>
            </div>
            <hr class="my-8 border-purple-700">
            <!-- Badges visibles -->
            <div>
                <h3 class="text-xl font-bold text-purple-300 mb-3"><i class="fas fa-award mr-2"></i>Badges visibles</h3>
                <?php if (!empty($badgesVisible)): ?>
                    <div class="flex flex-wrap gap-5 justify-center">
                        <?php foreach ($badgesVisible as $badge): ?>
                            <div class="bg-gradient-to-br from-purple-900 to-purple-700 border border-purple-500 rounded-xl p-4 flex flex-col items-center w-40 shadow-lg">
                                <img src="badges/<?php echo htmlspecialchars($badge['image']); ?>"
                                     alt="<?php echo htmlspecialchars($badge['name']); ?>"
                                     class="w-16 h-16 rounded mb-2 shadow-lg">
                                <span class="font-bold text-purple-200 text-base text-center mb-1"><?php echo htmlspecialchars($badge['name']); ?></span>
                                <span class="text-purple-300 text-xs">Attribué le <?php echo date('d/m/Y', strtotime($badge['date_attrib'])); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-gray-400">Aucun badge visible pour cet utilisateur.</div>
                <?php endif; ?>
            </div>
            <hr class="my-6 border-purple-700">
            <!-- Badges non visibles -->
            <div>
                <h3 class="text-xl font-bold text-purple-300 mb-3"><i class="fas fa-eye-slash mr-2"></i>Badges non visibles</h3>
                <?php if (!empty($badgesNonVisible)): ?>
                    <div class="flex flex-wrap gap-5 justify-center">
                        <?php foreach ($badgesNonVisible as $badge): ?>
                            <div class="bg-gradient-to-br from-gray-800 to-gray-700 border border-gray-500 rounded-xl p-4 flex flex-col items-center w-40 shadow-lg opacity-60">
                                <img src="badges/<?php echo htmlspecialchars($badge['image']); ?>"
                                     alt="<?php echo htmlspecialchars($badge['name']); ?>"
                                     class="w-16 h-16 rounded mb-2 shadow-lg">
                                <span class="font-bold text-gray-300 text-base text-center mb-1"><?php echo htmlspecialchars($badge['name']); ?></span>
                                <span class="text-gray-400 text-xs">Attribué le <?php echo date('d/m/Y', strtotime($badge['date_attrib'])); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-gray-400">Aucun badge non visible pour cet utilisateur.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="footer-fixed">
        <?php include 'includes/footer.php'; ?>
    </div>
    <script>
let clickCount = 0;
let poulpeTimeout = null;
const avatar = document.getElementById('avatar-minecraft');
const poulpe = document.getElementById('avatar-poulpe');
const poulpeIcon = poulpe.querySelector('i');
const roleBadge = document.getElementById('role-badge');
const profileUsername = document.getElementById('profile-username');
const originalUsername = "<?php echo htmlspecialchars($user['username']); ?>";

avatar.addEventListener('click', function() {
    clickCount++;
    if (clickCount === 4) {
        avatar.style.visibility = 'hidden';
        poulpe.style.display = 'flex';
        poulpeIcon.classList.add('rainbow');
        roleBadge.innerHTML = '<span class="rainbow" style="font-size:20px;font-weight:bold;">Poulpie &amp; Co</span>';
        profileUsername.innerHTML = '<i class="fa-brands fa-octopus-deploy"></i> Mr wiki';
        poulpeTimeout = setTimeout(() => {
            poulpe.style.display = 'none';
            avatar.style.visibility = 'visible';
            poulpeIcon.classList.remove('rainbow');
            roleBadge.innerHTML = '<i class="fas <?php echo $role_info["icon"]; ?>"></i> <?php echo $role_info["label"]; ?>';
            profileUsername.textContent = originalUsername;
            clickCount = 0;
        }, 4000); // 4 secondes
    }
});

// Le clic sur le poulpe ne fait rien
</script>
</body>
</html>
