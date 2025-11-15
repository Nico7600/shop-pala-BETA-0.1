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

$notif = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $minecraft_username = trim($_POST['minecraft_username']);

    if (empty($username)) {
        $notif = "Le nom d'utilisateur est requis.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $notif = "Email invalide.";
    } else {
        $update = $pdo->prepare('UPDATE users SET username = ?, email = ?, minecraft_username = ? WHERE id = ?');
        if ($update->execute([$username, $email, $minecraft_username, $user_id])) {
            $_SESSION['notif'] = "Profil mis à jour avec succès.";
            header('Location: profil.php');
            exit;
        } else {
            $notif = "Erreur lors de la mise à jour.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Modifier mon profil</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        .fade-in { animation: fadeIn 0.8s; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(30px);} to { opacity: 1; transform: translateY(0);} }
        .notif-pop { animation: notifFadeIn 0.6s; }
        @keyframes notifFadeIn { from { opacity: 0; transform: translateY(-20px);} to { opacity: 1; transform: translateY(0);}
        }
    </style>
</head>
<body class="bg-gray-900 text-gray-100 min-h-screen flex flex-col">
    <?php include 'includes/header.php'; ?>

    <?php if ($notif): ?>
        <div class="fixed top-8 left-1/2 -translate-x-1/2 z-50 notif-pop">
            <div class="flex items-center gap-3 px-6 py-4 rounded-xl shadow-2xl bg-gradient-to-r from-red-700/90 to-gray-900/90 text-white font-semibold border-2 border-red-600">
                <div class="w-10 h-10 rounded-full bg-red-800/80 flex items-center justify-center border-2 border-red-600">
                    <i class="fa-solid fa-exclamation-circle text-xl text-red-300"></i>
                </div>
                <span><?= htmlspecialchars($notif) ?></span>
                <button onclick="this.parentElement.parentElement.style.display='none';" class="ml-4 text-white hover:text-red-200 text-lg focus:outline-none">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
        </div>
    <?php endif; ?>

    <main class="flex-1 flex items-center justify-center">
        <form method="post" class="fade-in w-full max-w-lg mx-auto bg-gradient-to-br from-gray-800/80 via-gray-900/90 to-gray-900/80 shadow-2xl rounded-2xl p-10 border-2 border-gray-700 backdrop-blur">
            <h1 class="text-4xl font-extrabold mb-8 bg-gradient-to-r from-purple-400 via-pink-400 to-gray-400 bg-clip-text text-transparent text-center drop-shadow-lg tracking-tight">
                Modifier mon profil
            </h1>
            <div class="mb-6">
                <label for="username" class="block font-semibold mb-2 text-gray-300">
                    <i class="fa-solid fa-user mr-2"></i>Nom d'utilisateur
                </label>
                <input type="text" id="username" name="username" required value="<?= htmlspecialchars($user['username']) ?>"
                    class="w-full px-4 py-3 rounded-lg bg-gray-800 text-gray-100 border border-gray-700 focus:outline-none focus:border-purple-500 transition">
            </div>
            <div class="mb-6">
                <label for="email" class="block font-semibold mb-2 text-gray-300">
                    <i class="fa-solid fa-envelope mr-2"></i>Email
                </label>
                <input type="email" id="email" name="email" required value="<?= htmlspecialchars($user['email']) ?>"
                    class="w-full px-4 py-3 rounded-lg bg-gray-800 text-gray-100 border border-gray-700 focus:outline-none focus:border-purple-500 transition">
            </div>
            <div class="mb-8">
                <label for="minecraft_username" class="block font-semibold mb-2 text-gray-300">
                    <i class="fa-brands fa-minecraft mr-2"></i>Pseudo Minecraft
                </label>
                <input type="text" id="minecraft_username" name="minecraft_username" required value="<?= htmlspecialchars($user['minecraft_username']) ?>"
                    class="w-full px-4 py-3 rounded-lg bg-gray-800 text-gray-100 border border-gray-700 focus:outline-none focus:border-purple-500 transition">
            </div>
            <div class="flex gap-4">
                <button type="submit"
                    class="flex-1 bg-gradient-to-r from-purple-600 to-gray-700 hover:from-purple-700 hover:to-gray-800 text-white font-bold py-3 px-6 rounded-xl shadow-lg transition duration-300 transform hover:scale-105 flex items-center justify-center gap-2 text-lg">
                    <i class="fa-solid fa-save"></i> Enregistrer
                </button>
                <a href="profil.php"
                    class="flex-1 text-center bg-gray-700 hover:bg-gray-800 text-white font-bold py-3 px-6 rounded-xl shadow transition duration-200 flex items-center justify-center gap-2 text-lg">
                    <i class="fa-solid fa-arrow-left"></i> Annuler
                </a>
            </div>
        </form>
    </main>

    <?php include 'includes/footer.php'; ?>
</body>
</html>
