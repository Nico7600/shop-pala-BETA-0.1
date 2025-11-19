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
    'total_orders' => $pdo->query("SELECT COUNT(*) FROM orders WHERE status != 'cancelled'")->fetchColumn(),
    // Modifié pour calculer total - discount_amount uniquement sur les commandes non annulées
    'total_revenue' => $pdo->query("SELECT SUM(total - discount_amount) FROM orders WHERE status != 'cancelled'")->fetchColumn() ?? 0,
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
$data_logins = []; // Ajout pour connexions via user_logins
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
    // Connexions via user_logins
    $stmt3 = $pdo->prepare("SELECT COUNT(*) FROM user_logins WHERE DATE(login_at) = ?");
    $stmt3->execute([$date]);
    $data_logins[] = (int)$stmt3->fetchColumn();
}
$signup_stats['labels'] = $labels;
$signup_stats['signup'] = $data_signup;
$signup_stats['connexion'] = $data_connexion;
$signup_stats['logins'] = $data_logins; // Ajout

// Connexions par heure sur la journée en cours (toujours 24h, valeurs 0 pour les heures futures)
$connexion_hour_labels = [];
$connexion_hour_data = [];
$currentHour = (int)date('G');
$today = date('Y-m-d');
for ($h = 0; $h < 24; $h++) {
    $label = sprintf('%02dh', $h);
    $connexion_hour_labels[] = $label;
    if ($h <= $currentHour) {
        $hour_start = sprintf('%s %02d:00:00', $today, $h);
        $hour_end = sprintf('%s %02d:59:59', $today, $h);
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_logins WHERE login_at BETWEEN ? AND ?");
        $stmt->execute([$hour_start, $hour_end]);
        $connexion_hour_data[] = (int)$stmt->fetchColumn();
    } else {
        $connexion_hour_data[] = 0;
    }
}

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
    // Comptes les commandes non annulées
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE DATE(created_at) = ? AND status != 'cancelled'");
    $stmt->execute([$date]);
    $order_data[] = (int)$stmt->fetchColumn();
    // Argent en circulation ce jour-là (total - discount_amount sur les commandes non annulées du jour)
    $stmt2 = $pdo->prepare("SELECT COALESCE(SUM(total - discount_amount), 0) FROM orders WHERE DATE(created_at) = ? AND status != 'cancelled'");
    $stmt2->execute([$date]);
    $order_money[] = (float)$stmt2->fetchColumn();
}

// 1. Répartition des rôles utilisateurs triés par niveau (grade)
$role_labels = [];
$role_counts = [];
$role_colors = [];
$role_levels = [];
$role_order = [];
$role_query = $pdo->query("SELECT r.name, r.level, r.color, COUNT(u.id) as count FROM roles r LEFT JOIN users u ON r.name = u.role GROUP BY r.name, r.level, r.color ORDER BY r.level DESC");
while ($row = $role_query->fetch(PDO::FETCH_ASSOC)) {
    $role_labels[] = $row['name'];
    $role_counts[] = (int)$row['count'];
    $role_colors[] = $row['color'] ?: '#888';
    $role_levels[] = (int)$row['level'];
}

// Réorganise "partenaire" avant "licant"
$partenaireIndex = array_search('partenaire', $role_labels);
$licantIndex = array_search('licant', $role_labels);
if ($partenaireIndex !== false && $licantIndex !== false && $partenaireIndex > $licantIndex) {
    // On retire "partenaire" de sa position
    $label = array_splice($role_labels, $partenaireIndex, 1)[0];
    $count = array_splice($role_counts, $partenaireIndex, 1)[0];
    $color = array_splice($role_colors, $partenaireIndex, 1)[0];
    $level = array_splice($role_levels, $partenaireIndex, 1)[0];
    // On l'insère juste avant "licant"
    array_splice($role_labels, $licantIndex, 0, [$label]);
    array_splice($role_counts, $licantIndex, 0, [$count]);
    array_splice($role_colors, $licantIndex, 0, [$color]);
    array_splice($role_levels, $licantIndex, 0, [$level]);
}

// 2. Badges les plus attribués (du plus donné au moins donné, top 20)
$badge_labels = [];
$badge_counts = [];
$badge_query = $pdo->query("SELECT b.name, COUNT(ub.id) as count
    FROM badges b
    LEFT JOIN user_badges ub ON b.id = ub.badge_id
    GROUP BY b.name
    ORDER BY count DESC
    LIMIT 20");
while ($row = $badge_query->fetch(PDO::FETCH_ASSOC)) {
    $badge_labels[] = $row['name'];
    $badge_counts[] = (int)$row['count'];
}

// 3. Utilisation des codes promo par jour (durée personnalisable)
$promo_days = isset($_GET['promo_days']) && is_numeric($_GET['promo_days']) && $_GET['promo_days'] > 0 ? intval($_GET['promo_days']) : 30;
$promo_labels = [];
$promo_data = [];
for ($i = $promo_days - 1; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $promo_labels[] = date('d/m', strtotime($date));
    $stmt = $pdo->prepare("SELECT SUM(current_uses) FROM promo_codes WHERE DATE(created_at) = ?");
    $stmt->execute([$date]);
    $promo_data[] = (int)($stmt->fetchColumn() ?: 0);
}

$role_legend = [];
foreach ($role_labels as $i => $role) {
    $role_legend[] = [
        'name' => $role,
        'count' => $role_counts[$i],
        'color' => $role_colors[$i]
    ];
}
usort($role_legend, function($a, $b) {
    return $b['count'] <=> $a['count'];
});
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
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels"></script>
</head>
<body class="bg-gray-900 text-gray-100">
    <!-- Sidebar -->
    <div class="flex flex-col md:flex-row min-h-screen">
        <?php include 'sidebar.php'; ?>

        <!-- Main Content -->
        <main class="flex-1 p-2 sm:p-8 w-full">
            <div class="mb-4 sm:mb-8">
                <h2 class="text-2xl sm:text-3xl font-bold mb-2">Dashboard</h2>
                <p class="text-gray-400 text-sm sm:text-base">Vue d'ensemble du shop</p>
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

            <!-- Graphique créations de comptes + formulaire durée -->
            <div class="bg-gray-800 rounded-xl shadow-lg mb-8 p-6">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-4 gap-2">
                    <h3 class="text-lg sm:text-xl font-bold flex items-center gap-2">
                        <i class="fas fa-user-plus text-blue-400"></i>
                        Créations de comptes
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

            <!-- Graphique connexions par heure (24h) -->
            <div class="bg-gray-800 rounded-xl shadow-lg mb-8 p-6 relative">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-4 gap-2">
                    <h3 class="text-lg sm:text-xl font-bold flex items-center gap-2">
                        <i class="fas fa-sign-in-alt text-green-400"></i>
                        Connexions (24 dernières heures)
                    </h3>
                    <!-- Timer affiché en haut à droite -->
                    <div id="refresh-timer" class="absolute top-4 right-6 text-xs bg-gray-900/80 px-3 py-1 rounded text-gray-300 font-bold border border-gray-700"></div>
                </div>
                <canvas id="connexionHourChart" height="80"></canvas>
            </div>

            <!-- Graphique commandes par jour -->
            <div class="bg-gray-800 rounded-xl shadow-lg mb-8 p-6">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-4 gap-2">
                    <h3 class="text-lg sm:text-xl font-bold flex items-center gap-2">
                        <i class="fas fa-chart-bar text-green-400"></i>
                        Info commandes
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

            <!-- Graphique : Argent en circulation par jour -->
            <div class="bg-gray-800 rounded-xl shadow-lg mb-8 p-6">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-4 gap-2">
                    <h3 class="text-lg sm:text-xl font-bold flex items-center gap-2">
                        <i class="fas fa-coins text-yellow-400"></i>
                        Argent en circulation par jour
                    </h3>
                    <form method="get" class="flex items-center gap-2">
                        <label for="signup_days" class="text-sm text-gray-300 font-semibold">Durée :</label>
                        <input type="number" min="1" max="365" name="signup_days" id="signup_days"
                            value="<?php echo $days; ?>"
                            class="bg-gray-900 border border-gray-700 rounded px-2 py-1 w-20 text-gray-100 text-sm focus:outline-none focus:border-yellow-500" />
                        <button type="submit" class="bg-yellow-600 hover:bg-yellow-700 text-white px-3 py-1 rounded font-bold text-sm transition">Afficher</button>
                    </form>
                </div>
                <canvas id="moneyChart" height="60"></canvas>
            </div>

            <!-- Graphique : Utilisation des codes promo par jour -->
            <div class="bg-gray-800 rounded-xl shadow-lg mb-8 p-6">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-4 gap-2">
                    <h3 class="text-lg sm:text-xl font-bold flex items-center gap-2">
                        <i class="fas fa-ticket-alt text-pink-400"></i>
                        Utilisation des codes promo par jour
                    </h3>
                    <form method="get" class="flex items-center gap-2">
                        <label for="promo_days" class="text-sm text-gray-300 font-semibold">Durée :</label>
                        <input type="number" min="1" max="365" name="promo_days" id="promo_days"
                            value="<?php echo $promo_days; ?>"
                            class="bg-gray-900 border border-gray-700 rounded px-2 py-1 w-20 text-gray-100 text-sm focus:outline-none focus:border-pink-500" />
                        <?php if(isset($_GET['signup_days'])): ?>
                            <input type="hidden" name="signup_days" value="<?php echo intval($_GET['signup_days']); ?>" />
                        <?php endif; ?>
                        <button type="submit" class="bg-pink-600 hover:bg-pink-700 text-white px-3 py-1 rounded font-bold text-sm transition">Afficher</button>
                    </form>
                </div>
                <canvas id="promoDayChart" height="60"></canvas>
            </div>

            <!-- Container partagé : Répartition des rôles + Badges les plus attribués -->
            <div class="flex flex-col gap-8 mb-8">
                <!-- Catégorie 1 : Répartition des rôles -->
                <!-- Tout le bloc suivant est supprimé -->
                <!-- Catégorie 2 : Badges les plus attribués -->
                <div class="bg-gray-800 rounded-xl shadow-lg p-4 sm:p-6 flex flex-col min-h-[340px]">
                    <h3 class="text-lg sm:text-xl font-bold mb-6 flex items-center gap-2">
                        <i class="fas fa-award text-green-400"></i>
                        Badges les plus attribués
                    </h3>
                    <?php if (empty($badge_labels)): ?>
                        <div class="text-gray-400 text-center py-8">Aucun badge attribué pour le moment.</div>
                    <?php else: ?>
                        <?php
                        $max = min(12, count($badge_labels));
                        $left = ceil($max / 2);
                        ?>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-3 min-w-0">
                            <ol class="list-decimal ml-6 space-y-3 min-w-0 break-words">
                                <?php for ($i = 0; $i < $left; $i++): ?>
                                    <li class="flex items-center justify-between bg-gray-900 rounded px-4 py-2 shadow min-w-0 break-words">
                                        <span class="font-bold text-green-300 min-w-0 break-words"><?php echo htmlspecialchars($badge_labels[$i]); ?></span>
                                        <span class="text-xs text-gray-400 ml-2"><?php echo $badge_counts[$i]; ?> fois</span>
                                    </li>
                                <?php endfor; ?>
                            </ol>
                            <ol start="<?php echo $left + 1; ?>" class="list-decimal ml-6 space-y-3 min-w-0 break-words">
                                <?php for ($i = $left; $i < $max; $i++): ?>
                                    <li class="flex items-center justify-between bg-gray-900 rounded px-4 py-2 shadow min-w-0 break-words">
                                        <span class="font-bold text-green-300 min-w-0 break-words"><?php echo htmlspecialchars($badge_labels[$i]); ?></span>
                                        <span class="text-xs text-gray-400 ml-2"><?php echo $badge_counts[$i]; ?> fois</span>
                                    </li>
                                <?php endfor; ?>
                            </ol>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <script>
            // Actualisation automatique toutes les 5 minutes
            let refreshDelay = 300; // secondes
            let timer = refreshDelay;
            const timerDiv = document.getElementById('refresh-timer');
            function updateTimer() {
                let min = Math.floor(timer / 60);
                let sec = timer % 60;
                timerDiv.textContent = `Actualisation dans ${min}m ${sec < 10 ? '0' : ''}${sec}s`;
                if (timer <= 0) {
                    location.reload();
                } else {
                    timer--;
                    setTimeout(updateTimer, 1000);
                }
            }
            updateTimer();

            // Créations de comptes + connexions (user_logins)
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
                            data: <?php echo json_encode($signup_stats['logins']); ?>,
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

            // Connexions par heure (0h à 23h) - courbe, rouge si heure future
            const currentHour = new Date().getHours();
            const connexionHourCtx = document.getElementById('connexionHourChart').getContext('2d');
            new Chart(connexionHourCtx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($connexion_hour_labels); ?>,
                    datasets: [{
                        label: 'Connexions',
                        data: <?php echo json_encode($connexion_hour_data); ?>,
                        backgroundColor: 'rgba(34,197,94,0.15)',
                        borderColor: function(context) {
                            const idx = context.p0DataIndex;
                            return idx > currentHour ? 'rgba(239,68,68,1)' : 'rgba(34,197,94,1)';
                        },
                        pointBackgroundColor: function(context) {
                            const idx = context.dataIndex;
                            return idx > currentHour ? 'rgba(239,68,68,1)' : 'rgba(34,197,94,1)';
                        },
                        pointBorderColor: function(context) {
                            const idx = context.dataIndex;
                            return idx > currentHour ? 'rgba(239,68,68,1)' : 'rgba(34,197,94,1)';
                        },
                        pointRadius: 4,
                        borderWidth: 2,
                        fill: true,
                        tension: 0.3,
                        segment: {
                            borderColor: ctx => ctx.p0DataIndex > currentHour ? 'rgba(239,68,68,1)' : 'rgba(34,197,94,1)'
                        }
                    }]
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

            // Argent en circulation par jour
            const moneyCtx = document.getElementById('moneyChart').getContext('2d');
            new Chart(moneyCtx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($order_labels); ?>,
                    datasets: [{
                        label: 'Argent en circulation',
                        data: <?php echo json_encode($order_money); ?>,
                        backgroundColor: 'rgba(253,224,71,0.15)',
                        borderColor: 'rgba(253,224,71,1)',
                        borderWidth: 2,
                        pointBackgroundColor: 'rgba(253,224,71,1)',
                        pointRadius: 4,
                        fill: true,
                        tension: 0.3
                    }]
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

            // Utilisation des codes promo par jour
            const promoDayCtx = document.getElementById('promoDayChart').getContext('2d');
            new Chart(promoDayCtx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($promo_labels); ?>,
                    datasets: [{
                        label: 'Codes promo utilisés',
                        data: <?php echo json_encode($promo_data); ?>,
                        backgroundColor: 'rgba(236,72,153,0.15)',
                        borderColor: 'rgba(236,72,153,1)',
                        borderWidth: 2,
                        pointBackgroundColor: 'rgba(236,72,153,1)',
                        pointRadius: 4,
                        fill: true,
                        tension: 0.3
                    }]
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
            </script>
        </main>
    </div>
</body>
</html>
