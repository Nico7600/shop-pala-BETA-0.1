<?php
session_start();
require_once 'config.php';

$notif = '';
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Gestion de l'ajout d'échange
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_echange'])) {
    // Vérifier le nombre d'offres actives de l'utilisateur
    $stmt_count = $pdo->prepare("SELECT COUNT(*) FROM echanges WHERE user_id = ? AND (statut = 'attente' OR statut = 'valide')");
    $stmt_count->execute([$_SESSION['user_id']]);
    $nb_offres = $stmt_count->fetchColumn();
    if ($nb_offres >= 5) {
        $notif = "Vous ne pouvez pas avoir plus de 5 offres d'échange actives en même temps.";
    } else {
        $nom_java = trim($_POST['nom_paladium_java']);
        $nom_bedrock = trim($_POST['nom_paladium_bedrock']);
        $nom_discord = trim($_POST['nom_discord']);
        $type_echange = $_POST['type_echange'];
        $titre = trim($_POST['titre']);
        $description = trim($_POST['description']);
        if (
            empty($nom_java) || empty($nom_bedrock) || empty($nom_discord) ||
            empty($type_echange) || empty($titre) || empty($description)
        ) {
            $notif = "Veuillez remplir tous les champs.";
        } else {
            $stmt = $pdo->prepare("INSERT INTO echanges (user_id, nom_paladium_java, nom_paladium_bedrock, nom_discord, type_echange, titre, description, statut, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'attente', NOW())");
            if ($stmt->execute([$_SESSION['user_id'], $nom_java, $nom_bedrock, $nom_discord, $type_echange, $titre, $description])) {
                $_SESSION['notif_echange'] = "Votre proposition d'échange a été envoyée et est en attente de validation.";
                header('Location: echange.php');
                exit;
            } else {
                $notif = "Erreur lors de l'envoi.";
            }
        }
    }
}

// Suppression et archivage
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_echange'])) {
    $echange_id = intval($_POST['delete_echange']);
    // Récupérer l'échange à supprimer
    $stmt = $pdo->prepare("SELECT * FROM echanges WHERE id = ? AND user_id = ?");
    $stmt->execute([$echange_id, $_SESSION['user_id']]);
    $echange = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($echange) {
        // Archiver
        $fields = array_keys($echange);
        $columns = implode(',', $fields);
        $placeholders = implode(',', array_fill(0, count($fields), '?'));
        $archive_stmt = $pdo->prepare("INSERT INTO echanges_archive ($columns) VALUES ($placeholders)");
        $archive_stmt->execute(array_values($echange));
        // Supprimer
        $del_stmt = $pdo->prepare("DELETE FROM echanges WHERE id = ?");
        $del_stmt->execute([$echange_id]);
        $_SESSION['notif_echange'] = "L'offre d'échange a été supprimée et archivée.";
        header('Location: echange.php');
        exit;
    }
}

// Affichage notification après redirection
if (isset($_SESSION['notif_echange'])) {
    $notif = $_SESSION['notif_echange'];
    unset($_SESSION['notif_echange']);
}

// Récupérer les échanges (ajoute les nouveaux champs)
$stmt = $pdo->prepare("SELECT e.id, e.titre, e.description, e.statut, e.created_at, e.nom_paladium_java, e.nom_paladium_bedrock, e.nom_discord, e.type_echange, u.username, e.user_id FROM echanges e JOIN users u ON e.user_id = u.id ORDER BY e.created_at DESC");
$stmt->execute();
$echanges = $stmt->fetchAll();

// Filtrer les échanges pour l'affichage
$echanges_affichage = [];
foreach ($echanges as $echange) {
    // Si l'échange est en attente, ne l'afficher que pour son créateur
    if ($echange['statut'] === 'attente' && $echange['user_id'] != $_SESSION['user_id']) {
        continue;
    }
    $echanges_affichage[] = $echange;
}

// Archivage automatique des échanges refusés
foreach ($echanges as $echange) {
    if ($echange['statut'] === 'refuse') {
        // Récupérer l'échange complet
        $stmt_echange = $pdo->prepare("SELECT * FROM echanges WHERE id = ?");
        $stmt_echange->execute([$echange['id']]);
        $echange_complet = $stmt_echange->fetch(PDO::FETCH_ASSOC);
        if ($echange_complet) {
            // Archiver
            $fields = array_keys($echange_complet);
            $columns = implode(',', $fields);
            $placeholders = implode(',', array_fill(0, count($fields), '?'));
            $archive_stmt = $pdo->prepare("INSERT INTO echanges_archive ($columns) VALUES ($placeholders)");
            $archive_stmt->execute(array_values($echange_complet));
            // Supprimer
            $del_stmt = $pdo->prepare("DELETE FROM echanges WHERE id = ?");
            $del_stmt->execute([$echange['id']]);
        }
    }
}

// Récupérer le nombre d'offres actives pour l'affichage du bouton
$stmt_count = $pdo->prepare("SELECT COUNT(*) FROM echanges WHERE user_id = ? AND (statut = 'attente' OR statut = 'valide')");
$stmt_count->execute([$_SESSION['user_id']]);
$nb_offres_actives = $stmt_count->fetchColumn();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Échange</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="bg-gray-900 text-gray-100 min-h-screen flex flex-col">
    <?php include 'includes/header.php'; ?>
    <main class="flex-1 flex flex-col items-center justify-start">
        <div class="w-full max-w-6xl mx-auto bg-gray-800/80 shadow-2xl rounded-2xl p-10 border-2 border-gray-700 backdrop-blur mt-10 relative">
            <div class="flex justify-between items-center mb-8">
                <h1 class="text-3xl font-bold flex items-center gap-3">
                    <i class="fas fa-exchange-alt text-blue-400"></i>
                    Échange
                </h1>
                <?php if ($nb_offres_actives >= 5): ?>
                    <button class="bg-gray-600 text-white font-bold py-2 px-4 rounded-xl shadow-lg flex items-center gap-2 text-base cursor-not-allowed opacity-60" disabled>
                        <i class="fas fa-ban"></i> Limite d'offres atteinte
                    </button>
                <?php else: ?>
                    <button onclick="document.getElementById('addEchangeModal').classList.remove('hidden')" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-xl shadow-lg flex items-center gap-2 text-base">
                        <i class="fas fa-plus"></i> Proposer un échange
                    </button>
                <?php endif; ?>
            </div>
            <?php if ($notif): ?>
                <div class="mb-6 text-center text-blue-400 font-semibold"><?= htmlspecialchars($notif) ?></div>
            <?php endif; ?>

            <!-- Grille d'échanges façon catalog.php -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <?php foreach ($echanges_affichage as $e): ?>
                <div class="bg-gray-900 rounded-xl shadow-lg border border-gray-700 p-6 flex flex-col justify-between">
                    <div>
                        <h2 class="text-xl font-bold text-blue-400 mb-2"><?= htmlspecialchars($e['titre']) ?></h2>
                        <p class="text-gray-300 mb-2"><i class="fas fa-user mr-1"></i><?= htmlspecialchars($e['username']) ?></p>
                        <p class="text-sm mb-2">
                            <span class="font-semibold">Statut :</span>
                            <span class="<?= $e['statut'] === 'valide' ? 'text-green-400' : ($e['statut'] === 'refuse' ? 'text-red-400' : 'text-yellow-400') ?>">
                                <?= ucfirst($e['statut']) ?>
                            </span>
                        </p>
                        <p class="text-xs text-gray-400 mb-4"><i class="fas fa-clock mr-1"></i><?= date('d/m/Y H:i', strtotime($e['created_at'])) ?></p>
                    </div>
                    <button class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded mt-2 flex items-center justify-center gap-2"
                        onclick="showDetails(<?= htmlspecialchars(json_encode($e)) ?>)">
                        <i class="fas fa-eye"></i> Détails
                    </button>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Modal ajout échange -->
        <div id="addEchangeModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 hidden">
            <div class="bg-gray-900 rounded-2xl shadow-2xl p-8 w-full max-w-md border-2 border-blue-600 relative">
                <button onclick="document.getElementById('addEchangeModal').classList.add('hidden')" class="absolute top-4 right-4 text-gray-400 hover:text-white text-xl">
                    <i class="fas fa-times"></i>
                </button>
                <h2 class="text-2xl font-bold mb-6 text-blue-400 text-center">Proposer un échange</h2>
                <form method="post" class="space-y-6">
                    <input type="hidden" name="add_echange" value="1">
                    <div>
                        <label for="nom_paladium_java" class="block font-semibold mb-2 text-gray-300">Nom Paladium Java</label>
                        <input type="text" id="nom_paladium_java" name="nom_paladium_java" required class="w-full px-4 py-3 rounded-lg bg-gray-800 text-gray-100 border border-gray-700 focus:outline-none focus:border-blue-500 transition">
                    </div>
                    <div>
                        <label for="nom_paladium_bedrock" class="block font-semibold mb-2 text-gray-300">Nom Paladium Bedrock</label>
                        <input type="text" id="nom_paladium_bedrock" name="nom_paladium_bedrock" required class="w-full px-4 py-3 rounded-lg bg-gray-800 text-gray-100 border border-gray-700 focus:outline-none focus:border-blue-500 transition">
                    </div>
                    <div>
                        <label for="nom_discord" class="block font-semibold mb-2 text-gray-300">Nom Discord</label>
                        <input type="text" id="nom_discord" name="nom_discord" required class="w-full px-4 py-3 rounded-lg bg-gray-800 text-gray-100 border border-gray-700 focus:outline-none focus:border-blue-500 transition">
                    </div>
                    <div>
                        <label for="type_echange" class="block font-semibold mb-2 text-gray-300">Type d'échange</label>
                        <select id="type_echange" name="type_echange" required class="w-full px-4 py-3 rounded-lg bg-gray-800 text-gray-100 border border-gray-700 focus:outline-none focus:border-blue-500 transition">
                            <option value="">Sélectionner...</option>
                            <option value="Java vers Bedrock">Java vers Bedrock</option>
                            <option value="Bedrock vers Java">Bedrock vers Java</option>
                        </select>
                    </div>
                    <div>
                        <label for="titre" class="block font-semibold mb-2 text-gray-300">Titre de l'échange</label>
                        <input type="text" id="titre" name="titre" required class="w-full px-4 py-3 rounded-lg bg-gray-800 text-gray-100 border border-gray-700 focus:outline-none focus:border-blue-500 transition">
                    </div>
                    <div>
                        <label for="description" class="block font-semibold mb-2 text-gray-300">Description</label>
                        <textarea id="description" name="description" required rows="4" class="w-full px-4 py-3 rounded-lg bg-gray-800 text-gray-100 border border-gray-700 focus:outline-none focus:border-blue-500 transition"></textarea>
                    </div>
                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-6 rounded-xl shadow-lg transition duration-300 transform hover:scale-105 flex items-center justify-center gap-2 text-lg">
                        <i class="fas fa-paper-plane"></i> Envoyer
                    </button>
                </form>
            </div>
        </div>

        <!-- Modal détails échange -->
        <div id="detailsModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 hidden">
            <div class="bg-gray-900 rounded-2xl shadow-2xl p-8 w-full max-w-md border-2 border-blue-600 relative">
                <button onclick="document.getElementById('detailsModal').classList.add('hidden')" class="absolute top-4 right-4 text-gray-400 hover:text-white text-xl">
                    <i class="fas fa-times"></i>
                </button>
                <h2 class="text-2xl font-bold mb-6 text-blue-400 text-center" id="detailsTitre"></h2>
                <p class="mb-2 text-gray-300"><span class="font-semibold">Utilisateur :</span> <span id="detailsUser"></span></p>
                <p class="mb-2 text-gray-300"><span class="font-semibold">Date :</span> <span id="detailsDate"></span></p>
                <p class="mb-2 text-gray-300"><span class="font-semibold">Statut :</span> <span id="detailsStatut"></span></p>
                <p class="mb-2 text-gray-300"><span class="font-semibold">Nom Paladium Java :</span> <span id="detailsJava"></span></p>
                <p class="mb-2 text-gray-300"><span class="font-semibold">Nom Paladium Bedrock :</span> <span id="detailsBedrock"></span></p>
                <p class="mb-2 text-gray-300"><span class="font-semibold">Nom Discord :</span> <span id="detailsDiscord"></span></p>
                <p class="mb-2 text-gray-300"><span class="font-semibold">Type d'échange :</span> <span id="detailsType"></span></p>
                <div class="mb-4 text-gray-200"><span class="font-semibold">Description :</span><br><span id="detailsDesc"></span></div>
                <form id="deleteForm" method="post" class="mt-4 hidden">
                    <input type="hidden" name="delete_echange" id="deleteEchangeId">
                    <button type="submit" class="w-full bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-6 rounded-xl shadow-lg flex items-center justify-center gap-2 text-lg">
                        <i class="fas fa-trash"></i> Supprimer cette offre
                    </button>
                </form>
            </div>
        </div>
    </main>
    <?php include 'includes/footer.php'; ?>
    <script>
        function showDetails(echange) {
            document.getElementById('detailsTitre').textContent = echange.titre;
            document.getElementById('detailsUser').textContent = echange.username;
            document.getElementById('detailsDate').textContent = new Date(echange.created_at).toLocaleString('fr-FR');
            document.getElementById('detailsStatut').textContent = echange.statut.charAt(0).toUpperCase() + echange.statut.slice(1);
            document.getElementById('detailsJava').textContent = echange.nom_paladium_java;
            document.getElementById('detailsBedrock').textContent = echange.nom_paladium_bedrock;
            document.getElementById('detailsDiscord').textContent = echange.nom_discord;
            document.getElementById('detailsType').textContent = echange.type_echange;
            document.getElementById('detailsDesc').textContent = echange.description;
            // Affiche le bouton supprimer si c'est son offre
            if (echange.user_id == <?= $_SESSION['user_id'] ?>) {
                document.getElementById('deleteForm').classList.remove('hidden');
                document.getElementById('deleteEchangeId').value = echange.id;
            } else {
                document.getElementById('deleteForm').classList.add('hidden');
            }
            document.getElementById('detailsModal').classList.remove('hidden');
        }
    </script>
</body>
</html>
