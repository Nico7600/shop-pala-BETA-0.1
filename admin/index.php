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
    'total_revenue' => $pdo->query("SELECT SUM(total - discount_amount) FROM orders")->fetchColumn() ?? 0,
    'promo_used' => $pdo->query("SELECT SUM(current_uses) FROM promo_codes WHERE is_active = 1")->fetchColumn() ?? 0
];

// Récupère la durée choisie (GET), défaut 30 jours
$days = isset($_GET['signup_days']) && is_numeric($_GET['signup_days']) && $_GET['signup_days'] > 0 ? intval($_GET['signup_days']) : 30;

// Stat promo utilisés sur la période choisie
$promo_used_period = $pdo->prepare("SELECT SUM(current_uses) FROM promo_codes WHERE DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL ? DAY)");
$promo_used_period->execute([$days]);
$promo_used_period_value = $promo_used_period->fetchColumn() ?? 0;

// Récupère le nombre de comptes créés et de connexions par jour sur la période choisie
$signup_stats = [];
$labels = [];
$data_signup = [];
$data_connexion = [];
for ($i = $days - 1; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $labels[] = date('d/m', strtotime($date));
    // Comptes créés
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE DATE(created_at) = ?");
    $stmt->execute([$date]);
    $data_signup[] = (int)$stmt->fetchColumn();
    // Connexions (utilisateurs ayant été actifs ce jour)
    $stmt2 = $pdo->prepare("SELECT COUNT(DISTINCT id) FROM users WHERE DATE(last_activity) = ?");
    $stmt2->execute([$date]);
    $data_connexion[] = (int)$stmt2->fetchColumn();
}
$signup_stats['labels'] = $labels;
$signup_stats['signup'] = $data_signup;
$signup_stats['connexion'] = $data_connexion;

// Fonction pour vérifier si un utilisateur est en ligne
function isUserOnline($last_activity) {
    if (empty($last_activity)) return false;
    $last_time = strtotime($last_activity);
    $current_time = time();
    return ($current_time - $last_time) < 300;
}

// Statistiques rapides
$total_orders = $pdo->query("SELECT COUNT(*) FROM orders WHERE DATE(created_at) = CURDATE()")->fetchColumn() ?? 0;
// Ajout : argent en circulation
$total_money = $pdo->query("SELECT SUM(balance) FROM users")->fetchColumn() ?? 0;

// Commandes par jour (sur la période choisie)
$order_labels = [];
$order_data = [];
$order_money = [];
for ($i = $days - 1; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $order_labels[] = date('d/m', strtotime($date));
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE DATE(created_at) = ?");
    $stmt->execute([$date]);
    $order_data[] = (int)$stmt->fetchColumn();
    // Argent en circulation ce jour-là
    $stmt2 = $pdo->prepare("SELECT SUM(balance) FROM users WHERE DATE(last_activity) = ?");
    $stmt2->execute([$date]);
    $order_money[] = (int)$stmt2->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - CrazySouls Shop</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 sm:gap-6 mb-4 sm:mb-8">
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
                <div class="bg-gradient-to-br from-pink-600 to-pink-800 rounded-xl p-4 sm:p-6 shadow-lg">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-pink-200 text-xs sm:text-sm">Codes promo utilisés</p>
                            <p class="text-2xl sm:text-3xl font-bold mt-2"><?php echo $stats['promo_used']; ?></p>
                        </div>
                        <i class="fas fa-ticket-alt text-3xl sm:text-4xl text-pink-300"></i>
                    </div>
                </div>
            </div>

            <!-- Graphique comptes créés + connexions + formulaire durée -->
            <div class="bg-gray-800 rounded-xl shadow-lg mb-8 p-6">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-4 gap-2">
                    <h3 class="text-lg sm:text-xl font-bold flex items-center gap-2">
                        <i class="fas fa-chart-line text-blue-400"></i>
                        Statistiques d'activité (créations & connexions)
                    </h3>
                    <form method="get" class="flex items-center gap-2">
                        <label for="signup_days" class="text-sm text-gray-300 font-semibold">Durée :</label>
                        <input type="number" min="1" max="365" name="signup_days" id="signup_days"
                            value="<?php echo $days; ?>"
                            class="bg-gray-900 border border-gray-700 rounded px-2 py-1 w-20 text-gray-100 text-sm focus:outline-none focus:border-blue-500" />
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded font-bold text-sm transition">Afficher</button>
                    </form>
                </div>
                <canvas id="signupChart" height="80"></canvas>
            </div>

            <!-- Graphique commandes par jour -->
            <div class="bg-gray-800 rounded-xl shadow-lg mb-8 p-6">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-4 gap-2">
                    <h3 class="text-lg sm:text-xl font-bold flex items-center gap-2">
                        <i class="fas fa-chart-bar text-green-400"></i>
                        Info commandes (Commandes & Argent en circulation)
                    </h3>
                    <form method="get" class="flex items-center gap-2">
                        <label for="signup_days" class="text-sm text-gray-300 font-semibold">Durée :</label>
                        <input type="number" min="1" max="365" name="signup_days" id="signup_days"
                            value="<?php echo $days; ?>"
                            class="bg-gray-900 border border-gray-700 rounded px-2 py-1 w-20 text-gray-100 text-sm focus:outline-none focus:border-blue-500" />
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded font-bold text-sm transition">Afficher</button>
                    </form>
                </div>
                <canvas id="ordersChart" height="60"></canvas>
            </div>

            <!-- Nouveau graphique : Argent en circulation par jour -->
            <div class="bg-gray-800 rounded-xl shadow-lg mb-8 p-6">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-4 gap-2">
                    <h3 class="text-lg sm:text-xl font-bold flex items-center gap-2">
                        <i class="fas fa-coins text-cyan-400"></i>
                        Argent en circulation par jour
                    </h3>
                    <form method="get" class="flex items-center gap-2">
                        <label for="signup_days" class="text-sm text-gray-300 font-semibold">Durée :</label>
                        <input type="number" min="1" max="365" name="signup_days" id="signup_days"
                            value="<?php echo $days; ?>"
                            class="bg-gray-900 border border-gray-700 rounded px-2 py-1 w-20 text-gray-100 text-sm focus:outline-none focus:border-blue-500" />
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded font-bold text-sm transition">Afficher</button>
                    </form>
                </div>
                <canvas id="moneyChart" height="60"></canvas>
            </div>

            <script>
            // Graphique comptes créés + connexions
            const ctx = document.getElementById('signupChart').getContext('2d');
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($signup_stats['labels']); ?>,
                    datasets: [
                        {
                            label: 'Comptes créés',
                            data: <?php echo json_encode($signup_stats['signup']); ?>,
                            backgroundColor: 'rgba(59,130,246,0.15)',
                            borderColor: 'rgba(59,130,246,1)',
                            borderWidth: 2,
                            pointBackgroundColor: 'rgba(59,130,246,1)',
                            pointRadius: 4,
                            fill: true,
                            tension: 0.3
                        },
                        {
                            label: 'Connexions',
                            data: <?php echo json_encode($signup_stats['connexion']); ?>,
                            backgroundColor: 'rgba(34,197,94,0.15)',
                            borderColor: 'rgba(34,197,94,1)',
                            borderWidth: 2,
                            pointBackgroundColor: 'rgba(34,197,94,1)',
                            pointRadius: 4,
                            fill: true,
                            tension: 0.3
                        }
                    ]
                },
                options: {
                    scales: {
                        x: { grid: { color: '#374151' }, ticks: { color: '#d1d5db' } },
                        y: { beginAtZero: true, grid: { color: '#374151' }, ticks: { color: '#d1d5db' } }
                    },
                    plugins: {
                        legend: { display: true, labels: { color: '#d1d5db' } },
                        tooltip: { enabled: true }
                    }
                }
            });

            // Graphique commandes par jour
            const ordersCtx = document.getElementById('ordersChart').getContext('2d');
            new Chart(ordersCtx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($order_labels); ?>,
                    datasets: [
                        {
                            label: 'Commandes',
                            data: <?php echo json_encode($order_data); ?>,
                            backgroundColor: 'rgba(34,197,94,0.5)',
                            borderColor: 'rgba(34,197,94,1)',
                            borderWidth: 1,
                            yAxisID: 'y',
                        }
                    ]
                },
                options: {
                    scales: {
                        x: { grid: { color: '#374151' }, ticks: { color: '#d1d5db' } },
                        y: {
                            beginAtZero: true,
                            grid: { color: '#374151' },
                            ticks: { color: '#d1d5db' },
                            title: { display: true, text: 'Commandes', color: '#d1d5db' }
                        }
                    },
                    plugins: {
                        legend: { display: true, labels: { color: '#d1d5db' } },
                        tooltip: { enabled: true }
                    }
                }
            });

            // Nouveau graphique : Argent en circulation par jour
            const moneyCtx = document.getElementById('moneyChart').getContext('2d');
            new Chart(moneyCtx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($order_labels); ?>,
                    datasets: [
                        {
                            label: 'Argent en circulation',
                            data: <?php echo json_encode($order_money); ?>,
                            borderColor: 'rgba(6,182,212,1)',
                            backgroundColor: 'rgba(6,182,212,0.15)',
                            borderWidth: 3,
                            pointBackgroundColor: 'rgba(6,182,212,1)',
                            pointRadius: 3,
                            fill: true,
                            tension: 0.3
                        }
                    ]
                },
                options: {
                    scales: {
                        x: { grid: { color: '#374151' }, ticks: { color: '#d1d5db' } },
                        y: {
                            beginAtZero: true,
                            grid: { color: '#374151' },
                            ticks: { color: '#06b6d4' },
                            title: { display: true, text: 'Argent en circulation', color: '#06b6d4' }
                        }
                    },
                    plugins: {
                        legend: { display: true, labels: { color: '#d1d5db' } },
                        tooltip: { enabled: true }
                    }
                }
            });
            </script>
        </main>
    </div>
</body>
</html>
