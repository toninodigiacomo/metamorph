<?php
// Conversion de documents — Convertisseur (PDF -> EPUB via Calibre, PNG -> ICO via GD)
require_once __DIR__ . '/includes/i18n.php';

$uploadsDir = __DIR__ . '/uploads';
$outputDir  = __DIR__ . '/output';

$message = '';
$downloadFile = null;

// Table des conversions supportées : moteur ('calibre' ou 'ico') + extensions
$conversions = [
    'pdf_to_epub' => ['from' => 'pdf', 'to' => 'epub', 'engine' => 'calibre', 'label' => t('conv_option_pdf_epub')],
    'png_to_ico'  => ['from' => 'png', 'to' => 'ico',  'engine' => 'ico',     'label' => t('conv_option_png_ico')],
    'svg_to_png'  => ['from' => 'svg', 'to' => 'png',  'engine' => 'rsvg',    'label' => t('conv_option_svg_png')],
];

// Mime-types pour le téléchargement, selon l'extension du fichier produit
const OUTPUT_MIME_TYPES = [
    'epub' => 'application/epub+zip',
    'ico'  => 'image/vnd.microsoft.icon',
    'png'  => 'image/png',
];

/**
 * Construit un fichier .ico multi-résolutions (16 à 256px) à partir d'un PNG source,
 * en conservant la transparence. Format moderne : chaque résolution est un PNG
 * embarqué tel quel dans le conteneur ICO (supporté depuis Windows Vista).
 */
function pngToIco(string $srcPath, string $outPath, array $sizes = [16, 32, 48, 64, 128, 256]): bool {
    $src = @imagecreatefrompng($srcPath);
    if (!$src) {
        return false;
    }

    $srcW = imagesx($src);
    $srcH = imagesy($src);
    $images = [];

    foreach ($sizes as $size) {
        $canvas = imagecreatetruecolor($size, $size);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefill($canvas, 0, 0, $transparent);

        imagecopyresampled($canvas, $src, 0, 0, 0, 0, $size, $size, $srcW, $srcH);

        ob_start();
        imagepng($canvas);
        $images[$size] = ob_get_clean();
        imagedestroy($canvas);
    }
    imagedestroy($src);

    $count = count($images);
    $header = pack('vvv', 0, 1, $count); // reserved, type=1 (icon), count

    $dirEntries = '';
    $imageData  = '';
    $offset = 6 + ($count * 16); // en-tête + table des entrées

    foreach ($images as $size => $data) {
        $dim = $size === 256 ? 0 : $size; // 0 = 256px selon la spec ICO
        $dirEntries .= pack(
            'CCCCvvVV',
            $dim, $dim,     // largeur, hauteur
            0, 0,           // palette, réservé
            1, 32,          // plans couleur, bits par pixel
            strlen($data),  // taille des données
            $offset         // offset dans le fichier
        );
        $imageData .= $data;
        $offset += strlen($data);
    }

    return file_put_contents($outPath, $header . $dirEntries . $imageData) !== false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['document'])) {
    $conversionKey = $_POST['conversion'] ?? 'pdf_to_epub';

    if (!isset($conversions[$conversionKey])) {
        $message = t('conv_err_unknown_type');
    } else {
        $conv = $conversions[$conversionKey];
        $file = $_FILES['document'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $message = t('conv_err_upload', ['code' => $file['error']]);
        } else {
            $originalExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if ($originalExt !== $conv['from']) {
                $message = t('conv_err_ext', ['ext' => $conv['from']]);
            } else {
                // Nom de fichier sûr et unique
                $baseName = uniqid('doc_', true);
                $inputPath  = $uploadsDir . '/' . $baseName . '.' . $conv['from'];
                $outputPath = $outputDir  . '/' . $baseName . '.' . $conv['to'];

                if (move_uploaded_file($file['tmp_name'], $inputPath)) {
                    $ok = false;

                    if ($conv['engine'] === 'calibre') {
                        // Appel de ebook-convert (Calibre), en échappant les chemins
                        $cmd = sprintf(
                            'ebook-convert %s %s 2>&1',
                            escapeshellarg($inputPath),
                            escapeshellarg($outputPath)
                        );
                        exec($cmd, $outputLines, $returnCode);
                        $ok = ($returnCode === 0 && file_exists($outputPath));
                        $errorDetail = $ok ? '' : "<br><pre>" . htmlspecialchars(implode("\n", $outputLines)) . "</pre>";
                    } elseif ($conv['engine'] === 'ico') {
                        $ok = pngToIco($inputPath, $outputPath);
                        $errorDetail = '';
                    } elseif ($conv['engine'] === 'rsvg') {
                        // rsvg-convert : rendu SVG -> PNG à la résolution intrinsèque du fichier
                        $cmd = sprintf(
                            'rsvg-convert -o %s %s 2>&1',
                            escapeshellarg($outputPath),
                            escapeshellarg($inputPath)
                        );
                        exec($cmd, $outputLines, $returnCode);
                        $ok = ($returnCode === 0 && file_exists($outputPath));
                        $errorDetail = $ok ? '' : "<br><pre>" . htmlspecialchars(implode("\n", $outputLines)) . "</pre>";
                    }

                    if ($ok) {
                        $downloadFile = basename($outputPath);
                        $message = t('conv_success');
                    } else {
                        $message = t('conv_failed') . ($errorDetail ?? '');
                    }

                    // Nettoyage du fichier source uploadé
                    @unlink($inputPath);
                } else {
                    $message = t('conv_move_failed');
                }
            }
        }
    }
}

// Téléchargement direct du fichier produit
if (isset($_GET['download'])) {
    $safeName = basename($_GET['download']);
    $path = $outputDir . '/' . $safeName;
    if (file_exists($path)) {
        $ext = strtolower(pathinfo($safeName, PATHINFO_EXTENSION));
        $mime = OUTPUT_MIME_TYPES[$ext] ?? 'application/octet-stream';
        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="' . $safeName . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }
}

$activePage = 'convert';
$pageTitle  = 'Metamorph — ' . t('nav_convert');
include __DIR__ . '/includes/header.php';
?>
            <form method="post" enctype="multipart/form-data">
                <label for="conversion"><?= t('conv_type_label') ?></label>
                <select name="conversion" id="conversion">
                    <?php foreach ($conversions as $key => $conv): ?>
                        <option value="<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($conv['label']) ?></option>
                    <?php endforeach; ?>
                </select>

                <label for="document"><?= t('conv_file_label') ?></label>
                <div id="dropzone" class="dropzone">
                    <span id="dropzone-text"><?= t('conv_dropzone') ?></span>
                </div>
                <input type="file" name="document" id="document" required hidden>

                <button type="submit"><?= t('conv_btn_convert') ?></button>
            </form>

            <?php if ($message): ?>
                <div class="message">
                    <?= $message ?>
                    <?php if ($downloadFile): ?>
                        <br><a class="download" href="?download=<?= urlencode($downloadFile) ?>"><?= t('conv_download') ?></a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
    <script>
        const dropzone = document.getElementById('dropzone');
        const fileInput = document.getElementById('document');
        const dropzoneText = document.getElementById('dropzone-text');

        function showFileName(file) {
            if (file) {
                dropzoneText.textContent = file.name;
                dropzone.classList.add('has-file');
            }
        }

        // Clic sur la zone = ouvre le sélecteur de fichier classique
        dropzone.addEventListener('click', () => fileInput.click());

        // Sélection via le "Parcourir" natif
        fileInput.addEventListener('change', () => showFileName(fileInput.files[0]));

        // Glisser-déposer
        ['dragenter', 'dragover'].forEach(evt =>
            dropzone.addEventListener(evt, (e) => {
                e.preventDefault();
                dropzone.classList.add('dragover');
            })
        );
        ['dragleave', 'drop'].forEach(evt =>
            dropzone.addEventListener(evt, (e) => {
                e.preventDefault();
                dropzone.classList.remove('dragover');
            })
        );
        dropzone.addEventListener('drop', (e) => {
            const file = e.dataTransfer.files[0];
            if (file) {
                fileInput.files = e.dataTransfer.files;
                showFileName(file);
            }
        });
    </script>
</body>
</html>
