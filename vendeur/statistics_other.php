<?php
require_once '../config.php';
require_once 'check_seller.php';

// Autoriser fondateur, resp_vendeur et admin
if(!in_array($_SESSION['role'], ['fondateur', 'resp_vendeur', 'admin'])) {
    header('Location: statistics.php');
    exit;
}

if(!isset($_GET['seller_id']) || !is_numeric($_GET['seller_id'])) {
    echo "<div style='color:red;padding:2rem;'>Aucun vendeur sélectionné.</div>";
    exit;
}

$seller_id = intval($_GET['seller_id']);

// Vérifier que le vendeur existe et est bien un vendeur
$stmt = $pdo->prepare("SELECT username, role FROM users WHERE id = ? AND role IN ('vendeur_test','vendeur','vendeur_confirme','vendeur_senior','fondateur','resp_vendeur')");
$stmt->execute([$seller_id]);
$user = $stmt->fetch();
if(!$user) {
    echo "<div style='color:red;padding:2rem;'>Vendeur introuvable.</div>";
    exit;
}

// Récupération des stats (copie logique de statistics.php, mais avec $seller_id)
// Total commandes clôturées
$stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE seller_id = ? AND status = 'clos'");
$stmt->execute([$seller_id]);
$total_orders = $stmt->fetchColumn();

// Revenu total sur commandes clôturées (total - discount_amount)
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(o.total - o.discount_amount),0)
    FROM orders o
    WHERE o.seller_id = ? AND o.status = 'clos'
");
$stmt->execute([$seller_id]);
$total_revenue = $stmt->fetchColumn();

// Panier moyen
$avg_order_value = $total_orders > 0 ? $total_revenue / $total_orders : 0;

// Articles vendus
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(oi.quantity),0)
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.id
    WHERE o.seller_id = ? AND o.status = 'clos'
");
$stmt->execute([$seller_id]);
$total_items_sold = $stmt->fetchColumn();

// Commandes clôturées aujourd'hui
$stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE seller_id = ? AND status = 'clos' AND DATE(created_at) = CURDATE()");
$stmt->execute([$seller_id]);
$today_orders = $stmt->fetchColumn();

// Revenu aujourd'hui (total - discount_amount)
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(o.total - o.discount_amount),0)
    FROM orders o
    WHERE o.seller_id = ? AND o.status = 'clos' AND DATE(o.created_at) = CURDATE()
");
$stmt->execute([$seller_id]);
$today_revenue = $stmt->fetchColumn();

// Commandes clôturées ce mois
$stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE seller_id = ? AND status = 'clos' AND MONTH(created_at) = MONTH(NOW())");
$stmt->execute([$seller_id]);
$month_orders = $stmt->fetchColumn();

// Revenu ce mois (total - discount_amount)
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(o.total - o.discount_amount),0)
    FROM orders o
    WHERE o.seller_id = ? AND o.status = 'clos' AND MONTH(o.created_at) = MONTH(NOW())
");
$stmt->execute([$seller_id]);
$month_revenue = $stmt->fetchColumn();

// Statistique code promo : total discount_amount sur commandes clôturées
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(o.discount_amount),0)
    FROM orders o
    WHERE o.seller_id = ? AND o.status = 'clos' AND o.discount_amount > 0
");
$stmt->execute([$seller_id]);
$total_discount_given = $stmt->fetchColumn();

// Revenus des 30 derniers jours (pour le graphique, total - discount_amount)
$stmt = $pdo->prepare("
    SELECT DATE(o.created_at) as date, COUNT(DISTINCT o.id) as orders, COALESCE(SUM(o.total - o.discount_amount),0) as revenue
    FROM orders o
    WHERE o.seller_id = ? AND o.status = 'clos' AND o.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY date
    ORDER BY date ASC
");
$stmt->execute([$seller_id]);
$daily_stats = [];
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $daily_stats[] = [
        'date' => $row['date'],
        'orders' => $row['orders'],
        'revenue' => $row['revenue']
    ];
}

// Top 10 clients qui dépensent le plus sur commandes clôturées (total - discount_amount)
$stmt = $pdo->prepare("
    SELECT 
        u.username, 
        o.user_id, 
        COUNT(DISTINCT o.id) as orders_count,
        SUM(o.total - o.discount_amount) as total_spent
    FROM orders o
    JOIN users u ON o.user_id = u.id
    WHERE o.seller_id = ? AND o.status = 'clos'
    GROUP BY o.user_id
    ORDER BY total_spent DESC
    LIMIT 10
");
$stmt->execute([$seller_id]);
$top_clients = [];
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $top_clients[] = [
        'username' => $row['username'],
        'user_id' => $row['user_id'],
        'orders_count' => $row['orders_count'],
        'total_spent' => $row['total_spent']
    ];
}

// Préparation des stats pour affichage
$stats = [
    'total_orders' => $total_orders,
    'total_revenue' => $total_revenue,
    'avg_order_value' => $avg_order_value,
    'total_items_sold' => $total_items_sold,
    'today_orders' => $today_orders,
    'today_revenue' => $today_revenue,
    'month_orders' => $month_orders,
    'month_revenue' => $month_revenue,
    'total_discount_given' => $total_discount_given
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stats vendeur - <?php echo htmlspecialchars($user['username']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-900 text-gray-100">
    <div class="flex min-h-screen">
        <?php include 'sidebar.php'; ?>
        <main class="flex-1 p-8">
            <div class="container mx-auto">
                <div class="mb-8">
                    <h1 class="text-4xl font-bold mb-2">
                        <i class="fas fa-chart-line text-purple-500 mr-3"></i>
                        Statistiques de <?php echo htmlspecialchars($user['username']); ?>
                    </h1>
                    <p class="text-gray-400">Rôle : <?php echo htmlspecialchars($user['role']); ?></p>
                </div>

                <!-- Stats rapides -->
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6 mb-8 overflow-x-auto">
                    <div class="bg-gradient-to-br from-blue-600 to-blue-800 rounded-xl p-6 w-full min-w-[220px]">
                        <p class="text-blue-200 text-sm mb-1">Total Commandes</p>
                        <p class="text-4xl font-bold"><?php echo $stats['total_orders']; ?></p>
                    </div>
                    <div class="bg-gradient-to-br from-green-600 to-green-800 rounded-xl p-6 w-full min-w-[220px]">
                        <p class="text-green-200 text-sm mb-1">Revenu Total</p>
                        <p class="text-4xl font-bold"><?php echo number_format($stats['total_revenue'], 2); ?>€</p>
                    </div>
                    <div class="bg-gradient-to-br from-purple-600 to-purple-800 rounded-xl p-6 w-full min-w-[220px]">
                        <p class="text-purple-200 text-sm mb-1">Panier Moyen</p>
                        <p class="text-4xl font-bold"><?php echo number_format($stats['avg_order_value'], 2); ?>€</p>
                    </div>
                    <div class="bg-gradient-to-br from-orange-600 to-orange-800 rounded-xl p-6 w-full min-w-[220px]">
                        <p class="text-orange-200 text-sm mb-1">Aujourd'hui</p>
                        <p class="text-4xl font-bold"><?php echo $stats['today_orders']; ?></p>
                        <p class="text-xs text-orange-200 mt-1"><?php echo number_format($stats['today_revenue'], 2); ?>€</p>
                    </div>
                    <div class="bg-gradient-to-br from-pink-600 to-pink-800 rounded-xl p-6 w-full min-w-[220px]">
                        <p class="text-pink-200 text-sm mb-1">Ce mois</p>
                        <p class="text-4xl font-bold"><?php echo $stats['month_orders']; ?></p>
                        <p class="text-xs text-pink-200 mt-1"><?php echo number_format($stats['month_revenue'], 2); ?>€</p>
                    </div>
                    <div class="bg-gradient-to-br from-yellow-600 to-yellow-800 rounded-xl p-6 w-full min-w-[220px]">
                        <p class="text-yellow-200 text-sm mb-1">Articles vendus</p>
                        <p class="text-4xl font-bold"><?php echo $stats['total_items_sold']; ?></p>
                    </div>
                    <div class="bg-gradient-to-br from-indigo-600 to-indigo-800 rounded-xl p-6 w-full min-w-[220px]">
                        <p class="text-indigo-200 text-sm mb-1">Argent des code promo utilisé</p>
                        <p class="text-4xl font-bold"><?php echo number_format($stats['total_discount_given'], 2); ?>€</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                    <!-- Graphique des revenus -->
                    <div class="bg-gray-800 rounded-xl p-6">
                        <h2 class="text-xl font-bold mb-4 flex items-center gap-2">
                            <i class="fas fa-chart-area text-purple-500"></i>
                            Revenus des 30 derniers jours
                        </h2>
                        <div style="height: 300px; position: relative;">
                            <canvas id="revenueChart"></canvas>
                        </div>
                    </div>

                    <!-- Top clients -->
                    <div class="bg-gray-800 rounded-xl p-6">
                        <h2 class="text-xl font-bold mb-4 flex items-center gap-2">
                            <i class="fas fa-trophy text-yellow-500"></i>
                            Top 10 Clients (plus gros dépensiers)
                        </h2>
                        <div class="space-y-3 max-h-96 overflow-y-auto">
                            <?php foreach($top_clients as $index => $client): ?>
                            <div class="flex items-center justify-between bg-gray-700 rounded-lg p-3">
                                <div class="flex items-center gap-3">
                                    <span class="text-2xl font-bold text-purple-400">#<?php echo $index + 1; ?></span>
                                    <div class="flex items-center gap-4">
                                        <p class="font-semibold"><?php echo htmlspecialchars($client['username']); ?></p>
                                        <span class="text-xs text-gray-400 bg-gray-800 px-2 py-1 rounded">Commandes : <?php echo $client['orders_count']; ?></span>
                                    </div>
                                </div>
                                <span class="font-bold text-green-400"><?php echo number_format($client['total_spent'], 2); ?>€</span>
                            </div>
                            <?php endforeach; ?>

                            <?php if(empty($top_clients)): ?>
                            <p class="text-center text-gray-500 py-8">Aucune donnée disponible</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Tableau détaillé -->
                <div class="bg-gray-800 rounded-xl p-6">
                    <h2 class="text-xl font-bold mb-4 flex items-center gap-2">
                        <i class="fas fa-table text-blue-500"></i>
                        Détails par jour
                    </h2>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-900">
                                <tr>
                                    <th class="px-4 py-3 text-left">Date</th>
                                    <th class="px-4 py-3 text-center">Commandes</th>
                                    <th class="px-4 py-3 text-right">Revenu</th>
                                    <th class="px-4 py-3 text-right">Panier Moyen</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-700">
                                <?php foreach($daily_stats as $day): ?>
                                <tr class="hover:bg-gray-700/50">
                                    <td class="px-4 py-3"><?php echo date('d/m/Y', strtotime($day['date'])); ?></td>
                                    <td class="px-4 py-3 text-center font-bold text-blue-400"><?php echo $day['orders']; ?></td>
                                    <td class="px-4 py-3 text-right font-bold text-green-400"><?php echo number_format($day['revenue'], 2); ?>€</td>
                                    <td class="px-4 py-3 text-right font-bold text-purple-400"><?php echo number_format($day['revenue'] / $day['orders'], 2); ?>€</td>
                                </tr>
                                <?php endforeach; ?>

                                <?php if(empty($daily_stats)): ?>
                                <tr>
                                    <td colspan="4" class="px-4 py-8 text-center text-gray-500">
                                        Aucune donnée disponible
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Graphique des revenus
        const ctx = document.getElementById('revenueChart');
        
        <?php if(!empty($daily_stats)): ?>
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: [<?php 
                    $labels = array_reverse(array_map(function($day) {
                        return date('d/m', strtotime($day['date']));
                    }, $daily_stats));
                    echo "'" . implode("','", $labels) . "'";
                ?>],
                datasets: [{
                    label: 'Revenus (€)',
                    data: [<?php 
                        $revenues = array_reverse(array_map(function($day) {
                            return $day['revenue'];
                        }, $daily_stats));
                        echo implode(',', $revenues);
                    ?>],
                    borderColor: 'rgb(168, 85, 247)',
                    backgroundColor: 'rgba(168, 85, 247, 0.1)',
                    tension: 0.4,
                    fill: true,
                    borderWidth: 2,
                    pointRadius: 3,
                    pointHoverRadius: 5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        labels: { 
                            color: '#fff',
                            font: { size: 12 }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return 'Revenus: ' + context.parsed.y.toFixed(2) + '€';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { 
                            color: '#9ca3af',
                            callback: function(value) {
                                return value.toFixed(2) + '€';
                            }
                        },
                        grid: { color: 'rgba(255, 255, 255, 0.05)' }
                    },
                    x: {
                        ticks: { 
                            color: '#9ca3af',
                            maxRotation: 45,
                            minRotation: 45
                        },
                        grid: { color: 'rgba(255, 255, 255, 0.05)' }
                    }
                }
            }
        });
        <?php else: ?>
        // Afficher un message si pas de données
        ctx.getContext('2d').font = '16px Arial';
        ctx.getContext('2d').fillStyle = '#9ca3af';
        ctx.getContext('2d').textAlign = 'center';
        ctx.getContext('2d').fillText('Aucune donnée disponible', ctx.width / 2, ctx.height / 2);
        <?php endif; ?>
    </script>
</body>
</html>