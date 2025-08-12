"""Tests for whisper_transcribe.py."""
import os
import subprocess
import sys
from pathlib import Path


def test_invalid_path(tmp_path):
    (tmp_path / 'whisper.py').write_text('def load_model(name):\n    return None\n')
    (tmp_path / 'torch.py').write_text(
        'class cuda:\n'
        '    @staticmethod\n'
        '    def is_available():\n'
        '        return False\n'
    )
    env = {**os.environ, 'PYTHONPATH': str(tmp_path)}
    script = Path(__file__).with_name('whisper_transcribe.py')
    result = subprocess.run([sys.executable, str(script), 'nonexistent'], capture_output=True, text=True, env=env)
    assert result.returncode != 0
    assert 'Path not found' in result.stderr


def test_transcribe_directory(tmp_path):
    audio = tmp_path / 'sample.wav'
    audio.write_bytes(b'')
    whisper_stub = (
        'from pathlib import Path\n'
        'fp16_file = Path(__file__).with_name("fp16.txt")\n'
        'class Dummy:\n'
        '    def transcribe(self, path, fp16=False):\n'
        '        fp16_file.write_text(str(fp16))\n'
        '        return {"segments": [{"start": 0.0, "text": Path(path).name}]}\n'
        'def load_model(name):\n'
        '    return Dummy()\n'
    )
    (tmp_path / 'whisper.py').write_text(whisper_stub)
    (tmp_path / 'torch.py').write_text(
        'class cuda:\n'
        '    @staticmethod\n'
        '    def is_available():\n'
        '        return False\n'
    )
    env = {**os.environ, 'PYTHONPATH': str(tmp_path)}
    script = Path(__file__).with_name('whisper_transcribe.py')
    result = subprocess.run([sys.executable, str(script), str(tmp_path)], capture_output=True, env=env)
    assert result.returncode == 0
    expected = f"{audio}\t[00:00:00] sample.wav\r\n".encode()
    assert result.stdout == expected
    assert (tmp_path / 'fp16.txt').read_text() == 'False'
