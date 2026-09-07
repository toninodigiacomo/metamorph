<?php
// Gestion des métadonnées CBZ/CBR — standard ComicInfo.xml (ComicRack)
require_once __DIR__ . '/includes/i18n.php';

$uploadsDir = __DIR__ . '/uploads';
$outputDir  = __DIR__ . '/output';
$workRoot   = $uploadsDir . '/cbz_work';

$message      = '';
$downloadFile = null;
$workId       = null;
$fields       = [];      // valeurs des champs ComicInfo pré-remplies
$images       = [];      // liste des images détectées [name, compliant]
$allCompliant = true;
$originalBase = '';
$originalWasCbr = false;

// Définition des champs ComicInfo.xml (standard ComicRack) regroupés par section
// Les libellés sont résolus via t() au moment de l'affichage.
$fieldSections = [
    'cbz_section_series' => [
        ['name' => 'Series', 'labelKey' => 'f_series', 'type' => 'text'],
        ['name' => 'Title', 'labelKey' => 'f_title', 'type' => 'text'],
        ['name' => 'Number', 'labelKey' => 'f_number', 'type' => 'text'],
        ['name' => 'Count', 'labelKey' => 'f_count', 'type' => 'text'],
        ['name' => 'Volume', 'labelKey' => 'f_volume', 'type' => 'text'],
        ['name' => 'Year', 'labelKey' => 'f_year', 'type' => 'text'],
        ['name' => 'Month', 'labelKey' => 'f_month', 'type' => 'text'],
        ['name' => 'Day', 'labelKey' => 'f_day', 'type' => 'text'],
    ],
    'cbz_section_team' => [
        ['name' => 'Writer', 'labelKey' => 'f_writer', 'type' => 'text'],
        ['name' => 'Penciller', 'labelKey' => 'f_penciller', 'type' => 'text'],
        ['name' => 'Inker', 'labelKey' => 'f_inker', 'type' => 'text'],
        ['name' => 'Colorist', 'labelKey' => 'f_colorist', 'type' => 'text'],
        ['name' => 'Letterer', 'labelKey' => 'f_letterer', 'type' => 'text'],
        ['name' => 'CoverArtist', 'labelKey' => 'f_coverartist', 'type' => 'text'],
        ['name' => 'Editor', 'labelKey' => 'f_editor', 'type' => 'text'],
    ],
    'cbz_section_publication' => [
        ['name' => 'Publisher', 'labelKey' => 'f_publisher', 'type' => 'text'],
        ['name' => 'Imprint', 'labelKey' => 'f_imprint', 'type' => 'text'],
        ['name' => 'Genre', 'labelKey' => 'f_genre', 'type' => 'text'],
        ['name' => 'LanguageISO', 'labelKey' => 'f_language', 'type' => 'text'],
        ['name' => 'Format', 'labelKey' => 'f_format', 'type' => 'text'],
        ['name' => 'Web', 'labelKey' => 'f_web', 'type' => 'text'],
        ['name' => 'AgeRating', 'labelKey' => 'f_agerating', 'type' => 'select', 'options' => [
            'Unknown', 'Everyone', 'Everyone 10+', 'G', 'Early Childhood', 'Kids to Adults',
            'PG', 'Teen', 'MA15+', 'M', 'R18+', 'Mature 17+', 'X18+', 'Adults Only 18+', 'Rating Pending',
        ]],
        ['name' => 'BlackAndWhite', 'labelKey' => 'f_bw', 'type' => 'select', 'options' => ['Unknown', 'No', 'Yes']],
        ['name' => 'Manga', 'labelKey' => 'f_manga', 'type' => 'select', 'options' => ['Unknown', 'No', 'Yes', 'YesAndRightToLeft']],
    ],
    'cbz_section_content' => [
        ['name' => 'Characters', 'labelKey' => 'f_characters', 'type' => 'text'],
        ['name' => 'Teams', 'labelKey' => 'f_teams', 'type' => 'text'],
        ['name' => 'Locations', 'labelKey' => 'f_locations', 'type' => 'text'],
        ['name' => 'StoryArc', 'labelKey' => 'f_storyarc', 'type' => 'text'],
        ['name' => 'SeriesGroup', 'labelKey' => 'f_seriesgroup', 'type' => 'text'],
        ['name' => 'ScanInformation', 'labelKey' => 'f_scaninfo', 'type' => 'text'],
        ['name' => 'Summary', 'labelKey' => 'f_summary', 'type' => 'textarea'],
        ['name' => 'Notes', 'labelKey' => 'f_notes', 'type' => 'textarea'],
    ],
];

// Note : les VALEURS des champs à choix (AgeRating, BlackAndWhite, Manga) restent en anglais
// dans le XML — ce sont des tokens standard reconnus par tous les lecteurs ComicRack,
// indépendamment de la langue de l'interface. Seuls les libellés de champs sont traduits.

// Extensions d'images reconnues et motif de conformité ComicRack (PXXXXX.ext)
const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
const COMPLIANT_PATTERN = '/^P\d{5}\.(jpg|jpeg|png|gif|webp)$/i';

function rrmdir(string $dir): void {
    if (!is_dir($dir)) return;
    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $path = $dir . '/' . $item;
        is_dir($path) ? rrmdir($path) : @unlink($path);
    }
    @rmdir($dir);
}

// Si l'archive n'a qu'un seul sous-dossier à sa racine, on "descend" dedans
function flattenSingleSubdir(string $dir): string {
    $entries = array_values(array_diff(scandir($dir), ['.', '..']));
    if (count($entries) === 1 && is_dir($dir . '/' . $entries[0])) {
        return flattenSingleSubdir($dir . '/' . $entries[0]);
    }
    return $dir;
}

function findComicInfoXml(string $dir): ?string {
    foreach (scandir($dir) as $entry) {
        if (strcasecmp($entry, 'ComicInfo.xml') === 0) {
            return $dir . '/' . $entry;
        }
    }
    return null;
}

function listImages(string $dir): array {
    $files = [];
    foreach (scandir($dir) as $entry) {
        if ($entry === '.' || $entry === '..') continue;
        $ext = strtolower(pathinfo($entry, PATHINFO_EXTENSION));
        if (in_array($ext, IMAGE_EXTENSIONS, true)) {
            $files[] = $entry;
        }
    }
    natsort($files);
    return array_values($files);
}

function zipDirectory(string $sourceDir, string $zipPath): bool {
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        return false;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($sourceDir, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        $localName = substr($file->getPathname(), strlen($sourceDir) + 1);
        // Ignore les fichiers/dossiers cachés (ex: .thumbs/, .state.json) — jamais dans le CBZ final
        if (strpos($localName, '.') === 0 || strpos($localName, '/.') !== false) {
            continue;
        }
        $zip->addFile($file->getPathname(), $localName);
    }
    $zip->close();
    return true;
}

// ---------------------------------------------------------------------
// ANNULATION : nettoie le dossier de travail temporaire et repart de zéro
// ---------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel' && !empty($_POST['work_id'])) {
    $id = basename($_POST['work_id']);
    rrmdir($workRoot . '/' . $id);
    $message = t('cbz_cancelled');
}

// ---------------------------------------------------------------------
// PHASE 1 : upload initial + analyse de l'archive
// ---------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'analyze' && isset($_FILES['comic'])) {
    $file = $_FILES['comic'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $message = t('cbz_err_upload', ['code' => $file['error']]);
    } else {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['cbz', 'cbr', 'zip', 'rar'], true)) {
            $message = t('cbz_err_type');
        } else {
            $id = uniqid('cbz_', true);
            $dir = $workRoot . '/' . $id;
            mkdir($dir, 0775, true);

            $tmpUpload = $dir . '/_source.' . $ext;
            move_uploaded_file($file['tmp_name'], $tmpUpload);

            $isRar = in_array($ext, ['cbr', 'rar'], true);
            $extractOk = false;

            if ($isRar) {
                exec(sprintf('unar -f -q -o %s %s 2>&1', escapeshellarg($dir), escapeshellarg($tmpUpload)), $out, $code);
                $extractOk = ($code === 0);
            } else {
                $zip = new ZipArchive();
                if ($zip->open($tmpUpload) === true) {
                    $zip->extractTo($dir);
                    $zip->close();
                    $extractOk = true;
                }
            }
            @unlink($tmpUpload);

            if (!$extractOk) {
                $message = t('cbz_err_extract');
                rrmdir($dir);
            } else {
                $effectiveDir = flattenSingleSubdir($dir);

                $xmlPath = findComicInfoXml($effectiveDir);
                if ($xmlPath) {
                    $xml = @simplexml_load_file($xmlPath);
                    if ($xml !== false) {
                        foreach ($xml as $key => $value) {
                            $fields[(string)$key] = (string)$value;
                        }
                    }
                }

                $images = listImages($effectiveDir);
                $allCompliant = true;
                foreach ($images as $img) {
                    if (!preg_match(COMPLIANT_PATTERN, $img)) {
                        $allCompliant = false;
                        break;
                    }
                }

                $originalBase = pathinfo($file['name'], PATHINFO_FILENAME);
                file_put_contents($dir . '/.state.json', json_encode([
                    'original_base'    => $originalBase,
                    'was_cbr'          => $isRar,
                    'effective_subdir' => substr($effectiveDir, strlen($dir)),
                ]));

                $workId = $id;
            }
        }
    }
}

// ---------------------------------------------------------------------
// PHASE 2 : sauvegarde des métadonnées (+ renommage optionnel des pages)
// ---------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save' && !empty($_POST['work_id'])) {
    $id  = basename($_POST['work_id']); // basename() = protection contre la traversée de chemin
    $dir = $workRoot . '/' . $id;
    $statePath = $dir . '/.state.json';

    if (!is_dir($dir) || !file_exists($statePath)) {
        $message = t('cbz_err_session');
    } else {
        $state = json_decode(file_get_contents($statePath), true);
        $effectiveDir = $dir . ($state['effective_subdir'] ?? '');

        // Suppression des pages cochées par l'utilisateur
        $toDelete = $_POST['delete'] ?? [];
        $deletedCount = 0;
        foreach ($toDelete as $name) {
            $safeName = basename($name);
            $path = $effectiveDir . '/' . $safeName;
            $ext = strtolower(pathinfo($safeName, PATHINFO_EXTENSION));
            // On ne supprime que des images listées, jamais autre chose (protection)
            if (in_array($ext, IMAGE_EXTENSIONS, true) && file_exists($path)) {
                @unlink($path);
                $deletedCount++;
            }
        }

        // Une suppression casse forcément la séquence : la renumérotation devient obligatoire
        $shouldRename = !empty($_POST['rename_pages']) || $deletedCount > 0;

        // Renommage des pages selon le standard ComicRack, si demandé (ou imposé par une suppression)
        if ($shouldRename) {
            $imgs = listImages($effectiveDir);
            $tmpMap = [];
            // Étape 1 : passage par des noms temporaires pour éviter toute collision
            foreach ($imgs as $i => $name) {
                $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                $tmpName = '__tmp_' . $i . '.' . $ext;
                rename($effectiveDir . '/' . $name, $effectiveDir . '/' . $tmpName);
                $tmpMap[$i] = $tmpName;
            }
            // Étape 2 : noms définitifs PXXXXX.ext
            foreach ($tmpMap as $i => $tmpName) {
                $ext = strtolower(pathinfo($tmpName, PATHINFO_EXTENSION));
                $finalName = sprintf('P%05d.%s', $i + 1, $ext);
                rename($effectiveDir . '/' . $tmpName, $effectiveDir . '/' . $finalName);
            }
        }

        // Reconstruction du ComicInfo.xml
        $pageCount = count(listImages($effectiveDir));
        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = true;
        $root = $doc->createElement('ComicInfo');
        $root->setAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $root->setAttribute('xmlns:xsd', 'http://www.w3.org/2001/XMLSchema');
        $doc->appendChild($root);

        foreach ($fieldSections as $sectionFields) {
            foreach ($sectionFields as $f) {
                $value = trim($_POST['field'][$f['name']] ?? '');
                if ($value !== '') {
                    $el = $doc->createElement($f['name']);
                    $el->appendChild($doc->createTextNode($value));
                    $root->appendChild($el);
                }
            }
        }
        $pageCountEl = $doc->createElement('PageCount', (string)$pageCount);
        $root->appendChild($pageCountEl);

        // Supprime l'ancien ComicInfo.xml s'il existe (casse quelconque) puis écrit le nouveau
        $existing = findComicInfoXml($effectiveDir);
        if ($existing) @unlink($existing);
        $doc->save($effectiveDir . '/ComicInfo.xml');

        // Recompression en CBZ (le RAR n'est pas réinscriptible sans outil propriétaire)
        $outBase = preg_replace('/[^A-Za-z0-9._-]+/', '_', $state['original_base'] ?? 'comic');
        $outName = $outBase . '.cbz';
        $outPath = $outputDir . '/' . $outName;

        if (zipDirectory($effectiveDir, $outPath)) {
            $downloadFile = $outName;
            $message = t('cbz_saved');
            if ($deletedCount > 0) {
                $message .= ' ' . t('cbz_deleted_count', ['n' => $deletedCount]);
            }
            if ($state['was_cbr'] ?? false) {
                $message .= t('cbz_was_cbr');
            }
            rrmdir($dir); // nettoyage du dossier de travail temporaire
        } else {
            $message = t('cbz_err_zip');
        }
    }
}

// Téléchargement du fichier produit
if (isset($_GET['download'])) {
    $safeName = basename($_GET['download']);
    $path = $outputDir . '/' . $safeName;
    if (file_exists($path)) {
        header('Content-Type: application/vnd.comicbook+zip');
        header('Content-Disposition: attachment; filename="' . $safeName . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }
}

$activePage = 'comicinfo';
$pageTitle  = 'Metamorph — ' . t('nav_comicinfo');
$cardWidth  = 760;
include __DIR__ . '/includes/header.php';
?>
            <?php if (!$workId): ?>

                <!-- PHASE 1 : import du fichier -->
                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="analyze">
                    <label for="comic"><?= t('cbz_file_label') ?></label>
                    <div id="dropzone" class="dropzone">
                        <span id="dropzone-text"><?= t('cbz_dropzone') ?></span>
                    </div>
                    <input type="file" name="comic" id="comic" accept=".cbz,.cbr,.zip,.rar" required hidden>
                    <button type="submit"><?= t('cbz_btn_analyze') ?></button>
                </form>

            <?php else: ?>

                <!-- PHASE 2 : édition des métadonnées -->
                <div class="notice">
                    <?= t('cbz_pages_detected', ['n' => count($images)]) ?> —
                    <?php if ($allCompliant): ?>
                        <span class="badge ok"><?= t('cbz_badge_compliant') ?></span>
                    <?php else: ?>
                        <span class="badge warn"><?= t('cbz_badge_noncompliant') ?></span> <?= t('cbz_expected_format') ?>
                    <?php endif; ?>
                </div>

                <div class="thumb-grid">
                    <?php foreach ($images as $img): ?>
                        <div class="thumb-card">
                            <img src="thumb.php?work_id=<?= urlencode($workId) ?>&file=<?= urlencode($img) ?>" alt="<?= htmlspecialchars($img) ?>" loading="lazy">
                            <div class="thumb-name"><?= htmlspecialchars($img) ?></div>
                            <div class="thumb-row">
                                <label class="thumb-delete">
                                    <input type="checkbox" name="delete[]" value="<?= htmlspecialchars($img) ?>" form="comicinfo-form">
                                    <?= t('cbz_delete_label') ?>
                                </label>
                                <?php if (preg_match(COMPLIANT_PATTERN, $img)): ?>
                                    <span class="badge ok"><?= t('cbz_badge_ok') ?></span>
                                <?php else: ?>
                                    <span class="badge warn"><?= t('cbz_badge_rename') ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <form method="post" id="comicinfo-form">
                    <input type="hidden" name="work_id" value="<?= htmlspecialchars($workId) ?>">

                    <?php foreach ($fieldSections as $sectionKey => $sectionFields): ?>
                        <div class="section-title"><?= htmlspecialchars(t($sectionKey)) ?></div>
                        <div class="field-grid">
                            <?php foreach ($sectionFields as $f): ?>
                                <div style="<?= $f['type'] === 'textarea' ? 'grid-column: 1 / -1;' : '' ?>">
                                    <label for="f_<?= $f['name'] ?>"><?= htmlspecialchars(t($f['labelKey'])) ?></label>
                                    <?php if ($f['type'] === 'textarea'): ?>
                                        <textarea name="field[<?= $f['name'] ?>]" id="f_<?= $f['name'] ?>" rows="3"><?= htmlspecialchars($fields[$f['name']] ?? '') ?></textarea>
                                    <?php elseif ($f['type'] === 'select'): ?>
                                        <select name="field[<?= $f['name'] ?>]" id="f_<?= $f['name'] ?>">
                                            <?php foreach ($f['options'] as $opt): ?>
                                                <option value="<?= htmlspecialchars($opt) ?>" <?= ($fields[$f['name']] ?? '') === $opt ? 'selected' : '' ?>><?= htmlspecialchars($opt) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php else: ?>
                                        <input type="text" name="field[<?= $f['name'] ?>]" id="f_<?= $f['name'] ?>" value="<?= htmlspecialchars($fields[$f['name']] ?? '') ?>">
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>

                    <?php if (!$allCompliant): ?>
                        <label style="display:flex; align-items:center; gap:8px; color:#222; font-weight:600; margin: 4px 0 20px;">
                            <input type="checkbox" name="rename_pages" value="1" checked style="width:auto; margin:0;">
                            <?= t('cbz_rename_checkbox') ?>
                        </label>
                    <?php endif; ?>
                    <p style="font-size:0.8rem; color:#888; margin:-10px 0 20px;">
                        <?= t('cbz_rename_note') ?>
                    </p>

                    <div style="display:flex; gap:10px;">
                        <button type="submit" name="action" value="save"><?= t('cbz_btn_save') ?></button>
                        <button type="submit" name="action" value="cancel" class="btn-secondary"><?= t('cbz_btn_cancel') ?></button>
                    </div>
                </form>

            <?php endif; ?>

            <?php if ($message): ?>
                <div class="message">
                    <?= htmlspecialchars($message) ?>
                    <?php if ($downloadFile): ?>
                        <br><a class="download" href="?download=<?= urlencode($downloadFile) ?>"><?= t('cbz_download') ?></a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
    <script>
        const dropzone = document.getElementById('dropzone');
        const fileInput = document.getElementById('comic');
        if (dropzone && fileInput) {
            const dropzoneText = document.getElementById('dropzone-text');
            const showFileName = (file) => {
                if (file) {
                    dropzoneText.textContent = file.name;
                    dropzone.classList.add('has-file');
                }
            };
            dropzone.addEventListener('click', () => fileInput.click());
            fileInput.addEventListener('change', () => showFileName(fileInput.files[0]));
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
        }
    </script>
</body>
</html>
