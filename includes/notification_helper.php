<?php
/**
 * Crée une notification pour un utilisateur
 */
function createNotification($pdo, $user_id, $type, $title, $message, $link = null) {
    try {
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, type, title, message, link) VALUES (?, ?, ?, ?, ?)");
        return $stmt->execute([$user_id, $type, $title, $message, $link]);
    } catch(PDOException $e) {
        error_log("Erreur création notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Notification de bienvenue
 */
function notifyWelcome($pdo, $user_id, $username) {
    return createNotification(
        $pdo,
        $user_id,
        'welcome',
        'Bienvenue sur CrazySouls Shop !',
        "Bonjour {$username} ! Merci de vous être inscrit. Découvrez nos items premium et commencez votre aventure !",
        'catalog.php'
    );
}

/**
 * Notification d'achat
 */
function notifyPurchase($pdo, $user_id, $order_id, $total) {
    return createNotification(
        $pdo,
        $user_id,
        'purchase',
        'Commande confirmée',
        "Votre commande #{$order_id} d'un montant de " . number_format($total, 2) . "€ a été validée avec succès !",
        'orders.php'
    );
}

/**
 * Notification de nouveau produit
 */
function notifyNewProduct($pdo, $user_id, $product_name) {
    return createNotification(
        $pdo,
        $user_id,
        'new_product',
        'Nouveau produit disponible !',
        "Le produit '{$product_name}' vient d'être ajouté au catalogue. Ne manquez pas cette opportunité !",
        'catalog.php'
    );
}

/**
 * Notification admin
 */
function notifyAdmin($pdo, $user_id, $title, $message, $link = null) {
    return createNotification(
        $pdo,
        $user_id,
        'admin',
        $title,
        $message,
        $link
    );
}

/**
 * Marquer une notification comme lue
 */
function markNotificationAsRead($pdo, $notification_id, $user_id) {
    try {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = TRUE WHERE id = ? AND user_id = ?");
        return $stmt->execute([$notification_id, $user_id]);
    } catch(PDOException $e) {
        error_log("Erreur marquage notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Marquer toutes les notifications comme lues
 */
function markAllNotificationsAsRead($pdo, $user_id) {
    try {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = TRUE WHERE user_id = ? AND is_read = FALSE");
        return $stmt->execute([$user_id]);
    } catch(PDOException $e) {
        error_log("Erreur marquage notifications: " . $e->getMessage());
        return false;
    }
}

/**
 * Supprimer une notification
 */
function deleteNotification($pdo, $notification_id, $user_id) {
    try {
        $stmt = $pdo->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
        return $stmt->execute([$notification_id, $user_id]);
    } catch(PDOException $e) {
        error_log("Erreur suppression notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Compter les notifications non lues
 */
function countUnreadNotifications($pdo, $user_id) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = FALSE");
        $stmt->execute([$user_id]);
        return $stmt->fetchColumn();
    } catch(PDOException $e) {
        error_log("Erreur comptage notifications: " . $e->getMessage());
        return 0;
    }
}

/**
 * Récupérer les notifications d'un utilisateur
 */
function getUserNotifications($pdo, $user_id, $limit = 50) {
    $stmt = $pdo->prepare("
        SELECT * FROM notifications 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT " . intval($limit)
    );
    $stmt->execute([$user_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Notifier tous les utilisateurs (pour les admins)
 */
function notifyAllUsers($pdo, $type, $title, $message, $link = null, $exclude_user_id = null) {
    try {
        $query = "SELECT id FROM users WHERE role != 'admin'";
        if ($exclude_user_id) {
            $query .= " AND id != ?";
            $stmt = $pdo->prepare($query);
            $stmt->execute([$exclude_user_id]);
        } else {
            $stmt = $pdo->query($query);
        }
        
        $users = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $success = 0;
        
        foreach ($users as $user_id) {
            if (createNotification($pdo, $user_id, $type, $title, $message, $link)) {
                $success++;
            }
        }
        
        return $success;
    } catch(PDOException $e) {
        error_log("Erreur notification globale: " . $e->getMessage());
        return 0;
    }
}
?>
