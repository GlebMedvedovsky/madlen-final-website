param(
    [Parameter(Mandatory = $true)]
    [ValidatePattern('^[0-9a-f-]{36}$')]
    [string]$BackupUuid,

    [string]$OutputDirectory
)

$ErrorActionPreference = 'Stop'
$repositoryRoot = (Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '..\..')).Path
if (-not $OutputDirectory) {
    $OutputDirectory = Join-Path (Split-Path -Parent $repositoryRoot) '_private-madlen-migration-backups'
}
$resolvedOutput = [System.IO.Path]::GetFullPath($OutputDirectory)
$resolvedRepository = [System.IO.Path]::GetFullPath($repositoryRoot)
if ($resolvedOutput.StartsWith($resolvedRepository + [System.IO.Path]::DirectorySeparatorChar, [System.StringComparison]::OrdinalIgnoreCase)) {
    throw 'The private backup output must be outside the Git repository.'
}

New-Item -ItemType Directory -Path $resolvedOutput -Force | Out-Null
$containerId = (& docker compose --project-name madlen ps -q app).Trim()
if (-not $containerId) {
    throw 'The explicitly scoped Madlen app container is not running.'
}

$archiveName = "madlen-$BackupUuid.tar.gz"
$containerArchive = "/workspace/backend/storage/app/releases/backups/$archiveName"
$targetArchive = Join-Path $resolvedOutput $archiveName
& docker cp "${containerId}:${containerArchive}" $targetArchive
if ($LASTEXITCODE -ne 0) {
    throw 'Docker could not copy the selected Madlen backup.'
}

$checksum = (Get-FileHash -Algorithm SHA256 -LiteralPath $targetArchive).Hash.ToLowerInvariant()
$checksumPath = "$targetArchive.sha256"
Set-Content -LiteralPath $checksumPath -Encoding ascii -NoNewline -Value "$checksum  $archiveName`n"

Write-Output "BACKUP_PATH=$targetArchive"
Write-Output "BACKUP_SHA256=$checksum"
Write-Output 'The archive contents were not printed. Run the documented isolated restore test before upload.'
