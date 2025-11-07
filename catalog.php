<?php require_once 'config.php'; ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catalogue - CrazySouls Shop</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="styles.css">
    <link rel="stylesheet" href="css/sidebar.css">
    <link rel="stylesheet" href="css/catalog.css">
</head>
<?php include 'includes/header.php'; ?>
<body class="bg-gray-900 text-gray-100 min-h-screen">
    <div class="flex min-h-screen">
        <main class="flex-1">
            <div id="quantityModal" class="modal-overlay">
                <div class="modal-content">
                    <div class="p-6">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-2xl font-bold text-white">
                                <i class="fas fa-cart-plus mr-2 text-purple-500"></i>
                                Ajouter au panier
                            </h3>
                            <button onclick="closeQuantityModal()" class="text-gray-400 hover:text-white transition">
                                <i class="fas fa-times text-2xl"></i>
                            </button>
                        </div>
                        
                        <div class="mb-6">
                            <div class="flex items-center gap-4 p-4 bg-gray-800 rounded-lg border border-gray-700">
                                <img id="modalItemImage" src="" alt="" class="w-20 h-20 object-contain rounded">
                                <div class="flex-1">
                                    <h4 id="modalItemName" class="text-lg font-bold text-white mb-1"></h4>
                                    <p id="modalItemPrice" class="text-green-400 font-semibold"></p>
                                    <p id="modalItemStock" class="text-sm text-gray-400 mt-1"></p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-6">
                            <label class="block text-sm font-semibold text-gray-300 mb-3">
                                <i class="fas fa-sort-numeric-up mr-2"></i>
                                Quantité
                            </label>
                            <div class="flex items-center justify-center gap-4">
                                <button onclick="decreaseQuantity()" class="quantity-btn bg-gray-700 hover:bg-gray-600 text-white rounded-lg">
                                    <i class="fas fa-minus"></i>
                                </button>
                                <input type="number" id="modalQuantity" value="1" min="1" 
                                       class="quantity-input bg-gray-800 border-2 border-purple-500 text-white rounded-lg py-3 focus:outline-none focus:border-purple-400"
                                       onchange="validateQuantity()">
                                <button onclick="increaseQuantity()" class="quantity-btn bg-gray-700 hover:bg-gray-600 text-white rounded-lg">
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
            
            <div class="container mx-auto px-4 py-8">
                <div class="mb-8 text-center">
                    <h1 class="text-5xl font-bold mb-4 pb-2 bg-gradient-to-r from-purple-400 to-pink-600 bg-clip-text text-transparent">
                        <i class="fas fa-store mr-3 text-purple-500"></i>Catalogue Complet
                    </h1>
                    <p class="text-gray-400 text-lg">Découvrez tous nos items Paladium disponibles pour Bedrock & Java</p>
                </div>

                <div class="bg-gray-800 rounded-xl p-6 mb-8 shadow-2xl border border-gray-700">
                    <!-- Titre de la section -->
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-xl font-bold text-white flex items-center">
                            <i class="fas fa-filter mr-3 text-purple-400"></i>
                            Filtres de recherche
                        </h2>
                        <div class="px-4 py-2 bg-purple-500/20 border border-purple-500/30 rounded-full text-sm font-semibold">
                            <i class="fas fa-box mr-2"></i>
                            <span id="countNumber">0</span> produits disponibles
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-5">
                        <div class="space-y-2">
                            <label class="block text-sm font-semibold text-gray-300 flex items-center">
                                <i class="fas fa-folder mr-2 text-purple-400"></i>
                                Catégorie
                            </label>
                            <div class="relative">
                                <select id="categoryFilter" class="w-full bg-gray-900 border border-gray-700 rounded-lg px-4 py-2.5 text-white focus:border-purple-500 focus:outline-none transition appearance-none cursor-pointer">
                                    <option value="">Toutes</option>
                                    <?php
                                    $categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();
                                    foreach($categories as $cat):
                                    ?>
                                    <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <i class="fas fa-chevron-down absolute right-4 top-3.5 text-gray-400 pointer-events-none"></i>
                            </div>
                        </div>
                        
                        <div class="space-y-2">
                            <label class="block text-sm font-semibold text-gray-300 flex items-center">
                                <i class="fas fa-star mr-2 text-yellow-400"></i>
                                Rareté
                            </label>
                            <div class="relative">
                                <select id="rarityFilter" class="w-full bg-gray-900 border border-gray-700 rounded-lg px-4 py-2.5 text-white focus:border-purple-500 focus:outline-none transition appearance-none cursor-pointer">
                                    <option value="">Toutes</option>
                                    <option value="common">⚪ Commun</option>
                                    <option value="rare">🔵 Rare</option>
                                    <option value="epic">🟣 Épique</option>
                                    <option value="legendary">🟠 Légendaire</option>
                                </select>
                                <i class="fas fa-chevron-down absolute right-4 top-3.5 text-gray-400 pointer-events-none"></i>
                            </div>
                        </div>
                        
                        <!-- Filtre Plateforme -->
                        <div class="space-y-2">
                            <label class="block text-sm font-semibold text-gray-300 flex items-center">
                                <i class="fas fa-gamepad mr-2 text-green-400"></i>
                                Plateforme
                            </label>
                            <div class="relative">
                                <select id="platformFilter" class="w-full bg-gray-900 border border-gray-700 rounded-lg px-4 py-2.5 text-white focus:border-purple-500 focus:outline-none transition appearance-none cursor-pointer">
                                    <option value="">Toutes</option>
                                    <option value="bedrock">🟢 Bedrock</option>
                                    <option value="java">🟠 Java</option>
                                </select>
                                <i class="fas fa-chevron-down absolute right-4 top-3.5 text-gray-400 pointer-events-none"></i>
                            </div>
                        </div>
                        
                        <!-- Filtre Prix -->
                        <div class="space-y-2">
                            <label class="block text-sm font-semibold text-gray-300 flex items-center">
                                <i class="fas fa-dollar-sign mr-2 text-green-400"></i>
                                Prix maximum
                            </label>
                            <div class="relative">
                                <input type="number" id="priceFilter" placeholder="Ex: 10000" 
                                    class="w-full bg-gray-900 border border-gray-700 rounded-lg px-4 py-2.5 text-white focus:border-purple-500 focus:outline-none transition placeholder-gray-500">
                            </div>
                        </div>
                        
                        <!-- Barre de recherche -->
                        <div class="space-y-2">
                            <label class="block text-sm font-semibold text-gray-300 flex items-center">
                                <i class="fas fa-search mr-2 text-blue-400"></i>
                                Recherche rapide
                            </label>
                            <div class="relative">
                                <input type="text" id="searchInput" placeholder="Rechercher..." 
                                    class="w-full bg-gray-900 border border-gray-700 rounded-lg pl-10 pr-4 py-2.5 text-white focus:border-purple-500 focus:outline-none transition placeholder-gray-500">
                                <i class="fas fa-search absolute left-3 top-3.5 text-gray-400"></i>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Actions et statistiques -->
                    <div class="mt-6 flex flex-col sm:flex-row items-center justify-between gap-4 pt-6 border-t border-gray-700/50">
                        <div class="flex gap-3">
                            <button onclick="resetFilters()" class="px-6 py-2.5 bg-purple-600 hover:bg-purple-700 rounded-lg font-semibold text-white transition flex items-center">
                                <i class="fas fa-redo mr-2"></i>
                                Réinitialiser
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Grille de produits améliorée - UNE SEULE COLONNE -->
                <div class="flex flex-col gap-6 max-w-4xl mx-auto" id="itemsGrid">
                    <?php
                    $stmt = $pdo->query("SELECT i.*, c.name as category_name FROM items i 
                                        LEFT JOIN categories c ON i.category_id = c.id 
                                        WHERE i.stock > 0
                                        ORDER BY i.rarity DESC, i.created_at DESC");
                    while($item = $stmt->fetch()):
                        $rarityColors = [
                            'common' => 'gray',
                            'rare' => 'blue',
                            'epic' => 'purple',
                            'legendary' => 'orange'
                        ];
                        $color = $rarityColors[$item['rarity']] ?? 'gray';
                    ?>
                    <div class="item-card bg-gray-800 rounded-xl overflow-hidden shadow-lg hover:shadow-2xl transition-all duration-300 border-2 border-<?php echo $color; ?>-500 hover:border-<?php echo $color; ?>-400 transform hover:-translate-y-2" 
                         data-category="<?php echo $item['category_id']; ?>" 
                         data-rarity="<?php echo $item['rarity']; ?>" 
                         data-platform="<?php echo $item['platform']; ?>"
                         data-price="<?php echo $item['price']; ?>" 
                         data-name="<?php echo strtolower($item['name']); ?>"
                         data-stock="<?php echo $item['stock']; ?>"
                         data-item-id="<?php echo $item['id']; ?>"
                         data-item-name="<?php echo htmlspecialchars($item['name']); ?>"
                         data-item-image="<?php echo htmlspecialchars($item['image']); ?>">
                         
                        <div class="flex flex-col md:flex-row">
                            <!-- Image du produit -->
                            <div class="md:w-1/3 h-64 bg-gradient-to-br from-gray-800 to-gray-900 flex items-center justify-center relative overflow-hidden">
                                <div class="absolute inset-0 bg-<?php echo $color; ?>-500 opacity-5"></div>
                                <img src="images/<?php echo htmlspecialchars($item['image']); ?>" 
                                     alt="<?php echo htmlspecialchars($item['name']); ?>"
                                     class="max-w-full max-h-full object-contain z-10 transform hover:scale-110 transition duration-300"
                                     onerror="this.src='images/placeholder.png'">
                            
                                <!-- Badges rareté et plateforme -->
                                <div class="absolute top-3 right-3 flex flex-col gap-2 z-20">
                                    <span class="px-3 py-1 rounded-full text-xs font-bold uppercase bg-<?php echo $color; ?>-500 shadow-lg">
                                        <?php echo $item['rarity']; ?>
                                </span>
                                <?php if($item['platform'] === 'bedrock'): ?>
                                    <span class="platform-badge px-3 py-1 rounded-full text-xs font-bold uppercase bg-green-500 shadow-lg">
                                        <i class="fas fa-gamepad mr-1"></i>Bedrock
                                    </span>
                                <?php elseif($item['platform'] === 'java'): ?>
                                    <span class="platform-badge px-3 py-1 rounded-full text-xs font-bold uppercase bg-orange-500 shadow-lg">
                                        <i class="fas fa-coffee mr-1"></i>Java
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Informations du produit -->
                        <div class="md:w-2/3 p-6 flex flex-col justify-between">
                            <div>
                                <h3 class="text-2xl font-bold mb-2" title="<?php echo htmlspecialchars($item['name']); ?>">
                                    <?php echo htmlspecialchars($item['name']); ?>
                                </h3>
                                <p class="text-sm text-purple-400 mb-4 flex items-center">
                                    <i class="fas fa-tag mr-2"></i><?php echo htmlspecialchars($item['category_name']); ?>
                                </p>
                                <p class="text-gray-400 text-sm mb-4">
                                    <?php echo htmlspecialchars($item['description']); ?>
                                </p>
                            </div>
                            
                            <div class="flex items-center justify-between pt-4 border-t border-gray-700">
                                <div>
                                    <div class="text-xs text-gray-500 mb-1">Prix</div>
                                    <span class="text-3xl font-bold text-green-500">
                                        <?php echo number_format($item['price'], 0, ',', ' '); ?> $
                                    </span>
                                </div>
                                <div class="text-center">
                                    <div class="text-xs text-gray-500 mb-1">Stock</div>
                                    <span class="text-lg font-semibold text-gray-300">
                                        <i class="fas fa-box mr-1 text-blue-400"></i><?php echo $item['stock']; ?>
                                    </span>
                                </div>
                                <button onclick="openQuantityModal(<?php echo $item['id']; ?>)" 
                                    class="bg-gradient-to-r from-purple-600 to-purple-700 hover:from-purple-700 hover:to-purple-800 text-white font-bold px-8 py-3 rounded-lg transition duration-300 transform hover:scale-105 shadow-lg hover:shadow-purple-500/50">
                                    <i class="fas fa-cart-plus mr-2"></i>Ajouter au panier
                                </button>
                            </div>
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
        </main>
    </div>

    <?php include 'includes/footer.php'; ?>

    <script src="script.js"></script>
    <script>
        let currentItemId = null;
        let currentItemPrice = 0;
        let currentItemStock = 0;
        
        function openQuantityModal(itemId) {
            const card = document.querySelector(`[data-item-id="${itemId}"]`);
            if (!card) return;
            
            currentItemId = itemId;
            currentItemPrice = parseFloat(card.dataset.price);
            currentItemStock = parseInt(card.dataset.stock);
            
            document.getElementById('modalItemImage').src = 'images/' + card.dataset.itemImage;
            document.getElementById('modalItemName').textContent = card.dataset.itemName;
            document.getElementById('modalItemPrice').textContent = currentItemPrice.toLocaleString('fr-FR') + ' $';
            document.getElementById('modalItemStock').innerHTML = `<i class="fas fa-box mr-1 text-blue-400"></i>Stock disponible : ${currentItemStock}`;
            document.getElementById('modalQuantity').value = 1;
            document.getElementById('modalQuantity').max = currentItemStock;
            
            updateModalTotal();
            
            document.getElementById('quantityModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        
        function closeQuantityModal() {
            document.getElementById('quantityModal').classList.remove('active');
            document.body.style.overflow = '';
            currentItemId = null;
        }
        
        function increaseQuantity() {
            const input = document.getElementById('modalQuantity');
            const currentValue = parseInt(input.value) || 1;
            if (currentValue < currentItemStock) {
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
            if (value > currentItemStock) value = currentItemStock;
            
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
                window.location.reload();
            })
            .catch(error => {
                console.error('Erreur:', error);
                alert('Une erreur est survenue');
            });
        }
        
        // Fermer la modale en cliquant en dehors
        document.getElementById('quantityModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeQuantityModal();
            }
        });
        
        // Fermer avec la touche Échap
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && document.getElementById('quantityModal').classList.contains('active')) {
                closeQuantityModal();
            }
        });
        
        // Filtrage en temps réel amélioré
        function filterItems() {
            const category = document.getElementById('categoryFilter').value;
            const rarity = document.getElementById('rarityFilter').value;
            const platform = document.getElementById('platformFilter').value;
            const maxPrice = document.getElementById('priceFilter').value;
            const search = document.getElementById('searchInput').value.toLowerCase();
            
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
                
                let show = true;
                
                if(category && itemCategory !== category) show = false;
                if(rarity && itemRarity !== rarity) show = false;
                if(platform && itemPlatform !== platform) show = false;
                if(maxPrice && itemPrice > parseFloat(maxPrice)) show = false;
                if(search && !itemName.includes(search)) show = false;
                
                item.style.display = show ? 'flex' : 'none';
                
                if(show) {
                    visibleCount++;
                    const stockText = item.querySelector('.text-lg.font-semibold.text-gray-300');
                    if(stockText) {
                        const stock = parseInt(stockText.textContent.match(/\d+/)?.[0] || 0);
                        totalStock += stock;
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
            filterItems();
        }
        
        // Event listeners avec debounce pour la recherche
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
        
        // Initialiser le compteur au chargement
        window.addEventListener('load', filterItems);
    </script>
</body>
</html>
