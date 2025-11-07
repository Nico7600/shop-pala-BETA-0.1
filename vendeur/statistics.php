<?php
require_once '../config.php';
require_once 'check_seller.php';

// Vérifier la connexion PDO
if (!isset($pdo) || !$pdo) {
    die("Erreur de connexion à la base de données.");
}

// Vérifier le niveau d'accès
if(!in_array($_SESSION['role'], ['vendeur_test', 'vendeur', 'vendeur_confirme', 'vendeur_senior', 'resp_vendeur', 'fondateur'])) {
    // Accès refusé
    header('Location: index.php');
    exit;
}

// Calcul des statistiques à partir des commandes clôturées
$seller_id = $_SESSION['user_id'];

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
    'total_discount_given' => $total_discount_given // Ajout pour affichage
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Statistiques - Vendeur</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-900 text-gray-100">

    <div class="flex min-h-screen">
        <?php include 'sidebar.php'; ?>

        <main class="flex-1 p-2 sm:p-8 w-full">
            <div class="container mx-auto relative">
                <div class="mb-4 sm:mb-8 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-2 sm:gap-0">
                    <div>
                        <h1 class="text-2xl sm:text-4xl font-bold mb-2">
                            <i class="fas fa-chart-line text-purple-500 mr-2 sm:mr-3"></i>
                            Statistiques de Ventes
                        </h1>
                        <p class="text-gray-400 text-xs sm:text-base">Analysez vos performances commerciales</p>
                    </div>
                    <?php if(in_array($_SESSION['role'], ['fondateur', 'resp_vendeur', 'admin'])): ?>
                    <button onclick="openSellerModal()" class="bg-gradient-to-r from-purple-600 to-pink-600 hover:from-purple-700 hover:to-pink-700 text-white font-bold px-3 sm:px-4 py-2 sm:py-2 rounded transition flex items-center gap-2 shadow-lg text-xs sm:text-base">
                        <i class="fas fa-user-search"></i>
                        Voir les stats d'un vendeur
                    </button>
                    <?php endif; ?>
                </div>

                <!-- Modal popup choix vendeur -->
                <?php if(in_array($_SESSION['role'], ['fondateur', 'resp_vendeur', 'admin'])): ?>
                <div id="sellerModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden p-2 sm:p-0">
                    <div class="bg-gray-900 rounded-xl p-4 sm:p-8 shadow-2xl w-full max-w-full sm:max-w-md relative">
                        <button onclick="closeSellerModal()" class="absolute top-2 sm:top-3 right-2 sm:right-3 text-gray-400 hover:text-red-400 text-lg sm:text-xl">
                            <i class="fas fa-times"></i>
                        </button>
                        <h2 class="text-lg sm:text-2xl font-bold mb-2 sm:mb-4 text-purple-400 flex items-center gap-2">
                            <i class="fas fa-user-search"></i>
                            Choisir un vendeur
                        </h2>
                        <form action="statistics_other.php" method="get" class="flex flex-col gap-2 sm:gap-4">
                            <label for="seller_id" class="font-semibold text-gray-300 text-xs sm:text-base">Sélectionnez le vendeur :</label>
                            <select name="seller_id" id="seller_id" class="px-2 sm:px-3 py-2 rounded bg-gray-800 border border-purple-500 text-white w-full text-xs sm:text-base" required>
                                <option value="" disabled selected>Choisissez...</option>
                                <?php
                                // Inclure fondateur et resp_vendeur dans la liste des vendeurs
                                $vendeurs = $pdo->query("SELECT id, username, role FROM users WHERE role IN ('vendeur_test','vendeur','vendeur_confirme','vendeur_senior','fondateur','resp_vendeur') ORDER BY username ASC")->fetchAll();
                                foreach($vendeurs as $v):
                                ?>
                                <option value="<?php echo $v['id']; ?>">
                                    <?php echo htmlspecialchars($v['username']) . " (" . $v['role'] . " #" . $v['id'] . ")"; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="bg-gradient-to-r from-purple-600 to-pink-600 hover:from-purple-700 hover:to-pink-700 text-white font-bold px-3 sm:px-4 py-2 rounded transition flex items-center gap-2 text-xs sm:text-base">
                                <i class="fas fa-chart-bar"></i>
                                Voir les stats
                            </button>
                        </form>
                    </div>
                </div>
                <script>
                function openSellerModal() {
                    document.getElementById('sellerModal').classList.remove('hidden');
                }
                function closeSellerModal() {
                    document.getElementById('sellerModal').classList.add('hidden');
                }
                // Fermer la modal si clic en dehors
                document.addEventListener('click', function(e) {
                    const modal = document.getElementById('sellerModal');
                    if (!modal.classList.contains('hidden') && !modal.contains(e.target) && !e.target.closest('button[onclick="openSellerModal()"]')) {
                        closeSellerModal();
                    }
                });
                </script>
                <?php endif; ?>

                <!-- Stats rapides -->
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-2 sm:gap-6 mb-4 sm:mb-8 overflow-x-auto">
                    <div class="bg-gradient-to-br from-blue-600 to-blue-800 rounded-xl p-2 sm:p-6 w-full min-w-[180px] sm:min-w-[220px]">
                        <p class="text-blue-200 text-xs sm:text-sm mb-1">Total Commandes</p>
                        <p class="text-2xl sm:text-4xl font-bold"><?php echo $stats['total_orders']; ?></p>
                    </div>
                    <div class="bg-gradient-to-br from-green-600 to-green-800 rounded-xl p-2 sm:p-6 w-full min-w-[180px] sm:min-w-[220px]">
                        <p class="text-green-200 text-xs sm:text-sm mb-1">Revenu Total</p>
                        <p class="text-2xl sm:text-4xl font-bold"><?php echo number_format($stats['total_revenue'], 2); ?>$</p>
                    </div>
                    <div class="bg-gradient-to-br from-purple-600 to-purple-800 rounded-xl p-2 sm:p-6 w-full min-w-[180px] sm:min-w-[220px]">
                        <p class="text-purple-200 text-xs sm:text-sm mb-1">Panier Moyen</p>
                        <p class="text-2xl sm:text-4xl font-bold"><?php echo number_format($stats['avg_order_value'], 2); ?>$</p>
                    </div>
                    <div class="bg-gradient-to-br from-orange-600 to-orange-800 rounded-xl p-2 sm:p-6 w-full min-w-[180px] sm:min-w-[220px]">
                        <p class="text-orange-200 text-xs sm:text-sm mb-1">Aujourd'hui</p>
                        <p class="text-2xl sm:text-4xl font-bold"><?php echo $stats['today_orders']; ?></p>
                        <p class="text-xs text-orange-200 mt-1"><?php echo number_format($stats['today_revenue'], 2); ?>$</p>
                    </div>
                    <div class="bg-gradient-to-br from-pink-600 to-pink-800 rounded-xl p-2 sm:p-6 w-full min-w-[180px] sm:min-w-[220px]">
                        <p class="text-pink-200 text-xs sm:text-sm mb-1">Ce mois</p>
                        <p class="text-2xl sm:text-4xl font-bold"><?php echo $stats['month_orders']; ?></p>
                        <p class="text-xs text-pink-200 mt-1"><?php echo number_format($stats['month_revenue'], 2); ?>$</p>
                    </div>
                    <div class="bg-gradient-to-br from-yellow-600 to-yellow-800 rounded-xl p-2 sm:p-6 w-full min-w-[180px] sm:min-w-[220px]">
                        <p class="text-yellow-200 text-xs sm:text-sm mb-1">Articles vendus</p>
                        <p class="text-2xl sm:text-4xl font-bold"><?php echo $stats['total_items_sold']; ?></p>
                    </div>
                    <div class="bg-gradient-to-br from-indigo-600 to-indigo-800 rounded-xl p-2 sm:p-6 w-full min-w-[180px] sm:min-w-[220px]">
                        <p class="text-indigo-200 text-xs sm:text-sm mb-1">Argent des code promo utilisé</p>
                        <p class="text-2xl sm:text-4xl font-bold"><?php echo number_format($stats['total_discount_given'], 2); ?>$</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-2 sm:gap-6 mb-4 sm:mb-8">
                    <!-- Graphique des revenus -->
                    <div class="bg-gray-800 rounded-xl p-2 sm:p-6">
                        <h2 class="text-lg sm:text-xl font-bold mb-2 sm:mb-4 flex items-center gap-2">
                            <i class="fas fa-chart-area text-purple-500"></i>
                            Revenus des 30 derniers jours
                        </h2>
                        <div style="height: 220px; position: relative;">
                            <canvas id="revenueChart"></canvas>
                        </div>
                    </div>

                    <!-- Top clients -->
                    <div class="bg-gray-800 rounded-xl p-2 sm:p-6">
                        <h2 class="text-lg sm:text-xl font-bold mb-2 sm:mb-4 flex items-center gap-2">
                            <i class="fas fa-trophy text-yellow-500"></i>
                            Top 10 Clients (plus gros dépensiers)
                        </h2>
                        <div class="space-y-2 sm:space-y-3 max-h-64 sm:max-h-96 overflow-y-auto">
                            <?php foreach($top_clients as $index => $client): ?>
                            <div class="flex items-center justify-between bg-gray-700 rounded-lg p-2 sm:p-3">
                                <div class="flex items-center gap-2 sm:gap-3">
                                    <span class="text-lg sm:text-2xl font-bold text-purple-400">#<?php echo $index + 1; ?></span>
                                    <div class="flex items-center gap-2 sm:gap-4">
                                        <p class="font-semibold"><?php echo htmlspecialchars($client['username']); ?></p>
                                        <span class="text-xs text-gray-400 bg-gray-800 px-2 py-1 rounded">Commandes : <?php echo $client['orders_count']; ?></span>
                                    </div>
                                </div>
                                <span class="font-bold text-green-400"><?php echo number_format($client['total_spent'], 2); ?>$</span>
                            </div>
                            <?php endforeach; ?>

                            <?php if(empty($top_clients)): ?>
                            <p class="text-center text-gray-500 py-4 sm:py-8 text-xs sm:text-base">Aucune donnée disponible</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Tableau détaillé -->
                <div class="bg-gray-800 rounded-xl p-2 sm:p-6">
                    <h2 class="text-lg sm:text-xl font-bold mb-2 sm:mb-4 flex items-center gap-2">
                        <i class="fas fa-table text-blue-500"></i>
                        Détails par jour
                    </h2>
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs sm:text-base">
                            <thead class="bg-gray-900">
                                <tr>
                                    <th class="px-2 sm:px-4 py-2 sm:py-3 text-left">Date</th>
                                    <th class="px-2 sm:px-4 py-2 sm:py-3 text-center">Commandes</th>
                                    <th class="px-2 sm:px-4 py-2 sm:py-3 text-right">Revenu</th>
                                    <th class="px-2 sm:px-4 py-2 sm:py-3 text-right">Panier Moyen</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-700">
                                <?php foreach($daily_stats as $day): ?>
                                <tr class="hover:bg-gray-700/50">
                                    <td class="px-2 sm:px-4 py-2 sm:py-3"><?php echo date('d/m/Y', strtotime($day['date'])); ?></td>
                                    <td class="px-2 sm:px-4 py-2 sm:py-3 text-center font-bold text-blue-400"><?php echo $day['orders']; ?></td>
                                    <td class="px-2 sm:px-4 py-2 sm:py-3 text-right font-bold text-green-400"><?php echo number_format($day['revenue'], 2); ?>$</td>
                                    <td class="px-2 sm:px-4 py-2 sm:py-3 text-right font-bold text-purple-400"><?php echo number_format($day['revenue'] / $day['orders'], 2); ?>$</td>
                                </tr>
                                <?php endforeach; ?>

                                <?php if(empty($daily_stats)): ?>
                                <tr>
                                    <td colspan="4" class="px-2 sm:px-4 py-4 sm:py-8 text-center text-gray-500 text-xs sm:text-base">
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
                    label: 'Revenus ($)',
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
                                return 'Revenus: ' + context.parsed.y.toFixed(2) + '$';
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
                                return value.toFixed(2) + '$';
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
