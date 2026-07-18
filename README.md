# Asabana Hotel Menu

A self-contained, server-rendered PHP menu website with a private flat-file administration area. It is designed for Hostinger PHP/HTML hosting and requires no database, Node.js process, Composer install, or environment variables.

## Deployment

The repository can be connected to an existing Hostinger custom PHP/HTML website. When Hostinger auto-deployment is enabled for the `main` branch, each push updates the website code automatically.

The following server-managed paths are intentionally excluded from Git:

- `runtime/` — live menu data, administrator credentials, login throttling, and rolling backups
- `uploads/` — administrator-uploaded and automatically compressed menu images

Normal Git deployments should preserve these untracked paths. Export the menu regularly from the administration page and retain Hostinger backups before disconnecting or changing the deployment repository.

## First launch

Immediately after the first deployment:

1. Visit `/admin/`.
2. Create the one-time administrator account using a unique username and a password of at least 12 characters.
3. Sign in and confirm the imported menu.

The setup screen permanently closes as soon as the account is created.

## Image processing

Administrator uploads accept JPEG, PNG, and WebP files up to 8 MB. PHP GD:

- validates and decodes the actual image
- rejects oversized pixel dimensions
- applies JPEG orientation when available
- strips metadata by re-encoding
- limits the main image to 1600 pixels
- creates a 480-pixel thumbnail
- writes WebP when supported, otherwise optimized JPEG
- progressively lowers quality to meet sensible file-size targets

If PHP GD is unavailable, uploads fail safely instead of storing unoptimized originals.

## Security

- Secure, HTTP-only, SameSite administrator sessions
- Strong password hashing with Argon2id when available
- CSRF validation on every write action
- Server-side validation and escaped output
- Login throttling by hashed IP address
- Atomic JSON writes, file locking, and 30 rolling backups
- Strict upload MIME, size, and pixel validation
- Random upload filenames and blocked script execution in `uploads/`
- Direct web access blocked for `runtime/`, `data/`, and `includes/`
- Content Security Policy and restrictive browser security headers

## Content

`data/menu.seed.json` contains the initial imported menu. It is copied to `runtime/menu.json` only when the live file does not already exist. Later deployments therefore update code without resetting live menu changes.

