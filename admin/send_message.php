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
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: #111827;
        }
        .modal-bg {
            background: rgba(17, 24, 39, 0.85);
            backdrop-filter: blur(2px);
            transition: opacity 0.2s;
        }
        .modal {
            background: #232946;
            border-radius: 1.5rem;
            box-shadow: 0 16px 48px 0 #2563eb55;
            max-width: 30rem;
            width: 96%;
            animation: popupIn 0.25s;
            border: 2px solid #2563eb;
            padding: 2.5rem 2rem 2rem 2rem;
            position: relative;
        }
        @keyframes popupIn {
            from { transform: translateY(30px) scale(0.97); opacity: 0; }
            to { transform: translateY(0) scale(1); opacity: 1; }
        }
        .modal h2 {
            color: #38bdf8;
            letter-spacing: 0.02em;
            margin-bottom: 2rem;
            text-align: center;
        }
        .modal form {
            display: flex;
            flex-direction: column;
            gap: 1.2rem;
        }
        .modal label {
            font-weight: 600;
            color: #e0e7ef;
            margin-bottom: 0.3rem;
        }
        .modal input, .modal textarea, .modal select {
            background: #181f2a;
            color: #e0e7ef;
            border: 1.5px solid #2563eb;
            border-radius: 0.75rem;
            padding: 0.8em 1em;
            font-size: 1rem;
            width: 100%;
            transition: border 0.2s, box-shadow 0.2s;
        }
        .modal input:focus, .modal textarea:focus, .modal select:focus {
            border-color: #38bdf8;
            box-shadow: 0 0 0 2px #38bdf844;
            outline: none;
        }
        .modal button[type="submit"] {
            background: linear-gradient(90deg, #2563eb 0%, #38bdf8 100%);
            color: #fff;
            font-weight: bold;
            border-radius: 0.75rem;
            padding: 0.9em 2em;
            box-shadow: 0 2px 8px 0 #2563eb33;
            font-size: 1.1rem;
            margin-top: 0.5rem;
            transition: background 0.2s, transform 0.15s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.7em;
        }
        .modal button[type="submit"]:hover {
            background: linear-gradient(90deg, #1e40af 0%, #2563eb 100%);
            transform: scale(1.04);
        }
        #closeModalBtn {
            position: absolute;
            top: 1.2rem;
            right: 1.5rem;
            color: #38bdf8;
            background: none;
            border: none;
            font-size: 2.2rem;
            font-weight: bold;
            cursor: pointer;
            transition: color 0.2s, transform 0.15s;
            z-index: 10;
        }
        #closeModalBtn:hover {
            color: #fff;
            transform: scale(1.2) rotate(10deg);
        }
        /* Tableau notifications */
        .notif-table-card {
            background: #181f2a;
            border-radius: 1.25rem;
            box-shadow: 0 8px 32px 0 rgba(37,99,235,0.12);
            border: 2px solid #2563eb;
        }
        table.notifications-table {
            border-radius: 1rem;
            overflow: hidden;
            box-shadow: 0 4px 24px 0 rgba(37,99,235,0.10);
            background: #232946;
        }
        table.notifications-table th {
            background: #2563eb;
            color: #fff;
            font-weight: 700;
            font-size: 1rem;
            padding: 1rem 0.75rem;
            border-bottom: 2px solid #232946;
            text-align: left;
            letter-spacing: 0.01em;
        }
        table.notifications-table td {
            padding: 0.75rem;
            font-size: 0.95rem;
            vertical-align: middle;
            border-bottom: 1px solid #20232e;
        }
        table.notifications-table tr {
            transition: background 0.15s;
        }
        table.notifications-table tbody tr:nth-child(even) {
            background: #232946;
        }
        table.notifications-table tbody tr:nth-child(odd) {
            background: #20232e;
        }
        table.notifications-table tbody tr:hover {
            background: #38bdf8;
            color: #181f2a;
        }
        table.notifications-table td a {
            color: #38bdf8;
            text-decoration: underline;
        }
        table.notifications-table td a:hover {
            color: #2563eb;
        }
    </style>
</head>
<body class="min-h-screen flex flex-col md:flex-row bg-gray-900 text-gray-100">
    <?php require_once 'sidebar.php'; ?>
    <div class="flex-1 flex flex-col">
        <main class="flex-1 flex flex-col gap-8 px-2 sm:px-8 py-8">
            <!-- Bouton pour ouvrir la popup, centré et stylisé -->
            <div class="max-w-lg mx-auto mb-6 flex justify-center">
                <button
                    id="openModalBtn"
                    class="px-8 py-3 rounded-full text-white font-bold text-xl shadow-lg bg-gradient-to-r from-blue-700 via-blue-600 to-cyan-600
                           transition-all duration-200 hover:scale-105 hover:shadow-blue-500/40 focus:outline-none focus:ring-4 focus:ring-blue-400
                           border-2 border-blue-700"
                    style="box-shadow: 0 0 16px 2px #2563eb99;"
                >
                    <i class="fas fa-paper-plane"></i>
                    <span class="ml-2">Envoyer un message</span>
                </button>
            </div>
            <!-- Popup modale stylisée -->
            <div id="modalBg" class="fixed inset-0 z-50 flex items-center justify-center modal-bg hidden">
                <div class="modal">
                    <button id="closeModalBtn">&times;</button>
                    <h2 class="text-2xl font-bold flex items-center gap-2">
                        <i class="fas fa-envelope"></i>
                        Envoyer un message privé
                    </h2>
                    <?php if ($success): ?>
                        <div class="bg-green-600 text-white p-3 mb-4 rounded shadow text-base text-center"><?php echo $success; ?></div>
                    <?php endif; ?>
                    <?php if ($error): ?>
                        <div class="bg-red-600 text-white p-3 mb-4 rounded shadow text-base text-center"><?php echo $error; ?></div>
                    <?php endif; ?>
                    <form method="post" autocomplete="off">
                        <div>
                            <label>Utilisateur</label>
                            <select name="user_id" required>
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
                            <label>Titre</label>
                            <input type="text" name="title" required maxlength="100">
                        </div>
                        <div>
                            <label>Message</label>
                            <textarea name="message" rows="4" required maxlength="1000"></textarea>
                        </div>
                        <div>
                            <label>Lien (optionnel)</label>
                            <input type="url" name="link" placeholder="https://...">
                        </div>
                        <button type="submit">
                            <i class="fas fa-paper-plane"></i> Envoyer
                        </button>
                    </form>
                </div>
            </div>
            <!-- Tableau des notifications avec style amélioré -->
            <div class="max-w-7xl mx-auto notif-table-card p-4 sm:p-10 mt-4">
                <h2 class="text-xl font-bold mb-6 flex items-center gap-2 text-blue-400">
                    <i class="fas fa-bell"></i>
                    Toutes les notifications
                </h2>
                <div class="overflow-x-auto rounded-lg">
                    <table class="notifications-table min-w-full rounded-lg overflow-hidden text-gray-100 text-sm">
                        <thead>
                            <tr>
                                <th>Utilisateur</th>
                                <th>Titre</th>
                                <th class="hidden sm:table-cell">Message</th>
                                <th>Lien</th>
                                <th class="hidden sm:table-cell">Date</th>
                                <th>Lu</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($notifications as $i => $notif): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($notif['username'] ?? ''); ?></td>
                                    <td class="font-semibold"><?php echo htmlspecialchars($notif['title']); ?></td>
                                    <td class="hidden sm:table-cell"><?php echo htmlspecialchars($notif['message']); ?></td>
                                    <td>
                                        <?php if (!empty($notif['link'])): ?>
                                            <a href="<?php echo htmlspecialchars($notif['link']); ?>" target="_blank" class="flex items-center gap-1 font-semibold">
                                                <i class="fas fa-link"></i> <span>Lien</span>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-gray-500"><i class="fas fa-minus"></i></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="hidden sm:table-cell"><?php echo date('d/m/Y H:i', strtotime($notif['created_at'])); ?></td>
                                    <td class="text-center">
                                        <?php if ($notif['is_read']): ?>
                                            <span class="inline-flex items-center gap-1 text-green-400">
                                                <i class="fas fa-check-circle"></i> <span>Lu</span>
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center gap-1 text-red-400">
                                                <i class="fas fa-times-circle"></i> <span>Non lu</span>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <form method="post" class="flex gap-2">
                                            <input type="hidden" name="notif_id" value="<?php echo $notif['id']; ?>">
                                            <button type="submit" name="notif_action" value="delete"
                                                class="bg-red-600 hover:bg-red-700 text-gray-100 px-2 py-1 rounded flex items-center gap-1 text-xs"
                                                title="Supprimer"
                                                onclick="return confirm('Supprimer cette notification ?');">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            <?php if ($notif['is_read']): ?>
                                                <button type="submit" name="notif_action" value="unread"
                                                    class="bg-yellow-600 hover:bg-yellow-700 text-gray-100 px-2 py-1 rounded flex items-center gap-1 text-xs"
                                                    title="Remettre en non lu">
                                                    <i class="fas fa-undo"></i>
                                                </button>
                                            <?php endif; ?>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (empty($notifications)): ?>
                                <tr>
                                    <td colspan="7" class="px-4 py-6 text-center text-gray-400">Aucune notification trouvée.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                <div class="flex justify-center mt-4 sm:mt-6 gap-2">
                    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                        <a href="<?php echo '/send_message.php?page=' . $p; ?>"
                           class="px-3 py-1 rounded border <?php echo $p == $page ? 'bg-blue-700 text-white border-blue-700' : 'bg-gray-900 text-blue-300 border-gray-700 hover:bg-gray-700'; ?> text-xs sm:text-base font-semibold">
                            <?php echo $p; ?>
                        </a>
                    <?php endfor; ?>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>
    <script>
        // JS pour ouvrir/fermer la popup
        const openBtn = document.getElementById('openModalBtn');
        const modalBg = document.getElementById('modalBg');
        const closeBtn = document.getElementById('closeModalBtn');
        openBtn.onclick = () => { modalBg.classList.remove('hidden'); };
        closeBtn.onclick = () => { modalBg.classList.add('hidden'); };
        window.onclick = (e) => {
            if (e.target === modalBg) modalBg.classList.add('hidden');
        };
    </script>
</body>
</html>
