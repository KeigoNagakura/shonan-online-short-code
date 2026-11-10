"""Publish a prepared plugin ZIP and per-host metadata over pinned SSH."""

from __future__ import annotations

import argparse
import hashlib
import json
import os
import re
import subprocess
import sys
import tempfile
from pathlib import Path


def fail(message: str) -> None:
    raise ValueError(message)


def target(raw: dict) -> dict:
    required = {"endpoint", "ssh_host", "ssh_user", "directory"}
    if not isinstance(raw, dict) or not required.issubset(raw):
        fail("Each target needs endpoint, ssh_host, ssh_user and directory")
    from build_update_package import endpoint

    host, _ = endpoint(raw["endpoint"])
    ssh_host = raw["ssh_host"]
    user = raw["ssh_user"]
    directory = raw["directory"]
    port = raw.get("ssh_port", 22)
    if not isinstance(ssh_host, str) or not re.fullmatch(r"[A-Za-z0-9.-]+", ssh_host):
        fail("Invalid SSH host")
    if not isinstance(user, str) or not re.fullmatch(r"[A-Za-z_][A-Za-z0-9_.-]*", user):
        fail("Invalid SSH user")
    if (not isinstance(directory, str) or not directory.startswith("/")
            or any(part in ("", ".", "..") for part in directory[1:].split("/"))
            or not re.fullmatch(r"/[A-Za-z0-9_./-]+", directory)):
        fail("Directory must be an absolute path without spaces or traversal")
    if not isinstance(port, int) or not 1 <= port <= 65535:
        fail("Invalid SSH port")
    return {"host": host, "ssh_host": ssh_host, "ssh_user": user, "directory": directory, "ssh_port": port}


def run(command: list[str]) -> str:
    completed = subprocess.run(command, text=True, capture_output=True, check=False)
    if completed.returncode:
        fail(f"Remote command failed ({completed.returncode}): {completed.stderr.strip()[-500:]}")
    return completed.stdout.strip()


def main(args: argparse.Namespace) -> None:
    raw = os.environ.get("SOSC_DEPLOY_TARGETS", "")
    if not raw:
        fail("SOSC_DEPLOY_TARGETS is not configured")
    values = json.loads(raw)
    if not isinstance(values, list) or not values:
        fail("SOSC_DEPLOY_TARGETS must be a non-empty JSON array")
    targets = [target(item) for item in values]
    if len({item["host"] for item in targets}) != len(targets):
        fail("Duplicate update endpoint")
    output = Path(args.package_dir).resolve()
    zips = list(output.glob("so-short-code-*.zip"))
    if len(zips) != 1:
        fail("Exactly one release ZIP is required")
    archive = zips[0]
    if not re.fullmatch(r"so-short-code-[0-9]{2}\.[0-9]{2}\.[0-9]{2}\.zip", archive.name):
        fail("Invalid release ZIP filename")
    digest = hashlib.sha256(archive.read_bytes()).hexdigest()
    for item in targets:
        metadata_path = output / "metadata" / item["host"] / "so-short-code.json"
        if not metadata_path.is_file():
            fail(f"Metadata is missing for {item['host']}")
        metadata = json.loads(metadata_path.read_text(encoding="utf-8"))
        from urllib.parse import urlparse

        package_url = urlparse(metadata.get("download_url", ""))
        if (metadata.get("sha256") != digest or package_url.scheme != "https"
                or package_url.hostname != item["host"] or package_url.path != "/out_source/" + archive.name):
            fail(f"Metadata does not match ZIP for {item['host']}")
    if args.check_only:
        print(f"Prepared {archive.name} for {len(targets)} update host(s)")
        return
    key_value = os.environ.get("SOSC_DEPLOY_SSH_KEY", "")
    known_hosts_value = os.environ.get("SOSC_DEPLOY_KNOWN_HOSTS", "")
    if not key_value or not known_hosts_value:
        fail("SSH key and pinned known_hosts must be configured")
    with tempfile.TemporaryDirectory(prefix="sosc-update-") as temporary:
        key = Path(temporary) / "key"
        known_hosts = Path(temporary) / "known_hosts"
        key.write_text(key_value + "\n", encoding="utf-8")
        known_hosts.write_text(known_hosts_value + "\n", encoding="utf-8")
        os.chmod(key, 0o600)
        os.chmod(known_hosts, 0o600)
        for item in targets:
            base = ["-i", str(key), "-o", "BatchMode=yes", "-o", "StrictHostKeyChecking=yes",
                    "-o", f"UserKnownHostsFile={known_hosts}"]
            ssh = ["ssh", "-p", str(item["ssh_port"]), *base, f"{item['ssh_user']}@{item['ssh_host']}"]
            scp = ["scp", "-P", str(item["ssh_port"]), *base]
            directory = item["directory"]
            remote = f"{item['ssh_user']}@{item['ssh_host']}:"
            run([*ssh, f"test -d {directory} && test -w {directory}"])
            remote_zip = f"{directory}/{archive.name}"
            existing = run([*ssh, f"if test -f {remote_zip}; then sha256sum {remote_zip} | cut -d ' ' -f 1; fi"])
            if existing and existing != digest:
                fail(f"A different ZIP already exists at {item['host']}")
            if not existing:
                temporary_zip = f"{remote_zip}.upload-{os.getpid()}"
                run([*scp, str(archive), remote + temporary_zip])
                uploaded = run([*ssh, f"sha256sum {temporary_zip} | cut -d ' ' -f 1"])
                if uploaded != digest:
                    fail(f"Uploaded ZIP hash differs at {item['host']}")
                run([*ssh, f"mv {temporary_zip} {remote_zip}"])
        # Only switch metadata after every ZIP is available and verified.
        for item in targets:
            directory = item["directory"]
            remote = f"{item['ssh_user']}@{item['ssh_host']}:"
            base = ["-i", str(key), "-o", "BatchMode=yes", "-o", "StrictHostKeyChecking=yes",
                    "-o", f"UserKnownHostsFile={known_hosts}"]
            ssh = ["ssh", "-p", str(item["ssh_port"]), *base, f"{item['ssh_user']}@{item['ssh_host']}"]
            scp = ["scp", "-P", str(item["ssh_port"]), *base]
            metadata_path = output / "metadata" / item["host"] / "so-short-code.json"
            temporary_json = f"{directory}/so-short-code.json.upload-{os.getpid()}"
            run([*scp, str(metadata_path), remote + temporary_json])
            run([*ssh, f"if test -f {directory}/so-short-code.json; then cp -p {directory}/so-short-code.json {directory}/so-short-code.json.previous; fi && mv {temporary_json} {directory}/so-short-code.json"])
            print(f"Published {archive.name} and metadata to {item['host']}")


if __name__ == "__main__":
    parser = argparse.ArgumentParser()
    parser.add_argument("--package-dir", required=True)
    parser.add_argument("--check-only", action="store_true")
    try:
        main(parser.parse_args())
    except (OSError, ValueError, json.JSONDecodeError) as error:
        print(f"Release publish failed: {error}", file=sys.stderr)
        sys.exit(1)
