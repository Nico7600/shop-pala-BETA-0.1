<?php
require_once '../config.php';
require_once 'check_admin.php';

// Récupérer dynamiquement les utilisateurs depuis la base de données
$users = [];
$stmt = $pdo->query("SELECT id, username, email, role FROM users");
if ($stmt) {
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Pagination
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 10;
$totalNotif = $pdo->query("SELECT COUNT(*) FROM notifications")->fetchColumn();
$totalPages = ceil($totalNotif / $perPage);
$offset = ($page - 1) * $perPage;

$sql = "SELECT n.*, u.username FROM notifications n LEFT JOIN users u ON n.user_id = u.id ORDER BY n.created_at DESC LIMIT $perPage OFFSET $offset";
$stmt = $pdo->query($sql);
if ($stmt) {
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$success = $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = intval($_POST['user_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $link = trim($_POST['link'] ?? '');

    if ($user_id > 0 && $title !== '' && $message !== '') {
        // Insérer dans la table notifications
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, type, title, message, link, is_read, created_at) VALUES (?, 'admin', ?, ?, ?, 0, NOW())");
        if ($stmt->execute([$user_id, $title, $message, $link])) {
            $success = "Message envoyé avec succès !";
        } else {
            $error = "Erreur lors de l'envoi du message.";
        }
    } else {
        $error = "Veuillez remplir tous les champs obligatoires et sélectionner un utilisateur.";
    }
    // Action sur notification
    if (isset($_POST['notif_action'], $_POST['notif_id'])) {
        $notif_id = intval($_POST['notif_id']);
        if ($_POST['notif_action'] === 'delete') {
            $stmt = $pdo->prepare("DELETE FROM notifications WHERE id = ?");
            if ($stmt->execute([$notif_id])) {
                $success = "Notification supprimée.";
            } else {
                $error = "Erreur lors de la suppression.";
            }
        }
        if ($_POST['notif_action'] === 'unread') {
            $stmt = $pdo->prepare("UPDATE notifications SET is_read = 0 WHERE id = ?");
            if ($stmt->execute([$notif_id])) {
                $success = "Notification remise en non lu.";
            } else {
                $error = "Erreur lors de la modification.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Envoyer un message privé</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tailwindcss/dist/tailwind.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-900 text-white flex flex-col md:flex-row min-h-screen">
    <?php require_once 'sidebar.php'; ?>
    <!-- Contenu principal -->
    <div class="flex-1">
        <div class="max-w-lg mx-auto mt-6 sm:mt-10 bg-gray-800 p-2 sm:p-8 rounded shadow-lg">
            <h2 class="text-lg sm:text-2xl font-bold mb-4 sm:mb-6 flex items-center gap-2">
                <i class="fas fa-envelope"></i>
                Envoyer un message privé
            </h2>
            <?php if ($success): ?>
                <div class="bg-green-600 text-white p-2 sm:p-3 mb-2 sm:mb-4 rounded shadow text-xs sm:text-base"><?php echo $success; ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="bg-red-600 text-white p-2 sm:p-3 mb-2 sm:mb-4 rounded shadow text-xs sm:text-base"><?php echo $error; ?></div>
            <?php endif; ?>
            <form method="post" autocomplete="off" class="space-y-2 sm:space-y-4 text-xs sm:text-base">
                <div>
                    <label class="block mb-1 sm:mb-2 font-semibold">Utilisateur</label>
                    <select name="user_id" class="w-full p-2 rounded bg-gray-700 text-white border border-fuchsia-600 focus:outline-none focus:ring-2 focus:ring-fuchsia-500" required>
                        <option value="">Sélectionner...</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?php echo $user['id']; ?>">
                                <?php echo htmlspecialchars($user['username']); ?>
                                (<?php echo htmlspecialchars($user['role'] ?? ''); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block mb-1 sm:mb-2 font-semibold">Titre</label>
                    <input type="text" name="title" class="w-full p-2 rounded bg-gray-700 text-white border border-fuchsia-600 focus:outline-none focus:ring-2 focus:ring-fuchsia-500" required maxlength="100">
                </div>
                <div>
                    <label class="block mb-1 sm:mb-2 font-semibold">Message</label>
                    <textarea name="message" class="w-full p-2 rounded bg-gray-700 text-white border border-fuchsia-600 focus:outline-none focus:ring-2 focus:ring-fuchsia-500" rows="4" required maxlength="1000"></textarea>
                </div>
                <div>
                    <label class="block mb-1 sm:mb-2 font-semibold">Lien (optionnel)</label>
                    <input type="url" name="link" class="w-full p-2 rounded bg-gray-700 text-white border border-fuchsia-600 focus:outline-none focus:ring-2 focus:ring-fuchsia-500" placeholder="https://...">
                </div>
                <button type="submit" class="bg-fuchsia-600 px-4 sm:px-6 py-2 rounded text-white font-bold hover:bg-fuchsia-700 transition flex items-center gap-2 shadow text-xs sm:text-base">
                    <i class="fas fa-paper-plane"></i> Envoyer
                </button>
            </form>
        </div>
        <!-- Tableau des notifications -->
        <div class="max-w-7xl mx-auto mt-8 sm:mt-16 bg-gray-800 p-2 sm:p-8 rounded shadow-lg">
            <h2 class="text-base sm:text-xl font-bold mb-4 sm:mb-6 flex items-center gap-2">
                <i class="fas fa-bell"></i>
                Toutes les notifications
            </h2>
            <div class="overflow-x-auto rounded-lg border border-gray-700">
                <table class="min-w-full divide-y divide-gray-700 text-xs sm:text-sm">
                    <thead class="bg-gray-900">
                        <tr>
                            <th class="px-2 sm:px-4 py-2 text-left font-semibold">Utilisateur</th>
                            <th class="px-2 sm:px-4 py-2 text-left font-semibold">Titre</th>
                            <th class="px-2 sm:px-4 py-2 text-left font-semibold hidden sm:table-cell">Message</th>
                            <th class="px-2 sm:px-4 py-2 text-left font-semibold">Lien</th>
                            <th class="px-2 sm:px-4 py-2 text-left font-semibold hidden sm:table-cell">Date</th>
                            <th class="px-2 sm:px-4 py-2 text-left font-semibold">Lu</th>
                            <th class="px-2 sm:px-4 py-2 text-left font-semibold">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-700">
                        <?php foreach ($notifications as $i => $notif): ?>
                            <tr class="<?php echo $i % 2 ? 'bg-gray-800' : 'bg-gray-900'; ?> hover:bg-gray-700 transition">
                                <td class="px-2 sm:px-4 py-2"><?php echo htmlspecialchars($notif['username'] ?? ''); ?></td>
                                <td class="px-2 sm:px-4 py-2 font-semibold"><?php echo htmlspecialchars($notif['title']); ?></td>
                                <td class="px-2 sm:px-4 py-2 hidden sm:table-cell"><?php echo htmlspecialchars($notif['message']); ?></td>
                                <td class="px-2 sm:px-4 py-2">
                                    <?php if (!empty($notif['link'])): ?>
                                        <a href="<?php echo htmlspecialchars($notif['link']); ?>" target="_blank" class="text-fuchsia-400 underline flex items-center gap-1">
                                            <i class="fas fa-link"></i> Lien
                                        </a>
                                    <?php else: ?>
                                        <span class="text-gray-500"><i class="fas fa-minus"></i></span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-2 sm:px-4 py-2 hidden sm:table-cell"><?php echo date('d/m/Y H:i', strtotime($notif['created_at'])); ?></td>
                                <td class="px-2 sm:px-4 py-2 text-center">
                                    <?php if ($notif['is_read']): ?>
                                        <span class="inline-flex items-center gap-1 bg-green-600 text-white px-2 py-1 rounded text-xs">
                                            <i class="fas fa-check-circle"></i> Lu
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1 bg-red-600 text-white px-2 py-1 rounded text-xs">
                                            <i class="fas fa-times-circle"></i> Non lu
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-2 sm:px-4 py-2">
                                    <form method="post" class="flex gap-1 sm:gap-2">
                                        <input type="hidden" name="notif_id" value="<?php echo $notif['id']; ?>">
                                        <button type="submit" name="notif_action" value="delete"
                                            class="bg-red-600 hover:bg-red-700 text-white px-2 py-1 rounded text-xs flex items-center gap-1"
                                            onclick="return confirm('Supprimer cette notification ?');">
                                            <i class="fas fa-trash"></i> Supprimer
                                        </button>
                                        <?php if ($notif['is_read']): ?>
                                            <button type="submit" name="notif_action" value="unread"
                                                class="bg-yellow-600 hover:bg-yellow-700 text-white px-2 py-1 rounded text-xs flex items-center gap-1">
                                                <i class="fas fa-undo"></i> Non lu
                                            </button>
                                        <?php endif; ?>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($notifications)): ?>
                            <tr>
                                <td colspan="7" class="px-2 sm:px-4 py-4 sm:py-6 text-center text-gray-400">Aucune notification trouvée.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <div class="flex justify-center mt-4 sm:mt-6 gap-1 sm:gap-2">
                <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                    <a href="?page=<?php echo $p; ?>"
                       class="px-2 sm:px-3 py-1 rounded border <?php echo $p == $page ? 'bg-fuchsia-600 text-white border-fuchsia-600' : 'bg-gray-900 text-fuchsia-400 border-gray-700 hover:bg-gray-700'; ?> text-xs sm:text-base">
                        <?php echo $p; ?>
                    </a>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
