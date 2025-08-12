"""Tests for whisper_transcribe.py."""
import subprocess
import sys
from pathlib import Path


def test_missing_dependency():
    script = Path(__file__).with_name('whisper_transcribe.py')
    result = subprocess.run([sys.executable, str(script), 'nonexistent.wav'], capture_output=True, text=True)
    assert result.returncode != 0
    assert 'openai-whisper' in result.stderr
