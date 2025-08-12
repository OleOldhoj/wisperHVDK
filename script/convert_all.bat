@echo off
REM convert_all.bat - Transcribe WAV files to TXT using whisper_transcribe.py with debug info
set "BASE=%~1"
if "%BASE%"=="" set "BASE=C:\wisper\sound"
echo DEBUG: Base directory is %BASE%
if not exist "%BASE%" echo DEBUG: Directory not found: %BASE% & exit /b 1
for /r "%BASE%" %%F in (*.wav) do call :process "%%F"
exit /b

:process
set "FILE=%~1"
echo DEBUG: Found file "%FILE%"
if exist "%~dpn1.txt" (
    for %%Z in ("%~dpn1.txt") do if %%~zZ gtr 0 (
        echo DEBUG: Skipping "%FILE%" - transcript exists with size %%~zZ bytes
    ) else (
        echo DEBUG: Transcribing "%FILE%"
        python "%~dp0whisper_transcribe.py" "%FILE%" > "%~dpn1.txt"
        for /f %%C in ('type "%~dpn1.txt" ^| find /v /c ""') do echo DEBUG: "%FILE%" -> %%C lines
    )
) else (
    echo DEBUG: Transcribing "%FILE%"
    python "%~dp0whisper_transcribe.py" "%FILE%" > "%~dpn1.txt"
    for /f %%C in ('type "%~dpn1.txt" ^| find /v /c ""') do echo DEBUG: "%FILE%" -> %%C lines
)
exit /b
