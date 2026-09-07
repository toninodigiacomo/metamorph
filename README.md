# Metamorph
A small self-hosted toolbox of document utilities, running as a single PHP/Apache container. No database, no accounts — just a handful of focused tools behind one web UI.

## Features

### Converter
Convert a PDF file to EPUB, powered by [Calibre](https://calibre-ebook.com/)'s `ebook-convert` under the hood. Drag & drop or browse to upload.

### QR Generator
Type any text or URL and get a live QR code preview, rendered client-side. Download it as a high-resolution PNG (1024×1024) suitable for print, regardless of the on-screen preview size.

### CBZ/CBR Metadata Editor
Read, edit, and rewrite `ComicInfo.xml` metadata inside comic archives, following the [ComicRack](https://wiki.mobileread.com/wiki/ComicRack) `ComicInfo.xml` standard:

- Upload a `.cbz` or `.cbr` file.
- Existing metadata (series, writer, publisher, summary, age rating, etc.) is parsed and pre-filled into a form, organized by section.
- Each page is shown as a thumbnail, with a naming-convention check (`P00001.jpg`, `P00002.jpg`, …) and a per-page "delete" checkbox.
- On save, pages are (re)numbered to the standard if needed — automatically if any page was deleted — metadata is written back as `ComicInfo.xml`, and the archive is repacked as a downloadable `.cbz`.
- **Note:** `.cbr` (RAR) input is supported for reading, but the output is always `.cbz` — there is no free/open RAR encoder to write back to RAR.

## Internationalization
The UI is available in **English** (default), **French**, **Italian**, and **German**. The language is auto-detected from the browser's `Accept-Language` header, with a small manual switch (top-right) that persists the choice in a cookie for a year.

## Tech stack
- PHP 8.2 + Apache (official `php:8.2-apache` image, no custom Dockerfile file — the build steps live inline in `compose.yml` via `dockerfile_inline`)
- [Calibre](https://calibre-ebook.com/) (`ebook-convert`) for document conversion
- PHP `zip` extension (CBZ read/write) and `unar` (CBR extraction)
- PHP `gd` extension for on-the-fly thumbnail generation
- [qrcode.js](https://davidshimjs.github.io/qrcodejs/) (client-side, loaded from a CDN) for QR code rendering
- Vanilla JS/CSS — no build step, no framework

## Getting started
```bash
docker compose up --build -d
```
The app will be available on the port configured in `compose.yml` (default: `8218`).

### Volumes
| Host path   | Container path           | Purpose                             |
|-------------|--------------------------|-------------------------------------|
| `./www`     | `/var/www/html`          | Application code                    |
| `./uploads` | `/var/www/html/uploads`  | Temporary uploads & working files   |
| `./output`  | `/var/www/html/output`   | Converted/repacked output files     |

## Project structure
```
.
├── compose.yml                   # Build + service definition (Dockerfile inlined)
└── www/
    ├── index.php                 # Converter (PDF -> EPUB)
    ├── qrcode.php                # QR Generator
    ├── comicinfo.php             # CBZ/CBR metadata editor
    ├── thumb.php                 # On-the-fly page thumbnail endpoint
    └── includes/
        ├── header.php            # Shared layout: nav, styles, language switch
        ├── footer.php            # Shared layout: closing markup
        └── i18n.php              # Translation dictionary + language detection
```

## License
**MIT** [LICENCE.md](https://github.com/toninodigiacomo/pong-led-matrix/blob/f3098bfc4be7f9d33e8b683e3e7f83d1b701de16/LICENSE.md)