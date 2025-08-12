# install_ffmpeg.ps1
$ErrorActionPreference = "Stop"

# 1) Download
$zip = Join-Path $env:TEMP "ffmpeg-release-essentials.zip"
$uri = "https://www.gyan.dev/ffmpeg/builds/ffmpeg-release-essentials.zip"
Write-Host "Downloading FFmpeg..."
Invoke-WebRequest -Uri $uri -OutFile $zip

# 2) Extract to C:\ffmpeg
$dest = "C:\ffmpeg"
$tmp  = "C:\ffmpeg_tmp"
if (Test-Path $tmp) { Remove-Item $tmp -Recurse -Force }
if (Test-Path $dest) { Remove-Item $dest -Recurse -Force }

Write-Host "Extracting..."
Expand-Archive -Path $zip -DestinationPath $tmp -Force
$inner = Get-ChildItem $tmp -Directory | Where-Object { $_.Name -like "ffmpeg-*-essentials_build" } | Select-Object -First 1
if (-not $inner) { throw "Could not find extracted FFmpeg folder" }
Move-Item $inner.FullName $dest
Remove-Item $tmp -Recurse -Force
Remove-Item $zip -Force

$bin = Join-Path $dest "bin"
if (-not (Test-Path $bin)) { throw "FFmpeg bin not found at $bin" }

# 3) Add to PATH safely
function Add-ToPath {
  param([string]$scope)  # "Machine" or "User"
  $cur = [Environment]::GetEnvironmentVariable("Path", $scope)
  if (-not $cur) { $cur = "" }
  if ($cur.Split(';') -contains $bin) { return $true }
  $new = if ($cur.Trim().Length -gt 0) { "$bin;$cur" } else { $bin }
  [Environment]::SetEnvironmentVariable("Path", $new, $scope)
  return $true
}

$addedTo = $null
try {
  # try system PATH first
  Add-ToPath -scope "Machine" | Out-Null
  $addedTo = "system"
} catch {
  # fall back to user PATH
  Add-ToPath -scope "User" | Out-Null
  $addedTo = "user"
}

# 4) Broadcast change so new consoles pick it up
$code = @"
using System;
using System.Runtime.InteropServices;
public class EnvRefresh {
  [DllImport("user32.dll", SetLastError=true, CharSet=CharSet.Auto)]
  public static extern IntPtr SendMessageTimeout(IntPtr hWnd, int Msg, IntPtr wParam, string lParam, int fuFlags, int uTimeout, out IntPtr lpdwResult);
}
"@
Add-Type $code | Out-Null
[void][EnvRefresh]::SendMessageTimeout([IntPtr]0xffff, 0x1A, [IntPtr]0, "Environment", 0x0002, 5000, [ref]([IntPtr]::Zero))

Write-Host ""
Write-Host "FFmpeg installed at $dest"
Write-Host "PATH updated at $addedTo scope"
Write-Host "Open a NEW terminal and run:  ffmpeg -version"
