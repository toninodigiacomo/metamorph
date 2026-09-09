<?php
// Attend deux variables définies avant l'include :
// $activePage  -> 'convert', 'qrcode' ou 'comicinfo'
// $pageTitle   -> titre affiché dans l'onglet du navigateur
require_once __DIR__ . '/i18n.php';
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($LANG) ?>">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($pageTitle ?? 'Metamorph') ?></title>
    <link rel="icon" type="image/x-icon" href="favicon.ico">
    <link rel="shortcut icon" type="image/x-icon" href="favicon.ico">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@700&display=swap" rel="stylesheet">
    <style>
        :root {
            --accent: rgb(255, 90, 31);
            --accent-hover: rgb(224, 74, 20);
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: system-ui, sans-serif;
            color: #222;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Bandeau supérieur (marque + menu) */
        header {
            background: #000;
            color: #fff;
            padding: 14px 28px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }
        header .brand {
            display: flex;
            align-items: center;
            gap: 10px;
            font-family: 'Poppins', system-ui, sans-serif;
            font-weight: 700;
            font-size: 1.6rem;
        }
        header .brand img { height: 32px; width: 32px; }

        /* Menu en segments collés, aligné à droite */
        nav.tabs {
            display: flex;
        }
        nav.tabs a.tab {
            width: 150px;
            text-align: center;
            padding: 9px 12px;
            font-size: 0.9rem;
            font-weight: 600;
            text-decoration: none;
            color: #ccc;
            background: #1a1a1a;
            border-right: 1px solid #000;
            transition: background 0.15s ease, color 0.15s ease;
        }
        nav.tabs a.tab:first-child { border-radius: 6px 0 0 6px; }
        nav.tabs a.tab:last-child { border-right: none; border-radius: 0 6px 6px 0; }
        nav.tabs a.tab:hover:not(.active) { background: #2a2a2a; color: #fff; }
        nav.tabs a.tab.active {
            background: var(--accent);
            color: #fff;
        }

        /* Sélecteur de langue */
        .lang-switch { display: flex; gap: 6px; }
        .lang-switch a {
            font-size: 0.75rem;
            font-weight: 600;
            color: #777;
            text-decoration: none;
            padding: 2px 4px;
        }
        .lang-switch a:hover { color: #ccc; }
        .lang-switch a.active { color: var(--accent); }

        /* Zone principale sombre, carte centrée */
        main {
            flex: 1;
            background: #000;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 60px 20px;
        }

        .card {
            background: #fff;
            border-radius: 14px;
            padding: 32px;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.35);
        }

        label { display: block; color: var(--accent); font-weight: 600; margin-bottom: 6px; font-size: 0.9rem; }

        select, input[type=text] {
            display: block;
            width: 100%;
            padding: 8px 10px;
            margin: 0 0 20px;
            border: 1px solid #ddd;
            border-radius: 6px;
            background: #f7f7f7;
            font-size: 0.95rem;
        }

        button {
            background: var(--accent);
            color: #fff;
            border: none;
            padding: 12px 22px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.15s ease;
        }
        button:hover { background: var(--accent-hover); }
        button:disabled { background: #ccc; cursor: not-allowed; }
        button.btn-secondary {
            background: transparent;
            color: #888;
            border: 1px solid #ccc;
        }
        button.btn-secondary:hover { background: #f2f2f2; color: #555; }

        .message { margin-top: 20px; padding: 14px; border-radius: 8px; background: #f4f4f4; font-size: 0.9rem; }
        .download { display: inline-block; margin-top: 10px; font-weight: 600; color: var(--accent); }

        /* Zone de glisser-déposer (page Convertisseur) */
        .dropzone {
            border: 2px dashed #ccc;
            border-radius: 10px;
            padding: 28px 16px;
            text-align: center;
            color: #888;
            font-size: 0.9rem;
            cursor: pointer;
            margin-bottom: 24px;
            transition: border-color 0.15s ease, background 0.15s ease, color 0.15s ease;
        }
        .dropzone.dragover {
            border-color: var(--accent);
            background: rgba(255, 90, 31, 0.06);
            color: var(--accent);
        }
        .dropzone.has-file {
            border-style: solid;
            border-color: var(--accent);
            color: #222;
            font-weight: 600;
        }

        /* Aperçu QR code (page QR Générateur) */
        .qr-preview {
            display: flex;
            justify-content: center;
            margin: 4px 0 20px;
            min-height: 180px;
            align-items: center;
        }
        .qr-preview canvas { image-rendering: pixelated; }
        .qr-preview canvas,
        .qr-preview img {
            width: 220px !important;
            height: 220px !important;
        }
        .qr-placeholder { color: #bbb; font-size: 0.85rem; }

        /* Formulaire ComicInfo (page CBZ/CBR) */
        textarea {
            display: block;
            width: 100%;
            padding: 8px 10px;
            margin: 0 0 20px;
            border: 1px solid #ddd;
            border-radius: 6px;
            background: #f7f7f7;
            font-size: 0.9rem;
            font-family: inherit;
            resize: vertical;
        }
        .field-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0 16px;
        }
        .field-grid label { margin-top: 4px; }
        .section-title {
            font-weight: 700;
            color: #222;
            margin: 22px 0 10px;
            font-size: 0.95rem;
            border-bottom: 1px solid #eee;
            padding-bottom: 6px;
        }
        .section-title:first-of-type { margin-top: 0; }
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.75rem;
            font-weight: 700;
        }
        .badge.ok { background: #dff5e1; color: #1e7d32; }
        .badge.warn { background: #fff1e6; color: var(--accent); }
        .image-list {
            max-height: 160px;
            overflow-y: auto;
            border: 1px solid #eee;
            border-radius: 8px;
            padding: 8px 10px;
            margin-bottom: 16px;
            font-size: 0.82rem;
            color: #555;
        }
        .image-list div { display: flex; justify-content: space-between; padding: 2px 0; }

        /* Grille de vignettes avec suppression (page CBZ/CBR) */
        .thumb-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 12px;
            max-height: 420px;
            overflow-y: auto;
            padding: 4px 4px 16px;
            margin-bottom: 8px;
        }
        .thumb-card {
            border: 1px solid #eee;
            border-radius: 8px;
            overflow: hidden;
            background: #fafafa;
        }
        .thumb-card img {
            display: block;
            width: 100%;
            height: 130px;
            object-fit: cover;
            background: #eee;
        }
        .thumb-name {
            font-size: 0.72rem;
            color: #777;
            padding: 5px 6px 0;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .thumb-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 6px;
            padding: 4px 6px 6px;
        }
        .thumb-delete {
            display: flex;
            align-items: center;
            gap: 4px;
            font-size: 0.7rem;
            color: #c0392b;
            cursor: pointer;
        }
        .thumb-delete input { width: auto; margin: 0; }
        .notice {
            background: #fff8e1;
            border: 1px solid #ffe08a;
            color: #7a5c00;
            padding: 10px 12px;
            border-radius: 8px;
            font-size: 0.85rem;
            margin-bottom: 18px;
        }

        /* Pied de page */
        footer {
            background: #ececec;
            color: #777;
            text-align: center;
            padding: 14px;
            font-size: 0.85rem;
        }
    </style>
</head>
<body>
    <header>
        <div class="brand"><img src="assets/logo.png" alt="Metamorph"> Metamorph</div>
        <div style="display:flex; align-items:center; gap:16px;">
            <div class="lang-switch">
                <?php foreach (SUPPORTED_LANGS as $l): ?>
                    <a href="?lang=<?= $l ?>" class="<?= $l === $LANG ? 'active' : '' ?>"><?= strtoupper($l) ?></a>
                <?php endforeach; ?>
            </div>
            <nav class="tabs">
                <a href="index.php" class="tab <?= $activePage === 'convert' ? 'active' : '' ?>"><?= t('nav_convert') ?></a>
                <a href="qrcode.php" class="tab <?= $activePage === 'qrcode' ? 'active' : '' ?>"><?= t('nav_qrcode') ?></a>
                <a href="comicinfo.php" class="tab <?= $activePage === 'comicinfo' ? 'active' : '' ?>"><?= t('nav_comicinfo') ?></a>
            </nav>
        </div>
    </header>

    <main>
        <div class="card" style="max-width: <?= $cardWidth ?? 420 ?>px;">
