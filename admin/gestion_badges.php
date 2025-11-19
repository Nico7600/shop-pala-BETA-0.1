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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_type'])) {
    // Attribution à tous les utilisateurs
    if ($_POST['form_type'] === 'assign_badge_all' && isset($_POST['badge_id'])) {
        $badge_id = $_POST['badge_id'];
        // Récupère tous les utilisateurs qui n'ont pas déjà ce badge
        $stmt = $pdo->prepare("
            SELECT id FROM users 
            WHERE id NOT IN (
                SELECT user_id FROM user_badges WHERE badge_id = ?
            )
        ");
        $stmt->execute([$badge_id]);
        $usersToAssign = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if (count($usersToAssign) === 0) {
            $error = "Tous les utilisateurs ont déjà ce badge !";
        } else {
            $sql = "INSERT INTO user_badges (user_id, badge_id, date_attrib, actif) VALUES (?, ?, NOW(), 0)";
            $stmt = $pdo->prepare($sql);
            foreach ($usersToAssign as $uid) {
                $stmt->execute([$uid, $badge_id]);
            }
            $error = "Badge attribué à tous les utilisateurs avec succès !";
            header('Location: gestion_badges.php');
            exit;
        }
    }
}

// Vérifie si la colonne auto_assign existe dans la table badges
$autoAssignExists = false;
try {
    $result = $pdo->query("SHOW COLUMNS FROM badges LIKE 'auto_assign'");
    $autoAssignExists = $result && $result->rowCount() > 0;
} catch (Exception $e) {
    $autoAssignExists = false;
}

// Gestion de la configuration des badges auto-attribués
if ($autoAssignExists && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_type']) && $_POST['form_type'] === 'update_auto_badges') {
    $auto_badge_ids = $_POST['auto_badge_ids'] ?? [];
    $pdo->query("UPDATE badges SET auto_assign = 0");
    foreach ($auto_badge_ids as $id => $val) {
        if ($val == "1") {
            $pdo->prepare("UPDATE badges SET auto_assign = 1 WHERE id = ?")->execute([$id]);
        }
    }
    header('Location: gestion_badges.php');
    exit;
}

// Attribution des badges auto_assign à tous les utilisateurs qui ne les ont pas
if ($autoAssignExists && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_type']) && $_POST['form_type'] === 'give_auto_badges_to_all') {
    // Récupère tous les badges auto_assign
    $auto_badges = $pdo->query("SELECT id FROM badges WHERE auto_assign = 1")->fetchAll(PDO::FETCH_COLUMN);
    if (!empty($auto_badges)) {
        foreach ($auto_badges as $badge_id) {
            // Récupère les utilisateurs qui n'ont pas ce badge
            $stmt = $pdo->prepare("
                SELECT id FROM users
                WHERE id NOT IN (
                    SELECT user_id FROM user_badges WHERE badge_id = ?
                )
            ");
            $stmt->execute([$badge_id]);
            $usersToAssign = $stmt->fetchAll(PDO::FETCH_COLUMN);
            if (!empty($usersToAssign)) {
                $sql = "INSERT INTO user_badges (user_id, badge_id, date_attrib, actif) VALUES (?, ?, NOW(), 0)";
                $insert = $pdo->prepare($sql);
                foreach ($usersToAssign as $uid) {
                    $insert->execute([$uid, $badge_id]);
                }
            }
        }
        $error = "Les badges automatiques ont été attribués à tous les comptes existants qui ne les avaient pas.";
    } else {
        $error = "Aucun badge automatique à attribuer.";
    }
    header('Location: gestion_badges.php');
    exit;
}

$stmt = $pdo->query("SELECT * FROM badges");
$badges = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - CrazySouls Shop</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        .section-title {
            font-size: 1.5rem;
            font-weight: bold;
            color: #fb923c;
            margin-bottom: 1.5rem;
            text-align: center;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            justify-content: center;
        }
        .badge-auto {
            border: 2px solid #fb923c;
            background: #1f2937;
            color: #fb923c;
            font-weight: bold;
            border-radius: 0.75rem;
            padding: 0.5rem 1rem;
            margin-bottom: 0.5rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: box-shadow 0.2s;
        }
        .badge-auto img {
            width: 32px;
            height: 32px;
            border-radius: 0.5rem;
            background: #fff;
        }
        .badge-auto-list {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            justify-content: center;
            margin-bottom: 1rem;
        }
        .search-bar {
            margin-bottom: 2rem;
            text-align: center;
        }
        .search-bar input {
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            border: 1px solid #fb923c;
            background: #1f2937;
            color: #fff;
            font-size: 1rem;
            width: 300px;
            max-width: 90vw;
        }
        .fade-in {
            animation: fadeIn 0.3s;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px);}
            to { opacity: 1; transform: translateY(0);}
        }
        .line-clamp-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        .animate-popup {
            animation: fadeIn 0.4s;
        }
    </style>
</head>
<body class="bg-gray-900 text-gray-100">
    <div class="flex min-h-screen">
        <!-- Sidebar à gauche -->
        <?php include 'sidebar.php'; ?>
        <!-- Contenu principal à droite -->
        <div class="flex-1 flex flex-col">
            <main class="container mx-auto px-2 sm:px-8 pt-8">
                <div class="mb-4 sm:mb-8">
                    <h1 class="section-title fade-in">
                        <i class="fa-solid fa-medal"></i>
                        Gestion des Badges
                    </h1>
                    <p class="text-gray-400 text-xs sm:text-base text-center fade-in">Créez, attribuez et configurez les badges pour vos membres.</p>
                </div>
                <?php if ($error): ?>
                    <div class="bg-green-500/30 border border-green-500 text-green-400 px-2 sm:px-6 py-2 sm:py-4 rounded-lg mb-4 sm:mb-6 text-xs sm:text-base text-center fade-in">
                        <i class="fas fa-check-circle mr-2"></i><?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <!-- Section badges auto -->
                <?php if ($autoAssignExists): ?>
<div class="flex flex-col md:flex-row gap-8 mb-10 fade-in">
    <!-- Badges à la création de compte -->
    <div class="bg-gray-800 p-6 rounded-xl shadow-lg border border-orange-800 flex-1">
        <h2 class="section-title"><i class="fa-solid fa-gear"></i>Badges à la création de compte</h2>
        <form method="post">
            <input type="hidden" name="form_type" value="update_auto_badges">
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-6 mb-4">
                <?php foreach ($badges as $badge): ?>
                <div class="flex flex-col items-center bg-gray-900 rounded-xl p-4 shadow-lg border border-orange-700">
                    <?php if ($badge['image']): ?>
                        <img src="/badges/<?php echo htmlspecialchars($badge['image']); ?>" alt="" class="w-12 h-12 rounded border border-orange-700 mb-2">
                    <?php else: ?>
                        <div class="w-12 h-12 rounded border border-orange-700 mb-2 flex items-center justify-center bg-gray-700 text-gray-400">
                            <i class="fa-solid fa-image"></i>
                        </div>
                    <?php endif; ?>
                    <div class="font-bold text-orange-400 text-center mb-2"><?php echo htmlspecialchars($badge['name']); ?></div>
                    <label class="flex items-center cursor-pointer mt-2">
                        <input type="checkbox" name="auto_badge_ids[<?php echo $badge['id']; ?>]" value="1"
                            <?php if (!empty($badge['auto_assign'])) echo 'checked'; ?> class="sr-only">
                        <span class="relative inline-block w-12 h-6">
                            <span class="absolute left-0 top-0 w-12 h-6 rounded-full transition <?php echo !empty($badge['auto_assign']) ? 'bg-orange-500' : 'bg-gray-700'; ?>"></span>
                            <span class="absolute top-1 left-1 w-4 h-4 rounded-full bg-white shadow transition
                                <?php echo !empty($badge['auto_assign']) ? 'translate-x-6' : ''; ?>"></span>
                        </span>
                        <span class="ml-2 text-xs font-bold <?php echo !empty($badge['auto_assign']) ? 'text-orange-400' : 'text-gray-400'; ?>">
                            <?php echo !empty($badge['auto_assign']) ? 'Oui' : 'Non'; ?>
                        </span>
                    </label>
                </div>
                <?php endforeach; ?>
            </div>
            <button type="submit" class="bg-gradient-to-r from-orange-700 to-orange-600 hover:from-orange-800 hover:to-orange-700 px-6 py-3 rounded-lg font-bold text-base text-white shadow-lg transition-all transform hover:scale-105">
                <i class="fa-solid fa-floppy-disk mr-2"></i>Enregistrer la configuration
            </button>
            <div class="text-xs text-gray-400 mt-2">Activez les badges à attribuer automatiquement à chaque nouvel utilisateur.</div>
        </form>
    </div>

    <!-- Badges à chaque connexion -->
    <div class="bg-gray-800 p-6 rounded-xl shadow-lg border border-orange-800 flex-1">
        <h2 class="section-title"><i class="fa-solid fa-right-to-bracket"></i>Badges à chaque connexion</h2>
        <form method="post">
            <input type="hidden" name="form_type" value="update_login_badges">
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-6 mb-4">
                <?php foreach ($badges as $badge): ?>
                <div class="flex flex-col items-center bg-gray-900 rounded-xl p-4 shadow-lg border border-orange-700">
                    <?php if ($badge['image']): ?>
                        <img src="/badges/<?php echo htmlspecialchars($badge['image']); ?>" alt="" class="w-12 h-12 rounded border border-orange-700 mb-2">
                    <?php else: ?>
                        <div class="w-12 h-12 rounded border border-orange-700 mb-2 flex items-center justify-center bg-gray-700 text-gray-400">
                            <i class="fa-solid fa-image"></i>
                        </div>
                    <?php endif; ?>
                    <div class="font-bold text-orange-400 text-center mb-2"><?php echo htmlspecialchars($badge['name']); ?></div>
                    <label class="flex items-center cursor-pointer mt-2">
                        <input type="checkbox" name="login_badge_ids[<?php echo $badge['id']; ?>]" value="1"
                            <?php if (!empty($badge['login_assign'])) echo 'checked'; ?> class="sr-only">
                        <span class="relative inline-block w-12 h-6">
                            <span class="absolute left-0 top-0 w-12 h-6 rounded-full transition <?php echo !empty($badge['login_assign']) ? 'bg-orange-500' : 'bg-gray-700'; ?>"></span>
                            <span class="absolute top-1 left-1 w-4 h-4 rounded-full bg-white shadow transition
                                <?php echo !empty($badge['login_assign']) ? 'translate-x-6' : ''; ?>"></span>
                        </span>
                        <span class="ml-2 text-xs font-bold <?php echo !empty($badge['login_assign']) ? 'text-orange-400' : 'text-gray-400'; ?>">
                            <?php echo !empty($badge['login_assign']) ? 'Oui' : 'Non'; ?>
                        </span>
                    </label>
                </div>
                <?php endforeach; ?>
            </div>
            <button type="submit" class="bg-gradient-to-r from-orange-700 to-orange-600 hover:from-orange-800 hover:to-orange-700 px-6 py-3 rounded-lg font-bold text-base text-white shadow-lg transition-all transform hover:scale-105">
                <i class="fa-solid fa-floppy-disk mr-2"></i>Enregistrer la configuration
            </button>
            <div class="text-xs text-gray-400 mt-2">Activez les badges à attribuer automatiquement à chaque connexion utilisateur.</div>
        </form>
    </div>
</div>
<?php endif; ?>

                <!-- Section création/attribution -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-10 fade-in">
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
                                <i class="fa-solid fa-image mr-1"></i>Image du badge *
                            </label>
                            <input type="file" name="badge_img" accept="image/*"
                                class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-3 text-gray-100 transition">
                            <span class="text-xs text-gray-400 mt-1 block">L'image sera renommée selon le nom du badge. Formats acceptés : JPG, PNG, GIF.</span>
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
                        <!-- Bouton attribuer à tous -->
                        <button type="button"
                            onclick="document.getElementById('assign-badge-all-popup').classList.remove('hidden');"
                            class="w-full mt-2 bg-gradient-to-r from-orange-600 to-orange-700 hover:from-orange-700 hover:to-orange-800 px-4 py-3 rounded-lg font-bold text-base transition-all transform hover:scale-105 shadow-lg flex items-center justify-center gap-2 border border-orange-800 text-gray-100">
                            <i class="fa-solid fa-users"></i>
                            Attribuer à tous les utilisateurs
                        </button>
                    </form>
                </div>
                <!-- Popup confirmation attribution à tous -->
                <div id="assign-badge-all-popup" class="fixed inset-0 bg-black/60 flex items-center justify-center z-50 hidden">
                    <div class="bg-gray-900 p-8 rounded-2xl border-2 border-orange-700 w-full max-w-md relative shadow-2xl animate-popup">
                        <button onclick="document.getElementById('assign-badge-all-popup').classList.add('hidden');" class="absolute top-4 right-4 text-orange-500 hover:text-orange-300 text-3xl font-bold transition">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                        <form method="post">
                            <input type="hidden" name="form_type" value="assign_badge_all">
                            <div class="mb-4">
                                <label class="block text-sm font-medium mb-2 text-gray-200">
                                    <i class="fa-solid fa-medal mr-1"></i>Badge à attribuer *
                                </label>
                                <select name="badge_id" required class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-3 text-gray-100 focus:border-orange-600 focus:outline-none">
                                    <option value="">Sélectionnez un badge</option>
                                    <?php foreach ($badges as $badge): ?>
                                        <option value="<?php echo $badge['id']; ?>"><?php echo htmlspecialchars($badge['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-6 text-gray-400 text-sm text-center">
                                Cette action attribuera le badge sélectionné à tous les membres qui ne l'ont pas déjà.
                            </div>
                            <button type="submit" class="w-full bg-gradient-to-r from-orange-700 to-orange-600 hover:from-orange-800 hover:to-orange-700 px-6 py-3 rounded-xl font-bold text-base text-white shadow-lg transition-all transform hover:scale-105">
                                <i class="fa-solid fa-users mr-2"></i>Confirmer l'attribution à tous
                            </button>
                        </form>
                    </div>
                </div>
               
                <!-- Section utilisateurs et leurs badges -->
                <h2 class="section-title mt-12"><i class="fa-solid fa-users"></i>Utilisateurs & leurs badges</h2>
                <div class="search-bar fade-in">
                    <input type="text" id="userSearchInput" placeholder="Rechercher un utilisateur..." oninput="filterUsers()">
                </div>
                <ul id="usersGrid" class="fade-in divide-y divide-orange-800 bg-gray-800 rounded-xl shadow-lg mb-10">
                    <?php foreach ($usersList as $uid => $user): ?>
                        <li class="flex flex-col sm:flex-row items-center justify-between px-4 py-4 hover:bg-gray-700 transition user-card" data-username="<?php echo strtolower(htmlspecialchars($user['username'])); ?>">
                            <div class="flex items-center gap-2 flex-wrap w-full">
                                <i class="fa-solid fa-user text-orange-500"></i>
                                <span class="font-bold text-orange-400"><?php echo htmlspecialchars($user['username']); ?></span>
                                <?php if (count($user['badges'])): ?>
                                    <?php foreach ($user['badges'] as $badge): ?>
                                        <span class="flex items-center gap-1 bg-gray-900 px-2 py-1 rounded text-xs border border-orange-700 text-orange-300 ml-2" title="<?php echo htmlspecialchars($badge['desc']); ?>">
                                            <?php if ($badge['image']): ?>
                                                <img src="/badges/<?php echo htmlspecialchars($badge['image']); ?>" alt="" class="w-5 h-5 rounded border border-orange-600">
                                            <?php else: ?>
                                                <i class="fa-solid fa-image text-gray-400"></i>
                                            <?php endif; ?>
                                            <?php echo htmlspecialchars($badge['name']); ?>
                                        </span>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span class="text-gray-400 text-xs ml-2">Aucun badge</span>
                                <?php endif; ?>
                            </div>
                            <button onclick="openRemoveBadgePopup(<?php echo $uid; ?>)" class="ml-0 sm:ml-4 mt-3 sm:mt-0 px-3 py-2 bg-orange-700 hover:bg-orange-800 text-white rounded shadow text-xs font-bold transition flex items-center gap-1"
                                title="Gérer les badges">
                                <i class="fa-solid fa-gear"></i> Actions
                            </button>
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
                                        <div class="text-gray-300 mb-3 font-bold text-center text-lg">Retirer des badges :</div>
                                        <?php if (count($user['badges'])): ?>
                                            <form method="post" class="mb-2 grid grid-cols-2 sm:grid-cols-3 gap-4 justify-center">
                                                <input type="hidden" name="form_type" value="remove_badges">
                                                <input type="hidden" name="user_id" value="<?php echo $uid; ?>">
                                                <?php foreach ($user['badges'] as $badge): ?>
                                                    <label class="cursor-pointer flex flex-col items-center bg-gray-800 rounded-lg p-2 shadow">
                                                        <input type="checkbox" name="remove_badge_ids[]" value="<?php echo htmlspecialchars($badge['id']); ?>" class="accent-orange-600 scale-125 mb-2">
                                                        <?php if ($badge['image']): ?>
                                                            <img src="/badges/<?php echo htmlspecialchars($badge['image']); ?>"
                                                                 alt="Badge <?php echo htmlspecialchars($badge['name']); ?>"
                                                                 class="w-8 h-8 mb-1 shadow object-contain rounded border-2 border-orange-600">
                                                        <?php else: ?>
                                                            <div class="w-8 h-8 mb-1 bg-gray-700 flex items-center justify-center text-gray-400 rounded border-2 border-orange-600">
                                                                <i class="fa-solid fa-image"></i>
                                                            </div>
                                                        <?php endif; ?>
                                                        <div class="text-xs text-orange-400 font-bold text-center"><?php echo htmlspecialchars($badge['name']); ?></div>
                                                        <div class="text-[10px] text-gray-400 text-center mb-1"><?php echo htmlspecialchars($badge['desc']); ?></div>
                                                        <div class="text-[10px] text-gray-500 text-center italic">Le <?php echo date('d/m/Y', strtotime($badge['date_attrib'])); ?></div>
                                                    </label>
                                                <?php endforeach; ?>
                                                <button type="submit" class="col-span-2 sm:col-span-3 mt-4 bg-gradient-to-r from-red-700 to-orange-600 hover:from-red-800 hover:to-orange-700 px-6 py-3 rounded-xl font-bold text-base text-white shadow-lg transition-all transform hover:scale-105">
                                                    <i class="fa-solid fa-trash-can mr-2"></i>Retirer la sélection
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <div class="text-gray-400 text-sm text-center">Aucun badge attribué.</div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <script>
                function filterUsers() {
                    const val = document.getElementById('userSearchInput').value.toLowerCase();
                    document.querySelectorAll('.user-card').forEach(card => {
                        card.style.display = card.dataset.username.includes(val) ? '' : 'none';
                    });
                }
                function openRemoveBadgePopup(id) {
                    document.getElementById('remove-badge-popup-' + id).classList.remove('hidden');
                }
                function closeRemoveBadgePopup(id) {
                    document.getElementById('remove-badge-popup-' + id).classList.add('hidden');
                }
                </script>
            <!-- DÉPLACÉ ICI : Liste des badges existants -->
            <h2 class="section-title"><i class="fa-solid fa-list"></i>Liste des badges existants</h2>
            <div class="overflow-x-auto fade-in mb-10">
                <table class="min-w-full bg-gray-800 rounded-xl shadow-lg border border-orange-800">
                    <thead>
                        <tr>
                            <th class="px-4 py-3 text-left text-orange-400 font-bold">Logo</th>
                            <th class="px-4 py-3 text-left text-orange-400 font-bold">Nom</th>
                            <th class="px-4 py-3 text-left text-orange-400 font-bold">Description</th>
                            <th class="px-4 py-3 text-right text-orange-400 font-bold">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($badges as $badge): ?>
                        <tr class="border-b border-orange-700 hover:bg-gray-700 transition group">
                            <td class="px-4 py-3">
                                <?php if ($badge['image']): ?>
                                    <img src="/badges/<?php echo htmlspecialchars($badge['image']); ?>"
                                         alt="Badge <?php echo htmlspecialchars($badge['name']); ?>"
                                         class="w-12 h-12 object-contain rounded-xl border-2 border-orange-600 bg-transparent">
                                <?php else: ?>
                                    <div class="w-12 h-12 flex items-center justify-center text-gray-400 rounded-xl border-2 border-orange-600 bg-gray-700">
                                        <i class="fa-solid fa-image fa-lg"></i>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 font-bold text-orange-300"><?php echo htmlspecialchars($badge['name']); ?></td>
                            <td class="px-4 py-3 text-gray-300"><?php echo htmlspecialchars($badge['description']); ?></td>
                            <td class="px-4 py-3 text-right">
                                <button onclick="openEditBadge(<?php echo $badge['id']; ?>)"
                                        class="px-3 py-2 bg-orange-700 hover:bg-orange-800 text-white rounded shadow text-xs font-bold transition mr-2"
                                        title="Éditer le badge">
                                    <i class="fa-solid fa-pen-to-square"></i> Éditer
                                </button>
                                <button onclick="openDeleteBadgePopup(<?php echo $badge['id']; ?>)"
                                        class="px-3 py-2 bg-red-700 hover:bg-red-800 text-white rounded shadow text-xs font-bold transition"
                                        title="Supprimer le badge">
                                    <i class="fa-solid fa-trash-can"></i> Supprimer
                                </button>
                                <!-- Popup édition -->
                                <div id="edit-badge-<?php echo $badge['id']; ?>" class="fixed inset-0 bg-black/60 flex items-center justify-center z-50 hidden">
                                    <div class="bg-gray-900 p-6 rounded-xl border border-orange-700 w-full max-w-md relative animate-popup">
                                        <button onclick="closeEditBadge(<?php echo $badge['id']; ?>)" class="absolute top-4 right-4 text-orange-500 hover:text-orange-300 text-3xl font-bold transition" title="Fermer">
                                            <i class="fa-solid fa-xmark"></i>
                                        </button>
                                        <form method="post" enctype="multipart/form-data">
                                            <input type="hidden" name="form_type" value="edit_badge">
                                            <input type="hidden" name="badge_id" value="<?php echo $badge['id']; ?>">
                                            <div class="mb-4">
                                                <label class="block text-sm font-medium mb-2 text-gray-200 text-left">Nom</label>
                                                <input type="text" name="badge_name" value="<?php echo htmlspecialchars($badge['name']); ?>" required class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-gray-100 text-left">
                                            </div>
                                            <div class="mb-4">
                                                <label class="block text-sm font-medium mb-2 text-gray-200 text-left">Description</label>
                                                <input type="text" name="badge_desc" value="<?php echo htmlspecialchars($badge['description']); ?>" required class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-gray-100 text-left">
                                            </div>
                                            <div class="mb-4">
                                                <label class="block text-sm font-medium mb-2 text-gray-200 text-left">Image actuelle</label>
                                                <?php if ($badge['image']): ?>
                                                    <img src="/badges/<?php echo htmlspecialchars($badge['image']); ?>"
                                                         alt="Badge <?php echo htmlspecialchars($badge['name']); ?>"
                                                         class="w-12 h-12 object-contain rounded-xl border-2 border-orange-600 bg-transparent mb-2">
                                                <?php else: ?>
                                                    <div class="w-12 h-12 flex items-center justify-center text-gray-400 rounded-xl border-2 border-orange-600 bg-gray-700 mb-2">
                                                        <i class="fa-solid fa-image fa-lg"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="mb-4">
                                                <label class="block text-sm font-medium mb-2 text-gray-200 text-left">Changer l'image</label>
                                                <input type="file" name="badge_img" accept="image/*" class="w-full bg-gray-700 border border-gray-600 rounded-lg px-4 py-2 text-gray-100 text-left">
                                                <span class="text-xs text-gray-400 mt-1 block text-left">Laisser vide pour ne pas changer.</span>
                                            </div>
                                            <button type="submit" class="w-full bg-orange-700 hover:bg-orange-800 px-4 py-2 rounded-lg font-bold text-base text-white mt-2">
                                                <i class="fa-solid fa-floppy-disk mr-2"></i>Enregistrer
                                            </button>
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
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <!-- Fin tableau badges -->
                <script>
                function filterUsers() {
                    const val = document.getElementById('userSearchInput').value.toLowerCase();
                    document.querySelectorAll('.user-card').forEach(card => {
                        card.style.display = card.dataset.username.includes(val) ? '' : 'none';
                    });
                }
                function openRemoveBadgePopup(id) {
                    document.getElementById('remove-badge-popup-' + id).classList.remove('hidden');
                }
                function closeRemoveBadgePopup(id) {
                    document.getElementById('remove-badge-popup-' + id).classList.add('hidden');
                }
                </script>
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
    function filterUsers() {
        const val = document.getElementById('userSearchInput').value.toLowerCase();
        document.querySelectorAll('.user-card').forEach(card => {
            card.style.display = card.dataset.username.includes(val) ? 'block' : 'none';
        });
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

// Ajout de la logique pour la synchronisation personnalisée
if ($autoAssignExists && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_type']) && $_POST['form_type'] === 'sync_auto_badges_to_all') {
    $sync_badge_ids = isset($_POST['sync_badge_ids']) ? $_POST['sync_badge_ids'] : [];
    if (!empty($sync_badge_ids)) {
        foreach ($sync_badge_ids as $badge_id) {
            $stmt = $pdo->prepare("
                SELECT id FROM users
                WHERE id NOT IN (
                    SELECT user_id FROM user_badges WHERE badge_id = ?
                )
            ");
            $stmt->execute([$badge_id]);
            $usersToAssign = $stmt->fetchAll(PDO::FETCH_COLUMN);
            if (!empty($usersToAssign)) {
                $sql = "INSERT INTO user_badges (user_id, badge_id, date_attrib, actif) VALUES (?, ?, NOW(), 0)";
                $insert = $pdo->prepare($sql);
                foreach ($usersToAssign as $uid) {
                    $insert->execute([$uid, $badge_id]);
                }
            }
        }
        $error = "Les badges sélectionnés ont été attribués à tous les comptes existants qui ne les avaient pas.";
    } else {
        $error = "Aucun badge sélectionné pour la synchronisation.";
    }
    header('Location: gestion_badges.php');
    exit;
}

// Gestion de la configuration des badges auto-attribués à la création
if ($autoAssignExists && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_type']) && $_POST['form_type'] === 'update_auto_badges') {
    $auto_badge_ids = $_POST['auto_badge_ids'] ?? [];
    $pdo->query("UPDATE badges SET auto_assign = 0");
    foreach ($auto_badge_ids as $id => $val) {
        if ($val == "1") {
            $pdo->prepare("UPDATE badges SET auto_assign = 1 WHERE id = ?")->execute([$id]);
        }
    }
    header('Location: gestion_badges.php');
    exit;
}

// Gestion de la configuration des badges auto-attribués à la connexion
if ($autoAssignExists && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_type']) && $_POST['form_type'] === 'update_login_badges') {
    $login_badge_ids = $_POST['login_badge_ids'] ?? [];
    $pdo->query("UPDATE badges SET login_assign = 0");
    foreach ($login_badge_ids as $id => $val) {
        if ($val == "1") {
            $pdo->prepare("UPDATE badges SET login_assign = 1 WHERE id = ?")->execute([$id]);
        }
    }
    header('Location: gestion_badges.php');
    exit;
}
?>
