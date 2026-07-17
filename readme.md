# Subspace Offload CRON

**WordPress plugin.** Automatically offloads media files to a remote FTP server (subdomain/CDN) via WP-Cron. Files are kept locally for a set number of hours, then transferred and served from the CDN — no manual action needed.

> Looking for the manual version with a transfer button? See [Subspace Offload MVP](https://github.com/vladd_i_am/subspace-offload).

---

## How it works

1. You set a **CDN URL**, **local delay** (hours), and **FTP credentials**
2. WP-Cron runs automatically on the chosen interval
3. Files older than the delay are transferred to your FTP server
4. Local copies are deleted after a successful transfer
5. WordPress automatically serves old files from the CDN and new files from the local server — visitors notice nothing

---

## Requirements

- WordPress 5.8+
- PHP 7.4+
- PHP extensions: `openssl`, `ftp`
- FTP access to your media server

---

## Installation

1. Download the plugin folder `subspace-offload-cron/`
2. Place it in `wp-content/plugins/`
3. Go to **Plugins** in WordPress admin and activate **Subspace Offload CRON**
4. Go to **Settings → Subspace Offload** and configure

---

## Settings

### General settings

| Field | Description |
|---|---|
| **CDN URL** | Full URL of your media server. Example: `https://cdn.site.com` |
| **Local delay (hours)** | How long files stay on the local server before being transferred |
| **Check interval** | How often WP-Cron checks for files ready to transfer |
| **Files per batch** | How many files to transfer per one cron run (see below) |

### FTP Data

| Field | Description |
|---|---|
| **Host** | FTP server IP or domain. Example: `123.45.67.89` |
| **User** | FTP username |
| **Password** | FTP password — stored encrypted in the database |
| **Server path** | Path on the FTP server where files will be placed. Example: `/mysite/uploads/` (optional) |

---

## Local delay

How long a file stays on your main server before being moved to FTP:

| Value | When to use |
|---|---|
| 6 hours | High-traffic sites, fast turnover |
| 12 hours | Active sites |
| **24 hours** | **Recommended** |
| 48 hours | Extra safety buffer |
| 72 hours | Conservative, slow-moving sites |

---

## Check interval

How often WP-Cron wakes up and looks for files to transfer:

| Value | When to use |
|---|---|
| Every hour | Sites with frequent uploads |
| Every 12 hours | Moderate upload frequency |
| **Once a day** | **Recommended for most sites** |

Changing this setting takes effect immediately — the cron schedule is automatically updated.

---

## Files per batch

Each cron run processes files in batches. If there are more files than the batch size, the next cron run picks up where it left off:

| Value | When to use |
|---|---|
| 5 | Shared hosting, slow connection |
| 10 | Safe default for most servers |
| **20** | **Recommended** |
| 50 | VPS or dedicated server |
| 100 | Large servers only |

---

## Media Library warnings

When a file has been transferred to FTP:

- A **yellow banner** appears at the top of the Media Library page, showing the CDN URL and delay setting
- Clicking on an offloaded file shows a note in the right panel: `⚠️ Served from FTP: https://cdn.site.com`

Files that still exist locally show no warnings.

---

## WebP support

If you use **WebP Express** or a similar plugin that places `.webp` files in the uploads folder (as `photo.jpg.webp`), Subspace Offload CRON will detect and transfer them automatically alongside the original and all its thumbnails.

---

## URL replacement

The plugin replaces URLs automatically on every page load — no manual work needed:

- File **exists locally** → served from your main server (no change)
- File **does not exist locally** (transferred to FTP) → URL replaced with CDN domain

This works for images, thumbnails, srcsets, PDFs, documents, and inline content.

---

## Security

- FTP password is encrypted with AES-256-CBC before being stored in the database
- Plugin settings are accessible only to administrators (`manage_options`)

---

## Modules overview

| Module | What it does |
|---|---|
| **1-A** | Admin settings page |
| **1-B** | Transient cache — reduces DB queries on every page load |
| **1-C** | Password encryption / decryption |
| **1-D** | Media Library warnings |
| **2** | URL filtering — replaces domains based on file existence |
| **3** | WP-Cron registration, scheduling, and automatic file transfer |

---

## Changelog

### 0.3.0
- Initial release (edit. from S.S.O MVP)
- Fully automatic transfer via WP-Cron — no manual action needed
- Configurable local delay (hours) instead of fixed cutoff date
- Configurable check interval (hourly / 12h / daily)
- Cron auto-reschedules when interval is changed in settings
- Cron registers on activation, clears on deactivation
- FTP transfer with folder structure preservation
- Thumbnail and WebP transfer support
- Batch processing — picks up where it left off on next run
- Media Library warnings
- AES-256-CBC password encryption

---

## Author

**vladd_i_am**
