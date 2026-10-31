#!/usr/bin/env bash
set -Eeuo pipefail
cd "$(dirname "$0")/../.."
command -v docker >/dev/null || { echo 'Installez et ouvrez Docker Desktop avant de continuer.'; exit 1; }
docker info >/dev/null
mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
test -f .env || { echo 'Le fichier .env du transfert manque.'; exit 1; }
# Bootstrap Sail using the exact dependencies recorded in composer.lock.
docker run --rm -v "$PWD:/var/www/html" -w /var/www/html laravelsail/php83-composer:latest composer install --no-scripts --no-interaction --prefer-dist
# Keep APP_KEY and credentials. Adapt only the local Mac runtime and disable outbound work.
docker run --rm -v "$PWD:/var/www/html" -w /var/www/html --entrypoint php laravelsail/php83-composer:latest -r '
$p=".env"; $s=file_get_contents($p);
foreach (["APP_ENV"=>"local","APP_URL"=>"http://localhost:8080","APP_PORT"=>"8080","FORWARD_DB_PORT"=>"3307","DB_HOST"=>"mysql","DB_PORT"=>"3306","WWWUSER"=>"1000","WWWGROUP"=>"1000","MAIL_MAILER"=>"log","WHATSAPP_ENABLED"=>"false","WHATSAPP_TEMPLATE_SYNC_ENABLED"=>"false"] as $k=>$v) {
 $pattern="/^".preg_quote($k,"/")."=.*$/m";
 $s=preg_match($pattern,$s) ? preg_replace($pattern,$k."=".$v,$s) : rtrim($s)."\n".$k."=".$v."\n";
} file_put_contents($p,$s); chmod($p,0600);
'
./vendor/bin/sail up -d
./vendor/bin/sail composer install --no-interaction --prefer-dist
./vendor/bin/sail artisan config:clear
./vendor/bin/sail artisan view:clear
./vendor/bin/sail artisan route:clear
./vendor/bin/sail npm ci
./vendor/bin/sail npm run build
printf '\nEnvironnement prêt. Importez maintenant database.sql selon LIRE-MOI.md.\n'
