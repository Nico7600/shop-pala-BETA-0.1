<?php
require_once '../config.php';

if(!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['resp_vendeur', 'fondateur'])) {
    header('Location: ../login.php');
    exit;
}

// Vérifier et désactiver automatiquement les codes promo expirés
try {
    // Récupérer les codes qui vont être désactivés AVANT la mise à jour
    $check_stmt = $pdo->query("SELECT code FROM promo_codes WHERE expires_at IS NOT NULL AND expires_at < NOW() AND is_active = 1");
    $codes_to_disable = $check_stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if(!empty($codes_to_disable)) {
        // Désactiver les codes expirés
        $stmt = $pdo->prepare("UPDATE promo_codes SET is_active = 0 WHERE expires_at IS NOT NULL AND expires_at < NOW() AND is_active = 1");
        $stmt->execute();
        
        // Logger chaque code désactivé
        foreach($codes_to_disable as $code) {
            $log_stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, details, created_at) VALUES (?, ?, ?, NOW())");
            $log_stmt->execute([
                $_SESSION['user_id'],
                'update_promo_code',
                "Désactivation automatique du code promo expiré: " . $code
            ]);
        }
    }
} catch(PDOException $e) {
    error_log("Erreur vérification codes expirés: " . $e->getMessage());
}

// Création/Modification de code promo
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_promo'])) {
    $code = strtoupper($_POST['code']);
    $discount_type = $_POST['discount_type'];
    $discount_value = $_POST['discount_value'];
    $min_purchase = $_POST['min_purchase'] ?? 0;
    $max_uses = $_POST['max_uses'] ?? null;
    $expires_at = $_POST['expires_at'] ?? null;
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $show_in_banner = isset($_POST['show_in_banner']) ? 1 : 0;
    
    // Vérifier si la date d'expiration est dans le passé
    if($expires_at && strtotime($expires_at) < time()) {
        $is_active = 0; // Forcer l'inactivité si expiré
        $warning = "Le code a été créé/modifié mais est inactif car la date d'expiration est dépassée.";
    }
    
    if(isset($_POST['promo_id']) && !empty($_POST['promo_id'])) {
        $pdo->prepare("UPDATE promo_codes SET code = ?, discount_type = ?, discount_value = ?, min_purchase = ?, max_uses = ?, expires_at = ?, is_active = ?, show_in_banner = ? WHERE id = ?")
            ->execute([$code, $discount_type, $discount_value, $min_purchase, $max_uses, $expires_at, $is_active, $show_in_banner, $_POST['promo_id']]);
        
        // Logger l'action
        $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, details, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([
            $_SESSION['user_id'],
            'edit_promo_code',
            "Modification du code promo: " . $code . " (" . ($discount_type == 'percentage' ? $discount_value . '%' : $discount_value . '$') . ")"
        ]);
        
        $success = isset($warning) ? $warning : "Code promo mis à jour !";
    } else {
        $pdo->prepare("INSERT INTO promo_codes (code, discount_type, discount_value, min_purchase, max_uses, expires_at, is_active, show_in_banner, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)")
            ->execute([$code, $discount_type, $discount_value, $min_purchase, $max_uses, $expires_at, $is_active, $show_in_banner, $_SESSION['user_id']]);
        
        // Logger l'action
        $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, details, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([
            $_SESSION['user_id'],
            'add_promo_code',
            "Création du code promo: " . $code . " (" . ($discount_type == 'percentage' ? $discount_value . '%' : $discount_value . '$') . ")"
        ]);
        
        $success = isset($warning) ? $warning : "Code promo créé !";
    }
}

// Suppression
if(isset($_GET['delete'])) {
    // Récupérer le code avant suppression pour le log
    $stmt = $pdo->prepare("SELECT code, discount_type, discount_value FROM promo_codes WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    $promo_info = $stmt->fetch();
    
    $pdo->prepare("DELETE FROM promo_codes WHERE id = ?")->execute([$_GET['delete']]);
    
    // Logger l'action
    if($promo_info) {
        $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, details, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([
            $_SESSION['user_id'],
            'delete_promo_code',
            "Suppression du code promo: " . $promo_info['code'] . " (" . ($promo_info['discount_type'] == 'percentage' ? $promo_info['discount_value'] . '%' : $promo_info['discount_value'] . '$') . ")"
        ]);
    }
    
    $success = "Code promo supprimé !";
}

$promos = $pdo->query("SELECT p.*, u.username as author, 
                       (SELECT COUNT(*) FROM orders WHERE promo_code_id = p.id) as usage_count
                       FROM promo_codes p 
                       LEFT JOIN users u ON p.created_by = u.id 
                       ORDER BY p.created_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Codes Promo - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="bg-gray-900 text-gray-100">
    <div class="flex flex-col md:flex-row min-h-screen">
        <?php include 'sidebar.php'; ?>

        <main class="flex-1 p-2 sm:p-8 w-full">
            <div class="mb-4 sm:mb-8 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div>
                    <h2 class="text-2xl sm:text-3xl font-bold mb-2">Codes Promo</h2>
                    <p class="text-gray-400 text-sm sm:text-base">Créez et gérez les codes promotionnels</p>
                </div>
                <button onclick="openPromoModal()" class="bg-purple-600 hover:bg-purple-700 px-4 sm:px-6 py-2 sm:py-3 rounded-lg font-bold transition w-full sm:w-auto">
                    <i class="fas fa-plus mr-2"></i>Nouveau Code
                </button>
            </div>

            <?php if(isset($success)): ?>
            <div class="bg-green-500/20 border border-green-500 text-green-400 px-2 sm:px-4 py-2 sm:py-3 rounded-lg mb-4 sm:mb-6 text-xs sm:text-base">
                <i class="fas fa-check-circle mr-2"></i><?php echo $success; ?>
            </div>
            <?php endif; ?>

            <div class="bg-gray-800 rounded-xl shadow-lg overflow-x-auto">
                <table class="w-full text-xs sm:text-base">
                    <thead class="bg-gray-750">
                        <tr>
                            <th class="px-2 sm:px-6 py-2 sm:py-4 text-left font-medium text-gray-400 uppercase">Code</th>
                            <th class="px-2 sm:px-6 py-2 sm:py-4 text-left font-medium text-gray-400 uppercase">Réduction</th>
                            <th class="px-2 sm:px-6 py-2 sm:py-4 text-left font-medium text-gray-400 uppercase hidden sm:table-cell">Achat Min.</th>
                            <th class="px-2 sm:px-6 py-2 sm:py-4 text-left font-medium text-gray-400 uppercase">Utilisations</th>
                            <th class="px-2 sm:px-6 py-2 sm:py-4 text-left font-medium text-gray-400 uppercase hidden sm:table-cell">Expire</th>
                            <th class="px-2 sm:px-6 py-2 sm:py-4 text-left font-medium text-gray-400 uppercase">Statut</th>
                            <th class="px-2 sm:px-6 py-2 sm:py-4 text-left font-medium text-gray-400 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-700">
                        <?php foreach($promos as $promo): ?>
                        <tr class="hover:bg-gray-750 transition">
                            <td class="px-2 sm:px-6 py-2 sm:py-4">
                                <span class="bg-purple-600 px-2 sm:px-3 py-1 rounded-lg font-mono font-bold"><?php echo htmlspecialchars($promo['code']); ?></span>
                            </td>
                            <td class="px-2 sm:px-6 py-2 sm:py-4 font-bold text-green-400">
                                <?php if($promo['discount_type'] == 'percentage'): ?>
                                    -<?php echo $promo['discount_value']; ?>%
                                <?php else: ?>
                                    -<?php echo number_format($promo['discount_value'], 2); ?>$
                                <?php endif; ?>
                            </td>
                            <td class="px-2 sm:px-6 py-2 sm:py-4 text-gray-400 hidden sm:table-cell">
                                <?php echo $promo['min_purchase'] > 0 ? number_format($promo['min_purchase'], 2) . '$' : 'Aucun'; ?>
                            </td>
                            <td class="px-2 sm:px-6 py-2 sm:py-4">
                                <span class="text-blue-400 font-bold"><?php echo $promo['usage_count']; ?></span>
                                <?php if($promo['max_uses']): ?>
                                    / <?php echo $promo['max_uses']; ?>
                                <?php else: ?>
                                    / ∞
                                <?php endif; ?>
                            </td>
                            <td class="px-2 sm:px-6 py-2 sm:py-4 text-gray-400 hidden sm:table-cell">
                                <?php if($promo['expires_at']): ?>
                                    <?php echo date('d/m/Y', strtotime($promo['expires_at'])); ?>
                                <?php else: ?>
                                    Jamais
                                <?php endif; ?>
                            </td>
                            <td class="px-2 sm:px-6 py-2 sm:py-4">
                                <?php 
                                $is_expired = $promo['expires_at'] && strtotime($promo['expires_at']) < time();
                                ?>
                                <?php if($promo['is_active'] && !$is_expired): ?>
                                <span class="bg-green-500/20 text-green-400 px-2 sm:px-3 py-1 rounded-full text-xs font-bold">
                                    <i class="fas fa-check-circle mr-1"></i>Actif
                                </span>
                                <?php elseif($is_expired): ?>
                                <span class="bg-red-500/20 text-red-400 px-2 sm:px-3 py-1 rounded-full text-xs font-bold">
                                    <i class="fas fa-clock mr-1"></i>Expiré
                                </span>
                                <?php else: ?>
                                <span class="bg-gray-600/20 text-gray-400 px-2 sm:px-3 py-1 rounded-full text-xs font-bold">
                                    <i class="fas fa-times-circle mr-1"></i>Inactif
                                </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-2 sm:px-6 py-2 sm:py-4">
                                <div class="flex gap-1 sm:gap-2">
                                    <button onclick='editPromo(<?php echo json_encode($promo); ?>)' 
                                        class="bg-blue-600 hover:bg-blue-700 px-2 sm:px-3 py-1 sm:py-2 rounded-lg text-xs sm:text-sm transition">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <a href="?delete=<?php echo $promo['id']; ?>" 
                                        onclick="return confirm('Supprimer ce code ?')"
                                        class="bg-red-600 hover:bg-red-700 px-2 sm:px-3 py-1 sm:py-2 rounded-lg text-xs sm:text-sm transition">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <!-- Modal Code Promo -->
    <div id="promoModal" class="hidden fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center z-50 p-2">
        <div class="bg-gray-800 rounded-xl p-4 sm:p-8 max-w-full sm:max-w-2xl w-full mx-2 sm:mx-4 max-h-[90vh] overflow-y-auto">
            <h3 class="text-xl sm:text-2xl font-bold mb-4 sm:mb-6">
                <i class="fas fa-tags mr-2 text-purple-500"></i>
                <span id="modalTitle">Nouveau Code Promo</span>
            </h3>
            <form method="POST">
                <input type="hidden" name="save_promo" value="1">
                <input type="hidden" name="promo_id" id="promo_id">
                
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 sm:gap-4 mb-2 sm:mb-4">
                    <div>
                        <label class="block text-gray-300 mb-1 sm:mb-2">Code</label>
                        <input type="text" name="code" id="code" required 
                            class="w-full bg-gray-700 border border-gray-600 rounded-lg px-2 sm:px-4 py-2 sm:py-3 text-white uppercase focus:border-purple-500 focus:outline-none text-xs sm:text-base">
                    </div>
                    <div>
                        <label class="block text-gray-300 mb-1 sm:mb-2">Type de réduction</label>
                        <select name="discount_type" id="discount_type" class="w-full bg-gray-700 border border-gray-600 rounded-lg px-2 sm:px-4 py-2 sm:py-3 text-white focus:border-purple-500 focus:outline-none text-xs sm:text-base">
                            <option value="percentage">Pourcentage (%)</option>
                            <option value="fixed">Montant fixe ($)</option>
                        </select>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 sm:gap-4 mb-2 sm:mb-4">
                    <div>
                        <label class="block text-gray-300 mb-1 sm:mb-2">Valeur de réduction</label>
                        <input type="number" name="discount_value" id="discount_value" step="0.01" required 
                            class="w-full bg-gray-700 border border-gray-600 rounded-lg px-2 sm:px-4 py-2 sm:py-3 text-white focus:border-purple-500 focus:outline-none text-xs sm:text-base">
                    </div>
                    <div>
                        <label class="block text-gray-300 mb-1 sm:mb-2">Achat minimum ($)</label>
                        <input type="number" name="min_purchase" id="min_purchase" step="0.01" value="0"
                            class="w-full bg-gray-700 border border-gray-600 rounded-lg px-2 sm:px-4 py-2 sm:py-3 text-white focus:border-purple-500 focus:outline-none text-xs sm:text-base">
                    </div>
                </div>
                
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 sm:gap-4 mb-2 sm:mb-4">
                    <div>
                        <label class="block text-gray-300 mb-1 sm:mb-2">Utilisations max (vide = illimité)</label>
                        <input type="number" name="max_uses" id="max_uses" 
                            class="w-full bg-gray-700 border border-gray-600 rounded-lg px-2 sm:px-4 py-2 sm:py-3 text-white focus:border-purple-500 focus:outline-none text-xs sm:text-base">
                    </div>
                    <div>
                        <label class="block text-gray-300 mb-1 sm:mb-2">Date d'expiration</label>
                        <input type="datetime-local" name="expires_at" id="expires_at"
                            class="w-full bg-gray-700 border border-gray-600 rounded-lg px-2 sm:px-4 py-2 sm:py-3 text-white focus:border-purple-500 focus:outline-none text-xs sm:text-base">
                    </div>
                </div>
                
                <div class="mb-4 sm:mb-6">
                    <label class="flex items-center gap-2 sm:gap-3 cursor-pointer">
                        <input type="checkbox" name="is_active" id="is_active" class="w-5 h-5" checked>
                        <span class="text-gray-300 text-xs sm:text-base">Code actif</span>
                    </label>
                </div>
                
                <div class="mb-4 sm:mb-6">
                    <label class="flex items-center gap-2 sm:gap-3 cursor-pointer">
                        <input type="checkbox" name="show_in_banner" id="show_in_banner" class="w-5 h-5" checked>
                        <span class="text-gray-300 text-xs sm:text-base">Afficher dans la bannière</span>
                    </label>
                </div>
                
                <div class="flex flex-col sm:flex-row gap-2 sm:gap-3">
                    <button type="button" onclick="closePromoModal()" 
                        class="flex-1 bg-gray-700 hover:bg-gray-600 text-white py-2 sm:py-3 rounded-lg transition text-xs sm:text-base mb-2 sm:mb-0">
                        Annuler
                    </button>
                    <button type="submit" 
                        class="flex-1 bg-purple-600 hover:bg-purple-700 text-white py-2 sm:py-3 rounded-lg transition font-bold text-xs sm:text-base">
                        <i class="fas fa-save mr-2"></i>Enregistrer
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openPromoModal() {
            document.getElementById('modalTitle').textContent = 'Nouveau Code Promo';
            document.getElementById('promo_id').value = '';
            document.getElementById('code').value = '';
            document.getElementById('discount_type').value = 'percentage';
            document.getElementById('discount_value').value = '';
            document.getElementById('min_purchase').value = '0';
            document.getElementById('max_uses').value = '';
            document.getElementById('expires_at').value = '';
            document.getElementById('is_active').checked = true;
            document.getElementById('show_in_banner').checked = true;
            document.getElementById('promoModal').classList.remove('hidden');
        }

        function editPromo(promo) {
            document.getElementById('modalTitle').textContent = 'Modifier le Code Promo';
            document.getElementById('promo_id').value = promo.id;
            document.getElementById('code').value = promo.code;
            document.getElementById('discount_type').value = promo.discount_type;
            document.getElementById('discount_value').value = promo.discount_value;
            document.getElementById('min_purchase').value = promo.min_purchase;
            document.getElementById('max_uses').value = promo.max_uses || '';
            document.getElementById('expires_at').value = promo.expires_at ? promo.expires_at.replace(' ', 'T') : '';
            document.getElementById('is_active').checked = promo.is_active == 1;
            document.getElementById('show_in_banner').checked = promo.show_in_banner == 1;
            document.getElementById('promoModal').classList.remove('hidden');
        }

        function closePromoModal() {
            document.getElementById('promoModal').classList.add('hidden');
        }
    </script>
</body>
</html>
