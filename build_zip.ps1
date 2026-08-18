# Builds an uploadable plugin zip with forward-slash paths.
# Windows' Compress-Archive writes backslash separators, which break
# plugin installs on Linux WordPress servers. This uses forward slashes.

$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.IO.Compression.FileSystem

$root = $PSScriptRoot
$slug = 'gym-store-for-members'
$zipPath = Join-Path $root ($slug + '.zip')

if (Test-Path $zipPath) {
	Remove-Item $zipPath -Force
}

Push-Location $root
try {
	$files = @('gym-store-for-members.php', 'uninstall.php', 'README.md')
	$files += Get-ChildItem -Recurse -File -Path 'includes', 'admin', 'public' |
		ForEach-Object { (Resolve-Path -Relative $_.FullName) }

	$zip = [System.IO.Compression.ZipFile]::Open($zipPath, 'Create')
	try {
		foreach ($rel in $files) {
			$clean = ($rel -replace '^\.\\', '') -replace '\\', '/'
			$entry = $slug + '/' + $clean
			[System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip, (Join-Path $root $clean), $entry) | Out-Null
		}
	}
	finally {
		$zip.Dispose()
	}
}
finally {
	Pop-Location
}

Write-Host "Built $zipPath"
