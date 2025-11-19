<?php
session_start();
require_once 'config.php';

if(!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$cart_items = [];
$total = 0;

// Récupérer les articles du panier depuis la table cart avec jointure sur items
try {
    $stmt = $pdo->prepare("
        SELECT c.id as cart_id, c.quantity, i.* 
        FROM cart c
        INNER JOIN items i ON c.item_id = i.id
        WHERE c.user_id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach($products as $product) {
        $subtotal = $product['price'] * $product['quantity'];
        $total += $subtotal;
        
        $cart_items[] = [
            'cart_id' => $product['cart_id'],
            'product' => $product,
            'quantity' => $product['quantity'],
            'subtotal' => $subtotal
        ];
    }
} catch(PDOException $e) {
    error_log("Erreur panier : " . $e->getMessage());
    $_SESSION['error'] = "Erreur lors du chargement du panier : " . $e->getMessage();
}

// Correction : chaque réduction est calculée sur le total de base
$discount_amount = 0;
if (isset($_SESSION['discount_amount'])) {
    $discount_amount = floatval($_SESSION['discount_amount']);
}

$permission_discount = 0;
$abofac_type = '';
$abofac_label = '';
try {
    $stmt_perm = $pdo->prepare("SELECT permissions, type FROM abofac WHERE user_id = ?");
    $stmt_perm->execute([$_SESSION['user_id']]);
    $perm_row = $stmt_perm->fetch(PDO::FETCH_ASSOC);
    if ($perm_row) {
        $abofac_type = $perm_row['type'];
        if (in_array($abofac_type, ['Faction', 'Mini Faction', 'Grosse Faction'])) {
            $permission_discount = round($total * 0.10, 2); // 10% sur le total de base
            $abofac_label = 'Réduction Faction (10%)';
        } elseif ($abofac_type === 'Individuel') {
            $permission_discount = round($total * 0.05, 2); // 5% sur le total de base
            $abofac_label = 'Réduction Individuel (5%)';
        }
    }
} catch(PDOException $e) {
    error_log("Erreur permissions : " . $e->getMessage());
}

// Calculer le total des réductions sur la valeur de base
$total_discount = 0;
if ($discount_amount > 0) {
    $total_discount += $discount_amount;
}
if ($permission_discount > 0) {
    $total_discount += $permission_discount;
}
$final_total = $total - $total_discount;

try {
    // Récupérer le nom d'utilisateur
    $stmt_user = $pdo->prepare("SELECT username FROM users WHERE id = ?");
    $stmt_user->execute([$_SESSION['user_id']]);
    $user_row = $stmt_user->fetch(PDO::FETCH_ASSOC);
    if ($user_row) {
        $username = $user_row['username'];
    }
} catch(PDOException $e) {
    error_log("Erreur utilisateur : " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon Panier - CrazySouls Shop</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="bg-gray-900 text-gray-100 flex flex-col min-h-screen">
    <?php include 'includes/header.php'; ?>

    <div class="container mx-auto px-4 py-8 flex-grow">
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-4xl font-bold flex items-center">
                    <i class="fas fa-shopping-cart text-green-500 mr-3"></i>
                    Mon Panier
                </h1>
                <?php if($username): ?>
                <p class="text-gray-400 mt-2 text-lg">
                    <i class="fas fa-user mr-1"></i>
                    Utilisateur : <span class="font-semibold text-white"><?php echo htmlspecialchars($username); ?></span>
                </p>
                <?php endif; ?>
            </div>
            <a href="orders.php" class="bg-gray-700 hover:bg-gray-600 text-white font-bold py-3 px-6 rounded-lg transition-all">
                <i class="fas fa-box mr-2"></i>
                Suivis de commande
            </a>
        </div>

        <?php if(isset($_SESSION['success'])): ?>
            <div class="bg-green-500/20 border border-green-500 text-green-400 px-4 py-3 rounded mb-6 flex items-center gap-3">
                <i class="fas fa-check-circle text-xl"></i>
                <span><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></span>
            </div>
        <?php endif; ?>

        <?php if(isset($_SESSION['error'])): ?>
            <div class="bg-red-500/20 border border-red-500 text-red-400 px-4 py-3 rounded mb-6 flex items-center gap-3">
                <i class="fas fa-exclamation-circle text-xl"></i>
                <span><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></span>
            </div>
        <?php endif; ?>

        <?php if(empty($cart_items)): ?>
            <div class="bg-gray-800 rounded-xl p-12 text-center">
                <i class="fas fa-shopping-cart text-6xl text-gray-600 mb-4"></i>
                <h2 class="text-2xl font-bold mb-2">Votre panier est vide</h2>
                <p class="text-gray-400 mb-6">Ajoutez des produits pour commencer vos achats</p>
                <a href="index.php" class="inline-block bg-purple-600 hover:bg-purple-700 px-6 py-3 rounded-lg font-bold transition">
                    <i class="fas fa-shopping-bag mr-2"></i>
                    Voir les produits
                </a>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <div class="lg:col-span-2">
                    <div class="bg-gray-800 rounded-xl overflow-hidden">
                        <?php foreach($cart_items as $item): ?>
                            <div class="border-b border-gray-700 p-6 flex items-center gap-4">
                                <img src="images/<?php echo htmlspecialchars($item['product']['image']); ?>" 
                                     alt="<?php echo htmlspecialchars($item['product']['name']); ?>" 
                                     class="w-24 h-24 object-cover rounded-lg">
                                <div class="flex-1">
                                    <h3 class="text-xl font-bold mb-1"><?php echo htmlspecialchars($item['product']['name']); ?></h3>
                                    <p class="text-gray-400 text-sm mb-2"><?php echo htmlspecialchars(substr($item['product']['description'], 0, 80)); ?>...</p>
                                    <p class="text-green-400 font-bold"><?php echo number_format($item['product']['price'], 2); ?>$</p>
                                    <p class="text-xs text-gray-500 mt-1">
                                        <i class="fas fa-box mr-1"></i>
                                        Stock: <?php echo $item['product']['stock']; ?>
                                    </p>
                                </div>
                                <div class="text-center">
                                    <p class="text-sm text-gray-400 mb-2">Quantité</p>
                                    <p class="text-2xl font-bold"><?php echo $item['quantity']; ?></p>
                                    <?php if($item['quantity'] > $item['product']['stock']): ?>
                                    <p class="text-xs text-red-400 mt-1">
                                        <i class="fas fa-exclamation-triangle"></i> Stock insuffisant
                                    </p>
                                    <?php endif; ?>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm text-gray-400 mb-2">Sous-total</p>
                                    <p class="text-2xl font-bold text-purple-400"><?php echo number_format($item['subtotal'], 2); ?>$</p>
                                </div>
                                <form method="POST" action="remove_from_cart.php" class="ml-4">
                                    <input type="hidden" name="cart_id" value="<?php echo $item['cart_id']; ?>">
                                    <button type="submit" class="text-red-500 hover:text-red-400 transition">
                                        <i class="fas fa-trash text-xl"></i>
                                    </button>
                                </form>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div>
                    <div class="bg-gray-800 rounded-xl p-6 sticky top-4">
                        <h2 class="text-2xl font-bold mb-6">Récapitulatif</h2>
                        <div class="space-y-4 mb-6">
                            <div class="flex justify-between text-gray-400">
                                <span>Sous-total</span>
                                <span id="subtotal"><?php echo number_format($total, 2); ?>$</span>
                            </div>

                            <!-- Ajout du formulaire code promo -->
                            <form id="couponForm" class="flex gap-2 mb-2">
                                <input type="text" id="coupon_code" name="coupon_code" placeholder="Code promo" class="bg-gray-700 text-white px-3 py-2 rounded-lg flex-1" autocomplete="off">
                                <button type="submit" class="bg-purple-600 hover:bg-purple-700 text-white font-bold px-4 py-2 rounded-lg">Appliquer</button>
                            </form>
                            <div id="coupon_message" class="text-sm mt-1"></div>

                            <!-- Réduction code promo (remis avant total des réductions) -->
                            <?php if($discount_amount > 0): ?>
                            <div class="flex justify-between text-green-400" id="discount_row">
                                <span><i class="fas fa-tag mr-1"></i>Réduction code promo</span>
                                <span id="discount_amount">-<?php echo number_format($discount_amount, 2); ?>$</span>
                            </div>
                            <?php else: ?>
                            <div class="flex justify-between text-green-400 hidden" id="discount_row">
                                <span><i class="fas fa-tag mr-1"></i>Réduction code promo</span>
                                <span id="discount_amount"></span>
                            </div>
                            <?php endif; ?>

                            <!-- Réduction permissions Actif -->
                            <?php if($permission_discount > 0): ?>
                            <div class="flex justify-between text-blue-400" id="permission_discount_row">
                                <span>
                                    <i class="fas fa-user-shield mr-1"></i>
                                    <?php echo htmlspecialchars($abofac_label); ?>
                                    <?php if($abofac_type): ?>
                                        <span class="text-xs text-gray-400 ml-2">(<?php echo htmlspecialchars($abofac_type); ?>)</span>
                                    <?php endif; ?>
                                </span>
                                <span id="permission_discount_amount">-<?php echo number_format($permission_discount, 2); ?>$</span>
                            </div>
                            <?php endif; ?>

                            <!-- Total des réductions (somme des deux) -->
                            <?php if($total_discount > 0): ?>
                            <div class="flex justify-between text-yellow-400 font-bold border-t border-gray-700 pt-4">
                                <span>Total des réductions</span>
                                <span class="total_discount_span">-<?php echo number_format($total_discount, 2); ?>$</span>
                            </div>
                            <?php else: ?>
                            <div class="flex justify-between text-yellow-400 font-bold border-t border-gray-700 pt-4" style="display:none;">
                                <span>Total des réductions</span>
                                <span class="total_discount_span"></span>
                            </div>
                            <?php endif; ?>

                            <div class="border-t border-gray-700 pt-4 flex justify-between text-2xl font-bold">
                                <span>Total</span>
                                <span class="text-green-400" id="final_total"><?php echo number_format($final_total, 2); ?>$</span>
                            </div>
                        </div>
                        <!-- Formulaire pour passer la commande -->
                        <form method="POST" action="process_order.php" onsubmit="return confirm('Confirmer la commande ?')">
                            <!-- Champs cachés pour les réductions -->
                            <input type="hidden" name="discount_amount" value="<?php echo $discount_amount; ?>">
                            <input type="hidden" name="permission_discount" value="<?php echo $permission_discount; ?>">
                            <input type="hidden" name="total_discount" value="<?php echo $total_discount; ?>">
                            <input type="hidden" name="final_total" value="<?php echo $final_total; ?>">
                            <?php foreach($cart_items as $item): ?>
                                <input type="hidden" name="cart_id[]" value="<?php echo $item['cart_id']; ?>">
                                <input type="hidden" name="item_ids[]" value="<?php echo $item['product']['id']; ?>">
                                <input type="hidden" name="item_names[]" value="<?php echo htmlspecialchars($item['product']['name']); ?>">
                                <input type="hidden" name="item_quantities[]" value="<?php echo $item['quantity']; ?>">
                                <input type="hidden" name="item_prices[]" value="<?php echo $item['product']['price']; ?>">
                            <?php endforeach; ?>
                            <button type="submit" class="w-full bg-gradient-to-r from-green-600 to-teal-600 hover:from-green-700 hover:to-teal-700 text-white font-bold py-4 px-6 rounded-lg transition-all transform hover:scale-105 shadow-lg">
                                <i class="fas fa-check-circle mr-2"></i>
                                Passer la commande
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php include 'includes/footer.php'; ?>
</body>
</html>

<!-- Ajout du script JS pour gérer le code promo -->
<script>
function updateTotalDiscount() {
    // Utilise la valeur absolue pour le calcul
    let discount = Math.abs(parseFloat(document.getElementById('discount_amount')?.textContent?.replace(/[^\d.-]/g, '') || 0));
    let permissionDiscount = Math.abs(parseFloat(document.getElementById('permission_discount_amount')?.textContent?.replace(/[^\d.-]/g, '') || 0));
    let totalDiscount = (isNaN(discount) ? 0 : discount) + (isNaN(permissionDiscount) ? 0 : permissionDiscount);
    document.querySelectorAll('.total_discount_span').forEach(el => el.textContent = "-"+totalDiscount.toFixed(2)+"$");
    // Affiche la ligne si elle était cachée
    document.querySelectorAll('.total_discount_span').forEach(el => {
        if (el.parentElement.style.display === "none") el.parentElement.style.display = "";
    });
    // Met à jour le total final
    let subtotal = parseFloat(document.getElementById('subtotal').textContent.replace(/[^\d.-]/g, ''));
    let finalTotal = subtotal - totalDiscount;
    document.getElementById('final_total').textContent = finalTotal.toFixed(2)+"$";
    // Met à jour les champs cachés du formulaire commande
    document.querySelector('input[name="total_discount"]').value = totalDiscount;
    document.querySelector('input[name="final_total"]').value = finalTotal;
}

document.getElementById('couponForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const code = document.getElementById('coupon_code').value.trim();
    const msg = document.getElementById('coupon_message');
    msg.textContent = '';
    if (!code) {
        msg.textContent = "Veuillez entrer un code promo.";
        msg.className = "text-red-400";
        return;
    }
    fetch('apply_coupon.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'coupon_code=' + encodeURIComponent(code)
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            document.getElementById('discount_row').classList.remove('hidden');
            document.getElementById('discount_amount').textContent = "-"+parseFloat(data.discount_amount).toFixed(2)+"$";
            document.querySelector('input[name="discount_amount"]').value = data.discount_amount;
            msg.textContent = data.message || "Code promo appliqué !";
            msg.className = "text-green-400";
            updateTotalDiscount();
        } else {
            msg.textContent = data.message || "Code promo invalide.";
            msg.className = "text-red-400";
        }
    })
    .catch(() => {
        msg.textContent = "Erreur lors de l'application du code.";
        msg.className = "text-red-400";
    });
});

// Met à jour le total des réductions au chargement (pour permission + code promo déjà présents)
window.addEventListener('DOMContentLoaded', updateTotalDiscount);
</script>
