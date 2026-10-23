param(
    [string] $Version = ""
)

$ErrorActionPreference = "Stop"

$repoRoot = (Resolve-Path (Join-Path $PSScriptRoot "..")).Path
if (-not $Version) {
    $Version = (git -C $repoRoot describe --tags --always 2>$null).Trim()
    if (-not $Version) { throw "Could not determine a release version. Pass -Version explicitly." }
}

foreach ($command in @("git", "composer", "npm", "tar")) {
    if (-not (Get-Command $command -ErrorAction SilentlyContinue)) {
        throw "Required build command '$command' was not found in PATH."
    }
}

$archive = Join-Path ([System.IO.Path]::GetTempPath()) ("siyazrouter-source-" + [guid]::NewGuid().ToString("N") + ".tar")
$stage = Join-Path ([System.IO.Path]::GetTempPath()) ("siyazrouter-release-" + [guid]::NewGuid().ToString("N"))
$dist = Join-Path $repoRoot "dist"
$zipPath = Join-Path $dist ("siyazpos-router-{0}.zip" -f $Version)

try {
    New-Item -ItemType Directory -Path $stage | Out-Null
    New-Item -ItemType Directory -Path $dist -Force | Out-Null

    # Package committed files only, so local .env, keys, logs and unreviewed edits
    # cannot accidentally enter a customer release.
    git -C $repoRoot archive --format=tar --output=$archive HEAD
    if ($LASTEXITCODE -ne 0) { throw "git archive failed." }
    tar -xf $archive -C $stage
    if ($LASTEXITCODE -ne 0) { throw "Could not extract the committed source archive." }

    # Skip costly classmap optimization and Composer hooks on Windows. This app
    # uses Composer PSR-4 autoloading; Laravel builds its package manifest on
    # first boot, when the target host's bootstrap/cache directory is writable.
    composer config --working-dir=$stage optimize-autoloader false
    if ($LASTEXITCODE -ne 0) { throw "Could not set release build options." }
    composer install --working-dir=$stage --no-dev --prefer-dist --no-interaction --no-scripts
    if ($LASTEXITCODE -ne 0) { throw "Composer dependency installation failed." }
    Copy-Item -LiteralPath (Join-Path $repoRoot "composer.json") -Destination (Join-Path $stage "composer.json") -Force
    npm ci --prefix $stage --no-audit --no-fund
    if ($LASTEXITCODE -ne 0) { throw "Frontend dependency installation failed." }
    npm run build --prefix $stage
    if ($LASTEXITCODE -ne 0) { throw "Frontend asset build failed." }

    Remove-Item -LiteralPath (Join-Path $stage "node_modules") -Recurse -Force
    if (Test-Path -LiteralPath $zipPath) { Remove-Item -LiteralPath $zipPath -Force }
    $sevenZip = Get-Command "7z" -ErrorAction SilentlyContinue
    if (-not $sevenZip) {
        $defaultSevenZip = Join-Path $env:ProgramFiles "7-Zip\7z.exe"
        if (Test-Path -LiteralPath $defaultSevenZip) {
            $sevenZip = Get-Item -LiteralPath $defaultSevenZip
        }
    }
    if ($sevenZip) {
        & $sevenZip.Source a -tzip -mx=1 $zipPath (Join-Path $stage "*") | Out-Null
        if ($LASTEXITCODE -ne 0) { throw "Could not create the release ZIP." }
    }
    else {
        Add-Type -AssemblyName System.IO.Compression.FileSystem
        [System.IO.Compression.ZipFile]::CreateFromDirectory(
            $stage,
            $zipPath,
            [System.IO.Compression.CompressionLevel]::NoCompression,
            $false
        )
    }
    Write-Output "Created $zipPath"
}
finally {
    Remove-Item -LiteralPath $archive -Force -ErrorAction SilentlyContinue
    Remove-Item -LiteralPath $stage -Recurse -Force -ErrorAction SilentlyContinue
}
