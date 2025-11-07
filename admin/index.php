<?php
require_once '../config.php';

// Vérification des permissions
if(!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['resp_vendeur', 'fondateur'])) {
    header('Location: ../login.php');
    exit;
}

// Statistiques
$stats = [
    'total_users' => $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'total_items' => $pdo->query("SELECT COUNT(*) FROM items")->fetchColumn(),
    'total_orders' => $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn(),
    // Modifié pour calculer total - discount_amount
    'total_revenue' => $pdo->query("SELECT SUM(total - discount_amount) FROM orders")->fetchColumn() ?? 0
];

// Fonction pour vérifier si un utilisateur est en ligne
function isUserOnline($last_activity) {
    if (empty($last_activity)) return false;
    $last_time = strtotime($last_activity);
    $current_time = time();
    return ($current_time - $last_time) < 300;
}

// Utilisateurs récents
$recent_users = $pdo->query("SELECT u.*, r.name as role_name, r.color as role_color 
                             FROM users u 
                             LEFT JOIN roles r ON u.role = r.name 
                             ORDER BY u.created_at DESC LIMIT 10")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - CrazySouls Shop</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="bg-gray-900 text-gray-100">
    <!-- Sidebar -->
    <div class="flex flex-col md:flex-row min-h-screen">
        <?php include 'sidebar.php'; ?>

        <!-- Main Content -->
        <main class="flex-1 p-2 sm:p-8 w-full">
            <div class="mb-4 sm:mb-8">
                <h2 class="text-2xl sm:text-3xl font-bold mb-2">Dashboard</h2>
                <p class="text-gray-400 text-sm sm:text-base">Vue d'ensemble de votre boutique</p>
            </div>

            <!-- Stats Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6 mb-4 sm:mb-8">
                <div class="bg-gradient-to-br from-purple-600 to-purple-800 rounded-xl p-4 sm:p-6 shadow-lg">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-purple-200 text-xs sm:text-sm">Utilisateurs</p>
                            <p class="text-2xl sm:text-3xl font-bold mt-2"><?php echo $stats['total_users']; ?></p>
                        </div>
                        <i class="fas fa-users text-3xl sm:text-4xl text-purple-300"></i>
                    </div>
                </div>
                <div class="bg-gradient-to-br from-blue-600 to-blue-800 rounded-xl p-4 sm:p-6 shadow-lg">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-blue-200 text-xs sm:text-sm">Produits</p>
                            <p class="text-2xl sm:text-3xl font-bold mt-2"><?php echo $stats['total_items']; ?></p>
                        </div>
                        <i class="fas fa-box text-3xl sm:text-4xl text-blue-300"></i>
                    </div>
                </div>
                <div class="bg-gradient-to-br from-green-600 to-green-800 rounded-xl p-4 sm:p-6 shadow-lg">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-green-200 text-xs sm:text-sm">Commandes</p>
                            <p class="text-2xl sm:text-3xl font-bold mt-2"><?php echo $stats['total_orders']; ?></p>
                        </div>
                        <i class="fas fa-shopping-cart text-3xl sm:text-4xl text-green-300"></i>
                    </div>
                </div>
                <div class="bg-gradient-to-br from-yellow-600 to-yellow-800 rounded-xl p-4 sm:p-6 shadow-lg">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-yellow-200 text-xs sm:text-sm">Revenus</p>
                            <p class="text-2xl sm:text-3xl font-bold mt-2"><?php echo number_format($stats['total_revenue'], 0); ?> $</p>
                        </div>
                        <i class="fas fa-dollar-sign text-3xl sm:text-4xl text-yellow-300"></i>
                    </div>
                </div>
            </div>

            <!-- Recent Users -->
            <div class="bg-gray-800 rounded-xl shadow-lg overflow-hidden">
                <div class="p-4 sm:p-6 border-b border-gray-700">
                    <h3 class="text-lg sm:text-xl font-bold">
                        <i class="fas fa-user-clock mr-2 text-purple-500"></i>
                        Utilisateurs récents
                    </h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-xs sm:text-base">
                        <thead class="bg-gray-750">
                            <tr>
                                <th class="px-2 sm:px-6 py-2 sm:py-3 text-left font-medium text-gray-400 uppercase">Utilisateur</th>
                                <th class="px-2 sm:px-6 py-2 sm:py-3 text-left font-medium text-gray-400 uppercase hidden sm:table-cell">Email</th>
                                <th class="px-2 sm:px-6 py-2 sm:py-3 text-left font-medium text-gray-400 uppercase">Pseudo MC</th>
                                <th class="px-2 sm:px-6 py-2 sm:py-3 text-left font-medium text-gray-400 uppercase">Grade</th>
                                <th class="px-2 sm:px-6 py-2 sm:py-3 text-left font-medium text-gray-400 uppercase hidden sm:table-cell">Inscription</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-700">
                            <?php foreach($recent_users as $user): ?>
                            <tr class="hover:bg-gray-750 transition">
                                <td class="px-2 sm:px-6 py-2 sm:py-4">
                                    <div class="flex items-center">
                                        <div class="relative mr-2 sm:mr-3">
                                            <div class="w-8 sm:w-10 h-8 sm:h-10 bg-purple-600 rounded-full flex items-center justify-center">
                                                <i class="fas fa-user"></i>
                                            </div>
                                            <?php 
                                            $is_online = isUserOnline($user['last_activity'] ?? null);
                                            ?>
                                            <!-- Indicateur de statut -->
                                            <div class="absolute -bottom-1 -right-1">
                                                <?php if($is_online): ?>
                                                <div class="relative">
                                                    <div class="absolute inset-0 bg-green-500 rounded-full animate-pulse opacity-50"></div>
                                                    <div class="relative w-2 sm:w-3 h-2 sm:h-3 bg-green-500 rounded-full border-2 border-gray-800"></div>
                                                </div>
                                                <?php else: ?>
                                                <div class="w-2 sm:w-3 h-2 sm:h-3 bg-red-500 rounded-full border-2 border-gray-800"></div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div>
                                            <span class="font-medium"><?php echo htmlspecialchars($user['username']); ?></span>
                                            <!-- Statut en ligne/hors ligne après le nom -->
                                            <div class="mt-0.5">
                                                <span class="text-xs <?php echo $is_online ? 'text-green-400' : 'text-red-400'; ?> font-semibold">
                                                    <?php echo $is_online ? 'En ligne' : 'Hors ligne'; ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-2 sm:px-6 py-2 sm:py-4 text-gray-400 hidden sm:table-cell"><?php echo htmlspecialchars($user['email']); ?></td>
                                <td class="px-2 sm:px-6 py-2 sm:py-4">
                                    <span class="bg-gray-700 px-2 sm:px-3 py-1 rounded-full text-xs sm:text-sm">
                                        <?php echo htmlspecialchars($user['minecraft_username'] ?? 'N/A'); ?>
                                    </span>
                                </td>
                                <td class="px-2 sm:px-6 py-2 sm:py-4">
                                    <span class="px-2 sm:px-3 py-1 rounded-full text-xs sm:text-sm font-medium" 
                                        style="background-color: <?php echo $user['role_color']; ?>20; color: <?php echo $user['role_color']; ?>;">
                                        <?php echo htmlspecialchars($user['role_name'] ?? $user['role']); ?>
                                    </span>
                                </td>
                                <td class="px-2 sm:px-6 py-2 sm:py-4 text-gray-400 hidden sm:table-cell">
                                    <?php echo date('d/m/Y', strtotime($user['created_at'])); ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
