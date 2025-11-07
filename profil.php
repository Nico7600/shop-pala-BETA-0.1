<?php

session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'config.php';

$user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$user_id]);
$user = $stmt->fetch();

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
    <?php if (!empty($_SESSION['notif'])): ?>
        <div class="fixed top-8 left-1/2 -translate-x-1/2 z-50 notif-pop">
            <div class="flex items-center gap-3 px-6 py-4 rounded-xl shadow-2xl bg-gradient-to-r from-gray-700/90 to-gray-900/90 text-white font-semibold border-2 border-gray-600">
                <div class="w-10 h-10 rounded-full bg-gray-800/80 flex items-center justify-center border-2 border-gray-600">
                    <i class="fa-solid fa-bell text-xl text-purple-300"></i>
                </div>
                <span><?= htmlspecialchars($_SESSION['notif']) ?></span>
                <button onclick="this.parentElement.parentElement.style.display='none';" class="ml-4 text-white hover:text-purple-200 text-lg focus:outline-none">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
        </div>
        <?php unset($_SESSION['notif']); ?>
    <?php endif; ?>

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
    </main>

    <?php include 'includes/footer.php'; ?>
</body>
</html>
