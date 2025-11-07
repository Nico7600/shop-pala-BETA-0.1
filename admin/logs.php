<?php
require_once '../config.php';
require_once 'check_admin.php';

// Traitement de la suppression d'un log
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_log'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM activity_logs WHERE id = ?");
        $stmt->execute([$_POST['log_id']]);
        
        $_SESSION['success'] = "Log supprimé avec succès!";
    } catch(PDOException $e) {
        $_SESSION['error'] = "Erreur de suppression : " . $e->getMessage();
    }
    
    // Redirection pour éviter la re-soumission du formulaire
    header('Location: logs.php');
    exit;
}

// Suppression de plusieurs logs
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_selected']) && !empty($_POST['selected_logs'])) {
    try {
        $ids = array_map('intval', $_POST['selected_logs']);
        $in = str_repeat('?,', count($ids) - 1) . '?';
        $stmt = $pdo->prepare("DELETE FROM activity_logs WHERE id IN ($in)");
        $stmt->execute($ids);
        $_SESSION['success'] = "Logs sélectionnés supprimés avec succès!";
    } catch(PDOException $e) {
        $_SESSION['error'] = "Erreur de suppression multiple : " . $e->getMessage();
    }
    header('Location: logs.php');
    exit;
}

// Récupérer les messages de session
$success = isset($_SESSION['success']) ? $_SESSION['success'] : null;
$error = isset($_SESSION['error']) ? $_SESSION['error'] : null;
unset($_SESSION['success'], $_SESSION['error']);

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 50;
$offset = ($page - 1) * $per_page;

// Filtres
$filter_user = isset($_GET['user']) ? $_GET['user'] : ''; 
$filter_action = isset($_GET['action']) ? $_GET['action'] : '';

// Construire la requête
$where = [];
$params = [];

if ($filter_user) {
    $where[] = "u.username LIKE ?";
    $params[] = "%$filter_user%";
}

if ($filter_action) {
    $where[] = "al.action = ?";
    $params[] = $filter_action;
}

$where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

// Récupérer les logs
try {
    $stmt = $pdo->prepare("
        SELECT al.*, u.username, u.role 
        FROM activity_logs al
        LEFT JOIN users u ON al.user_id = u.id
        $where_clause
        ORDER BY al.created_at DESC
        LIMIT $per_page OFFSET $offset
    ");
    $stmt->execute($params);
    $logs = $stmt->fetchAll();
    
    // Compter le total
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM activity_logs al
        LEFT JOIN users u ON al.user_id = u.id
        $where_clause
    ");
    $stmt->execute($params);
    $total_logs = $stmt->fetchColumn();
    $total_pages = ceil($total_logs / $per_page);
    
    // Récupérer les actions uniques pour le filtre
    $stmt = $pdo->query("SELECT DISTINCT action FROM activity_logs ORDER BY action");
    $actions = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Statistiques supplémentaires
    $stats_today = $pdo->query("SELECT COUNT(*) FROM activity_logs WHERE DATE(created_at) = CURDATE()")->fetchColumn();
    $stats_week = $pdo->query("SELECT COUNT(*) FROM activity_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
    $stats_month = $pdo->query("SELECT COUNT(*) FROM activity_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();
    
    // Utilisateur le plus actif
    $most_active_user = $pdo->query("
        SELECT u.username, COUNT(*) as count 
        FROM activity_logs al 
        LEFT JOIN users u ON al.user_id = u.id 
        WHERE u.username IS NOT NULL
        GROUP BY al.user_id 
        ORDER BY count DESC 
        LIMIT 1
    ")->fetch();
    
    // Action la plus fréquente
    $most_common_action = $pdo->query("
        SELECT action, COUNT(*) as count 
        FROM activity_logs 
        GROUP BY action 
        ORDER BY count DESC 
        LIMIT 1
    ")->fetch();
    
} catch(PDOException $e) {
    $logs = [];
    $total_pages = 0;
    $error = "Erreur : " . $e->getMessage();
    $stats_today = 0;
    $stats_week = 0;
    $stats_month = 0;
    $most_active_user = null;
    $most_common_action = null;
}

// Fonction pour formater l'action
function formatAction($action) {
    $actions_map = [
        'delete_product' => ['text' => 'Suppression produit', 'color' => 'red', 'icon' => 'trash'],
        'edit_product' => ['text' => 'Modification produit', 'color' => 'blue', 'icon' => 'edit'],
        'add_product' => ['text' => 'Ajout produit', 'color' => 'green', 'icon' => 'plus'],
        'login' => ['text' => 'Connexion', 'color' => 'cyan', 'icon' => 'sign-in-alt'],
        'logout' => ['text' => 'Déconnexion', 'color' => 'gray', 'icon' => 'sign-out-alt'],
        'update_user' => ['text' => 'Modification utilisateur', 'color' => 'purple', 'icon' => 'user-edit'],
        'delete_user' => ['text' => 'Suppression utilisateur', 'color' => 'red', 'icon' => 'user-times'],
    ];
    
    return $actions_map[$action] ?? ['text' => ucfirst($action), 'color' => 'gray', 'icon' => 'info-circle'];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logs d'activité - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="bg-gray-900 text-gray-100">

    <div class="flex flex-col md:flex-row min-h-screen">
        <?php include 'sidebar.php'; ?>

        <main class="flex-1 p-2 sm:p-8 w-full">
            <div class="container mx-auto">
                <div class="mb-4 sm:mb-8">
                    <h1 class="text-2xl sm:text-4xl font-bold mb-2">
                        <i class="fas fa-history text-cyan-500 mr-3"></i>
                        Logs d'activité
                    </h1>
                    <p class="text-gray-400 text-sm sm:text-base">Historique complet des actions effectuées sur le site</p>
                </div>

                <?php if($success): ?>
                <div class="bg-green-500/20 border border-green-500 text-green-400 px-4 sm:px-6 py-3 sm:py-4 rounded-lg mb-4 sm:mb-6 text-xs sm:text-base">
                    <i class="fas fa-check-circle mr-2"></i><?php echo htmlspecialchars($success); ?>
                </div>
                <?php endif; ?>

                <?php if($error): ?>
                <div class="bg-red-500/20 border border-red-500 text-red-400 px-4 sm:px-6 py-3 sm:py-4 rounded-lg mb-4 sm:mb-6 text-xs sm:text-base">
                    <i class="fas fa-exclamation-circle mr-2"></i><?php echo htmlspecialchars($error); ?>
                </div>
                <?php endif; ?>

                <!-- Filtres -->
                <div class="bg-gray-800 rounded-xl p-2 sm:p-6 mb-4 sm:mb-6 shadow-lg">
                    <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-2 sm:gap-4 text-xs sm:text-base">
                        <div>
                            <label class="block font-bold mb-2">
                                <i class="fas fa-user mr-2 text-blue-400"></i>Utilisateur
                            </label>
                            <input type="text" name="user" value="<?php echo htmlspecialchars($filter_user); ?>" placeholder="Nom d'utilisateur..." class="w-full bg-gray-700 border border-gray-600 rounded-lg px-2 sm:px-4 py-1 sm:py-2 focus:outline-none focus:border-blue-500">
                        </div>
                        <div>
                            <label class="block font-bold mb-2">
                                <i class="fas fa-filter mr-2 text-purple-400"></i>Action
                            </label>
                            <select name="action" class="w-full bg-gray-700 border border-gray-600 rounded-lg px-2 sm:px-4 py-1 sm:py-2 focus:outline-none focus:border-purple-500">
                                <option value="">Toutes les actions</option>
                                <?php foreach($actions as $action): ?>
                                <option value="<?php echo htmlspecialchars($action); ?>" <?php echo $filter_action === $action ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars(formatAction($action)['text']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="flex items-end gap-2">
                            <button type="submit" class="flex-1 bg-gradient-to-r from-purple-600 to-pink-600 hover:from-purple-700 hover:to-pink-700 px-4 sm:px-6 py-1 sm:py-2 rounded-lg font-bold transition-all text-xs sm:text-base">
                                <i class="fas fa-search mr-2"></i>Filtrer
                            </button>
                            <a href="logs.php" class="bg-gray-700 hover:bg-gray-600 px-2 sm:px-4 py-1 sm:py-2 rounded-lg transition-all text-xs sm:text-base">
                                <i class="fas fa-times"></i>
                            </a>
                        </div>
                    </form>
                </div>

                <!-- Statistiques -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6 mb-4 sm:mb-6">
                    <div class="bg-gradient-to-br from-cyan-600 to-blue-600 rounded-xl p-4 sm:p-6 shadow-lg">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-cyan-100 text-xs sm:text-sm mb-1">Total logs</p>
                                <p class="text-2xl sm:text-3xl font-bold text-white"><?php echo number_format($total_logs); ?></p>
                            </div>
                            <i class="fas fa-database text-3xl sm:text-4xl text-white/30"></i>
                        </div>
                    </div>
                    <div class="bg-gradient-to-br from-green-600 to-emerald-600 rounded-xl p-4 sm:p-6 shadow-lg">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-green-100 text-xs sm:text-sm mb-1">Aujourd'hui</p>
                                <p class="text-2xl sm:text-3xl font-bold text-white"><?php echo number_format($stats_today); ?></p>
                            </div>
                            <i class="fas fa-calendar-day text-3xl sm:text-4xl text-white/30"></i>
                        </div>
                    </div>
                    <div class="bg-gradient-to-br from-purple-600 to-pink-600 rounded-xl p-4 sm:p-6 shadow-lg">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-purple-100 text-xs sm:text-sm mb-1">Cette semaine</p>
                                <p class="text-2xl sm:text-3xl font-bold text-white"><?php echo number_format($stats_week); ?></p>
                            </div>
                            <i class="fas fa-calendar-week text-3xl sm:text-4xl text-white/30"></i>
                        </div>
                    </div>
                    <div class="bg-gradient-to-br from-orange-600 to-red-600 rounded-xl p-4 sm:p-6 shadow-lg">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-orange-100 text-xs sm:text-sm mb-1">Ce mois</p>
                                <p class="text-2xl sm:text-3xl font-bold text-white"><?php echo number_format($stats_month); ?></p>
                            </div>
                            <i class="fas fa-calendar-alt text-3xl sm:text-4xl text-white/30"></i>
                        </div>
                    </div>
                </div>

                <!-- Informations supplémentaires -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-6 mb-4 sm:mb-6">
                    <div class="bg-gray-800 rounded-xl p-4 sm:p-6 shadow-lg border-l-4 border-blue-500">
                        <div class="flex items-center mb-2 sm:mb-3">
                            <i class="fas fa-trophy text-blue-500 text-xl sm:text-2xl mr-2 sm:mr-3"></i>
                            <h3 class="text-lg sm:text-xl font-bold">Utilisateur le plus actif</h3>
                        </div>
                        <?php if($most_active_user): ?>
                            <p class="text-gray-300 text-base sm:text-lg">
                                <span class="text-blue-400 font-bold"><?php echo htmlspecialchars($most_active_user['username']); ?></span>
                                <span class="text-xs sm:text-sm text-gray-500 ml-2">(<?php echo number_format($most_active_user['count']); ?> actions)</span>
                            </p>
                        <?php else: ?>
                            <p class="text-gray-500 italic">Aucune donnée disponible</p>
                        <?php endif; ?>
                    </div>
                    <div class="bg-gray-800 rounded-xl p-4 sm:p-6 shadow-lg border-l-4 border-purple-500">
                        <div class="flex items-center mb-2 sm:mb-3">
                            <i class="fas fa-chart-bar text-purple-500 text-xl sm:text-2xl mr-2 sm:mr-3"></i>
                            <h3 class="text-lg sm:text-xl font-bold">Action la plus fréquente</h3>
                        </div>
                        <?php if($most_common_action): ?>
                            <?php $action_info = formatAction($most_common_action['action']); ?>
                            <p class="text-gray-300 text-base sm:text-lg">
                                <span class="text-purple-400 font-bold"><?php echo htmlspecialchars($action_info['text']); ?></span>
                                <span class="text-xs sm:text-sm text-gray-500 ml-2">(<?php echo number_format($most_common_action['count']); ?> fois)</span>
                            </p>
                        <?php else: ?>
                            <p class="text-gray-500 italic">Aucune donnée disponible</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Liste des logs -->
                <form method="POST" id="multiDeleteForm">
                <div class="bg-gray-800 rounded-xl shadow-lg overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs sm:text-base">
                            <thead class="bg-gray-700">
                                <tr>
                                    <th class="px-2 sm:px-3 py-2 sm:py-4 text-center">
                                        <input type="checkbox" id="selectAll" onclick="toggleAllLogs(this)">
                                    </th>
                                    <th class="px-2 sm:px-6 py-2 sm:py-4 text-left font-bold text-gray-300 uppercase">Date & Heure</th>
                                    <th class="px-2 sm:px-6 py-2 sm:py-4 text-left font-bold text-gray-300 uppercase">Utilisateur</th>
                                    <th class="px-2 sm:px-6 py-2 sm:py-4 text-left font-bold text-gray-300 uppercase">Action</th>
                                    <th class="px-2 sm:px-6 py-2 sm:py-4 text-left font-bold text-gray-300 uppercase hidden sm:table-cell">Détails</th>
                                    <th class="px-2 sm:px-6 py-2 sm:py-4 text-center font-bold text-gray-300 uppercase">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-700">
                                <?php foreach($logs as $log): 
                                    $action_info = formatAction($log['action']);
                                ?>
                                <tr class="hover:bg-gray-700/50 transition-colors">
                                    <td class="px-2 sm:px-3 py-2 sm:py-4 text-center">
                                        <input type="checkbox" name="selected_logs[]" value="<?php echo $log['id']; ?>" class="log-checkbox">
                                    </td>
                                    <td class="px-2 sm:px-6 py-2 sm:py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <i class="fas fa-clock text-gray-400 mr-2"></i>
                                            <span class="text-xs sm:text-sm"><?php echo date('d/m/Y H:i:s', strtotime($log['created_at'])); ?></span>
                                        </div>
                                    </td>
                                    <td class="px-2 sm:px-6 py-2 sm:py-4">
                                        <div class="flex items-center">
                                            <i class="fas fa-user text-blue-400 mr-2"></i>
                                            <div>
                                                <p class="font-semibold"><?php echo htmlspecialchars($log['username'] ?? 'Utilisateur supprimé'); ?></p>
                                                <?php if($log['role']): ?>
                                                <p class="text-xs text-gray-400"><?php echo htmlspecialchars($log['role']); ?></p>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-2 sm:px-6 py-2 sm:py-4">
                                        <span class="inline-flex items-center px-2 sm:px-3 py-1 rounded-full font-bold bg-<?php echo $action_info['color']; ?>-500/20 text-<?php echo $action_info['color']; ?>-400 border border-<?php echo $action_info['color']; ?>-500/30 text-xs sm:text-base">
                                            <i class="fas fa-<?php echo $action_info['icon']; ?> mr-2"></i>
                                            <?php echo htmlspecialchars($action_info['text']); ?>
                                        </span>
                                    </td>
                                    <td class="px-2 sm:px-6 py-2 sm:py-4 hidden sm:table-cell">
                                        <p class="text-xs sm:text-sm text-gray-300"><?php echo htmlspecialchars($log['details']); ?></p>
                                    </td>
                                    <td class="px-2 sm:px-6 py-2 sm:py-4 text-center">
                                        <button type="button" onclick="confirmDelete(<?php echo $log['id']; ?>)" class="bg-red-600 hover:bg-red-700 px-2 sm:px-3 py-1 rounded font-bold transition-colors text-xs sm:text-base">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>

                                <?php if(empty($logs)): ?>
                                <tr>
                                    <td colspan="6" class="px-2 sm:px-6 py-8 sm:py-12 text-center">
                                        <i class="fas fa-inbox text-4xl sm:text-6xl text-gray-600 mb-2 sm:mb-4"></i>
                                        <p class="text-base sm:text-xl font-semibold text-gray-400">Aucun log trouvé</p>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Bouton suppression multiple -->
                    <div class="px-2 sm:px-6 py-2 sm:py-4 bg-gray-700 border-t border-gray-600 flex flex-col sm:flex-row items-center justify-between gap-2 sm:gap-0">
                        <button type="submit" name="delete_selected" class="bg-red-600 hover:bg-red-700 px-4 sm:px-6 py-1 sm:py-2 rounded-lg font-bold text-white transition-all text-xs sm:text-base"
                            onclick="return confirm('Supprimer tous les logs sélectionnés ?')">
                            <i class="fas fa-trash mr-2"></i>Supprimer la sélection
                        </button>
                        <!-- Pagination -->
                        <?php if($total_pages > 1): ?>
                        <div>
                            <div class="text-xs sm:text-sm text-gray-400">
                                Page <?php echo $page; ?> sur <?php echo $total_pages; ?>
                            </div>
                            <div class="flex gap-1 sm:gap-2">
                                <?php if($page > 1): ?>
                                <a href="?page=<?php echo $page - 1; ?>&user=<?php echo urlencode($filter_user); ?>&action=<?php echo urlencode($filter_action); ?>" class="bg-gray-600 hover:bg-gray-500 px-2 sm:px-4 py-1 sm:py-2 rounded-lg transition-all">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                                <?php endif; ?>
                                
                                <?php for($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <a href="?page=<?php echo $i; ?>&user=<?php echo urlencode($filter_user); ?>&action=<?php echo urlencode($filter_action); ?>" class="px-2 sm:px-4 py-1 sm:py-2 rounded-lg transition-all <?php echo $i == $page ? 'bg-purple-600 text-white' : 'bg-gray-600 hover:bg-gray-500'; ?>">
                                    <?php echo $i; ?>
                                </a>
                                <?php endfor; ?>
                                
                                <?php if($page < $total_pages): ?>
                                <a href="?page=<?php echo $page + 1; ?>&user=<?php echo urlencode($filter_user); ?>&action=<?php echo urlencode($filter_action); ?>" class="bg-gray-600 hover:bg-gray-500 px-2 sm:px-4 py-1 sm:py-2 rounded-lg transition-all">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                </form>
            </div>
        </main>
    </div>

    <!-- Modal de confirmation de suppression d'un log -->
    <div id="deleteModal" class="hidden fixed inset-0 bg-black/70 flex items-center justify-center z-50 p-2 sm:p-4">
        <div class="bg-gray-800 rounded-xl max-w-md w-full shadow-2xl">
            <div class="p-4 sm:p-6 text-center">
                <div class="w-12 sm:w-16 h-12 sm:h-16 bg-red-500/20 rounded-full flex items-center justify-center mx-auto mb-2 sm:mb-4">
                    <i class="fas fa-exclamation-triangle text-red-500 text-2xl sm:text-3xl"></i>
                </div>
                <h2 class="text-xl sm:text-2xl font-bold mb-2">Supprimer ce log ?</h2>
                <p class="text-gray-400 mb-4 sm:mb-6 text-xs sm:text-base">
                    Cette action est irréversible.
                </p>
                
                <form method="POST" id="deleteForm">
                    <input type="hidden" name="log_id" id="delete_log_id">
                    <input type="hidden" name="delete_log" value="1">
                    
                    <div class="flex flex-col sm:flex-row gap-2 sm:gap-3">
                        <button type="button" onclick="closeDeleteModal()" class="flex-1 px-4 sm:px-6 py-2 sm:py-3 bg-gray-700 hover:bg-gray-600 rounded-lg font-bold transition-all text-xs sm:text-base">
                            Annuler
                        </button>
                        <button type="submit" class="flex-1 bg-red-600 hover:bg-red-700 px-4 sm:px-6 py-2 sm:py-3 rounded-lg font-bold transition-all text-xs sm:text-base">
                            <i class="fas fa-trash mr-2"></i>Supprimer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function confirmDelete(logId) {
            document.getElementById('delete_log_id').value = logId;
            document.getElementById('deleteModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
            document.body.style.overflow = 'auto';
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeDeleteModal();
            }
        });

        document.getElementById('deleteModal').addEventListener('click', function(e) {
            if (e.target === this) closeDeleteModal();
        });

        // Sélectionner/désélectionner tous les logs
        function toggleAllLogs(source) {
            document.querySelectorAll('.log-checkbox').forEach(cb => {
                cb.checked = source.checked;
            });
        }
    </script>
</body>
</html>
