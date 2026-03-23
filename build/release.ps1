param(
    [ValidateSet("patch", "minor", "major")]
    [string]$Bump = "patch",
    [string]$Version = "",
    [string]$PrereleaseLabel = "",
    [ValidateRange(0, 9999)]
    [int]$PrereleaseNumber = 0,
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
    if ($raw -notmatch '^\d+\.\d+\.\d+(?:-[0-9A-Za-z-]+(?:\.[0-9A-Za-z-]+)*)?$') {
        throw "VERSION musí být ve formátu MAJOR.MINOR.PATCH nebo MAJOR.MINOR.PATCH-prerelease. Nalezeno: '$raw'"
    }
    return $raw
}

function Parse-SemVer {
    param([string]$Value)
    if ($Value -notmatch '^(?<major>\d+)\.(?<minor>\d+)\.(?<patch>\d+)(?:-(?<prerelease>[0-9A-Za-z-]+(?:\.[0-9A-Za-z-]+)*))?$') {
        throw "Neplatná semver verze: '$Value'"
    }

    $prerelease = ''
    if ($Matches['prerelease']) {
        $prerelease = $Matches['prerelease']
    }

    return @{
        Major = [int]$Matches['major']
        Minor = [int]$Matches['minor']
        Patch = [int]$Matches['patch']
        Prerelease = $prerelease
    }
}

function Compare-SemVer {
    param([string]$Left, [string]$Right)
    $l = Parse-SemVer $Left
    $r = Parse-SemVer $Right

    foreach ($part in @('Major', 'Minor', 'Patch')) {
        if ($l[$part] -lt $r[$part]) { return -1 }
        if ($l[$part] -gt $r[$part]) { return 1 }
    }

    if ($l['Prerelease'] -eq '' -and $r['Prerelease'] -eq '') { return 0 }
    if ($l['Prerelease'] -eq '') { return 1 }
    if ($r['Prerelease'] -eq '') { return -1 }

    $lIdentifiers = $l['Prerelease'].Split('.')
    $rIdentifiers = $r['Prerelease'].Split('.')
    $maxCount = [Math]::Max($lIdentifiers.Count, $rIdentifiers.Count)

    for ($i = 0; $i -lt $maxCount; $i++) {
        if ($i -ge $lIdentifiers.Count) { return -1 }
        if ($i -ge $rIdentifiers.Count) { return 1 }

        $leftId = $lIdentifiers[$i]
        $rightId = $rIdentifiers[$i]
        $leftNumeric = $leftId -match '^\d+$'
        $rightNumeric = $rightId -match '^\d+$'

        if ($leftNumeric -and $rightNumeric) {
            $leftNumber = [int64]$leftId
            $rightNumber = [int64]$rightId
            if ($leftNumber -lt $rightNumber) { return -1 }
            if ($leftNumber -gt $rightNumber) { return 1 }
            continue
        }

        if ($leftNumeric -and -not $rightNumeric) { return -1 }
        if (-not $leftNumeric -and $rightNumeric) { return 1 }

        $ordinal = [string]::CompareOrdinal($leftId, $rightId)
        if ($ordinal -lt 0) { return -1 }
        if ($ordinal -gt 0) { return 1 }
    }

    return 0
}

function Get-BumpedVersion {
    param(
        [string]$Current,
        [string]$Kind,
        [string]$PrereleaseLabel = "",
        [int]$PrereleaseNumber = 0
    )

    $parts = Parse-SemVer $Current
    $currentBase = "$($parts['Major']).$($parts['Minor']).$($parts['Patch'])"
    $currentPre = $parts['Prerelease']

    # Aktuální verze je prerelease (např. 3.0.0-beta.1)
    if ($currentPre -ne '') {
        if ($PrereleaseLabel.Trim() -ne '') {
            # Další prerelease – base zůstává, mění se jen prerelease suffix
            if ($currentPre -match '^([a-zA-Z]+)\.(\d+)$' -and $Matches[1] -eq $PrereleaseLabel) {
                # Stejný label → auto-increment (beta.1 → beta.2)
                $num = if ($PrereleaseNumber -gt 0) { $PrereleaseNumber } else { [int]$Matches[2] + 1 }
            } else {
                # Jiný label → začít od 1 (beta.3 → rc.1)
                $num = if ($PrereleaseNumber -gt 0) { $PrereleaseNumber } else { 1 }
            }
            return "$currentBase-$PrereleaseLabel.$num"
        }
        # Bez labelu → promovat prerelease na stable (3.0.0-beta.3 → 3.0.0)
        return $currentBase
    }

    # Aktuální verze je stable – bump base
    $baseVersion = switch ($Kind) {
        "major" { "$([int]$parts['Major'] + 1).0.0" }
        "minor" { "$($parts['Major']).$([int]$parts['Minor'] + 1).0" }
        default { "$($parts['Major']).$($parts['Minor']).$([int]$parts['Patch'] + 1)" }
    }

    if ($PrereleaseLabel.Trim() -ne '') {
        $num = if ($PrereleaseNumber -gt 0) { $PrereleaseNumber } else { 1 }
        return "$baseVersion-$PrereleaseLabel.$num"
    }

    return $baseVersion
}

function Test-PrereleaseVersion {
    param([string]$Value)
    return (Parse-SemVer $Value)['Prerelease'] -ne ''
}

function Update-Changelog {
    param([string]$Path, [string]$NewVersion)
    if (!(Test-Path $Path)) { return $false }
    $content = [System.IO.File]::ReadAllText($Path, [System.Text.UTF8Encoding]::new($false))
    $today = (Get-Date).ToString("yyyy-MM-dd")
    $updated = $false
    $dash = [char]0x2013  # en-dash
    # 1) Nahradit ## [Unreleased] za ## [verze] - datum
    if ($content -match '(?m)^## \[Unreleased\]') {
        $content = $content -replace '(?m)^## \[Unreleased\].*$', "## [$NewVersion] $dash $today"
        $updated = $true
    }
    # 2) Nebo nahradit ## [verze] bez data za ## [verze] - datum
    elseif ($content -match ('(?m)^## \[' + [regex]::Escape($NewVersion) + '\]\s*$')) {
        $content = $content -replace ('(?m)^## \[' + [regex]::Escape($NewVersion) + '\]\s*$'), "## [$NewVersion] $dash $today"
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

    $exclude = @('.git', '.gitignore', '.claude', 'uploads', 'build', 'dist', 'docs', 'config.php', 'aconfig.php', 'AGENTS.md', '.DS_Store', 'Thumbs.db', '.vscode', '.idea')

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
$normalizedPrereleaseLabel = $PrereleaseLabel.Trim().ToLowerInvariant()
if ($normalizedPrereleaseLabel -ne '' -and $normalizedPrereleaseLabel -notin @('alpha', 'beta', 'rc')) {
    throw "Parametr -PrereleaseLabel musí být alpha, beta nebo rc."
}
if ($Version -and $Version.Trim() -ne "" -and $normalizedPrereleaseLabel -ne '') {
    throw "Použijte buď -Version, nebo kombinaci -PrereleaseLabel/-PrereleaseNumber."
}

$newVersion = if ($Version -and $Version.Trim() -ne "") {
    $Version.Trim()
} else {
    Get-BumpedVersion -Current $currentVersion -Kind $Bump -PrereleaseLabel $normalizedPrereleaseLabel -PrereleaseNumber $PrereleaseNumber
}

if ($newVersion -notmatch '^\d+\.\d+\.\d+(?:-[0-9A-Za-z-]+(?:\.[0-9A-Za-z-]+)*)?$') {
    throw "Verze '$newVersion' není platná. Použijte formát MAJOR.MINOR.PATCH nebo MAJOR.MINOR.PATCH-prerelease."
}
if ((Compare-SemVer -Left $newVersion -Right $currentVersion) -le 0) {
    throw "Nová verze '$newVersion' musí být vyšší než stávající '$currentVersion'."
}
$isPrerelease = Test-PrereleaseVersion -Value $newVersion

$tagName = "v$newVersion"
if ((& git tag --list $tagName) -contains $tagName) {
    throw "Tag $tagName již existuje lokálně."
}

Write-Host "Verze: $currentVersion → $newVersion"
if ($isPrerelease) {
    Write-Host "Typ release: prerelease"
}

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
    $editArgs = @("release", "edit", $tagName, "--title", "Kora CMS $newVersion")
    if ($isPrerelease) {
        $editArgs += "--prerelease"
    }
    & gh @editArgs
    if ($LASTEXITCODE -ne 0) { throw "Úprava GitHub release selhala." }
    & gh release upload $tagName $zipPath --clobber
    if ($LASTEXITCODE -ne 0) { throw "Nahrání assetu do GitHub release selhalo." }
} else {
    $createArgs = @("release", "create", $tagName, $zipPath, "--target", "main", "--title", "Kora CMS $newVersion", "--generate-notes")
    if ($isPrerelease) {
        $createArgs += @("--prerelease", "--latest=false")
    }
    & gh @createArgs
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
