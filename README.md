# wisperHVDK

Prototype demonstrating how a Laravel-compatible PHP script can invoke the
`openai-whisper` Python package to transcribe audio files.

## Layout

- `public_html/index.php` – PHP entry point that calls the Python helper.
- `public_html/openai_transcribe.php` – PHP script calling OpenAI's Whisper API and
  returning a timestamped transcript with CRLF line endings.

- `script/convert_all.bat` – Windows helper to batch transcribe `.wav` files; skips files with an existing non-empty `.txt` transcript and produces an empty `.txt` for silent audio.
- `script/whisper_transcribe.py` – Python script performing the transcription. It
  selects FP16 on GPUs and uses FP32 on CPUs to avoid precision warnings. Each
  transcript line includes a `[HH:MM:SS]` timestamp, emits UTF-8 text and uses
  Windows style CRLF line endings.
- `script/delete_short_files.php` – removes `.wav` files shorter than one minute.
- `script/whisper_cost.php` – estimates the cost of transcribing `.wav` files
  using Whisper's pricing (`$0.006` per minute).
- `script/rename_recording.php` – renames call recordings by mapping extension numbers to contact names; supports filenames like
 `out-123-0-8504-20250704-...` and `exten-8504-unknown-20250701-...`.
- `script/fill_wispertalk.php` – populates the `WisperTALK` column in `sales_call_ratings` using MySQL (default DB `salescallsanalyse`) and OpenAI's Whisper API for entries missing transcripts; emits verbose debug to STDERR.
- `script/fill_call_ratings.php` – evaluates `WisperTALK` transcripts with
  OpenAI's GPT-5 model via the Responses API and updates scoring fields such as
  `greeting_quality` and `WhatWorked`.
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

To populate missing `WisperTALK` values in the database:

```bash
OPENAI_API_KEY=your_key php script/fill_wispertalk.php
```

To score calls and fill in rating fields:

```bash
OPENAI_API_KEY=your_key php script/fill_call_ratings.php
```

### Convert and save a transcript

Use `script/convertThis.php` to create a text transcript next to an audio file.
The `.wav` extension is replaced with `.openai.txt`:

```bash
php script/convertThis.php "file:///C:/wisper/sound/07/01/exten-FredericNygaard-unknown-20250701-080009-1751349609.81224.wav"
```

The script prints debug information and returns the path of the generated
`*.openai.txt` file. A cron entry can run the conversion automatically:

```cron
* * * * * cd /path/to/wisperHVDK && php script/convertThis.php "file:///C:/wisper/sound/07/01/example.wav"
```

To delete `.wav` files shorter than one minute within a directory (default is `sound`):

```bash
php script/delete_short_files.php /path/to/dir
```

## Testing

```bash
pytest
php script/tests/test_fill_wispertalk.php
php script/tests/test_fill_call_ratings.php
```
