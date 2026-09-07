<?php
// Conversion de documents — v1 : PDF -> EPUB (via Calibre / ebook-convert)
require_once __DIR__ . '/includes/i18n.php';

$uploadsDir = __DIR__ . '/uploads';
$outputDir  = __DIR__ . '/output';

$message = '';
$downloadFile = null;

// Table des conversions supportées : extension d'entrée => [extension de sortie, label]
$conversions = [
    'pdf_to_epub' => ['from' => 'pdf', 'to' => 'epub', 'label' => t('conv_option_pdf_epub')],
];

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
                    // Appel de ebook-convert (Calibre), en échappant les chemins
                    $cmd = sprintf(
                        'ebook-convert %s %s 2>&1',
                        escapeshellarg($inputPath),
                        escapeshellarg($outputPath)
                    );
                    exec($cmd, $outputLines, $returnCode);

                    if ($returnCode === 0 && file_exists($outputPath)) {
                        $downloadFile = basename($outputPath);
                        $message = t('conv_success');
                    } else {
                        $message = t('conv_failed') . "<br><pre>" .
                            htmlspecialchars(implode("\n", $outputLines)) . "</pre>";
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
        header('Content-Type: application/epub+zip');
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
