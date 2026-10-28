# Deployment (GitHub Actions → CloudPanel on Hostinger)

Push to `master` runs `.github/workflows/deploy.yml`:

1. **test** — MySQL service, `tsc`, Vitest, Vite build, PHPUnit. A failure stops the deploy.
2. **deploy** — builds `vendor/` (`--no-dev`) and `public/build` in CI, rsyncs to the server,
   then runs `package:discover`, `filament:upgrade`, `migrate --force`, `optimize --except=routes`,
   `app:generate-sitemap`, `queue:restart`, and finally checks `APP_URL/up`.

The server needs PHP only: no Composer, no Node.

`/robots.txt` is served by `RobotsController` (no file, nothing to generate); `/sitemap.xml` is generated on every deploy and daily by the scheduler.

`optimize` skips routes on purpose: `route:cache` bakes in a single locale prefix and makes `/en/*` return 404.
Do not run `optimize:clear` or `cache:clear` on the server; the DB cache carries the `queue:restart` signal.

## Placeholders: replace before running anything

Commands and files below contain placeholders in capital letters. Replace them with your real values; do not run them as written.
`ampsquad.lt` is the real domain and stays as is.

| Placeholder | Replace with | Example |
| --- | --- | --- |
| `SITE_USER` | the site user you chose when creating the site in CloudPanel | `ampsquad` |
| `HOST` | the server IP (same value as the `HOSTINGER_HOST` secret) | `203.0.113.10` |

## One-time server setup

1. CloudPanel → **Add Site → Create a PHP Site** → *Laravel 13* template, PHP 8.5. Add a database (Databases tab). Enable SSL.
2. Site → **SSH/FTP**: add the public half of a new deploy key (`ssh-keygen -t ed25519 -f deploy_key -N "" -C "github-actions-deploy"`).
3. Copy and run the setup script as the site user. Run both commands on your dev machine, from the repo root, after replacing
   `SITE_USER` and `HOST` (for example `ampsquad@203.0.113.10`). It asks for the site user's password, or add `-i deploy_key` to both commands to log in with the key from step 2:
   ```bash
   scp deploy/setup.sh SITE_USER@HOST:~/
   ssh SITE_USER@HOST 'mkdir -p ~/htdocs/ampsquad.lt && mv ~/setup.sh ~/htdocs/ampsquad.lt/ && cd ~/htdocs/ampsquad.lt && bash setup.sh && rm setup.sh'
   ```
4. Create `~/htdocs/ampsquad.lt/.env` on the server from `deploy/.env.production.example`, then `chmod 600 .env`.
   Fill in every blank value: `APP_KEY`, the `DB_*` credentials from the CloudPanel database, and the `MAIL_*` settings.
   Generate `APP_KEY` on your dev machine with `php artisan key:generate --show`.
5. CloudPanel → site → **Settings → Root Directory**: `ampsquad.lt/public`.
6. GitHub → Settings → Secrets and variables → Actions:

   | Secret | Value |
   | --- | --- |
   | `HOSTINGER_HOST` | the server IP (`HOST`) |
   | `HOSTINGER_USER` | the CloudPanel site user (`SITE_USER`) |
   | `HOSTINGER_SSH_KEY` | private half of the deploy key |
   | `HOSTINGER_KNOWN_HOSTS` | output of `ssh-keyscan -p 22 HOST` with `HOST` replaced by the same IP, run on your dev machine (not the server) from a network you trust |

   Production secrets (DB, mail, `APP_KEY`) stay in the server `.env`, never in GitHub or Git.
7. Push to `master`, or run the workflow manually from the Actions tab. The first deploy must succeed
   before the next step: Supervisor needs `artisan` and `.env` on the server, or the programs crash-loop (`FATAL`).
8. First admin user: `php8.5 artisan make:filament-user`. Do **not** run `AdminUserSeeder` (fixed dev password).
9. Install Supervisor programs, see below.

## Supervisor: queue worker and scheduler

`deploy/supervisor-worker.conf` defines two programs. Supervisor replaces the CloudPanel cron job, so do **not**
also add a `schedule:run` cron entry (the scheduler would run twice).

- `ampsquad-worker`: `queue:work`. Contact-form mail, order confirmation mail, image conversions and catalog imports never happen without it.
  Each deploy runs `queue:restart`; the worker finishes its job, exits, and Supervisor starts it on the new code.
  Set `DB_QUEUE_RETRY_AFTER=1900` in `.env`: a catalog import can run for minutes, and with the default of 90 seconds
  the queue would hand the running job to a second worker.
- `ampsquad-scheduler`: `schedule:work`, which runs `schedule:run` every minute (daily sitemap, and every minute the expiry of unpaid orders, which puts their stock back).
  No restart needed on deploy; every run is a fresh PHP process.

The `deploy/` folder is not rsynced to the server, so copy the file over yourself. Run once, after the first deploy.
Replace `HOST` in the commands below, and set `SITE_USER` as marked in step 3 below:

1. Install Supervisor on the server if it is missing (as root):
   ```bash
   apt install -y supervisor
   systemctl enable --now supervisor
   ```
2. Copy the file from your dev machine, from the repo root (replace `HOST`):
   ```bash
   scp deploy/supervisor-worker.conf root@HOST:/tmp/ampsquad.conf
   ```
3. Log in to the server as root (replace `HOST`) and run the rest there. Change `SITE_USER=` on the first line to your
   real site user, because the `sed` line writes it into the file:
   ```bash
   ssh root@HOST
   SITE_USER=ampsquad   # <- change to your CloudPanel site user
   sed -i "s/SITE_USER/$SITE_USER/g" /tmp/ampsquad.conf
   mv /tmp/ampsquad.conf /etc/supervisor/conf.d/ampsquad.conf
   supervisorctl reread
   supervisorctl update
   supervisorctl status
   ```
   `update` starts both programs (`autostart=true`). `status` should show `ampsquad-worker:ampsquad-worker_00`
   and `ampsquad-scheduler` as `RUNNING`.

Useful commands (as root):

```bash
supervisorctl restart ampsquad-worker:*   # after editing the worker settings
supervisorctl restart ampsquad-scheduler
tail -f /var/log/supervisor/ampsquad-worker.log
tail -f /var/log/supervisor/ampsquad-scheduler.log
```

After editing `/etc/supervisor/conf.d/ampsquad.conf` run `supervisorctl reread && supervisorctl update`.
Verify the scheduler as the site user: `cd ~/htdocs/ampsquad.lt && php8.5 artisan schedule:list`.

## Payments (OPAY)

Online checkout takes payments through OPAY (<https://opay.dev/docs/v1>) on its hosted payment page. The shop signs every request to OPAY and every message from OPAY with a shared password (md5), so there is no key file to keep.

**Before switching checkout on**

1. Ask OPAY (<https://opay.eu/en/contacts>) for sandbox access: a *website id*, a *signing password* and a *test user id*. Production gets its own website id and password after OPAY approves the shop.
2. Put them in the server `.env` (`OPAY_WEBSITE_ID`, `OPAY_SIGNATURE_PASSWORD`, `OPAY_TEST_USER_ID`; `OPAY_BASE_URL` stays on the sandbox), then `php8.5 artisan optimize --except=routes`.
3. OPAY's servers must reach `https://ampsquad.lt/payments/opay/callback` over HTTPS with a POST: no login, no firewall or bot rule in front of it, and the site's TLS certificate valid. OPAY waits 3 seconds for the answer `OK` and retries 4 more times (after 1 second, 1 minute, 5 minutes, 1 hour). Check the CloudPanel firewall and any Cloudflare-style protection.
4. The deploy creates a pickup point and four couriers (LP Express, DPD, Omniva, Lietuvos paštas) at 3.00 EUR with VAT each. In the admin panel (**Shop → Shipping methods**) set the real prices and switch off the ones you do not offer, and tick **Paid order to review** on the recipients who should handle orders (**Communication → Recipients**). Make sure the pages with the keys `buying-and-selling-rules` (slugs `pirkimo-pardavimo-taisyklės` / `buying-and-selling-rules`) and `privacy-policy` exist (**Content → Pages**), since checkout links to them.
5. Make sure the Supervisor **worker** (emails) and **scheduler** (`app:expire-unpaid-orders` every minute) are running.
6. Switch on **Shop → Shop settings → Online checkout enabled**, place a test order in the sandbox, and watch the order go from awaiting payment to paid and the emails arrive.

**Going live:** set the production `OPAY_WEBSITE_ID` and `OPAY_SIGNATURE_PASSWORD`, `OPAY_BASE_URL=https://gateway.opay.lt`, and **empty `OPAY_TEST_USER_ID`** (when it is set, every payment is a test payment). Refresh the config cache as above. Refunds are made by hand in OPAY's portal.

**Watching it**

- A payment that needs a person (paid after the order expired or was cancelled, paid twice, wrong amount) emails everyone with **Paid order to review**. Each email says what to do.
- `storage/logs/laravel.log` records `A payment message was refused` (a message that failed its signature check: a wrong password, or someone probing the URL) and `OPAY did not start the payment` with OPAY's error codes, never the password.
- Unpaid orders older than 20 minutes that are still "awaiting payment" mean the scheduler is not running: `supervisorctl status`.

## Rollback

Revert the bad commit on `master` and push; the pipeline redeploys the previous code (about 3 minutes).

Migrations are not undone. Keep them backward-compatible (add columns/tables first, remove in a later deploy).
If a migration damaged data, restore the CloudPanel nightly dump (`~/backups`, 7 days) or a Hostinger VPS backup.
Take a manual dump before any destructive migration.

## Known limits

- The deploy is in place, not atomic: files are swapped with `rsync --delay-updates`, and migrations run right after.
- A failed `migrate` leaves the new code live on the old schema (MySQL DDL is not transactional); fix forward or revert.
- After editing the server `.env`, run `php8.5 artisan optimize --except=routes` to refresh the config cache.

## Troubleshooting

| Symptom | Check |
| --- | --- |
| Deploy fails at the first SSH step | secrets, `HOSTINGER_KNOWN_HOSTS`, deploy key added to the site user, `setup.sh` ran, `.env` exists |
| 500 after deploy | `storage/logs/laravel.log`; `.env` filled; `storage` and `bootstrap/cache` writable by the site user |
| Missing styles or scripts | `public/build/manifest.json` exists on the server; `APP_URL` matches the domain |
| `/en/...` returns 404 | someone ran `route:cache`; run `php8.5 artisan route:clear` |
| Uploaded images 404 | `ls -l public/storage` must point to `../storage/app/public` (re-run the `ln -sfn` line from `setup.sh`) |
| Contact emails or thumbnails missing | `supervisorctl status` (worker `RUNNING`?); `ampsquad-worker.log`; `jobs` and `failed_jobs` tables |
| `/sitemap.xml` 404 or stale | `supervisorctl status` (scheduler `RUNNING`?); `ampsquad-scheduler.log`; no duplicate cron entry |
| Supervisor program stuck in `FATAL` | `SITE_USER` replaced in the conf; first deploy done (`artisan` and `.env` exist); log file for the exact error |
| `/admin` login fails | `APP_URL`, `SESSION_SECURE_COOKIE`, user created with `make:filament-user` |
