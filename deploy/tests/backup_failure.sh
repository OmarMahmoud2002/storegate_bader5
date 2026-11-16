#!/usr/bin/env bash
# Run on Hostinger after a successful backup; only a read-only plan is performed.
set -Eeuo pipefail
BASE="$HOME/storegate-deploy"
BACKUP="$BASE/backups/latest.tar"
[[ -f "$BACKUP" ]] || { echo 'Create the first backup before testing.' >&2; exit 1; }
BEFORE=$(sha256sum "$BACKUP")
TEST_DIR=$(mktemp -d "$BASE/backup-failure-test.XXXXXX")
cleanup() { if [[ "$TEST_DIR" == "$BASE"/backup-failure-test.* ]]; then rm -rf -- "$TEST_DIR"; fi; }
trap cleanup EXIT
printf '#!/usr/bin/env bash\necho "Simulated dump failure" >&2\nexit 73\n' > "$TEST_DIR/mysqldump"
chmod 700 "$TEST_DIR/mysqldump"
if PATH="$TEST_DIR:$PATH" bash "$BASE/hostinger.sh" --backup-only > "$TEST_DIR/result.log" 2>&1; then
  echo 'FAIL: backup unexpectedly succeeded.' >&2; exit 1
fi
grep -q 'Simulated dump failure' "$TEST_DIR/result.log"
[[ "$(sha256sum "$BACKUP")" == "$BEFORE" ]]
[[ "$(find "$BASE/backups" -maxdepth 1 -type f | wc -l)" -eq 1 ]]
[[ -z "$(find "$BASE" -maxdepth 1 -type d -name 'backup-stage.*' -print -quit)" ]]
echo 'PASS: failed database export preserved the single completed backup and cleaned temporary files.'
