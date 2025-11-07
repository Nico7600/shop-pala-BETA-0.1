<?php
require_once '../config.php';
session_start();
// Vérifier si admin ou fondateur
if (!in_array($_SESSION['role'] ?? '', ['admin', 'fondateur'])) {
    header('Location: index.php'); exit;
}

$error = null;

// Mettre à jour les statuts expirés
$pdo->query("UPDATE abofac SET permissions='Inactif' WHERE date_fin IS NOT NULL AND date_fin < NOW() AND permissions='Actif'");

// Suppression
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_abonnement'])) {
    $id = intval($_POST['delete_abonnement']);
    if ($id > 0) {
        try {
            // Dissocier l'abonnement des utilisateurs avant suppression
            $pdo->prepare("UPDATE users SET abonnement_id=NULL WHERE abonnement_id=?")->execute([$id]);
            $stmt = $pdo->prepare("DELETE FROM abofac WHERE id=?");
            $stmt->execute([$id]);
        } catch (PDOException $e) {
            $error = "Erreur lors de la suppression : " . htmlspecialchars($e->getMessage());
        }
    } else {
        $error = "ID d'abonnement invalide.";
    }
}

// Edition permissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_perm'])) {
    $id = intval($_POST['aboid']);
    $perm = $_POST['permissions'];
    // Récupérer l'abonnement pour le log
    $abo = $pdo->prepare("SELECT user_id, duree, permissions, date_fin, date_debut FROM abofac WHERE id=?");
    $abo->execute([$id]);
    $row = $abo->fetch();
    $old_perm = $row['permissions'] ?? '';
    $user_id = $row['user_id'] ?? null;
    if ($perm === 'Actif') {
        // Récupérer la durée et l'état actuel
        $abo = $pdo->prepare("SELECT duree, permissions, date_fin, date_debut FROM abofac WHERE id=?");
        $abo->execute([$id]);
        $row = $abo->fetch();
        // Si pas déjà actif ou pas de date_fin, on calcule la date de fin
        if ($row && ($row['permissions'] !== 'Actif' || empty($row['date_fin']))) {
            $jours = 30;
            if ($row['duree'] === '3 mois') $jours = 60;
            elseif ($row['duree'] === '6 mois') $jours = 90;
            // Date de début = date_debut si existant, sinon NOW()
            $date_debut = $row['date_debut'] ?? date('Y-m-d H:i:s');
            $date_fin = date('Y-m-d H:i:s', strtotime($date_debut . " +$jours days"));
            $pdo->prepare("UPDATE abofac SET permissions=?, date_fin=? WHERE id=?")->execute([$perm, $date_fin, $id]);
        } else {
            // Si déjà actif et date_fin présente, ne pas toucher à la date_fin
            $pdo->prepare("UPDATE abofac SET permissions=? WHERE id=?")->execute([$perm, $id]);
        }
    } elseif ($perm === 'Annulé' || $perm === 'Inactif') {
        // Actualise la date de fin uniquement si le statut change
        if ($row && $row['permissions'] !== $perm) {
            $pdo->prepare("UPDATE abofac SET permissions=?, date_fin=? WHERE id=?")->execute([$perm, date('Y-m-d H:i:s'), $id]);
        } else {
            $pdo->prepare("UPDATE abofac SET permissions=? WHERE id=?")->execute([$perm, $id]);
        }
    } else {
        $pdo->prepare("UPDATE abofac SET permissions=? WHERE id=?")->execute([$perm, $id]);
    }
    // Log l'action
    $admin_id = $_SESSION['user_id'] ?? 0;
    $log_stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, details) VALUES (?, ?, ?)");
    $log_stmt->execute([
        $admin_id,
        'change_abonnement_status',
        "Changement statut abonnement #$id (user_id: $user_id) de '$old_perm' à '$perm'"
    ]);
}

// Ajout manuel
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_abonnement'])) {
    $user_id = intval($_POST['user_id']);
    $type = $_POST['type'];
    $duree = $_POST['duree'];
    $prix = intval($_POST['prix']);
    $jours = 30;
    if ($duree === '3 mois') $jours = 60;
    elseif ($duree === '6 mois') $jours = 90;
    $date_fin = date('Y-m-d H:i:s', strtotime("+$jours days"));
    $pdo->prepare("INSERT INTO abofac (user_id, type, duree, prix, date_debut, date_fin, permissions) VALUES (?, ?, ?, ?, NOW(), ?, 'Actif')")
        ->execute([$user_id, $type, $duree, $prix, $date_fin]);
    header("Location: gestion_abos.php");
    exit;
}

// Récupérer tous les utilisateurs pour le select
$users = $pdo->query("SELECT id, username FROM users ORDER BY username ASC")->fetchAll(PDO::FETCH_ASSOC);

// Prix par type et durée
$prix_map = [
    'Individuel' => [
        '1 mois' => 10000,
        '3 mois' => 27000,
        '6 mois' => 48000
    ],
    'Mini Faction' => [
        '1 mois' => 30000,
        '3 mois' => 72000,
        '6 mois' => 128000
    ],
    'Faction' => [
        '1 mois' => 55000,
        '3 mois' => 135000,
        '6 mois' => 240000
    ],
    'Grosse Faction' => [
        '1 mois' => 95000,
        '3 mois' => 228000,
        '6 mois' => 420000
    ]
];

$abos = $pdo->query("SELECT a.*, u.username FROM abofac a LEFT JOIN users u ON a.user_id=u.id")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <title>Gestion Abonnements</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        body {
            background: #18181b;
        }
        .container { max-width: 1100px; }
        .card {
            background: #23232b;
            border-radius: 1.25rem;
            box-shadow: 0 4px 24px 0 rgba(124,58,237,0.12), 0 1.5px 8px 0 rgba(236,72,153,0.10);
            border: 1.5px solid #7c3aed;
        }
        th, td { text-align: left; padding: 0.75rem; }
        th {
            background: #312e81;
            color: #fff;
            font-weight: 600;
            letter-spacing: 0.03em;
        }
        tr:nth-child(even) { background: #23232b; }
        tr:nth-child(odd) { background: #18181b; }
        tr:hover { background: #4c1d95; color: #fff; }
        table { border-collapse: separate; border-spacing: 0; }
        input[type="text"], input[type="number"] {
            border: 1px solid #7c3aed;
            background: #18181b;
            color: #fff;
            transition: border 0.2s;
        }
        input[type="text"]:focus, input[type="number"]:focus {
            outline: none;
            border-color: #c084fc;
            background: #23232b;
        }
        .badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.85rem;
            font-weight: 600;
            color: #fff;
            margin-right: 0.5rem;
            display: inline-block;
        }
        .badge-individuel { background: #a78bfa; }
        .badge-mini { background: #ec4899; }
        .badge-faction { background: #22c55e; }
        .badge-grosse { background: #eab308; color: #18181b; }
        .btn {
            border-radius: 0.5rem;
            font-weight: 600;
            transition: transform 0.15s, box-shadow 0.15s;
        }
        .btn:hover {
            transform: scale(1.05);
            box-shadow: 0 2px 8px 0 #7c3aed44;
        }
    </style>
    <script>
    // Prix JS
    const prixMap = <?php echo json_encode($prix_map); ?>;
    function updatePrix() {
        const type = document.getElementById('type-select').value;
        const duree = document.getElementById('duree-select').value;
        const prixInput = document.getElementById('prix-input');
        if (prixMap[type] && prixMap[type][duree]) {
            prixInput.value = prixMap[type][duree];
        } else {
            prixInput.value = '';
        }
    }
    </script>
</head>
<body class="bg-gray-900 text-gray-100 flex min-h-screen">
    <?php include 'sidebar.php'; ?>
    <div class="container mx-auto p-2 sm:p-8 flex-1 flex items-center justify-center w-full">
        <div class="card w-full p-2 sm:p-8">
            <h1 class="text-xl sm:text-3xl font-bold text-gray-100 mb-4 sm:mb-8 text-center tracking-tight flex items-center justify-center gap-2 sm:gap-3">
                <i class="fas fa-id-card text-purple-400"></i>
                Gestion des abonnements
            </h1>
            <?php if ($error): ?>
                <div class="mb-4 sm:mb-6 p-2 sm:p-4 bg-red-700 text-white rounded-lg font-semibold text-center text-xs sm:text-base">
                    <?= $error ?>
                </div>
            <?php endif; ?>
            <form method="POST" class="mb-4 sm:mb-8 flex flex-wrap gap-2 sm:gap-4 items-center bg-gray-800/80 p-2 sm:p-6 rounded-xl shadow text-xs sm:text-base">
                <select name="user_id" required class="px-2 sm:px-4 py-1 sm:py-2 rounded-lg text-white bg-gray-900 border border-purple-500 focus:ring-2 focus:ring-purple-400 font-semibold" id="user-select">
                    <option value="" disabled selected>Utilisateur</option>
                    <?php foreach($users as $u): ?>
                        <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['username']) ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="type" required class="px-2 sm:px-4 py-1 sm:py-2 rounded-lg text-white bg-gray-900 border border-purple-500 focus:ring-2 focus:ring-purple-400 font-semibold" id="type-select" onchange="updatePrix()">
                    <option value="" disabled selected>Type d'abonnement</option>
                    <option value="Individuel">Individuel</option>
                    <option value="Mini Faction">Mini Faction</option>
                    <option value="Faction">Faction</option>
                    <option value="Grosse Faction">Grosse Faction</option>
                </select>
                <select name="duree" required class="px-2 sm:px-4 py-1 sm:py-2 rounded-lg text-white bg-gray-900 border border-purple-500 focus:ring-2 focus:ring-purple-400 font-semibold" id="duree-select" onchange="updatePrix()">
                    <option value="" disabled selected>Durée</option>
                    <option value="1 mois">1 mois</option>
                    <option value="3 mois">3 mois</option>
                    <option value="6 mois">6 mois</option>
                </select>
                <input type="number" name="prix" id="prix-input" placeholder="Prix" required class="px-2 sm:px-4 py-1 sm:py-2 rounded-lg text-white bg-gray-900 border border-purple-500 focus:ring-2 focus:ring-purple-400 font-semibold" readonly>
                <button type="submit" name="add_abonnement" class="btn bg-gradient-to-r from-purple-700 to-pink-700 px-4 sm:px-8 py-1 sm:py-2 text-white shadow text-xs sm:text-base">Ajouter</button>
            </form>
            <script>
                document.getElementById('type-select').addEventListener('change', updatePrix);
                document.getElementById('duree-select').addEventListener('change', updatePrix);
            </script>
            <div class="overflow-x-auto">
            <table class="w-full rounded-xl shadow-xl overflow-hidden text-xs sm:text-base">
                <tr>
                    <th>ID</th>
                    <th>User</th>
                    <th>Type</th>
                    <th class="hidden sm:table-cell">Durée</th>
                    <th>Prix</th>
                    <th class="hidden sm:table-cell">Début</th>
                    <th class="hidden sm:table-cell">Fin de période</th>
                    <th>Permissions</th>
                    <th>Actions</th>
                </tr>
                <?php foreach ($abos as $abo): ?>
                <tr class="transition-all duration-150">
                    <td><?= $abo['id'] ?></td>
                    <td><span class="font-semibold text-purple-300"><?= htmlspecialchars($abo['username']) ?></span></td>
                    <td>
                        <?php
                        $type = strtolower($abo['type']);
                        $badge_class = 'badge-individuel';
                        if(strpos($type, 'mini') !== false) $badge_class = 'badge-mini';
                        elseif(strpos($type, 'grosse') !== false) $badge_class = 'badge-grosse';
                        elseif(strpos($type, 'faction') !== false) $badge_class = 'badge-faction';
                        ?>
                        <span class="badge <?= $badge_class ?>"><?= htmlspecialchars($abo['type']) ?></span>
                    </td>
                    <td class="hidden sm:table-cell"><?= htmlspecialchars($abo['duree']) ?></td>
                    <td><span class="font-bold text-green-400"><?= number_format($abo['prix'], 0, ',', ' ') ?> $</span></td>
                    <td class="hidden sm:table-cell"><?= htmlspecialchars($abo['date_debut']) ?></td>
                    <td class="hidden sm:table-cell"><?= htmlspecialchars($abo['date_fin']) ?></td>
                    <td>
                        <form method="POST" class="flex flex-col sm:flex-row items-center gap-1 sm:gap-2">
                            <input type="hidden" name="aboid" value="<?= $abo['id'] ?>">
                            <?php
                            $perm_options = [
                                'En attente de paiement',
                                'Actif',
                                'Inactif',
                                'Annulé'
                            ];
                            ?>
                            <select name="permissions" class="px-2 py-1 rounded-lg text-white bg-gray-900 border border-purple-500 focus:ring-2 focus:ring-purple-400 font-semibold transition-all duration-150 text-xs sm:text-base">
                                <?php foreach($perm_options as $opt): ?>
                                    <option value="<?= $opt ?>" <?= ($abo['permissions'] == $opt) ? 'selected' : '' ?>><?= $opt ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" name="edit_perm" class="btn bg-green-700 px-2 sm:px-3 py-1 text-white shadow text-xs sm:text-base">Edit</button>
                        </form>
                    </td>
                    <td>
                        <form method="POST" onsubmit="return confirm('Confirmer la suppression ?');" class="inline">
                            <input type="hidden" name="delete_abonnement" value="<?= $abo['id'] ?>">
                            <button type="submit" class="btn bg-red-700 px-2 sm:px-3 py-1 text-white shadow text-xs sm:text-base">Supprimer</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
            </div>
        </div>
    </div>
</body>
</html>
