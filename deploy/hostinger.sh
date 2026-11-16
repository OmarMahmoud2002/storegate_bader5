#!/usr/bin/env bash
# Run on Hostinger: bash ~/storegate-deploy/hostinger.sh [--apply]
set -Eeuo pipefail
umask 077
MODE=${1:---dry-run}
case "$MODE" in --dry-run|--apply|--backup-only) ;; *) echo 'Usage: hostinger.sh [--dry-run|--apply|--backup-only]' >&2; exit 2 ;; esac
BASE="$HOME/storegate-deploy"
SITE="$HOME/domains/storegate.org/public_html"
REPO="$BASE/repository"
mkdir -p "$BASE/backups"
exec 9>"$BASE/deploy.lock"
flock -n 9 || { echo 'Another deployment is running.' >&2; exit 1; }
export GIT_SSH_COMMAND="ssh -i $HOME/.ssh/storegate_github -o IdentitiesOnly=yes -o BatchMode=yes -o StrictHostKeyChecking=yes"
if [[ ! -d "$REPO/.git" ]]; then
  git clone --no-checkout --branch main --single-branch git@github.com:OmarMahmoud2002/storegate_bader5.git "$REPO"
  git -C "$REPO" sparse-checkout set --no-cone /application/ /system/ /public/css/ /public/js/ /public/fonts/ /public/img/backgrounds/ /composer.json /composer.lock /deploy/
  git -C "$REPO" checkout main
fi
[[ -z "$(git -C "$REPO" status --porcelain)" ]] || { echo 'Deployment checkout has local changes; stopping.' >&2; exit 1; }
git -C "$REPO" fetch origin main
git -C "$REPO" merge --ff-only origin/main
COMMIT=$(git -C "$REPO" rev-parse HEAD)
printf 'Target commit: %s\n' "$COMMIT"
TOOLS="$REPO/deploy"
[[ -f "$TOOLS/database.php" ]] || TOOLS="$BASE/tooling"
[[ -f "$TOOLS/database.php" && -d "$TOOLS/schema_migrations" ]] || { echo 'Schema-only database deployment tools are missing.' >&2; exit 1; }
[[ -f "$SITE/application/config/database.php" && -f "$SITE/index.php" ]] || { echo 'Site path is not valid.' >&2; exit 1; }
# Dependency changes need a separate staged Composer installation before deployment.
for dependency in composer.json composer.lock; do
  diff --strip-trailing-cr -q "$REPO/$dependency" "$SITE/$dependency" >/dev/null || { echo "$dependency differs: prepare dependencies before deploying." >&2; exit 1; }
done
FILTERS=(
  '--exclude=/application/config/config.php'
  '--exclude=/application/config/database.php'
  '--exclude=/application/config/production/***'
  '--exclude=/application/cache/***'
  '--exclude=/application/logs/***'
  '--exclude=.env*'
  '--include=/application/***'
  '--include=/system/***'
  '--include=/public/'
  '--include=/public/css/***'
  '--include=/public/js/***'
  '--include=/public/fonts/***'
  '--include=/public/img/'
  '--include=/public/img/backgrounds/***'
  '--exclude=*'
)
# Explicit code allowlist: runtime uploads, vendor, index.php, .htaccess,
# host configuration and root utility scripts remain untouched. No --delete.
# Check PHP files with substantive changes, excluding line-ending-only changes
# in legacy libraries already present on the host.
PHP_LIST=$(mktemp "$BASE/php-files.XXXXXX")
BACKUP_STAGE=''
MAINTENANCE=0
CHANGES_STARTED=0
SAVED_HTACCESS="$BASE/maintenance-original.htaccess"
cleanup() {
  rm -f -- "$PHP_LIST"
  if [[ "$MAINTENANCE" -eq 1 ]]; then
    if [[ "$CHANGES_STARTED" -eq 0 ]]; then
      cp "$SAVED_HTACCESS" "$SITE/.htaccess"
      chmod 644 "$SITE/.htaccess"
      rm -f -- "$SAVED_HTACCESS"
    else
      echo 'Deployment stopped. Site remains in maintenance; backup and recovery marker retained.' >&2
    fi
  fi
  # Only remove the private directory allocated by mktemp for this invocation.
  if [[ "$BACKUP_STAGE" == "$BASE"/backup-stage.* ]]; then rm -rf -- "$BACKUP_STAGE"; fi
}
trap cleanup EXIT
find "$REPO/application" "$REPO/system" -type f -name '*.php' ! -path "$REPO/application/config/config.php" ! -path "$REPO/application/config/database.php" ! -path "$REPO/application/config/production/*" ! -path "$REPO/application/cache/*" ! -path "$REPO/application/logs/*" -print0 > "$PHP_LIST"
while IFS= read -r -d '' phpfile; do
  livefile="$SITE/${phpfile#"$REPO/"}"
  if [[ -f "$livefile" ]] && diff --strip-trailing-cr -q "$phpfile" "$livefile" >/dev/null; then
    continue
  fi
  php -l "$phpfile" >/dev/null
done < "$PHP_LIST"
php -l "$TOOLS/database.php" >/dev/null
php -l "$TOOLS/migration_support.php" >/dev/null
for migration in "$TOOLS"/schema_migrations/[0-9]*.php; do php -l "$migration" >/dev/null; done
php "$TOOLS/database.php" plan "$SITE" "$TOOLS/schema_migrations"
if [[ "$MODE" == --dry-run ]]; then
  rsync -rltcin --no-perms --no-owner --no-group "${FILTERS[@]}" "$REPO/" "$SITE/"
  echo 'Preview only. Apply with: bash ~/storegate-deploy/hostinger.sh --apply'
  exit 0
fi
[[ ! -f "$BASE/deployment-incomplete" ]] || { echo 'Previous deployment was interrupted. Review recovery before replacing its backup.' >&2; exit 1; }
BACKUP_STAGE=$(mktemp -d "$BASE/backup-stage.XXXXXX")
BACKUP="$BASE/backups/latest.tar"
# Archive normal site files before enabling temporary maintenance.
tar -czf "$BACKUP_STAGE/files.tar.gz" -C "$(dirname "$SITE")" "$(basename "$SITE")"
gzip -t "$BACKUP_STAGE/files.tar.gz"
if [[ "$MODE" == --apply ]]; then
  cp "$SITE/.htaccess" "$SAVED_HTACCESS"
  { printf 'ErrorDocument 503 "Site temporarily unavailable during update"\nRewriteEngine On\nRewriteRule ^ - [R=503,L]\n'; cat "$SAVED_HTACCESS"; } > "$SITE/.htaccess.deploy"
  chmod 644 "$SITE/.htaccess.deploy"
  mv "$SITE/.htaccess.deploy" "$SITE/.htaccess"
  MAINTENANCE=1
fi
php "$TOOLS/database.php" dump-config "$SITE" "$BACKUP_STAGE/client.cnf" > "$BACKUP_STAGE/dump-meta"
DUMP_NAME=$(head -n 1 "$BACKUP_STAGE/dump-meta")
DUMP_MODE=$(tail -n 1 "$BACKUP_STAGE/dump-meta")
mysqldump --defaults-extra-file="$BACKUP_STAGE/client.cnf" "$DUMP_MODE" --quick --hex-blob --routines --events --triggers -- "$DUMP_NAME" | gzip > "$BACKUP_STAGE/database.sql.gz"
rm -f -- "$BACKUP_STAGE/client.cnf" "$BACKUP_STAGE/dump-meta"
gzip -t "$BACKUP_STAGE/database.sql.gz"
php "$TOOLS/database.php" fingerprint-save "$SITE" "$BACKUP_STAGE/fingerprints.json"
printf 'created_utc=%s\ntarget_commit=%s\nprevious_commit=%s\n' "$(date -u +%FT%TZ)" "$COMMIT" "$(cat "$BASE/deployed-commit" 2>/dev/null || echo unknown)" > "$BACKUP_STAGE/manifest.txt"
tar -cf "$BACKUP_STAGE/latest.tar" -C "$BACKUP_STAGE" database.sql.gz files.tar.gz manifest.txt fingerprints.json
tar -tf "$BACKUP_STAGE/latest.tar" >/dev/null
# Atomic replacement keeps the previous snapshot if exporting or packaging fails.
mv -f -- "$BACKUP_STAGE/latest.tar" "$BACKUP"
# Consolidate the dated backups produced by earlier versions of this script.
find "$BASE/backups" -maxdepth 1 -type f -name 'site-*.tar.gz' -delete
echo "Single files + database backup: $BACKUP"
if [[ "$MODE" == --backup-only ]]; then exit 0; fi
# Persist this marker across failures so a retry cannot replace the recovery backup.
printf '%s\n' "$COMMIT" > "$BASE/deployment-incomplete"
CHANGES_STARTED=1
php "$TOOLS/database.php" migrate "$SITE" "$TOOLS/schema_migrations"
umask 022
rsync -rltci --no-perms --no-owner --no-group --chmod=Du=rwx,Dgo=rx,Fu=rw,Fgo=r "${FILTERS[@]}" "$REPO/" "$SITE/"
umask 077
php "$TOOLS/database.php" fingerprint-check "$SITE" "$BACKUP_STAGE/fingerprints.json"
printf '%s\n' "$COMMIT" > "$BASE/deployed-commit"
cp "$SAVED_HTACCESS" "$SITE/.htaccess"
chmod 644 "$SITE/.htaccess"
MAINTENANCE=0
rm -f -- "$SAVED_HTACCESS"
rm -f -- "$BASE/deployment-incomplete"
printf 'Applied %s\nBackup: %s\n' "$COMMIT" "$BACKUP"
