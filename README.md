# SafeMatch – Mother-Baby Identification System

Pure PHP + MySQL + vanilla JavaScript + Tailwind (CDN). No frameworks, no Composer.

## Quick start (XAMPP)

1. Put this folder in `C:\xampp\htdocs\Safematch` and start Apache + MySQL.
2. Import the schema (creates its **own** database, `safematch_php`):
   ```
   C:\xampp\mysql\bin\mysql.exe -u root < database.sql
   ```
   The script has no `DROP` statements. Importing into a database that already
   has these tables fails instead of overwriting data.
3. Open http://localhost/Safematch/ and sign in:

   | Role | Email | Password |
   |---|---|---|
   | Admin | admin@safematch.com | admin123 |
   | Healthcare personnel | midwifery@safematch.com | midwifery123 |

   **Change both passwords after the first login** (My Profile > Change password).

## Configuration

`config/database.php` and `config/constants.php`. You can also set these environment variables:

| Variable | Default | Purpose |
|---|---|---|
| `SAFEMATCH_DB_HOST/PORT/NAME/USER/PASS` | localhost / 3306 / safematch_php / root / (empty) | Database |
| `SAFEMATCH_SECRET` | value in constants.php | HMAC key that signs QR bands. **Set a new random value in production.** Changing it invalidates bands that are already printed. |
| `SAFEMATCH_HTTPS` | off | Set to `1` behind HTTPS (secure cookies) |
| `SAFEMATCH_TIMEZONE` | Asia/Manila | PHP and MySQL session timezone |
| `SAFEMATCH_BASE_URL` | auto-detected | Override the URL path, e.g. `/safematch` |

## How verification works

- Registering a newborn generates a QR pair (PNG, stored base64 in `qr_codes`).
  The payload is JSON, for example
  `{"type":"mother","id":"M2026001","name":"Maria Santos","dob":"1990-05-15","admission":"2026-11-10","sig":"…"}`.
  The baby's code also carries `mother_id`.
- `sig` is a truncated HMAC-SHA256, so edited or forged bands fail the check.
- Verification (step 1 mother, step 2 baby) is a **MATCH** only if both bands are
  authentic, match the current records, and the baby is registered to that mother.
  Every attempt is logged. A mismatch notifies all admins.
- QR images are produced by a built-in encoder (`includes/qr-functions.php`,
  versions 1–40, ECC level M). It was tested by decoding its output with an
  independent decoder.
- The live camera needs HTTPS or `localhost`. On plain-HTTP LAN addresses, staff
  can still use **Scan from photo** or manual entry. Manual entry is flagged in the log.

## Discharge rules

- Readiness % = met / active requirements.
- Traffic light: **green** when every mandatory requirement is met, **yellow** at 50% or more, **red** below 50%.
- Final discharge needs every mandatory requirement met **and** the pair's
  latest QR verification to be a match.
- Discharged records are locked and cannot be deleted.
- Admins configure requirements on the Discharge page. Admins also manage
  screening and assessment types on those pages.

## Security summary

- PDO prepared statements everywhere; sort columns are whitelisted.
- Output escaped with `htmlspecialchars` in PHP and `SM.escape` in JS.
- CSRF token on every state-changing request (`X-CSRF-Token` header).
- Session ID regenerated at login; HttpOnly + SameSite cookies; 30-minute
  inactivity timeout with an in-app warning. Background polling does not extend the session.
- Login throttling: 5 failures in 15 minutes per email or IP.
- `password_hash` / `password_verify`; minimum 8 characters.
- Uploads: real MIME check, images only, 2MB maximum, random file names.
  `uploads/.htaccess` blocks script execution.
- `config/`, `includes/`, `templates/` and `*.sql`/`*.md` are blocked by `.htaccess`.

## Deployment checklist

1. Upload the files and import `database.sql`.
2. Set the DB credentials and a new `SAFEMATCH_SECRET`; enable `SAFEMATCH_HTTPS`.
3. Make `uploads/profile_pictures/` writable by the web server (755).
4. Make sure Apache allows `.htaccess` overrides (`AllowOverride All`).
5. Sign in and change the default passwords.
6. Optional for production: replace the Tailwind Play CDN with a compiled stylesheet.
