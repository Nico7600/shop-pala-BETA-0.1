<?php
session_start();
require_once '../config.php';

// Vérification du rôle
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'fondateur'])) {
    header('Location: ../login.php');
    exit;
}

// Fonction pour ajouter un log d'activité
function log_activity($pdo, $user_id, $action, $details, $custom_message = null) {
    $message = $custom_message ?? $action;
    $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, details) VALUES (?, ?, ?)");
    $stmt->execute([$user_id, $message, $details]);
}

// Traitement validation/refus/supprimer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['echange_id'])) {
    $id = intval($_POST['echange_id']);
    $stmt_info = $pdo->prepare("SELECT e.*, u.username FROM echanges e JOIN users u ON e.user_id = u.id WHERE e.id = ?");
    $stmt_info->execute([$id]);
    $echange_info = $stmt_info->fetch();
    $details = $echange_info ? json_encode([
        'id' => $echange_info['id'],
        'titre' => $echange_info['titre'],
        'type_echange' => $echange_info['type_echange'],
        'description' => $echange_info['description'],
        'nom_discord' => $echange_info['nom_discord'],
        'username' => $echange_info['username'],
        'statut' => $echange_info['statut']
    ]) : 'Echange introuvable';

    if ($_POST['action'] === 'valider') {
        $stmt = $pdo->prepare("UPDATE echanges SET statut = 'valide' WHERE id = ?");
        $stmt->execute([$id]);
        log_activity(
            $pdo,
            $_SESSION['user_id'],
            'valider',
            $details,
            "Échange validé : « {$echange_info['titre']} » par {$echange_info['username']}"
        );
    } elseif ($_POST['action'] === 'refuser') {
        $stmt = $pdo->prepare("UPDATE echanges SET statut = 'refuse' WHERE id = ?");
        $stmt->execute([$id]);
        log_activity(
            $pdo,
            $_SESSION['user_id'],
            'refuser',
            $details,
            "Échange refusé : « {$echange_info['titre']} » par {$echange_info['username']}"
        );
    } elseif ($_POST['action'] === 'attente') {
        $stmt = $pdo->prepare("UPDATE echanges SET statut = 'attente' WHERE id = ?");
        $stmt->execute([$id]);
        log_activity(
            $pdo,
            $_SESSION['user_id'],
            'remettre_attente',
            $details,
            "Échange remis en attente : « {$echange_info['titre']} » par {$echange_info['username']}"
        );
    } elseif ($_POST['action'] === 'supprimer') {
        $stmt = $pdo->prepare("DELETE FROM echanges WHERE id = ?");
        $stmt->execute([$id]);
        log_activity(
            $pdo,
            $_SESSION['user_id'],
            'supprimer',
            $details,
            "Échange supprimé définitivement : « {$echange_info['titre']} » par {$echange_info['username']}"
        );
    }
    header('Location: echanges.php');
    exit;
}

// Récupérer les échanges en attente
$stmt = $pdo->prepare("SELECT e.*, u.username FROM echanges e JOIN users u ON e.user_id = u.id WHERE e.statut = 'attente' ORDER BY e.created_at DESC");
$stmt->execute();
$echanges = $stmt->fetchAll();

// Récupérer les échanges validés
$stmt_valides = $pdo->prepare("SELECT e.*, u.username FROM echanges e JOIN users u ON e.user_id = u.id WHERE e.statut = 'valide' ORDER BY e.created_at DESC");
$stmt_valides->execute();
$echanges_valides = $stmt_valides->fetchAll();

// Récupérer les échanges refusés
$stmt_refuses_list = $pdo->prepare("SELECT e.*, u.username FROM echanges e JOIN users u ON e.user_id = u.id WHERE e.statut = 'refuse' ORDER BY e.created_at DESC");
$stmt_refuses_list->execute();
$echanges_refuses = $stmt_refuses_list->fetchAll();

// Statistiques
$stmt_refuses = $pdo->prepare("SELECT COUNT(*) FROM echanges WHERE statut = 'refuse'");
$stmt_refuses->execute();
$nb_refuses = $stmt_refuses->fetchColumn();

$nb_attente = count($echanges);
$nb_valides = count($echanges_valides);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Gestion des échanges | Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="bg-gray-900 text-gray-100">
    <div class="flex flex-col md:flex-row min-h-screen">
        <!-- Sidebar -->
        <?php
        // Début intégration du contenu de slidebar.php
        include 'sidebar.php';
        // Fin intégration slidebar.php
        ?>
        <!-- Contenu principal -->
        <main class="flex-1 p-2 sm:p-8 w-full">
            <div class="container mx-auto">
                <!-- En-tête -->
                <div class="bg-gray-800 rounded-2xl shadow-2xl p-4 md:p-8 max-w-7xl mx-auto">
                    <header class="flex items-center gap-4 mb-8 border-b border-gray-700 pb-4">
                        <span class="bg-blue-900 rounded-full p-3 flex items-center justify-center">
                            <i class="fas fa-exchange-alt text-blue-400 text-2xl md:text-3xl"></i>
                        </span>
                        <div>
                            <h1 class="text-2xl md:text-3xl font-bold text-blue-400">Gestion des échanges</h1>
                            <p class="text-gray-400 text-sm md:text-base">Vue d'ensemble et gestion des demandes d'échange.</p>
                        </div>
                    </header>
                    <!-- Statistiques -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                        <div class="bg-yellow-500/90 rounded-xl shadow-lg flex items-center gap-4 px-6 py-4">
                            <i class="fas fa-hourglass-half text-yellow-900 text-3xl"></i>
                            <div>
                                <div class="text-2xl font-bold"><?= $nb_attente ?></div>
                                <div class="text-sm font-semibold text-yellow-900">En attente</div>
                            </div>
                        </div>
                        <div class="bg-green-500/90 rounded-xl shadow-lg flex items-center gap-4 px-6 py-4">
                            <i class="fas fa-check-circle text-green-900 text-3xl"></i>
                            <div>
                                <div class="text-2xl font-bold"><?= $nb_valides ?></div>
                                <div class="text-sm font-semibold text-green-900">Validés</div>
                            </div>
                        </div>
                        <div class="bg-red-500/90 rounded-xl shadow-lg flex items-center gap-4 px-6 py-4">
                            <i class="fas fa-times-circle text-red-900 text-3xl"></i>
                            <div>
                                <div class="text-2xl font-bold"><?= $nb_refuses ?></div>
                                <div class="text-sm font-semibold text-red-900">Refusés</div>
                            </div>
                        </div>
                    </div>
                    <!-- Tableaux -->
                    <div class="grid grid-cols-1 gap-8">
                        <!-- Cartes des échanges en attente -->
                        <section>
                            <h2 class="text-lg font-bold text-yellow-400 mb-3 flex items-center gap-2">
                                <i class="fas fa-hourglass-half"></i> En attente
                            </h2>
                            <?php if (empty($echanges)): ?>
                                <div class="text-center text-gray-400 py-4">Aucune demande en attente.</div>
                            <?php else: ?>
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                                <?php foreach ($echanges as $e): ?>
                                <div class="bg-gray-800 rounded-xl shadow-lg border border-yellow-500/40 p-4 flex flex-col gap-2">
                                    <div class="flex items-center gap-2 mb-2">
                                        <i class="fas fa-user text-blue-400"></i>
                                        <span class="font-bold"><?= htmlspecialchars($e['username']) ?></span>
                                    </div>
                                    <div class="font-semibold text-lg text-yellow-300"><?= htmlspecialchars($e['titre']) ?></div>
                                    <div>
                                        <span class="inline-block px-2 py-1 rounded text-xs font-bold
                                            <?= $e['type_echange'] === 'Java vers Bedrock' ? 'bg-blue-700 text-blue-200' : 'bg-purple-700 text-purple-200' ?>">
                                            <i class="fas fa-retweet"></i> <?= htmlspecialchars($e['type_echange']) ?>
                                        </span>
                                    </div>
                                    <div class="text-gray-300 text-sm"><?= htmlspecialchars($e['description']) ?></div>
                                    <div class="flex items-center gap-2 text-indigo-400">
                                        <i class="fab fa-discord"></i>
                                        <span><?= htmlspecialchars($e['nom_discord']) ?></span>
                                    </div>
                                    <div class="flex items-center gap-2 text-yellow-400 text-xs">
                                        <i class="fas fa-clock"></i>
                                        <?= date('d/m/Y H:i', strtotime($e['created_at'])) ?>
                                    </div>
                                    <div class="flex gap-2 justify-end mt-2">
                                        <form method="post" style="display:inline;">
                                            <input type="hidden" name="echange_id" value="<?= $e['id'] ?>">
                                            <input type="hidden" name="action" value="valider">
                                            <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-2 py-1 rounded font-bold flex items-center gap-1 text-xs" title="Valider">
                                                <i class="fas fa-check"></i>
                                            </button>
                                        </form>
                                        <form method="post" style="display:inline;">
                                            <input type="hidden" name="echange_id" value="<?= $e['id'] ?>">
                                            <input type="hidden" name="action" value="refuser">
                                            <button type="submit" class="bg-red-600 hover:bg-red-700 text-white px-2 py-1 rounded font-bold flex items-center gap-1 text-xs" title="Refuser">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </section>
                        <!-- Cartes des échanges validés -->
                        <section>
                            <h2 class="text-lg font-bold text-green-400 mb-3 flex items-center gap-2">
                                <i class="fas fa-check-circle"></i> Validés
                            </h2>
                            <?php if (empty($echanges_valides)): ?>
                                <div class="text-center text-gray-400 py-4">Aucun échange validé.</div>
                            <?php else: ?>
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                                <?php foreach ($echanges_valides as $e): ?>
                                <div class="bg-gray-800 rounded-xl shadow-lg border border-green-500/40 p-4 flex flex-col gap-2">
                                    <div class="flex items-center gap-2 mb-2">
                                        <i class="fas fa-user text-blue-400"></i>
                                        <span class="font-bold"><?= htmlspecialchars($e['username']) ?></span>
                                    </div>
                                    <div class="font-semibold text-lg text-green-300"><?= htmlspecialchars($e['titre']) ?></div>
                                    <div>
                                        <span class="inline-block px-2 py-1 rounded text-xs font-bold
                                            <?= $e['type_echange'] === 'Java vers Bedrock' ? 'bg-blue-700 text-blue-200' : 'bg-purple-700 text-purple-200' ?>">
                                            <i class="fas fa-retweet"></i> <?= htmlspecialchars($e['type_echange']) ?>
                                        </span>
                                    </div>
                                    <div class="text-gray-300 text-sm"><?= htmlspecialchars($e['description']) ?></div>
                                    <div class="flex items-center gap-2 text-indigo-400">
                                        <i class="fab fa-discord"></i>
                                        <span><?= htmlspecialchars($e['nom_discord']) ?></span>
                                    </div>
                                    <div class="flex items-center gap-2 text-yellow-400 text-xs">
                                        <i class="fas fa-clock"></i>
                                        <?= date('d/m/Y H:i', strtotime($e['created_at'])) ?>
                                    </div>
                                    <div class="flex gap-2 justify-end mt-2">
                                        <form method="post" style="display:inline;">
                                            <input type="hidden" name="echange_id" value="<?= $e['id'] ?>">
                                            <input type="hidden" name="action" value="attente">
                                            <button type="submit" class="bg-yellow-600 hover:bg-yellow-700 text-white px-2 py-1 rounded font-bold flex items-center gap-1 text-xs" title="Mettre en attente">
                                                <i class="fas fa-hourglass-half"></i>
                                            </button>
                                        </form>
                                        <form method="post" style="display:inline;">
                                            <input type="hidden" name="echange_id" value="<?= $e['id'] ?>">
                                            <input type="hidden" name="action" value="refuser">
                                            <button type="submit" class="bg-red-600 hover:bg-red-700 text-white px-2 py-1 rounded font-bold flex items-center gap-1 text-xs" title="Refuser">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </section>
                        <!-- Cartes des échanges refusés -->
                        <section>
                            <h2 class="text-lg font-bold text-red-400 mb-3 flex items-center gap-2">
                                <i class="fas fa-times-circle"></i> Refusés
                            </h2>
                            <?php if (empty($echanges_refuses)): ?>
                                <div class="text-center text-gray-400 py-4">Aucun échange refusé.</div>
                            <?php else: ?>
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                                <?php foreach ($echanges_refuses as $e): ?>
                                <div class="bg-gray-800 rounded-xl shadow-lg border border-red-500/40 p-4 flex flex-col gap-2 opacity-80">
                                    <div class="flex items-center gap-2 mb-2">
                                        <i class="fas fa-user text-blue-400"></i>
                                        <span class="font-bold"><?= htmlspecialchars($e['username']) ?></span>
                                    </div>
                                    <div class="font-semibold text-lg text-red-300"><?= htmlspecialchars($e['titre']) ?></div>
                                    <div>
                                        <span class="inline-block px-2 py-1 rounded text-xs font-bold
                                            <?= $e['type_echange'] === 'Java vers Bedrock' ? 'bg-blue-700 text-blue-200' : 'bg-purple-700 text-purple-200' ?>">
                                            <i class="fas fa-retweet"></i> <?= htmlspecialchars($e['type_echange']) ?>
                                        </span>
                                    </div>
                                    <div class="text-gray-300 text-sm"><?= htmlspecialchars($e['description']) ?></div>
                                    <div class="flex items-center gap-2 text-indigo-400">
                                        <i class="fab fa-discord"></i>
                                        <span><?= htmlspecialchars($e['nom_discord']) ?></span>
                                    </div>
                                    <div class="flex items-center gap-2 text-yellow-400 text-xs">
                                        <i class="fas fa-clock"></i>
                                        <?= date('d/m/Y H:i', strtotime($e['created_at'])) ?>
                                    </div>
                                    <div class="flex gap-2 justify-end mt-2">
                                        <form method="post" style="display:inline;">
                                            <input type="hidden" name="echange_id" value="<?= $e['id'] ?>">
                                            <input type="hidden" name="action" value="attente">
                                            <button type="submit" class="bg-yellow-600 hover:bg-yellow-700 text-white px-2 py-1 rounded font-bold flex items-center gap-1 text-xs" title="Remettre en attente">
                                                <i class="fas fa-undo"></i>
                                            </button>
                                        </form>
                                        <form method="post" style="display:inline;" onsubmit="return confirm('Supprimer définitivement cet échange ?');">
                                            <input type="hidden" name="echange_id" value="<?= $e['id'] ?>">
                                            <input type="hidden" name="action" value="supprimer">
                                            <button type="submit" class="bg-red-800 hover:bg-red-900 text-white px-2 py-1 rounded font-bold flex items-center gap-1 text-xs" title="Supprimer">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </section>
                    </div>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
