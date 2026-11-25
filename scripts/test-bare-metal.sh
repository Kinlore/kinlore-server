#!/usr/bin/env bash
# Proves docs/self-hosting/bare-metal.md (the export of the two 300 MB test videos needs several minutes on a small machine): runs the guide's commands on a clean Debian 13 in a throwaway
# container (no Docker Compose, no services of this repo), then runs the end-to-end smoke test
# against the result, once through Caddy and once through nginx.
# Usage: scripts/test-bare-metal.sh        Needs: docker.
# The inner script comes in on stdin, so every command that reads stdin gets -nostdin or </dev/null.
# A container has no systemd, so the script starts PHP-FPM, the web servers and the two queue
# workers by hand; every other command is the one in the guide.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"

docker run --rm -i -v "$ROOT":/src:ro debian:13 bash -euo pipefail <<'INNER'
export DEBIAN_FRONTEND=noninteractive
step() { echo; echo "=== $*"; }

step "1. Packages"
apt-get update -qq
# Debian has no PHP 8.5 package yet: add the PHP package source, as the guide does
apt-get install -y -qq curl ca-certificates lsb-release >/dev/null
curl -sSLo /tmp/debsuryorg-archive-keyring.deb https://packages.sury.org/debsuryorg-archive-keyring.deb
dpkg -i /tmp/debsuryorg-archive-keyring.deb >/dev/null
echo "deb [signed-by=/usr/share/keyrings/debsuryorg-archive-keyring.gpg] https://packages.sury.org/php/ $(lsb_release -sc) main" > /etc/apt/sources.list.d/php.list
apt-get update -qq
apt-get install -y -qq php8.5-fpm php8.5-cli php8.5-sqlite3 php8.5-pgsql php8.5-gd php8.5-zip \
  php8.5-intl php8.5-mbstring php8.5-bcmath php8.5-xml php8.5-curl \
  ffmpeg libheif-examples composer git unzip curl jq uuid-runtime sqlite3 rsync caddy nginx >/dev/null
command -v heif-dec >/dev/null   # the HEIC decoder of the guide's package list
apt-get install -y -qq procps >/dev/null   # test only: pkill and pgrep stand in for systemctl stop (a real Debian host has them)

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
cp ../deploy/bare-metal/php-kinlore.ini /etc/php/8.5/fpm/conf.d/99-kinlore.ini
cp ../deploy/bare-metal/php-kinlore.ini /etc/php/8.5/cli/conf.d/99-kinlore.ini
cp ../deploy/bare-metal/php-fpm-kinlore.conf /etc/php/8.5/fpm/pool.d/zz-kinlore.conf

step "5. Database"
# Not exported: the admin comes from the setup page in step 8b, not from the seeder
SEED_USER_NAME="Test Admin" SEED_USER_EMAIL=admin@example.test SEED_USER_PASSWORD='BareMetal-12345'
runuser -u www-data -- php artisan migrate --force --seed
runuser -u www-data -- php artisan config:cache
runuser -u www-data -- php artisan route:cache
runuser -u www-data -- php artisan view:cache

step "6. PHP-FPM (systemd does this on a real host)"
mkdir -p /run/php && php-fpm8.5 --daemonize

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

step "8. Queue workers (the two systemd units run these commands)"
start_workers() {
  for unit in kinlore-queue kinlore-queue-default; do
    WORKER_CMD=$(sed -n 's/^ExecStart=//p' "/srv/kinlore/deploy/bare-metal/$unit.service")
    WORKER_DIR=$(sed -n 's/^WorkingDirectory=//p' "/srv/kinlore/deploy/bare-metal/$unit.service")
    test -n "$WORKER_CMD" && test "$WORKER_DIR" = /srv/kinlore/kinlore-api
    echo "$unit: $WORKER_CMD"
    # shellcheck disable=SC2086
    (cd "$WORKER_DIR" && runuser -u www-data -- $WORKER_CMD >>"/var/log/$unit.log" 2>&1 &)
  done
}
start_workers

step "8b. Admin account on the setup page, with the token of the command (guide step 8)"
sleep 2
SETUP=$(runuser -u www-data -- php artisan kinlore:setup-token | sed -n 's/^Setup token: //p')
test "${#SETUP}" -ge 32
CSRF=$(curl -fsS -c /tmp/setup-jar http://localhost/setup | sed -n 's/.*name="_token" value="\([^"]*\)".*/\1/p')
test -n "$CSRF"
curl -fsS -b /tmp/setup-jar -o /tmp/setup-done.html --data-urlencode "_token=$CSRF" --data-urlencode "setup_token=$SETUP" \
  --data-urlencode "name=$SEED_USER_NAME" --data-urlencode "email=$SEED_USER_EMAIL" \
  --data-urlencode "password=$SEED_USER_PASSWORD" --data-urlencode "password_confirmation=$SEED_USER_PASSWORD" http://localhost/setup
grep -q 'Your admin account is ready' /tmp/setup-done.html
test ! -e storage/app/setup-token
runuser -u www-data -- php artisan kinlore:setup-token | grep -q 'already set up'
echo "admin created with the setup token; the token file is gone"
rm -f /tmp/setup-jar /tmp/setup-done.html

step "9. Checks from the guide"
sleep 2
curl -fsS http://localhost/api/health; echo
curl -fsS http://localhost:8081/api/health; echo
curl -fsS http://localhost/api/auth/setup-status; echo
test "$(curl -s -o /dev/null -w '%{http_code}' http://localhost/.env)" = 404
test "$(curl -s -o /dev/null -w '%{http_code}' http://localhost/.htaccess)" = 404
runtime_user() { runuser -u www-data -- "$@"; }
runtime_user php artisan about --only=environment | sed -n 1,12p

step "10. Large file through both web servers (300 MB; the PHP memory limit is 1 GB)"
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

step "11. Backup script (three runs: what a run deletes or overwrites goes to old/<date>)"
B=/tmp/kinlore-backup OLD=/tmp/kinlore-backup/old/$(date +%F)
sh ../deploy/bare-metal/kinlore-backup.sh $B
test -s $B/database.sqlite; test -d $B/media; test -s $B/env
echo marker > /var/lib/kinlore/media/backup-test-marker
sh ../deploy/bare-metal/kinlore-backup.sh $B
test -s $OLD/database.sqlite; test -s $OLD/env; test -f $B/media/backup-test-marker
rm /var/lib/kinlore/media/backup-test-marker
sh ../deploy/bare-metal/kinlore-backup.sh $B
test ! -e $B/media/backup-test-marker; test "$(cat $OLD/media/backup-test-marker)" = marker; echo "old/ keeps the deleted file"

step "12. Password reset command"
runtime_user php artisan kinlore:reset-password "$SEED_USER_EMAIL" --password='BareMetal-67890' | tail -1
runtime_user php artisan queue:failed | tail -2
runtime_user php artisan seaweedfs:test >/dev/null && echo "storage check ok"

step "12b. Update commands (all but 'git pull')"
composer install --no-dev --optimize-autoloader --no-interaction --quiet
runtime_user php artisan migrate --force | tail -1
runtime_user php artisan config:cache >/dev/null && runtime_user php artisan route:cache >/dev/null && runtime_user php artisan view:cache >/dev/null
curl -fsS http://localhost/api/health; echo

step "12c. Restore from the backup of step 11, onto an empty media folder"
# The guide's restore commands; pkill stands in for "systemctl stop kinlore-queue kinlore-queue-default php8.5-fpm"
pkill -f 'artisan queue:work' || true; pkill -f php-fpm || true
for _ in $(seq 1 60); do pgrep -f 'artisan queue:work|php-fpm' >/dev/null || break; sleep 1; done
if pgrep -f 'artisan queue:work|php-fpm' >/dev/null; then echo "workers or PHP-FPM did not stop" >&2; exit 1; fi
rm -rf /var/lib/kinlore/media/*   # a lost media disk
rm -f /var/lib/kinlore/database.sqlite-wal /var/lib/kinlore/database.sqlite-shm
cp /tmp/kinlore-backup/database.sqlite /var/lib/kinlore/database.sqlite
rsync -a /tmp/kinlore-backup/media/ /var/lib/kinlore/media/
cp /tmp/kinlore-backup/env /srv/kinlore/kinlore-api/.env
chown -R www-data:www-data /var/lib/kinlore
chown root:www-data /srv/kinlore/kinlore-api/.env
runtime_user php artisan migrate --force | tail -1
runtime_user php artisan config:cache >/dev/null
php-fpm8.5 --daemonize; start_workers; sleep 2
# The backup is older than the password reset of step 12: only the restored database knows the first password
TOKEN=$(curl -fsS -X POST http://localhost/api/auth/login -H 'Content-Type: application/json' -H 'Accept: application/json' \
  -d "{\"email\":\"$SEED_USER_EMAIL\",\"password\":\"$SEED_USER_PASSWORD\"}" | jq -r .data.token)
test -n "$TOKEN"; test "$TOKEN" != null; echo "restored database is served"
# The media folder was empty: the 300 MB video of step 10 can only come from the backup
GOT=$(curl -fsS -o /dev/null -w '%{size_download}' -H "Authorization: Bearer $TOKEN" "http://localhost/api/v1/moments/$MID/file/original")
test "$GOT" = "$SIZE"; echo "restored media is served"

step "13. End-to-end smoke test through Caddy"
sleep 61   # sign-in limit: 5 per minute
SMOKE_EXPORT_WAIT=900 bash /srv/kinlore/scripts/smoke-test.sh http://localhost "$SEED_USER_EMAIL" "$SEED_USER_PASSWORD" </dev/null | grep -A2 -E "FAIL|Passed:"

step "14. End-to-end smoke test through nginx"
sleep 61
SMOKE_EXPORT_WAIT=900 bash /srv/kinlore/scripts/smoke-test.sh http://localhost:8081 "$SEED_USER_EMAIL" "$SEED_USER_PASSWORD" </dev/null | grep -A2 -E "FAIL|Passed:"

echo; echo "BARE METAL GUIDE: ALL STEPS PASSED"
INNER
