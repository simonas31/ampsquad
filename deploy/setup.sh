#!/usr/bin/env bash
# Run ONCE on the server (over SSH, as the site user) before the first deploy.
#   mkdir -p ~/htdocs/ampsquad.lt && cd ~/htdocs/ampsquad.lt && bash setup.sh
# (copy this file there first, e.g. with scp)
set -e

# Persistent folders (excluded from deploys, so they survive every push).
mkdir -p storage/app/public storage/app/private storage/framework/{cache/data,sessions,views} storage/logs bootstrap/cache
chmod -R ug+rwX,o-rwx storage bootstrap/cache

# Relative symlink so uploaded media is reachable at APP_URL/storage/...
# (same as `php artisan storage:link`, but works before vendor/ is installed
# and is created exactly once - deploys never touch it).
mkdir -p public
ln -sfn ../storage/app/public public/storage

if [ ! -f .env ]; then
  echo "Next: create .env (copy deploy/.env.production.example), fill it in, chmod 600 .env"
  echo "APP_KEY: run 'php artisan key:generate --show' on your dev machine and paste the result."
fi
echo "Done. Point the CloudPanel site root directory to: $(pwd)/public"
