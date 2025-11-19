<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once '../config.php';

// Vérification des permissions
if(!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['resp_vendeur', 'fondateur'])) {
    header('Location: ../login.php');
    exit;
}

$message = '';
$images_dir = dirname(__DIR__) . '/images/';
if (!is_dir($images_dir)) {
    mkdir($images_dir, 0755, true);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['image'])) {
    $file = $_FILES['image'];
    if ($file['error'] === UPLOAD_ERR_OK) {
        if ($file['size'] > 5 * 1024 * 1024) {
            $message = "Fichier trop volumineux (max 5 Mo).";
        } else {
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $mime = mime_content_type($file['tmp_name']);
            $allowed_mime = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (in_array($ext, $allowed) && in_array($mime, $allowed_mime)) {
                // Sécurise le nom du fichier
                $filename = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', basename($file['name']));
                $target = $images_dir . $filename;
                if (file_exists($target)) {
                    $message = "Cette image existe déjà dans la galerie.";
                } else if (move_uploaded_file($file['tmp_name'], $target)) {
                    require_once __DIR__ . '/../resize_image.php';
                    resizeImage($target, $target, 256, 256);
                    $message = "Image uploadée et redimensionnée à 256x256px avec succès !";
                } else {
                    $message = "Erreur lors du déplacement du fichier.";
                }
            } else {
                $message = "Format ou type d'image non autorisé.";
            }
        }
    } else {
        $message = "Erreur d'upload.";
    }
}

$image_files = [];
if (is_dir($images_dir)) {
    $image_files = array_filter(scandir($images_dir), function($file) use ($images_dir) {
        return preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $file) && is_file($images_dir . $file);
    });
    sort($image_files, SORT_NATURAL | SORT_FLAG_CASE); // Tri alphabétique
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Image - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        .drop-zone {
            border: 2px dashed #2563eb;
            border-radius: 12px;
            padding: 32px;
            text-align: center;
            cursor: pointer;
            background: #1f2937; /* bg-gray-800 */
            transition: all 0.3s;
        }
        .drop-zone.active {
            border-color: #1d4ed8;
            background: #374151; /* bg-gray-700 */
            transform: scale(1.02);
        }
        .drop-zone .icon {
            font-size: 48px;
            margin-bottom: 10px;
            color: #2563eb;
        }
        .import-btn {
            display: inline-block;
            margin-top: 10px;
            padding: 10px 20px;
            background-color: #2563eb;
            color: white;
            border-radius: 6px;
            font-weight: bold;
            text-transform: uppercase;
            cursor: pointer;
        }
        .image-preview {
            margin-top: 10px;
            max-width: 300px;
        }
        .image-preview img {
            max-width: 100%;
            height: auto;
            border: 2px solid #ddd;
            border-radius: 8px;
            padding: 5px;
            background: #fff;
        }
    </style>
</head>
<body class="bg-gray-900 text-gray-100">
    <div class="flex flex-col md:flex-row min-h-screen">
        <?php include 'sidebar.php'; ?>
        <main class="flex-1 p-2 sm:p-8 w-full">
            <div class="mb-4 sm:mb-8">
                <h2 class="text-2xl sm:text-3xl font-bold mb-2">Ajouter une image au shop</h2>
                <p class="text-gray-400 text-sm sm:text-base">Uploader une image, elle sera redimensionnée à 256x256px.</p>
            </div>
            <!-- Conteneur d'ajout d'image -->
            <div class="max-w-xl mx-auto bg-gray-800 rounded-2xl shadow-2xl border-2 border-blue-900 p-8 mb-10">
                <?php if ($message): ?>
                    <div class="mb-6 p-4 bg-blue-900 text-blue-100 rounded-lg border border-blue-700 shadow flex items-center gap-2">
                        <i class="fas fa-info-circle text-blue-300"></i>
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>
                <form method="post" enctype="multipart/form-data" class="space-y-6">
                    <div>
                        <label class="block text-blue-700 font-bold mb-2 text-lg">Image du shop *</label>
                        <div class="drop-zone" id="dropZone">
                            <div class="icon"><i class="fa-solid fa-folder-open"></i></div>
                            <p>Glissez-déposez une image ici</p>
                            <span class="import-btn">ou cliquez pour IMPORTER</span>
                            <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/jpg,image/gif,image/webp" required style="display: none;">
                        </div>
                        <small class="text-gray-500">Formats acceptés : JPG, JPEG, PNG, GIF, WEBP (max 5 Mo)</small>
                        <div class="image-preview" id="imagePreview"></div>
                    </div>
                    <button type="submit" class="w-full bg-gradient-to-r from-blue-600 to-cyan-600 hover:from-blue-700 hover:to-cyan-700 text-white font-bold py-3 rounded-xl shadow-lg transition flex items-center justify-center gap-2 text-lg">
                        <i class="fas fa-upload"></i>
                        Uploader
                    </button>
                </form>
            </div>

            <!-- Conteneur d'affichage de la galerie -->
            <?php if (!empty($image_files)): ?>
                <!-- Barre de recherche -->
                <div class="max-w-5xl mx-auto mb-6 flex items-center gap-3">
                    <div class="relative w-full max-w-xs">
                        <input type="text" id="searchInput" class="w-full pl-10 pr-4 py-2 rounded-lg border border-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-800 text-gray-100 bg-gray-900" placeholder="Rechercher une image...">
                        <span class="absolute left-3 top-2.5 text-blue-400">
                            <i class="fa fa-search"></i>
                        </span>
                    </div>
                </div>
                <div class="max-w-5xl mx-auto bg-gray-900 rounded-2xl shadow-xl border-2 border-blue-900 p-8">
                    <h3 class="text-2xl font-extrabold mb-6 text-blue-300 flex items-center gap-2">
                        <i class="fa-solid fa-images text-blue-400"></i>
                        Galerie du shop
                    </h3>
                    <div class="grid grid-cols-2 sm:grid-cols-5 gap-6" id="galleryGrid">
                        <?php foreach($image_files as $img): ?>
                            <div class="bg-gray-800 rounded-xl shadow-lg hover:shadow-2xl border border-blue-900 p-3 flex flex-col items-center transition-all duration-200 hover:scale-105 group gallery-item" data-name="<?php echo htmlspecialchars($img); ?>">
                                <img src="../images/<?php echo htmlspecialchars($img); ?>" alt="<?php echo htmlspecialchars($img); ?>" class="w-32 h-32 object-contain mb-2 rounded-lg border-2 border-blue-700 group-hover:border-blue-400 transition-all duration-200 bg-gray-900">
                                <span class="text-xs text-blue-300 font-semibold break-all text-center"><?php echo htmlspecialchars($img); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>
    <script>
        const dropZone = document.getElementById('dropZone');
        const fileInput = document.getElementById('image');
        const imagePreview = document.getElementById('imagePreview');

        dropZone.addEventListener('click', (e) => {
            if(e.target !== fileInput) {
                fileInput.click();
            }
        });

        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, (e) => {
                e.preventDefault();
                e.stopPropagation();
            });
        });

        ['dragenter', 'dragover'].forEach(eventName => {
            dropZone.classList.remove('active');
            dropZone.addEventListener(eventName, () => dropZone.classList.add('active'));
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, () => dropZone.classList.remove('active'));
        });

        dropZone.addEventListener('drop', (e) => {
            const files = e.dataTransfer.files;
            if(files.length) {
                fileInput.files = files;
                previewImage(files[0]);
            }
        });

        fileInput.addEventListener('change', (e) => {
            if(e.target.files.length) {
                previewImage(e.target.files[0]);
            }
        });

        function previewImage(file) {
            const reader = new FileReader();
            reader.onload = (e) => {
                imagePreview.innerHTML = `<img src="${e.target.result}" alt="Aperçu">`;
                const pTag = dropZone.querySelector('p');
                if(pTag) {
                    pTag.textContent = 'Image importée : ' + file.name;
                }
                const icon = dropZone.querySelector('.icon');
                if(icon) {
                    icon.innerHTML = '<i class="fa-solid fa-circle-check"></i>';
                }
                const btn = dropZone.querySelector('.import-btn');
                if(btn) {
                    btn.textContent = 'Cliquez pour changer l\'image';
                }
            };
            reader.readAsDataURL(file);
        }

        // Filtre de recherche pour la galerie
        const searchInput = document.getElementById('searchInput');
        const galleryGrid = document.getElementById('galleryGrid');
        if (searchInput && galleryGrid) {
            searchInput.addEventListener('input', function() {
                const query = this.value.toLowerCase();
                const items = galleryGrid.querySelectorAll('.gallery-item');
                items.forEach(item => {
                    const name = item.getAttribute('data-name').toLowerCase();
                    item.style.display = name.includes(query) ? '' : 'none';
                });
            });
        }
    </script>
</body>
</html>
