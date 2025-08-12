#!/usr/bin/env python3
"""Transcribe an audio file using openai-whisper."""
import os
import sys


def main() -> int:
    if len(sys.argv) != 2:
        print('Usage: whisper_transcribe.py <audio_file>', file=sys.stderr)
        return 1
    audio_path = sys.argv[1]
    try:
        import whisper  # type: ignore
    except ImportError:
        print('openai-whisper is not installed', file=sys.stderr)
        return 1
    if not os.path.exists(audio_path):
        print(f'File not found: {audio_path}', file=sys.stderr)
        return 1
    model = whisper.load_model('base')
    result = model.transcribe(audio_path)
    print(result.get('text', '').strip())
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
