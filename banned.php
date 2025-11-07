<?php
session_start();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Accès interdit</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-gray-100 flex items-center justify-center min-h-screen">
    <div class="bg-red-800/80 border-2 border-red-500 rounded-xl p-10 text-center max-w-lg shadow-2xl">
        <i class="fas fa-ban text-6xl text-red-400 mb-6"></i>
        <h1 class="text-3xl font-bold text-red-400 mb-4">Accès interdit</h1>
        <p class="text-lg mb-4">Votre compte a été banni.<br>Vous n'avez plus accès à la boutique.</p>
        <a href="index.php" class="bg-gray-700 hover:bg-gray-600 px-6 py-2 rounded-lg text-white font-bold mt-4 inline-block">
            Retour à l'accueil
        </a>
    </div>
</body>
</html>
