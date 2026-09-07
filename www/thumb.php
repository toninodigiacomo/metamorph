<?php
// Sert une miniature (générée et mise en cache via GD) d'une page en cours d'édition.
// Les fichiers de cache (.thumbs/) sont hors du dossier zippé, donc jamais inclus dans le CBZ final.

$uploadsDir = __DIR__ . '/uploads';
$workRoot   = $uploadsDir . '/cbz_work';

$workId = basename($_GET['work_id'] ?? '');
$file   = basename($_GET['file'] ?? '');

$dir = $workRoot . '/' . $workId;
$statePath = $dir . '/.state.json';

if ($workId === '' || $file === '' || !is_dir($dir) || !file_exists($statePath)) {
    http_response_code(404);
    exit;
}

$state = json_decode(file_get_contents($statePath), true);
$effectiveDir = $dir . ($state['effective_subdir'] ?? '');
$sourcePath = $effectiveDir . '/' . $file;

if (!file_exists($sourcePath)) {
    http_response_code(404);
    exit;
}

$thumbDir = $dir . '/.thumbs';
if (!is_dir($thumbDir)) {
    mkdir($thumbDir, 0775, true);
}
$thumbPath = $thumbDir . '/' . md5($file) . '.jpg';

// Régénère seulement si absent (le fichier source ne change pas pendant une session d'édition)
if (!file_exists($thumbPath)) {
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $src = null;
    switch ($ext) {
        case 'jpg':
        case 'jpeg':
            $src = @imagecreatefromjpeg($sourcePath);
            break;
        case 'png':
            $src = @imagecreatefrompng($sourcePath);
            break;
        case 'gif':
            $src = @imagecreatefromgif($sourcePath);
            break;
        case 'webp':
            $src = @imagecreatefromwebp($sourcePath);
            break;
    }

    if (!$src) {
        http_response_code(415);
        exit;
    }

    $maxWidth = 220;
    $w = imagesx($src);
    $h = imagesy($src);
    $ratio = min(1, $maxWidth / $w);
    $newW = max(1, (int)round($w * $ratio));
    $newH = max(1, (int)round($h * $ratio));

    $thumb = imagecreatetruecolor($newW, $newH);
    $white = imagecolorallocate($thumb, 255, 255, 255);
    imagefill($thumb, 0, 0, $white);
    imagecopyresampled($thumb, $src, 0, 0, 0, 0, $newW, $newH, $w, $h);

    imagejpeg($thumb, $thumbPath, 82);
    imagedestroy($thumb);
    imagedestroy($src);
}

header('Content-Type: image/jpeg');
header('Cache-Control: private, max-age=3600');
header('Content-Length: ' . filesize($thumbPath));
readfile($thumbPath);
