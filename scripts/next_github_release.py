"""Choose a unique version for the current main commit."""

import json
import os
import re
import subprocess
from pathlib import Path


def next_version(base: str, latest: dict | None, sha: str) -> tuple[str | None, str | None]:
    current = tuple(map(int, base.split(".")))
    if latest:
        if latest.get("target_commitish") == sha:
            return None, None
        match = re.fullmatch(r"v(\d+)\.(\d+)\.(\d+)", latest.get("tag_name", ""))
        if not match:
            raise ValueError("Latest release has an unexpected tag")
        previous = tuple(map(int, match.groups()))
    else:
        previous = (0, 0, 0)
    major, minor, patch = max(current, previous)
    version = f"{major}.{minor}.{patch + 1}"
    return version, f"v{version}"


def main() -> None:
    source = Path("src/so-short-code/so_short_code.php").read_text(encoding="utf-8")
    match = re.search(r"^\s*\*\s*Version:\s*(\d+\.\d+\.\d+)\s*$", source, re.MULTILINE)
    if not match:
        raise ValueError("Plugin version header is missing")
    response = subprocess.run(
        ["gh", "api", f"repos/{os.environ['GITHUB_REPOSITORY']}/releases/latest"],
        capture_output=True, text=True, check=False,
    )
    if response.returncode == 0:
        latest = json.loads(response.stdout)
    elif "404" in response.stderr:
        latest = None
    else:
        raise RuntimeError(response.stderr)
    version, tag = next_version(match.group(1), latest, os.environ["GITHUB_SHA"])
    with open(os.environ["GITHUB_OUTPUT"], "a", encoding="utf-8") as output:
        if version is None:
            output.write("skip=true\n")
        else:
            output.write(f"version={version}\ntag={tag}\n")


if __name__ == "__main__":
    main()
