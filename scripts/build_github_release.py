"""Build a GitHub release ZIP with a monotonically increasing plugin version."""

import argparse
import re
import zipfile
from pathlib import Path

from build_update_package import MAIN, SETTINGS, SOURCE, package_name, source_files


def replace_once(data: bytes, pattern: bytes, replacement: bytes) -> bytes:
    result, count = re.subn(pattern, replacement, data, count=1, flags=re.MULTILINE)
    if count != 1:
        raise ValueError(f"Expected exactly one version field matching {pattern!r}")
    return result


def build(version: str, output: Path) -> Path:
    if not re.fullmatch(r"[0-9]+\.[0-9]+\.[0-9]+", version):
        raise ValueError("Invalid release version")
    if not (SOURCE / "vendor" / "autoload.php").is_file():
        raise ValueError("Composer dependencies are missing")
    if SOURCE == output.resolve() or SOURCE in output.resolve().parents:
        raise ValueError("Output must be outside plugin source")
    output.mkdir(parents=True, exist_ok=True)
    path = output / package_name(version)
    with zipfile.ZipFile(path, "w", compression=zipfile.ZIP_DEFLATED, compresslevel=9) as archive:
        for file in source_files():
            data = file.read_bytes()
            if file == MAIN:
                data = replace_once(data, rb"^(\s*\*\s*Version:\s*)[0-9]+\.[0-9]+\.[0-9]+", rb"\g<1>" + version.encode())
            elif file == SETTINGS:
                data = replace_once(data, rb"(define\(\s*'SOSC_VERSION'\s*,\s*')[0-9]+\.[0-9]+\.[0-9]+", rb"\g<1>" + version.encode())
            info = zipfile.ZipInfo("so-short-code/" + file.relative_to(SOURCE).as_posix(), (1980, 1, 1, 0, 0, 0))
            info.compress_type = zipfile.ZIP_DEFLATED
            info.external_attr = 0o644 << 16
            archive.writestr(info, data, compress_type=zipfile.ZIP_DEFLATED, compresslevel=9)
    return path


if __name__ == "__main__":
    parser = argparse.ArgumentParser()
    parser.add_argument("--version", required=True)
    parser.add_argument("--output", type=Path, required=True)
    args = parser.parse_args()
    print(build(args.version, args.output))
