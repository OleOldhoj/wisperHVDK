import os
import subprocess
import tempfile
import wave


def create_wave(path: str, duration: int, sample_rate: int = 8000) -> None:
    frames = duration * sample_rate
    with wave.open(path, "wb") as wf:
        wf.setnchannels(1)
        wf.setsampwidth(1)
        wf.setframerate(sample_rate)
        wf.writeframes(b"\x00" * frames)


def test_delete_short_files() -> None:
    with tempfile.TemporaryDirectory() as tmpdir:
        short = os.path.join(tmpdir, "short.wav")
        long = os.path.join(tmpdir, "long.wav")
        create_wave(short, 30)
        create_wave(long, 61)
        subprocess.run([
            "php",
            os.path.join("script", "delete_short_files.php"),
            tmpdir,
        ], check=True)
        assert not os.path.exists(short)
        assert os.path.exists(long)
