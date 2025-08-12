#!/usr/bin/env python3
"""Transcribe audio files using openai-whisper.

The script accepts either a single audio file or a directory. When a directory
is supplied all ``.wav`` files within it are transcribed and the results are
printed one per line in the format ``<path>\t<text>``.
"""
from pathlib import Path
import sys


def _transcribe_file(model: "whisper.Whisper", audio_path: Path) -> str:
    """Return transcription text for ``audio_path`` using ``model``."""
    result = model.transcribe(str(audio_path))
    return result.get("text", "").strip()


def main() -> int:
    if len(sys.argv) != 2:
        print('Usage: whisper_transcribe.py <audio_file_or_dir>', file=sys.stderr)
        return 1
    target = Path(sys.argv[1])
    try:
        import whisper  # type: ignore
    except ImportError:
        print('openai-whisper is not installed', file=sys.stderr)
        return 1
    if not target.exists():
        print(f'Path not found: {target}', file=sys.stderr)
        return 1
    model = whisper.load_model('base')
    if target.is_file():
        print(_transcribe_file(model, target))
    else:
        for audio_file in sorted(target.rglob('*.wav')):
            text = _transcribe_file(model, audio_file)
            print(f"{audio_file}\t{text}")
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
