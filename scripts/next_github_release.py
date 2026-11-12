"""Choose a unique version when the distributable plugin source changes."""

import json
import os
import re
import subprocess
from pathlib import Path


def next_version(base: str, latest: dict | None, sha: str, current_source_tree: str | None = None, previous_source_tree: str | None = None) -> tuple[str | None, str | None]:
    current = tuple(map(int, base.split(".")))
    if latest:
        if latest.get("target_commitish") == sha:
            return None, None
        if not current_source_tree or not previous_source_tree:
            raise ValueError("Plugin source tree comparison is required for a new commit")
        if current_source_tree == previous_source_tree:
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
        capture_output=True, text=True, encoding="utf-8", check=False,
    )
    if response.returncode == 0:
        latest = json.loads(response.stdout)
    elif "404" in response.stderr:
        latest = None
    else:
        raise RuntimeError(response.stderr)
    current_source_tree = previous_source_tree = None
    if latest and latest.get("target_commitish") != os.environ["GITHUB_SHA"]:
        current_source_tree = subprocess.run(
            ["git", "rev-parse", "HEAD:src/so-short-code"], capture_output=True, text=True, encoding="utf-8", check=True,
        ).stdout.strip()
        previous_commit = subprocess.run(
            ["gh", "api", f"repos/{os.environ['GITHUB_REPOSITORY']}/git/commits/{latest['target_commitish']}"],
            capture_output=True, text=True, encoding="utf-8", check=True,
        )
        root_tree = json.loads(previous_commit.stdout)["tree"]["sha"]
        previous_tree = subprocess.run(
            ["gh", "api", f"repos/{os.environ['GITHUB_REPOSITORY']}/git/trees/{root_tree}?recursive=1"],
            capture_output=True, text=True, encoding="utf-8", check=True,
        )
        tree_response = json.loads(previous_tree.stdout)
        if tree_response.get("truncated"):
            raise ValueError("Previous release tree response is truncated")
        matches = [item["sha"] for item in tree_response["tree"]
                   if item["path"] == "src/so-short-code" and item["type"] == "tree"]
        if len(matches) != 1:
            raise ValueError("Previous release has no unique plugin source tree")
        previous_source_tree = matches[0]
    version, tag = next_version(match.group(1), latest, os.environ["GITHUB_SHA"], current_source_tree, previous_source_tree)
    with open(os.environ["GITHUB_OUTPUT"], "a", encoding="utf-8") as output:
        if version is None:
            output.write("skip=true\n")
        else:
            output.write(f"version={version}\ntag={tag}\n")


if __name__ == "__main__":
    main()
