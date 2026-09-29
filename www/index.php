<?php
// Conversion de documents — Convertisseur
// Matrice entrée -> sortie : source unique de vérité, utilisée côté serveur (validation)
// ET côté client (peuplement dynamique de la liste "sortie" via JS généré depuis ce même tableau).
require_once __DIR__ . '/includes/i18n.php';

$uploadsDir = __DIR__ . '/uploads';
$outputDir  = __DIR__ . '/output';

$message = '';
$downloadFile = null;

$conversions = [
    'pdf' => ['epub' => ['engine' => 'calibre']],
    'png' => ['ico'  => ['engine' => 'ico']],
    'svg' => ['png'  => ['engine' => 'rsvg']],
    'cbr' => ['cbz'  => ['engine' => 'repack']],
];

// Mime-types pour le téléchargement, selon l'extension du fichier produit
const OUTPUT_MIME_TYPES = [
    'epub' => 'application/epub+zip',
    'ico'  => 'image/vnd.microsoft.icon',
    'png'  => 'image/png',
    'cbz'  => 'application/vnd.comicbook+zip',
];

function rrmdirLocal(string $dir): void {
    if (!is_dir($dir)) return;
    foreach (scandir($dir) as $item) {
        if ($item === '.' || $item === '..') continue;
        $path = $dir . '/' . $item;
        is_dir($path) ? rrmdirLocal($path) : @unlink($path);
    }
    @rmdir($dir);
}

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

/**
 * Repaquette un CBR (RAR) en CBZ (ZIP), sans toucher aux métadonnées.
 * Le RAR n'étant pas réinscriptible sans outil propriétaire, seul CBR -> CBZ est proposé.
 */
function repackCbrToCbz(string $srcPath, string $outPath): bool {
    $tmpDir = sys_get_temp_dir() . '/cbr_repack_' . uniqid('', true);
    mkdir($tmpDir, 0775, true);

    exec(sprintf('unar -f -q -o %s %s 2>&1', escapeshellarg($tmpDir), escapeshellarg($srcPath)), $out, $code);
    if ($code !== 0) {
        rrmdirLocal($tmpDir);
        return false;
    }

    // Si l'archive n'a qu'un seul sous-dossier à sa racine, on "descend" dedans
    $effective = $tmpDir;
    $entries = array_values(array_diff(scandir($effective), ['.', '..']));
    while (count($entries) === 1 && is_dir($effective . '/' . $entries[0])) {
        $effective .= '/' . $entries[0];
        $entries = array_values(array_diff(scandir($effective), ['.', '..']));
    }

    $zip = new ZipArchive();
    if ($zip->open($outPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        rrmdirLocal($tmpDir);
        return false;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($effective, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        $localName = substr($file->getPathname(), strlen($effective) + 1);
        if (strpos($localName, '.') === 0 || strpos($localName, '/.') !== false) {
            continue; // ignore fichiers/dossiers cachés
        }
        $zip->addFile($file->getPathname(), $localName);
    }
    $zip->close();

    rrmdirLocal($tmpDir);
    return true;
}

// Sélection courante (conservée après soumission, sinon première paire valide par défaut)
$inputExts = array_keys($conversions);
$selectedFrom = $_POST['from_ext'] ?? $inputExts[0];
if (!isset($conversions[$selectedFrom])) {
    $selectedFrom = $inputExts[0];
}
$outputExtsForFrom = array_keys($conversions[$selectedFrom]);
$selectedTo = $_POST['to_ext'] ?? $outputExtsForFrom[0];
if (!in_array($selectedTo, $outputExtsForFrom, true)) {
    $selectedTo = $outputExtsForFrom[0];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['document'])) {
    $fromExt = strtolower($_POST['from_ext'] ?? '');
    $toExt   = strtolower($_POST['to_ext'] ?? '');

    if (!isset($conversions[$fromExt][$toExt])) {
        $message = t('conv_err_unknown_type');
    } else {
        $engine = $conversions[$fromExt][$toExt]['engine'];
        $file = $_FILES['document'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $message = t('conv_err_upload', ['code' => $file['error']]);
        } else {
            $originalExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if ($originalExt !== $fromExt) {
                $message = t('conv_err_ext', ['ext' => $fromExt]);
            } else {
                // Nom de fichier sûr et unique
                $baseName = uniqid('doc_', true);
                $inputPath  = $uploadsDir . '/' . $baseName . '.' . $fromExt;
                $outputPath = $outputDir  . '/' . $baseName . '.' . $toExt;

                if (move_uploaded_file($file['tmp_name'], $inputPath)) {
                    $ok = false;
                    $errorDetail = '';

                    if ($engine === 'calibre') {
                        $cmd = sprintf(
                            'ebook-convert %s %s 2>&1',
                            escapeshellarg($inputPath),
                            escapeshellarg($outputPath)
                        );
                        exec($cmd, $outputLines, $returnCode);
                        $ok = ($returnCode === 0 && file_exists($outputPath));
                        $errorDetail = $ok ? '' : "<br><pre>" . htmlspecialchars(implode("\n", $outputLines)) . "</pre>";
                    } elseif ($engine === 'ico') {
                        $ok = pngToIco($inputPath, $outputPath);
                    } elseif ($engine === 'rsvg') {
                        $cmd = sprintf(
                            'rsvg-convert -o %s %s 2>&1',
                            escapeshellarg($outputPath),
                            escapeshellarg($inputPath)
                        );
                        exec($cmd, $outputLines, $returnCode);
                        $ok = ($returnCode === 0 && file_exists($outputPath));
                        $errorDetail = $ok ? '' : "<br><pre>" . htmlspecialchars(implode("\n", $outputLines)) . "</pre>";
                    } elseif ($engine === 'repack') {
                        $ok = repackCbrToCbz($inputPath, $outputPath);
                    }

                    if ($ok) {
                        $downloadFile = basename($outputPath);
                        $message = t('conv_success');
                    } else {
                        $message = t('conv_failed') . $errorDetail;
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
                <div class="conversion-picker">
                    <div class="field">
                        <label for="from_ext"><?= t('conv_input_label') ?></label>
                        <select name="from_ext" id="from_ext">
                            <?php foreach ($inputExts as $ext): ?>
                                <option value="<?= htmlspecialchars($ext) ?>" <?= $ext === $selectedFrom ? 'selected' : '' ?>><?= strtoupper($ext) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="swap-icon" aria-hidden="true">&#8594;</div>
                    <div class="field">
                        <label for="to_ext"><?= t('conv_output_label') ?></label>
                        <select name="to_ext" id="to_ext">
                            <?php foreach ($outputExtsForFrom as $ext): ?>
                                <option value="<?= htmlspecialchars($ext) ?>" <?= $ext === $selectedTo ? 'selected' : '' ?>><?= strtoupper($ext) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <label for="document"><?= t('conv_file_label') ?></label>
                <div id="dropzone" class="dropzone">
                    <span id="dropzone-text"><?= t('conv_dropzone') ?></span>
                </div>
                <div id="file-error" class="field-error" style="display:none;"></div>
                <input type="file" name="document" id="document" required hidden>

                <button type="submit" id="convert-btn"><span class="fill" id="convert-fill"></span><span class="label" id="convert-label"><?= t('conv_btn_convert') ?></span></button>
            </form>

            <div class="message" id="js-error" style="display:none;"></div>

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
        // Même matrice que côté serveur, exportée en JSON pour peupler dynamiquement
        // la liste "sortie" en fonction du format d'entrée choisi.
        const MATRIX = <?= json_encode(array_map(fn($outs) => array_keys($outs), $conversions)) ?>;

        const fromSelect = document.getElementById('from_ext');
        const toSelect = document.getElementById('to_ext');

        fromSelect.addEventListener('change', () => {
            const options = MATRIX[fromSelect.value] || [];
            toSelect.innerHTML = '';
            options.forEach(ext => {
                const opt = document.createElement('option');
                opt.value = ext;
                opt.textContent = ext.toUpperCase();
                toSelect.appendChild(opt);
            });
        });

        const dropzone = document.getElementById('dropzone');
        const fileInput = document.getElementById('document');
        const dropzoneText = document.getElementById('dropzone-text');
        const fileError = document.getElementById('file-error');
        const convertBtn = document.getElementById('convert-btn');
        const wrongExtText = <?= json_encode(t('conv_err_ext')) ?>; // contient le jeton {ext}

        function validateFileExt() {
            const file = fileInput.files[0];
            if (!file) {
                fileError.style.display = 'none';
                dropzone.classList.remove('has-error');
                convertBtn.disabled = false;
                return true;
            }
            const actualExt = file.name.split('.').pop().toLowerCase();
            if (actualExt !== fromSelect.value) {
                fileError.textContent = wrongExtText.replace('{ext}', fromSelect.value.toUpperCase());
                fileError.style.display = 'block';
                dropzone.classList.add('has-error');
                convertBtn.disabled = true;
                return false;
            }
            fileError.style.display = 'none';
            dropzone.classList.remove('has-error');
            convertBtn.disabled = false;
            return true;
        }

        function showFileName(file) {
            if (file) {
                dropzoneText.textContent = file.name;
                dropzone.classList.add('has-file');
            }
            validateFileExt();
        }

        dropzone.addEventListener('click', () => fileInput.click());
        fileInput.addEventListener('change', () => showFileName(fileInput.files[0]));
        // Revalide si l'utilisateur change le format d'entrée après avoir choisi un fichier
        fromSelect.addEventListener('change', validateFileExt);
        ['dragenter', 'dragover'].forEach(evt =>
            dropzone.addEventListener(evt, (e) => { e.preventDefault(); dropzone.classList.add('dragover'); })
        );
        ['dragleave', 'drop'].forEach(evt =>
            dropzone.addEventListener(evt, (e) => { e.preventDefault(); dropzone.classList.remove('dragover'); })
        );
        dropzone.addEventListener('drop', (e) => {
            const file = e.dataTransfer.files[0];
            if (file) {
                fileInput.files = e.dataTransfer.files;
                showFileName(file);
            }
        });

        // Soumission via XHR : permet un vrai suivi de progression pour l'envoi
        // du fichier. La phase de traitement serveur (Calibre, rsvg-convert, etc.)
        // n'est en revanche pas mesurable côté navigateur sans file d'attente
        // dédiée côté serveur — on affiche alors un indicateur "en cours" honnête
        // plutôt qu'un faux pourcentage.
        const form = document.querySelector('form');
        const btn = document.getElementById('convert-btn');
        const fill = document.getElementById('convert-fill');
        const label = document.getElementById('convert-label');
        const jsError = document.getElementById('js-error');
        const originalLabel = label.textContent;
        const uploadingText = <?= json_encode(t('conv_uploading')) ?>;
        const processingText = <?= json_encode(t('conv_processing')) ?>;
        const networkErrorText = <?= json_encode(t('conv_network_error')) ?>;

        form.addEventListener('submit', (e) => {
            if (!fileInput.files.length) return; // laisse le navigateur gérer le "required"
            if (!validateFileExt()) { e.preventDefault(); return; }
            e.preventDefault();

            jsError.style.display = 'none';
            btn.disabled = true;
            btn.classList.add('loading');
            fill.style.width = '0%';
            label.textContent = uploadingText.replace('{pct}', '0');

            const xhr = new XMLHttpRequest();
            xhr.open('POST', window.location.pathname + window.location.search, true);

            xhr.upload.addEventListener('progress', (evt) => {
                if (evt.lengthComputable) {
                    const pct = Math.round((evt.loaded / evt.total) * 100);
                    fill.style.width = pct + '%';
                    label.textContent = uploadingText.replace('{pct}', pct);
                }
            });

            xhr.upload.addEventListener('load', () => {
                // Envoi terminé, le serveur traite maintenant le fichier (durée inconnue)
                fill.style.width = '100%';
                fill.classList.add('indeterminate');
                label.textContent = processingText;
            });

            xhr.onload = () => {
                // Remplace la page entière par la réponse du serveur (résultat, lien de
                // téléchargement, ou message d'erreur) — équivalent d'une navigation classique.
                document.open();
                document.write(xhr.responseText);
                document.close();
            };

            xhr.onerror = () => {
                btn.disabled = false;
                btn.classList.remove('loading');
                fill.classList.remove('indeterminate');
                fill.style.width = '0%';
                label.textContent = originalLabel;
                jsError.textContent = networkErrorText;
                jsError.style.display = 'block';
            };

            xhr.send(new FormData(form));
        });
    </script>
</body>
</html>
