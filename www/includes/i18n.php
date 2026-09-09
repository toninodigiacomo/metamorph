<?php
// Système de traduction — détection automatique via Accept-Language du navigateur,
// avec possibilité de forcer manuellement via ?lang=xx (persisté 1 an en cookie).

const SUPPORTED_LANGS = ['en', 'fr', 'it', 'de'];
const DEFAULT_LANG = 'en';

function detectLang(): string {
    // 1) Choix manuel explicite dans l'URL -> on le mémorise en cookie
    if (isset($_GET['lang']) && in_array($_GET['lang'], SUPPORTED_LANGS, true)) {
        setcookie('lang', $_GET['lang'], time() + 31536000, '/');
        return $_GET['lang'];
    }

    // 2) Choix déjà mémorisé précédemment
    if (isset($_COOKIE['lang']) && in_array($_COOKIE['lang'], SUPPORTED_LANGS, true)) {
        return $_COOKIE['lang'];
    }

    // 3) Détection depuis l'en-tête Accept-Language du navigateur
    $header = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
    if ($header !== '') {
        foreach (explode(',', $header) as $part) {
            $code = strtolower(substr(trim(explode(';', $part)[0]), 0, 2));
            if (in_array($code, SUPPORTED_LANGS, true)) {
                return $code;
            }
        }
    }

    // 4) Repli par défaut
    return DEFAULT_LANG;
}

$LANG = detectLang();

$TRANSLATIONS = [

    // --- Menu / commun ---
    'nav_convert'    => ['en' => 'Converter',      'fr' => 'Convertisseur',   'it' => 'Convertitore',    'de' => 'Konverter'],
    'nav_qrcode'     => ['en' => 'QR Generator',    'fr' => 'QR Générateur',  'it' => 'Generatore QR',   'de' => 'QR-Generator'],
    'nav_comicinfo'  => ['en' => 'CBZ/CBR',         'fr' => 'CBZ/CBR',         'it' => 'CBZ/CBR',         'de' => 'CBZ/CBR'],

    // --- Page Convertisseur ---
    'conv_type_label'   => ['en' => 'Conversion type',      'fr' => 'Type de conversion',      'it' => 'Tipo di conversione',   'de' => 'Konvertierungstyp'],
    'conv_option_pdf_epub' => ['en' => 'PDF to EPUB',       'fr' => 'PDF vers EPUB',            'it' => 'PDF in EPUB',           'de' => 'PDF zu EPUB'],
    'conv_option_png_ico'  => ['en' => 'PNG to ICO',        'fr' => 'PNG vers ICO',              'it' => 'PNG in ICO',            'de' => 'PNG zu ICO'],
    'conv_option_svg_png'  => ['en' => 'SVG to PNG',        'fr' => 'SVG vers PNG',              'it' => 'SVG in PNG',            'de' => 'SVG zu PNG'],
    'conv_file_label'   => ['en' => 'File to convert',      'fr' => 'Fichier à convertir',      'it' => 'File da convertire',    'de' => 'Zu konvertierende Datei'],
    'conv_dropzone'     => [
        'en' => 'Drag & drop a file here<br>or click to browse',
        'fr' => 'Glissez-déposez un fichier ici<br>ou cliquez pour parcourir',
        'it' => 'Trascina un file qui<br>o clicca per selezionarlo',
        'de' => 'Datei hierher ziehen<br>oder klicken zum Auswählen',
    ],
    'conv_btn_convert'  => ['en' => 'Convert', 'fr' => 'Convertir', 'it' => 'Converti', 'de' => 'Konvertieren'],
    'conv_err_unknown_type' => ['en' => 'Unknown conversion type.', 'fr' => 'Type de conversion inconnu.', 'it' => 'Tipo di conversione sconosciuto.', 'de' => 'Unbekannter Konvertierungstyp.'],
    'conv_err_upload'   => [
        'en' => 'Upload error (code {code}).',
        'fr' => "Erreur lors de l'upload (code {code}).",
        'it' => 'Errore di caricamento (codice {code}).',
        'de' => 'Fehler beim Hochladen (Code {code}).',
    ],
    'conv_err_ext'      => [
        'en' => 'The uploaded file must be a .{ext}',
        'fr' => 'Le fichier envoyé doit être un .{ext}',
        'it' => 'Il file caricato deve essere un .{ext}',
        'de' => 'Die hochgeladene Datei muss eine .{ext}-Datei sein',
    ],
    'conv_success'      => ['en' => 'Conversion successful!', 'fr' => 'Conversion réussie !', 'it' => 'Conversione riuscita!', 'de' => 'Konvertierung erfolgreich!'],
    'conv_failed'       => ['en' => 'Conversion failed.', 'fr' => 'Échec de la conversion.', 'it' => 'Conversione non riuscita.', 'de' => 'Konvertierung fehlgeschlagen.'],
    'conv_move_failed'  => [
        'en' => 'Unable to move the uploaded file.',
        'fr' => 'Impossible de déplacer le fichier envoyé.',
        'it' => 'Impossibile spostare il file caricato.',
        'de' => 'Die hochgeladene Datei konnte nicht verschoben werden.',
    ],
    'conv_download'     => ['en' => 'Download the converted file', 'fr' => 'Télécharger le fichier converti', 'it' => 'Scarica il file convertito', 'de' => 'Konvertierte Datei herunterladen'],

    // --- Page QR Générateur ---
    'qr_text_label'     => ['en' => 'Text or URL', 'fr' => 'Texte ou URL', 'it' => 'Testo o URL', 'de' => 'Text oder URL'],
    'qr_placeholder'    => [
        'en' => 'https://example.com or any text',
        'fr' => 'https://exemple.ch ou n\'importe quel texte',
        'it' => 'https://esempio.it o qualsiasi testo',
        'de' => 'https://beispiel.de oder ein beliebiger Text',
    ],
    'qr_preview_placeholder' => ['en' => 'The QR code will appear here', 'fr' => 'Le QR code apparaîtra ici', 'it' => 'Il codice QR apparirà qui', 'de' => 'Der QR-Code erscheint hier'],
    'qr_download'       => ['en' => 'Download QR code (PNG)', 'fr' => 'Télécharger le QR code (PNG)', 'it' => 'Scarica il codice QR (PNG)', 'de' => 'QR-Code herunterladen (PNG)'],

    // --- Page CBZ/CBR : import ---
    'cbz_file_label'    => ['en' => 'CBZ or CBR file', 'fr' => 'Fichier CBZ ou CBR', 'it' => 'File CBZ o CBR', 'de' => 'CBZ- oder CBR-Datei'],
    'cbz_dropzone'      => [
        'en' => 'Drag & drop a CBZ/CBR here<br>or click to browse',
        'fr' => 'Glissez-déposez un CBZ/CBR ici<br>ou cliquez pour parcourir',
        'it' => 'Trascina un CBZ/CBR qui<br>o clicca per selezionarlo',
        'de' => 'CBZ/CBR hierher ziehen<br>oder klicken zum Auswählen',
    ],
    'cbz_btn_analyze'   => ['en' => 'Analyze file', 'fr' => 'Analyser le fichier', 'it' => 'Analizza il file', 'de' => 'Datei analysieren'],
    'cbz_err_upload'    => [
        'en' => 'Upload error (code {code}).',
        'fr' => "Erreur lors de l'upload (code {code}).",
        'it' => 'Errore di caricamento (codice {code}).',
        'de' => 'Fehler beim Hochladen (Code {code}).',
    ],
    'cbz_err_type'      => ['en' => 'The file must be a .cbz or .cbr', 'fr' => 'Le fichier doit être un .cbz ou .cbr.', 'it' => 'Il file deve essere un .cbz o .cbr.', 'de' => 'Die Datei muss eine .cbz- oder .cbr-Datei sein.'],
    'cbz_err_extract'   => ['en' => 'Unable to extract the archive.', 'fr' => "Impossible d'extraire l'archive.", 'it' => "Impossibile estrarre l'archivio.", 'de' => 'Das Archiv konnte nicht entpackt werden.'],
    'cbz_err_session'   => [
        'en' => 'Editing session not found or expired. Please re-import the file.',
        'fr' => "Session d'édition introuvable ou expirée. Merci de réimporter le fichier.",
        'it' => 'Sessione di modifica non trovata o scaduta. Reimporta il file.',
        'de' => 'Bearbeitungssitzung nicht gefunden oder abgelaufen. Bitte Datei erneut importieren.',
    ],
    'cbz_err_zip'       => ['en' => 'Failed to repack the archive.', 'fr' => "Échec de la recompression de l'archive.", 'it' => "Impossibile ricomprimere l'archivio.", 'de' => 'Das Archiv konnte nicht neu gepackt werden.'],
    'cbz_cancelled'     => ['en' => 'Editing cancelled.', 'fr' => 'Édition annulée.', 'it' => 'Modifica annullata.', 'de' => 'Bearbeitung abgebrochen.'],

    // --- Page CBZ/CBR : analyse ---
    'cbz_pages_detected'    => ['en' => '{n} page(s) detected', 'fr' => '{n} page(s) détectée(s)', 'it' => '{n} pagina/e rilevata/e', 'de' => '{n} Seite(n) erkannt'],
    'cbz_badge_compliant'   => ['en' => 'ComicRack compliant', 'fr' => 'conforme ComicRack', 'it' => 'conforme a ComicRack', 'de' => 'ComicRack-konform'],
    'cbz_badge_noncompliant'=> ['en' => 'non-standard naming', 'fr' => 'nommage non conforme', 'it' => 'denominazione non conforme', 'de' => 'nicht konforme Benennung'],
    'cbz_expected_format'   => [
        'en' => '(expected: P00001.jpg, P00002.jpg, …)',
        'fr' => '(attendu : P00001.jpg, P00002.jpg, …)',
        'it' => '(atteso: P00001.jpg, P00002.jpg, …)',
        'de' => '(erwartet: P00001.jpg, P00002.jpg, …)',
    ],
    'cbz_badge_ok'          => ['en' => 'OK', 'fr' => 'OK', 'it' => 'OK', 'de' => 'OK'],
    'cbz_badge_rename'      => ['en' => 'to rename', 'fr' => 'à renommer', 'it' => 'da rinominare', 'de' => 'umbenennen'],
    'cbz_delete_label'      => ['en' => 'delete', 'fr' => 'à supprimer', 'it' => 'elimina', 'de' => 'löschen'],

    // --- Page CBZ/CBR : sections du formulaire ---
    'cbz_section_series'    => ['en' => 'Series & numbering', 'fr' => 'Série & numérotation', 'it' => 'Serie e numerazione', 'de' => 'Serie & Nummerierung'],
    'cbz_section_team'      => ['en' => 'Creative team', 'fr' => 'Équipe créative', 'it' => 'Team creativo', 'de' => 'Kreativteam'],
    'cbz_section_publication' => ['en' => 'Publication', 'fr' => 'Publication', 'it' => 'Pubblicazione', 'de' => 'Veröffentlichung'],
    'cbz_section_content'   => ['en' => 'Content', 'fr' => 'Contenu', 'it' => 'Contenuto', 'de' => 'Inhalt'],

    // --- Page CBZ/CBR : libellés de champs ---
    'f_series'      => ['en' => 'Series', 'fr' => 'Série', 'it' => 'Serie', 'de' => 'Serie'],
    'f_title'       => ['en' => 'Title', 'fr' => 'Titre', 'it' => 'Titolo', 'de' => 'Titel'],
    'f_number'      => ['en' => 'Number', 'fr' => 'Numéro', 'it' => 'Numero', 'de' => 'Nummer'],
    'f_count'       => ['en' => 'Total issue count', 'fr' => 'Nombre total de numéros', 'it' => 'Numero totale di numeri', 'de' => 'Gesamtzahl der Ausgaben'],
    'f_volume'      => ['en' => 'Volume', 'fr' => 'Volume', 'it' => 'Volume', 'de' => 'Band'],
    'f_year'        => ['en' => 'Year', 'fr' => 'Année', 'it' => 'Anno', 'de' => 'Jahr'],
    'f_month'       => ['en' => 'Month', 'fr' => 'Mois', 'it' => 'Mese', 'de' => 'Monat'],
    'f_day'         => ['en' => 'Day', 'fr' => 'Jour', 'it' => 'Giorno', 'de' => 'Tag'],
    'f_writer'      => ['en' => 'Writer', 'fr' => 'Scénariste', 'it' => 'Sceneggiatore', 'de' => 'Autor'],
    'f_penciller'   => ['en' => 'Penciller', 'fr' => 'Dessinateur', 'it' => 'Disegnatore', 'de' => 'Zeichner'],
    'f_inker'       => ['en' => 'Inker', 'fr' => 'Encreur', 'it' => 'Inchiostratore', 'de' => 'Inker'],
    'f_colorist'    => ['en' => 'Colorist', 'fr' => 'Coloriste', 'it' => 'Colorista', 'de' => 'Kolorist'],
    'f_letterer'    => ['en' => 'Letterer', 'fr' => 'Lettreur', 'it' => 'Letterista', 'de' => 'Letterer'],
    'f_coverartist' => ['en' => 'Cover artist', 'fr' => 'Artiste couverture', 'it' => 'Artista copertina', 'de' => 'Cover-Künstler'],
    'f_editor'      => ['en' => 'Editor (person)', 'fr' => 'Éditeur (personne)', 'it' => 'Redattore (persona)', 'de' => 'Redakteur (Person)'],
    'f_publisher'   => ['en' => 'Publisher', 'fr' => 'Éditeur (maison)', 'it' => 'Editore (casa editrice)', 'de' => 'Verlag'],
    'f_imprint'     => ['en' => 'Imprint', 'fr' => 'Label', 'it' => 'Marchio editoriale', 'de' => 'Imprint'],
    'f_genre'       => ['en' => 'Genre', 'fr' => 'Genre', 'it' => 'Genere', 'de' => 'Genre'],
    'f_language'    => ['en' => 'Language (ISO code, e.g. en)', 'fr' => 'Langue (code ISO, ex: fr)', 'it' => 'Lingua (codice ISO, es: it)', 'de' => 'Sprache (ISO-Code, z.B. de)'],
    'f_format'      => ['en' => 'Format', 'fr' => 'Format', 'it' => 'Formato', 'de' => 'Format'],
    'f_web'         => ['en' => 'Web link', 'fr' => 'Lien web', 'it' => 'Link web', 'de' => 'Web-Link'],
    'f_agerating'   => ['en' => 'Age rating', 'fr' => "Classification d'âge", 'it' => 'Classificazione per età', 'de' => 'Altersfreigabe'],
    'f_bw'          => ['en' => 'Black & white', 'fr' => 'Noir et blanc', 'it' => 'Bianco e nero', 'de' => 'Schwarz-Weiß'],
    'f_manga'       => ['en' => 'Manga', 'fr' => 'Manga', 'it' => 'Manga', 'de' => 'Manga'],
    'f_characters'  => ['en' => 'Characters', 'fr' => 'Personnages', 'it' => 'Personaggi', 'de' => 'Charaktere'],
    'f_teams'       => ['en' => 'Teams', 'fr' => 'Équipes', 'it' => 'Squadre', 'de' => 'Teams'],
    'f_locations'   => ['en' => 'Locations', 'fr' => 'Lieux', 'it' => 'Luoghi', 'de' => 'Orte'],
    'f_storyarc'    => ['en' => 'Story arc', 'fr' => 'Arc narratif', 'it' => 'Arco narrativo', 'de' => 'Handlungsbogen'],
    'f_seriesgroup' => ['en' => 'Series group', 'fr' => 'Groupe de séries', 'it' => 'Gruppo di serie', 'de' => 'Seriengruppe'],
    'f_scaninfo'    => ['en' => 'Scan information', 'fr' => 'Informations de scan', 'it' => 'Informazioni sulla scansione', 'de' => 'Scan-Informationen'],
    'f_summary'     => ['en' => 'Summary', 'fr' => 'Résumé', 'it' => 'Riepilogo', 'de' => 'Zusammenfassung'],
    'f_notes'       => ['en' => 'Notes', 'fr' => 'Notes', 'it' => 'Note', 'de' => 'Notizen'],

    // --- Page CBZ/CBR : actions & messages ---
    'cbz_rename_checkbox' => [
        'en' => 'Rename pages to the ComicRack standard (P00001, P00002, …)',
        'fr' => 'Renommer les pages selon le standard ComicRack (P00001, P00002, …)',
        'it' => 'Rinomina le pagine secondo lo standard ComicRack (P00001, P00002, …)',
        'de' => 'Seiten nach ComicRack-Standard umbenennen (P00001, P00002, …)',
    ],
    'cbz_rename_note'    => [
        'en' => 'Note: if you check "delete" on one or more pages, full renumbering will be applied automatically, even if not checked above.',
        'fr' => 'Note : si vous cochez « à supprimer » sur une ou plusieurs pages, la renumérotation complète sera appliquée automatiquement, même si elle n\'est pas cochée ci-dessus.',
        'it' => 'Nota: se selezioni "elimina" su una o più pagine, la rinumerazione completa verrà applicata automaticamente, anche se non selezionata sopra.',
        'de' => 'Hinweis: Wird bei einer oder mehreren Seiten „löschen“ angehakt, erfolgt automatisch eine vollständige Neunummerierung, auch wenn dies oben nicht ausgewählt wurde.',
    ],
    'cbz_btn_save'   => ['en' => 'Save to CBZ', 'fr' => 'Enregistrer dans le CBZ', 'it' => 'Salva nel CBZ', 'de' => 'Im CBZ speichern'],
    'cbz_btn_cancel' => ['en' => 'Cancel', 'fr' => 'Annuler', 'it' => 'Annulla', 'de' => 'Abbrechen'],
    'cbz_saved'      => ['en' => 'Metadata saved successfully.', 'fr' => 'Métadonnées enregistrées avec succès.', 'it' => 'Metadati salvati con successo.', 'de' => 'Metadaten erfolgreich gespeichert.'],
    'cbz_deleted_count' => [
        'en' => '{n} page(s) deleted.',
        'fr' => '{n} page(s) supprimée(s).',
        'it' => '{n} pagina/e eliminata/e.',
        'de' => '{n} Seite(n) gelöscht.',
    ],
    'cbz_was_cbr'    => [
        'en' => ' The original file was a CBR: the result is provided as CBZ.',
        'fr' => " Le fichier d'origine était un CBR : le résultat est fourni en CBZ.",
        'it' => " Il file originale era un CBR: il risultato è fornito in CBZ.",
        'de' => ' Die Originaldatei war eine CBR-Datei: Das Ergebnis wird als CBZ bereitgestellt.',
    ],
    'cbz_download'   => ['en' => 'Download the file', 'fr' => 'Télécharger le fichier', 'it' => 'Scarica il file', 'de' => 'Datei herunterladen'],
];

/**
 * Traduit une clé dans la langue courante, avec repli sur l'anglais puis sur la clé elle-même.
 * $vars permet de remplacer des jetons {nom} dans la chaîne, ex: t('foo', ['n' => 3]).
 */
function t(string $key, array $vars = []): string {
    global $TRANSLATIONS, $LANG;
    $str = $TRANSLATIONS[$key][$LANG] ?? $TRANSLATIONS[$key][DEFAULT_LANG] ?? $key;
    foreach ($vars as $name => $value) {
        $str = str_replace('{' . $name . '}', (string)$value, $str);
    }
    return $str;
}
