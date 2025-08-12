"""Tests for whisper_transcribe.py."""
import os
import subprocess
import sys
from pathlib import Path


def test_invalid_path(tmp_path):
    (tmp_path / 'whisper.py').write_text('def load_model(name):\n    return None\n')
    env = {**os.environ, 'PYTHONPATH': str(tmp_path)}
    script = Path(__file__).with_name('whisper_transcribe.py')
    result = subprocess.run([sys.executable, str(script), 'nonexistent'], capture_output=True, text=True, env=env)
    assert result.returncode != 0
    assert 'Path not found' in result.stderr


def test_transcribe_directory(tmp_path):
    audio = tmp_path / 'sample.wav'
    audio.write_bytes(b'')
    stub = (
        'from pathlib import Path\n'
        'class Dummy:\n'
        '    def transcribe(self, path):\n'
        '        return {"text": Path(path).name}\n'
        'def load_model(name):\n'
        '    return Dummy()\n'
    )
    (tmp_path / 'whisper.py').write_text(stub)
    env = {**os.environ, 'PYTHONPATH': str(tmp_path)}
    script = Path(__file__).with_name('whisper_transcribe.py')
    result = subprocess.run([sys.executable, str(script), str(tmp_path)], capture_output=True, text=True, env=env)
    assert result.returncode == 0
    assert 'sample.wav\tsample.wav' in result.stdout
