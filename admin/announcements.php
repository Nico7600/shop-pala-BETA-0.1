<?php
require_once '../config.php';

if(!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['resp_vendeur', 'fondateur'])) {
    header('Location: ../login.php');
    exit;
}

// Création/Modification d'annonce
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_announcement'])) {
    $title = $_POST['title'];
    $content = $_POST['content'];
    $type = $_POST['type'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $show_in_banner = isset($_POST['show_in_banner']) ? 1 : 0;
    
    if(isset($_POST['announcement_id']) && !empty($_POST['announcement_id'])) {
        $pdo->prepare("UPDATE announcements SET title = ?, content = ?, type = ?, is_active = ?, show_in_banner = ? WHERE id = ?")
            ->execute([$title, $content, $type, $is_active, $show_in_banner, $_POST['announcement_id']]);
        
        // Logger l'action
        $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, details, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([
            $_SESSION['user_id'],
            'edit_announcement',
            "Modification de l'annonce: " . $title
        ]);
        
        $success = "Annonce mise à jour !";
    } else {
        $pdo->prepare("INSERT INTO announcements (title, content, type, is_active, show_in_banner, created_by) VALUES (?, ?, ?, ?, ?, ?)")
            ->execute([$title, $content, $type, $is_active, $show_in_banner, $_SESSION['user_id']]);
        
        // Logger l'action
        $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, details, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([
            $_SESSION['user_id'],
            'add_announcement',
            "Création de l'annonce: " . $title
        ]);
        
        $success = "Annonce créée !";
    }
}

// Suppression d'annonce
if(isset($_GET['delete'])) {
    // Récupérer le titre avant suppression pour le log
    $stmt = $pdo->prepare("SELECT title FROM announcements WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    $announcement_title = $stmt->fetchColumn();
    
    $pdo->prepare("DELETE FROM announcements WHERE id = ?")->execute([$_GET['delete']]);
    
    // Logger l'action
    $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, details, created_at) VALUES (?, ?, ?, NOW())");
    $stmt->execute([
        $_SESSION['user_id'],
        'delete_announcement',
        "Suppression de l'annonce: " . $announcement_title
    ]);
    
    $success = "Annonce supprimée !";
}

$announcements = $pdo->query("SELECT a.*, u.username as author 
                              FROM announcements a 
                              LEFT JOIN users u ON a.created_by = u.id 
                              ORDER BY a.created_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Annonces - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="bg-gray-900 text-gray-100">
    <div class="flex flex-col md:flex-row min-h-screen">
        <?php include 'sidebar.php'; ?>

        <main class="flex-1 p-4 sm:p-8 w-full">
            <div class="mb-8 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div>
                    <h2 class="text-2xl sm:text-3xl font-bold mb-2">Gestion des Annonces</h2>
                    <p class="text-gray-400 text-sm sm:text-base">Créez et gérez les annonces du site</p>
                </div>
                <button onclick="openAnnouncementModal()" class="bg-purple-600 hover:bg-purple-700 px-4 sm:px-6 py-2 sm:py-3 rounded-lg font-bold transition w-full sm:w-auto">
                    <i class="fas fa-plus mr-2"></i>Nouvelle Annonce
                </button>
            </div>

            <?php if(isset($success)): ?>
            <div class="bg-green-500/20 border border-green-500 text-green-400 px-4 py-3 rounded-lg mb-6 text-sm sm:text-base">
                <i class="fas fa-check-circle mr-2"></i><?php echo $success; ?>
            </div>
            <?php endif; ?>

            <div class="grid gap-6">
                <?php foreach($announcements as $announcement): ?>
                <div class="bg-gray-800 rounded-xl p-4 sm:p-6 border-l-4 <?php 
                    echo $announcement['type'] == 'info' ? 'border-blue-500' : 
                        ($announcement['type'] == 'success' ? 'border-green-500' : 
                        ($announcement['type'] == 'warning' ? 'border-yellow-500' : 'border-red-500')); 
                ?>">
                    <div class="flex flex-col sm:flex-row justify-between items-start mb-4 gap-2">
                        <div class="flex-1">
                            <div class="flex flex-wrap items-center gap-2 sm:gap-3 mb-2">
                                <h3 class="text-lg sm:text-xl font-bold"><?php echo htmlspecialchars($announcement['title']); ?></h3>
                                <?php if($announcement['is_active']): ?>
                                <span class="bg-green-500/20 text-green-400 px-2 sm:px-3 py-1 rounded-full text-xs font-bold">
                                    <i class="fas fa-check-circle mr-1"></i>Active
                                </span>
                                <?php else: ?>
                                <span class="bg-gray-600/20 text-gray-400 px-2 sm:px-3 py-1 rounded-full text-xs font-bold">
                                    <i class="fas fa-times-circle mr-1"></i>Inactive
                                </span>
                                <?php endif; ?>
                                <?php if($announcement['show_in_banner']): ?>
                                <span class="bg-purple-500/20 text-purple-400 px-2 sm:px-3 py-1 rounded-full text-xs font-bold">
                                    <i class="fas fa-eye mr-1"></i>Bannière
                                </span>
                                <?php else: ?>
                                <span class="bg-gray-600/20 text-gray-400 px-2 sm:px-3 py-1 rounded-full text-xs font-bold">
                                    <i class="fas fa-eye-slash mr-1"></i>Masquée
                                </span>
                                <?php endif; ?>
                            </div>
                            <p class="text-gray-300 mb-3 text-sm sm:text-base"><?php echo nl2br(htmlspecialchars($announcement['content'])); ?></p>
                            <p class="text-xs sm:text-sm text-gray-500">
                                Par <span class="text-purple-400"><?php echo htmlspecialchars($announcement['author']); ?></span> 
                                le <?php echo date('d/m/Y à H:i', strtotime($announcement['created_at'])); ?>
                            </p>
                        </div>
                        <div class="flex gap-2 mt-2 sm:mt-0">
                            <button onclick='editAnnouncement(<?php echo json_encode($announcement); ?>)' 
                                class="bg-blue-600 hover:bg-blue-700 px-3 sm:px-4 py-2 rounded-lg text-xs sm:text-sm transition">
                                <i class="fas fa-edit"></i>
                            </button>
                            <a href="?delete=<?php echo $announcement['id']; ?>" 
                                onclick="return confirm('Supprimer cette annonce ?')"
                                class="bg-red-600 hover:bg-red-700 px-3 sm:px-4 py-2 rounded-lg text-xs sm:text-sm transition">
                                <i class="fas fa-trash"></i>
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </main>
    </div>

    <!-- Modal Annonce -->
    <div id="announcementModal" class="hidden fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center z-50 px-2">
        <div class="bg-gray-800 rounded-xl p-4 sm:p-8 max-w-full sm:max-w-2xl w-full mx-2 sm:mx-4">
            <h3 class="text-xl sm:text-2xl font-bold mb-6">
                <i class="fas fa-bullhorn mr-2 text-purple-500"></i>
                <span id="modalTitle">Nouvelle Annonce</span>
            </h3>
            <form method="POST">
                <input type="hidden" name="save_announcement" value="1">
                <input type="hidden" name="announcement_id" id="announcement_id">
                
                <div class="mb-4">
                    <label class="block text-gray-300 mb-2">Titre</label>
                    <input type="text" name="title" id="title" required 
                        class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-3 text-white focus:border-purple-500 focus:outline-none text-sm sm:text-base">
                </div>
                
                <div class="mb-4">
                    <label class="block text-gray-300 mb-2">Contenu</label>
                    <textarea name="content" id="content" rows="4" required
                        class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-3 text-white focus:border-purple-500 focus:outline-none text-sm sm:text-base"></textarea>
                </div>
                
                <div class="mb-4">
                    <label class="block text-gray-300 mb-2">Type</label>
                    <select name="type" id="type" class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-3 text-white focus:border-purple-500 focus:outline-none text-sm sm:text-base">
                        <option value="info">Info (Bleu)</option>
                        <option value="success">Succès (Vert)</option>
                        <option value="warning">Attention (Jaune)</option>
                        <option value="danger">Danger (Rouge)</option>
                    </select>
                </div>
                
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">
                    <label class="flex items-center gap-3 cursor-pointer bg-gray-700/50 p-4 rounded-lg hover:bg-gray-700 transition">
                        <input type="checkbox" name="is_active" id="is_active" class="w-5 h-5" checked>
                        <div>
                            <span class="text-gray-300 font-semibold block">Annonce active</span>
                            <span class="text-xs text-gray-400">L'annonce est visible</span>
                        </div>
                    </label>
                    
                    <label class="flex items-center gap-3 cursor-pointer bg-gray-700/50 p-4 rounded-lg hover:bg-gray-700 transition">
                        <input type="checkbox" name="show_in_banner" id="show_in_banner" class="w-5 h-5" checked>
                        <div>
                            <span class="text-gray-300 font-semibold block">Afficher en bannière</span>
                            <span class="text-xs text-gray-400">Apparaît sur la page d'accueil</span>
                        </div>
                    </label>
                </div>
                
                <div class="flex flex-col sm:flex-row gap-3">
                    <button type="button" onclick="closeAnnouncementModal()" 
                        class="flex-1 bg-gray-700 hover:bg-gray-600 text-white py-3 rounded-lg transition mb-2 sm:mb-0">
                        Annuler
                    </button>
                    <button type="submit" 
                        class="flex-1 bg-purple-600 hover:bg-purple-700 text-white py-3 rounded-lg transition font-bold">
                        <i class="fas fa-save mr-2"></i>Enregistrer
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openAnnouncementModal() {
            document.getElementById('modalTitle').textContent = 'Nouvelle Annonce';
            document.getElementById('announcement_id').value = '';
            document.getElementById('title').value = '';
            document.getElementById('content').value = '';
            document.getElementById('type').value = 'info';
            document.getElementById('is_active').checked = true;
            document.getElementById('show_in_banner').checked = true;
            document.getElementById('announcementModal').classList.remove('hidden');
        }

        function editAnnouncement(announcement) {
            document.getElementById('modalTitle').textContent = 'Modifier l\'annonce';
            document.getElementById('announcement_id').value = announcement.id;
            document.getElementById('title').value = announcement.title;
            document.getElementById('content').value = announcement.content;
            document.getElementById('type').value = announcement.type;
            document.getElementById('is_active').checked = announcement.is_active == 1;
            document.getElementById('show_in_banner').checked = announcement.show_in_banner == 1;
            document.getElementById('announcementModal').classList.remove('hidden');
        }

        function closeAnnouncementModal() {
            document.getElementById('announcementModal').classList.add('hidden');
        }
    </script>
</body>
</html>
