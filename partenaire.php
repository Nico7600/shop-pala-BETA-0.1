<?php
session_start();
require_once 'config.php';

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Vérification du rôle
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['fondateur', 'partenaire'])) {
    header('Location: index.php');
    exit;
}

// Récupération du nom d'utilisateur cible (via GET ou session)
$target_username = isset($_GET['name']) ? $_GET['name'] : $_SESSION['username'];

// Vérification d'accès : seul le fondateur ou la personne concernée peut voir ses stats
if ($_SESSION['role'] !== 'fondateur' && $target_username !== $_SESSION['username']) {
    header('Location: index.php');
    exit;
}

// Récupération de l'id utilisateur cible
$stmt_user = $pdo->prepare("SELECT id, username FROM users WHERE username = ?");
$stmt_user->execute([$target_username]);
$user_data = $stmt_user->fetch(PDO::FETCH_ASSOC);

if (!$user_data) {
    echo "<div class='text-red-500 text-center mt-10'>Utilisateur non trouvé.</div>";
    exit;
}
$partner_id = $user_data['id'];

// Génération d'un code promo
function generatePromoCode() {
    return sprintf('Partenaire-%03d-%03d-%03d', rand(0,999), rand(0,999), rand(0,999));
}

// Création du code promo
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['create_code'])
    && $target_username === $_SESSION['username'] // On ne peut ajouter que sur sa propre page
) {
    $code = generatePromoCode();
    $user_id = $partner_id;
    $discount_type = 'percentage';
    $discount_value = 12; 
    $min_purchase = 150.00;
    $max_uses = 10;
    $expires_at = date('Y-m-d H:i:s', strtotime('+1 day')); // 24h après création
    $is_active = 1;
    $show_in_banner = 0;
    $stmt = $pdo->prepare("INSERT INTO promo_codes 
        (code, discount_type, discount_value, min_purchase, max_uses, current_uses, expires_at, is_active, show_in_banner, created_by, created_at, updated_at) 
        VALUES (?, ?, ?, ?, ?, 0, ?, ?, ?, ?, NOW(), NOW())");
    $stmt->execute([
        $code, $discount_type, $discount_value, $min_purchase, $max_uses, $expires_at, $is_active, $show_in_banner, $user_id
    ]);
    // Redirection sans id, avec name
    header('Location: partenaire.php?name=' . urlencode($target_username));
    exit;
}

// Récupération de l'annonce "live" active non expirée pour l'utilisateur
$active_live_announce = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM announcements WHERE created_by = ? AND is_active = 1 AND expires_at > NOW() ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$partner_id]);
    $active_live_announce = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $active_live_announce = null;
}

// Traitement de l'annonce "en live"
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['create_live_announce'])
    && $target_username === $_SESSION['username']
    && !empty($_POST['live_content'])
    && !empty($_POST['live_link'])
    && !$active_live_announce // Empêche la création si une annonce existe déjà
) {
    // Vérifie si la table announcements existe
    try {
        $table_check = $pdo->query("SHOW TABLES LIKE 'announcements'");
        if ($table_check->rowCount() === 0) {
            echo "<div class='bg-red-700/30 border border-red-400 text-red-400 text-center font-bold py-2 px-4 rounded-lg mb-4 shadow'>Erreur : La table <b>announcements</b> n'existe pas dans la base de données.</div>";
        } else {
            $title = $target_username . " est en live";
            $content = trim($_POST['live_content']) . "\nLien du live : " . trim($_POST['live_link']);
            $type = 'info';
            $is_active = 1;
            $show_in_banner = 1;
            $created_by = $partner_id;
            $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour')); // expire dans 1h automatiquement
            $stmt = $pdo->prepare("INSERT INTO announcements (title, content, type, is_active, show_in_banner, created_by, expires_at, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
            $stmt->execute([$title, $content, $type, $is_active, $show_in_banner, $created_by, $expires_at]);
            $live_announce_success = true;
        }
    } catch (PDOException $e) {
        echo "<div class='bg-red-700/30 border border-red-400 text-red-400 text-center font-bold py-2 px-4 rounded-lg mb-4 shadow'>Erreur SQL : " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

// Désactivation automatique des codes promo expirés (pour partenaire/fondateur)
try {
    $check_stmt = $pdo->prepare("SELECT code FROM promo_codes WHERE created_by = ? AND expires_at IS NOT NULL AND expires_at < NOW() AND is_active = 1");
    $check_stmt->execute([$partner_id]);
    $codes_to_disable = $check_stmt->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($codes_to_disable)) {
        $stmt = $pdo->prepare("UPDATE promo_codes SET is_active = 0 WHERE created_by = ? AND expires_at IS NOT NULL AND expires_at < NOW() AND is_active = 1");
        $stmt->execute([$partner_id]);

        foreach ($codes_to_disable as $code) {
            $log_stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, details, created_at) VALUES (?, ?, ?, NOW())");
            $log_stmt->execute([
                $_SESSION['user_id'],
                'update_promo_code',
                "Désactivation automatique du code promo expiré: " . $code
            ]);
        }
    }
} catch (PDOException $e) {
    error_log("Erreur vérification codes expirés: " . $e->getMessage());
}

// Récupération des codes promos du partenaire
$stmt = $pdo->prepare("SELECT * FROM promo_codes WHERE created_by = ?");
$stmt->execute([$partner_id]);
$codes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Statistiques (seulement les codes "Partenaire-")
$total_codes = 0;
$active_codes = 0;
$total_uses = 0;
foreach ($codes as $c) {
    if (strpos($c['code'], 'Partenaire-') === 0) {
        $total_codes++;
        if ($c['is_active']) $active_codes++;
        $total_uses += intval($c['current_uses']);
    }
}

// Récupération des partenaires pour le select (si fondateur)
$partners_list = [];
$founders_list = [];
if ($_SESSION['role'] === 'fondateur') {
    $stmt_partners = $pdo->query("SELECT id, username AS nom FROM users WHERE role = 'partenaire' ORDER BY username ASC");
    $partners_list = $stmt_partners->fetchAll(PDO::FETCH_ASSOC);

    $stmt_founders = $pdo->query("SELECT id, username AS nom FROM users WHERE role = 'fondateur' ORDER BY username ASC");
    $founders_list = $stmt_founders->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Espace partenaire pour la gestion des codes promos.">
    <title>Codes promos du partenaire</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="bg-gray-900 min-h-screen flex flex-col">
    <?php include 'includes/header.php'; ?>
    <main class="flex flex-col items-center justify-start px-4 py-10 flex-1 w-full bg-gray-900">
        <div class="w-full max-w-6xl mx-auto">
            <!-- Statistiques style index.php -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
                <div class="bg-gray-800 rounded-xl shadow-lg p-6 flex flex-col items-center border border-blue-900">
                    <i class="fa-solid fa-ticket text-blue-400 text-3xl mb-2"></i>
                    <span class="text-3xl font-extrabold text-blue-400"><?php echo $total_codes; ?></span>
                    <span class="text-gray-300 mt-2 font-medium">Codes générés par <?php echo htmlspecialchars($target_username); ?></span>
                </div>
                <div class="bg-gray-800 rounded-xl shadow-lg p-6 flex flex-col items-center border border-green-900">
                    <i class="fa-solid fa-check-circle text-green-400 text-3xl mb-2"></i>
                    <span class="text-3xl font-extrabold text-green-400"><?php echo $active_codes; ?></span>
                    <span class="text-gray-300 mt-2 font-medium">Codes actifs</span>
                </div>
                <div class="bg-gray-800 rounded-xl shadow-lg p-6 flex flex-col items-center border border-purple-900">
                    <i class="fa-solid fa-chart-line text-purple-400 text-3xl mb-2"></i>
                    <span class="text-3xl font-extrabold text-purple-400"><?php echo $total_uses; ?></span>
                    <span class="text-gray-300 mt-2 font-medium">Utilisations totales</span>
                </div>
            </div>
            <div class="bg-gray-800 rounded-xl shadow-lg p-8 mb-8 border border-gray-900">
                <h1 class="text-2xl font-bold text-center text-blue-400 mb-8">
                    <?php
                    if ($_SESSION['role'] === 'fondateur' && isset($_GET['type']) && $_GET['type'] === 'fondateur') {
                        echo "Codes promos du fondateur";
                    } else {
                        echo "Codes promos du partenaire";
                    }
                    ?>
                </h1>
                <?php if ($_SESSION['role'] === 'fondateur'): ?>
                <!-- Sélecteur utilisateur amélioré, sans avatar, aligné et épuré -->
                <div class="flex flex-col sm:flex-row items-center justify-center gap-4 mb-6">
                    <form method="get" class="flex items-center gap-3" id="userSelectForm">
                        <label for="name" class="text-sm font-semibold text-gray-300">Utilisateur :</label>
                        <select name="name" id="name" class="px-3 py-2 rounded-lg border border-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-400 bg-gray-800 text-white w-48 font-semibold">
                            <optgroup label="Partenaires">
                                <?php foreach ($partners_list as $p): ?>
                                    <option value="<?php echo htmlspecialchars($p['nom']); ?>" <?php if ($target_username == $p['nom'] && (!isset($_GET['type']) || $_GET['type'] === 'partenaire')) echo 'selected'; ?>>
                                        <?php echo htmlspecialchars($p['nom']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                            <optgroup label="Fondateurs">
                                <?php foreach ($founders_list as $f): ?>
                                    <option value="<?php echo htmlspecialchars($f['nom']); ?>" <?php if ($target_username == $f['nom'] && isset($_GET['type']) && $_GET['type'] === 'fondateur') echo 'selected'; ?>>
                                        <?php echo htmlspecialchars($f['nom']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        </select>
                        <input type="hidden" name="type" id="typeHidden" value="<?php echo isset($_GET['type']) ? $_GET['type'] : 'partenaire'; ?>">
                        <button type="submit"
                            class="flex items-center gap-2 px-6 py-2 rounded-xl font-semibold bg-gradient-to-r from-blue-600 to-purple-600 hover:from-blue-700 hover:to-purple-700 focus:ring-4 focus:ring-blue-400/50 shadow-lg transition-all duration-200 scale-100 hover:scale-105 active:scale-95 text-white">
                            <i class="fa-solid fa-eye text-lg"></i>
                            <span>Afficher</span>
                        </button>
                    </form>
                    <span class="text-blue-400 font-bold text-base"><?php echo htmlspecialchars($target_username); ?></span>
                </div>
                <script>
                document.getElementById('name').addEventListener('change', function() {
                    var selected = this.options[this.selectedIndex];
                    document.getElementById('typeHidden').value = selected.parentNode.label === "Fondateurs" ? "fondateur" : "partenaire";
                });
                window.addEventListener('DOMContentLoaded', function() {
                    if (window.location.search.length > 0) {
                        history.replaceState(null, '', window.location.pathname);
                    }
                });
                </script>
                <?php endif; ?>

                <!-- Boutons style harmonisé -->
                <div class="flex justify-end mb-6 gap-4">
                    <?php if ($target_username === $_SESSION['username']): ?>
                    <form method="post">
                        <button type="submit" name="create_code"
                            class="flex items-center gap-2 px-6 py-2 rounded-xl font-bold bg-gradient-to-r from-blue-600 to-purple-600 hover:from-blue-700 hover:to-purple-700 focus:ring-4 focus:ring-purple-400/50 shadow-lg transition-all duration-200 scale-100 hover:scale-105 active:scale-95 text-white">
                            <i class="fa-solid fa-plus text-lg"></i>
                            <span>Générer un code promo</span>
                        </button>
                    </form>
                    <!-- Bouton annonce avec timer -->
                    <?php
                        $disabled = $active_live_announce ? 'disabled' : '';
                        $expires_at = $active_live_announce ? strtotime($active_live_announce['expires_at']) : null;
                        $now = time();
                        $seconds_left = $expires_at ? max(0, $expires_at - $now) : 0;
                    ?>
                    <button type="button" onclick="document.getElementById('liveModal').classList.remove('hidden')"
                        class="flex items-center gap-2 px-6 py-2 rounded-xl font-bold bg-gradient-to-r from-blue-600 to-purple-600 hover:from-purple-700 hover:to-blue-700 focus:ring-4 focus:ring-purple-400/50 shadow-lg transition-all duration-200 scale-100 hover:scale-105 active:scale-95 text-white"
                        <?php echo $disabled; ?>>
                        <i class="fa-solid fa-bullhorn text-lg"></i>
                        <span>Mettre une annonce</span>
                        <?php if ($active_live_announce): ?>
                            <span id="announce-timer" class="ml-3 bg-gray-700 text-white px-3 py-1 rounded-lg font-mono text-xs"></span>
                        <?php endif; ?>
                    </button>
                    <script>
                    <?php if ($active_live_announce): ?>
                        // Timer JS pour le bouton
                        let secondsLeft = <?php echo $seconds_left; ?>;
                        function updateAnnounceTimer() {
                            if (secondsLeft <= 0) {
                                document.getElementById('announce-timer').textContent = "Disponible";
                                document.querySelector('button[disabled]').disabled = false;
                                return;
                            }
                            let h = Math.floor(secondsLeft / 3600);
                            let m = Math.floor((secondsLeft % 3600) / 60);
                            let s = secondsLeft % 60;
                            document.getElementById('announce-timer').textContent =
                                (h > 0 ? h + "h " : "") + (m > 0 ? m + "m " : "") + s + "s";
                            secondsLeft--;
                            setTimeout(updateAnnounceTimer, 1000);
                        }
                        updateAnnounceTimer();
                    <?php endif; ?>
                    </script>
                    <?php endif; ?>
                </div>

                <!-- Modal annonce -->
                <div id="liveModal" class="fixed inset-0 bg-black bg-opacity-60 flex items-center justify-center z-50 hidden">
                    <form method="post" class="bg-gray-800 rounded-xl p-8 shadow-lg w-full max-w-md border border-blue-900 relative">
                        <h2 class="text-xl font-bold text-blue-400 mb-4">Annonce</h2>
                        <label class="block text-gray-200 mb-2 font-medium" for="live_content">Description :</label>
                        <textarea name="live_content" id="live_content" rows="3" required class="w-full px-3 py-2 rounded-lg border border-gray-700 bg-gray-900 text-gray-100 mb-4"></textarea>
                        <label class="block text-gray-200 mb-2 font-medium" for="live_link">Lien du live :</label>
                        <input type="url" name="live_link" id="live_link" required placeholder="https://..." class="w-full px-3 py-2 rounded-lg border border-gray-700 bg-gray-900 text-gray-100 mb-6">
                        <div class="flex justify-end gap-2">
                            <button type="button" onclick="document.getElementById('liveModal').classList.add('hidden')" class="bg-gray-700 hover:bg-gray-600 text-white px-4 py-2 rounded-lg">Annuler</button>
                            <button type="submit" name="create_live_announce" class="bg-blue-600 hover:bg-blue-700 text-white font-bold px-4 py-2 rounded-lg">Publier l'annonce</button>
                        </div>
                        <button type="button" onclick="document.getElementById('liveModal').classList.add('hidden')" class="absolute top-2 right-2 text-gray-400 hover:text-red-400 text-xl">&times;</button>
                    </form>
                </div>
                <?php if (!empty($live_announce_success)): ?>
                <div class="bg-green-700/30 border border-green-400 text-green-400 text-center font-bold py-2 px-4 rounded-lg mb-4 shadow">
                    L'annonce "en live" a été publiée !
                </div>
                <?php endif; ?>

                <!-- Tableau style my_products.php -->
                <div class="overflow-x-auto rounded-lg shadow-lg border border-gray-700">
                    <table class="min-w-full divide-y divide-gray-700 bg-gray-900 text-gray-100">
                        <thead class="bg-gray-800">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-bold text-blue-400 uppercase">Code</th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-purple-400 uppercase">Type</th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-green-400 uppercase">Valeur</th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-400 uppercase">Utilisations</th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-pink-400 uppercase">Max</th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-400 uppercase">Expiration</th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-green-400 uppercase">Statut</th>
                                <th class="px-4 py-3 text-left text-xs font-bold text-gray-400 uppercase">Créé le</th>
                            </tr>
                        </thead>
                        <tbody class="bg-gray-900 divide-y divide-gray-800">
                            <?php foreach ($codes as $i => $c): ?>
                            <?php $is_expired = $c['expires_at'] && strtotime($c['expires_at']) < time(); ?>
                            <tr class="hover:bg-gray-800 transition-colors duration-150">
                                <td class="px-4 py-3 font-mono text-blue-400"><?php echo htmlspecialchars($c['code']); ?></td>
                                <td class="px-4 py-3 text-purple-400"><?php echo $c['discount_type'] === 'percentage' ? 'Pourcentage' : 'Fixe'; ?></td>
                                <td class="px-4 py-3 text-green-400"><?php echo $c['discount_type'] === 'percentage' ? $c['discount_value'].'%' : $c['discount_value'].'€'; ?></td>
                                <td class="px-4 py-3 text-gray-300"><?php echo $c['current_uses']; ?></td>
                                <td class="px-4 py-3 text-pink-400"><?php echo $c['max_uses'] ?? '∞'; ?></td>
                                <td class="px-4 py-3 text-gray-300"><?php echo $c['expires_at'] ? date('d/m/Y', strtotime($c['expires_at'])) : 'Illimité'; ?></td>
                                <td class="px-4 py-3">
                                    <?php if ($c['is_active']): ?>
                                    <span class="bg-green-700/30 border border-green-400 text-green-400 text-xs font-bold py-1 px-2 rounded-full flex items-center gap-2 shadow-md">
                                        <i class="fa-solid fa-circle-check"></i>
                                        Actif
                                    </span>
                                    <?php else: ?>
                                    <span class="bg-red-700/30 border border-red-400 text-red-400 text-xs font-bold py-1 px-2 rounded-full flex items-center gap-2 shadow-md">
                                        <i class="fa-solid fa-circle-xmark"></i>
                                        Inactif
                                    </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 text-gray-400 text-xs font-mono"><?php echo date('d/m/Y H:i', strtotime($c['created_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
    <footer class="bg-gray-900 text-gray-200 py-4 text-center w-full left-0 z-50 mt-auto">
        <?php include 'includes/footer.php'; ?>
    </footer>
</body>
</html>