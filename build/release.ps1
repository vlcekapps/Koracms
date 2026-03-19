param(
    [ValidateSet("patch", "minor", "major")]
    [string]$Bump = "patch",
    [string]$Version = "",
    [switch]$SkipPush
)

$ErrorActionPreference = "Stop"

# ---------------------------------------------------------------------------
# Pomocné funkce
# ---------------------------------------------------------------------------

function Require-Command {
    param([string]$Name)
    if ($null -eq (Get-Command $Name -ErrorAction SilentlyContinue)) {
        throw "Příkaz '$Name' není dostupný v PATH."
    }
}

function Invoke-Git {
    param([string[]]$GitArgs)
    & git @GitArgs
    if ($LASTEXITCODE -ne 0) {
        throw "git selhal: git $($GitArgs -join ' ')"
    }
}

function Read-VersionFile {
    param([string]$Path)
    if (!(Test-Path $Path)) { throw "Soubor VERSION nenalezen: $Path" }
    $raw = [System.IO.File]::ReadAllText($Path, [System.Text.UTF8Encoding]::new($false)).Trim()
    if ($raw -notmatch '^\d+\.\d+\.\d+$') {
        throw "VERSION musí být ve formátu MAJOR.MINOR.PATCH. Nalezeno: '$raw'"
    }
    return $raw
}

function Parse-SemVer {
    param([string]$Value)
    $parts = $Value.Split('.')
    return @([int]$parts[0], [int]$parts[1], [int]$parts[2])
}

function Compare-SemVer {
    param([string]$Left, [string]$Right)
    $l = Parse-SemVer $Left
    $r = Parse-SemVer $Right
    for ($i = 0; $i -lt 3; $i++) {
        if ($l[$i] -lt $r[$i]) { return -1 }
        if ($l[$i] -gt $r[$i]) { return 1 }
    }
    return 0
}

function Get-BumpedVersion {
    param([string]$Current, [string]$Kind)
    $parts = Parse-SemVer $Current
    switch ($Kind) {
        "major" { return "$([int]$parts[0] + 1).0.0" }
        "minor" { return "$($parts[0]).$([int]$parts[1] + 1).0" }
        default { return "$($parts[0]).$($parts[1]).$([int]$parts[2] + 1)" }
    }
}

function Update-Changelog {
    param([string]$Path, [string]$NewVersion)
    if (!(Test-Path $Path)) { return $false }
    $content = [System.IO.File]::ReadAllText($Path, [System.Text.UTF8Encoding]::new($false))
    $today = (Get-Date).ToString("yyyy-MM-dd")
    $updated = $false
    # 1) Nahradit ## [Unreleased] za ## [verze] – datum
    if ($content -match '(?m)^## \[Unreleased\]') {
        $content = $content -replace '(?m)^## \[Unreleased\].*$', "## [$NewVersion] – $today"
        $updated = $true
    }
    # 2) Nebo nahradit ## [stará verze] bez data za ## [nová verze] – datum (pokud datum chybí)
    elseif ($content -match ('(?m)^## \[' + [regex]::Escape($NewVersion) + '\]\s*$')) {
        $content = $content -replace ('(?m)^## \[' + [regex]::Escape($NewVersion) + '\]\s*$'), "## [$NewVersion] – $today"
        $updated = $true
    }
    if ($updated) {
        [System.IO.File]::WriteAllText($Path, $content, [System.Text.UTF8Encoding]::new($false))
    }
    return $updated
}

function Assert-CleanWorkingTree {
    $status = (& git status --porcelain)
    if ($LASTEXITCODE -ne 0) { throw "Nelze načíst git status." }
    if ($status -and $status.Count -gt 0) {
        throw "Pracovní strom není čistý. Commitujte nebo stashujte změny před spuštěním release.ps1."
    }
}

function New-ReleaseZip {
    param([string]$ProjectRoot, [string]$OutPath)

    $exclude = @('.git', '.gitignore', '.claude', 'uploads', 'build', 'dist', 'config.php', 'aconfig.php', '.DS_Store', 'Thumbs.db', '.vscode', '.idea')

    $tempDir = Join-Path ([System.IO.Path]::GetTempPath()) ("koracms_" + [System.Guid]::NewGuid().ToString("N"))
    New-Item -ItemType Directory -Path $tempDir | Out-Null

    try {
        Get-ChildItem -Path $ProjectRoot -Force | Where-Object {
            $_.Name -notin $exclude
        } | ForEach-Object {
            Copy-Item -Path $_.FullName -Destination $tempDir -Recurse -Force
        }

        if (Test-Path $OutPath) { Remove-Item $OutPath -Force }
        Compress-Archive -Path (Join-Path $tempDir '*') -DestinationPath $OutPath
    } finally {
        Remove-Item $tempDir -Recurse -Force -ErrorAction SilentlyContinue
    }
}

# ---------------------------------------------------------------------------
# Hlavní průběh
# ---------------------------------------------------------------------------

Require-Command -Name git

$projectRoot = (Resolve-Path (Join-Path $PSScriptRoot "..")).Path
$versionPath = Join-Path $projectRoot "VERSION"
$distDir     = Join-Path $projectRoot "dist"

Assert-CleanWorkingTree

$currentVersion = Read-VersionFile -Path $versionPath
$newVersion = if ($Version -and $Version.Trim() -ne "") { $Version.Trim() } else { Get-BumpedVersion -Current $currentVersion -Kind $Bump }

if ($newVersion -notmatch '^\d+\.\d+\.\d+$') {
    throw "Verze '$newVersion' není platná. Použijte formát MAJOR.MINOR.PATCH."
}
if ((Compare-SemVer -Left $newVersion -Right $currentVersion) -le 0) {
    throw "Nová verze '$newVersion' musí být vyšší než stávající '$currentVersion'."
}

$tagName = "v$newVersion"
if ((& git tag --list $tagName) -contains $tagName) {
    throw "Tag $tagName již existuje lokálně."
}

Write-Host "Verze: $currentVersion → $newVersion"

# Aktualizovat VERSION
[System.IO.File]::WriteAllText($versionPath, $newVersion, [System.Text.UTF8Encoding]::new($false))

# Aktualizovat CHANGELOG.md
$changelogPath = Join-Path $projectRoot "CHANGELOG.md"
$changelogUpdated = Update-Changelog -Path $changelogPath -NewVersion $newVersion
if ($changelogUpdated) {
    Write-Host "CHANGELOG.md aktualizován."
} else {
    Write-Host "CHANGELOG.md: nebyla nalezena sekce [Unreleased] ani [$newVersion] bez data – beze změn."
}

# Vytvořit zip
if (!(Test-Path $distDir)) { New-Item -ItemType Directory -Path $distDir | Out-Null }
$zipPath = Join-Path $distDir "koracms-$newVersion.zip"
Write-Host "Vytvářím $zipPath ..."
New-ReleaseZip -ProjectRoot $projectRoot -OutPath $zipPath

# Commit + tag
Invoke-Git @("add", "VERSION")
if ($changelogUpdated) { Invoke-Git @("add", "CHANGELOG.md") }
Invoke-Git @("commit", "-m", "chore(release): $newVersion")

if (-not $SkipPush) {
    Invoke-Git @("push", "origin", "main")
}

Invoke-Git @("tag", "-a", $tagName, "-m", "Release $newVersion")
if (-not $SkipPush) {
    Invoke-Git @("push", "origin", $tagName)
}

# GitHub release
if ($SkipPush) {
    throw "GitHub release nelze vytvořit s -SkipPush. Nejdřív pushněte, pak spusťte bez tohoto přepínače."
}
if (!(Test-Path $zipPath)) {
    throw "Zip soubor nenalezen: $zipPath"
}
Require-Command -Name gh
$releaseExists = $false
try { & gh release view $tagName *> $null 2>&1; $releaseExists = ($LASTEXITCODE -eq 0) } catch { $releaseExists = $false }
if ($releaseExists) {
    & gh release edit $tagName --title "Kora CMS $newVersion"
    if ($LASTEXITCODE -ne 0) { throw "Úprava GitHub release selhala." }
    & gh release upload $tagName $zipPath --clobber
    if ($LASTEXITCODE -ne 0) { throw "Nahrání assetu do GitHub release selhalo." }
} else {
    & gh release create $tagName $zipPath --target main --title "Kora CMS $newVersion" --generate-notes
    if ($LASTEXITCODE -ne 0) { throw "Vytvoření GitHub release selhalo." }
}
# Ověřit, že asset je skutečně přítomný
$assets = & gh release view $tagName --json assets --jq ".assets[].name"
if ($assets -notcontains "koracms-$newVersion.zip") {
    throw "Asset koracms-$newVersion.zip nebyl nalezen v release $tagName."
}

Write-Host ""
Write-Host "Release $newVersion dokončen."
Write-Host "Zip: $zipPath"
