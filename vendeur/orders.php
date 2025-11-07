<?php
require_once '../config.php';
require_once 'check_seller.php';

// Récupérer toutes les commandes avec les détails
try {
    $stmt = $pdo->prepare("
        SELECT o.*, u.username, u.minecraft_username,
               s.username as seller_name,
               GROUP_CONCAT(CONCAT(oi.quantity, 'x ', oi.product_name) SEPARATOR ', ') as items,
               COALESCE(SUM(oi.price * oi.quantity), 0) as total_price
        FROM orders o
        JOIN users u ON o.user_id = u.id
        LEFT JOIN users s ON o.seller_id = s.id
        LEFT JOIN order_items oi ON o.id = oi.order_id
        GROUP BY o.id
        ORDER BY o.created_at DESC
    ");
    $stmt->execute();
    $orders = $stmt->fetchAll();
} catch(PDOException $e) {
    $orders = [];
    $error = "Erreur lors de la récupération des commandes : " . $e->getMessage();
}

// Traitement du claim
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['claim_order'])) {
    try {
        $order_id = $_POST['order_id'];
        
        // Vérifier que la commande n'est pas déjà claim
        $stmt = $pdo->prepare("SELECT seller_id FROM orders WHERE id = ?");
        $stmt->execute([$order_id]);
        $order = $stmt->fetch();
        
        if($order && !$order['seller_id']) {
            $stmt = $pdo->prepare("UPDATE orders SET seller_id = ?, status = 'processing' WHERE id = ?");
            $stmt->execute([$_SESSION['user_id'], $order_id]);
            
            $_SESSION['success'] = "Commande #$order_id prise en charge avec succès";
        } else {
            $_SESSION['error'] = "Cette commande est déjà prise en charge";
        }
        
        header('Location: orders.php');
        exit;
    } catch(PDOException $e) {
        $_SESSION['error'] = "Erreur : " . $e->getMessage();
    }
}

// Traitement du unclaim
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['unclaim_order'])) {
    try {
        $order_id = $_POST['order_id'];
        
        $stmt = $pdo->prepare("UPDATE orders SET seller_id = NULL, status = 'pending' WHERE id = ? AND seller_id = ?");
        $stmt->execute([$order_id, $_SESSION['user_id']]);
        
        $_SESSION['success'] = "Commande #$order_id libérée";
        header('Location: orders.php');
        exit;
    } catch(PDOException $e) {
        $_SESSION['error'] = "Erreur : " . $e->getMessage();
    }
}

// Traitement du changement de statut
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id'], $_POST['status'])) {
    try {
        $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ? AND seller_id = ?");
        $stmt->execute([$_POST['status'], $_POST['order_id'], $_SESSION['user_id']]);
        
        // Notifier le client
        $stmt = $pdo->prepare("SELECT user_id FROM orders WHERE id = ?");
        $stmt->execute([$_POST['order_id']]);
        $user_id = $stmt->fetchColumn();
        
        $status_messages = [
            'completed' => 'Votre commande #' . $_POST['order_id'] . ' a été marquée comme complétée',
            'delivered' => 'Votre commande #' . $_POST['order_id'] . ' a été livrée',
            'cancelled' => 'Votre commande #' . $_POST['order_id'] . ' a été annulée'
        ];
        
        if(isset($status_messages[$_POST['status']])) {
            $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'order')");
            $stmt->execute([$user_id, $status_messages[$_POST['status']]]);
        }
        
        $_SESSION['success'] = "Statut de la commande mis à jour";
        header('Location: orders.php');
        exit;
    } catch(PDOException $e) {
        $_SESSION['error'] = "Erreur lors de la mise à jour : " . $e->getMessage();
    }
}

// Traitement de l'envoi de notification
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_notification'])) {
    try {
        $order_id = $_POST['order_id'];
        $message = trim($_POST['notification_message']);
        $title = trim($_POST['notification_title']);
        
        if(empty($message)) {
            throw new Exception("Le message ne peut pas être vide");
        }
        
        // Récupérer l'user_id de la commande
        $stmt = $pdo->prepare("SELECT user_id FROM orders WHERE id = ? AND seller_id = ?");
        $stmt->execute([$order_id, $_SESSION['user_id']]);
        $user_id = $stmt->fetchColumn();
        
        if($user_id) {
            $stmt = $pdo->prepare("
                INSERT INTO notifications (user_id, type, title, message, link, is_read, created_at) 
                VALUES (?, 'order', ?, ?, ?, 0, NOW())
            ");
            $stmt->execute([
                $user_id, 
                $title ?: 'Mise à jour de votre commande',
                $message,
                'orders.php'
            ]);
            
            $_SESSION['success'] = "Notification envoyée au client";
        } else {
            $_SESSION['error'] = "Commande introuvable ou non assignée";
        }
        
        header('Location: orders.php');
        exit;
    } catch(Exception $e) {
        $_SESSION['error'] = "Erreur : " . $e->getMessage();
    }
}

// Traitement du bouton Valider
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['validate_order'])) {
    try {
        $order_id = $_POST['order_id'];
        $seller_id = $_SESSION['user_id'];

        // Mettre à jour le statut à 'clos'
        $stmt = $pdo->prepare("UPDATE orders SET status = 'clos' WHERE id = ? AND seller_id = ? AND status = 'completed'");
        $stmt->execute([$order_id, $seller_id]);

        $_SESSION['success'] = "Commande #$order_id clôturée avec succès.";
        header('Location: orders.php');
        exit;
    } catch(PDOException $e) {
        $_SESSION['error'] = "Erreur lors de la clôture : " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Commandes</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.8);
        }
        .modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }
    </style>
</head>
<body class="bg-gray-900 text-gray-100">
    <div class="flex flex-col md:flex-row min-h-screen">
        <?php include 'sidebar.php'; ?>

        <main class="flex-1 p-2 sm:p-8 w-full">
            <div class="container mx-auto">
                <div class="mb-4 sm:mb-8">
                    <h1 class="text-2xl sm:text-4xl font-bold mb-2">
                        <i class="fas fa-shopping-cart text-orange-500 mr-2 sm:mr-3"></i>
                        Gestion des Commandes
                    </h1>
                    <p class="text-gray-400 text-xs sm:text-base">Gérer et suivre toutes les commandes</p>
                </div>

                <?php if(isset($_SESSION['success'])): ?>
                <div class="bg-green-500/20 border border-green-500 text-green-400 px-2 sm:px-4 py-2 sm:py-3 rounded-lg mb-4 sm:mb-6 text-xs sm:text-base">
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
                <?php endif; ?>

                <?php if(isset($_SESSION['error'])): ?>
                <div class="bg-red-500/20 border border-red-500 text-red-400 px-2 sm:px-4 py-2 sm:py-3 rounded-lg mb-4 sm:mb-6 text-xs sm:text-base">
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
                <?php endif; ?>

                <!-- Filtres -->
                <div class="bg-gray-800 rounded-xl p-2 sm:p-4 mb-4 sm:mb-6 flex flex-col sm:flex-row gap-2 sm:gap-4">
                    <button onclick="filterOrders('all')" class="filter-btn px-4 py-2 rounded-lg bg-purple-600 hover:bg-purple-700 transition-all">
                        Toutes
                    </button>
                    <button onclick="filterOrders('pending')" class="filter-btn px-4 py-2 rounded-lg bg-gray-700 hover:bg-gray-600 transition-all">
                        En attente
                    </button>
                    <button onclick="filterOrders('processing')" class="filter-btn px-4 py-2 rounded-lg bg-gray-700 hover:bg-gray-600 transition-all">
                        En cours
                    </button>
                    <button onclick="filterOrders('completed')" class="filter-btn px-4 py-2 rounded-lg bg-gray-700 hover:bg-gray-600 transition-all">
                        Complétées
                    </button>
                    <button onclick="filterOrders('delivered')" class="filter-btn px-4 py-2 rounded-lg bg-gray-700 hover:bg-gray-600 transition-all">
                        Livrées
                    </button>
                    <button onclick="filterOrders('my')" class="filter-btn px-4 py-2 rounded-lg bg-gray-700 hover:bg-gray-600 transition-all">
                        <i class="fas fa-user mr-1"></i> Mes commandes
                    </button>
                </div>

                <!-- Liste des commandes -->
                <div class="bg-gray-800 rounded-xl overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs sm:text-base">
                            <thead class="bg-gray-900">
                                <tr>
                                    <th class="px-2 sm:px-4 py-2 sm:py-3 text-left">ID</th>
                                    <th class="px-2 sm:px-4 py-2 sm:py-3 text-left">Client</th>
                                    <th class="px-2 sm:px-4 py-2 sm:py-3 text-left">Pseudo MC</th>
                                    <th class="px-2 sm:px-4 py-2 sm:py-3 text-left hidden sm:table-cell">Articles</th>
                                    <th class="px-2 sm:px-4 py-2 sm:py-3 text-center">Montant</th>
                                    <th class="px-2 sm:px-4 py-2 sm:py-3 text-center hidden sm:table-cell">Vendeur</th>
                                    <th class="px-2 sm:px-4 py-2 sm:py-3 text-center">Statut</th>
                                    <th class="px-2 sm:px-4 py-2 sm:py-3 text-center hidden sm:table-cell">Date</th>
                                    <th class="px-2 sm:px-4 py-2 sm:py-3 text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-700">
                                <?php foreach($orders as $order): ?>
                                <tr class="hover:bg-gray-700/50 order-row" data-status="<?php echo $order['status']; ?>" data-seller="<?php echo $order['seller_id'] == $_SESSION['user_id'] ? 'my' : 'other'; ?>">
                                    <td class="px-2 sm:px-4 py-2 sm:py-3 font-mono text-xs sm:text-sm font-bold text-purple-400">#<?php echo $order['id']; ?></td>
                                    <td class="px-2 sm:px-4 py-2 sm:py-3"><?php echo htmlspecialchars($order['username']); ?></td>
                                    <td class="px-2 sm:px-4 py-2 sm:py-3">
                                        <code class="bg-gray-900 px-2 py-1 rounded text-xs">
                                            <?php echo htmlspecialchars($order['minecraft_username'] ?? 'N/A'); ?>
                                        </code>
                                    </td>
                                    <td class="px-2 sm:px-4 py-2 sm:py-3 text-sm text-gray-400 hidden sm:table-cell">
                                        <?php echo htmlspecialchars($order['items']); ?>
                                    </td>
                                    <td class="px-2 sm:px-4 py-2 sm:py-3 text-center font-bold text-green-400">
                                        <?php
                                        $total = $order['total'] ?? 0;
                                        $discount_amount = $order['discount_amount'] ?? 0;
                                        $total_paid = $total - $discount_amount;
                                        echo number_format($total_paid, 2) . "€";
                                        ?>
                                    </td>
                                    <td class="px-2 sm:px-4 py-2 sm:py-3 text-center hidden sm:table-cell">
                                        <?php if($order['seller_id']): ?>
                                            <span class="inline-block px-2 py-1 rounded text-xs font-bold <?php echo $order['seller_id'] == $_SESSION['user_id'] ? 'bg-green-500/20 text-green-400' : 'bg-gray-700 text-gray-400'; ?>">
                                                <i class="fas fa-user-check mr-1"></i>
                                                <?php echo $order['seller_id'] == $_SESSION['user_id'] ? 'Vous' : htmlspecialchars($order['seller_name']); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-gray-500 text-xs">Non assignée</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-2 sm:px-4 py-2 sm:py-3 text-center">
                                        <?php
                                        $status_colors = [
                                            'pending' => 'bg-orange-500/20 text-orange-400 border-orange-500/30',
                                            'processing' => 'bg-blue-500/20 text-blue-400 border-blue-500/30',
                                            'completed' => 'bg-green-500/20 text-green-400 border-green-500/30',
                                            'cancelled' => 'bg-red-500/20 text-red-400 border-red-500/30',
                                            'delivered' => 'bg-purple-500/20 text-purple-400 border-purple-500/30',
                                            'clos' => 'bg-gray-500/20 text-gray-400 border-gray-500/30'
                                        ];
                                        $status_labels = [
                                            'pending' => 'En attente',
                                            'processing' => 'En cours',
                                            'completed' => 'Complétée',
                                            'cancelled' => 'Annulée',
                                            'delivered' => 'Livrée',
                                            'clos' => 'Clôturée'
                                        ];
                                        ?>
                                        <span class="inline-block px-2 sm:px-3 py-1 rounded-full text-xs font-bold border <?php echo $status_colors[$order['status']]; ?>">
                                            <?php echo $status_labels[$order['status']]; ?>
                                        </span>
                                    </td>
                                    <td class="px-2 sm:px-4 py-2 sm:py-3 text-center text-xs sm:text-sm text-gray-400 hidden sm:table-cell">
                                        <?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?>
                                    </td>
                                    <td class="px-2 sm:px-4 py-2 sm:py-3 text-center">
                                        <?php if(!$order['seller_id']): ?>
                                            <!-- Bouton Claim -->
                                            <form method="POST" class="inline-block">
                                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                <button type="submit" name="claim_order" class="bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded text-sm font-bold transition-all">
                                                    <i class="fas fa-hand-paper mr-1"></i>
                                                    Claim
                                                </button>
                                            </form>
                                        <?php elseif($order['seller_id'] == $_SESSION['user_id']): ?>
                                            <!-- Actions pour mes commandes -->
                                            <div class="flex gap-2 justify-center items-center">
                                                <?php if($order['status'] !== 'clos'): ?>
                                                <form method="POST" class="inline-block">
                                                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                    <select name="status" onchange="this.form.submit()" class="bg-gray-700 border border-gray-600 rounded px-2 py-1 text-sm">
                                                        <option value="">Statut</option>
                                                        <option value="processing" <?php echo $order['status'] === 'processing' ? 'selected' : ''; ?>>En cours</option>
                                                        <option value="completed" <?php echo $order['status'] === 'completed' ? 'selected' : ''; ?>>Complétée</option>
                                                        <option value="delivered" <?php echo $order['status'] === 'delivered' ? 'selected' : ''; ?>>Livrée</option>
                                                        <option value="cancelled" <?php echo $order['status'] === 'cancelled' ? 'selected' : ''; ?>>Annulée</option>
                                                    </select>
                                                </form>
                                                
                                                <!-- Bouton Notification -->
                                                <button onclick="openNotificationModal(<?php echo $order['id']; ?>, '<?php echo htmlspecialchars($order['username']); ?>')" 
                                                        class="bg-blue-600 hover:bg-blue-700 text-white px-2 py-1 rounded text-sm" 
                                                        title="Envoyer une notification">
                                                    <i class="fas fa-bell"></i>
                                                </button>
                                                
                                                <?php if($order['status'] === 'pending' || $order['status'] === 'processing'): ?>
                                                <form method="POST" class="inline-block">
                                                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                    <button type="submit" name="unclaim_order" class="bg-red-600 hover:bg-red-700 text-white px-2 py-1 rounded text-sm" onclick="return confirm('Libérer cette commande ?')">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                </form>
                                                <?php endif; ?>
                                                
                                                <!-- Bouton Valider pour commandes complétées -->
                                                <?php if($order['status'] === 'completed'): ?>
                                                <form method="POST" class="inline-block">
                                                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                    <button type="submit" name="validate_order" class="bg-purple-600 hover:bg-purple-700 text-white px-2 py-1 rounded text-sm" onclick="return confirm('Valider et clôturer la commande ?')">
                                                        <i class="fas fa-check"></i> Clôturer
                                                    </button>
                                                </form>
                                                <?php endif; ?>
                                                <?php else: ?>
                                                <span class="text-gray-500 text-sm">Clôturée</span>
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-gray-500 text-sm">Assignée</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                
                                <?php if(empty($orders)): ?>
                                <tr>
                                    <td colspan="9" class="px-2 sm:px-4 py-6 sm:py-8 text-center text-gray-500">
                                        <i class="fas fa-inbox text-2xl sm:text-4xl mb-2 opacity-20"></i>
                                        <p class="text-xs sm:text-base">Aucune commande pour le moment</p>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Modal de notification -->
    <div id="notificationModal" class="modal p-2 sm:p-0">
        <div class="bg-gray-800 rounded-xl p-2 sm:p-6 max-w-full sm:max-w-md w-full mx-2 sm:mx-4 border border-gray-700">
            <div class="flex justify-between items-center mb-2 sm:mb-4">
                <h3 class="text-lg sm:text-2xl font-bold">
                    <i class="fas fa-bell text-blue-500 mr-2"></i>
                    Envoyer une notification
                </h3>
                <button onclick="closeNotificationModal()" class="text-gray-400 hover:text-white text-lg sm:text-xl">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form method="POST" id="notificationForm" class="space-y-2 sm:space-y-4 text-xs sm:text-base">
                <input type="hidden" name="order_id" id="modalOrderId">
                
                <div>
                    <label class="block text-xs sm:text-sm font-bold mb-1 sm:mb-2">Client</label>
                    <div class="bg-gray-900 px-2 sm:px-3 py-2 rounded text-gray-400" id="modalUsername"></div>
                </div>
                
                <div>
                    <label class="block text-xs sm:text-sm font-bold mb-1 sm:mb-2">Titre (optionnel)</label>
                    <input type="text" name="notification_title" 
                           class="w-full bg-gray-700 border border-gray-600 rounded px-2 sm:px-3 py-2 focus:outline-none focus:border-blue-500"
                           placeholder="Ex: Mise à jour importante">
                </div>
                
                <div>
                    <label class="block text-xs sm:text-sm font-bold mb-1 sm:mb-2">Message *</label>
                    <textarea name="notification_message" required rows="4"
                              class="w-full bg-gray-700 border border-gray-600 rounded px-2 sm:px-3 py-2 focus:outline-none focus:border-blue-500"
                              placeholder="Votre message au client..."></textarea>
                </div>
                
                <div class="flex flex-col sm:flex-row gap-2 sm:gap-3">
                    <button type="submit" name="send_notification" 
                            class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 sm:py-3 px-2 sm:px-6 rounded-lg transition-all text-xs sm:text-base">
                        <i class="fas fa-paper-plane mr-2"></i>
                        Envoyer
                    </button>
                    <button type="button" onclick="closeNotificationModal()" 
                            class="px-2 sm:px-6 py-2 sm:py-3 bg-gray-700 hover:bg-gray-600 rounded-lg transition-all text-xs sm:text-base">
                        Annuler
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function filterOrders(status) {
        const rows = document.querySelectorAll('.order-row');
        
        rows.forEach(row => {
            if(status === 'all') {
                row.style.display = '';
            } else if(status === 'my') {
                row.style.display = row.dataset.seller === 'my' ? '' : 'none';
            } else if(row.dataset.status === status) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }
    
    function openNotificationModal(orderId, username) {
        document.getElementById('modalOrderId').value = orderId;
        document.getElementById('modalUsername').textContent = username;
        document.getElementById('notificationModal').classList.add('active');
        document.querySelector('textarea[name="notification_message"]').value = '';
        document.querySelector('input[name="notification_title"]').value = '';
    }
    
    function closeNotificationModal() {
        document.getElementById('notificationModal').classList.remove('active');
    }
    
    // Fermer la modal en cliquant en dehors
    document.getElementById('notificationModal').addEventListener('click', function(e) {
        if(e.target === this) {
            closeNotificationModal();
        }
    });
    </script>
</body>
</html>
