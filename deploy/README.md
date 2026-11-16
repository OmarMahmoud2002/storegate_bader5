# Hostinger deployment

Repository: `OmarMahmoud2002/storegate_bader5`, branch `main`.
The server checkout and script live in `~/storegate-deploy`, outside the web root.

From the local project directory, preview with:

```powershell
.\deploy\hostinger.ps1
```

After committing and pushing the intended application changes to GitHub, apply with:

```powershell
.\deploy\hostinger.ps1 -Apply
```

The wrapper uses the local private key `~/.ssh/storegate_hostinger` and calls
`bash ~/storegate-deploy/hostinger.sh --apply`. The server uses a separate,
read-only GitHub deploy key at `~/.ssh/storegate_github`.

The script fetches `main`, permits only fast-forward updates, validates PHP files
with substantive changes, previews additive schema migrations, backs up the complete
web root and live database, applies pending schema changes, then copies code.
For an apply, maintenance mode blocks web requests while the database backup,
migrations and code deployment run. Original table rows are fingerprinted before
and after deployment; changed values or row counts stop reopening the site.

Exactly one completed snapshot is retained at `~/storegate-deploy/backups/latest.tar`.
It contains `files.tar.gz` (the web root), `database.sql.gz` (SQL export including
triggers, routines and events), `fingerprints.json` and `manifest.txt`. A replacement is built and
verified privately before atomically replacing the previous snapshot. The initial
dated file backups are removed only after the combined snapshot succeeds.
Temporary space for both snapshots is required during creation.

To refresh only the backup, without migrations or code deployment:

```powershell
.\deploy\hostinger.ps1 -BackupOnly
```

InnoDB tables use a consistent transactional dump. If nontransactional tables are
present, the dump uses a global read lock for consistency. File uploads can still
change while the file archive is being built; deploy during a quiet period.

Deployment covers `application`, `system`, public CSS, JavaScript, fonts and
background images. It preserves database.php, config.php, production configuration,
cache, logs, uploads, product images, vendor, index.php and .htaccess. Root utility
scripts and PHP files directly under public are excluded. Files are never deleted;
intentional code deletions require a separately reviewed change.

Composer manifest or lock changes stop deployment and require preparing compatible
dependencies separately. Changes to preserved entry points or server settings
require a separate reviewed update.

Active numbered PHP migrations live in `deploy/schema_migrations`. Commit each new migration with
the matching application change. A migration returns a callable accepting a
`DeploymentSchema`; build SQL with `create`, `column` and `sql`. The read-only plan
prints pending statements. `_storegate_schema_migrations` records successful versions
and normalized SHA-256 checksums. Applied files must never be edited or removed;
add a new numbered migration instead. Each migration must be safe to retry because
MySQL DDL commits implicitly. Only CREATE TABLE IF NOT EXISTS, ADD COLUMN and ADD
UNIQUE KEY statements are allowed. Data updates, inserts, deletes, seeds and destructive
schema changes are rejected. Migration generation from arbitrary PHP is not automatic.
The old `deploy/migrations` directory is retained as history and is never executed.

The initial migrations cover model-managed inventory history and metadata, return
requests, employee leaves, closing extras, serial notes, shelf returns and quantity
tracking. They add schema without filling or changing application data. The new
quantity tracking table starts empty and is populated by later inventory imports.
Existing model initialization remains compatible.

If a migration or code copy fails after the backup, `~/storegate-deploy/deployment-incomplete`
blocks further applies and backup replacement. The site remains in maintenance
if failure occurs after changes start. Review the failed migration and recover
or complete it before removing that marker. Read-only previews remain available.
Database errors stop deployment; no automatic database rollback is attempted.

Copying to the existing web root is not atomic; use a quiet period for deployments.
The script does not perform an application health check or automatic rollback.
For recovery, extract `latest.tar` outside the web root and inspect its manifest.
Restore only the required files, or restore `database.sql.gz` into the intended
database using a private credentials file. Database restore replaces saved data;
review the recovery point first. Restoring the complete file archive may replace
uploads created since the backup.

Migration integration tests use a disposable local MySQL database, automatically
removed afterward: `php deploy/tests/migrations.php`. The test expects local MySQL
on 127.0.0.1 with the Laragon root account and an empty password.

To update the remote script after editing it locally:

```powershell
scp -i "$env:USERPROFILE\.ssh\storegate_hostinger" -P 65002 deploy/hostinger.sh u696297566@93.127.178.126:storegate-deploy/hostinger.sh
```

Before these deployment files are pushed to GitHub, their database tooling is
installed under `~/storegate-deploy/tooling`. Once `deploy` exists on `main`, the
script uses the versioned database tooling and migrations from the checkout.
