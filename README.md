# wisperHVDK

Prototype demonstrating how a Laravel-compatible PHP script can invoke the
`openai-whisper` Python package to transcribe audio files.

## Layout

- `public_html/index.php` – PHP entry point that calls the Python helper.
- `script/whisper_transcribe.py` – Python script performing the transcription.
- `config_files/config.php` – configuration for paths.
- `documents/`, `business_information/`, `etc/` – placeholders for project
  organisation.

## Requirements

- Python 3 with `openai-whisper` installed (`pip install -U openai-whisper`).
- `ffmpeg` available on the system path.
- PHP 8 running under XAMPP or similar.

## Usage

Place this repository at `C:\wisper` and run:

```bash
php public_html/index.php file="C:\wisper\07\09\rg-900-+4550499106-20250709-131344-1752059605.163788.wav"
```

The script prints the transcription text to standard output.

## Testing

```bash
pytest script/test_whisper_transcribe.py
php script/test_index.php
```
