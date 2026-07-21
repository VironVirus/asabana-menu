# Tapxora Template Menu

A reusable, mobile-first digital menu website with a private administration area. It is designed for ordinary Hostinger PHP hosting and requires no WordPress, database, Node.js server, Composer install, or environment variables.

The public menu includes featured items, food and drinks collections, category filtering, search, an order basket, and optional WhatsApp checkout. The administrator can add, edit, feature, hide, and delete menu items; uploaded images are automatically resized and compressed.

## Quick customisation

Edit `includes/config.php` to change the menu name, short name, tagline, logo path, WhatsApp order number, and order greeting. Enter the WhatsApp number as digits only with the country code, such as `2348012345678`. A blank number keeps checkout in demo mode and prevents messages from being sent accidentally.

Edit `includes/categories.php` to rename or replace the food and drink categories. Category keys already used by live items should not be changed unless those items are updated too.

The Tapxora logo in `images/tapxora-logo.jpg` is the official public logo retrieved from [tapxora.com](https://tapxora.com/). Replace it when adapting the template for another brand.

## Deployment

Connect the repository to an existing Hostinger custom PHP/HTML website. When Hostinger auto-deployment is enabled for the deployment branch, each push updates the website code automatically.

These server-managed paths are intentionally excluded from Git:

- `runtime/` — live menu data, administrator credentials, login throttling, and rolling backups
- `uploads/` — administrator-uploaded and automatically compressed menu images

Normal Git deployments should preserve these untracked paths. Export the menu regularly from the administration page and retain Hostinger backups before disconnecting or changing the deployment repository.

## First launch

1. Visit `/admin/` immediately after the first deployment.
2. Create the one-time administrator account with a unique username and a password of at least 12 characters.
3. Sign in, review the sample items, and configure the WhatsApp number in `includes/config.php` before accepting orders.

The setup screen permanently closes after the administrator account is created.

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

If PHP GD is unavailable, uploads fail safely instead of storing unoptimised originals.

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

## Sample content

`data/menu.seed.json` contains the demonstration menu. It is copied to `runtime/menu.json` only when no live data file exists. Later deployments therefore update the website without resetting menu changes made in the administrator.
