# wisperHVDK

Prototype demonstrating how a Laravel-compatible PHP script can invoke the
`openai-whisper` Python package to transcribe audio files.

## Layout

- `public_html/index.php` – PHP entry point that calls the Python helper.
- `public_html/openai_transcribe.php` – PHP script calling OpenAI's Whisper API.

- `script/convert_all.bat` – Windows helper to batch transcribe `.wav` files; skips files with an existing non-empty `.txt` transcript and produces an empty `.txt` for silent audio.
- `script/whisper_transcribe.py` – Python script performing the transcription. It
  selects FP16 on GPUs and uses FP32 on CPUs to avoid precision warnings. Each
  transcript line includes a `[HH:MM:SS]` timestamp, emits UTF-8 text and uses
  Windows style CRLF line endings.
- `config_files/config.php` – configuration for paths including the sound directory.
- `documents/`, `business_information/`, `etc/` – placeholders for project
  organisation.

## Requirements

- Python 3 with `openai-whisper` installed (`pip install -U openai-whisper`).
- `ffmpeg` available on the system path.
- PHP 8 running under XAMPP or similar.
- OpenAI API key in `OPENAI_API_KEY` for API-based transcription.

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

The script prints the transcription text to standard output with timestamps and
CRLF line endings, making the output Windows friendly.

On Windows you can convert every `.wav` under `C:\\wisper\\sound` to
individual `.txt` files by running:

```bat
script\convert_all.bat
```

Existing non-empty `.txt` files are left untouched.

To transcribe a file using the OpenAI API:

```bash
OPENAI_API_KEY=your_key php public_html/openai_transcribe.php path/to/audio.wav
```

## Testing

```bash
pytest
php script/test_index.php
php script/test_openai_transcribe.php
```
