import os
import sqlite3
import subprocess
import tempfile
from pathlib import Path


def create_file(path: Path) -> None:
    path.write_bytes(b"test")


def test_insert_sound_files(tmp_path: Path) -> None:
    # Create nested files
    sub = tmp_path / "sub"
    sub.mkdir()
    file1 = tmp_path / "a.txt"
    file2 = sub / "b.txt"
    create_file(file1)
    create_file(file2)

    # Set up SQLite database
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
    assert str(file1) in inserted
    assert str(file2) in inserted
