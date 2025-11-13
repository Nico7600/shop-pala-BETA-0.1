<?php
require_once '../config.php';
require_once 'check_admin.php';

// Désactiver le cache navigateur pour éviter l'affichage d'anciennes données
header("Cache-Control: no-cache, must-revalidate");
header("Expires: Sat, 1 Jul 2000 05:00:00 GMT");

// Fonction pour vérifier si un utilisateur est en ligne (activité dans les 5 dernières minutes)
function isUserOnline($last_activity) {
    if (empty($last_activity)) return false;
    $last_time = strtotime($last_activity);
    $current_time = time();
    return ($current_time - $last_time) < 300; // 5 minutes = 300 secondes
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        try {
            switch ($_POST['action']) {
                case 'update_role':
                    $user_id = (int)$_POST['user_id'];
                    $new_role = $_POST['role'];
                    $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
                    $stmt->execute([$new_role, $user_id]);
                    
                    // Logger l'action
                    $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, details, created_at) VALUES (?, ?, ?, NOW())");
                    $stmt->execute([
                        $_SESSION['user_id'],
                        'update_user',
                        "Modification du rôle de l'utilisateur: " . $_POST['username'] . " (ID: " . $_POST['user_id'] . ") - Nouveau rôle: " . $_POST['role']
                    ]);
                    
                    $success = "Rôle mis à jour avec succès";
                    break;
                    
                case 'toggle_ban':
                    $user_id = (int)$_POST['user_id'];
                    
                    // Récupérer l'état actuel et le nom d'utilisateur
                    $stmt = $pdo->prepare("SELECT username, is_banned FROM users WHERE id = ?");
                    $stmt->execute([$user_id]);
                    $user_info = $stmt->fetch();
                    
                    $stmt = $pdo->prepare("UPDATE users SET is_banned = NOT is_banned WHERE id = ?");
                    $stmt->execute([$user_id]);
                    
                    // Logger l'action
                    $new_status = $user_info['is_banned'] ? 'débanni' : 'banni';
                    $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, details, created_at) VALUES (?, ?, ?, NOW())");
                    $stmt->execute([
                        $_SESSION['user_id'],
                        'update_user',
                        "L'utilisateur " . $user_info['username'] . " (ID: " . $user_id . ") a été " . $new_status
                    ]);
                    
                    // Redirection pour forcer le rechargement et éviter le double POST/affichage
                    header("Location: users.php");
                    exit;
                    
                case 'change_password':
                    $user_id = (int)$_POST['user_id'];
                    $new_password = $_POST['new_password'];
                    
                    // Récupérer le nom d'utilisateur
                    $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
                    $stmt->execute([$user_id]);
                    $username = $stmt->fetchColumn();
                    
                    if (strlen($new_password) >= 6) {
                        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                        $stmt->execute([$hashed, $user_id]);
                        
                        // Logger l'action
                        $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, details, created_at) VALUES (?, ?, ?, NOW())");
                        $stmt->execute([
                            $_SESSION['user_id'],
                            'update_user',
                            "Réinitialisation du mot de passe de l'utilisateur: " . $username . " (ID: " . $user_id . ")"
                        ]);
                        
                        $success = "Mot de passe modifié avec succès";
                    } else {
                        $error = "Le mot de passe doit contenir au moins 6 caractères";
                    }
                    break;
                    
                case 'delete_user':
                    $user_id = (int)$_POST['user_id'];

                    // Récupérer les infos de l'utilisateur avant suppression
                    $stmt = $pdo->prepare("SELECT username, email FROM users WHERE id = ?");
                    $stmt->execute([$user_id]);
                    $user_info = $stmt->fetch();

                    // Supprimer les logs liés à l'utilisateur (évite l'erreur de contrainte)
                    $stmt = $pdo->prepare("DELETE FROM activity_logs WHERE user_id = ?");
                    $stmt->execute([$user_id]);

                    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role NOT IN ('admin', 'fondateur')");
                    $stmt->execute([$user_id]);
                    
                    // Logger l'action (sur l'admin qui supprime, pas sur l'utilisateur supprimé)
                    $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, details, created_at) VALUES (?, ?, ?, NOW())");
                    $stmt->execute([
                        $_SESSION['user_id'],
                        'delete_user',
                        "Suppression de l'utilisateur: " . $user_info['username'] . " (" . $user_info['email'] . ") - ID: " . $user_id
                    ]);
                    
                    $success = "Utilisateur supprimé";
                    break;
            }
        } catch (PDOException $e) {
            $error = "Erreur : " . $e->getMessage();
        }
    }
}

// Récupérer les statistiques
try {
    // Compte les utilisateurs ayant au moins un abonnement actif ou en attente
    $stats['abonnes'] = $pdo->query(
        "SELECT COUNT(DISTINCT user_id) FROM abofac WHERE permissions NOT IN ('Annulé', 'Inactif')"
    )->fetchColumn();

    $stats['total'] = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $stats['fondateurs'] = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'fondateur'")->fetchColumn();
    $stats['resp_vendeur'] = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'resp_vendeur'")->fetchColumn();
    $stats['vendeur_senior'] = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'vendeur_senior'")->fetchColumn();
    $stats['vendeur_confirme'] = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'vendeur_confirme'")->fetchColumn();
    $stats['vendeur_test'] = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'vendeur_test'")->fetchColumn();
    $stats['clients'] = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'client'")->fetchColumn();
    $stats['banned'] = $pdo->query("SELECT COUNT(*) FROM users WHERE is_banned = 1")->fetchColumn();
    $stats['partenaires'] = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'partenaire'")->fetchColumn();
} catch (PDOException $e) {
    error_log("Erreur statistiques utilisateurs : " . $e->getMessage());
    $error = "Erreur de connexion : " . $e->getMessage();
    $stats = [
        'total' => 0, 'fondateurs' => 0, 'resp_vendeur' => 0, 'vendeur_senior' => 0, 'vendeur_confirme' => 0,
        'vendeur_test' => 0, 'clients' => 0, 'banned' => 0, 'abonnes' => 0, 'partenaires' => 0
    ];
}

// Filtres
$search = $_GET['search'] ?? '';
$role_filter = $_GET['role'] ?? '';
$status_filter = $_GET['status'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20; // Nombre d'utilisateurs par page

// Construction de la requête - Version simplifiée sans dépendance à la table orders
$sql = "SELECT u.* FROM users u WHERE 1=1";
$params = [];

if ($search) {
    $sql .= " AND (u.username LIKE ? OR u.email LIKE ? OR u.minecraft_username LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

if ($role_filter) {
    $sql .= " AND u.role = ?";
    $params[] = $role_filter;
}

if ($status_filter === 'banned') {
    $sql .= " AND u.is_banned = 1";
} elseif ($status_filter === 'active') {
    $sql .= " AND u.is_banned = 0";
}

// Compter le total pour la pagination
$count_sql = "SELECT COUNT(*) " . substr($sql, strpos($sql, 'FROM'));
try {
    $count_stmt = $pdo->prepare($count_sql);
    $count_stmt->execute($params);
    $total_users = $count_stmt->fetchColumn();
    $total_pages = max(1, ceil($total_users / $per_page));
    $page = min($page, $total_pages);
} catch (PDOException $e) {
    $total_users = 0;
    $total_pages = 1;
}

$sql .= " ORDER BY 
    CASE u.role 
        WHEN 'fondateur' THEN 1
        WHEN 'resp_vendeur' THEN 2
        WHEN 'vendeur_senior' THEN 3
        WHEN 'vendeur_confirme' THEN 4
        WHEN 'vendeur_test' THEN 5
        WHEN 'client' THEN 6
        ELSE 7
    END,
    u.created_at DESC";

// Ajouter la limitation pour la pagination
$offset = ($page - 1) * $per_page;
$sql .= " LIMIT $per_page OFFSET $offset";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll();

    // Récupérer les abonnements pour chaque utilisateur
    $user_ids = array_column($users, 'id');
    $abos = [];
    if ($user_ids) {
        $in = implode(',', array_map('intval', $user_ids));
        $abo_stmt = $pdo->query("SELECT * FROM abofac WHERE user_id IN ($in) ORDER BY id DESC");
        foreach ($abo_stmt->fetchAll() as $abo) {
            // On ne garde que le dernier abonnement par user_id
            if (!isset($abos[$abo['user_id']])) {
                $abos[$abo['user_id']] = $abo;
            }
        }
    }
} catch (PDOException $e) {
    error_log("Erreur récupération utilisateurs : " . $e->getMessage());
    $error = "Erreur lors de la récupération des utilisateurs : " . $e->getMessage();
    $users = [];
    $abos = [];
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
</head>
<body class="bg-gray-900 text-gray-100">
    <div class="flex flex-col md:flex-row min-h-screen">
        <?php include 'sidebar.php'; ?>
        <main class="flex-1 p-2 sm:p-8 w-full">
            <div class="container mx-auto">
                <!-- En-tête -->
                <div class="mb-4 sm:mb-8">
                    <h1 class="text-2xl sm:text-4xl font-bold mb-2">
                        <i class="fas fa-users text-purple-500 mr-2 sm:mr-3"></i>
                        Gestion des Utilisateurs
                    </h1>
                    <p class="text-gray-400 text-xs sm:text-base">Gérez les utilisateurs et leurs permissions</p>
                </div>

                <?php if(isset($success)): ?>
                <div class="bg-green-500/20 border border-green-500 text-green-400 px-2 sm:px-6 py-2 sm:py-4 rounded-lg mb-4 sm:mb-6 text-xs sm:text-base">
                    <i class="fas fa-check-circle mr-2"></i><?php echo htmlspecialchars($success); ?>
                </div>
                <?php endif; ?>

                <?php if(isset($error)): ?>
                <div class="bg-red-500/20 border border-red-500 text-red-400 px-2 sm:px-6 py-2 sm:py-4 rounded-lg mb-4 sm:mb-6 text-xs sm:text-base">
                    <i class="fas fa-exclamation-circle mr-2"></i><?php echo htmlspecialchars($error); ?>
                </div>
                <?php endif; ?>

                <!-- Statistiques -->
                <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-10 gap-2 sm:gap-4 mb-4 sm:mb-8">
                    <div class="bg-gray-800 rounded-xl p-4 border-2 border-purple-500/30 hover:border-purple-500/50 transition-all">
                        <div class="text-center">
                            <i class="fas fa-users text-3xl text-purple-500 mb-2"></i>
                            <p class="text-gray-400 text-xs mb-1">Total</p>
                            <p class="text-2xl font-bold text-purple-400"><?php echo $stats['total']; ?></p>
                        </div>
                    </div>
                    <div class="bg-gray-800 rounded-xl p-4 border-2 border-yellow-500/30 hover:border-yellow-500/50 transition-all">
                        <div class="text-center">
                            <i class="fas fa-crown text-3xl text-yellow-500 mb-2"></i>
                            <p class="text-gray-400 text-xs mb-1">Fondateurs</p>
                            <p class="text-2xl font-bold text-yellow-400"><?php echo $stats['fondateurs']; ?></p>
                        </div>
                    </div>
                    <div class="bg-gray-800 rounded-xl p-4 border-2 border-purple-600/30 hover:border-purple-600/50 transition-all">
                        <div class="text-center">
                            <i class="fas fa-user-shield text-3xl text-purple-600 mb-2"></i>
                            <p class="text-gray-400 text-xs mb-1">Resp. Vendeur</p>
                            <p class="text-2xl font-bold text-purple-400"><?php echo $stats['resp_vendeur']; ?></p>
                        </div>
                    </div>
                    <div class="bg-gray-800 rounded-xl p-4 border-2 border-amber-500/30 hover:border-amber-500/50 transition-all">
                        <div class="text-center">
                            <i class="fas fa-medal text-3xl text-amber-500 mb-2"></i>
                            <p class="text-gray-400 text-xs mb-1">V. Senior</p>
                            <p class="text-2xl font-bold text-amber-400"><?php echo $stats['vendeur_senior']; ?></p>
                        </div>
                    </div>
                    <div class="bg-gray-800 rounded-xl p-4 border-2 border-green-500/30 hover:border-green-500/50 transition-all">
                        <div class="text-center">
                            <i class="fas fa-check-circle text-3xl text-green-500 mb-2"></i>
                            <p class="text-gray-400 text-xs mb-1">V. Confirmé</p>
                            <p class="text-2xl font-bold text-green-400"><?php echo $stats['vendeur_confirme']; ?></p>
                        </div>
                    </div>
                    <div class="bg-gray-800 rounded-xl p-4 border-2 border-blue-500/30 hover:border-blue-500/50 transition-all">
                        <div class="text-center">
                            <i class="fas fa-vial text-3xl text-blue-500 mb-2"></i>
                            <p class="text-gray-400 text-xs mb-1">V. Test</p>
                            <p class="text-2xl font-bold text-blue-400"><?php echo $stats['vendeur_test']; ?></p>
                        </div>
                    </div>
                    <div class="bg-gray-800 rounded-xl p-4 border-2 border-indigo-500/30 hover:border-indigo-500/50 transition-all">
                        <div class="text-center">
                            <i class="fas fa-star text-3xl text-indigo-400 mb-2"></i>
                            <p class="text-gray-400 text-xs mb-1">Abonnement Actif</p>
                            <p class="text-2xl font-bold text-indigo-400"><?php echo $stats['abonnes']; ?></p>
                        </div>
                    </div>
                    <div class="bg-gray-800 rounded-xl p-4 border-2 border-cyan-500/30 hover:border-cyan-500/50 transition-all">
                        <div class="text-center">
                            <i class="fas fa-handshake text-3xl text-cyan-400 mb-2"></i>
                            <p class="text-gray-400 text-xs mb-1">Partenaires</p>
                            <p class="text-2xl font-bold text-cyan-400"><?php echo $stats['partenaires']; ?></p>
                        </div>
                    </div>
                    <div class="bg-gray-800 rounded-xl p-4 border-2 border-gray-500/30 hover:border-gray-500/50 transition-all">
                        <div class="text-center">
                            <i class="fas fa-user text-3xl text-gray-500 mb-2"></i>
                            <p class="text-gray-400 text-xs mb-1">Clients</p>
                            <p class="text-2xl font-bold text-gray-400"><?php echo $stats['clients']; ?></p>
                        </div>
                    </div>
                    <div class="bg-gray-800 rounded-xl p-4 border-2 border-red-500/30 hover:border-red-500/50 transition-all">
                        <div class="text-center">
                            <i class="fas fa-ban text-3xl text-red-500 mb-2"></i>
                            <p class="text-gray-400 text-xs mb-1">Bannis</p>
                            <p class="text-2xl font-bold text-red-400"><?php echo $stats['banned']; ?></p>
                        </div>
                    </div>
                </div>

                <!-- Filtres -->
                <div class="bg-gray-800 rounded-xl p-2 sm:p-6 mb-4 sm:mb-6">
                    <form method="GET" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-2 sm:gap-4 text-xs sm:text-base">
                        <div>
                            <label class="block text-sm font-medium mb-2">
                                <i class="fas fa-search mr-1"></i>Recherche
                            </label>
                            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                                   placeholder="Nom, email, pseudo Minecraft..." 
                                   class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 focus:border-purple-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-2">
                                <i class="fas fa-user-tag mr-1"></i>Rôle
                            </label>
                            <select name="role" class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 focus:border-purple-500 focus:outline-none">
                                <option value="">Tous les rôles</option>
                                <option value="fondateur" <?php echo $role_filter === 'fondateur' ? 'selected' : ''; ?>>👑 Fondateur</option>
                                <option value="resp_vendeur" <?php echo $role_filter === 'resp_vendeur' ? 'selected' : ''; ?>>🛡️ Resp. Vendeur</option>
                                <option value="vendeur_senior" <?php echo $role_filter === 'vendeur_senior' ? 'selected' : ''; ?>>🥇 Vendeur Senior</option>
                                <option value="vendeur_confirme" <?php echo $role_filter === 'vendeur_confirme' ? 'selected' : ''; ?>>✅ Vendeur Confirmé</option>
                                <option value="vendeur_test" <?php echo $role_filter === 'vendeur_test' ? 'selected' : ''; ?>>🧪 Vendeur Test</option>
                                <option value="partenaire" <?php echo $role_filter === 'partenaire' ? 'selected' : ''; ?>>🤝 Partenaire</option>
                                <option value="client" <?php echo $role_filter === 'client' ? 'selected' : ''; ?>>👤 Client</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-2">
                                <i class="fas fa-filter mr-1"></i>Statut
                            </label>
                            <select name="status" class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 focus:border-purple-500 focus:outline-none">
                                <option value="">Tous les statuts</option>
                                <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>✅ Actif</option>
                                <option value="banned" <?php echo $status_filter === 'banned' ? 'selected' : ''; ?>>🚫 Banni</option>
                            </select>
                        </div>
                        <div class="flex items-end gap-2">
                            <button type="submit" class="flex-1 bg-purple-600 hover:bg-purple-700 px-4 py-2 rounded-lg font-bold transition-colors">
                                <i class="fas fa-search mr-2"></i>Filtrer
                            </button>
                            <a href="users.php" class="bg-gray-700 hover:bg-gray-600 px-4 py-2 rounded-lg transition-colors" title="Réinitialiser">
                                <i class="fas fa-redo"></i>
                            </a>
                        </div>
                    </form>
                </div>

                <!-- Liste des utilisateurs -->
                <div class="bg-gray-800 rounded-xl">
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs sm:text-base">
                            <thead class="bg-gray-900">
                                <tr>
                                    <th class="px-2 sm:px-6 py-2 sm:py-4 text-left"><i class="fas fa-user mr-2"></i>Utilisateur</th>
                                    <th class="px-2 sm:px-6 py-2 sm:py-4 text-left hidden sm:table-cell"><i class="fas fa-envelope mr-2"></i>Email</th>
                                    <th class="px-2 sm:px-6 py-2 sm:py-4 text-left"><i class="fab fa-minecraft mr-2"></i>Minecraft</th>
                                    <th class="px-2 sm:px-6 py-2 sm:py-4 text-center"><i class="fas fa-user-tag mr-2"></i>Rôle</th>
                                    <th class="px-2 sm:px-6 py-2 sm:py-4 text-center"><i class="fas fa-check-circle mr-2"></i>Statut</th>
                                    <th class="px-2 sm:px-6 py-2 sm:py-4 text-center hidden sm:table-cell"><i class="fas fa-star mr-2"></i>Abonnement</th>
                                    <th class="px-2 sm:px-6 py-2 sm:py-4 text-center hidden sm:table-cell"><i class="fas fa-calendar mr-2"></i>Inscription</th>
                                    <th class="px-2 sm:px-6 py-2 sm:py-4 text-center"><i class="fas fa-cog mr-2"></i>Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-700">
                                <?php foreach($users as $user): ?>
                                <tr class="hover:bg-gray-700/50 transition-colors">
                                    <td class="px-2 sm:px-6 py-2 sm:py-4">
                                        <div class="flex items-center gap-3">
                                            <?php
                                            $gradient = 'from-purple-600 to-pink-600';
                                            if ($user['role'] === 'fondateur') $gradient = 'from-yellow-500 to-orange-500';
                                            elseif ($user['role'] === 'admin') $gradient = 'from-blue-500 to-cyan-500';
                                            elseif ($user['role'] === 'vendeur') $gradient = 'from-orange-500 to-red-500';
                                            ?>
                                            <div class="w-10 h-10 bg-gradient-to-br <?php echo $gradient; ?> rounded-full flex items-center justify-center font-bold shadow-lg">
                                                <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                                            </div>
                                            <div>
                                                <p class="font-semibold"><?php echo htmlspecialchars($user['username']); ?></p>
                                                <?php 
                                                $is_online = isUserOnline($user['last_activity'] ?? null);
                                                ?>
                                                <!-- Statut en ligne/hors ligne après le nom -->
                                                <span class="text-xs <?php echo $is_online ? 'text-green-400' : 'text-red-400'; ?> font-semibold">
                                                    <?php echo $is_online ? 'En ligne' : 'Hors ligne'; ?>
                                                </span>
                                                <p class="text-xs text-gray-500">ID: <?php echo $user['id']; ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-2 sm:px-6 py-2 sm:py-4 hidden sm:table-cell">
                                        <div class="text-sm">
                                            <p class="text-gray-400"><?php echo htmlspecialchars($user['email']); ?></p>
                                        </div>
                                    </td>
                                    <td class="px-2 sm:px-6 py-2 sm:py-4">
                                        <?php if($user['minecraft_username']): ?>
                                            <div class="flex items-center gap-2">
                                                <img src="https://minotar.net/avatar/<?php echo htmlspecialchars($user['minecraft_username']); ?>/32" 
                                                     class="w-8 h-8 rounded minecraft-avatar" 
                                                     alt="Avatar Minecraft"
                                                     data-username="<?php echo htmlspecialchars($user['minecraft_username']); ?>"
                                                     onerror="this.src='https://mc-heads.net/avatar/<?php echo htmlspecialchars($user['minecraft_username']); ?>/32'; if(this.dataset.tried) this.src='https://static.wikia.nocookie.net/minecraft_gamepedia/images/b/b2/Bedrock_JE2_BE2.png';"
                                                     onload="checkIfDefaultSkin(this)"
                                                     loading="lazy">
                                                <code class="text-xs bg-gray-900 px-2 py-1 rounded">
                                                    <?php echo htmlspecialchars($user['minecraft_username']); ?>
                                                </code>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-xs text-gray-500 italic">Non défini</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-2 sm:px-6 py-2 sm:py-4 text-center">
                                        <?php if($user['role'] === 'fondateur'): ?>
                                            <span class="inline-flex items-center px-3 py-1 bg-gradient-to-r from-yellow-500 to-orange-500 text-white rounded-full text-sm font-bold shadow-lg">
                                                <i class="fas fa-crown mr-1"></i>Fondateur
                                            </span>
                                        <?php elseif($user['role'] === 'resp_vendeur'): ?>
                                            <span class="inline-flex items-center px-3 py-1 bg-purple-600/20 text-purple-400 border border-purple-500/30 rounded-full text-sm font-bold">
                                                <i class="fas fa-user-shield mr-1"></i>Resp. Vendeur
                                            </span>
                                        <?php elseif($user['role'] === 'vendeur_senior'): ?>
                                            <span class="inline-flex items-center px-3 py-1 bg-amber-500/20 text-amber-400 border border-amber-500/30 rounded-full text-sm font-bold">
                                                <i class="fas fa-medal mr-1"></i>Vendeur Senior
                                            </span>
                                        <?php elseif($user['role'] === 'vendeur_confirme'): ?>
                                            <span class="inline-flex items-center px-3 py-1 bg-green-500/20 text-green-400 border border-green-500/30 rounded-full text-sm font-bold">
                                                <i class="fas fa-check-circle mr-1"></i>Vendeur Confirmé
                                            </span>
                                        <?php elseif($user['role'] === 'vendeur_test'): ?>
                                            <span class="inline-flex items-center px-3 py-1 bg-blue-500/20 text-blue-400 border border-blue-500/30 rounded-full text-sm font-bold">
                                                <i class="fas fa-vial mr-1"></i>Vendeur Test
                                            </span>
                                        <?php elseif($user['role'] === 'partenaire'): ?>
                                            <span class="inline-flex items-center px-3 py-1 bg-cyan-500/20 text-cyan-400 border border-cyan-500/30 rounded-full text-sm font-bold">
                                                <i class="fas fa-handshake mr-1"></i>Partenaire
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center px-3 py-1 bg-gray-700 text-gray-300 rounded-full text-sm">
                                                <i class="fas fa-user mr-1"></i>Client
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-2 sm:px-6 py-2 sm:py-4 text-center">
                                        <?php if($user['is_banned']): ?>
                                            <span class="inline-flex items-center px-3 py-1 bg-red-500/20 text-red-400 border border-red-500/30 rounded-full text-sm font-bold">
                                                <i class="fas fa-ban mr-1"></i>Banni
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center px-3 py-1 bg-green-500/20 text-green-400 border border-green-500/30 rounded-full text-sm font-bold">
                                                <i class="fas fa-check mr-1"></i>Actif
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-2 sm:px-6 py-2 sm:py-4 text-center hidden sm:table-cell">
                                        <!-- Abonnement : afficher le statut d'abonnement -->
                                        <?php if(isset($abos[$user['id']])): ?>
                                            <?php $abo = $abos[$user['id']]; ?>
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-bold
                                                <?php
                                                    if ($abo['permissions'] === 'Actif') echo 'bg-indigo-500/20 text-indigo-400 border border-indigo-500/30';
                                                    elseif ($abo['permissions'] === 'Annulé') echo 'bg-red-500/20 text-red-400 border border-red-500/30';
                                                    elseif ($abo['permissions'] === 'Inactif') echo 'bg-gray-700 text-gray-300';
                                                    else echo 'bg-gray-700 text-gray-300';
                                                ?>">
                                                <i class="fas fa-star mr-1"></i>
                                                <?php echo htmlspecialchars($abo['permissions']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-bold bg-gray-700 text-gray-300">
                                                <i class="fas fa-star mr-1"></i>
                                                Aucun
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-2 sm:px-6 py-2 sm:py-4 text-center hidden sm:table-cell">
                                        <!-- Inscription : afficher la date d'inscription -->
                                        <div class="text-sm">
                                            <p class="font-medium"><?php echo date('d/m/Y', strtotime($user['created_at'])); ?></p>
                                            <p class="text-xs text-gray-500"><?php echo date('H:i', strtotime($user['created_at'])); ?></p>
                                        </div>
                                    </td>
                                    <td class="px-2 sm:px-6 py-2 sm:py-4">
                                        <!-- Actions -->
                                        <div class="flex items-center justify-center gap-2 flex-wrap">
                                            <!-- Changer le rôle -->
                                            <button onclick="changeRole(<?php echo $user['id']; ?>, '<?php echo $user['role']; ?>', '<?php echo htmlspecialchars($user['username']); ?>')"
                                                    class="bg-blue-600 hover:bg-blue-700 px-3 py-1 rounded text-sm transition-colors"
                                                    title="Changer le rôle">
                                                <i class="fas fa-user-shield"></i>
                                            </button>
                                            
                                            <!-- Changer le mot de passe -->
                                            <button onclick="changePassword(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')"
                                                    class="bg-yellow-600 hover:bg-yellow-700 px-3 py-1 rounded text-sm transition-colors"
                                                    title="Réinitialiser le mot de passe">
                                                <i class="fas fa-key"></i>
                                            </button>
                                            
                                            <!-- Ban/Unban -->
                                            <form method="POST" class="inline" onsubmit="return confirm('Confirmer cette action?')">
                                                <input type="hidden" name="action" value="toggle_ban">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <button type="submit" class="<?php echo $user['is_banned'] ? 'bg-green-600 hover:bg-green-700' : 'bg-orange-600 hover:bg-orange-700'; ?> px-3 py-1 rounded text-sm transition-colors"
                                                        title="<?php echo $user['is_banned'] ? 'Débannir' : 'Bannir'; ?>">
                                                    <i class="fas fa-<?php echo $user['is_banned'] ? 'check' : 'ban'; ?>"></i>
                                                </button>
                                            </form>
                                            
                                            <!-- Supprimer (sauf admin et fondateur) -->
                                            <?php if($user['role'] !== 'admin' && $user['role'] !== 'fondateur'): ?>
                                            <form method="POST" class="inline" onsubmit="return confirm('⚠️ ATTENTION!\n\nSupprimer cet utilisateur définitivement?\n\nToutes ses données seront perdues!')">
                                                <input type="hidden" name="action" value="delete_user">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <button type="submit" class="bg-red-600 hover:bg-red-700 px-3 py-1 rounded text-sm transition-colors"
                                                        title="Supprimer">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                
                                <?php if(empty($users)): ?>
                                <tr>
                                    <td colspan="7" class="px-2 sm:px-6 py-8 sm:py-12 text-center">
                                        <div class="flex flex-col items-center justify-center text-gray-500">
                                            <i class="fas fa-users-slash text-4xl sm:text-6xl mb-2 sm:mb-4 opacity-20"></i>
                                            <p class="text-base sm:text-xl font-semibold mb-2">Aucun utilisateur trouvé</p>
                                            <p class="text-xs sm:text-sm">Essayez de modifier vos filtres de recherche</p>
                                        </div>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Info totale -->
                <div class="mt-2 sm:mt-4 text-center text-gray-400 text-xs sm:text-sm">
                    <i class="fas fa-info-circle mr-1"></i>
                    Affichage de <strong class="text-purple-400"><?php echo count($users); ?></strong> utilisateur(s) sur <strong class="text-purple-400"><?php echo $total_users; ?></strong>
                    <?php if ($total_pages > 1): ?>
                    (Page <?php echo $page; ?> sur <?php echo $total_pages; ?>)
                    <?php endif; ?>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="mt-4 sm:mt-6 bg-gray-800 rounded-xl p-2 sm:p-6">
                    <div class="flex flex-col md:flex-row items-center justify-between gap-2 sm:gap-4">
                        <!-- Boutons Précédent/Suivant -->
                        <div class="flex items-center gap-3">
                            <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $role_filter ? '&role=' . urlencode($role_filter) : ''; ?><?php echo $status_filter ? '&status=' . urlencode($status_filter) : ''; ?>" 
                               class="bg-purple-600 hover:bg-purple-700 px-4 py-2 rounded-lg font-bold transition-colors flex items-center gap-2">
                                <i class="fas fa-chevron-left"></i>
                                <span>Précédent</span>
                            </a>
                            <?php else: ?>
                            <button disabled class="bg-gray-700 text-gray-500 px-4 py-2 rounded-lg font-bold cursor-not-allowed flex items-center gap-2">
                                <i class="fas fa-chevron-left"></i>
                                <span>Précédent</span>
                            </button>
                            <?php endif; ?>

                            <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page + 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $role_filter ? '&role=' . urlencode($role_filter) : ''; ?><?php echo $status_filter ? '&status=' . urlencode($status_filter) : ''; ?>" 
                               class="bg-purple-600 hover:bg-purple-700 px-4 py-2 rounded-lg font-bold transition-colors flex items-center gap-2">
                                <span>Suivant</span>
                                <i class="fas fa-chevron-right"></i>
                            </a>
                            <?php else: ?>
                            <button disabled class="bg-gray-700 text-gray-500 px-4 py-2 rounded-lg font-bold cursor-not-allowed flex items-center gap-2">
                                <span>Suivant</span>
                                <i class="fas fa-chevron-right"></i>
                            </button>
                            <?php endif; ?>
                        </div>

                        <!-- Slider de pagination -->
                        <div class="flex-1 max-w-md">
                            <div class="flex items-center gap-3">
                                <span class="text-sm text-gray-400 whitespace-nowrap">Page:</span>
                                <div class="flex-1 relative">
                                    <!-- Augmenter la largeur du slider -->
                                    <input type="range" 
                                           id="pageSlider" 
                                           min="1" 
                                           max="<?php echo $total_pages; ?>" 
                                           value="<?php echo $page; ?>" 
                                           class="w-full h-6 bg-gray-700 rounded-lg appearance-none cursor-pointer slider"
                                           style="width: 350px;" <!-- Ajouté pour élargir le slider -->
                                           onchange="goToPage(this.value)"
                                           oninput="updatePageLabel(this.value)">
                                </div>
                                <span id="pageLabel" class="text-sm font-bold text-purple-400 min-w-[4rem] text-center">
                                    <?php echo $page; ?> / <?php echo $total_pages; ?>
                                </span>
                            </div>
                        </div>

                        <!-- Navigation rapide -->
                        <div class="flex items-center gap-2">
                            <span class="text-sm text-gray-400">Aller à:</span>
                            <input type="number" 
                                   id="pageInput" 
                                   min="1" 
                                   max="<?php echo $total_pages; ?>" 
                                   value="<?php echo $page; ?>" 
                                   class="w-20 bg-gray-700 border border-gray-600 rounded-lg px-3 py-2 text-center focus:border-purple-500 focus:outline-none"
                                   onkeypress="if(event.key === 'Enter') goToPage(this.value)">
                            <button onclick="goToPage(document.getElementById('pageInput').value)" 
                                    class="bg-gray-700 hover:bg-gray-600 px-3 py-2 rounded-lg transition-colors"
                                    title="Aller à la page">
                                <i class="fas fa-arrow-right"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Modal pour changer le rôle -->
    <div id="roleModal" class="hidden fixed inset-0 bg-black/80 z-50 flex items-center justify-center">
        <div class="bg-gray-800 rounded-xl p-8 max-w-md w-full mx-4 border border-gray-700 shadow-2xl">
            <h3 class="text-2xl font-bold mb-4">
                <i class="fas fa-user-shield text-blue-500 mr-2"></i>
                Changer le rôle
            </h3>
            <p class="text-gray-400 mb-6">
                Utilisateur: <span id="modalUsername" class="text-white font-bold"></span>
            </p>
            
            <form method="POST">
                <input type="hidden" name="action" value="update_role">
                <input type="hidden" name="user_id" id="modalUserId">
                
                <div class="mb-6">
                    <label class="block text-sm font-medium mb-3">Sélectionner un nouveau rôle</label>
                    <select name="role" id="modalRole" class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-3 focus:border-purple-500 focus:outline-none">
                        <option value="client">👤 Client - Accès basique</option>
                        <option value="vendeur_test">🧪 Vendeur Test - En formation</option>
                        <option value="vendeur_confirme">✅ Vendeur Confirmé - Gestion boutique</option>
                        <option value="vendeur_senior">🥇 Vendeur Senior - Expérimenté</option>
                        <option value="resp_vendeur">🛡️ Responsable Vendeur - Gestion équipe</option>
                        <option value="fondateur">👑 Fondateur - Tous les droits</option>
                        <option value="partenaire">🤝 Partenaire - Accès partenaire</option>
                    </select>
                    <p class="text-xs text-gray-500 mt-2">
                        <i class="fas fa-info-circle mr-1"></i>
                        Les vendeurs confirmés et supérieurs ont accès au panneau d'administration
                    </p>
                </div>
                
                <div class="flex gap-3">
                    <button type="submit" class="flex-1 bg-purple-600 hover:bg-purple-700 px-4 py-3 rounded-lg font-bold transition-colors">
                        <i class="fas fa-save mr-2"></i>Enregistrer
                    </button>
                    <button type="button" onclick="closeRoleModal()" class="flex-1 bg-gray-700 hover:bg-gray-600 px-4 py-3 rounded-lg font-bold transition-colors">
                        Annuler
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal pour changer le mot de passe -->
    <div id="passwordModal" class="hidden fixed inset-0 bg-black/80 z-50 flex items-center justify-center">
        <div class="bg-gray-800 rounded-xl p-8 max-w-md w-full mx-4">
            <h3 class="text-2xl font-bold mb-4">
                <i class="fas fa-key text-yellow-500 mr-2"></i>
                Réinitialiser le mot de passe
            </h3>
            <p class="text-gray-400 mb-6">
                Utilisateur: <span id="passwordModalUsername" class="text-white font-bold"></span>
            </p>
            
            <form method="POST">
                <input type="hidden" name="action" value="change_password">
                <input type="hidden" name="user_id" id="passwordModalUserId">
                
                <div class="mb-6">
                    <label class="block text-sm font-medium mb-2">
                        <i class="fas fa-lock mr-1"></i>
                        Nouveau mot de passe
                    </label>
                    <div class="relative">
                        <input type="password" 
                               name="new_password" 
                               id="newPasswordInput"
                               required 
                               minlength="6"
                               placeholder="Minimum 6 caractères"
                               class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-3 pr-12">
                        <button type="button" 
                                onclick="togglePasswordVisibility()"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-white">
                            <i id="togglePasswordIcon" class="fas fa-eye"></i>
                        </button>
                    </div>
                    <p class="text-xs text-gray-500 mt-2">
                        <i class="fas fa-info-circle mr-1"></i>
                        Le mot de passe doit contenir au moins 6 caractères
                    </p>
                </div>
                
                <div class="mb-6">
                    <button type="button" 
                            onclick="generatePassword()"
                            class="w-full bg-gray-700 hover:bg-gray-600 px-4 py-2 rounded-lg text-sm border border-gray-600">
                        <i class="fas fa-random mr-2"></i>
                        Générer un mot de passe aléatoire
                    </button>
                </div>
                
                <div class="flex gap-3">
                    <button type="submit" class="flex-1 bg-yellow-600 hover:bg-yellow-700 px-4 py-3 rounded-lg font-bold">
                        <i class="fas fa-save mr-2"></i>Enregistrer
                    </button>
                    <button type="button" onclick="closePasswordModal()" class="flex-1 bg-gray-700 hover:bg-gray-600 px-4 py-3 rounded-lg font-bold">
                        Annuler
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Variables pour la pagination
        const totalPages = <?php echo $total_pages; ?>;
        const currentPage = <?php echo $page; ?>;
        const searchParam = '<?php echo addslashes($search); ?>';
        const roleParam = '<?php echo addslashes($role_filter); ?>';
        const statusParam = '<?php echo addslashes($status_filter); ?>';

        function goToPage(page) {
            page = Math.max(1, Math.min(parseInt(page), totalPages));
            if (isNaN(page)) return;
            
            let url = 'users.php?page=' + page;
            if (searchParam) url += '&search=' + encodeURIComponent(searchParam);
            if (roleParam) url += '&role=' + encodeURIComponent(roleParam);
            if (statusParam) url += '&status=' + encodeURIComponent(statusParam);
            
            window.location.href = url;
        }

        function updatePageLabel(page) {
            document.getElementById('pageLabel').textContent = page + ' / ' + totalPages;
            document.getElementById('pageInput').value = page;
        }

        // Synchroniser le slider et l'input
        document.getElementById('pageSlider')?.addEventListener('input', function() {
            document.getElementById('pageInput').value = this.value;
        });

        document.getElementById('pageInput')?.addEventListener('input', function() {
            const slider = document.getElementById('pageSlider');
            if (slider && this.value >= 1 && this.value <= totalPages) {
                slider.value = this.value;
                updatePageLabel(this.value);
            }
        });

        // Style personnalisé pour le slider
        const style = document.createElement('style');
        style.textContent = `
            .slider {
                height: 2.5rem !important;
                width: 350px !important; /* Ajouté pour élargir le slider */
                max-width: 100%;
                display: block;
                margin: 0 auto;
            }
            .slider::-webkit-slider-thumb {
                appearance: none;
                width: 28px;
                height: 28px;
                background: linear-gradient(135deg, #a855f7, #ec4899);
                cursor: pointer;
                border-radius: 50%;
                box-shadow: 0 0 10px rgba(168, 85, 247, 0.5);
                transition: all 0.3s ease;
                border: 3px solid #fff;
            }
            .slider::-moz-range-thumb {
                width: 28px;
                height: 28px;
                background: linear-gradient(135deg, #a855f7, #ec4899);
                cursor: pointer;
                border-radius: 50%;
                border: 3px solid #fff;
                box-shadow: 0 0 10px rgba(168, 85, 247, 0.5);
                transition: all 0.3s ease;
            }
            .slider::-webkit-slider-thumb:hover,
            .slider::-moz-range-thumb:hover {
                transform: scale(1.2);
                box-shadow: 0 0 15px rgba(168, 85, 247, 0.8);
            }
            .slider::-webkit-slider-runnable-track {
                height: 12px;
                background: linear-gradient(to right, 
                    #a855f7 0%, 
                    #a855f7 <?php echo ($page / $total_pages) * 100; ?>%, 
                    #374151 <?php echo ($page / $total_pages) * 100; ?>%, 
                    #374151 100%);
                border-radius: 9999px;
            }
            .slider::-moz-range-track {
                height: 12px;
                background: #374151;
                border-radius: 9999px;
            }
            .slider::-moz-range-progress {
                height: 12px;
                background: #a855f7;
                border-radius: 9999px;
            }
            .slider:focus {
                outline: none;
                box-shadow: 0 0 0 2px #a855f7;
            }
        `;
        document.head.appendChild(style);

        function changeRole(userId, currentRole, username) {
            document.getElementById('modalUserId').value = userId;
            document.getElementById('modalUsername').textContent = username;
            document.getElementById('modalRole').value = currentRole;
            document.getElementById('roleModal').classList.remove('hidden');
        }
        
        function closeRoleModal() {
            document.getElementById('roleModal').classList.add('hidden');
        }

        function changePassword(userId, username) {
            document.getElementById('passwordModalUserId').value = userId;
            document.getElementById('passwordModalUsername').textContent = username;
            document.getElementById('newPasswordInput').value = '';
            document.getElementById('togglePasswordIcon').className = 'fas fa-eye';
            document.getElementById('passwordModal').classList.remove('hidden');
        }
        
        function closePasswordModal() {
            document.getElementById('passwordModal').classList.add('hidden');
        }

        function togglePasswordVisibility() {
            const input = document.getElementById('newPasswordInput');
            const icon = document.getElementById('togglePasswordIcon');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.className = 'fas fa-eye-slash';
            } else {
                input.type = 'password';
                icon.className = 'fas fa-eye';
            }
        }

        function generatePassword() {
            const length = 12;
            const charset = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*";
            let password = "";
            
            for (let i = 0; i < length; i++) {
                const randomIndex = Math.floor(Math.random() * charset.length);
                password += charset[randomIndex];
            }
            
            document.getElementById('newPasswordInput').value = password;
            document.getElementById('newPasswordInput').type = 'text';
            document.getElementById('togglePasswordIcon').className = 'fas fa-eye-slash';
            
            const notification = document.createElement('div');
            notification.className = 'fixed top-4 right-4 bg-green-500/20 border border-green-500 text-green-400 px-4 py-2 rounded-lg z-50';
            notification.innerHTML = '<i class="fas fa-check mr-2"></i>Mot de passe généré !';
            document.body.appendChild(notification);
            setTimeout(() => notification.remove(), 2000);
        }

        document.getElementById('roleModal')?.addEventListener('click', function(e) {
            if (e.target === this) closeRoleModal();
        });
        
        document.getElementById('passwordModal')?.addEventListener('click', function(e) {
            if (e.target === this) closePasswordModal();
        });

        // Fonction pour vérifier si c'est un skin par défaut (Steve/Alex)
        function checkIfDefaultSkin(img) {
            // Créer un canvas pour analyser l'image
            const canvas = document.createElement('canvas');
            const ctx = canvas.getContext('2d');
            canvas.width = img.width;
            canvas.height = img.height;
            
            // Dessiner l'image sur le canvas
            ctx.drawImage(img, 0, 0);
            
            try {
                // Obtenir les données de pixels
                const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
                const data = imageData.data;
                
                // Vérifier les couleurs caractéristiques de Steve (peau beige)
                // Steve a des tons de peau autour de RGB(222, 177, 147) ou similaires
                let steveColorCount = 0;
                let totalPixels = data.length / 4;
                
                for (let i = 0; i < data.length; i += 4) {
                    const r = data[i];
                    const g = data[i + 1];
                    const b = data[i + 2];
                    
                    // Vérifier si c'est proche de la couleur de peau de Steve
                    if (r > 200 && r < 240 && g > 150 && g < 200 && b > 120 && b < 170) {
                        steveColorCount++;
                    }
                }
                
                // Si plus de 30% des pixels correspondent à la couleur de Steve
                if (steveColorCount > totalPixels * 0.3) {
                    // Remplacer par un bloc de bedrock
                    img.src = 'https://static.wikia.nocookie.net/minecraft_gamepedia/images/b/b2/Bedrock_JE2_BE2.png';
                    img.title = 'Pseudo non trouvé - Bloc de Bedrock';
                    img.classList.add('opacity-60');
                }
            } catch (e) {
                // En cas d'erreur CORS, utiliser une approche alternative
                // Vérifier si l'URL contient MHF_Steve (avatar par défaut)
                if (img.src.includes('MHF_Steve') || img.src.includes('char.png')) {
                    img.src = 'https://static.wikia.nocookie.net/minecraft_gamepedia/images/b/b2/Bedrock_JE2_BE2.png';
                    img.title = 'Pseudo non trouvé - Bloc de Bedrock';
                    img.classList.add('opacity-60');
                }
            }
        }
    </script>
</body>
</html>
