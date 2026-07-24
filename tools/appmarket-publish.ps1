#requires -Version 5.1

[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [ValidateNotNullOrEmpty()]
    [string] $ProjectPath,

    [Parameter(Mandatory = $true)]
    [ValidateNotNull()]
    [uri] $PublishApiUrl,

    [string] $ApkPath = '',

    [string] $ReleaseNotes = '',

    [string] $ReleaseNotesPath = '',

    [ValidateRange(30, 3600)]
    [int] $TimeoutSeconds = 600
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

function Invoke-NativeTool {
    param(
        [Parameter(Mandatory = $true)]
        [string] $FilePath,

        [Parameter(Mandatory = $true)]
        [string[]] $Arguments
    )

    $output = @(& $FilePath @Arguments 2>&1)
    $exitCode = $LASTEXITCODE

    [pscustomobject] @{
        ExitCode = [int] $exitCode
        Output = (($output | ForEach-Object { [string] $_ }) -join [Environment]::NewLine).Trim()
    }
}

function Get-ResolvedPath {
    param(
        [Parameter(Mandatory = $true)]
        [string] $Path,

        [string] $BasePath = ''
    )

    $candidate = $Path
    if (-not [IO.Path]::IsPathRooted($candidate) -and $BasePath -ne '') {
        $candidate = Join-Path -Path $BasePath -ChildPath $candidate
    }

    (Resolve-Path -LiteralPath $candidate).ProviderPath
}

function Test-PathWithin {
    param(
        [Parameter(Mandatory = $true)]
        [string] $Path,

        [Parameter(Mandatory = $true)]
        [string] $Root
    )

    $comparison = if ($env:OS -eq 'Windows_NT') {
        [StringComparison]::OrdinalIgnoreCase
    } else {
        [StringComparison]::Ordinal
    }
    $fullPath = [IO.Path]::GetFullPath($Path)
    $fullRoot = [IO.Path]::GetFullPath($Root).TrimEnd(
        [IO.Path]::DirectorySeparatorChar,
        [IO.Path]::AltDirectorySeparatorChar
    )
    $rootPrefix = $fullRoot + [IO.Path]::DirectorySeparatorChar

    $fullPath.Equals($fullRoot, $comparison) -or $fullPath.StartsWith($rootPrefix, $comparison)
}

function Get-RepositoryRoot {
    param(
        [Parameter(Mandatory = $true)]
        [string] $Path
    )

    $git = Get-Command -Name git -CommandType Application -ErrorAction SilentlyContinue
    if ($null -eq $git) {
        throw 'Git nebyl nalezen v PATH.'
    }

    $result = Invoke-NativeTool -FilePath $git.Source -Arguments @(
        '-C',
        $Path,
        'rev-parse',
        '--show-toplevel'
    )
    if ($result.ExitCode -ne 0 -or [string]::IsNullOrWhiteSpace($result.Output)) {
        throw 'Zadaný Android projekt není uvnitř Git repozitáře.'
    }

    Get-ResolvedPath -Path (($result.Output -split '\r?\n')[-1])
}

function Assert-CleanRepository {
    param(
        [Parameter(Mandatory = $true)]
        [string] $RepositoryRoot
    )

    $git = Get-Command -Name git -CommandType Application -ErrorAction Stop
    $result = Invoke-NativeTool -FilePath $git.Source -Arguments @(
        '-C',
        $RepositoryRoot,
        'status',
        '--porcelain=v1',
        '--untracked-files=all'
    )
    if ($result.ExitCode -ne 0) {
        throw 'Git nedokázal ověřit stav pracovního stromu.'
    }
    if (-not [string]::IsNullOrWhiteSpace($result.Output)) {
        throw 'Publisher odmítl nečistý Git pracovní strom. Změny nejprve commitněte nebo odstraňte.'
    }
}

function Get-ReleaseApk {
    param(
        [Parameter(Mandatory = $true)]
        [string] $RepositoryRoot,

        [string] $RequestedPath
    )

    if (-not [string]::IsNullOrWhiteSpace($RequestedPath)) {
        $resolved = Get-ResolvedPath -Path $RequestedPath -BasePath $RepositoryRoot
        if (-not (Test-PathWithin -Path $resolved -Root $RepositoryRoot)) {
            throw 'APK musí ležet uvnitř ověřovaného Git repozitáře.'
        }
        if ([IO.Path]::GetExtension($resolved) -ine '.apk') {
            throw 'Parametr ApkPath musí odkazovat na soubor APK.'
        }

        return $resolved
    }

    $candidates = @(
        Get-ChildItem -LiteralPath $RepositoryRoot -Filter '*.apk' -File -Recurse -ErrorAction SilentlyContinue |
            Where-Object {
                $relativePath = $_.FullName.Substring($RepositoryRoot.TrimEnd('\', '/').Length)
                $isReleasePath = $relativePath -match (
                    '(?i)(^|[\\/])build[\\/]outputs[\\/]apk[\\/](?:[^\\/]+[\\/])*release[\\/]'
                )
                $isRejectedVariant = $relativePath -match (
                    '(?i)(^|[._\-\\/])(debug|qa|androidtest)(?=$|[._\-\\/])'
                )
                $isRejectedArtifact = $_.Name -match (
                    '(?i)(^|[._-])(unsigned|unaligned)(?=$|[._-])'
                )
                $isReleasePath -and -not $isRejectedVariant -and -not $isRejectedArtifact
            } |
            Sort-Object -Property FullName -Unique
    )

    if ($candidates.Count -eq 0) {
        throw 'Nebyl nalezen produkční release APK. Sestavte release variantu nebo použijte parametr ApkPath.'
    }
    if ($candidates.Count -gt 1) {
        $relativeCandidates = $candidates | ForEach-Object {
            $_.FullName.Substring($RepositoryRoot.TrimEnd('\', '/').Length).TrimStart('\', '/')
        }
        $candidateList = $relativeCandidates -join "`n - "
        throw "Bylo nalezeno více release APK. Vyberte jeden pomocí ApkPath:`n - $candidateList"
    }

    $candidates[0].FullName
}

function Get-VersionSortValue {
    param([string] $Value)

    $parsed = $null
    if ([version]::TryParse($Value, [ref] $parsed)) {
        return $parsed
    }

    [version] '0.0'
}

function Find-AndroidSdkTool {
    param(
        [Parameter(Mandatory = $true)]
        [ValidateSet('apkanalyzer', 'apksigner')]
        [string] $Name
    )

    $fileNames = if ($env:OS -eq 'Windows_NT') {
        @("$Name.bat", "$Name.cmd", "$Name.exe")
    } else {
        @($Name)
    }

    foreach ($fileName in $fileNames) {
        $command = Get-Command -Name $fileName -CommandType Application -ErrorAction SilentlyContinue |
            Select-Object -First 1
        if ($null -ne $command) {
            return $command.Source
        }
    }

    $sdkRoots = @(
        [Environment]::GetEnvironmentVariable('ANDROID_SDK_ROOT', 'Process'),
        [Environment]::GetEnvironmentVariable('ANDROID_HOME', 'Process')
    ) | Where-Object {
        -not [string]::IsNullOrWhiteSpace($_) -and (Test-Path -LiteralPath $_ -PathType Container)
    } | Select-Object -Unique

    foreach ($sdkRoot in $sdkRoots) {
        if ($Name -eq 'apkanalyzer') {
            $cmdlineRoot = Join-Path -Path $sdkRoot -ChildPath 'cmdline-tools'
            if (Test-Path -LiteralPath $cmdlineRoot -PathType Container) {
                $toolDirectories = @(
                    Get-ChildItem -LiteralPath $cmdlineRoot -Directory -ErrorAction SilentlyContinue |
                        Sort-Object -Property @{
                            Expression = {
                                if ($_.Name -eq 'latest') {
                                    [version] '9999.0'
                                } else {
                                    Get-VersionSortValue -Value $_.Name
                                }
                            }
                            Descending = $true
                        }
                )
                foreach ($directory in $toolDirectories) {
                    foreach ($fileName in $fileNames) {
                        $candidate = Join-Path -Path $directory.FullName -ChildPath "bin\$fileName"
                        if (Test-Path -LiteralPath $candidate -PathType Leaf) {
                            return (Resolve-Path -LiteralPath $candidate).ProviderPath
                        }
                    }
                }
            }

            foreach ($fileName in $fileNames) {
                $legacyCandidate = Join-Path -Path $sdkRoot -ChildPath "tools\bin\$fileName"
                if (Test-Path -LiteralPath $legacyCandidate -PathType Leaf) {
                    return (Resolve-Path -LiteralPath $legacyCandidate).ProviderPath
                }
            }
        } else {
            $buildToolsRoot = Join-Path -Path $sdkRoot -ChildPath 'build-tools'
            if (Test-Path -LiteralPath $buildToolsRoot -PathType Container) {
                $buildToolDirectories = @(
                    Get-ChildItem -LiteralPath $buildToolsRoot -Directory -ErrorAction SilentlyContinue |
                        Sort-Object -Property @{
                            Expression = { Get-VersionSortValue -Value $_.Name }
                            Descending = $true
                        }
                )
                foreach ($directory in $buildToolDirectories) {
                    foreach ($fileName in $fileNames) {
                        $candidate = Join-Path -Path $directory.FullName -ChildPath $fileName
                        if (Test-Path -LiteralPath $candidate -PathType Leaf) {
                            return (Resolve-Path -LiteralPath $candidate).ProviderPath
                        }
                    }
                }
            }
        }
    }

    throw "Android SDK nástroj $Name nebyl nalezen v PATH, ANDROID_SDK_ROOT ani ANDROID_HOME."
}

function Get-ManifestValue {
    param(
        [Parameter(Mandatory = $true)]
        [string] $ApkanalyzerPath,

        [Parameter(Mandatory = $true)]
        [string] $ApkPath,

        [Parameter(Mandatory = $true)]
        [string] $Field
    )

    $result = Invoke-NativeTool -FilePath $ApkanalyzerPath -Arguments @(
        'manifest',
        $Field,
        $ApkPath
    )
    if ($result.ExitCode -ne 0) {
        throw "apkanalyzer nedokázal ověřit manifestové pole $Field."
    }

    $lines = @(
        $result.Output -split '\r?\n' |
            ForEach-Object { $_.Trim() } |
            Where-Object {
                $isMeaningful = $_ -ne ''
                $isDiagnostic = $_ -match '^(?i:warning|info|picked up |_java_options|openjdk)'
                $isMeaningful -and -not $isDiagnostic
            }
    )
    if ($lines.Count -eq 0) {
        return ''
    }

    $lines[-1]
}

function Get-PositiveInteger {
    param(
        [Parameter(Mandatory = $true)]
        [string] $Value,

        [Parameter(Mandatory = $true)]
        [string] $Label
    )

    $parsed = 0L
    if (-not [long]::TryParse($Value.Trim(), [ref] $parsed) -or $parsed -le 0) {
        throw "$Label není platné kladné celé číslo."
    }

    $parsed
}

function Convert-SecureStringToPlainText {
    param(
        [Parameter(Mandatory = $true)]
        [Security.SecureString] $Value
    )

    $pointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($Value)
    try {
        [Runtime.InteropServices.Marshal]::PtrToStringBSTR($pointer)
    } finally {
        [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($pointer)
    }
}

function Get-ResponsePayload {
    param(
        [Parameter(Mandatory = $true)]
        [string] $Json
    )

    if ([string]::IsNullOrWhiteSpace($Json)) {
        return $null
    }

    try {
        $Json | ConvertFrom-Json -ErrorAction Stop
    } catch {
        $null
    }
}

function Get-TextSha256 {
    param(
        [Parameter(Mandatory = $true)]
        [AllowEmptyString()]
        [string] $Text
    )

    $sha256 = [Security.Cryptography.SHA256]::Create()
    try {
        $bytes = [Text.UTF8Encoding]::new($false).GetBytes($Text)
        ([BitConverter]::ToString($sha256.ComputeHash($bytes)) -replace '-', '').ToLowerInvariant()
    } finally {
        $sha256.Dispose()
    }
}

$tokenText = [Environment]::GetEnvironmentVariable('KORA_APPMARKET_TOKEN', 'Process')
if ([string]::IsNullOrWhiteSpace($tokenText)) {
    throw 'Chybí env KORA_APPMARKET_TOKEN. Token se z bezpečnostních důvodů nepřijímá jako parametr.'
}
$signingKeyPath = [Environment]::GetEnvironmentVariable('KORA_APPMARKET_SIGNING_KEY', 'Process')
if ([string]::IsNullOrWhiteSpace($signingKeyPath)) {
    throw 'Chybí env KORA_APPMARKET_SIGNING_KEY s cestou k privátnímu publisher klíči.'
}
$tokenSecure = ConvertTo-SecureString -String $tokenText -AsPlainText -Force
$tokenText = $null

# Child procesy s Android nástroji token ani cestu ke klíči nepotřebují a nesmějí je zdědit.
[Environment]::SetEnvironmentVariable('KORA_APPMARKET_TOKEN', $null, 'Process')
[Environment]::SetEnvironmentVariable('KORA_APPMARKET_SIGNING_KEY', $null, 'Process')

try {
    if (-not $PublishApiUrl.IsAbsoluteUri) {
        throw 'PublishApiUrl musí být absolutní URL.'
    }
    if ($PublishApiUrl.UserInfo -ne '' -or $PublishApiUrl.Query -ne '' -or $PublishApiUrl.Fragment -ne '') {
        throw 'PublishApiUrl nesmí obsahovat přihlašovací údaje, query ani fragment.'
    }
    if ($PublishApiUrl.Scheme -ne 'https' -and -not $PublishApiUrl.IsLoopback) {
        throw 'Publish API musí používat HTTPS; HTTP je povoleno pouze pro localhost.'
    }
    if ($ReleaseNotes -ne '' -and $ReleaseNotesPath -ne '') {
        throw 'Použijte jen jeden z parametrů ReleaseNotes a ReleaseNotesPath.'
    }

    $resolvedProject = Get-ResolvedPath -Path $ProjectPath
    if (-not (Test-Path -LiteralPath $resolvedProject -PathType Container)) {
        throw 'ProjectPath musí odkazovat na existující adresář.'
    }
    $repositoryRoot = Get-RepositoryRoot -Path $resolvedProject
    Assert-CleanRepository -RepositoryRoot $repositoryRoot
    $resolvedSigningKey = Get-ResolvedPath -Path $signingKeyPath
    if (Test-PathWithin -Path $resolvedSigningKey -Root $repositoryRoot) {
        throw 'Privátní publisher klíč musí ležet mimo zdrojový Git repozitář aplikace.'
    }
    $php = Get-Command -Name php -CommandType Application -ErrorAction SilentlyContinue
    if ($null -eq $php) {
        throw 'PHP CLI nebylo nalezeno v PATH a publisher proto nemůže podepsat manifest.'
    }
    $attestationTool = Join-Path -Path $PSScriptRoot -ChildPath 'appmarket-attest.php'
    if (-not (Test-Path -LiteralPath $attestationTool -PathType Leaf)) {
        throw 'Vedle publisheru chybí nástroj tools/appmarket-attest.php.'
    }
    $fingerprintResult = Invoke-NativeTool -FilePath $php.Source -Arguments @(
        $attestationTool,
        'fingerprint',
        $resolvedSigningKey
    )
    $fingerprintPayload = Get-ResponsePayload -Json $fingerprintResult.Output
    if (($fingerprintResult.ExitCode -ne 0) -or
        ($null -eq $fingerprintPayload) -or
        ($null -eq $fingerprintPayload.PSObject.Properties['key_fingerprint_sha256'])
    ) {
        throw 'Privátní publisher klíč se nepodařilo ověřit.'
    }
    $attestationKeyFingerprint = ([string] $fingerprintPayload.key_fingerprint_sha256).ToLowerInvariant()
    if ($attestationKeyFingerprint -notmatch '^[a-f0-9]{64}$') {
        throw 'Privátní publisher klíč nevrátil platný SHA-256 otisk.'
    }

    $resolvedApk = Get-ReleaseApk -RepositoryRoot $repositoryRoot -RequestedPath $ApkPath
    if (-not (Test-Path -LiteralPath $resolvedApk -PathType Leaf)) {
        throw 'Release APK nebyl nalezen.'
    }
    $apkInfo = Get-Item -LiteralPath $resolvedApk
    if ($apkInfo.Length -le 0) {
        throw 'Release APK je prázdný.'
    }

    $relativeApkPath = $resolvedApk.Substring(
        $repositoryRoot.TrimEnd('\', '/').Length
    ).TrimStart('\', '/')
    $hasRejectedVariant = $relativeApkPath -match (
        '(?i)(^|[._\-\\/])(debug|qa|androidtest)(?=$|[._\-\\/])'
    )
    $hasRejectedArtifactName = $apkInfo.Name -match (
        '(?i)(^|[._-])(unsigned|unaligned)(?=$|[._-])'
    )
    if ($hasRejectedVariant -or $hasRejectedArtifactName) {
        throw 'Publisher odmítl debug, QA, androidTest, unsigned nebo unaligned APK.'
    }

    $apkanalyzer = Find-AndroidSdkTool -Name 'apkanalyzer'
    $apksigner = Find-AndroidSdkTool -Name 'apksigner'

    $packageId = Get-ManifestValue -ApkanalyzerPath $apkanalyzer -ApkPath $resolvedApk -Field 'application-id'
    $versionName = Get-ManifestValue -ApkanalyzerPath $apkanalyzer -ApkPath $resolvedApk -Field 'version-name'
    $versionCode = Get-PositiveInteger -Value (
        Get-ManifestValue -ApkanalyzerPath $apkanalyzer -ApkPath $resolvedApk -Field 'version-code'
    ) -Label 'versionCode'
    $minSdk = Get-PositiveInteger -Value (
        Get-ManifestValue -ApkanalyzerPath $apkanalyzer -ApkPath $resolvedApk -Field 'min-sdk'
    ) -Label 'minSdk'
    $targetSdk = Get-PositiveInteger -Value (
        Get-ManifestValue -ApkanalyzerPath $apkanalyzer -ApkPath $resolvedApk -Field 'target-sdk'
    ) -Label 'targetSdk'
    $debuggableValue = (
        Get-ManifestValue -ApkanalyzerPath $apkanalyzer -ApkPath $resolvedApk -Field 'debuggable'
    ).ToLowerInvariant()

    if ($packageId -notmatch '^[A-Za-z][A-Za-z0-9_]*(\.[A-Za-z][A-Za-z0-9_]*)+$') {
        throw 'APK neobsahuje platné Android applicationId.'
    }
    if ($packageId.Length -gt 255) {
        throw 'Android applicationId překračuje bezpečný limit 255 znaků.'
    }
    if ([string]::IsNullOrWhiteSpace($versionName) -or $versionName.Length -gt 100) {
        throw 'APK neobsahuje platné versionName.'
    }
    if ($debuggableValue -ne 'false') {
        throw 'Publisher přijímá jen APK s android:debuggable=false.'
    }
    if ("$packageId`n$versionName" -match '(?i)(^|[._\-\\/])(debug|qa|androidtest)(?=$|[._\-\\/])') {
        throw 'ApplicationId nebo versionName odpovídá debug/QA sestavení.'
    }

    $permissionResult = Invoke-NativeTool -FilePath $apkanalyzer -Arguments @(
        'manifest',
        'permissions',
        $resolvedApk
    )
    if ($permissionResult.ExitCode -ne 0) {
        throw 'apkanalyzer nedokázal ověřit oprávnění APK.'
    }
    $permissions = @(
        $permissionResult.Output -split '\r?\n' |
            ForEach-Object { $_.Trim() } |
            Where-Object { $_ -match '^[A-Za-z0-9._-]{1,255}$' } |
            Sort-Object -Unique
    )
    if ($permissions.Count -gt 256) {
        throw 'APK deklaruje více než 256 oprávnění a publisher jej odmítl.'
    }

    $signatureResult = Invoke-NativeTool -FilePath $apksigner -Arguments @(
        'verify',
        '--verbose',
        '--print-certs',
        $resolvedApk
    )
    if ($signatureResult.ExitCode -ne 0) {
        throw 'apksigner odmítl APK: produkční podpis není platný.'
    }

    $fingerprintMatch = [regex]::Match(
        $signatureResult.Output,
        '(?im)^\s*Signer #1 certificate SHA-256 digest:\s*([A-Fa-f0-9: ]{64,})\s*$'
    )
    $subjectMatch = [regex]::Match(
        $signatureResult.Output,
        '(?im)^\s*Signer #1 certificate DN:\s*(.+?)\s*$'
    )
    if (-not $fingerprintMatch.Success) {
        throw 'Z ověřeného podpisu se nepodařilo získat SHA-256 certifikátu.'
    }

    $certificateSha256 = ($fingerprintMatch.Groups[1].Value -replace '[^A-Fa-f0-9]', '').ToLowerInvariant()
    if ($certificateSha256 -notmatch '^[a-f0-9]{64}$') {
        throw 'Podpisový certifikát nemá platný SHA-256 fingerprint.'
    }
    $certificateSubject = if ($subjectMatch.Success) {
        $subjectMatch.Groups[1].Value.Trim()
    } else {
        ''
    }
    if ($certificateSubject.Length -gt 4000) {
        throw 'Identita podpisového certifikátu překračuje bezpečný limit.'
    }
    if ($certificateSubject -match '(?i)Android\s+Debug') {
        throw 'Publisher odmítl APK podepsaný Android debug certifikátem.'
    }

    $apkSha256 = (Get-FileHash -LiteralPath $resolvedApk -Algorithm SHA256).Hash.ToLowerInvariant()
    if ($apkSha256 -notmatch '^[a-f0-9]{64}$') {
        throw 'Nepodařilo se vypočítat SHA-256 APK.'
    }

    if ($ReleaseNotesPath -ne '') {
        $resolvedNotesPath = Get-ResolvedPath -Path $ReleaseNotesPath -BasePath $repositoryRoot
        if (-not (Test-PathWithin -Path $resolvedNotesPath -Root $repositoryRoot)) {
            throw 'Soubor s poznámkami k vydání musí ležet uvnitř ověřovaného Git repozitáře.'
        }
        $ReleaseNotes = Get-Content -LiteralPath $resolvedNotesPath -Raw -Encoding UTF8
    }
    $ReleaseNotes = (($ReleaseNotes -replace "`r`n", "`n") -replace "`r", "`n").Trim()
    if ($ReleaseNotes.Length -gt 50000) {
        throw 'Poznámky k vydání překračují bezpečný limit 50 000 znaků.'
    }

    $git = Get-Command -Name git -CommandType Application -ErrorAction Stop
    $commitResult = Invoke-NativeTool -FilePath $git.Source -Arguments @(
        '-C',
        $repositoryRoot,
        'rev-parse',
        'HEAD'
    )
    $sourceCommit = $commitResult.Output.Trim().ToLowerInvariant()
    if ($commitResult.ExitCode -ne 0 -or $sourceCommit -notmatch '^[a-f0-9]{40,64}$') {
        throw 'Git nedokázal určit zdrojový commit vydání.'
    }

    $metadata = [ordered] @{
        schema_version = 2
        attestation_type = 'kora-appmarket-release'
        attestation_algorithm = 'rsa-sha256'
        key_fingerprint_sha256 = $attestationKeyFingerprint
        issued_at = [DateTime]::UtcNow.ToString(
            'yyyy-MM-ddTHH:mm:ssZ',
            [Globalization.CultureInfo]::InvariantCulture
        )
        nonce = [Guid]::NewGuid().ToString('N')
        source_commit = $sourceCommit
        release_notes_sha256 = Get-TextSha256 -Text $ReleaseNotes
        package_id = $packageId
        version_name = $versionName
        version_code = $versionCode
        min_sdk = $minSdk
        target_sdk = $targetSdk
        certificate_sha256 = $certificateSha256
        certificate_subject = $certificateSubject
        certificate_serial = ''
        certificate_valid_from = ''
        certificate_valid_to = ''
        debuggable = $false
        build_type = 'release'
        permissions = $permissions
        apk_sha256 = $apkSha256
        apk_size = $apkInfo.Length
    }
    $metadataJson = $metadata | ConvertTo-Json -Depth 5 -Compress
    if ([Text.Encoding]::UTF8.GetByteCount($metadataJson) -gt 65536) {
        throw 'Metadata vydání překračují bezpečný limit 64 KiB.'
    }

    $manifestPath = [IO.Path]::GetTempFileName()
    try {
        [IO.File]::WriteAllText(
            $manifestPath,
            $metadataJson,
            [Text.UTF8Encoding]::new($false)
        )
        $signatureResult = Invoke-NativeTool -FilePath $php.Source -Arguments @(
            $attestationTool,
            'sign',
            $resolvedSigningKey,
            $manifestPath
        )
        $signaturePayload = Get-ResponsePayload -Json $signatureResult.Output
        if (($signatureResult.ExitCode -ne 0) -or
            ($null -eq $signaturePayload) -or
            ($null -eq $signaturePayload.PSObject.Properties['signature_base64']) -or
            ($null -eq $signaturePayload.PSObject.Properties['key_fingerprint_sha256'])
        ) {
            throw 'Publisher manifest se nepodařilo podepsat.'
        }
        $returnedFingerprint = ([string] $signaturePayload.key_fingerprint_sha256).ToLowerInvariant()
        $attestationSignature = [string] $signaturePayload.signature_base64
        if (($returnedFingerprint -ne $attestationKeyFingerprint) -or
            ($attestationSignature -notmatch '^[A-Za-z0-9+/]+={0,2}$') -or
            ($attestationSignature.Length -gt 4096)
        ) {
            throw 'Podpis publisher manifestu neodpovídá očekávanému klíči.'
        }
    } finally {
        if (Test-Path -LiteralPath $manifestPath -PathType Leaf) {
            Remove-Item -LiteralPath $manifestPath -Force
        }
    }

    Add-Type -AssemblyName System.Net.Http
    $handler = [Net.Http.HttpClientHandler]::new()
    $handler.AllowAutoRedirect = $false
    $client = [Net.Http.HttpClient]::new($handler)
    $multipart = [Net.Http.MultipartFormDataContent]::new()
    $fileStream = $null
    $response = $null
    try {
        $client.Timeout = [TimeSpan]::FromSeconds($TimeoutSeconds)
        $client.DefaultRequestHeaders.UserAgent.ParseAdd('Kora-Appmarket-Publisher/1.0')
        $client.DefaultRequestHeaders.ExpectContinue = $false

        $plainToken = Convert-SecureStringToPlainText -Value $tokenSecure
        try {
            $client.DefaultRequestHeaders.Authorization = [Net.Http.Headers.AuthenticationHeaderValue]::new(
                'Bearer',
                $plainToken
            )
        } finally {
            $plainToken = $null
        }

        $fileStream = [IO.File]::Open(
            $resolvedApk,
            [IO.FileMode]::Open,
            [IO.FileAccess]::Read,
            [IO.FileShare]::Read
        )
        $fileContent = [Net.Http.StreamContent]::new($fileStream)
        $fileContent.Headers.ContentType = [Net.Http.Headers.MediaTypeHeaderValue]::new(
            'application/vnd.android.package-archive'
        )
        $multipart.Add($fileContent, 'apk', $apkInfo.Name)
        $multipart.Add(
            [Net.Http.StringContent]::new($metadataJson, [Text.Encoding]::UTF8, 'application/json'),
            'metadata'
        )
        $multipart.Add(
            [Net.Http.StringContent]::new(
                $attestationSignature,
                [Text.Encoding]::UTF8,
                'text/plain'
            ),
            'attestation_signature'
        )
        $multipart.Add(
            [Net.Http.StringContent]::new($ReleaseNotes, [Text.Encoding]::UTF8, 'text/plain'),
            'release_notes'
        )

        $response = $client.PostAsync($PublishApiUrl, $multipart).GetAwaiter().GetResult()
        $responseJson = $response.Content.ReadAsStringAsync().GetAwaiter().GetResult()
        $payload = Get-ResponsePayload -Json $responseJson

        if (-not $response.IsSuccessStatusCode) {
            $safeMessages = @()
            $responseToken = Convert-SecureStringToPlainText -Value $tokenSecure
            try {
                if ($null -ne $payload -and $null -ne $payload.PSObject.Properties['messages']) {
                    $safeMessages = @(
                        $payload.messages | ForEach-Object {
                            ([string] $_).Replace($responseToken, '[REDACTED]')
                        }
                    )
                }
                $safeError = if ($null -ne $payload -and $null -ne $payload.PSObject.Properties['error']) {
                    ([string] $payload.error).Replace($responseToken, '[REDACTED]')
                } else {
                    'publish_failed'
                }
            } finally {
                $responseToken = $null
            }
            if ($safeError -notmatch '^[a-z0-9_]{1,64}$') {
                $safeError = 'publish_failed'
            }
            $detail = if ($safeMessages.Count -gt 0) {
                ': ' + ($safeMessages -join '; ')
            } else {
                ''
            }
            throw "Publish API vrátilo HTTP $([int] $response.StatusCode) ($safeError)$detail"
        }

        $hasExpectedPayload = $null -ne $payload
        $hasStatusProperty = $hasExpectedPayload -and $null -ne $payload.PSObject.Properties['status']
        $isDraft = $hasStatusProperty -and [string] $payload.status -eq 'draft'
        if (-not $isDraft) {
            throw 'Publish API nevrátilo očekávané potvrzení konceptu.'
        }

        $releaseId = if ($null -ne $payload.PSObject.Properties['release_id']) {
            [string] $payload.release_id
        } else {
            ''
        }
        if ($releaseId -notmatch '^[1-9][0-9]{0,18}$') {
            $releaseId = 'neuvedeno'
        }
        Write-Output "Appmarket přijal koncept vydání $versionName ($versionCode), release ID: $releaseId."
        Write-Output "Balíček: $packageId"
        Write-Output "APK SHA-256: $apkSha256"
        Write-Output 'Koncept nyní musí zkontrolovat a publikovat superadmin Kora CMS.'
    } finally {
        if ($null -ne $response) {
            $response.Dispose()
        }
        if ($null -ne $multipart) {
            $multipart.Dispose()
        }
        if ($null -ne $fileStream) {
            $fileStream.Dispose()
        }
        if ($null -ne $client) {
            $client.Dispose()
        }
        if ($null -ne $handler) {
            $handler.Dispose()
        }
    }
} finally {
    if ($null -ne $tokenSecure) {
        $restoredToken = Convert-SecureStringToPlainText -Value $tokenSecure
        try {
            [Environment]::SetEnvironmentVariable('KORA_APPMARKET_TOKEN', $restoredToken, 'Process')
        } finally {
            $restoredToken = $null
            $tokenSecure.Dispose()
        }
    }
    [Environment]::SetEnvironmentVariable('KORA_APPMARKET_SIGNING_KEY', $signingKeyPath, 'Process')
}
