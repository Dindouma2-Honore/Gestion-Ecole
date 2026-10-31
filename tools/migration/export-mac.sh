#!/usr/bin/env bash
set -Eeuo pipefail
umask 077
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
cd "$ROOT"
mkdir -p .transfer
STAMP="$(date +%Y%m%d-%H%M%S)"
OUT="$ROOT/.transfer/ambassadors-mac-$STAMP"
STAGE="$(mktemp -d "$ROOT/.transfer/staging.XXXXXX")"
trap 'rm -rf "$STAGE"' EXIT
mkdir -p "$STAGE/ssh"
openssl rand -base64 48 > "$OUT.password.txt"
encrypt() {
    openssl enc -aes-256-cbc -salt -pbkdf2 -iter 200000 -md sha256 -pass "file:$OUT.password.txt" -out "$1"
}
# Secrets flow directly into encryption: no unencrypted dump or copied private key.
docker exec ambassadors-app-mysql-1 sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" exec mysqldump -uroot --single-transaction --quick --routines --triggers --events --no-tablespaces --set-gtid-purged=OFF "$MYSQL_DATABASE"' | encrypt "$STAGE/database.sql.enc"
tar -C /home/joelo/.ssh -czf - id_ed25519 id_ed25519.pub known_hosts | encrypt "$STAGE/ssh.tar.gz.enc"
cat > "$STAGE/ssh/config.fragment" <<'CFG'
Host github-ambassadors
    HostName github.com
    User git
    IdentityFile ~/.ssh/id_ed25519_ambassadors
    IdentitiesOnly yes

Host plesk-ambassadors
    HostName crm.ambassadors-aec.com
    User scholl-ambassadors
    IdentityFile ~/.ssh/id_ed25519_ambassadors
    IdentitiesOnly yes
CFG
# Include uncommitted work, Git history, .env, storage documents. Rebuild dependencies.
tar --exclude='./.transfer' --exclude='./.worktrees' --exclude='./vendor' \
    --exclude='./node_modules' --exclude='./.git/worktrees' \
    --exclude='./storage/logs' --exclude='./storage/framework' \
    --exclude='./bootstrap/cache/*.php' --exclude='./public/hot' \
    --exclude='./public/storage' --exclude='./.phpunit.cache' \
    -czf - . | encrypt "$STAGE/project.tar.gz.enc"
cp docs/migration-mac.md "$STAGE/LIRE-MOI.md"
git rev-parse HEAD > "$STAGE/source-commit.txt"
git status --short > "$STAGE/local-changes.txt"
for PART in database.sql.enc project.tar.gz.enc ssh.tar.gz.enc; do
    openssl enc -d -aes-256-cbc -pbkdf2 -iter 200000 -md sha256 -pass "file:$OUT.password.txt" -in "$STAGE/$PART" -out /dev/null
done
(cd "$STAGE" && sha256sum database.sql.enc project.tar.gz.enc ssh.tar.gz.enc > SHA256SUMS)
tar -C "$STAGE" -cf "$OUT.tar" .
(cd "$ROOT/.transfer" && sha256sum "$(basename "$OUT.tar")" > "$(basename "$OUT.sha256")")
printf 'Paquet chiffré vérifié : %s.tar\nMot de passe conservé séparément : %s.password.txt\n' "$OUT" "$OUT"
