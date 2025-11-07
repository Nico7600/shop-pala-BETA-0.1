<?php
require_once '../config.php';

if(!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['resp_vendeur', 'fondateur'])) {
    header('Location: ../login.php');
    exit;
}

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $target = $_POST['target'] ?? 'all';
    $title = $_POST['title'] ?? '';
    $message = $_POST['message'] ?? '';
    $type = $_POST['type'] ?? 'info';
    $link = $_POST['link'] ?? '';
    
    if($target === 'all') {
        $users = $pdo->query("SELECT id FROM users")->fetchAll();
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, link) VALUES (?, ?, ?, ?, ?)");
        foreach($users as $user) {
            $stmt->execute([$user['id'], $title, $message, $type, $link ?: null]);
        }
    } else {
        $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, link) VALUES (?, ?, ?, ?, ?)")
            ->execute([$target, $title, $message, $type, $link ?: null]);
    }
    
    $success = "Notification envoyée avec succès !";
}

$users = $pdo->query("SELECT id, username FROM users ORDER BY username")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Envoyer une Notification - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="bg-gray-900 text-gray-100">
    <div class="flex min-h-screen">
        <?php include 'sidebar.php'; ?>

        <main class="flex-1 p-8">
            <div class="max-w-3xl mx-auto">
                <h2 class="text-3xl font-bold mb-8">
                    <i class="fas fa-paper-plane mr-3 text-blue-500"></i>
                    Envoyer une Notification
                </h2>

                <?php if(isset($success)): ?>
                <div class="bg-green-500/20 border border-green-500 text-green-400 px-6 py-4 rounded-lg mb-6">
                    <i class="fas fa-check-circle mr-2"></i><?php echo $success; ?>
                </div>
                <?php endif; ?>

                <form method="POST" class="bg-gray-800 rounded-xl p-6 space-y-6">
                    <div>
                        <label class="block text-gray-300 mb-2 font-medium">Destinataire</label>
                        <select name="target" required class="w-full px-4 py-3 bg-gray-900 border border-gray-700 rounded-lg text-white focus:border-purple-500 focus:outline-none">
                            <option value="all">Tous les utilisateurs</option>
                            <optgroup label="Utilisateurs spécifiques">
                                <?php foreach($users as $user): ?>
                                <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['username']); ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                        </select>
                    </div>

                    <div>
                        <label class="block text-gray-300 mb-2 font-medium">Type</label>
                        <select name="type" required class="w-full px-4 py-3 bg-gray-900 border border-gray-700 rounded-lg text-white focus:border-purple-500 focus:outline-none">
                            <option value="info">Information</option>
                            <option value="success">Succès</option>
                            <option value="warning">Avertissement</option>
                            <option value="error">Erreur</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-gray-300 mb-2 font-medium">Titre</label>
                        <input type="text" name="title" required maxlength="255" 
                            class="w-full px-4 py-3 bg-gray-900 border border-gray-700 rounded-lg text-white focus:border-purple-500 focus:outline-none">
                    </div>

                    <div>
                        <label class="block text-gray-300 mb-2 font-medium">Message</label>
                        <textarea name="message" required rows="4" 
                            class="w-full px-4 py-3 bg-gray-900 border border-gray-700 rounded-lg text-white focus:border-purple-500 focus:outline-none"></textarea>
                    </div>

                    <div>
                        <label class="block text-gray-300 mb-2 font-medium">Lien (optionnel)</label>
                        <input type="text" name="link" placeholder="catalog.php" 
                            class="w-full px-4 py-3 bg-gray-900 border border-gray-700 rounded-lg text-white focus:border-purple-500 focus:outline-none">
                    </div>

                    <button type="submit" 
                        class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded-lg transition-colors">
                        <i class="fas fa-paper-plane mr-2"></i>Envoyer la notification
                    </button>
                </form>
            </div>
        </main>
    </div>
</body>
</html>
