<?php
require_once __DIR__ . '/includes/i18n.php';
$activePage = 'qrcode';
$pageTitle  = 'Metamorph — ' . t('nav_qrcode');
include __DIR__ . '/includes/header.php';
?>
            <form id="qr-form" onsubmit="return false;">
                <label for="qr-text"><?= t('qr_text_label') ?></label>
                <input type="text" id="qr-text" placeholder="<?= htmlspecialchars(t('qr_placeholder')) ?>" autocomplete="off">

                <div class="qr-preview" id="qr-preview">
                    <span class="qr-placeholder" id="qr-placeholder-text"><?= t('qr_preview_placeholder') ?></span>
                </div>

                <button type="button" id="qr-download" disabled><?= t('qr_download') ?></button>
            </form>
<?php include __DIR__ . '/includes/footer.php'; ?>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script>
        const textInput   = document.getElementById('qr-text');
        const preview     = document.getElementById('qr-preview');
        const downloadBtn = document.getElementById('qr-download');
        const placeholderHtml = <?= json_encode('<span class="qr-placeholder">' . t('qr_preview_placeholder') . '</span>') ?>;

        // Taille de rendu interne élevée pour un export net (affichée en 220px via CSS)
        const RENDER_SIZE = 1024;

        let qr = null;
        let debounceTimer = null;

        function renderQr(value) {
            preview.innerHTML = '';

            if (!value.trim()) {
                preview.innerHTML = placeholderHtml;
                downloadBtn.disabled = true;
                return;
            }

            const holder = document.createElement('div');
            preview.appendChild(holder);

            qr = new QRCode(holder, {
                text: value,
                width: RENDER_SIZE,
                height: RENDER_SIZE,
                correctLevel: QRCode.CorrectLevel.H
            });

            // La taille d'affichage (220px) est forcée en CSS sur canvas ET img.
            // La librairie convertit le canvas en <img> de façon asynchrone puis
            // masque le canvas elle-même — on laisse faire, mais par sécurité on
            // s'assure après coup qu'un seul élément reste visible.
            setTimeout(() => {
                const elements = holder.querySelectorAll('canvas, img');
                elements.forEach((el, i) => {
                    el.style.display = (i === elements.length - 1) ? 'block' : 'none';
                });
            }, 60);

            downloadBtn.disabled = false;
        }

        textInput.addEventListener('input', () => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => renderQr(textInput.value), 200);
        });

        downloadBtn.addEventListener('click', () => {
            const canvas = preview.querySelector('canvas');
            if (!canvas) return;

            const link = document.createElement('a');
            link.download = 'qrcode.png';
            link.href = canvas.toDataURL('image/png');
            link.click();
        });
    </script>
</body>
</html>
