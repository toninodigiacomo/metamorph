# Security Policy
Metamorph is a small self-hosted document-conversion toolbox (Converter, QR Generator, CBZ/CBR metadata editor), designed to be run by a single administrator on their own infrastructure (a home server, a NAS, a small VPS), not as a multi-tenant public service.

**Metamorph has no authentication, no accounts, and no sessions at all.** Every page and every conversion is available to anyone who can reach the site. If you need to expose it beyond a trusted network, put an authenticating reverse proxy in front of it first; Metamorph itself will not stop an unauthenticated visitor from using any feature.

## Supported Versions
Metamorph does not currently follow a formal release/version numbering scheme. Security fixes are applied to the `main` branch; if you are running a fork or an older checkout, please pull the latest `main` before assuming an issue is unpatched.

## Reporting a Vulnerability
If you find a security issue, please **do not open a public GitHub issue** for it.  
  
Instead, use GitHub's private vulnerability reporting:
1. Go to the repository's **Security** tab.
2. Click **Report a vulnerability**.
  
This opens a private conversation with the maintainer, visible only to the two of you, so the issue can be discussed and fixed before any public disclosure.
  
Please include:
- What you found and why it's a security issue (not just "this seems wrong").
- Steps to reproduce, or a proof of concept if you have one.
- The affected file(s)/endpoint(s), if you know them.
  
There's no bug bounty — this is a personal project — but reports are genuinely appreciated, and you'll be credited (if you want to be) once a fix ships.
  
## What's already in place
**Command execution**
- Every external tool invocation (`ebook-convert`, `rsvg-convert`, `unar`, `convert`, `potrace`) wraps every path argument in `escapeshellarg()`. No user-supplied string is ever concatenated directly into a shell command — all filenames the app itself generates are `uniqid()`/`random_bytes()`-based, not taken from the upload's original name.
- The input/output format matrix (`$conversions` in `index.php`) is a fixed whitelist; a request naming a format pair that isn't in it is rejected before any file touches disk.
  
**Archive handling**
- ZIP/CBZ extraction validates every archive entry's path before calling `ZipArchive::extractTo()` — absolute paths, `..` traversal, and control characters are rejected outright ("Zip Slip" protection). CBR/RAR extraction relies on `unar`'s own path handling rather than a redundant check of our own.
- Rewriting a CBZ's metadata always builds the new archive in a separate working directory first; the original upload is never modified in place, and the working directory is deleted (`rrmdir`) as soon as the session is saved or cancelled.
- ComicInfo.xml is parsed with `LIBXML_NONET`, blocking any network access during XML parsing, as defense in depth on top of modern libxml2's default entity-loading restrictions.
  
**File access**
- Every download/thumbnail endpoint (`index.php?download=`, `comicinfo.php?download=`, `thumb.php`) resolves the requested filename through `basename()` before touching the filesystem — a `../` in the query string can't escape the intended directory.
- `uploads/` and `output/` are denied at the Apache level (`Require all denied`), even though they sit inside the container's document root for volume-mounting convenience. The only way to retrieve a file is through the PHP endpoints above, which apply their own filename handling.
- CBZ/CBR editing sessions and their output filenames use cryptographically random identifiers (`random_bytes()`), not the predictable, time-based `uniqid()` default — a session or output file can't be guessed by another visitor.
  
**Image/document processing**
- ImageMagick's `policy.xml` explicitly disables the coders Metamorph never needs — the Ghostscript-backed PS/PDF/EPS/XPS delegates, `MSL`, `URL`/`HTTP(S)`, `MVG`, `TEXT`, `SHOW`, `WIN`, `PLT`, `EPHEMERAL` — closing off the delegate/SSRF-style RCE class these coders are historically associated with, while leaving PSD/PNG/JPEG/GIF/WEBP fully functional.
- Every conversion's stderr/stdout is HTML-escaped (`htmlspecialchars`) before being shown back to the user, and is never used to build a shell command — it's purely diagnostic text.
  
**Infrastructure**
- `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, and `Referrer-Policy: strict-origin-when-cross-origin` are set on every response.
- `display_errors` follows the PHP image's production defaults (off); nothing from a failed conversion leaks a stack trace or a filesystem path beyond the tool's own error messages.
- Scheduled cleanup (a host-side cron job, see `scripts/cleanup.sh`) removes converted files and abandoned CBZ/CBR editing sessions after 24h, limiting how long any uploaded content sits on disk and bounding disk-exhaustion from repeated uploads over time.
  
## Known trade-offs
- **No authentication, by design** — see the threat model above. Anyone who can reach the site can convert files, edit CBZ metadata, or generate QR codes. This is intentional for a personal tool, but it means Metamorph must never be the only thing standing between the internet and your server.
- **No rate limiting** — every conversion shells out to a real tool (Calibre, ImageMagick, potrace); nothing stops a visitor from submitting requests back-to-back and consuming CPU/memory. On a trusted network this is a non-issue; if you ever add authentication in front of this, consider rate-limiting too.
- **No CSRF protection on the conversion/edit forms** — normally this matters because it lets another site trigger actions using *your* authenticated session. Since Metamorph has no sessions or cookies carrying privilege, classic CSRF doesn't apply today. If authentication is ever added later, this will need revisiting before it's safe to trust a login.
- **CBR/RAR extraction has no redundant path-traversal check of its own** — it relies on `unar`, a well-maintained dedicated extraction tool, rather than Metamorph re-validating every RAR entry itself (unlike the explicit check added for ZIP/CBZ). Keep the container's `unar` package up to date.
- **Zip/decompression-bomb size isn't pre-checked** — a small, deeply-compressed CBZ/CBR could expand to something much larger on disk before any limit kicks in. The 24h cleanup job bounds how long that lingers, but not an acute burst.
- **Calibre is installed via `wget | sh` at build time**, piping the official installer script straight to a shell — this is Calibre's own documented install method, and it only runs during `docker build` (not against live user data), but it is still a "trust the remote script" pattern worth knowing about.
- **CDN-loaded client-side libraries have no Subresource Integrity (SRI) hash** — `qr-code-styling` (pinned to a fixed version) and the Poppins font are loaded from `unpkg.com` / `fonts.googleapis.com` without an `integrity` attribute. A compromise of either CDN could serve modified JS/CSS to visitors' browsers. Worth adding SRI to the script tag; Google Fonts' CSS is dynamically generated per request and doesn't support SRI at all.
