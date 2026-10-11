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

## One-time server setup

1. CloudPanel → **Add Site → Create a PHP Site** → *Laravel 13* template, PHP 8.5. Add a database (Databases tab). Enable SSL.
2. Site → **SSH/FTP**: add the public half of a new deploy key (`ssh-keygen -t ed25519 -f deploy_key -N ""`).
3. Copy and run the setup script as the site user:
   ```bash
   scp deploy/setup.sh SITE_USER@HOST:~/
   ssh SITE_USER@HOST 'mkdir -p ~/htdocs/ampsquad.lt && mv ~/setup.sh ~/htdocs/ampsquad.lt/ && cd ~/htdocs/ampsquad.lt && bash setup.sh && rm setup.sh'
   ```
4. Create `~/htdocs/ampsquad.lt/.env` from `deploy/.env.production.example`, `chmod 600 .env`.
   Generate `APP_KEY` on your dev machine with `php artisan key:generate --show`.
5. CloudPanel → site → **Settings → Root Directory**: `ampsquad.lt/public`.
6. GitHub → Settings → Secrets and variables → Actions:

   | Secret | Value |
   | --- | --- |
   | `HOSTINGER_HOST` | server IP or hostname |
   | `HOSTINGER_USER` | CloudPanel site user |
   | `HOSTINGER_SSH_KEY` | private half of the deploy key |
   | `HOSTINGER_KNOWN_HOSTS` | output of `ssh-keyscan -p 22 HOST` (run from a network you trust) |

   Production secrets (DB, mail, `APP_KEY`) stay in the server `.env`, never in GitHub or Git.
7. Push to `master`, or run the workflow manually from the Actions tab. The first deploy must succeed
   before the next step: Supervisor needs `artisan` and `.env` on the server, or the programs crash-loop (`FATAL`).
8. First admin user: `php8.5 artisan make:filament-user`. Do **not** run `AdminUserSeeder` (fixed dev password).
9. Install Supervisor programs, see below.

## Supervisor: queue worker and scheduler

`deploy/supervisor-worker.conf` defines two programs. Supervisor replaces the CloudPanel cron job, so do **not**
also add a `schedule:run` cron entry (the scheduler would run twice).

- `ampsquad-worker`: `queue:work`. Contact-form mail and image conversions never happen without it.
  Each deploy runs `queue:restart`; the worker finishes its job, exits, and Supervisor starts it on the new code.
- `ampsquad-scheduler`: `schedule:work`, which runs `schedule:run` every minute (daily sitemap).
  No restart needed on deploy; every run is a fresh PHP process.

The `deploy/` folder is not rsynced to the server, so copy the file over yourself. Run once, after the first deploy:

1. Install Supervisor on the server if it is missing (as root):
   ```bash
   apt install -y supervisor
   systemctl enable --now supervisor
   ```
2. Copy the file from your dev machine:
   ```bash
   scp deploy/supervisor-worker.conf root@HOST:/tmp/ampsquad.conf
   ```
3. On the server, as root, set the site user, install, and load it:
   ```bash
   ssh root@HOST
   sed -i 's/SITE_USER/your-site-user/g' /tmp/ampsquad.conf
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
Verify the scheduler from the site user: `php8.5 artisan schedule:list`.

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
