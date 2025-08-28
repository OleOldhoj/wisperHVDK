import os
import subprocess

def test_openai_evaluate_env() -> None:
    subprocess.run([
        "php",
        os.path.join("script", "tests", "test_openai_evaluate_env.php"),
    ], check=True)
