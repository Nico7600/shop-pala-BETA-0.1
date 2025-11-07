<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'fondateur'])) {
    header('Location: ../login.php');
    exit;
}

// Action : valider/refuser
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'], $_POST['action'])) {
    $id = intval($_POST['id']);
    $action = $_POST['action'] === 'valider' ? 'valide' : 'refuse';
    $stmt = $pdo->prepare("UPDATE echanges SET statut = ? WHERE id = ?");
    $stmt->execute([$action, $id]);
}

// Récupération des échanges
$stmt = $pdo->query("SELECT e.id, u.username, e.nom_paladium_java, e.nom_paladium_bedrock, e.nom_discord, e.type_echange, e.titre, e.description, e.statut, e.created_at FROM echanges e JOIN users u ON e.user_id = u.id ORDER BY e.created_at DESC");
$echanges = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Gestion des échanges</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="bg-gray-900 text-gray-100 min-h-screen flex flex-col">
    <?php include 'sidebar.php'; ?>
    <main class="flex-1 flex items-center justify-center w-full">
        <div class="w-full max-w-7xl mx-auto bg-gray-800/90 shadow-2xl rounded-2xl p-2 sm:p-8 border-2 border-blue-700 backdrop-blur mt-4 sm:mt-10">
            <h1 class="text-xl sm:text-3xl font-bold text-center mb-4 sm:mb-8 flex items-center justify-center gap-2 sm:gap-3">
                <i class="fas fa-exchange-alt text-blue-400"></i>
                Gestion des échanges
            </h1>
            <div class="overflow-x-auto">
                <table class="w-full text-left border border-gray-700 rounded-lg overflow-hidden mb-6 sm:mb-10 bg-gray-900/80 text-xs sm:text-base">
                    <thead>
                        <tr>
                            <th class="py-2 sm:py-3 px-2 sm:px-4 bg-gray-800/60 text-gray-300">ID</th>
                            <th class="py-2 sm:py-3 px-2 sm:px-4 bg-gray-800/60 text-gray-300">Utilisateur</th>
                            <th class="py-2 sm:py-3 px-2 sm:px-4 bg-gray-800/60 text-gray-300 hidden sm:table-cell">Java</th>
                            <th class="py-2 sm:py-3 px-2 sm:px-4 bg-gray-800/60 text-gray-300 hidden sm:table-cell">Bedrock</th>
                            <th class="py-2 sm:py-3 px-2 sm:px-4 bg-gray-800/60 text-gray-300 hidden sm:table-cell">Discord</th>
                            <th class="py-2 sm:py-3 px-2 sm:px-4 bg-gray-800/60 text-gray-300">Type</th>
                            <th class="py-2 sm:py-3 px-2 sm:px-4 bg-gray-800/60 text-gray-300">Titre</th>
                            <th class="py-2 sm:py-3 px-2 sm:px-4 bg-gray-800/60 text-gray-300 hidden sm:table-cell">Description</th>
                            <th class="py-2 sm:py-3 px-2 sm:px-4 bg-gray-800/60 text-gray-300">Statut</th>
                            <th class="py-2 sm:py-3 px-2 sm:px-4 bg-gray-800/60 text-gray-300">Date</th>
                            <th class="py-2 sm:py-3 px-2 sm:px-4 bg-gray-800/60 text-gray-300">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($echanges) === 0): ?>
                            <tr>
                                <td colspan="11" class="py-4 sm:py-6 px-2 sm:px-4 text-center text-gray-400">Aucun échange à afficher.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($echanges as $e): ?>
                            <tr class="<?= $e['statut'] === 'valide' ? 'bg-green-950/40' : ($e['statut'] === 'refuse' ? 'bg-red-950/40' : 'bg-yellow-950/30') ?> border-b border-gray-800">
                                <td class="py-2 sm:py-3 px-2 sm:px-4 font-mono"><?= $e['id'] ?></td>
                                <td class="py-2 sm:py-3 px-2 sm:px-4"><?= htmlspecialchars($e['username']) ?></td>
                                <td class="py-2 sm:py-3 px-2 sm:px-4 hidden sm:table-cell"><?= htmlspecialchars($e['nom_paladium_java']) ?></td>
                                <td class="py-2 sm:py-3 px-2 sm:px-4 hidden sm:table-cell"><?= htmlspecialchars($e['nom_paladium_bedrock']) ?></td>
                                <td class="py-2 sm:py-3 px-2 sm:px-4 hidden sm:table-cell"><?= htmlspecialchars($e['nom_discord']) ?></td>
                                <td class="py-2 sm:py-3 px-2 sm:px-4"><?= htmlspecialchars($e['type_echange']) ?></td>
                                <td class="py-2 sm:py-3 px-2 sm:px-4"><?= htmlspecialchars($e['titre']) ?></td>
                                <td class="py-2 sm:py-3 px-2 sm:px-4 hidden sm:table-cell"><?= htmlspecialchars($e['description']) ?></td>
                                <td class="py-2 sm:py-3 px-2 sm:px-4">
                                    <?php if ($e['statut'] === 'valide'): ?>
                                        <span class="inline-flex items-center gap-1 sm:gap-2 px-2 sm:px-3 py-1 rounded bg-green-700 text-green-300 font-semibold text-xs sm:text-base">
                                            <i class="fas fa-check-circle"></i> Validé
                                        </span>
                                    <?php elseif ($e['statut'] === 'refuse'): ?>
                                        <span class="inline-flex items-center gap-1 sm:gap-2 px-2 sm:px-3 py-1 rounded bg-red-700 text-red-300 font-semibold text-xs sm:text-base">
                                            <i class="fas fa-times-circle"></i> Refusé
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1 sm:gap-2 px-2 sm:px-3 py-1 rounded bg-yellow-700 text-yellow-300 font-semibold text-xs sm:text-base">
                                            <i class="fas fa-hourglass-half"></i> En attente
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-2 sm:py-3 px-2 sm:px-4"><?= date('d/m/Y H:i', strtotime($e['created_at'])) ?></td>
                                <td class="py-2 sm:py-3 px-2 sm:px-4">
                                    <?php if ($e['statut'] === 'attente'): ?>
                                    <form method="post" class="flex flex-col sm:flex-row gap-2">
                                        <input type="hidden" name="id" value="<?= $e['id'] ?>">
                                        <button name="action" value="valider" class="bg-green-600 hover:bg-green-700 text-white px-2 sm:px-3 py-1 rounded flex items-center gap-1 text-xs sm:text-base"><i class="fas fa-check"></i> Valider</button>
                                        <button name="action" value="refuser" class="bg-red-600 hover:bg-red-700 text-white px-2 sm:px-3 py-1 rounded flex items-center gap-1 text-xs sm:text-base"><i class="fas fa-times"></i> Refuser</button>
                                    </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</body>
</html>
