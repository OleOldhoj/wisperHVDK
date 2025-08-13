import os
import sqlite3
import subprocess
import tempfile
from pathlib import Path


def test_fill_wispertalk() -> None:
    with tempfile.TemporaryDirectory() as tmpdir:
        db_path = Path(tmpdir) / "db.sqlite"
        conn = sqlite3.connect(db_path)
        conn.execute(
            """
            CREATE TABLE sales_call_ratings (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                filepath TEXT,
                WisperTALK TEXT
            )
            """
        )
        conn.execute(
            "INSERT INTO sales_call_ratings (filepath) VALUES ('dummy.wav')"
        )
        conn.commit()
        conn.close()

        env = os.environ.copy()
        env.update({
            "DB_CONNECTION": "sqlite",
            "DB_DATABASE": str(db_path),
        })

        subprocess.run(
            ["php", os.path.join("script", "tests", "test_fill_wispertalk.php")],
            check=True,
            env=env,
        )

        conn = sqlite3.connect(db_path)
        text = conn.execute(
            "SELECT WisperTALK FROM sales_call_ratings"
        ).fetchone()[0]
        conn.close()
        assert text == "stub text"
