<?php require_once 'config.php'; ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catalogue - CrazySouls Shop</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="bg-gray-900 min-h-screen">
    <?php include 'includes/header.php'; ?>
    <main class="container mx-auto px-4 py-8">
        <div class="mb-8 text-center">
            <h1 class="text-5xl font-bold mb-4 bg-gradient-to-r from-purple-400 to-pink-600 bg-clip-text text-transparent flex items-center justify-center">
                <i class="fas fa-store mr-3 text-purple-500"></i>Catalogue Complet
            </h1>
            <p class="text-gray-400 text-lg">Découvrez tous nos items Paladium disponibles pour Bedrock & Java</p>
        </div>
        <!-- Filtres -->
        <div class="bg-gray-800 rounded-xl p-6 mb-8 shadow-2xl border border-gray-700">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-xl font-bold text-white flex items-center">
                    <i class="fas fa-filter mr-3 text-purple-400"></i>
                    Filtres de recherche
                </h2>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-5">
                <div>
                    <label class="block text-sm font-semibold text-gray-300 flex items-center mb-2">
                        <i class="fas fa-folder mr-2 text-purple-400"></i>Catégorie
                    </label>
                    <select id="categoryFilter" class="w-full bg-gray-900 border border-gray-700 rounded-lg px-4 py-2.5 text-white">
                        <option value="">Toutes</option>
                        <?php
                        $categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();
                        foreach($categories as $cat):
                        ?>
                        <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-300 flex items-center mb-2">
                        <i class="fas fa-star mr-2 text-yellow-400"></i>Rareté
                    </label>
                    <select id="rarityFilter" class="w-full bg-gray-900 border border-gray-700 rounded-lg px-4 py-2.5 text-white">
                        <option value="">Toutes</option>
                        <option value="common">⚪ Commun</option>
                        <option value="rare">🔵 Rare</option>
                        <option value="epic">🟣 Épique</option>
                        <option value="legendary">🟠 Légendaire</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-300 flex items-center mb-2">
                        <i class="fas fa-gamepad mr-2 text-green-400"></i>Plateforme
                    </label>
                    <select id="platformFilter" class="w-full bg-gray-900 border border-gray-700 rounded-lg px-4 py-2.5 text-white">
                        <option value="">Toutes</option>
                        <option value="bedrock">🟢 Bedrock</option>
                        <option value="java">🟠 Java</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-300 flex items-center mb-2">
                        <i class="fas fa-dollar-sign mr-2 text-green-400"></i>Prix maximum
                    </label>
                    <input type="number" id="priceFilter" placeholder="Ex: 10000"
                        class="w-full bg-gray-900 border border-gray-700 rounded-lg px-4 py-2.5 text-white">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-300 flex items-center mb-2">
                        <i class="fas fa-user mr-2 text-purple-400"></i>Vendeur
                    </label>
                    <select id="sellerFilter" class="w-full bg-gray-900 border border-gray-700 rounded-lg px-4 py-2.5 text-white">
                        <option value="">Tous</option>
                        <?php
                        // Récupère les vendeurs avec les rôles techniques
                        $sellers = $pdo->query("
                            SELECT id, username 
                            FROM users 
                            WHERE role IN ('vendeur_test', 'vendeur_confirme', 'vendeur_senior', 'resp_vendeur', 'fondateur')
                            ORDER BY username
                        ")->fetchAll();
                        foreach($sellers as $seller):
                        ?>
                        <option value="<?php echo $seller['id']; ?>"><?php echo htmlspecialchars($seller['username']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-300 flex items-center mb-2">
                        <i class="fas fa-search mr-2 text-blue-400"></i>Recherche rapide
                    </label>
                    <input type="text" id="searchInput" placeholder="Rechercher..."
                        class="w-full bg-gray-900 border border-gray-700 rounded-lg pl-10 pr-4 py-2.5 text-white">
                </div>
            </div>
            <div class="mt-6 flex flex-col sm:flex-row items-center justify-between gap-4 pt-6 border-t border-gray-700/50">
                <button onclick="resetFilters()" class="px-6 py-2.5 bg-purple-600 hover:bg-purple-700 rounded-lg font-semibold text-white flex items-center">
                    <i class="fas fa-redo mr-2"></i>Réinitialiser
                </button>
            </div>
        </div>
        <!-- Grille produits -->
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-8" id="itemsGrid">
            <?php
            $stmt = $pdo->query("SELECT i.*, c.name as category_name, u.username as seller_name
                                 FROM items i
                                 LEFT JOIN categories c ON i.category_id = c.id
                                 LEFT JOIN users u ON i.seller_id = u.id
                                 WHERE i.stock IS NULL OR i.stock > 0
                                 ORDER BY i.rarity DESC, i.created_at DESC");
            while($item = $stmt->fetch()):
                $isUnlimitedStock = is_null($item['stock']) || $item['stock'] === '';
            ?>
            <div class="flex flex-col bg-gray-800 rounded-xl shadow-lg border border-gray-700 p-4 item-card"
                data-item-id="<?php echo $item['id']; ?>"
                data-category="<?php echo $item['category_id']; ?>"
                data-rarity="<?php echo $item['rarity']; ?>"
                data-platform="<?php echo $item['platform']; ?>"
                data-price="<?php echo $item['price']; ?>"
                data-name="<?php echo strtolower($item['name']); ?>"
                data-stock="<?php echo $isUnlimitedStock ? 'unlimited' : $item['stock']; ?>"
                data-item-image="<?php echo htmlspecialchars($item['image']); ?>"
                data-item-name="<?php echo htmlspecialchars($item['name']); ?>"
                data-seller-id="<?php echo $item['seller_id']; ?>"
            >
                <div class="flex gap-2 mb-2">
                    <?php if($item['price'] < 10): ?>
                        <span class="px-2 py-1 bg-pink-500 text-white text-xs rounded font-bold">Promo</span>
                    <?php endif; ?>
                    <span class="px-2 py-1 text-xs rounded font-bold
                        <?php
                            if($item['rarity'] === 'common') echo 'bg-gray-500 text-white';
                            elseif($item['rarity'] === 'rare') echo 'bg-blue-500 text-white';
                            elseif($item['rarity'] === 'epic') echo 'bg-purple-600 text-white';
                            elseif($item['rarity'] === 'legendary') echo 'bg-orange-500 text-white';
                        ?>">
                        <?php echo strtoupper($item['rarity']); ?>
                    </span>
                    <?php if($item['platform'] === 'bedrock'): ?>
                        <span class="px-2 py-1 bg-green-500 text-white text-xs rounded flex items-center gap-1">
                            <i class="fas fa-gamepad"></i>BEDROCK
                        </span>
                    <?php elseif($item['platform'] === 'java'): ?>
                        <span class="px-2 py-1 bg-orange-600 text-white text-xs rounded flex items-center gap-1">
                            <i class="fas fa-coffee"></i>JAVA
                        </span>
                    <?php endif; ?>
                    <!-- Nom du vendeur à droite pour JAVA et BEDROCK -->
                    <?php if (($item['platform'] === 'java' || $item['platform'] === 'bedrock') && !empty($item['seller_name'])): ?>
                        <span class="ml-auto px-2 py-1 bg-purple-600 text-white text-xs rounded flex items-center gap-1">
                            <i class="fas fa-user"></i><?php echo htmlspecialchars($item['seller_name']); ?>
                        </span>
                    <?php endif; ?>
                </div>
                <div class="flex justify-center items-center mb-3 w-full" style="height:256px;">
                    <img src="images/<?php echo htmlspecialchars($item['image']); ?>"
                         alt="<?php echo htmlspecialchars($item['name']); ?>"
                         class="w-[256px] h-[256px] object-contain rounded mx-auto"
                         style="width:256px;height:256px;"
                         width="256" height="256"
                         decoding="async" loading="lazy"
                         onerror="this.src='images/placeholder.png'">
                </div>
                <div class="flex-1 flex flex-col justify-between">
                    <div>
                        <div class="font-bold text-lg text-white mb-1 truncate" title="<?php echo htmlspecialchars($item['name']); ?>">
                            <?php echo htmlspecialchars($item['name']); ?>
                        </div>
                        <div class="text-sm text-gray-400 mb-2 flex items-center gap-1">
                            <i class="fas fa-tag"></i><?php echo htmlspecialchars($item['category_name']); ?>
                        </div>
                        <div class="text-gray-300 text-sm mb-2">
                            <?php echo htmlspecialchars($item['description']); ?>
                        </div>
                    </div>
                    <div class="flex items-center justify-between mt-2">
                        <span class="text-green-400 font-bold text-lg">
                            <?php echo number_format($item['price'], 2, ',', ' '); ?> $
                        </span>
                        <span class="text-blue-400 text-sm flex items-center gap-1">
                            <i class="fas fa-box"></i>
                            <?php echo $isUnlimitedStock ? 'Illimité' : $item['stock']; ?>
                        </span>
                    </div>
                    <div class="flex items-center gap-2 mt-4">
                        <button onclick="openQuantityModal(<?php echo $item['id']; ?>)"
                            class="bg-purple-600 hover:bg-purple-700 text-white font-bold px-4 py-2 rounded-lg flex items-center gap-2 transition">
                            <i class="fas fa-cart-plus"></i> Ajouter au panier
                        </button>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
        <!-- Message aucun résultat -->
        <div id="noResults" class="hidden text-center py-20">
            <div class="inline-block p-8 bg-gray-800 rounded-2xl">
                <i class="fas fa-search text-7xl text-gray-600 mb-4"></i>
                <p class="text-2xl font-bold text-gray-300 mb-2">Aucun produit trouvé</p>
                <p class="text-gray-500">Essayez de modifier vos filtres de recherche</p>
                <button onclick="resetFilters()" class="mt-4 px-6 py-2 bg-purple-600 hover:bg-purple-700 rounded-lg transition">
                    <i class="fas fa-redo mr-2"></i>Réinitialiser
                </button>
            </div>
        </div>
        <!-- Modale quantité -->
        <div id="quantityModal" class="fixed inset-0 bg-black bg-opacity-70 flex items-center justify-center z-50 hidden">
            <div class="bg-gray-800 rounded-xl shadow-2xl w-full max-w-md mx-auto">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-2xl font-bold text-white flex items-center">
                            <i class="fas fa-cart-plus mr-2 text-purple-500"></i>
                            Ajouter au panier
                        </h3>
                        <button onclick="closeQuantityModal()" class="text-gray-400 hover:text-white transition">
                            <i class="fas fa-times text-2xl"></i>
                        </button>
                    </div>
                    <div class="mb-6">
                        <div class="flex items-center gap-4 p-4 bg-gray-700 rounded-lg border border-gray-600">
                            <div class="flex justify-center items-center w-full" style="height:256px;">
                                <img id="modalItemImage" src="" alt="" class="w-[256px] h-[256px] object-contain rounded mx-auto"
                                     style="width:256px;height:256px;" width="256" height="256"
                                     decoding="async" loading="lazy">
                            </div>
                            <div class="flex-1 flex flex-col justify-center">
                                <!-- Suppression des éléments suivants :
                                <h4 id="modalItemName" class="text-lg font-bold text-white mb-1"></h4>
                                <p id="modalItemPrice" class="text-green-400 font-semibold"></p>
                                <p id="modalItemStock" class="text-sm text-gray-400 mt-1"></p>
                                -->
                            </div>
                        </div>
                    </div>
                    <div class="mb-6">
                        <label class="block text-sm font-semibold text-gray-300 mb-3 flex items-center">
                            <i class="fas fa-sort-numeric-up mr-2"></i>
                            Quantité
                        </label>
                        <div class="flex items-center justify-center gap-4">
                            <button onclick="decreaseQuantity()" class="bg-gray-700 hover:bg-gray-600 text-white rounded-lg px-4 py-2">
                                <i class="fas fa-minus"></i>
                            </button>
                            <input type="number" id="modalQuantity" value="1" min="1"
                                   class="bg-gray-800 border-2 border-purple-500 text-white rounded-lg py-3 px-4 w-20 text-center focus:outline-none focus:border-purple-400"
                                   onchange="validateQuantity()">
                            <button onclick="increaseQuantity()" class="bg-gray-700 hover:bg-gray-600 text-white rounded-lg px-4 py-2">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                    </div>
                    <div class="mb-6 p-4 bg-purple-500/10 border border-purple-500/30 rounded-lg">
                        <div class="flex items-center justify-between text-lg">
                            <span class="text-gray-300">Total :</span>
                            <span id="modalTotal" class="text-2xl font-bold text-green-400"></span>
                        </div>
                    </div>
                    <div class="flex gap-3">
                        <button onclick="closeQuantityModal()" class="flex-1 bg-gray-700 hover:bg-gray-600 text-white font-bold py-3 rounded-lg transition">
                            <i class="fas fa-times mr-2"></i>
                            Annuler
                        </button>
                        <button onclick="confirmAddToCart()" class="flex-1 bg-gradient-to-r from-purple-600 to-purple-700 hover:from-purple-700 hover:to-purple-800 text-white font-bold py-3 rounded-lg transition">
                            <i class="fas fa-check mr-2"></i>
                            Confirmer
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </main>
    <?php include 'includes/footer.php'; ?>
    <script>
        let currentItemId = null;
        let currentItemPrice = 0;
        let currentItemStock = 0;
        let isUnlimitedStock = false;

        function openQuantityModal(itemId) {
            const card = document.querySelector(`[data-item-id="${itemId}"]`);
            if (!card) return;
            currentItemId = itemId;
            currentItemPrice = parseFloat(card.dataset.price);
            isUnlimitedStock = card.dataset.stock === 'unlimited';
            currentItemStock = isUnlimitedStock ? null : parseInt(card.dataset.stock);

            document.getElementById('modalItemImage').src = 'images/' + card.dataset.itemImage;
            document.getElementById('modalQuantity').max = isUnlimitedStock ? '' : currentItemStock;
            updateModalTotal();
            document.getElementById('quantityModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }
        function closeQuantityModal() {
            document.getElementById('quantityModal').classList.add('hidden');
            document.body.style.overflow = '';
            currentItemId = null;
        }
        function increaseQuantity() {
            const input = document.getElementById('modalQuantity');
            const currentValue = parseInt(input.value) || 1;
            if (isUnlimitedStock || currentValue < currentItemStock) {
                input.value = currentValue + 1;
                updateModalTotal();
            }
        }
        function decreaseQuantity() {
            const input = document.getElementById('modalQuantity');
            const currentValue = parseInt(input.value) || 1;
            if (currentValue > 1) {
                input.value = currentValue - 1;
                updateModalTotal();
            }
        }
        function validateQuantity() {
            const input = document.getElementById('modalQuantity');
            let value = parseInt(input.value) || 1;
            if (value < 1) value = 1;
            if (!isUnlimitedStock && value > currentItemStock) value = currentItemStock;
            input.value = value;
            updateModalTotal();
        }
        function updateModalTotal() {
            const quantity = parseInt(document.getElementById('modalQuantity').value) || 1;
            const total = currentItemPrice * quantity;
            document.getElementById('modalTotal').textContent = total.toLocaleString('fr-FR') + ' $';
        }
        function confirmAddToCart() {
            const quantity = parseInt(document.getElementById('modalQuantity').value) || 1;
            const formData = new FormData();
            formData.append('item_id', currentItemId);
            formData.append('quantity', quantity);
            fetch('add_to_cart.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(() => {
                closeQuantityModal();
                // Vous pouvez mettre à jour le panier dynamiquement ici
            })
            .catch(error => {
                console.error('Erreur:', error);
                alert('Une erreur est survenue');
            });
        }
        document.getElementById('quantityModal').addEventListener('click', function(e) {
            if (e.target === this) closeQuantityModal();
        });
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && !document.getElementById('quantityModal').classList.contains('hidden')) {
                closeQuantityModal();
            }
        });
        function filterItems() {
            const category = document.getElementById('categoryFilter').value;
            const rarity = document.getElementById('rarityFilter').value;
            const platform = document.getElementById('platformFilter').value;
            const maxPrice = document.getElementById('priceFilter').value;
            const search = document.getElementById('searchInput').value.toLowerCase();
            const seller = document.getElementById('sellerFilter').value;
            const items = document.querySelectorAll('.item-card');
            let visibleCount = 0;
            let totalStock = 0;
            const categoriesSet = new Set();
            items.forEach(item => {
                const itemCategory = item.dataset.category;
                const itemRarity = item.dataset.rarity;
                const itemPlatform = item.dataset.platform;
                const itemPrice = parseFloat(item.dataset.price);
                const itemName = item.dataset.name;
                const itemSellerId = item.dataset.sellerId;
                const itemSellerName = item.querySelector('.bg-purple-600 .fa-user') 
                    ? item.querySelector('.bg-purple-600 .fa-user').nextSibling.textContent.trim()
                    : '';
                let show = true;
                if(category && itemCategory !== category) show = false;
                if(rarity && itemRarity !== rarity) show = false;
                if(platform && itemPlatform !== platform) show = false;
                if(maxPrice && itemPrice > parseFloat(maxPrice)) show = false;
                if(search && !itemName.includes(search)) show = false;
                if(seller) {
                    if (
                        itemSellerId !== seller &&
                        itemSellerName.toUpperCase() !== seller.toUpperCase()
                    ) show = false;
                }
                item.style.display = show ? 'flex' : 'none';
                if(show) {
                    visibleCount++;
                    if(item.dataset.stock === 'unlimited') {
                        totalStock = 'Illimité';
                    } else if(totalStock !== 'Illimité') {
                        totalStock += parseInt(item.dataset.stock) || 0;
                    }
                    if(itemCategory) categoriesSet.add(itemCategory);
                }
            });
            document.getElementById('noResults').classList.toggle('hidden', visibleCount > 0);
            document.getElementById('countNumber').textContent = visibleCount;
            document.getElementById('stockTotal').textContent = totalStock;
            document.getElementById('categoryCount').textContent = categoriesSet.size;
        }
        function resetFilters() {
            document.getElementById('categoryFilter').value = '';
            document.getElementById('rarityFilter').value = '';
            document.getElementById('platformFilter').value = '';
            document.getElementById('priceFilter').value = '';
            document.getElementById('searchInput').value = '';
            document.getElementById('sellerFilter').value = '';
            filterItems();
        }
        let searchTimeout;
        document.getElementById('searchInput').addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(filterItems, 300);
        });
        document.getElementById('categoryFilter').addEventListener('change', filterItems);
        document.getElementById('rarityFilter').addEventListener('change', filterItems);
        document.getElementById('platformFilter').addEventListener('change', filterItems);
        document.getElementById('priceFilter').addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(filterItems, 500);
        });
        document.getElementById('sellerFilter').addEventListener('change', filterItems);
        window.addEventListener('load', filterItems);
    </script>
</body>
</html>
