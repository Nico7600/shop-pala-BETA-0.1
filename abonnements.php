<?php
session_start();
require_once 'config.php';

$user_id = $_SESSION['user_id'] ?? null;
$has_abonnement = false;
$can_rebuy = false;
$lastAboType = null;
if ($user_id) {
    // Vérifie le dernier abonnement de l'utilisateur
    $stmt = $pdo->prepare("SELECT id, type, permissions FROM abofac WHERE user_id=? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$user_id]);
    $lastAbo = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($lastAbo) {
        $lastAboType = $lastAbo['type'];
        if ($lastAbo['permissions'] === 'Annulé' || $lastAbo['permissions'] === 'Inactif') {
            $can_rebuy = true;
            $has_abonnement = false;
        } else {
            $has_abonnement = true;
        }
    }
}

// Gestion de la notification et PRG
$showNotif = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['type_abonnement'])) {
    $type = $_POST['type_abonnement'];
    $duree = $_POST['duree'];
    $prix = $_POST['prix'];
    if ($user_id) {
        // Vérifie le dernier abonnement
        $stmt = $pdo->prepare("SELECT id, permissions FROM abofac WHERE user_id=? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$user_id]);
        $lastAbo = $stmt->fetch(PDO::FETCH_ASSOC);

        $can_rebuy = false;
        $has_abonnement = false;
        if ($lastAbo) {
            if ($lastAbo['permissions'] === 'Annulé' || $lastAbo['permissions'] === 'Inactif') {
                $can_rebuy = true;
                $has_abonnement = false;
            } else {
                $has_abonnement = true;
            }
        }

        if (!$has_abonnement || $can_rebuy) {
            // Crée un nouvel abonnement
            $stmt = $pdo->prepare("INSERT INTO abofac (user_id, type, duree, prix, date_debut, permissions) VALUES (?, ?, ?, ?, NOW(), ?)");
            $stmt->execute([$user_id, $type, $duree, $prix, 'En attente de paiement']);
            $lastId = $pdo->lastInsertId();
            $pdo->prepare("UPDATE users SET abonnement_id=? WHERE id=?")->execute([$lastId, $user_id]);
            header("Location: abonnements.php?abo_success=1");
            exit;
        } else {
            header("Location: abonnements.php?abo_error=1");
            exit;
        }
    } else {
        header("Location: abonnements.php?abo_error=1");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catalogue - CrazySouls Shop</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="bg-gray-900 text-gray-100 min-h-screen">
    <?php include 'includes/header.php'; ?>
    <div class="mt-8"></div>
    <?php if (isset($_GET['abo_success'])): ?>
        <div id="notif-abo" class="bg-green-700 text-white p-6 rounded mb-8 mx-auto max-w-3xl text-center shadow-lg flex flex-col items-center gap-2">
            <span class="text-3xl mb-2"><i class="fas fa-check-circle"></i></span>
            <strong>Abonnement enregistré !</strong><br>
            Demande envoyée, en attente de confirmation des staff.<br>
            <span class='font-bold'>Merci de MP Nico7600 sur discord</span>
        </div>
    <?php elseif (isset($_GET['abo_error'])): ?>
        <div id="notif-abo" class="bg-red-700 text-white p-6 rounded mb-8 mx-auto max-w-3xl text-center shadow-lg flex flex-col items-center gap-2">
            <span class="text-3xl mb-2"><i class="fas fa-exclamation-circle"></i></span>
            Connectez-vous pour vous abonner.
        </div>
    <?php endif; ?>
    <div class="container mx-auto px-4 py-10">
        <div class="max-w-7xl mx-auto bg-gray-900/90 rounded-2xl shadow-2xl border-2 border-purple-700 p-10">
            <h2 class="text-5xl font-extrabold bg-gradient-to-r from-purple-400 to-pink-400 bg-clip-text text-transparent mb-12 text-center drop-shadow-lg tracking-tight">Abonnements CrazySouls</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-10">
                <!-- Offre Individuelle -->
                <div class="bg-gray-800/90 rounded-xl shadow-xl border-2 border-purple-500 p-8 flex flex-col items-center hover:scale-105 hover:border-purple-400 transition-transform duration-200">
                    <div class="text-5xl mb-2">
                        <i class="fas fa-user text-purple-400"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-purple-400 mb-1">Individuel</h3>
                    <div class="text-sm text-purple-300 font-bold mb-2">1 joueur</div>
                    <p class="text-gray-300 mb-4 text-center">Pour les joueurs solo qui veulent des avantages premium.</p>
                    <form method="POST">
                        <input type="hidden" name="type_abonnement" value="Individuel">
                        <label class="block text-sm text-gray-300 mb-2 font-semibold">Durée & Prix :</label>
                        <select name="duree" class="w-full mb-4 px-3 py-2 rounded bg-gray-900 text-gray-100 border border-purple-500 focus:ring-2 focus:ring-purple-400 font-semibold" id="prix-indiv" onchange="updatePrixIndiv()">
                            <option value="1 mois" data-prix="10000">1 mois - 10 000 $</option>
                            <option value="3 mois" data-prix="27000">3 mois - 27 000 $</option>
                            <option value="6 mois" data-prix="48000">6 mois - 48 000 $</option>
                        </select>
                        <input type="hidden" name="prix" id="prix-indiv-hidden" value="10000">
                        <div class="mb-2 text-sm text-purple-400 font-bold">Réduction shop : <span class="bg-purple-700/30 px-2 py-1 rounded">5 %</span></div>
                        <button type="submit"
                            class="bg-gradient-to-r from-purple-600 to-pink-600 text-white font-bold px-6 py-2 rounded-lg shadow transition
                            <?php if($has_abonnement && !($can_rebuy && $lastAboType === 'Individuel')): ?> opacity-60 cursor-not-allowed bg-gray-600 from-gray-600 to-gray-600 <?php endif; ?>"
                            <?php if($has_abonnement && !($can_rebuy && $lastAboType === 'Individuel')): ?>disabled<?php endif; ?>>
                            <?php if($has_abonnement && !($can_rebuy && $lastAboType === 'Individuel')): ?>
                                Abonnement déjà actif
                            <?php elseif($can_rebuy && $lastAboType === 'Individuel'): ?>
                                Racheter le grade
                            <?php else: ?>
                                Acheter
                            <?php endif; ?>
                        </button>
                    </form>
                    <ul class="mt-6 text-base text-gray-200 list-disc pl-5 text-left space-y-1">
                        <li>5 % de réduction sur toutes les commandes du shop</li>
                        <li>Commandes traitées plus rapidement</li>
                    </ul>
                </div>
                <!-- Mini Faction -->
                <div class="bg-gray-800/90 rounded-xl shadow-xl border-2 border-pink-500 p-8 flex flex-col items-center hover:scale-105 hover:border-pink-400 transition-transform duration-200">
                    <div class="text-5xl mb-2">
                        <i class="fas fa-gem text-pink-400"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-pink-400 mb-1">Mini Faction</h3>
                    <div class="text-sm text-pink-300 font-bold mb-2">2-10 joueurs</div>
                    <p class="text-gray-300 mb-4 text-center">Pour des joueurs fixes, non modifiables pendant la durée de l'abonnement.</p>
                    <form method="POST">
                        <input type="hidden" name="type_abonnement" value="Mini Faction">
                        <label class="block text-sm text-gray-300 mb-2 font-semibold">Durée & Prix :</label>
                        <select name="duree" class="w-full mb-4 px-3 py-2 rounded bg-gray-900 text-gray-100 border border-pink-500 focus:ring-2 focus:ring-pink-400 font-semibold" id="prix-minifaction" onchange="updatePrixMiniFaction()">
                            <option value="1 mois" data-prix="30000">1 mois - 30 000 $</option>
                            <option value="3 mois" data-prix="72000">3 mois - 72 000 $</option>
                            <option value="6 mois" data-prix="128000">6 mois - 128 000 $</option>
                        </select>
                        <input type="hidden" name="prix" id="prix-minifaction-hidden" value="30000">
                        <div class="mb-2 text-sm text-pink-400 font-bold">Réduction shop : <span class="bg-pink-700/30 px-2 py-1 rounded">10 %</span></div>
                        <button type="submit"
                            class="bg-gradient-to-r from-pink-500 to-purple-500 text-white font-bold px-6 py-2 rounded-lg shadow transition
                            <?php if($has_abonnement && !($can_rebuy && $lastAboType === 'Mini Faction')): ?> opacity-60 cursor-not-allowed bg-gray-600 from-gray-600 to-gray-600 <?php endif; ?>"
                            <?php if($has_abonnement && !($can_rebuy && $lastAboType === 'Mini Faction')): ?>disabled<?php endif; ?>>
                            <?php if($has_abonnement && !($can_rebuy && $lastAboType === 'Mini Faction')): ?>
                                Abonnement déjà actif
                            <?php elseif($can_rebuy && $lastAboType === 'Mini Faction'): ?>
                                Racheter le grade
                            <?php else: ?>
                                Acheter
                            <?php endif; ?>
                        </button>
                    </form>
                    <ul class="mt-6 text-base text-gray-200 list-disc pl-5 text-left space-y-1">
                        <li>10 % de réduction sur tous les achats du shop</li>
                        <li>Commandes traitées plus rapidement</li>
                    </ul>
                </div>
                <!-- Faction -->
                <div class="bg-gray-800/90 rounded-xl shadow-xl border-2 border-green-500 p-8 flex flex-col items-center hover:scale-105 hover:border-green-400 transition-transform duration-200">
                    <div class="text-5xl mb-2">
                        <i class="fas fa-shield-alt text-green-400"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-green-400 mb-1">Faction</h3>
                    <div class="text-sm text-green-300 font-bold mb-2">11-25 joueurs</div>
                    <p class="text-gray-300 mb-4 text-center">Pour des joueurs fixes, non modifiables pendant la durée de l'abonnement.</p>
                    <form method="POST">
                        <input type="hidden" name="type_abonnement" value="Faction">
                        <label class="block text-sm text-gray-300 mb-2 font-semibold">Durée & Prix :</label>
                        <select name="duree" class="w-full mb-4 px-3 py-2 rounded bg-gray-900 text-gray-100 border border-green-500 focus:ring-2 focus:ring-green-400 font-semibold" id="prix-faction" onchange="updatePrixFaction()">
                            <option value="1 mois" data-prix="55000">1 mois - 55 000 $</option>
                            <option value="3 mois" data-prix="135000">3 mois - 135 000 $</option>
                            <option value="6 mois" data-prix="240000">6 mois - 240 000 $</option>
                        </select>
                        <input type="hidden" name="prix" id="prix-faction-hidden" value="55000">
                        <div class="mb-2 text-sm text-green-400 font-bold">Réduction shop : <span class="bg-green-700/30 px-2 py-1 rounded">10 %</span></div>
                        <button type="submit"
                            class="bg-gradient-to-r from-green-500 to-purple-500 text-white font-bold px-6 py-2 rounded-lg shadow transition
                            <?php if($has_abonnement && !($can_rebuy && $lastAboType === 'Faction')): ?> opacity-60 cursor-not-allowed bg-gray-600 from-gray-600 to-gray-600 <?php endif; ?>"
                            <?php if($has_abonnement && !($can_rebuy && $lastAboType === 'Faction')): ?>disabled<?php endif; ?>>
                            <?php if($has_abonnement && !($can_rebuy && $lastAboType === 'Faction')): ?>
                                Abonnement déjà actif
                            <?php elseif($can_rebuy && $lastAboType === 'Faction'): ?>
                                Racheter le grade
                            <?php else: ?>
                                Acheter
                            <?php endif; ?>
                        </button>
                    </form>
                    <ul class="mt-6 text-base text-gray-200 list-disc pl-5 text-left space-y-1">
                        <li>10 % de réduction sur tous les achats du shop</li>
                        <li>Commandes traitées en priorité</li>
                    </ul>
                </div>
                <!-- Grosse Faction -->
                <div class="bg-gray-800/90 rounded-xl shadow-xl border-2 border-yellow-500 p-8 flex flex-col items-center hover:scale-105 hover:border-yellow-400 transition-transform duration-200">
                    <div class="text-5xl mb-2">
                        <i class="fas fa-crown text-yellow-400"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-yellow-400 mb-1">Grosse Faction</h3>
                    <div class="text-sm text-yellow-300 font-bold mb-2">26-40 joueurs</div>
                    <p class="text-gray-300 mb-4 text-center">Pour des joueurs fixes, non modifiables pendant la durée de l'abonnement.</p>
                    <form method="POST">
                        <input type="hidden" name="type_abonnement" value="Grosse Faction">
                        <label class="block text-sm text-gray-300 mb-2 font-semibold">Durée & Prix :</label>
                        <select name="duree" class="w-full mb-4 px-3 py-2 rounded bg-gray-900 text-gray-100 border border-yellow-500 focus:ring-2 focus:ring-yellow-400 font-semibold" id="prix-grosse-faction" onchange="updatePrixGrosseFaction()">
                            <option value="1 mois" data-prix="95000">1 mois - 95 000 $</option>
                            <option value="3 mois" data-prix="228000">3 mois - 228 000 $</option>
                            <option value="6 mois" data-prix="420000">6 mois - 420 000 $</option>
                        </select>
                        <input type="hidden" name="prix" id="prix-grosse-faction-hidden" value="95000">
                        <div class="mb-2 text-sm text-yellow-400 font-bold">Réduction shop : <span class="bg-yellow-700/30 px-2 py-1 rounded">10 %</span></div>
                        <button type="submit"
                            class="bg-gradient-to-r from-yellow-500 to-pink-500 text-white font-bold px-6 py-2 rounded-lg shadow transition
                            <?php if($has_abonnement && !($can_rebuy && $lastAboType === 'Grosse Faction')): ?> opacity-60 cursor-not-allowed bg-gray-600 from-gray-600 to-gray-600 <?php endif; ?>"
                            <?php if($has_abonnement && !($can_rebuy && $lastAboType === 'Grosse Faction')): ?>disabled<?php endif; ?>>
                            <?php if($has_abonnement && !($can_rebuy && $lastAboType === 'Grosse Faction')): ?>
                                Abonnement déjà actif
                            <?php elseif($can_rebuy && $lastAboType === 'Grosse Faction'): ?>
                                Racheter le grade
                            <?php else: ?>
                                Acheter
                            <?php endif; ?>
                        </button>
                    </form>
                    <ul class="mt-6 text-base text-gray-200 list-disc pl-5 text-left space-y-1">
                        <li>10 % de réduction sur tous les achats du shop</li>
                        <li>Commandes ultra prioritaires</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    <?php require_once 'includes/footer.php'; ?>
</body>
</html>
<script>
document.getElementById('prix-indiv').addEventListener('change', function() {
    var prix = this.options[this.selectedIndex].getAttribute('data-prix');
    document.getElementById('prix-indiv-hidden').value = prix;
});
document.getElementById('prix-minifaction').addEventListener('change', function() {
    var prix = this.options[this.selectedIndex].getAttribute('data-prix');
    document.getElementById('prix-minifaction-hidden').value = prix;
});
document.getElementById('prix-faction').addEventListener('change', function() {
    var prix = this.options[this.selectedIndex].getAttribute('data-prix');
    document.getElementById('prix-faction-hidden').value = prix;
});
document.getElementById('prix-grosse-faction').addEventListener('change', function() {
    var prix = this.options[this.selectedIndex].getAttribute('data-prix');
    document.getElementById('prix-grosse-faction-hidden').value = prix;
});
setTimeout(function() {
    var notif = document.getElementById('notif-abo');
    if (notif) notif.remove();
}, 10000);
</script>
