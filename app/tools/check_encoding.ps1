param(
    [string]$Root = ".",
    [switch]$Strict
)

$ErrorActionPreference = "Stop"

$allowedExt = @(
    ".php", ".js", ".html", ".css", ".md", ".txt", ".sql", ".json",
    ".xml", ".yml", ".yaml", ".ini", ".htaccess"
)

# Third-party minified vendors can trigger false positives for mojibake regex.
$ignoreMojibake = @(
    "assets/vendor/select2/select2.min.4.1.0.js"
)

function Test-Utf8Strict([byte[]]$bytes) {
    try {
        $enc = New-Object System.Text.UTF8Encoding($false, $true)
        $null = $enc.GetString($bytes)
        return $true
    } catch {
        return $false
    }
}

function Is-IgnoredMojibake([string]$path) {
    $norm = $path.Replace("\", "/")
    foreach ($ig in $ignoreMojibake) {
        if ($norm.EndsWith($ig)) { return $true }
    }
    return $false
}

$files = Get-ChildItem -Path $Root -Recurse -File | Where-Object {
    $_.FullName -notmatch "\\.git\\" -and (
        $allowedExt -contains $_.Extension.ToLower() -or $_.Name -eq ".htaccess"
    )
}

$badUtf8 = @()
$bomFiles = @()
$mojibakeFiles = @()

foreach ($f in $files) {
    $bytes = [System.IO.File]::ReadAllBytes($f.FullName)
    if (-not (Test-Utf8Strict $bytes)) {
        $badUtf8 += $f.FullName
        continue
    }

    if ($bytes.Length -ge 3 -and $bytes[0] -eq 0xEF -and $bytes[1] -eq 0xBB -and $bytes[2] -eq 0xBF) {
        $bomFiles += $f.FullName
    }

    if (-not (Is-IgnoredMojibake $f.FullName)) {
        $text = [System.IO.File]::ReadAllText($f.FullName)
        if ([regex]::IsMatch($text, "Ã.|Â.|â€|ï¿½|ðŸ")) {
            $mojibakeFiles += $f.FullName
        }
    }
}

Write-Host "Checked files: $($files.Count)"
Write-Host "Invalid UTF-8: $($badUtf8.Count)"
Write-Host "BOM files: $($bomFiles.Count)"
Write-Host "Mojibake pattern files: $($mojibakeFiles.Count)"

if ($badUtf8.Count -gt 0) {
    Write-Host "`n[ERROR] Invalid UTF-8 files:"
    $badUtf8 | ForEach-Object { Write-Host " - $_" }
}

if ($bomFiles.Count -gt 0) {
    Write-Host "`n[ERROR] UTF-8 BOM files:"
    $bomFiles | ForEach-Object { Write-Host " - $_" }
}

if ($mojibakeFiles.Count -gt 0) {
    Write-Host "`n[ERROR] Mojibake candidates:"
    $mojibakeFiles | ForEach-Object { Write-Host " - $_" }
}

$hasErrors = ($badUtf8.Count -gt 0) -or ($bomFiles.Count -gt 0) -or ($mojibakeFiles.Count -gt 0)
if ($hasErrors -and $Strict) { exit 1 }
if ($hasErrors) { exit 2 }
exit 0
