import os
import subprocess

def test_openai_evaluate() -> None:
    subprocess.run([
        "php",
        os.path.join("script", "tests", "test_openai_evaluate.php"),
    ], check=True)
