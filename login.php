<?php
require_once 'config.php';
require_once 'includes/notification_helper.php';

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    if(isset($_POST['login'])) {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];

            // Gestion du login streak
            $today = date('Y-m-d');
            $yesterday = date('Y-m-d', strtotime('-1 day'));
            $last_login = $user['last_login_date'];
            $login_streak = (int)$user['login_streak'];

            if ($last_login === $today) {
                // Déjà connecté aujourd'hui, ne rien changer
            } elseif ($last_login === $yesterday) {
                // Connexion consécutive, incrémenter le streak
                $login_streak++;
            } else {
                // Nouvelle série
                $login_streak = 1;
            }

            // Mettre à jour la date de connexion et le streak
            $stmt = $pdo->prepare("UPDATE users SET last_login_date = ?, login_streak = ? WHERE id = ?");
            $stmt->execute([$today, $login_streak, $user['id']]);
            
            // Logger la connexion
            try {
                $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, details, created_at) VALUES (?, ?, ?, NOW())");
                $stmt->execute([
                    $user['id'],
                    'login',
                    "Connexion réussie de " . $user['username'] . " (IP: " . $_SERVER['REMOTE_ADDR'] . ")"
                ]);
            } catch(PDOException $e) {
                // Ignorer l'erreur de log
            }

            // Enregistre la connexion dans user_logins
            try {
                $stmt = $pdo->prepare("INSERT INTO user_logins (user_id, login_at) VALUES (?, NOW())");
                $stmt->execute([$user['id']]);
            } catch(PDOException $e) {
                // Ignorer l'erreur de log
            }

            // Attribution des badges login_assign
            try {
                $stmt_badges = $pdo->prepare("SELECT id FROM badges WHERE login_assign = 1");
                $stmt_badges->execute();
                $login_badges = $stmt_badges->fetchAll(PDO::FETCH_COLUMN);
                foreach ($login_badges as $badge_id) {
                    // Vérifier si l'utilisateur a déjà ce badge
                    $stmt_check = $pdo->prepare("SELECT 1 FROM user_badges WHERE user_id = ? AND badge_id = ?");
                    $stmt_check->execute([$user['id'], $badge_id]);
                    if (!$stmt_check->fetch()) {
                        $stmt_insert = $pdo->prepare("INSERT INTO user_badges (user_id, badge_id, date_obtenue) VALUES (?, ?, NOW())");
                        $stmt_insert->execute([$user['id'], $badge_id]);
                    }
                }
            } catch(PDOException $e) {
                // Ignorer l'erreur de badge
            }
            
            header('Location: index.php');
            exit;
        }
        $error = "Identifiants incorrects";
    } elseif(isset($_POST['register'])) {
        $username = $_POST['reg_username'] ?? '';
        $email = $_POST['reg_email'] ?? '';
        $password = $_POST['reg_password'] ?? '';
        $minecraft_username = $_POST['minecraft_username'] ?? '';

        if(strlen($password) < 6) {
            $error = "Le mot de passe doit contenir au moins 6 caractères";
        } else {
            try {
                // Vérifier si le nom d'utilisateur ou l'email existe déjà
                $stmt_check = $pdo->prepare("SELECT 1 FROM users WHERE username = ? OR email = ?");
                $stmt_check->execute([$username, $email]);
                if ($stmt_check->fetch()) {
                    $error = "Cet email ou nom d'utilisateur existe déjà";
                } else {
                    $stmt = $pdo->prepare("INSERT INTO users (username, email, password, minecraft_username, role) VALUES (?, ?, ?, ?, 'client')");
                    $stmt->execute([$username, $email, password_hash($password, PASSWORD_DEFAULT), $minecraft_username]);

                    // Enregistre la première connexion dans user_logins
                    $new_user_id = $pdo->lastInsertId();
                    try {
                        $stmt = $pdo->prepare("INSERT INTO user_logins (user_id, login_at) VALUES (?, NOW())");
                        $stmt->execute([$new_user_id]);
                    } catch(PDOException $e) {
                        // Ignorer l'erreur de log
                    }

                    // Créer une notification de bienvenue
                    notifyWelcome($pdo, $new_user_id, $username);

                    // Attribution des badges auto_assign
                    try {
                        $stmt_badges = $pdo->prepare("SELECT id FROM badges WHERE auto_assign = 1");
                        $stmt_badges->execute();
                        $auto_badges = $stmt_badges->fetchAll(PDO::FETCH_COLUMN);
                        foreach ($auto_badges as $badge_id) {
                            $stmt_insert = $pdo->prepare("INSERT INTO user_badges (user_id, badge_id, date_obtenue) VALUES (?, ?, NOW())");
                            $stmt_insert->execute([$new_user_id, $badge_id]);
                        }
                    } catch(PDOException $e) {
                        // Ignorer l'erreur de badge
                    }

                    $success = "Compte créé avec succès ! Vous pouvez vous connecter.";
                }
            } catch(PDOException $e) {
                $error = "Erreur lors de la création du compte";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - CrazySouls Shop</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
</head>
<body class="bg-gray-900 h-full flex flex-col">
    <div class="flex-grow flex items-center justify-center p-4">
        <div class="max-w-4xl w-full bg-gray-800 rounded-2xl shadow-2xl overflow-hidden border border-gray-700">
            <!-- Logo en haut -->
            <div class="bg-gradient-to-r from-purple-600 to-pink-600 p-6 text-center">
                <i class="fas fa-gem text-5xl text-white mb-2"></i>
                <h1 class="text-2xl font-bold text-white">CrazySouls Shop</h1>
                <p class="text-purple-100 text-sm">Connectez-vous pour accéder à la boutique</p>
            </div>

            <div class="grid md:grid-cols-2">
                <!-- Connexion -->
                <div class="p-8 border-r border-gray-700">
                    <h2 class="text-3xl font-bold text-purple-500 mb-6">
                        <i class="fas fa-sign-in-alt mr-2"></i>Connexion
                    </h2>
                    <?php if(isset($error)): ?>
                        <div class="bg-red-500/20 border border-red-500 text-red-400 px-4 py-3 rounded-lg mb-4">
                            <i class="fas fa-exclamation-circle mr-2"></i><?php echo $error; ?>
                        </div>
                    <?php endif; ?>
                    <?php if(isset($success)): ?>
                        <div class="bg-green-500/20 border border-green-500 text-green-400 px-4 py-3 rounded-lg mb-4">
                            <i class="fas fa-check-circle mr-2"></i><?php echo $success; ?>
                        </div>
                    <?php endif; ?>
                    <form method="POST" class="space-y-4">
                        <div>
                            <label class="block text-gray-300 mb-2">
                                <i class="fas fa-user mr-2"></i>Nom d'utilisateur
                            </label>
                            <input type="text" name="username" required 
                                class="w-full px-4 py-3 bg-gray-900 border border-gray-700 rounded-lg text-white focus:border-purple-500 focus:outline-none transition">
                        </div>
                        <div>
                            <label class="block text-gray-300 mb-2">
                                <i class="fas fa-lock mr-2"></i>Mot de passe
                            </label>
                            <input type="password" name="password" required 
                                class="w-full px-4 py-3 bg-gray-900 border border-gray-700 rounded-lg text-white focus:border-purple-500 focus:outline-none transition">
                        </div>
                        <button type="submit" name="login" 
                            class="w-full bg-purple-600 hover:bg-purple-700 text-white font-bold py-3 rounded-lg transition duration-300">
                            <i class="fas fa-sign-in-alt mr-2"></i>Se connecter
                        </button>
                    </form>
                    <div class="mt-6 text-center">
                        <a href="index.php" class="text-sm text-purple-400 hover:text-purple-300 transition-colors">
                            <i class="fas fa-arrow-left mr-1"></i>Retour à l'accueil
                        </a>
                    </div>
                </div>

                <!-- Inscription -->
                <div class="p-8 bg-gray-750">
                    <h2 class="text-3xl font-bold text-green-500 mb-6">
                        <i class="fas fa-user-plus mr-2"></i>Inscription
                    </h2>
                    <form method="POST" class="space-y-4">
                        <div>
                            <label class="block text-gray-300 mb-2">
                                <i class="fas fa-user mr-2"></i>Nom d'utilisateur
                            </label>
                            <input type="text" name="reg_username" required 
                                class="w-full px-4 py-3 bg-gray-900 border border-gray-700 rounded-lg text-white focus:border-green-500 focus:outline-none transition">
                        </div>
                        <div>
                            <label class="block text-gray-300 mb-2">
                                <i class="fas fa-envelope mr-2"></i>Email
                            </label>
                            <input type="email" name="reg_email" required 
                                class="w-full px-4 py-3 bg-gray-900 border border-gray-700 rounded-lg text-white focus:border-green-500 focus:outline-none transition">
                        </div>
                        <div>
                            <label class="block text-gray-300 mb-2">
                                <i class="fas fa-gamepad mr-2"></i>Pseudo Minecraft
                            </label>
                            <input type="text" name="minecraft_username" required 
                                class="w-full px-4 py-3 bg-gray-900 border border-gray-700 rounded-lg text-white focus:border-green-500 focus:outline-none transition">
                        </div>
                        <div>
                            <label class="block text-gray-300 mb-2">
                                <i class="fas fa-lock mr-2"></i>Mot de passe
                            </label>
                            <input type="password" name="reg_password" required minlength="6"
                                class="w-full px-4 py-3 bg-gray-900 border border-gray-700 rounded-lg text-white focus:border-green-500 focus:outline-none transition">
                        </div>
                        <button type="submit" name="register" 
                            class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-3 rounded-lg transition duration-300">
                            <i class="fas fa-user-plus mr-2"></i>Créer un compte
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Mini Footer pour la page login -->
    <footer class="bg-gray-800 border-t border-gray-700 py-4 mt-8">
        <div class="container mx-auto px-4 text-center text-gray-400 text-sm">
            <p>&copy; 2024 CrazySouls Shop - Tous droits réservés</p>
        </div>
    </footer>
</body>
</html>
