<?php
// Fonction de base pour redimensionner une image
function resize_image($file, $width, $height) {
    return $file;
}

function resizeImageTo256($srcPath, $destPath) {
    $info = getimagesize($srcPath);
    $mime = $info['mime'];

    switch ($mime) {
        case 'image/jpeg':
            $src = imagecreatefromjpeg($srcPath);
            break;
        case 'image/png':
            $src = imagecreatefrompng($srcPath);
            break;
        case 'image/webp':
            $src = imagecreatefromwebp($srcPath);
            break;
        default:
            throw new Exception("Format d'image non supporté");
    }

    $dst = imagecreatetruecolor(256, 256);

    // Pour PNG, préserver la transparence
    if ($mime === 'image/png') {
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
    }

    imagecopyresampled($dst, $src, 0, 0, 0, 0, 256, 256, imagesx($src), imagesy($src));

    // Sauvegarde selon le format d'origine
    switch ($mime) {
        case 'image/jpeg':
            imagejpeg($dst, $destPath, 90);
            break;
        case 'image/png':
            imagepng($dst, $destPath, 9);
            break;
        case 'image/webp':
            imagewebp($dst, $destPath, 90);
            break;
    }

    imagedestroy($src);
    imagedestroy($dst);
}

function resizeImage($src, $dest, $width, $height) {
    $info = getimagesize($src);
    if (!$info) return false;

    list($srcWidth, $srcHeight) = $info;
    $type = $info[2];

    switch ($type) {
        case IMAGETYPE_JPEG:
            $image = imagecreatefromjpeg($src);
            break;
        case IMAGETYPE_PNG:
            $image = imagecreatefrompng($src);
            break;
        case IMAGETYPE_GIF:
            $image = imagecreatefromgif($src);
            break;
        case IMAGETYPE_WEBP:
            if (function_exists('imagecreatefromwebp')) {
                $image = imagecreatefromwebp($src);
            } else {
                return false;
            }
            break;
        default:
            return false;
    }

    $resized = imagecreatetruecolor($width, $height);
    // Préserver la transparence pour PNG, GIF, WEBP
    if ($type == IMAGETYPE_PNG || $type == IMAGETYPE_GIF || $type == IMAGETYPE_WEBP) {
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
    }
    imagecopyresampled($resized, $image, 0, 0, 0, 0, $width, $height, $srcWidth, $srcHeight);

    switch ($type) {
        case IMAGETYPE_JPEG:
            imagejpeg($resized, $dest, 90);
            break;
        case IMAGETYPE_PNG:
            imagepng($resized, $dest);
            break;
        case IMAGETYPE_GIF:
            imagegif($resized, $dest);
            break;
        case IMAGETYPE_WEBP:
            if (function_exists('imagewebp')) {
                imagewebp($resized, $dest);
            } else {
                return false;
            }
            break;
    }
    imagedestroy($image);
    imagedestroy($resized);
    return true;
}
?>
