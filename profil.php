<?php

session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'config.php';

// Affiche les erreurs PHP (à retirer en prod)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// --- Ajout gestion du toggle badge ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['badge_id'], $_POST['toggle_actif'])) {
    $badge_id = intval($_POST['badge_id']);
    $new_actif = intval($_POST['toggle_actif']);

    if ($new_actif === 1) {
        // Vérifie le nombre de badges actifs
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM user_badges WHERE user_id = ? AND actif = 1');
        $stmt->execute([$user_id]);
        $active_count = $stmt->fetchColumn();

        if ($active_count >= 3) {
            // Redirige sans activer si déjà 3 badges actifs
            header("Location: profil.php");
            exit;
        }
    }

    $stmt = $pdo->prepare('UPDATE user_badges SET actif = ? WHERE user_id = ? AND badge_id = ?');
    $stmt->execute([$new_actif, $user_id, $badge_id]);
    header("Location: profil.php");
    exit;
}

// --- Récupération des badges de l'utilisateur ---
// Correction du nom de colonne image
$stmt = $pdo->prepare('
    SELECT ub.*, b.name, b.image, ub.badge_id AS user_badge_id
    FROM user_badges ub
    JOIN badges b ON ub.badge_id = b.id
    WHERE ub.user_id = ?
    ORDER BY ub.date_attrib DESC
');
$stmt->execute([$user_id]);
$user_badges = $stmt->fetchAll();

if (!$user) {
    echo "Utilisateur introuvable.";
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Profil</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        .fade-in {
            animation: fadeIn 0.8s;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(30px);}
            to { opacity: 1; transform: translateY(0);}
        }
        .notif-pop {
            animation: notifFadeIn 0.6s;
        }
        @keyframes notifFadeIn {
            from { opacity: 0; transform: translateY(-20px);}
            to { opacity: 1; transform: translateY(0);}
        }
        /* Ajout d'une bordure lumineuse */
        .glow-border {
            box-shadow: 0 0 30px 5px #a78bfa, 0 0 10px 2px #f472b6;
            border: 2px solid #a78bfa;
        }
        /* Animation sur le titre */
        .title-animate {
            animation: titleGlow 2s infinite alternate;
        }
        @keyframes titleGlow {
            from { text-shadow: 0 0 10px #a78bfa, 0 0 20px #f472b6; }
            to { text-shadow: 0 0 30px #a78bfa, 0 0 40px #f472b6; }
        }
        /* Effet survol tableau */
        table tr:hover td {
            background: linear-gradient(90deg, #a78bfa33 0%, #f472b633 100%);
            transition: background 0.3s;
        }
        table th, table td {
            transition: background 0.3s, color 0.3s;
        }
        /* Animation icône bouton */
        .btn-animate i {
            transition: transform 0.2s;
        }
        .btn-animate:hover i {
            transform: rotate(-10deg) scale(1.2);
        }
    </style>
</head>
<body class="bg-gray-900 text-gray-100 min-h-screen flex flex-col">
    <?php include 'includes/header.php'; ?>

    <!-- Notification Pop-up -->
    <?php /* Bloc notification supprimé */ ?>

    <main class="flex-1 flex items-center justify-center">
        <div class="fade-in glow-border w-full max-w-2xl mx-auto bg-gradient-to-br from-gray-800/80 via-gray-900/90 to-gray-900/80 shadow-2xl rounded-2xl p-10 border-2 border-gray-700 backdrop-blur">
            <h1 class="text-3xl font-bold text-center mb-10">Mon Profil</h1>
            <!-- Avatar Minecraft supprimé -->
            <!-- <div class="flex justify-center mb-8">
                <img src="https://minotar.net/avatar/<?= urlencode($user['minecraft_username']) ?>/120.png"
                     alt="Avatar Minecraft"
                     class="rounded-full border-4 border-purple-400 shadow-lg hover:scale-105 transition duration-300 w-28 h-28 bg-gray-800 object-cover">
            </div> -->
            <table class="w-full text-left border border-gray-700 rounded-lg overflow-hidden mb-10 bg-gray-900/80">
                <tbody>
                    <tr class="border-b border-gray-800">
                        <th class="py-4 px-6 font-semibold bg-gray-800/60 text-gray-300 w-1/3">Nom d'utilisateur</th>
                        <td class="py-4 px-6"><?= htmlspecialchars($user['username']) ?></td>
                    </tr>
                    <tr class="border-b border-gray-800">
                        <th class="py-4 px-6 font-semibold bg-gray-800/60 text-gray-300">Email</th>
                        <td class="py-4 px-6"><?= htmlspecialchars($user['email']) ?></td>
                    </tr>
                    <tr class="border-b border-gray-800">
                        <th class="py-4 px-6 font-semibold bg-gray-800/60 text-gray-300">Date d'inscription</th>
                        <td class="py-4 px-6">
                            <?php
                                $date = new DateTime($user['created_at']);
                                echo $date->format('d/m/Y H:i');
                            ?>
                        </td>
                    </tr>
                    <tr class="border-b border-gray-800">
                        <th class="py-4 px-6 font-semibold bg-gray-800/60 text-gray-300">Rôle</th>
                        <td class="py-4 px-6"><?= htmlspecialchars($user['role']) ?></td>
                    </tr>
                    <tr class="border-b border-gray-800">
                        <th class="py-4 px-6 font-semibold bg-gray-800/60 text-gray-300">Banni</th>
                        <td class="py-4 px-6"><?= $user['is_banned'] ? 'Oui' : 'Non' ?></td>
                    </tr>
                    <tr class="border-b border-gray-800">
                        <th class="py-4 px-6 font-semibold bg-gray-800/60 text-gray-300">Pseudo Minecraft</th>
                        <td class="py-4 px-6"><?= htmlspecialchars($user['minecraft_username']) ?></td>
                    </tr>
                    <!-- Suppression des lignes Solde et ID Abonnement -->
                </tbody>
            </table>
            <a href="edit_profil.php"
                class="btn-animate block w-full text-center bg-gradient-to-r from-purple-600 to-gray-700 hover:from-purple-700 hover:to-gray-800 text-white font-bold py-4 px-6 rounded-xl shadow-lg transition duration-300 transform hover:scale-105 flex items-center justify-center gap-2 text-lg">
                <i class="fa-solid fa-user-pen"></i> Modifier mon profil
            </a>
        </div>

        <!-- Conteneur Ma Collection (Badges) harmonisé avec Mon Profil -->
        <div class="fade-in glow-border w-full max-w-2xl mx-auto mt-12 bg-gradient-to-br from-gray-800/80 via-gray-900/90 to-gray-900/80 shadow-2xl rounded-2xl p-10 border-2 border-gray-700 backdrop-blur">
            <h2 class="text-2xl font-bold text-center mb-8 flex items-center justify-center gap-3">
                <i class="fa-solid fa-star text-purple-400 text-2xl"></i>
                Ma Collection
            </h2>
            <?php if (count($user_badges) === 0): ?>
                <div class="text-center text-gray-400">Aucun badge attribué.</div>
            <?php else: ?>
                <table class="w-full text-center border border-gray-700 rounded-lg overflow-hidden bg-gray-900/80">
                    <thead>
                        <tr>
                            <th class="py-4 px-6 font-semibold bg-gray-800/60 text-gray-300 w-1/6 text-center">Badge</th>
                            <th class="py-4 px-6 font-semibold bg-gray-800/60 text-gray-300 text-center">Nom</th>
                            <th class="py-4 px-6 font-semibold bg-gray-800/60 text-gray-300 text-center">Visibilité</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($user_badges as $badge): ?>
                            <tr class="border-b border-gray-800 hover:bg-gradient-to-r hover:from-purple-700/20 hover:to-pink-400/10 transition">
                                <td class="py-4 px-6 flex justify-center items-center">
                                    <?php if (!empty($badge['image'])): ?>
                                        <img src="badges/<?= htmlspecialchars($badge['image']) ?>" alt="<?= htmlspecialchars($badge['name']) ?>" class="w-10 h-10 object-contain mx-auto transition-transform duration-300 hover:scale-110">
                                    <?php else: ?>
                                        <i class="fa-solid fa-medal text-3xl text-purple-300"></i>
                                    <?php endif; ?>
                                </td>
                                <td class="py-4 px-6 font-semibold text-center"><?= htmlspecialchars($badge['name']) ?></td>
                                <td class="py-4 px-6 text-center">
                                    <form method="post" class="inline badge-toggle-form" data-badge-id="<?= $badge['user_badge_id'] ?>">
                                        <input type="hidden" name="badge_id" value="<?= $badge['user_badge_id'] ?>">
                                        <input type="hidden" name="toggle_actif" value="<?= $badge['actif'] ? 0 : 1 ?>">
                                        <button type="submit"
                                            class="btn-animate px-4 py-2 rounded-lg font-bold transition bg-gradient-to-r <?= $badge['actif'] ? 'from-green-500 to-green-700 hover:from-green-600 hover:to-green-800' : 'from-gray-500 to-gray-700 hover:from-gray-600 hover:to-gray-800' ?> text-white flex items-center gap-2 justify-center badge-toggle-btn"
                                            data-actif="<?= $badge['actif'] ? 1 : 0 ?>">
                                            <i class="fa-solid <?= $badge['actif'] ? 'fa-eye' : 'fa-eye-slash' ?>"></i>
                                            <?= $badge['actif'] ? 'Visible' : 'Masqué' ?>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </main>

    <?php include 'includes/footer.php'; ?>
</body>
</html>
<script>
document.querySelectorAll('.badge-toggle-form').forEach(function(form) {
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        const badgeId = form.getAttribute('data-badge-id');
        const btn = form.querySelector('.badge-toggle-btn');
        const actif = btn.getAttribute('data-actif') === "1" ? 0 : 1;

        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> ...';

        fetch('profil.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `badge_id=${badgeId}&toggle_actif=${actif}`
        })
        .then(() => {
            // Met à jour le bouton sans recharger
            btn.setAttribute('data-actif', actif);
            if (actif === 1) {
                btn.classList.remove('from-gray-500', 'to-gray-700', 'hover:from-gray-600', 'hover:to-gray-800');
                btn.classList.add('from-green-500', 'to-green-700', 'hover:from-green-600', 'hover:to-green-800');
                btn.innerHTML = '<i class="fa-solid fa-eye"></i> Visible';
            } else {
                btn.classList.remove('from-green-500', 'to-green-700', 'hover:from-green-600', 'hover:to-green-800');
                btn.classList.add('from-gray-500', 'to-gray-700', 'hover:from-gray-600', 'hover:to-gray-800');
                btn.innerHTML = '<i class="fa-solid fa-eye-slash"></i> Masqué';
            }
            btn.disabled = false;
        })
        .catch(() => {
            btn.innerHTML = '<i class="fa-solid fa-exclamation-triangle"></i> Erreur';
            btn.disabled = false;
        });
    });
});
</script>
