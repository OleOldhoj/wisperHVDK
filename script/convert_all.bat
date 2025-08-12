@echo off
REM convert_all.bat - Transcribe WAV files to TXT using whisper_transcribe.py
set "BASE=%~1"
if "%BASE%"=="" set "BASE=C:\wisper\sound"
if not exist "%BASE%" echo Directory not found: %BASE% & exit /b 1
for /r "%BASE%" %%F in (*.wav) do if exist "%%~dpnF.txt" (for %%Z in ("%%~dpnF.txt") do if %%~zZ gtr 0 (echo Skipping "%%F") else python "%~dp0whisper_transcribe.py" "%%F" > "%%~dpnF.txt") else python "%~dp0whisper_transcribe.py" "%%F" > "%%~dpnF.txt"
