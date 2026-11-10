"""Build a deterministic WordPress plugin ZIP and per-host update metadata."""

from __future__ import annotations

import argparse
import hashlib
import json
import re
import sys
import zipfile
from datetime import date
from pathlib import Path
from urllib.parse import urlparse


ROOT = Path(__file__).resolve().parents[1]
SOURCE = ROOT / "src" / "so-short-code"
MAIN = SOURCE / "so_short_code.php"
SETTINGS = SOURCE / "settings.php"
TEMPLATE = SOURCE / "update" / "so-short-code.json"
SKIP_DIRS = {".git", "__pycache__", "node_modules", "tests", ".local"}
SECRET_SUFFIXES = {".key", ".pem", ".p12", ".pfx", ".env"}


def fail(message: str) -> None:
    raise ValueError(message)


def version_from_file(file: Path, pattern: str) -> str:
    match = re.search(pattern, file.read_text(encoding="utf-8"), re.MULTILINE)
    if not match:
        fail(f"Version not found: {file.relative_to(ROOT)}")
    return match.group(1)


def package_name(version: str) -> str:
    parts = version.split(".")
    if len(parts) != 3 or any(not part.isdigit() or int(part) > 99 for part in parts):
        fail("Release version must contain three numeric parts from 0 to 99")
    return "so-short-code-" + ".".join(f"{int(part):02d}" for part in parts) + ".zip"


def endpoint(value: str) -> tuple[str, str]:
    url = urlparse(value)
    if (url.scheme != "https" or not url.hostname or url.username or url.password
            or url.query or url.fragment or url.port or url.path.rstrip("/") != "/out_source"):
        fail(f"Invalid update endpoint: {value}")
    return url.hostname.lower(), f"https://{url.hostname.lower()}/out_source"


def source_files() -> list[Path]:
    result = []
    for path in SOURCE.rglob("*"):
        relative = path.relative_to(SOURCE)
        if any(part in SKIP_DIRS for part in relative.parts):
            continue
        if path.is_symlink():
            fail(f"Symlink is not allowed in release: {relative}")
        if not path.is_file() or relative.as_posix() == "update/so-short-code.json":
            continue
        if any(part.lower().endswith("_old") or re.search(r"(?i)_old_?\d{8}$|_\d{8}_old$", part) for part in relative.parts):
            continue
        if path.suffix.lower() in SECRET_SUFFIXES or path.name.lower().startswith(".env"):
            fail(f"Potential secret file in release source: {relative}")
        result.append(path)
    return sorted(result, key=lambda path: path.relative_to(SOURCE).as_posix())


def build(args: argparse.Namespace) -> None:
    version = version_from_file(MAIN, r"^\s*\*\s*Version:\s*([0-9]+\.[0-9]+\.[0-9]+)\s*$")
    configured_version = version_from_file(SETTINGS, r"define\(\s*'SOSC_VERSION'\s*,\s*'([0-9]+\.[0-9]+\.[0-9]+)'\s*\)")
    template = json.loads(TEMPLATE.read_text(encoding="utf-8"))
    if version != configured_version or version != template.get("version"):
        fail(f"Plugin, settings and JSON versions differ: {version}, {configured_version}, {template.get('version')}")
    if args.tag != f"v{version}":
        fail(f"Tag {args.tag} does not match plugin version {version}")
    release_date = date.fromisoformat(args.release_date)
    if not (SOURCE / "vendor" / "autoload.php").is_file():
        fail("Composer vendor/autoload.php is missing; run composer install --no-dev first")
    files = source_files()
    if not files or MAIN not in files:
        fail("Plugin source is incomplete")
    output = Path(args.output).resolve()
    if output == SOURCE or SOURCE in output.parents:
        fail("Output must be outside plugin source")
    output.mkdir(parents=True, exist_ok=True)
    zip_path = output / package_name(version)
    with zipfile.ZipFile(zip_path, "w", compression=zipfile.ZIP_DEFLATED, compresslevel=9) as archive:
        for file in files:
            relative = file.relative_to(SOURCE).as_posix()
            info = zipfile.ZipInfo(f"so-short-code/{relative}", (1980, 1, 1, 0, 0, 0))
            info.compress_type = zipfile.ZIP_DEFLATED
            info.external_attr = 0o644 << 16
            archive.writestr(info, file.read_bytes(), compress_type=zipfile.ZIP_DEFLATED, compresslevel=9)
    digest = hashlib.sha256(zip_path.read_bytes()).hexdigest()
    hosts = set()
    for raw_endpoint in args.endpoint:
        host, base_url = endpoint(raw_endpoint)
        if host in hosts:
            fail(f"Duplicate update host: {host}")
        hosts.add(host)
        metadata = dict(template)
        metadata.update(version=version, download_url=f"{base_url}/{zip_path.name}", sha256=digest,
                        last_updated=release_date.isoformat())
        destination = output / "metadata" / host
        destination.mkdir(parents=True, exist_ok=True)
        (destination / "so-short-code.json").write_text(
            json.dumps(metadata, ensure_ascii=False, indent=2) + "\n", encoding="utf-8"
        )
    configured_api = re.search(r"define\(\s*'SOSC_API_URL'\s*,\s*'https://([^/']+)/'\s*\)", SETTINGS.read_text(encoding="utf-8"))
    if not configured_api or configured_api.group(1).lower() not in hosts:
        fail("No release endpoint matches SOSC_API_URL in settings.php")
    with zipfile.ZipFile(zip_path) as archive:
        names = archive.namelist()
        if not names or any(not name.startswith("so-short-code/") for name in names):
            fail("Invalid ZIP root")
        if "so-short-code/so_short_code.php" not in names or "so-short-code/vendor/autoload.php" not in names:
            fail("Required plugin files are missing from ZIP")
    print(f"Built {zip_path.name}: {len(files)} files, SHA-256 {digest}, hosts {', '.join(sorted(hosts))}")


if __name__ == "__main__":
    parser = argparse.ArgumentParser()
    parser.add_argument("--tag", required=True)
    parser.add_argument("--release-date", required=True)
    parser.add_argument("--endpoint", action="append", required=True, help="HTTPS URL ending in /out_source")
    parser.add_argument("--output", required=True)
    try:
        build(parser.parse_args())
    except (OSError, ValueError, json.JSONDecodeError) as error:
        print(f"Release build failed: {error}", file=sys.stderr)
        sys.exit(1)
