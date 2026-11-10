[CmdletBinding()]
param(
    [Parameter(Mandatory)]
    [ValidateSet('ExportForPr', 'RestoreAfterMain', 'VerifySnapshot', 'SelfTest')]
    [string]$Mode,

    [string]$SourceSqlFile
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$projectRoot = (Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '..')).Path
$composeFile = Join-Path $projectRoot 'compose.yml'
$credentialsFile = Join-Path $projectRoot '.local/credentials.clixml'
$snapshotDirectory = Join-Path $projectRoot 'server/db_dump'
$snapshotFile = Join-Path $snapshotDirectory 'wordpress.sql.gz'
$manifestFile = Join-Path $snapshotDirectory 'manifest.json'
$backupDirectory = Join-Path $projectRoot '.local/db-backups'
$requiredEnvironment = @('WP_DB_PASSWORD', 'WP_ROOT_PASSWORD', 'WP_LOCAL_SALT', 'WP_ADMIN_PASSWORD')
$previousEnvironment = @{}

function Compress-Bytes {
    param([Parameter(Mandatory)][byte[]]$Bytes)
    $output = [IO.MemoryStream]::new()
    try {
        $gzip = [IO.Compression.GZipStream]::new($output, [IO.Compression.CompressionLevel]::Optimal, $true)
        try { $gzip.Write($Bytes, 0, $Bytes.Length) } finally { $gzip.Dispose() }
        return $output.ToArray()
    } finally { $output.Dispose() }
}

function Expand-Bytes {
    param([Parameter(Mandatory)][byte[]]$Bytes)
    $input = [IO.MemoryStream]::new($Bytes)
    $output = [IO.MemoryStream]::new()
    try {
        $gzip = [IO.Compression.GZipStream]::new($input, [IO.Compression.CompressionMode]::Decompress)
        try { $gzip.CopyTo($output) } finally { $gzip.Dispose() }
        return $output.ToArray()
    } finally {
        $input.Dispose()
        $output.Dispose()
    }
}

function Get-Sha256 {
    param([Parameter(Mandatory)][byte[]]$Bytes)
    return [Convert]::ToHexString([Security.Cryptography.SHA256]::HashData($Bytes)).ToLowerInvariant()
}

function Test-SqlDumpBytes {
    param([Parameter(Mandatory)][byte[]]$Bytes)
    if ($Bytes.Length -lt 100) { return $false }
    $sample = [Text.Encoding]::UTF8.GetString($Bytes, 0, [Math]::Min($Bytes.Length, 65536))
    return $sample -match '(?i)(MySQL dump|phpMyAdmin SQL Dump)' -and $sample -match '(?im)^CREATE TABLE\s'
}

function Invoke-NativeChecked {
    param(
        [Parameter(Mandatory)][string]$Command,
        [Parameter(Mandatory)][string[]]$Arguments,
        [switch]$Capture
    )
    $savedPreference = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'
    try {
        if ($Capture) {
            $result = & $Command @Arguments 2>&1
        } else {
            & $Command @Arguments | Out-Host
            $result = $null
        }
        $exitCode = $LASTEXITCODE
    } finally { $ErrorActionPreference = $savedPreference }
    if ($exitCode -ne 0) {
        $detail = if ($Capture) { (($result | ForEach-Object { $_.ToString() }) -join [Environment]::NewLine).Trim() } else { '' }
        throw "$Command failed with exit code $exitCode. $detail"
    }
    if ($Capture) { return (($result | ForEach-Object { $_.ToString() }) -join [Environment]::NewLine).Trim() }
}

function Invoke-Compose {
    param([Parameter(Mandatory)][string[]]$Arguments, [switch]$Capture)
    $dockerArguments = @('compose', '--project-directory', $projectRoot, '-f', $composeFile) + $Arguments
    return Invoke-NativeChecked -Command 'docker' -Arguments $dockerArguments -Capture:$Capture
}

function Assert-Repository {
    if (!(Test-Path -LiteralPath $composeFile -PathType Leaf)) { throw 'compose.yml is missing.' }
    if ((Get-Content -LiteralPath $composeFile -Raw) -notmatch '(?m)^name:\s*osaru-wp\s*$') {
        throw 'Unexpected Compose project. Expected osaru-wp.'
    }
    $topLevel = Invoke-NativeChecked -Command 'git' -Arguments @('-C', $projectRoot, 'rev-parse', '--show-toplevel') -Capture
    if ((Resolve-Path -LiteralPath $topLevel).Path -ne $projectRoot) { throw 'Unexpected Git repository root.' }
}

function Import-LocalCredentials {
    if (!(Test-Path -LiteralPath $credentialsFile -PathType Leaf)) {
        throw 'Encrypted local credentials are missing. Start the development environment first.'
    }
    $encrypted = Import-Clixml -LiteralPath $credentialsFile
    foreach ($key in $requiredEnvironment) {
        if (!$encrypted.ContainsKey($key)) { throw "Credential entry is missing: $key" }
        $previousEnvironment[$key] = [Environment]::GetEnvironmentVariable($key, 'Process')
        $plainValue = [Net.NetworkCredential]::new('', $encrypted[$key]).Password
        [Environment]::SetEnvironmentVariable($key, $plainValue, 'Process')
    }
}

function Clear-LocalCredentials {
    foreach ($key in $previousEnvironment.Keys) {
        [Environment]::SetEnvironmentVariable($key, $previousEnvironment[$key], 'Process')
    }
}

function Assert-LocalDocker {
    $endpoint = Invoke-NativeChecked -Command 'docker' -Arguments @('context', 'inspect', '--format', '{{.Endpoints.docker.Host}}') -Capture
    if ($endpoint -notmatch '^npipe:') { throw 'A local Docker Desktop context is required.' }
    if ($env:DOCKER_HOST -and $env:DOCKER_HOST -notmatch '^npipe:') { throw 'Remote Docker host is not allowed.' }
    Invoke-Compose -Arguments @('config', '--quiet')
    foreach ($service in @('db', 'web')) {
        $containerId = Invoke-Compose -Arguments @('ps', '--status', 'running', '-q', $service) -Capture
        if ([string]::IsNullOrWhiteSpace($containerId)) { throw "Required service is not running: $service" }
    }
    $homeUrl = Invoke-Compose -Arguments @('exec', '-T', '--user', 'www-data', 'web', 'wp', 'option', 'get', 'home') -Capture
    if ($homeUrl -notin @('http://localhost', 'https://localhost')) {
        throw "The WordPress home URL is not local: $homeUrl"
    }
}

function New-DatabaseDump {
    $token = [Guid]::NewGuid().ToString('N')
    $remoteFile = "/tmp/wp-plugin-$token.sql"
    $localFile = Join-Path $projectRoot ".local/wp-plugin-$token.sql"
    [void](New-Item -ItemType Directory -Path (Split-Path -Parent $localFile) -Force)
    try {
        $dumpCommand = 'umask 077; MYSQL_PWD="$MYSQL_PASSWORD" mysqldump -u wordpress --single-transaction --quick --skip-lock-tables --routines --events --triggers --hex-blob --default-character-set=utf8mb4 --set-gtid-purged=OFF --no-tablespaces --add-drop-table wordpress > ' + $remoteFile
        Invoke-Compose -Arguments @('exec', '-T', 'db', 'sh', '-ec', $dumpCommand)
        Invoke-Compose -Arguments @('cp', "db:$remoteFile", $localFile)
        $bytes = [IO.File]::ReadAllBytes($localFile)
        if (!(Test-SqlDumpBytes -Bytes $bytes)) {
            throw 'The database dump is empty or unrecognized.'
        }
        return $bytes
    } finally {
        if (Test-Path -LiteralPath $localFile) { Remove-Item -LiteralPath $localFile -Force }
        try { Invoke-Compose -Arguments @('exec', '-T', 'db', 'rm', '-f', $remoteFile) } catch { Write-Warning "Could not remove the container temporary dump: $($_.Exception.Message)" }
    }
}

function Read-ExistingSqlDump {
    param([Parameter(Mandatory)][string]$Path)
    $resolved = (Resolve-Path -LiteralPath $Path).Path
    $projectPrefix = $projectRoot.TrimEnd([IO.Path]::DirectorySeparatorChar, [IO.Path]::AltDirectorySeparatorChar) + [IO.Path]::DirectorySeparatorChar
    if (!$resolved.StartsWith($projectPrefix, [StringComparison]::OrdinalIgnoreCase)) {
        throw 'SourceSqlFile must be inside this project.'
    }
    if ([IO.Path]::GetExtension($resolved) -ne '.sql') { throw 'SourceSqlFile must be a .sql file.' }
    if ([IO.Path]::GetFileNameWithoutExtension($resolved) -match '(?i)(?:_old(?:_?\d{8})?|_\d{8}_old)$') {
        throw 'Old-data files cannot be imported.'
    }
    $bytes = [IO.File]::ReadAllBytes($resolved)
    if (!(Test-SqlDumpBytes -Bytes $bytes)) {
        throw 'SourceSqlFile is empty or is not a recognized MySQL dump.'
    }
    return @{ Bytes = $bytes; RelativePath = [IO.Path]::GetRelativePath($projectRoot, $resolved).Replace('\', '/') }
}

function Write-GzipDump {
    param(
        [Parameter(Mandatory)][byte[]]$SqlBytes,
        [Parameter(Mandatory)][string]$Destination
    )
    $compressed = Compress-Bytes -Bytes $SqlBytes
    [void](New-Item -ItemType Directory -Path (Split-Path -Parent $Destination) -Force)
    [IO.File]::WriteAllBytes($Destination, $compressed)
}

function Read-GzipDump {
    param([Parameter(Mandatory)][string]$Source)
    if (!(Test-Path -LiteralPath $Source -PathType Leaf)) { throw "Gzip dump is missing: $Source" }
    return Expand-Bytes -Bytes ([IO.File]::ReadAllBytes($Source))
}

function Read-Manifest {
    if (!(Test-Path -LiteralPath $manifestFile -PathType Leaf)) { throw 'Snapshot manifest is missing.' }
    $manifest = Get-Content -LiteralPath $manifestFile -Raw -Encoding UTF8 | ConvertFrom-Json
    if ($manifest.format_version -ne 2 -or $manifest.compose_project -ne 'osaru-wp' -or $manifest.database -ne 'wordpress') {
        throw 'Snapshot manifest target or format is invalid.'
    }
    return $manifest
}

function Assert-Snapshot {
    $manifest = Read-Manifest
    $sqlBytes = Read-GzipDump -Source $snapshotFile
    $actualHash = Get-Sha256 -Bytes $sqlBytes
    if ($actualHash -ne $manifest.plaintext_sha256) { throw 'Snapshot SHA-256 does not match its manifest.' }
    if (!(Test-SqlDumpBytes -Bytes $sqlBytes)) {
        throw 'Decrypted snapshot is not a recognized MySQL dump.'
    }
    return @{ Manifest = $manifest; SqlBytes = $sqlBytes; Sha256 = $actualHash }
}

function Import-DatabaseDump {
    param([Parameter(Mandatory)][byte[]]$SqlBytes)
    $token = [Guid]::NewGuid().ToString('N')
    $remoteFile = "/tmp/wp-plugin-restore-$token.sql"
    $localFile = Join-Path $projectRoot ".local/wp-plugin-restore-$token.sql"
    try {
        [IO.File]::WriteAllBytes($localFile, $SqlBytes)
        Invoke-Compose -Arguments @('cp', $localFile, "db:$remoteFile")
        $importCommand = 'MYSQL_PWD="$MYSQL_PASSWORD" mysql -u wordpress wordpress < ' + $remoteFile
        Invoke-Compose -Arguments @('exec', '-T', 'db', 'sh', '-ec', $importCommand)
    } finally {
        if (Test-Path -LiteralPath $localFile) { Remove-Item -LiteralPath $localFile -Force }
        try { Invoke-Compose -Arguments @('exec', '-T', 'db', 'rm', '-f', $remoteFile) } catch { Write-Warning "Could not remove the container temporary restore file: $($_.Exception.Message)" }
    }
}

function Test-RestoredDatabase {
    $tableCountText = Invoke-Compose -Arguments @('exec', '-T', 'db', 'sh', '-ec', 'MYSQL_PWD="$MYSQL_PASSWORD" mysql -u wordpress -Nse "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=''wordpress''"') -Capture
    $tableCount = 0
    if (![int]::TryParse($tableCountText.Trim(), [ref]$tableCount) -or $tableCount -lt 2) { throw 'The restored database has too few tables.' }
    Invoke-Compose -Arguments @('exec', '-T', '--user', 'www-data', 'web', 'wp', 'core', 'is-installed')
    return $tableCount
}

function Invoke-SelfTest {
    $sample = [Text.Encoding]::UTF8.GetBytes("-- MySQL dump`nCREATE TABLE sample (id int);`n")
    $roundTrip = Expand-Bytes -Bytes (Compress-Bytes -Bytes $sample)
    if (!(Get-Sha256 -Bytes $sample).Equals((Get-Sha256 -Bytes $roundTrip), [StringComparison]::Ordinal)) {
        throw 'Gzip compression round-trip failed.'
    }
    [pscustomobject]@{ mode = 'SelfTest'; verified = $true; plaintext_sha256 = (Get-Sha256 -Bytes $sample) } | ConvertTo-Json
}

if ($Mode -eq 'SelfTest') {
    Invoke-SelfTest
    return
}

Assert-Repository
if ($Mode -eq 'VerifySnapshot') {
    if ($SourceSqlFile) { throw 'SourceSqlFile is only valid with ExportForPr.' }
    $verified = Assert-Snapshot
    [pscustomobject]@{ mode = $Mode; verified = $true; plaintext_sha256 = $verified.Sha256; created_at = $verified.Manifest.created_at } | ConvertTo-Json
    return
}
if ($Mode -eq 'ExportForPr') {
    $branch = Invoke-NativeChecked -Command 'git' -Arguments @('-C', $projectRoot, 'branch', '--show-current') -Capture
    if ([string]::IsNullOrWhiteSpace($branch) -or $branch -eq 'main') { throw 'ExportForPr requires a non-main working branch.' }
    if ($SourceSqlFile) {
        $sourceDump = Read-ExistingSqlDump -Path $SourceSqlFile
        [byte[]]$sqlBytes = $sourceDump.Bytes
        $sourceKind = 'existing-sql-file'
        $sourcePath = $sourceDump.RelativePath
    } else {
        Import-LocalCredentials
        try {
            Assert-LocalDocker
            [byte[]]$sqlBytes = New-DatabaseDump
        } finally { Clear-LocalCredentials }
        $sourceKind = 'running-compose-db'
        $sourcePath = $null
    }
    $hash = Get-Sha256 -Bytes $sqlBytes
    Write-GzipDump -SqlBytes $sqlBytes -Destination $snapshotFile
    $commit = Invoke-NativeChecked -Command 'git' -Arguments @('-C', $projectRoot, 'rev-parse', 'HEAD') -Capture
    $manifest = [ordered]@{
        format_version = 2
        created_at = [DateTimeOffset]::Now.ToString('o')
        compose_project = 'osaru-wp'
        database = 'wordpress'
        source_kind = $sourceKind
        source_path = $sourcePath
        source_branch = $branch
        source_commit = $commit
        compression = 'gzip'
        storage = 'gzip; unencrypted local test data'
        plaintext_sha256 = $hash
        verified = $true
    }
    [void](New-Item -ItemType Directory -Path $snapshotDirectory -Force)
    [IO.File]::WriteAllText($manifestFile, (($manifest | ConvertTo-Json) + [Environment]::NewLine), [Text.UTF8Encoding]::new($false))
    [void](Assert-Snapshot)
    [pscustomobject]@{ mode = $Mode; verified = $true; snapshot = $snapshotFile; manifest = $manifestFile; plaintext_sha256 = $hash; source_kind = $sourceKind } | ConvertTo-Json
    return
}
if ($SourceSqlFile) { throw 'SourceSqlFile is only valid with ExportForPr.' }
Import-LocalCredentials
try {
    Assert-LocalDocker
    if ($Mode -eq 'RestoreAfterMain') {
        $branch = Invoke-NativeChecked -Command 'git' -Arguments @('-C', $projectRoot, 'branch', '--show-current') -Capture
        if ($branch -ne 'main') { throw 'RestoreAfterMain requires the main branch.' }
        $dirty = Invoke-NativeChecked -Command 'git' -Arguments @('-C', $projectRoot, 'status', '--porcelain') -Capture
        if (![string]::IsNullOrWhiteSpace($dirty)) { throw 'RestoreAfterMain requires a clean worktree.' }
        $verified = Assert-Snapshot
        [void](New-Item -ItemType Directory -Path $backupDirectory -Force)
        $backupFile = Join-Path $backupDirectory ("pre-restore_{0}.sql.gz" -f [DateTime]::Now.ToString('yyyyMMddHHmmss'))
        $currentSql = New-DatabaseDump
        Write-GzipDump -SqlBytes $currentSql -Destination $backupFile
        $rollbackHash = Get-Sha256 -Bytes $currentSql
        Invoke-Compose -Arguments @('stop', 'web', 'phpmyadmin')
        try {
            Import-DatabaseDump -SqlBytes $verified.SqlBytes
        } catch {
            $importError = $_.Exception.Message
            $rollbackError = $null
            try {
                Import-DatabaseDump -SqlBytes (Read-GzipDump -Source $backupFile)
                Invoke-Compose -Arguments @('start', 'web', 'phpmyadmin')
            } catch {
                $rollbackError = $_.Exception.Message
            }
            if ($rollbackError) {
                throw "Snapshot import failed and rollback did not complete. Preserve $backupFile. Import error: $importError Rollback error: $rollbackError"
            }
            throw "Snapshot import failed; the pre-restore database was restored. Import error: $importError"
        }
        Invoke-Compose -Arguments @('start', 'web', 'phpmyadmin')
        $tableCount = Test-RestoredDatabase
        [pscustomobject]@{
            mode = $Mode
            verified = $true
            plaintext_sha256 = $verified.Sha256
            pre_restore_backup = $backupFile
            pre_restore_sha256 = $rollbackHash
            table_count = $tableCount
        } | ConvertTo-Json
    }
} finally {
    Clear-LocalCredentials
}
