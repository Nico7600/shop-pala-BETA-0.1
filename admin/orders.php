<?php
require_once '../config.php';

if(!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['resp_vendeur', 'fondateur'])) {
    header('Location: ../login.php');
    exit;
}

$orders = $pdo->query("SELECT o.*, u.username, u.minecraft_username 
                       FROM orders o 
                       JOIN users u ON o.user_id = u.id 
                       ORDER BY o.created_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Commandes - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="bg-gray-900 text-gray-100">
    <div class="flex flex-col md:flex-row min-h-screen">
        <?php include 'sidebar.php'; ?>

        <main class="flex-1 p-2 sm:p-8 w-full">
            <div class="mb-4 sm:mb-8">
                <h2 class="text-2xl sm:text-3xl font-bold mb-2">Gestion des Commandes</h2>
                <p class="text-gray-400 text-sm sm:text-base">Historique complet des transactions</p>
            </div>

            <div class="bg-gray-800 rounded-xl shadow-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-xs sm:text-base">
                        <thead class="bg-gray-750">
                            <tr>
                                <th class="px-2 sm:px-6 py-2 sm:py-4 text-left font-medium text-gray-400 uppercase">ID</th>
                                <th class="px-2 sm:px-6 py-2 sm:py-4 text-left font-medium text-gray-400 uppercase">Client</th>
                                <th class="px-2 sm:px-6 py-2 sm:py-4 text-left font-medium text-gray-400 uppercase">Pseudo MC</th>
                                <th class="px-2 sm:px-6 py-2 sm:py-4 text-left font-medium text-gray-400 uppercase">Total</th>
                                <th class="px-2 sm:px-6 py-2 sm:py-4 text-left font-medium text-gray-400 uppercase">Statut</th>
                                <th class="px-2 sm:px-6 py-2 sm:py-4 text-left font-medium text-gray-400 uppercase hidden sm:table-cell">Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-700">
                            <?php foreach($orders as $order): ?>
                            <tr class="hover:bg-gray-750 transition">
                                <td class="px-2 sm:px-6 py-2 sm:py-4 font-mono text-purple-400">#<?php echo str_pad($order['id'], 6, '0', STR_PAD_LEFT); ?></td>
                                <td class="px-2 sm:px-6 py-2 sm:py-4 font-medium"><?php echo htmlspecialchars($order['username']); ?></td>
                                <td class="px-2 sm:px-6 py-2 sm:py-4">
                                    <span class="bg-gray-700 px-2 sm:px-3 py-1 rounded-full text-xs sm:text-sm">
                                        <i class="fas fa-gamepad mr-1"></i>
                                        <?php echo htmlspecialchars($order['minecraft_username']); ?>
                                    </span>
                                </td>
                                <td class="px-2 sm:px-6 py-2 sm:py-4 font-bold text-green-500 text-base sm:text-lg">
                                    <?php
                                        $final_total = $order['total'] - ($order['discount_amount'] ?? 0);
                                        echo number_format($final_total, 2);
                                    ?> $
                                </td>
                                <td class="px-2 sm:px-6 py-2 sm:py-4">
                                    <span class="px-2 sm:px-3 py-1 rounded-full text-xs sm:text-sm font-bold <?php 
                                        echo $order['status'] === 'completed' ? 'bg-green-500/20 text-green-500' : 'bg-yellow-500/20 text-yellow-500'; 
                                    ?>">
                                        <i class="fas fa-<?php echo $order['status'] === 'completed' ? 'check-circle' : 'clock'; ?> mr-1"></i>
                                        <?php
                                            if ($order['status'] === 'completed') {
                                                echo 'Terminée';
                                            } elseif ($order['status'] === 'pending') {
                                                echo 'En attente';
                                            } else {
                                                echo ucfirst($order['status']);
                                            }
                                        ?>
                                    </span>
                                </td>
                                <td class="px-2 sm:px-6 py-2 sm:py-4 text-gray-400 hidden sm:table-cell">
                                    <i class="fas fa-calendar mr-2"></i>
                                    <?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
