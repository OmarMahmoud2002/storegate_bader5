param([switch]$Apply, [switch]$BackupOnly)
$ErrorActionPreference = 'Stop'
if ($Apply -and $BackupOnly) { throw 'Choose Apply or BackupOnly, not both.' }
$deployKeyPath = Join-Path $env:USERPROFILE '.ssh\storegate_hostinger'
if (-not (Test-Path -LiteralPath $deployKeyPath)) {
    throw "SSH key is missing: $deployKeyPath"
}
$deployMode = if ($Apply) { '--apply' } elseif ($BackupOnly) { '--backup-only' } else { '--dry-run' }
& ssh -i $deployKeyPath -o BatchMode=yes -o IdentitiesOnly=yes -o StrictHostKeyChecking=yes -p 65002 u696297566@93.127.178.126 "bash ~/storegate-deploy/hostinger.sh $deployMode"
if ($LASTEXITCODE -ne 0) { throw "Deployment stopped (exit $LASTEXITCODE)." }
