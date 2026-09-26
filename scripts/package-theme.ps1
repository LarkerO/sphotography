# Run from any directory: pwsh -File scripts/package-theme.ps1
$ErrorActionPreference = 'Stop'
$repoRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$outputDir = Join-Path $repoRoot 'dist'
New-Item -ItemType Directory -Force -Path $outputDir | Out-Null
$outputZip = Join-Path $outputDir 'Sphotography.zip'
$files = @('style.css', 'functions.php', 'index.php', 'template-map.php', 'screenshot.png', 'LICENSE', 'LICENSE.original.md', 'LICENSE-GPL-2.0.original.txt')
foreach ($folder in @('admin', 'inc', 'assets')) {
    $files += Get-ChildItem -LiteralPath (Join-Path $repoRoot $folder) -File -Recurse | ForEach-Object {
        [System.IO.Path]::GetRelativePath($repoRoot, $_.FullName).Replace('\', '/')
    } | Where-Object { $_ -notlike 'assets/geo/*' }
}
# Validate all inputs before replacing the previous package.
foreach ($file in $files) {
    if (-not (Test-Path -LiteralPath (Join-Path $repoRoot $file) -PathType Leaf)) { throw "Missing theme file: $file" }
}
Add-Type -AssemblyName System.IO.Compression
$stream = [System.IO.File]::Open($outputZip, [System.IO.FileMode]::Create)
$archive = [System.IO.Compression.ZipArchive]::new($stream, [System.IO.Compression.ZipArchiveMode]::Create)
try {
    foreach ($file in ($files | Sort-Object -Unique)) {
        [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($archive, (Join-Path $repoRoot $file), ('Sphotography/' + $file), [System.IO.Compression.CompressionLevel]::Optimal) | Out-Null
    }
} finally {
    $archive.Dispose()
    $stream.Dispose()
}
Write-Output "Theme package: $outputZip"
