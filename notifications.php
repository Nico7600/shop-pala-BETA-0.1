<?php
require_once 'config.php';
require_once 'includes/notification_helper.php';

if(!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Marquer comme lu
if(isset($_POST['mark_read'])) {
    markNotificationAsRead($pdo, $_POST['notification_id'], $_SESSION['user_id']);
    header('Location: notifications.php');
    exit;
}

// Marquer tout comme lu
if(isset($_POST['mark_all_read'])) {
    markAllNotificationsAsRead($pdo, $_SESSION['user_id']);
    header('Location: notifications.php');
    exit;
}

// Supprimer
if(isset($_POST['delete'])) {
    deleteNotification($pdo, $_POST['notification_id'], $_SESSION['user_id']);
    header('Location: notifications.php');
    exit;
}

$notifications = getUserNotifications($pdo, $_SESSION['user_id']);
$unread_count = countUnreadNotifications($pdo, $_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - CrazySouls Shop</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="bg-gray-900 text-white h-full flex flex-col">
    <?php include 'includes/header.php'; ?>

    <main class="flex-grow container mx-auto px-4 py-8">
        <div class="max-w-4xl mx-auto">
            <!-- Header -->
            <div class="flex justify-between items-center mb-8">
                <div>
                    <h1 class="text-4xl font-bold mb-2">
                        <i class="fas fa-bell mr-3 text-blue-500"></i>Notifications
                    </h1>
                    <p class="text-gray-400">
                        <?php echo $unread_count; ?> notification(s) non lue(s)
                    </p>
                </div>
                <?php if($unread_count > 0): ?>
                <form method="POST">
                    <button type="submit" name="mark_all_read" class="bg-purple-600 hover:bg-purple-700 px-4 py-2 rounded-lg transition">
                        <i class="fas fa-check-double mr-2"></i>Tout marquer comme lu
                    </button>
                </form>
                <?php endif; ?>
            </div>

            <!-- Notifications -->
            <?php if(empty($notifications)): ?>
            <div class="bg-gray-800 rounded-xl p-12 text-center">
                <i class="fas fa-bell-slash text-6xl text-gray-600 mb-4"></i>
                <h2 class="text-2xl font-bold mb-2">Aucune notification</h2>
                <p class="text-gray-400">Vous n'avez pas encore de notifications</p>
            </div>
            <?php else: ?>
            <div class="space-y-3">
                <?php foreach($notifications as $notif): 
                    $icon_map = [
                        'welcome' => 'fa-hand-wave text-yellow-500',
                        'purchase' => 'fa-shopping-bag text-green-500',
                        'new_product' => 'fa-sparkles text-purple-500',
                        'admin' => 'fa-shield-alt text-red-500',
                        'default' => 'fa-bell text-blue-500'
                    ];
                    $icon = $icon_map[$notif['type']] ?? $icon_map['default'];
                ?>
                <div class="bg-gray-800 rounded-xl p-4 <?php echo $notif['is_read'] ? 'opacity-60' : 'border-l-4 border-purple-500'; ?> hover:bg-gray-750 transition">
                    <div class="flex items-start gap-4">
                        <div class="w-12 h-12 bg-gray-700 rounded-full flex items-center justify-center flex-shrink-0">
                            <i class="fas <?php echo $icon; ?> text-xl"></i>
                        </div>
                        <div class="flex-1">
                            <div class="flex items-start justify-between">
                                <div>
                                    <h3 class="font-bold text-lg <?php echo !$notif['is_read'] ? 'text-white' : 'text-gray-400'; ?>">
                                        <?php echo htmlspecialchars($notif['title']); ?>
                                    </h3>
                                    <p class="text-gray-400 text-sm mt-1">
                                        <?php echo htmlspecialchars($notif['message']); ?>
                                    </p>
                                    <p class="text-xs text-gray-500 mt-2">
                                        <i class="fas fa-clock mr-1"></i>
                                        <?php echo date('d/m/Y à H:i', strtotime($notif['created_at'])); ?>
                                    </p>
                                </div>
                                <div class="flex gap-2">
                                    <?php if(!$notif['is_read']): ?>
                                    <form method="POST">
                                        <input type="hidden" name="notification_id" value="<?php echo $notif['id']; ?>">
                                        <button type="submit" name="mark_read" class="text-green-400 hover:text-green-300 transition" title="Marquer comme lu">
                                            <i class="fas fa-check"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                    <form method="POST" onsubmit="return confirm('Supprimer cette notification ?')">
                                        <input type="hidden" name="notification_id" value="<?php echo $notif['id']; ?>">
                                        <button type="submit" name="delete" class="text-red-400 hover:text-red-300 transition" title="Supprimer">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                            <?php if($notif['link']): ?>
                            <a href="<?php echo htmlspecialchars($notif['link']); ?>" class="inline-block mt-3 text-purple-400 hover:text-purple-300 text-sm font-medium">
                                <i class="fas fa-arrow-right mr-1"></i>Voir plus
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <?php include 'includes/footer.php'; ?>
</body>
</html>
