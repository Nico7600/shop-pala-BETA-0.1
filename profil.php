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
    <meta name="viewport" content="width=device-width, initial-scale=1">
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
        /* Responsive container */
        @media (max-width: 768px) {
            .responsive-container {
                padding: 1.5rem !important;
            }
            .responsive-table th, .responsive-table td {
                padding: 0.5rem !important;
                font-size: 0.95rem;
            }
            .responsive-table img {
                width: 2.2rem !important;
                height: 2.2rem !important;
            }
        }
        @media (max-width: 480px) {
            .responsive-container {
                padding: 0.7rem !important;
            }
            .responsive-table th, .responsive-table td {
                padding: 0.3rem !important;
                font-size: 0.85rem;
            }
            .responsive-table img {
                width: 1.7rem !important;
                height: 1.7rem !important;
            }
            h1, h2 {
                font-size: 1.3rem !important;
            }
        }
        /* Badge button improved style */
        .badge-toggle-btn {
            position: relative;
            overflow: hidden;
            outline: none;
            border: none;
            box-shadow: 0 2px 8px #a78bfa44;
        }
        .badge-toggle-btn .badge-btn-bg {
            position: absolute;
            left: 0; top: 0; width: 100%; height: 100%;
            z-index: 0;
            opacity: 0.2;
            pointer-events: none;
            background: linear-gradient(90deg, #a78bfa 0%, #f472b6 100%);
            transition: opacity 0.3s;
        }
        .badge-toggle-btn[data-actif="1"] {
            border: 2px solid #22c55e;
            box-shadow: 0 0 10px #22c55e55;
        }
        .badge-toggle-btn[data-actif="0"] {
            border: 2px solid #64748b;
            box-shadow: 0 0 10px #64748b55;
        }
        .badge-toggle-btn:active {
            transform: scale(0.97);
        }
        .badge-toggle-btn:focus {
            outline: 2px solid #a78bfa;
        }
        /* Spinner animation */
        .badge-spinner {
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            100% { transform: rotate(360deg);}
        }
        /* Slider switch style amélioré */
        .switch {
            position: relative;
            display: inline-block;
            width: 54px;
            height: 28px;
            vertical-align: middle;
        }
        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0; left: 0; right: 0; bottom: 0;
            background: linear-gradient(90deg, #64748b 0%, #a78bfa 100%);
            transition: .4s;
            border-radius: 34px;
            box-shadow: 0 2px 8px #a78bfa44;
            border: 2px solid #64748b;
        }
        .slider:before {
            position: absolute;
            content: "";
            height: 22px;
            width: 22px;
            left: 3px;
            bottom: 3px;
            background-color: #fff;
            transition: .4s;
            border-radius: 50%;
            box-shadow: 0 1px 4px #a78bfa33;
            border: 2px solid #a78bfa;
        }
        input:checked + .slider {
            background: linear-gradient(90deg, #22c55e 0%, #16a34a 100%);
            border: 2px solid #22c55e;
            box-shadow: 0 0 10px #22c55e88;
        }
        input:checked + .slider:before {
            transform: translateX(26px);
            background-color: #22c55e;
            border: 2px solid #16a34a;
            box-shadow: 0 0 8px #22c55e88;
        }
        .switch-label {
            margin-left: 10px;
            font-weight: 600;
            font-size: 1rem;
            color: #fff;
            user-select: none;
            text-shadow: 0 1px 4px #0005;
        }
        @media (max-width: 480px) {
            .switch-label {
                font-size: 0.85rem;
            }
            .switch {
                width: 44px;
                height: 22px;
            }
            .slider:before {
                height: 16px;
                width: 16px;
                left: 3px;
                bottom: 3px;
            }
        }
        /* Notification badge style */
        .notif-badge {
            position: fixed;
            top: 18px;
            left: 50%;
            transform: translateX(-50%);
            background: linear-gradient(90deg, #a78bfa 0%, #f472b6 100%);
            color: #fff;
            padding: 0.7em 1.5em;
            border-radius: 1em;
            font-weight: 600;
            font-size: 1rem;
            box-shadow: 0 2px 16px #a78bfa55;
            z-index: 9999;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s;
        }
        .notif-badge.show {
            opacity: 1;
            pointer-events: auto;
        }
        @media (max-width: 480px) {
            .notif-badge {
                font-size: 0.85rem;
                padding: 0.5em 1em;
            }
        }
    </style>
</head>
<body class="bg-gray-900 text-gray-100 min-h-screen flex flex-col">
    <?php include 'includes/header.php'; ?>
    <div id="notif-badge" class="notif-badge"></div>

    <!-- Padding entre header et Mon Profil avec Tailwind -->
    <div class="w-full py-8"></div>

    <!-- Notification Pop-up -->
    <?php /* Bloc notification supprimé */ ?>

    <main class="flex-1 flex flex-col items-center justify-center px-2">
        <div class="fade-in glow-border responsive-container w-full max-w-2xl mx-auto bg-gradient-to-br from-gray-800/80 via-gray-900/90 to-gray-900/80 shadow-2xl rounded-2xl p-10 border-2 border-gray-700 backdrop-blur">
            <h1 class="text-3xl font-bold text-center mb-10">Mon Profil</h1>
            <!-- Avatar Minecraft supprimé -->
            <!-- <div class="flex justify-center mb-8">
                <img src="https://minotar.net/avatar/<?= urlencode($user['minecraft_username']) ?>/120.png"
                     alt="Avatar Minecraft"
                     class="rounded-full border-4 border-purple-400 shadow-lg hover:scale-105 transition duration-300 w-28 h-28 bg-gray-800 object-cover">
            </div> -->
            <table class="responsive-table w-full text-left border border-gray-700 rounded-lg overflow-hidden mb-10 bg-gray-900/80">
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
        <div class="fade-in glow-border responsive-container w-full max-w-2xl mx-auto mt-12 bg-gradient-to-br from-gray-800/80 via-gray-900/90 to-gray-900/80 shadow-2xl rounded-2xl p-10 border-2 border-gray-700 backdrop-blur">
            <h2 class="text-2xl font-bold text-center mb-8 flex items-center justify-center gap-3">
                <i class="fa-solid fa-star text-purple-400 text-2xl"></i>
                Ma Collection
            </h2>
            <?php if (count($user_badges) === 0): ?>
                <div class="text-center text-gray-400">Aucun badge attribué.</div>
            <?php else: ?>
                <table class="responsive-table w-full text-center border border-gray-700 rounded-lg overflow-hidden bg-gray-900/80">
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
                                        <!-- Slider Tailwind -->
                                        <label class="relative inline-flex items-center cursor-pointer">
                                            <input type="checkbox"
                                                class="badge-toggle-slider sr-only peer"
                                                <?= $badge['actif'] ? 'checked' : '' ?>
                                                data-badge-id="<?= $badge['user_badge_id'] ?>">
                                            <div class="w-12 h-7 bg-gray-500 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-purple-400 rounded-full peer peer-checked:bg-gradient-to-r peer-checked:from-green-500 peer-checked:to-green-700 transition-colors duration-300"></div>
                                            <div class="absolute left-0.5 top-0.5 w-6 h-6 bg-white border-2 border-purple-400 rounded-full transition-transform duration-300 peer-checked:translate-x-5 peer-checked:bg-green-500 peer-checked:border-green-600 shadow"></div>
                                        </label>
                                        <span class="switch-label ml-3 font-semibold text-white text-shadow"><?= $badge['actif'] ? 'Affiché' : 'Masqué' ?></span>
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
function showNotifBadge(msg) {
    const notif = document.getElementById('notif-badge');
    notif.textContent = msg;
    notif.classList.add('show');
    setTimeout(() => notif.classList.remove('show'), 2500);
}

document.querySelectorAll('.badge-toggle-slider').forEach(function(slider) {
    slider.addEventListener('change', function(e) {
        const form = slider.closest('.badge-toggle-form');
        const badgeId = slider.getAttribute('data-badge-id');
        const actif = slider.checked ? 1 : 0;
        const label = form.querySelector('.switch-label');

        // Vérifie le nombre de badges actifs
        const sliders = document.querySelectorAll('.badge-toggle-slider');
        let activeCount = 0;
        sliders.forEach(s => { if (s.checked) activeCount++; });

        // Si on veut activer et déjà 3 actifs, refuse
        if (actif === 1 && activeCount > 3) {
            slider.checked = false;
            label.textContent = 'Masqué';
            showNotifBadge("Vous ne pouvez afficher que 3 badges maximum.");
            return;
        }

        slider.disabled = true;
        label.textContent = '...';

        fetch('profil.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `badge_id=${badgeId}&toggle_actif=${actif}`
        })
        .then(() => {
            label.textContent = actif === 1 ? 'Affiché' : 'Masqué';
            slider.disabled = false;
        })
        .catch(() => {
            label.textContent = 'Erreur';
            slider.disabled = false;
        });
    });
});
</script>
