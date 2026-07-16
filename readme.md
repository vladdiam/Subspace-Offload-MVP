# Subspace Offload

**WordPress plugin.** Offloads media files to a remote FTP server (subdomain/CDN) based on a cutoff date. Files older than the specified date are transferred to FTP and served from there — the main server only keeps recent uploads.

---

## How it works

1. You set a **cutoff date** and a **CDN URL** (e.g. `https://cdn.site.com`)
2. Click **Start transfer** — the plugin sends all media files older than that date to your FTP server
3. Local copies are deleted after a successful transfer
4. WordPress automatically serves old files from the CDN and new files from the local server — visitors notice nothing

---

## Requirements

- WordPress 5.8+
- PHP 7.4+
- PHP extensions: `openssl`, `ftp`
- FTP access to your media server

---

## Installation

1. Download the plugin folder `subspace-offload/`
2. Place it in `wp-content/plugins/`
3. Go to **Plugins** in WordPress admin and activate **Subspace Offload**
4. Go to **Settings → Subspace Offload** and configure

---

## Settings

### General settings

| Field | Description |
|---|---|
| **CDN URL** | Full URL of your media server. Example: `https://cdn.site.com` |
| **Max. cutoff date** | Files uploaded before this month/year will be transferred to FTP |
| **Files per batch** | How many files to send per one request (see below) |

### FTP Data

| Field | Description |
|---|---|
| **Host** | FTP server IP or domain. Example: `123.45.67.89` |
| **User** | FTP username |
| **Password** | FTP password — stored encrypted in the database |
| **Server path** | Path on the FTP server where files will be placed. Example: `/mysite/uploads/` (optional) |

---

## Files per batch

The transfer runs in batches to avoid server timeouts. Choose based on your server:

| Value | When to use |
|---|---|
| 5 | Shared hosting, slow connection |
| 10 | Safe default for most servers |
| **20** | **Recommended** |
| 50 | VPS or dedicated server |
| 100 | Large servers only |

Each batch is a separate request. The plugin automatically continues until all files are transferred.

---

## Sync actions

After saving your settings, scroll down to **Sync actions** and click **Start transfer**.

The status box will show progress in real time:
```
✔ Transferred: 2026/03/photo.jpg
✔ Transferred: 2026/04/document.pdf
✔ All done! Transferred: 47, Failed: 0.
```

If a file fails — it stays on the local server. You can run the transfer again at any time, already-transferred files won't be duplicated.

---

## Media Library warnings

When a file has been transferred to FTP:

- A **yellow banner** appears at the top of the Media Library page
- Clicking on an offloaded file shows a note in the right panel: `⚠️ Served from FTP: https://cdn.site.com`

Files that still exist locally show no warnings.

---

## WebP support

If you use **WebP Express** or a similar plugin that places `.webp` files in the uploads folder (as `photo.jpg.webp`), Subspace Offload will detect and transfer them automatically alongside the original.

---

## URL replacement

The plugin replaces URLs automatically on every page load — no manual work needed:

- File **exists locally** → served from your main server (no change)
- File **does not exist locally** (transferred to FTP) → URL replaced with CDN domain

This works for images, thumbnails, srcsets, PDFs, documents, and inline content.

---

## Security

- FTP password is encrypted with AES-256-CBC before being stored in the database
- All admin actions are protected with WordPress nonce verification
- Plugin settings are accessible only to administrators (`manage_options`)

---

## Modules overview

| Module  | What it does |
|----|----|
| **1-A** | Admin settings page |
| **1-B** | Transient cache — reduces DB queries on every page load |
| **1-C** | Password encryption / decryption |
| **1-D** | Media Library warnings |
| **2**   | URL filtering — replaces domains based on file existence |
| **3-A** | FTP connection and authentication |
| **3-B** | File transfer logic with batch support and WebP handling |

---

## Changelog

### 0.2.0
- URL replacement now based on `file_exists()` instead of cutoff date — new uploads always served locally
- Fixed srcset (responsive image sizes) not being served correctly after transfer
- Removed unused variables, code cleanup

### 0.1.0
- Initial release
- Admin settings: CDN URL, cutoff date, FTP credentials, batch size
- FTP transfer with folder structure preservation
- Thumbnail and WebP transfer support
- Batch transfer with real-time status
- Media Library warnings
- AES-256-CBC password encryption

---

## Author

**vladd_i_am**
