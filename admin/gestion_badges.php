<?php
require_once '../config.php';
session_start();
// Vérifier si admin ou fondateur
if (!in_array($_SESSION['role'] ?? '', ['admin', 'fondateur'])) {
    header('Location: index.php'); exit;
}

$error = null;

// Création badge
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_type']) && $_POST['form_type'] === 'create_badge') {
    $name = $_POST['badge_name'];
    $desc = $_POST['badge_desc'];
    $img = '';
    // Vérifie unicité du nom
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM badges WHERE name = ?");
    $stmt->execute([$name]);
    if ($stmt->fetchColumn() > 0) {
        $error = "Un badge avec ce nom existe déjà !";
    } else {
        if (!empty($_FILES['badge_img']['name'])) {
            $targetDir = $_SERVER['DOCUMENT_ROOT'] . "/badges/";
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0755, true); // Crée le dossier si absent
            }
            $ext = strtolower(pathinfo($_FILES["badge_img"]["name"], PATHINFO_EXTENSION));
            $img = $name . '.' . $ext; // Nom du badge comme nom de fichier
            move_uploaded_file($_FILES["badge_img"]["tmp_name"], $targetDir . $img);
        }
        $stmt = $pdo->prepare("INSERT INTO badges (name, description, image) VALUES (?, ?, ?)");
        $stmt->execute([$name, $desc, $img]);
        header('Location: gestion_badges.php');
        exit;
    }
}

$users = $pdo->query("SELECT id, username FROM users")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_type']) && $_POST['form_type'] === 'assign_badge' && isset($_POST['user_id'], $_POST['badge_id'])) {
    $user_id = $_POST['user_id'];
    $badge_id = $_POST['badge_id'];
    // Vérifie si le badge est déjà attribué à l'utilisateur
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_badges WHERE user_id = ? AND badge_id = ?");
    $stmt->execute([$user_id, $badge_id]);
    if ($stmt->fetchColumn() > 0) {
        $error = "Ce badge est déjà attribué à cet utilisateur !";
    } else {
        // Ajout colonne actif à 0
        $stmt = $pdo->prepare("INSERT INTO user_badges (user_id, badge_id, date_attrib, actif) VALUES (?, ?, NOW(), 0)");
        $stmt->execute([$user_id, $badge_id]);
        $error = "Badge attribué avec succès !";
        header('Location: gestion_badges.php');
        exit;
    }
}

$stmt = $pdo->query("SELECT * FROM badges");
$badges = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
    <div class="flex min-h-screen">
        <!-- Sidebar à gauche -->
        <?php include 'sidebar.php'; ?>
        <!-- Contenu principal à droite -->
        <div class="flex-1 flex flex-col">
            <main class="container mx-auto px-2 sm:px-8 pt-8">
                <div class="mb-4 sm:mb-8">
                    <h1 class="text-2xl sm:text-4xl font-bold mb-2 text-orange-600 text-center tracking-tight drop-shadow flex items-center justify-center gap-2">
                        <i class="fa-solid fa-medal text-orange-700"></i>
                        Gestion des Badges
                    </h1>
                    <p class="text-gray-400 text-xs sm:text-base text-center">Créez et attribuez des badges aux membres</p>
                </div>
                <?php if ($error): ?>
                    <div class="bg-green-500/20 border border-green-500 text-green-400 px-2 sm:px-6 py-2 sm:py-4 rounded-lg mb-4 sm:mb-6 text-xs sm:text-base text-center">
                        <i class="fas fa-check-circle mr-2"></i><?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-10">
                    <!-- Formulaire création badge -->
                    <form method="post" enctype="multipart/form-data" class="bg-gray-800 p-6 sm:p-8 rounded-xl shadow-lg border border-orange-800">
                        <input type="hidden" name="form_type" value="create_badge">
                        <h2 class="text-lg sm:text-2xl font-bold mb-4 sm:mb-6 flex items-center gap-2 text-orange-600 text-center">
                            <i class="fa-solid fa-plus"></i>
                            Nouveau badge
                        </h2>
                        <div class="mb-6">
                            <label class="block text-sm font-medium mb-2 text-gray-200">
                                <i class="fa-solid fa-tag mr-1"></i>Nom du badge *
                            </label>
                            <input type="text" name="badge_name" required placeholder="Ex : Super Membre"
                                class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-3 focus:border-orange-600 focus:outline-none text-gray-100 transition">
                        </div>
                        <div class="mb-6">
                            <label class="block text-sm font-medium mb-2 text-gray-200">
                                <i class="fa-solid fa-align-left mr-1"></i>Description *
                            </label>
                            <input type="text" name="badge_desc" required placeholder="Décrivez le badge..."
                                class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-3 focus:border-orange-600 focus:outline-none text-gray-100 transition">
                        </div>
                        <div class="mb-6">
                            <label class="block text-sm font-medium mb-2 text-gray-200">
                                <i class="fa-solid fa-image mr-1"></i>Image ou PDF du badge *
                            </label>
                            <input type="file" name="badge_img" accept="image/*,.pdf"
                                class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-3 text-gray-100 transition">
                            <span class="text-xs text-gray-400 mt-1 block">L'image ou le PDF sera renommé selon le nom du badge. Formats acceptés : JPG, PNG, GIF, PDF.</span>
                        </div>
                        <button type="submit"
                            class="w-full mt-4 bg-gradient-to-r from-orange-700 to-orange-600 hover:from-orange-800 hover:to-orange-700 px-4 py-3 rounded-lg font-bold text-base transition-all transform hover:scale-105 shadow-lg flex items-center justify-center gap-2 border border-orange-800 text-gray-100">
                            <i class="fa-solid fa-medal"></i>
                            Créer le badge
                        </button>
                    </form>
                    <!-- Formulaire attribution badge -->
                    <form method="post" class="bg-gray-800 p-6 sm:p-8 rounded-xl shadow-lg border border-orange-800 flex flex-col justify-between">
                        <input type="hidden" name="form_type" value="assign_badge">
                        <h2 class="text-lg sm:text-2xl font-bold mb-4 sm:mb-6 flex items-center gap-2 text-orange-600 text-center">
                            <i class="fa-solid fa-user-plus"></i>
                            Attribuer un badge
                        </h2>
                        <div class="mb-6">
                            <label class="block text-sm font-medium mb-2 text-gray-200">
                                <i class="fa-solid fa-user mr-1"></i>Utilisateur *
                            </label>
                            <select name="user_id" required class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-3 text-gray-100 focus:border-orange-600 focus:outline-none">
                                <option value="">Sélectionnez un utilisateur</option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['username']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-6">
                            <label class="block text-sm font-medium mb-2 text-gray-200">
                                <i class="fa-solid fa-medal mr-1"></i>Badge *
                            </label>
                            <select name="badge_id" required class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-3 text-gray-100 focus:border-orange-600 focus:outline-none">
                                <option value="">Sélectionnez un badge</option>
                                <?php foreach ($badges as $badge): ?>
                                    <option value="<?php echo $badge['id']; ?>"><?php echo htmlspecialchars($badge['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit"
                            class="w-full mt-4 bg-gradient-to-r from-orange-700 to-orange-600 hover:from-orange-800 hover:to-orange-700 px-4 py-3 rounded-lg font-bold text-base transition-all transform hover:scale-105 shadow-lg flex items-center justify-center gap-2 border border-orange-800 text-gray-100">
                            <i class="fa-solid fa-user-check"></i>
                            Attribuer le badge
                        </button>
                    </form>
                </div>
                <h2 class="text-xl font-bold mb-6 text-orange-600 text-center flex items-center gap-2">
                    <i class="fa-solid fa-list"></i>
                    Liste des badges existants
                </h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <?php foreach ($badges as $badge): ?>
                        <div class="bg-gray-800 p-6 rounded-xl shadow flex flex-col items-center border border-orange-800 min-h-[340px]"> <!-- Ajout min-h-[340px] -->
                            <?php
                                $isPdf = strtolower(pathinfo($badge['image'], PATHINFO_EXTENSION)) === 'pdf';
                            ?>
                            <?php if ($badge['image']): ?>
                                <?php if ($isPdf): ?>
                                    <a href="/badges/<?php echo htmlspecialchars($badge['image']); ?>" target="_blank" class="w-20 h-20 mb-3 flex items-center justify-center bg-gray-700 border-2 border-orange-700 shadow">
                                        <i class="fa-solid fa-file-pdf fa-3x text-orange-600"></i>
                                    </a>
                                <?php else: ?>
                                    <img src="/badges/<?php echo htmlspecialchars($badge['image']); ?>"
                                         alt="Badge <?php echo htmlspecialchars($badge['name']); ?>"
                                         class="w-20 h-20 mb-3 shadow bg-transparent object-contain"
                                         style="background: transparent;">
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="w-20 h-20 mb-3 bg-gray-700 flex items-center justify-center text-gray-400">
                                    <i class="fa-solid fa-image fa-2x"></i>
                                </div>
                            <?php endif; ?>
                            <div class="font-bold text-orange-600 text-lg mb-1"><?php echo htmlspecialchars($badge['name']); ?></div>
                            <div class="text-gray-300 text-center mb-2"><?php echo htmlspecialchars($badge['description']); ?></div>
                            <div class="flex gap-2">
                                <button onclick="openEditBadge(<?php echo $badge['id']; ?>)" class="px-4 py-2 bg-orange-700 hover:bg-orange-800 text-white rounded shadow text-sm font-bold transition">
                                    <i class="fa-solid fa-pen-to-square mr-1"></i>Éditer
                                </button>
                                <button onclick="openDeleteBadgePopup(<?php echo $badge['id']; ?>)" class="px-4 py-2 bg-red-700 hover:bg-red-800 text-white rounded shadow text-sm font-bold transition">
                                    <i class="fa-solid fa-trash-can mr-1"></i>Supprimer
                                </button>
                            </div>
                            <!-- Popup édition -->
                            <div id="edit-badge-<?php echo $badge['id']; ?>" class="fixed inset-0 bg-black/60 flex items-center justify-center z-50 hidden">
                                <div class="bg-gray-900 p-6 rounded-xl border border-orange-700 w-full max-w-md relative">
                                    <button onclick="closeEditBadge(<?php echo $badge['id']; ?>)" class="absolute top-2 right-2 text-gray-400 hover:text-orange-600 text-xl">&times;</button>
                                    <form method="post" enctype="multipart/form-data">
                                        <input type="hidden" name="form_type" value="edit_badge">
                                        <input type="hidden" name="badge_id" value="<?php echo $badge['id']; ?>">
                                        <div class="mb-4">
                                            <label class="block text-sm font-medium mb-2 text-gray-200">Nom</label>
                                            <input type="text" name="badge_name" value="<?php echo htmlspecialchars($badge['name']); ?>" required class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-gray-100">
                                        </div>
                                        <div class="mb-4">
                                            <label class="block text-sm font-medium mb-2 text-gray-200">Description</label>
                                            <input type="text" name="badge_desc" value="<?php echo htmlspecialchars($badge['description']); ?>" required class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-gray-100">
                                        </div>
                                        <div class="mb-4">
                                            <label class="block text-sm font-medium mb-2 text-gray-200">Image/PDF</label>
                                            <input type="file" name="badge_img" accept="image/*,.pdf" class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-gray-100">
                                            <span class="text-xs text-gray-400 mt-1 block">Laisser vide pour ne pas changer.</span>
                                        </div>
                                        <button type="submit" class="w-full bg-orange-700 hover:bg-orange-800 px-4 py-2 rounded-lg font-bold text-base text-white">Enregistrer</button>
                                    </form>
                                </div>
                            </div>
                            <!-- Popup suppression badge -->
                            <div id="delete-badge-popup-<?php echo $badge['id']; ?>" class="fixed inset-0 bg-black/60 flex items-center justify-center z-50 hidden transition-opacity duration-200">
                                <div class="bg-gray-900 p-8 rounded-2xl border-2 border-red-700 w-full max-w-md relative shadow-2xl animate-popup">
                                    <button onclick="closeDeleteBadgePopup(<?php echo $badge['id']; ?>)" class="absolute top-4 right-4 text-red-500 hover:text-red-300 text-3xl font-bold transition">
                                        <i class="fa-solid fa-xmark"></i>
                                    </button>
                                    <div class="font-bold text-red-500 text-2xl mb-6 text-center flex items-center justify-center gap-2">
                                        <i class="fa-solid fa-trash-can"></i>
                                        Supprimer le badge
                                    </div>
                                    <div class="text-gray-300 mb-6 text-center">
                                        Êtes-vous sûr de vouloir supprimer le badge <span class="font-bold text-orange-400"><?php echo htmlspecialchars($badge['name']); ?></span> ?<br>
                                        Cette action va le retirer de tous les utilisateurs.
                                    </div>
                                    <form method="post">
                                        <input type="hidden" name="form_type" value="delete_badge">
                                        <input type="hidden" name="badge_id" value="<?php echo $badge['id']; ?>">
                                        <button type="submit" class="w-full bg-gradient-to-r from-red-700 to-orange-600 hover:from-red-800 hover:to-orange-700 px-6 py-3 rounded-xl font-bold text-base text-white shadow-lg transition-all transform hover:scale-105">
                                            <i class="fa-solid fa-trash-can mr-2"></i>Confirmer la suppression
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <!-- Section utilisateurs et leurs badges -->
                <h2 class="text-xl font-bold mt-12 mb-6 text-orange-600 text-center flex items-center gap-2">
                    <i class="fa-solid fa-users"></i>
                    Utilisateurs & leurs badges
                </h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-10">
                    <?php
                    // Récupère les badges attribués avec la date
                    $userBadges = $pdo->query("
                        SELECT u.id, u.username, b.id AS badge_id, b.name AS badge_name, b.image AS badge_image, b.description AS badge_desc, ub.date_attrib
                        FROM users u
                        LEFT JOIN user_badges ub ON u.id = ub.user_id
                        LEFT JOIN badges b ON ub.badge_id = b.id
                        ORDER BY u.username, ub.date_attrib DESC
                    ")->fetchAll(PDO::FETCH_ASSOC);

                    // Regroupe les badges par utilisateur
                    $usersList = [];
                    foreach ($userBadges as $row) {
                        $uid = $row['id'];
                        if (!isset($usersList[$uid])) {
                            $usersList[$uid] = [
                                'username' => $row['username'],
                                'badges' => []
                            ];
                        }
                        if ($row['badge_name']) {
                            $usersList[$uid]['badges'][] = [
                                'id' => $row['badge_id'],
                                'name' => $row['badge_name'],
                                'image' => $row['badge_image'],
                                'desc' => $row['badge_desc'],
                                'date_attrib' => $row['date_attrib']
                            ];
                        }
                    }
                    foreach ($usersList as $uid => $user): ?>
                        <div class="bg-gray-800/90 p-6 rounded-2xl shadow-2xl border-2 border-orange-800 mb-4 user-card hover:shadow-orange-900 transition-all duration-200 animate-card relative" data-username="<?php echo strtolower(htmlspecialchars($user['username'])); ?>">
                            <!-- Bouton gestion flottant -->
                            <button onclick="openRemoveBadgePopup(<?php echo $uid; ?>)" class="absolute top-4 right-4 px-3 py-2 bg-orange-700 hover:bg-orange-800 text-white rounded-full shadow-lg text-base font-bold transition-all transform hover:scale-110 flex items-center gap-1 animate-btn z-10"
                                title="Gérer les badges">
                                <i class="fa-solid fa-gear"></i>
                            </button>
                            <div class="font-bold text-orange-500 text-lg mb-4 flex items-center gap-2">
                                <i class="fa-solid fa-user"></i>
                                <?php echo htmlspecialchars($user['username']); ?>
                                <span class="ml-2 text-xs text-gray-400">(<?php echo count($user['badges']); ?> badge<?php echo count($user['badges']) > 1 ? 's' : ''; ?>)</span>
                            </div>
                            <?php if (count($user['badges'])): ?>
                                <div class="flex flex-row flex-wrap gap-6 overflow-x-auto pb-2 justify-center">
                                    <?php foreach ($user['badges'] as $badge): ?>
                                        <div class="flex flex-col items-center bg-gray-900 rounded-xl p-4 shadow-lg min-w-[150px] min-h-[200px] max-w-[170px] max-h-[210px] border border-orange-700 transition-all duration-200 hover:scale-105 hover:border-orange-500 hover:shadow-orange-700 group">
                                            <?php if ($badge['image']): ?>
                                                <img src="/badges/<?php echo htmlspecialchars($badge['image']); ?>"
                                                     alt="Badge <?php echo htmlspecialchars($badge['name']); ?>"
                                                     class="w-16 h-16 mb-2 shadow bg-transparent object-contain rounded-lg border-2 border-orange-600 group-hover:border-orange-400 transition-all duration-200"
                                                     style="background: transparent;">
                                            <?php else: ?>
                                                <div class="w-16 h-16 mb-2 bg-gray-700 flex items-center justify-center text-gray-400 rounded-lg border-2 border-orange-600 group-hover:border-orange-400 transition-all duration-200">
                                                    <i class="fa-solid fa-image fa-2x"></i>
                                                </div>
                                            <?php endif; ?>
                                            <div class="text-sm text-orange-400 font-bold text-center mb-1 group-hover:text-orange-300 transition-all duration-200"><?php echo htmlspecialchars($badge['name']); ?></div>
                                            <div class="text-xs text-gray-300 text-center mb-2 group-hover:text-gray-200 transition-all duration-200"><?php echo htmlspecialchars($badge['desc']); ?></div>
                                            <div class="text-[11px] text-gray-500 text-center italic mt-auto opacity-80">Attribué le <?php echo date('d/m/Y', strtotime($badge['date_attrib'])); ?></div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-gray-400 text-sm">Aucun badge attribué.</div>
                            <?php endif; ?>
                            <!-- Popup suppression des badges utilisateur -->
                            <div id="remove-badge-popup-<?php echo $uid; ?>" class="fixed inset-0 bg-black/60 flex items-center justify-center z-50 hidden transition-opacity duration-200">
                                <div class="bg-gray-900 p-8 rounded-2xl border-2 border-orange-700 w-full max-w-lg relative shadow-2xl animate-popup">
                                    <button onclick="closeRemoveBadgePopup(<?php echo $uid; ?>)" class="absolute top-4 right-4 text-orange-500 hover:text-orange-300 text-3xl font-bold transition">
                                        <i class="fa-solid fa-xmark"></i>
                                    </button>
                                    <div class="font-bold text-orange-500 text-2xl mb-6 text-center flex items-center justify-center gap-2">
                                        <i class="fa-solid fa-user"></i>
                                        <?php echo htmlspecialchars($user['username']); ?>
                                    </div>
                                    <div class="mb-6">
                                        <div class="text-gray-300 mb-3 font-bold text-center text-lg">Badges attribués :</div>
                                        <?php if (count($user['badges'])): ?>
                                            <form method="post" class="mb-2 flex flex-wrap gap-4 justify-center">
                                                <input type="hidden" name="form_type" value="remove_badges">
                                                <input type="hidden" name="user_id" value="<?php echo $uid; ?>">
                                                <?php foreach ($user['badges'] as $badge): ?>
                                                    <div class="flex flex-col items-center bg-gray-800 rounded-lg p-2 shadow min-w-[120px] min-h-[160px] max-w-[140px] max-h-[160px]">
                                                        <label class="cursor-pointer flex flex-col items-center">
                                                            <input type="checkbox" name="remove_badge_ids[]" value="<?php echo htmlspecialchars($badge['id']); ?>" class="mr-1 accent-orange-600 scale-125 mb-2">
                                                            <?php if ($badge['image']): ?>
                                                                <img src="/badges/<?php echo htmlspecialchars($badge['image']); ?>"
                                                                     alt="Badge <?php echo htmlspecialchars($badge['name']); ?>"
                                                                     class="w-12 h-12 mb-1 shadow bg-transparent object-contain"
                                                                     style="background: transparent;">
                                                            <?php else: ?>
                                                                <div class="w-12 h-12 mb-1 bg-gray-700 flex items-center justify-center text-gray-400">
                                                                    <i class="fa-solid fa-image"></i>
                                                                </div>
                                                            <?php endif; ?>
                                                            <div class="text-xs text-orange-400 font-bold text-center"><?php echo htmlspecialchars($badge['name']); ?></div>
                                                            <div class="text-[10px] text-gray-400 text-center mb-1"><?php echo htmlspecialchars($badge['desc']); ?></div>
                                                            <div class="text-[10px] text-gray-500 text-center italic">Attribué le <?php echo date('d/m/Y', strtotime($badge['date_attrib'])); ?></div>
                                                        </label>
                                                    </div>
                                                <?php endforeach; ?>
                                                <button type="submit" class="w-full mt-4 bg-gradient-to-r from-red-700 to-orange-600 hover:from-red-800 hover:to-orange-700 px-6 py-3 rounded-xl font-bold text-base text-white shadow-lg transition-all transform hover:scale-105">
                                                    <i class="fa-solid fa-trash-can mr-2"></i>Retirer la sélection
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <div class="text-gray-400 text-sm text-center">Aucun badge attribué.</div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </main>
        </div>
    </div>
    <script>
    function openEditBadge(id) {
        document.getElementById('edit-badge-' + id).classList.remove('hidden');
    }
    function closeEditBadge(id) {
        document.getElementById('edit-badge-' + id).classList.add('hidden');
    }
    function openDeleteBadgePopup(id) {
        document.getElementById('delete-badge-popup-' + id).classList.remove('hidden');
    }
    function closeDeleteBadgePopup(id) {
        document.getElementById('delete-badge-popup-' + id).classList.add('hidden');
    }
    function openRemoveBadgePopup(id) {
        document.getElementById('remove-badge-popup-' + id).classList.remove('hidden');
    }
    function closeRemoveBadgePopup(id) {
        document.getElementById('remove-badge-popup-' + id).classList.add('hidden');
    }
    </script>
    <style>
    @keyframes popup {
        from { transform: scale(0.95); opacity: 0; }
        to { transform: scale(1); opacity: 1; }
    }
    .animate-popup {
        animation: popup 0.2s ease;
    }
    </style>
</body>
</html>

<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_type']) && $_POST['form_type'] === 'edit_badge' && isset($_POST['badge_id'])) {
    $badge_id = $_POST['badge_id'];
    $name = $_POST['badge_name'];
    $desc = $_POST['badge_desc'];
    $img = '';
    if (!empty($_FILES['badge_img']['name'])) {
        $targetDir = $_SERVER['DOCUMENT_ROOT'] . "/badges/";
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }
        $ext = strtolower(pathinfo($_FILES["badge_img"]["name"], PATHINFO_EXTENSION));
        $img = $name . '.' . $ext;
        move_uploaded_file($_FILES["badge_img"]["tmp_name"], $targetDir . $img);
        $stmt = $pdo->prepare("UPDATE badges SET name=?, description=?, image=? WHERE id=?");
        $stmt->execute([$name, $desc, $img, $badge_id]);
    } else {
        $stmt = $pdo->prepare("UPDATE badges SET name=?, description=? WHERE id=?");
        $stmt->execute([$name, $desc, $badge_id]);
    }
    header('Location: gestion_badges.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_type']) && $_POST['form_type'] === 'delete_badge' && isset($_POST['badge_id'])) {
    $badge_id = $_POST['badge_id'];
    // Supprime le badge des utilisateurs
    $stmt = $pdo->prepare("DELETE FROM user_badges WHERE badge_id = ?");
    $stmt->execute([$badge_id]);
    // Supprime le badge
    $stmt = $pdo->prepare("DELETE FROM badges WHERE id = ?");
    $stmt->execute([$badge_id]);
    header('Location: gestion_badges.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_type']) && $_POST['form_type'] === 'remove_badges' && isset($_POST['user_id'], $_POST['remove_badge_ids'])) {
    $user_id = $_POST['user_id'];
    $badge_ids = $_POST['remove_badge_ids'];
    $in = str_repeat('?,', count($badge_ids) - 1) . '?';
    $params = array_merge([$user_id], $badge_ids);
    $sql = "DELETE FROM user_badges WHERE user_id = ? AND badge_id IN ($in)";
    $pdo->prepare($sql)->execute($params);
    header('Location: gestion_badges.php');
    exit;
}
?>
