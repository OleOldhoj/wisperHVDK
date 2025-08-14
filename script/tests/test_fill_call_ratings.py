import os
import sqlite3
import subprocess
from pathlib import Path

def test_fill_call_ratings(tmp_path: Path) -> None:
    db_file = tmp_path / "db.sqlite"
    conn = sqlite3.connect(db_file)
    conn.execute(
        """
        CREATE TABLE sales_call_ratings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            WisperTALK TEXT,
            greeting_quality INTEGER NOT NULL DEFAULT 0,
            needs_assessment INTEGER NOT NULL DEFAULT 0,
            product_knowledge INTEGER NOT NULL DEFAULT 0,
            persuasion INTEGER NOT NULL DEFAULT 0,
            closing INTEGER NOT NULL DEFAULT 0,
            WhatWorked TEXT NOT NULL DEFAULT '',
            WhatDidNotWork TEXT NOT NULL DEFAULT '',
            manager_comment TEXT,
            warning_comment TEXT
        )
        """
    )
    conn.execute("INSERT INTO sales_call_ratings (WisperTALK) VALUES ('example talk')")
    conn.commit()
    conn.close()

    env = os.environ.copy()
    env.update({
        "DB_CONNECTION": "sqlite",
        "DB_DATABASE": str(db_file),
    })

    subprocess.run(
        ["php", os.path.join("script", "tests", "test_fill_call_ratings.php")],
        check=True,
        env=env,
    )

    conn = sqlite3.connect(db_file)
    row = conn.execute(
        "SELECT greeting_quality, needs_assessment, product_knowledge, persuasion, closing, WhatWorked, WhatDidNotWork, manager_comment, warning_comment FROM sales_call_ratings"
    ).fetchone()
    conn.close()

    assert row == (3, 4, 5, 2, 1, "good knowledge", "weak closing", "improve closing", "check closing")
