import os
import sqlite3
import subprocess
from pathlib import Path
import wave


def create_wave(path: Path, duration: int, sample_rate: int = 1) -> None:
    frames = duration * sample_rate
    with wave.open(str(path), "wb") as wf:
        wf.setnchannels(1)
        wf.setsampwidth(1)
        wf.setframerate(sample_rate)
        wf.writeframes(b"\x00" * frames)


def test_insert_sound_files(tmp_path: Path) -> None:
    sub = tmp_path / "sub"
    sub.mkdir()

    short = tmp_path / "short.wav"
    long = sub / "long.wav"
    other = tmp_path / "ignore.txt"

    create_wave(short, 100)
    create_wave(long, 130)
    other.write_text("ignore")

    db_file = tmp_path / "db.sqlite"
    conn = sqlite3.connect(db_file)
    conn.execute(
        """
        CREATE TABLE sales_call_ratings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            filepath TEXT,
            WisperTALK TEXT,
            call_id TEXT NOT NULL,
            greeting_quality INTEGER NOT NULL,
            needs_assessment INTEGER NOT NULL,
            product_knowledge INTEGER NOT NULL,
            persuasion INTEGER NOT NULL,
            closing INTEGER NOT NULL,
            manager_comment TEXT,
            warning_comment TEXT,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP
        )
        """
    )
    conn.commit()
    conn.close()

    env = os.environ.copy()
    env.update({
        "DB_CONNECTION": "sqlite",
        "DB_DATABASE": str(db_file),
    })

    subprocess.run([
        "php",
        os.path.join("script", "insert_sound_files.php"),
        str(tmp_path),
    ], check=True, env=env)

    conn = sqlite3.connect(db_file)
    rows = conn.execute("SELECT filepath FROM sales_call_ratings ORDER BY filepath").fetchall()
    conn.close()

    inserted = [row[0] for row in rows]
    assert inserted == [str(short)]
