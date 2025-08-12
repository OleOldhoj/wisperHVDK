# wisperHVDK

Prototype demonstrating how a Laravel-compatible PHP script can invoke the
`openai-whisper` Python package to transcribe audio files.

## Layout

- `public_html/index.php` – PHP entry point that calls the Python helper.
- `script/whisper_transcribe.py` – Python script performing the transcription. It
  selects FP16 on GPUs and uses FP32 on CPUs to avoid precision warnings.
- `script/convert_all.bat` – Windows helper to batch transcribe `.wav` files.
- `config_files/config.php` – configuration for paths including the sound directory.
- `documents/`, `business_information/`, `etc/` – placeholders for project
  organisation.

## Requirements

- Python 3 with `openai-whisper` installed (`pip install -U openai-whisper`).
- `ffmpeg` available on the system path.
- PHP 8 running under XAMPP or similar.

## Usage

Place this repository at `C:\\wisper`. To transcribe all `.wav` files under
`C:\\wisper\\sound` run:

```bash
php public_html/index.php
```

To transcribe a single file:

```bash
php public_html/index.php path="C:\\wisper\\07\\09\\rg-900-+4550499106-20250709-131344-1752059605.163788.wav"
```

The script prints the transcription text to standard output.

On Windows you can convert every `.wav` under `C:\\wisper\\sound` to
individual `.txt` files by running:

```bat
script\convert_all.bat
```

## Testing

```bash
pytest
php script/test_index.php
```
