import os
import subprocess

def test_openai_payload() -> None:
    subprocess.run([
        "php",
        os.path.join("script", "tests", "test_openai_payload.php"),
    ], check=True)
