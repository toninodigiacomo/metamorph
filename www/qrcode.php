<?php
require_once __DIR__ . '/includes/i18n.php';
$activePage = 'qrcode';
$pageTitle  = 'Metamorph — ' . t('nav_qrcode');
include __DIR__ . '/includes/header.php';
?>
            <form id="qr-form" onsubmit="return false;">
                <label for="qr-text"><?= t('qr_text_label') ?></label>
                <input type="text" id="qr-text" placeholder="<?= htmlspecialchars(t('qr_placeholder')) ?>" autocomplete="off">

                <label for="qr-style"><?= t('qr_style_label') ?></label>
                <select id="qr-style">
                    <option value="square"><?= t('qr_style_square') ?></option>
                    <option value="dots"><?= t('qr_style_dots') ?></option>
                    <option value="rounded"><?= t('qr_style_rounded') ?></option>
                    <option value="classy"><?= t('qr_style_classy') ?></option>
                    <option value="extra-rounded"><?= t('qr_style_extra_rounded') ?></option>
                </select>

                <label for="qr-logo"><?= t('qr_logo_label') ?></label>
                <div style="display:flex; align-items:center; gap:10px; margin-bottom:20px;">
                    <input type="file" id="qr-logo" accept="image/*" style="width:auto; margin:0;">
                    <a href="#" id="qr-logo-remove" style="display:none; font-size:0.8rem; color:#c0392b; text-decoration:none; white-space:nowrap;"><?= t('qr_logo_remove') ?></a>
                </div>

                <div class="qr-preview" id="qr-preview">
                    <span class="qr-placeholder" id="qr-placeholder-text"><?= t('qr_preview_placeholder') ?></span>
                </div>

                <button type="button" id="qr-download" disabled><?= t('qr_download') ?></button>
            </form>
<?php include __DIR__ . '/includes/footer.php'; ?>
    <script src="https://unpkg.com/qr-code-styling@1.5.0/lib/qr-code-styling.js"></script>
    <script>
        const textInput    = document.getElementById('qr-text');
        const styleSelect  = document.getElementById('qr-style');
        const logoInput    = document.getElementById('qr-logo');
        const logoRemove   = document.getElementById('qr-logo-remove');
        const preview      = document.getElementById('qr-preview');
        const downloadBtn  = document.getElementById('qr-download');
        const placeholderHtml = <?= json_encode('<span class="qr-placeholder">' . t('qr_preview_placeholder') . '</span>') ?>;

        // Taille de rendu interne élevée pour un export net (affichée en 220px via CSS)
        const RENDER_SIZE = 900;

        let qr = null;
        let logoDataUrl = null;
        let debounceTimer = null;

        function renderQr(value) {
            preview.innerHTML = '';

            if (!value.trim()) {
                preview.innerHTML = placeholderHtml;
                downloadBtn.disabled = true;
                qr = null;
                return;
            }

            const holder = document.createElement('div');
            preview.appendChild(holder);

            const options = {
                width: RENDER_SIZE,
                height: RENDER_SIZE,
                type: 'canvas',
                data: value,
                dotsOptions: {
                    color: '#000000',
                    type: styleSelect.value
                },
                backgroundOptions: {
                    color: '#ffffff'
                },
                qrOptions: {
                    // Correction d'erreur renforcée dès qu'une image est présente,
                    // pour rester scannable malgré la zone masquée au centre.
                    errorCorrectionLevel: logoDataUrl ? 'H' : 'Q'
                }
            };

            if (logoDataUrl) {
                options.image = logoDataUrl;
                options.imageOptions = {
                    margin: 8,
                    imageSize: 0.4,
                    hideBackgroundDots: true,
                    crossOrigin: 'anonymous'
                };
            }

            qr = new QRCodeStyling(options);
            qr.append(holder);

            downloadBtn.disabled = false;
        }

        function scheduleRender() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => renderQr(textInput.value), 200);
        }

        textInput.addEventListener('input', scheduleRender);
        styleSelect.addEventListener('change', scheduleRender);

        logoInput.addEventListener('change', () => {
            const file = logoInput.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = () => {
                logoDataUrl = reader.result;
                logoRemove.style.display = 'inline';
                scheduleRender();
            };
            reader.readAsDataURL(file);
        });

        logoRemove.addEventListener('click', (e) => {
            e.preventDefault();
            logoDataUrl = null;
            logoInput.value = '';
            logoRemove.style.display = 'none';
            scheduleRender();
        });

        downloadBtn.addEventListener('click', () => {
            if (!qr) return;
            qr.download({ name: 'qrcode', extension: 'png' });
        });
    </script>
</body>
</html>
