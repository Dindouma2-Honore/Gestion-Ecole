#!/usr/bin/env bash
set -Eeuo pipefail
DUMP="${1:?Usage: bash tools/migration/import-database-mac.sh /chemin/database.sql}"
test -s "$DUMP" || { echo 'Export SQL absent ou vide.'; exit 1; }
DUMP="$(cd "$(dirname "$DUMP")" && pwd)/$(basename "$DUMP")"
cd "$(dirname "$0")/../.."
for attempt in {1..30}; do
    if docker compose exec -T mysql sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysqladmin ping -uroot --silent' >/dev/null 2>&1; then break; fi
    sleep 2
done
COUNT="$(docker compose exec -T mysql sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -uroot -N -s "$MYSQL_DATABASE" -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE()"')"
if [[ "$COUNT" != 0 ]]; then
    echo 'Import arrêté : la base Mac contient déjà des tables. Aucune donnée écrasée.'
    exit 1
fi
docker compose exec -T mysql sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysql -uroot "$MYSQL_DATABASE"' < "$DUMP"
./vendor/bin/sail artisan migrate --force
./vendor/bin/sail artisan storage:link
./vendor/bin/sail artisan optimize:clear
./vendor/bin/sail artisan migrate:status
printf '\nRestauration terminée : http://localhost:8080/admin/login\n'
