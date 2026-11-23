#!/usr/bin/env bash
# Proves docs/BARE_METAL.md: runs the guide's commands on a clean Debian 13 in a throwaway
# container (no Docker Compose, no services of this repo), then runs the end-to-end smoke test
# against the result, once through Caddy and once through nginx.
# Usage: scripts/test-bare-metal.sh        Needs: docker.
# The inner script comes in on stdin, so every command that reads stdin gets -nostdin or </dev/null.
# A container has no systemd, so the script starts PHP-FPM, the web servers and the queue
# worker by hand; every other command is the one in the guide.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"

docker run --rm -i -v "$ROOT":/src:ro debian:13 bash -euo pipefail <<'INNER'
export DEBIAN_FRONTEND=noninteractive
step() { echo; echo "=== $*"; }

step "1. Packages"
apt-get update -qq
apt-get install -y -qq php8.4-fpm php8.4-cli php8.4-sqlite3 php8.4-pgsql php8.4-gd php8.4-zip \
  php8.4-intl php8.4-mbstring php8.4-bcmath php8.4-xml php8.4-curl \
  ffmpeg composer git unzip curl jq uuid-runtime sqlite3 rsync caddy nginx >/dev/null

step "2. Code and data folders"
mkdir -p /srv/kinlore && cp -a /src/. /srv/kinlore/
# The copy stands in for "git clone": drop what a clone does not have
cd /srv/kinlore/kinlore-api && rm -rf vendor .env bootstrap/cache/*.php storage/logs/*.log storage/framework/cache/data/* && cd /
mkdir -p /var/lib/kinlore/media
touch /var/lib/kinlore/database.sqlite
chown -R www-data:www-data /var/lib/kinlore

step "3. Install and configure"
cd /srv/kinlore/kinlore-api
composer install --no-dev --optimize-autoloader --no-interaction --quiet
cp ../deploy/bare-metal/env.example .env
php artisan key:generate --force
sed -i 's#^APP_URL=.*#APP_URL=http://localhost#' .env
chown -R www-data:www-data storage bootstrap/cache
chown root:www-data .env && chmod 640 .env

step "4. PHP limits"
cp ../deploy/bare-metal/php-kinlore.ini /etc/php/8.4/fpm/conf.d/99-kinlore.ini
cp ../deploy/bare-metal/php-kinlore.ini /etc/php/8.4/cli/conf.d/99-kinlore.ini

step "5. Database"
export SEED_USER_NAME="Test Admin" SEED_USER_EMAIL=admin@example.test SEED_USER_PASSWORD='BareMetal-12345'
runuser -u www-data -- env SEED_USER_NAME="$SEED_USER_NAME" SEED_USER_EMAIL="$SEED_USER_EMAIL" SEED_USER_PASSWORD="$SEED_USER_PASSWORD" php artisan migrate --force --seed
runuser -u www-data -- php artisan config:cache
runuser -u www-data -- php artisan route:cache
runuser -u www-data -- php artisan view:cache

step "6. PHP-FPM (systemd does this on a real host)"
mkdir -p /run/php && php-fpm8.4 --daemonize

step "7a. Caddy"
usermod -aG www-data caddy
sed 's#^photos.example.com {#:80 {#' ../deploy/bare-metal/Caddyfile > /etc/caddy/Caddyfile
caddy validate --config /etc/caddy/Caddyfile --adapter caddyfile >/dev/null 2>&1
runuser -u caddy -g caddy -G www-data -- caddy start --config /etc/caddy/Caddyfile --adapter caddyfile >/dev/null 2>&1

step "7b. nginx (second web server, on port 8081, to prove that config too)"
sed -e 's#listen 80;#listen 8081;#' -e 's#listen \[::\]:80;##' -e 's#server_name photos.example.com;#server_name _;#' \
  ../deploy/bare-metal/nginx.conf > /etc/nginx/sites-available/kinlore
ln -sf /etc/nginx/sites-available/kinlore /etc/nginx/sites-enabled/kinlore && rm -f /etc/nginx/sites-enabled/default
nginx -t 2>&1 | tail -1
nginx

step "8. Queue worker (the systemd unit runs this command)"
WORKER_CMD=$(sed -n 's/^ExecStart=//p' ../deploy/bare-metal/kinlore-queue.service)
WORKER_DIR=$(sed -n 's/^WorkingDirectory=//p' ../deploy/bare-metal/kinlore-queue.service)
test -n "$WORKER_CMD" && test "$WORKER_DIR" = /srv/kinlore/kinlore-api
echo "worker: $WORKER_CMD"
# shellcheck disable=SC2086
(cd "$WORKER_DIR" && runuser -u www-data -- $WORKER_CMD >/var/log/kinlore-queue.log 2>&1 &)

step "9. Checks from the guide"
sleep 2
curl -fsS http://localhost/api/health; echo
curl -fsS http://localhost:8081/api/health; echo
curl -fsS http://localhost/api/auth/setup-status; echo
test "$(curl -s -o /dev/null -w '%{http_code}' http://localhost/.env)" = 404
test "$(curl -s -o /dev/null -w '%{http_code}' http://localhost/.htaccess)" = 404
runtime_user() { runuser -u www-data -- "$@"; }
runtime_user php artisan about --only=environment | sed -n 1,12p

step "10. Large file through both web servers (300 MB, PHP memory limit is 256 MB)"
TOKEN=$(curl -fsS -X POST http://localhost/api/auth/login -H 'Content-Type: application/json' -H 'Accept: application/json' \
  -d "{\"email\":\"$SEED_USER_EMAIL\",\"password\":\"$SEED_USER_PASSWORD\"}" | jq -r .data.token)
KID=$(curl -fsS -X POST http://localhost/api/v1/kids -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' -H 'Accept: application/json' \
  -d '{"name":"Big File Kid","date_of_birth":"2024-01-01","avatar_color":"#88aaff"}' | jq -r .data.id)
ffmpeg -nostdin -loglevel error -y -f lavfi -i "testsrc2=size=1280x720:rate=30,noise=alls=100:allf=t" -t 5 -c:v libx264 -preset ultrafast -qp 0 -pix_fmt yuv420p /tmp/big.mp4
SIZE=$(stat -c %s /tmp/big.mp4); echo "test video: $SIZE bytes"
for base in http://localhost http://localhost:8081; do
  MID=$(curl -fsS -H "Authorization: Bearer $TOKEN" -H 'Accept: application/json' -F kid_id="$KID" -F "file=@/tmp/big.mp4;type=video/mp4" "$base/api/v1/moments" | jq -r .data.moment.id)
  GOT=$(curl -fsS -o /dev/null -w '%{size_download}' -H "Authorization: Bearer $TOKEN" "$base/api/v1/moments/$MID/file/original")
  echo "$base upload ok, original download $GOT bytes"
  test "$GOT" = "$SIZE"
done
rm -f /tmp/big.mp4

step "10b. Wait until the worker has converted the two large videos (slow on a small machine)"
# Before the backup: a backup taken in the middle of a conversion restores a job that nobody works on
for _ in $(seq 1 180); do
  LEFT=$(sqlite3 /var/lib/kinlore/database.sqlite "select count(*) from jobs")
  [ "$LEFT" = 0 ] && break; sleep 10
done
echo "jobs left: $LEFT"; test "$LEFT" = 0

step "11. Backup script"
sh ../deploy/bare-metal/kinlore-backup.sh /tmp/kinlore-backup
test -s /tmp/kinlore-backup/database.sqlite && test -d /tmp/kinlore-backup/media && test -s /tmp/kinlore-backup/env

step "12. Password reset command"
runtime_user php artisan kinlore:reset-password "$SEED_USER_EMAIL" --password='BareMetal-67890' | tail -1
runtime_user php artisan queue:failed | tail -2
runtime_user php artisan seaweedfs:test >/dev/null && echo "storage check ok"

step "12b. Update commands (all but 'git pull')"
composer install --no-dev --optimize-autoloader --no-interaction --quiet
runtime_user php artisan migrate --force | tail -1
runtime_user php artisan config:cache >/dev/null && runtime_user php artisan route:cache >/dev/null && runtime_user php artisan view:cache >/dev/null
curl -fsS http://localhost/api/health; echo

step "12c. Restore from the backup of step 11"
cp /tmp/kinlore-backup/database.sqlite /var/lib/kinlore/database.sqlite
rsync -a /tmp/kinlore-backup/media/ /var/lib/kinlore/media/
cp /tmp/kinlore-backup/env /srv/kinlore/kinlore-api/.env
chown -R www-data:www-data /var/lib/kinlore
chown root:www-data /srv/kinlore/kinlore-api/.env
runtime_user php artisan migrate --force | tail -1
runtime_user php artisan config:cache >/dev/null
# The backup is older than the password reset of step 12, so the first password is back
test "$(curl -fsS http://localhost/api/v1/kids -H "Authorization: Bearer $TOKEN" -H 'Accept: application/json' | jq '.data|length')" -ge 1 && echo "restored data is served"

step "13. End-to-end smoke test through Caddy"
sleep 61   # sign-in limit: 5 per minute
bash /srv/kinlore/scripts/smoke-test.sh http://localhost "$SEED_USER_EMAIL" "$SEED_USER_PASSWORD" </dev/null | grep -A2 -E "FAIL|Passed:"

step "14. End-to-end smoke test through nginx"
sleep 61
bash /srv/kinlore/scripts/smoke-test.sh http://localhost:8081 "$SEED_USER_EMAIL" "$SEED_USER_PASSWORD" </dev/null | grep -A2 -E "FAIL|Passed:"

echo; echo "BARE METAL GUIDE: ALL STEPS PASSED"
INNER
