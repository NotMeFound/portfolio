# Portfolio Site — Setup & Deployment

This is a PHP + MySQL portfolio site with a secure contact form. The code
is complete, but — like any backend application — it needs a server
environment configured before it will run. Follow these steps in order.

## 1. Requirements

- PHP 8.0+ with the `pdo_mysql` extension enabled
- MySQL 5.7+ or MariaDB 10.3+
- Composer (to install PHPMailer)
- A Gmail account with 2-Step Verification enabled (for the App Password)
- HTTPS on your production domain (required for secure cookies to work)

## 2. Install dependencies

```bash
composer install
```

This downloads PHPMailer into `/vendor` based on `composer.json`.
`/vendor` is intentionally excluded from git via `.gitignore` — never
commit it, always run `composer install` on the server instead.

## 3. Create the database

```bash
mysql -u root -p < database.sql
```

Then create a dedicated MySQL user with **least privilege** — it only
ever needs to insert and read from one table:

```sql
CREATE USER 'portfolio_app_user'@'localhost' IDENTIFIED BY 'a-strong-unique-password';
GRANT SELECT, INSERT ON tu_portfolio_db.contact_logs TO 'portfolio_app_user'@'localhost';
FLUSH PRIVILEGES;
```

## 4. Configure environment variables

```bash
cp .env.example .env
```

Edit `.env` and fill in:
- Your real database credentials (matching the user created above)
- Your Gmail address
- A Gmail **App Password** (not your login password) — generate one at
  https://myaccount.google.com/apppasswords (requires 2-Step Verification
  to be turned on first)

`.env` is git-ignored and must never be pushed to a public repository.

## 5. Point your web server at /public

The only folder that should be publicly reachable is `public/`.
`config/`, `core/`, and `.env` must NOT be accessible directly.

- **If your host lets you set the document root**: point it at the
  `public/` folder directly, then delete the root `.htaccess` file.
- **If you're on shared hosting and can't change the document root**
  (e.g. cPanel serving from a fixed folder): keep the root `.htaccess`
  as-is — it rewrites all requests into `public/` and blocks direct
  access to `.env`. `config/.htaccess` and `core/.htaccess` add a second
  layer of protection for those folders on Apache.
- These `.htaccess` rules only work on **Apache**. If you're deploying
  to **Nginx**, you must replicate this in your server block instead —
  set `root` to the `public/` folder and add a `location` block denying
  access to `/config`, `/core`, and `/.env`.

## 6. Verify

Visit your domain, scroll to the contact form, and submit a real test
message. You should see:
- A green success message in the browser
- A new row in the `contact_logs` table
- A notification email arriving at `MAIL_TO_ADDRESS`

If the email doesn't arrive but the success message still shows, check
your PHP error log — the app is designed to save the message even if
email delivery fails, and will log the mail error separately rather than
losing the visitor's message.

## 7. Before going public

- [ ] `.env` is filled in with real values and is NOT committed to git
- [ ] HTTPS is active on the domain (required for secure session cookies)
- [ ] `composer install` has been run on the server (`/vendor` exists)
- [ ] The database user has only `SELECT, INSERT` — not full privileges
- [ ] You can reach `public/index.php` but NOT `config/database.php` or
      `core/security.php` directly by URL
- [ ] Test the form once end-to-end (see step 6)

## Known limitations (by design, not bugs)

- Rate limiting is a simple 20-second-per-session cooldown, not a full
  WAF or CAPTCHA. If the form gets targeted by determined spam bots,
  add a CAPTCHA (e.g. Cloudflare Turnstile) on top of this.
- There's no admin panel to view submitted messages — they're stored in
  `contact_logs` and also emailed to you. Query the table directly or
  build a simple authenticated admin view if you want one later.
- No automated tests are included.
