<?php
session_start();
require_once '../config.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['fondateur', 'resp_vendeur', 'admin'])) {
    header('Location: ../index.php');
    exit;
}

// Vérifier la connexion PDO
if (!isset($pdo)) {
    die("Erreur : La connexion à la base de données n'est pas établie. Vérifiez le fichier config.php");
}

// Vérifier que les tables existent
try {
    $pdo->query("SELECT 1 FROM patch_notes LIMIT 1");
} catch (PDOException $e) {
    die("Erreur : La table 'patch_notes' n'existe pas. Veuillez exécuter le script SQL suivant :<br><br>
    <pre style='background:#1f2937;padding:20px;border-radius:8px;color:#10b981;overflow-x:auto;'>
CREATE TABLE IF NOT EXISTS patch_notes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    version VARCHAR(20) NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    changes TEXT NOT NULL,
    release_date DATE NOT NULL,
    upvotes INT NOT NULL DEFAULT 0,
    downvotes INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_release_date (release_date DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    </pre><br>Détails: " . $e->getMessage());
}

function log_patchnote_action($user_id, $action, $details = '') {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, details, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->execute([$user_id, $action, $details]);
}

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        try {
            if ($_POST['action'] === 'add') {
                $stmt = $pdo->prepare("INSERT INTO patch_notes (version, title, description, changes, release_date, upvotes, downvotes) VALUES (?, ?, ?, ?, ?, 0, 0)");
                $stmt->execute([
                    $_POST['version'],
                    $_POST['title'],
                    $_POST['description'],
                    $_POST['changes'],
                    $_POST['release_date']
                ]);
                $success = "Patch note ajoutée avec succès !";
                log_patchnote_action($_SESSION['user_id'], 'ADD_PATCHNOTE', 'version=' . $_POST['version']);
            } elseif ($_POST['action'] === 'delete' && isset($_POST['id'])) {
                $stmt = $pdo->prepare("DELETE FROM patch_notes WHERE id = ?");
                $stmt->execute([$_POST['id']]);
                $success = "Patch note supprimée avec succès !";
                log_patchnote_action($_SESSION['user_id'], 'DELETE_PATCHNOTE', 'id=' . $_POST['id']);
            } elseif ($_POST['action'] === 'edit' && isset($_POST['id'])) {
                $stmt = $pdo->prepare("UPDATE patch_notes SET version = ?, title = ?, description = ?, changes = ?, release_date = ? WHERE id = ?");
                $stmt->execute([
                    $_POST['version'],
                    $_POST['title'],
                    $_POST['description'],
                    $_POST['changes'],
                    $_POST['release_date'],
                    $_POST['id']
                ]);
                $success = "Patch note modifiée avec succès !";
                log_patchnote_action($_SESSION['user_id'], 'EDIT_PATCHNOTE', 'id=' . $_POST['id']);
            } elseif ($_POST['action'] === 'reset_votes' && isset($_POST['id'])) {
                $stmt = $pdo->prepare("UPDATE patch_notes SET upvotes = 0, downvotes = 0 WHERE id = ?");
                $stmt->execute([$_POST['id']]);
                $success = "Votes réinitialisés avec succès !";
                log_patchnote_action($_SESSION['user_id'], 'RESET_VOTES_PATCHNOTE', 'id=' . $_POST['id']);
            }
        } catch (PDOException $e) {
            $error = "Erreur de base de données : " . $e->getMessage();
        }
    }
}

// Récupérer toutes les patch notes
try {
    $stmt = $pdo->query("SELECT * FROM patch_notes ORDER BY release_date DESC, created_at DESC");
    $patch_notes = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Erreur lors de la récupération des patch notes : " . $e->getMessage();
    $patch_notes = [];
}

// Récupérer une patch note pour édition
$edit_patch = null;
if (isset($_GET['edit'])) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM patch_notes WHERE id = ?");
        $stmt->execute([$_GET['edit']]);
        $edit_patch = $stmt->fetch();
    } catch (PDOException $e) {
        $error = "Erreur lors de la récupération de la patch note : " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Patch Notes - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .modal-bg { background: rgba(31,41,55,0.85); }
        .modal { z-index: 50; }
    </style>
</head>
<body class="bg-gray-900 text-gray-100">
    <div class="flex flex-col md:flex-row min-h-screen">
        <?php include 'sidebar.php'; ?>

    <main class="container mx-auto px-2 sm:px-4 py-4 sm:py-8">
        <div class="mb-4 sm:mb-8">
            <h1 class="text-2xl sm:text-4xl font-bold mb-2 bg-gradient-to-r from-purple-400 to-pink-400 bg-clip-text text-transparent">
                <i class="fas fa-clipboard-list mr-2 sm:mr-3"></i>Gestion des Patch Notes
            </h1>
            <p class="text-gray-400 text-xs sm:text-base">Ajoutez, modifiez ou supprimez des patch notes</p>
        </div>

        <?php if (isset($success)): ?>
        <div class="bg-green-900/20 border border-green-500 rounded-lg p-2 sm:p-4 mb-4 sm:mb-6 text-xs sm:text-base">
            <i class="fas fa-check-circle text-green-400 mr-2"></i>
            <?php echo htmlspecialchars($success); ?>
        </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
        <div class="bg-red-900/20 border border-red-500 rounded-lg p-2 sm:p-4 mb-4 sm:mb-6 text-xs sm:text-base">
            <i class="fas fa-exclamation-circle text-red-400 mr-2"></i>
            <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-8">
            <!-- Formulaire d'ajout -->
            <?php if (!$edit_patch): ?>
            <div class="bg-gray-800 rounded-xl border border-gray-700 p-2 sm:p-6">
                <h2 class="text-lg sm:text-2xl font-bold mb-4 sm:mb-6 flex items-center gap-2">
                    <i class="fas fa-plus-circle text-purple-400"></i>
                    Ajouter une Patch Note
                </h2>
                <form method="POST" class="space-y-2 sm:space-y-4 text-xs sm:text-base">
                    <input type="hidden" name="action" value="add">
                    <div>
                        <label class="block text-gray-300 mb-1 sm:mb-2 font-semibold">Version *</label>
                        <input type="text" name="version" required
                               value=""
                               placeholder="Ex: 1.2.0"
                               class="w-full bg-gray-900 border border-gray-700 rounded-lg px-2 sm:px-4 py-2 sm:py-3 focus:border-purple-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-gray-300 mb-1 sm:mb-2 font-semibold">Titre *</label>
                        <input type="text" name="title" required
                               value=""
                               placeholder="Ex: Mise à jour majeure"
                               class="w-full bg-gray-900 border border-gray-700 rounded-lg px-2 sm:px-4 py-2 sm:py-3 focus:border-purple-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-gray-300 mb-1 sm:mb-2 font-semibold">Description *</label>
                        <textarea name="description" required rows="3"
                                  placeholder="Description de la mise à jour"
                                  class="w-full bg-gray-900 border border-gray-700 rounded-lg px-2 sm:px-4 py-2 sm:py-3 focus:border-purple-500 focus:outline-none resize-none"></textarea>
                    </div>
                    <div>
                        <label class="block text-gray-300 mb-1 sm:mb-2 font-semibold">Changements * <span class="text-xs sm:text-sm text-gray-500">(un par ligne, précédé de -)</span></label>
                        <textarea name="changes" required rows="6"
                                  placeholder="- Nouveau système de notifications&#10;- Interface améliorée&#10;- Correction de bugs"
                                  class="w-full bg-gray-900 border border-gray-700 rounded-lg px-2 sm:px-4 py-2 sm:py-3 focus:border-purple-500 focus:outline-none resize-none font-mono text-xs sm:text-sm"></textarea>
                    </div>
                    <div>
                        <label class="block text-gray-300 mb-1 sm:mb-2 font-semibold">Date de sortie *</label>
                        <input type="date" name="release_date" required
                               value="<?php echo date('Y-m-d'); ?>"
                               class="w-full bg-gray-900 border border-gray-700 rounded-lg px-2 sm:px-4 py-2 sm:py-3 focus:border-purple-500 focus:outline-none">
                    </div>
                    <div class="flex flex-col sm:flex-row gap-2 sm:gap-3">
                        <button type="submit" class="flex-1 bg-gradient-to-r from-purple-600 to-pink-600 hover:from-purple-700 hover:to-pink-700 px-4 sm:px-6 py-2 sm:py-3 rounded-lg font-bold transition-all text-xs sm:text-base">
                            <i class="fas fa-plus mr-2"></i>
                            Ajouter
                        </button>
                    </div>
                </form>
            </div>
            <?php endif; ?>
            <!-- Liste -->
            <div class="space-y-2 sm:space-y-4">
                <h2 class="text-lg sm:text-2xl font-bold flex items-center gap-2">
                    <i class="fas fa-list text-purple-400"></i>
                    Patch Notes existantes (<?php echo count($patch_notes); ?>)
                </h2>

                <?php foreach ($patch_notes as $patch): ?>
                <div class="bg-gray-800 rounded-xl border border-gray-700 p-2 sm:p-4 hover:border-purple-500 transition-all">
                    <div class="flex flex-col sm:flex-row items-start justify-between gap-2 sm:gap-4 mb-2 sm:mb-3">
                        <div class="flex-1">
                            <div class="flex flex-wrap items-center gap-2 mb-1">
                                <span class="px-2 sm:px-3 py-1 bg-gradient-to-r from-purple-600 to-pink-600 rounded-full text-xs font-bold">
                                    v<?php echo htmlspecialchars($patch['version']); ?>
                                </span>
                                <span class="text-gray-400 text-xs sm:text-sm">
                                    <i class="far fa-calendar-alt mr-1"></i>
                                    <?php echo date('d/m/Y', strtotime($patch['release_date'])); ?>
                                </span>
                            </div>
                            <h3 class="text-base sm:text-lg font-bold text-white">
                                <?php echo htmlspecialchars($patch['title']); ?>
                            </h3>
                            <div class="flex items-center gap-2 sm:gap-3 mt-1 sm:mt-2">
                                <span class="text-xs sm:text-sm text-green-400">
                                    <i class="fas fa-thumbs-up mr-1"></i>
                                    <?php echo $patch['upvotes']; ?>
                                </span>
                                <span class="text-xs sm:text-sm text-red-400">
                                    <i class="fas fa-thumbs-down mr-1"></i>
                                    <?php echo $patch['downvotes']; ?>
                                </span>
                            </div>
                        </div>
                        <div class="flex gap-1 sm:gap-2 mt-2 sm:mt-0">
                            <button type="button"
                                class="bg-blue-600 hover:bg-blue-700 px-2 sm:px-3 py-1 sm:py-2 rounded-lg transition-all text-xs sm:text-sm"
                                onclick="openEditModal(<?php echo htmlspecialchars(json_encode($patch)); ?>)">
                                <i class="fas fa-edit"></i>
                            </button>
                            <form method="POST" class="inline" onsubmit="return confirm('Supprimer cette patch note ?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?php echo $patch['id']; ?>">
                                <button type="submit" class="bg-red-600 hover:bg-red-700 px-2 sm:px-3 py-1 sm:py-2 rounded-lg transition-all text-xs sm:text-sm">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                    <p class="text-gray-400 text-xs sm:text-sm line-clamp-2">
                        <?php echo htmlspecialchars($patch['description']); ?>
                    </p>
                </div>
                <?php endforeach; ?>

                <?php if (empty($patch_notes)): ?>
                <div class="text-center py-8 sm:py-12 bg-gray-800 rounded-xl border border-gray-700">
                    <i class="fas fa-inbox text-3xl sm:text-4xl text-gray-600 mb-2 sm:mb-3"></i>
                    <p class="text-gray-400 text-xs sm:text-base">Aucune patch note pour le moment</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Popup modale édition -->
        <div id="editModal" class="fixed inset-0 flex items-center justify-center modal-bg hidden p-2 sm:p-0">
            <div class="modal bg-gray-900 border border-purple-700 rounded-xl p-4 sm:p-8 w-full max-w-lg relative">
                <button onclick="closeEditModal()" class="absolute top-2 sm:top-3 right-2 sm:right-3 text-gray-400 hover:text-white text-lg sm:text-xl">
                    <i class="fas fa-times"></i>
                </button>
                <h2 class="text-lg sm:text-2xl font-bold mb-4 sm:mb-6 flex items-center gap-2">
                    <i class="fas fa-edit text-purple-400"></i>
                    Modifier une Patch Note
                </h2>
                <form method="POST" class="space-y-2 sm:space-y-4 text-xs sm:text-base" id="editPatchForm">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" id="edit_id">
                    <div>
                        <label class="block text-gray-300 mb-1 sm:mb-2 font-semibold">Version *</label>
                        <input type="text" name="version" id="edit_version" required
                            class="w-full bg-gray-900 border border-gray-700 rounded-lg px-2 sm:px-4 py-2 sm:py-3 focus:border-purple-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-gray-300 mb-1 sm:mb-2 font-semibold">Titre *</label>
                        <input type="text" name="title" id="edit_title" required
                            class="w-full bg-gray-900 border border-gray-700 rounded-lg px-2 sm:px-4 py-2 sm:py-3 focus:border-purple-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-gray-300 mb-1 sm:mb-2 font-semibold">Description *</label>
                        <textarea name="description" id="edit_description" required rows="3"
                            class="w-full bg-gray-900 border border-gray-700 rounded-lg px-2 sm:px-4 py-2 sm:py-3 focus:border-purple-500 focus:outline-none resize-none"></textarea>
                    </div>
                    <div>
                        <label class="block text-gray-300 mb-1 sm:mb-2 font-semibold">Changements *</label>
                        <textarea name="changes" id="edit_changes" required rows="6"
                            class="w-full bg-gray-900 border border-gray-700 rounded-lg px-2 sm:px-4 py-2 sm:py-3 focus:border-purple-500 focus:outline-none resize-none font-mono text-xs sm:text-sm"></textarea>
                    </div>
                    <div>
                        <label class="block text-gray-300 mb-1 sm:mb-2 font-semibold">Date de sortie *</label>
                        <input type="date" name="release_date" id="edit_release_date" required
                            class="w-full bg-gray-900 border border-gray-700 rounded-lg px-2 sm:px-4 py-2 sm:py-3 focus:border-purple-500 focus:outline-none">
                    </div>
                    <div class="flex flex-col sm:flex-row gap-2 sm:gap-3">
                        <button type="submit" class="flex-1 bg-gradient-to-r from-purple-600 to-pink-600 hover:from-purple-700 hover:to-pink-700 px-4 sm:px-6 py-2 sm:py-3 rounded-lg font-bold transition-all text-xs sm:text-base">
                            <i class="fas fa-save mr-2"></i>Mettre à jour
                        </button>
                        <button type="button" onclick="closeEditModal()" class="bg-gray-700 hover:bg-gray-600 px-4 sm:px-6 py-2 sm:py-3 rounded-lg font-bold transition-all text-xs sm:text-base">
                            <i class="fas fa-times mr-2"></i>Annuler
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <script>
        function openEditModal(patch) {
            document.getElementById('edit_id').value = patch.id;
            document.getElementById('edit_version').value = patch.version;
            document.getElementById('edit_title').value = patch.title;
            document.getElementById('edit_description').value = patch.description;
            document.getElementById('edit_changes').value = patch.changes;
            document.getElementById('edit_release_date').value = patch.release_date;
            document.getElementById('editModal').classList.remove('hidden');
        }
        function closeEditModal() {
            document.getElementById('editModal').classList.add('hidden');
        }
        </script>
    </main>
</body>
</html>
