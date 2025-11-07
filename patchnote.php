<?php
// Afficher les erreurs pour déboguer
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Vérifier que config.php existe
if (!file_exists('config.php')) {
    die('Erreur : Le fichier config.php est introuvable.');
}

require_once 'config.php';

// Vérifier que la connexion PDO existe
if (!isset($pdo)) {
    die('Erreur : La connexion à la base de données n\'est pas établie. Vérifiez votre fichier config.php.');
}

$patch_notes = [];

try {
    $stmt = $pdo->query("
        SELECT *
        FROM patch_notes
        ORDER BY release_date DESC, created_at DESC
    ");
    $patch_notes = $stmt->fetchAll();
} catch(PDOException $e) {
    // Les tables n'existent pas encore
    $error_message = "Les tables de patch notes n'existent pas encore. Veuillez exécuter le script SQL fourni.";
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patch Notes - CrazySouls Shop</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gradient-to-br from-gray-900 via-gray-800 to-gray-900 text-white min-h-screen">
    <?php include 'includes/header.php'; ?>

    <main class="container mx-auto px-4 py-8">
        <!-- Header -->
        <div class="text-center mb-12">
            <h1 class="text-4xl md:text-5xl font-bold mb-4 bg-gradient-to-r from-purple-400 to-pink-400 bg-clip-text text-transparent">
                <i class="fas fa-clipboard-list mr-3"></i>Patch Notes
            </h1>
            <p class="text-gray-400 text-lg">Découvrez les dernières mises à jour et améliorations</p>
        </div>

        <?php if (isset($error_message)): ?>
            <!-- Message d'erreur -->
            <div class="max-w-5xl mx-auto">
                <div class="bg-red-900/20 border border-red-500 rounded-xl p-8 text-center">
                    <i class="fas fa-exclamation-triangle text-5xl text-red-500 mb-4"></i>
                    <h2 class="text-2xl font-bold text-red-400 mb-4">Configuration requise</h2>
                    <p class="text-gray-300 mb-6"><?php echo htmlspecialchars($error_message); ?></p>
                    <div class="bg-gray-900 rounded-lg p-4 text-left">
                        <p class="text-gray-400 mb-2 font-bold">Exécutez ce SQL dans votre base de données :</p>
                        <pre class="text-sm text-green-400 overflow-x-auto"><code>CREATE TABLE IF NOT EXISTS patch_notes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    version VARCHAR(20) NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    changes TEXT NOT NULL,
    release_date DATE NOT NULL,
    upvotes INT NOT NULL DEFAULT 0,
    downvotes INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_release_date (release_date DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;</code></pre>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- Patch Notes List -->
            <div class="max-w-5xl mx-auto space-y-6">
                <?php foreach ($patch_notes as $patch): ?>
                    <?php
                    $net_votes = $patch['upvotes'] - $patch['downvotes'];
                    ?>
                    <div class="bg-gray-800 rounded-xl border border-gray-700 overflow-hidden hover:border-purple-500 transition-all duration-300 shadow-xl">
                        <div class="p-6">
                            <!-- Header avec version et date -->
                            <div class="flex flex-wrap items-start justify-between gap-4 mb-4">
                                <div>
                                    <div class="flex items-center gap-3 mb-2">
                                        <span class="px-4 py-1.5 bg-gradient-to-r from-purple-600 to-pink-600 rounded-full text-sm font-bold">
                                            v<?php echo htmlspecialchars($patch['version']); ?>
                                        </span>
                                        <span class="text-gray-400 text-sm">
                                            <i class="far fa-calendar-alt mr-1"></i>
                                            <?php echo date('d/m/Y', strtotime($patch['release_date'])); ?>
                                        </span>
                                    </div>
                                    <h2 class="text-2xl font-bold text-white">
                                        <?php echo htmlspecialchars($patch['title']); ?>
                                    </h2>
                                </div>

                                <!-- Vote System -->
                                <div class="flex items-center gap-2 bg-gray-900 rounded-lg p-2">
                                    <button 
                                        onclick="vote(<?php echo $patch['id']; ?>, 'upvote')"
                                        class="vote-btn px-3 py-2 rounded-lg transition-all duration-200 bg-gray-800 text-gray-400 hover:bg-green-600 hover:text-white"
                                    >
                                        <i class="fas fa-arrow-up"></i>
                                    </button>
                                    <span class="vote-count font-bold text-lg px-3 <?php echo $net_votes > 0 ? 'text-green-400' : ($net_votes < 0 ? 'text-red-400' : 'text-gray-400'); ?>">
                                        <?php echo $net_votes > 0 ? '+' : ''; ?><?php echo $net_votes; ?>
                                    </span>
                                    <button 
                                        onclick="vote(<?php echo $patch['id']; ?>, 'downvote')"
                                        class="vote-btn px-3 py-2 rounded-lg transition-all duration-200 bg-gray-800 text-gray-400 hover:bg-red-600 hover:text-white"
                                    >
                                        <i class="fas fa-arrow-down"></i>
                                    </button>
                                </div>
                            </div>

                            <!-- Description -->
                            <p class="text-gray-300 mb-4 text-lg">
                                <?php echo nl2br(htmlspecialchars($patch['description'])); ?>
                            </p>

                            <!-- Changes -->
                            <div class="bg-gray-900 rounded-lg p-4 border border-gray-700">
                                <h3 class="text-purple-400 font-bold mb-3 flex items-center gap-2">
                                    <i class="fas fa-code-branch"></i>
                                    Changements :
                                </h3>
                                <div class="text-gray-300 space-y-2">
                                    <?php
                                    $changes = explode("\n", $patch['changes']);
                                    foreach ($changes as $change) {
                                        $change = trim($change);
                                        if (!empty($change)) {
                                            echo '<div class="flex items-start gap-2">';
                                            if (strpos($change, '-') === 0) {
                                                echo '<i class="fas fa-check text-green-400 mt-1"></i>';
                                                echo '<span>' . htmlspecialchars(substr($change, 1)) . '</span>';
                                            } else {
                                                echo '<i class="fas fa-check text-green-400 mt-1"></i>';
                                                echo '<span>' . htmlspecialchars($change) . '</span>';
                                            }
                                            echo '</div>';
                                        }
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

                <?php if (empty($patch_notes)): ?>
                    <div class="text-center py-16">
                        <i class="fas fa-inbox text-6xl text-gray-600 mb-4"></i>
                        <p class="text-gray-400 text-xl">Aucune patch note disponible pour le moment.</p>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </main>

    <script>
        function vote(patchId, voteType) {
            fetch('vote_patch.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    patch_id: patchId,
                    vote_type: voteType
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.message || 'Erreur lors du vote');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Erreur lors du vote');
            });
        }
    </script>
</body>
</html>
