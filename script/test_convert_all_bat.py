"""Tests for convert_all.bat."""
from pathlib import Path


def test_batch_script_contains_expected_commands():
    bat = Path(__file__).with_name('convert_all.bat').read_text(encoding='utf-8')
    assert 'whisper_transcribe.py' in bat
    assert 'for /r "%BASE%" %%F in (*.wav)' in bat
