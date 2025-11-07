<?php
session_start();
require_once 'config.php';

$roles = [
    'fondateur' => ['label' => 'FONDATEUR', 'color' => 'from-red-500 to-red-700', 'bg' => 'bg-red-500/10', 'icon' => 'fa-crown', 'level' => 1, 'border' => 'border-red-500'],
    'admin' => ['label' => 'ADMIN', 'color' => 'from-orange-500 to-orange-700', 'bg' => 'bg-orange-500/10', 'icon' => 'fa-shield-alt', 'level' => 2, 'border' => 'border-orange-500'],
    'resp_vendeur' => ['label' => 'RESPONSABLE VENDEUR', 'color' => 'from-yellow-500 to-yellow-700', 'bg' => 'bg-yellow-500/10', 'icon' => 'fa-user-tie', 'level' => 3, 'border' => 'border-yellow-500'],
    'vendeur_senior' => ['label' => 'VENDEUR SENIOR', 'color' => 'from-green-500 to-green-700', 'bg' => 'bg-green-500/10', 'icon' => 'fa-star', 'level' => 4, 'border' => 'border-green-500'],
    'vendeur_confirme' => ['label' => 'VENDEUR CONFIRMÉ', 'color' => 'from-blue-500 to-blue-700', 'bg' => 'bg-blue-500/10', 'icon' => 'fa-check-circle', 'level' => 5, 'border' => 'border-blue-500'],
    'vendeur' => ['label' => 'VENDEUR', 'color' => 'from-indigo-500 to-indigo-700', 'bg' => 'bg-indigo-500/10', 'icon' => 'fa-shopping-bag', 'level' => 6, 'border' => 'border-indigo-500'],
    'vendeur_test' => ['label' => 'VENDEUR TEST', 'color' => 'from-gray-500 to-gray-700', 'bg' => 'bg-gray-500/10', 'icon' => 'fa-flask', 'level' => 7, 'border' => 'border-gray-500'],
];

// Récupérer tous les membres du staff depuis la base de données
$staff_members = [];
$error_message = null;

try {
    $staff_query = $pdo->query("
        SELECT id, username, role, created_at, last_activity
        FROM users 
        WHERE role IN ('fondateur', 'admin', 'resp_vendeur', 'vendeur_senior', 'vendeur_confirme', 'vendeur', 'vendeur_test')
        ORDER BY 
            CASE role
                WHEN 'fondateur' THEN 1
                WHEN 'admin' THEN 2
                WHEN 'resp_vendeur' THEN 3
                WHEN 'vendeur_senior' THEN 4
                WHEN 'vendeur_confirme' THEN 5
                WHEN 'vendeur' THEN 6
                WHEN 'vendeur_test' THEN 7
            END,
            username ASC
    ");
    $staff_members = $staff_query->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    error_log("Erreur staff.php: " . $e->getMessage());
    $error_message = "Erreur lors de la récupération des données du staff.";
}

// Grouper par rôle
$staff_by_role = [];
foreach ($staff_members as $member) {
    $staff_by_role[$member['role']][] = $member;
}

// Calculer les statistiques
$total_staff = count($staff_members);
$role_counts = !empty($staff_members) ? array_count_values(array_column($staff_members, 'role')) : [];

// Calculer les groupes et le nombre en ligne
$main_roles = ['fondateur', 'admin', 'resp_vendeur'];
$seller_roles = ['vendeur_senior', 'vendeur_confirme', 'vendeur', 'vendeur_test'];

$main_staff = array_filter($staff_members, fn($m) => in_array($m['role'], $main_roles));
$seller_staff = array_filter($staff_members, fn($m) => in_array($m['role'], $seller_roles));

$main_online = count(array_filter($main_staff, fn($m) => isOnline($m['last_activity'] ?? null)));
$seller_online = count(array_filter($seller_staff, fn($m) => isOnline($m['last_activity'] ?? null)));

$main_total = count($main_staff);
$seller_total = count($seller_staff);

// Fonction pour vérifier si un utilisateur est en ligne (activité dans les 5 dernières minutes)
function isOnline($last_activity) {
    if (empty($last_activity)) return false;
    $last_time = strtotime($last_activity);
    $current_time = time();
    return ($current_time - $last_time) < 300; // 5 minutes = 300 secondes
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff - CrazySouls Shop</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: #1a202c; /* gris foncé Tailwind: bg-gray-900 */
            min-height: 100vh;
            position: relative;
            overflow-x: hidden;
        }
        .card-hover {
            transition: all 0.3s ease;
        }
        .card-hover:hover {
            transform: translateY(-5px);
        }
        @keyframes pulse-ring {
            0% {
                transform: scale(1);
                opacity: 1;
            }
            100% {
                transform: scale(1.5);
                opacity: 0;
            }
        }
        .pulse-ring {
            animation: pulse-ring 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
    </style>
</head>
<body class="bg-gray-900 text-white min-h-screen">
    <?php include 'includes/header.php'; ?>

    <div class="container mx-auto px-4 py-8">
        <!-- En-tête de la page -->
        <div class="text-center mb-12">
            <div class="inline-block mb-4">
                <i class="fas fa-users text-6xl text-purple-400"></i>
            </div>
            <h1 class="text-5xl font-black mb-4 bg-gradient-to-r from-purple-400 to-pink-400 bg-clip-text text-transparent">
                Notre Équipe
            </h1>
            <p class="text-gray-300 text-lg max-w-2xl mx-auto mb-4">
                Découvrez les membres qui font vivre <span class="text-purple-400 font-bold">CrazySouls Shop</span>
            </p>
            <!-- Compteurs Staff -->
            <div class="flex flex-col md:flex-row gap-4 justify-center items-center mt-4">
                <div class="inline-block bg-purple-500/20 backdrop-blur-sm border border-purple-500/30 rounded-xl px-6 py-3">
                    <span class="text-xl font-bold text-purple-400 mr-2">Staff</span>
                    <span class="text-3xl font-black text-green-400"><?php echo $main_online; ?></span>
                    <span class="text-gray-300 mx-1">/</span>
                    <span class="text-3xl font-black text-purple-400"><?php echo $main_total; ?></span>
                </div>
                <div class="inline-block bg-blue-500/20 backdrop-blur-sm border border-blue-500/30 rounded-xl px-6 py-3">
                    <span class="text-xl font-bold text-blue-400 mr-2">Vendeurs</span>
                    <span class="text-3xl font-black text-green-400"><?php echo $seller_online; ?></span>
                    <span class="text-gray-300 mx-1">/</span>
                    <span class="text-3xl font-black text-blue-400"><?php echo $seller_total; ?></span>
                </div>
            </div>
        </div>

        <?php if($error_message): ?>
        <div class="bg-red-500/20 border-2 border-red-500 rounded-xl p-4 mb-8 text-center">
            <i class="fas fa-exclamation-triangle text-2xl mr-2 text-red-400"></i>
            <span class="text-lg"><?php echo htmlspecialchars($error_message); ?></span>
        </div>
        <?php endif; ?>

        <?php if($total_staff > 0): ?>

        <!-- Membres du staff par rôle -->
        <?php foreach ($roles as $role_key => $role_info): ?>
            <div class="mb-12">
                <!-- Titre de section -->
                <div class="flex items-center gap-3 mb-6 pb-3 border-b-2 <?php echo $role_info['border']; ?>">
                    <div class="w-12 h-12 bg-gradient-to-r <?php echo $role_info['color']; ?> rounded-xl flex items-center justify-center shadow-lg">
                        <i class="fas <?php echo $role_info['icon']; ?> text-2xl"></i>
                    </div>
                    <div>
                        <h2 class="text-2xl font-black bg-gradient-to-r <?php echo $role_info['color']; ?> bg-clip-text text-transparent">
                            <?php echo $role_info['label']; ?>
                        </h2>
                        <p class="text-sm text-gray-400">
                            <?php echo isset($staff_by_role[$role_key]) ? count($staff_by_role[$role_key]) : 0; ?> membre<?php echo (isset($staff_by_role[$role_key]) && count($staff_by_role[$role_key]) > 1) ? 's' : ''; ?>
                        </p>
                    </div>
                </div>
                
                <?php if (isset($staff_by_role[$role_key]) && count($staff_by_role[$role_key]) > 0): ?>
                    <!-- Grille de cartes -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                        <?php foreach ($staff_by_role[$role_key] as $member): ?>
                            <?php 
                            $is_online = isOnline($member['last_activity'] ?? null);
                            $status_color = $is_online ? 'bg-green-500' : 'bg-red-500';
                            $status_text = $is_online ? 'En ligne' : 'Hors ligne';
                            $status_text_color = $is_online ? 'text-green-400' : 'text-red-400';
                            ?>
                            <div class="bg-gray-800/50 backdrop-blur-sm rounded-xl p-5 border-2 border-gray-700 hover:border-purple-500 card-hover">
                                <div class="flex items-center gap-3 mb-4">
                                    <div class="relative">
                                        <div class="w-14 h-14 bg-gradient-to-br <?php echo $role_info['color']; ?> rounded-full flex items-center justify-center font-black text-xl shadow-lg">
                                            <?php echo strtoupper(substr($member['username'], 0, 2)); ?>
                                        </div>
                                        <!-- Indicateur de statut avec animation -->
                                        <div class="absolute -bottom-1 -right-1">
                                            <?php if($is_online): ?>
                                            <div class="relative">
                                                <div class="absolute inset-0 <?php echo $status_color; ?> rounded-full pulse-ring"></div>
                                                <div class="relative w-4 h-4 <?php echo $status_color; ?> rounded-full border-2 border-gray-800"></div>
                                            </div>
                                            <?php else: ?>
                                            <div class="w-4 h-4 <?php echo $status_color; ?> rounded-full border-2 border-gray-800"></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div>
                                        <h3 class="font-bold text-lg text-white"><?php echo htmlspecialchars($member['username']); ?></h3>
                                        <!-- Statut en ligne/hors ligne après le nom -->
                                        <span class="text-xs <?php echo $status_text_color; ?> font-semibold">
                                            <?php echo $status_text; ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="flex items-center justify-between pt-3 border-t border-gray-700">
                                    <span class="inline-flex items-center gap-2 px-3 py-1 bg-gradient-to-r <?php echo $role_info['color']; ?> rounded-lg text-xs font-bold">
                                        <i class="fas <?php echo $role_info['icon']; ?>"></i>
                                        <?php echo $role_info['label']; ?>
                                    </span>
                                    <span class="text-xs text-gray-500 font-mono">
                                        #<?php echo str_pad($member['id'], 4, '0', STR_PAD_LEFT); ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <!-- Message si aucun membre -->
                    <div class="bg-gray-800/30 rounded-xl p-8 border border-gray-700 text-center">
                        <i class="fas fa-user-slash text-4xl text-gray-600 mb-3"></i>
                        <p class="text-gray-400">Aucun membre avec ce rôle actuellement</p>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

        <?php else: ?>
        <!-- Message si aucun staff -->
        <div class="text-center py-16">
            <i class="fas fa-users-slash text-6xl text-gray-600 mb-4"></i>
            <h2 class="text-3xl font-bold text-gray-400 mb-2">Aucun membre du staff</h2>
            <p class="text-gray-500">Il n'y a actuellement aucun membre du staff enregistré.</p>
        </div>
        <?php endif; ?>
    </div>

    <?php include 'includes/footer.php'; ?>
</body>
</html>
