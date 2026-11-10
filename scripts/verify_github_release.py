"""Check the version and required files in a release ZIP."""

import argparse
import glob
import re
import zipfile


def verify(version: str, package_glob: str) -> None:
    paths = glob.glob(package_glob)
    if len(paths) != 1:
        raise ValueError("Expected exactly one release ZIP")
    with zipfile.ZipFile(paths[0]) as archive:
        for name, pattern in (
            ("so-short-code/so_short_code.php", rb"^\s*\*\s*Version:\s*(\d+\.\d+\.\d+)\s*$"),
            ("so-short-code/settings.php", rb"define\(\s*'SOSC_VERSION'\s*,\s*'(\d+\.\d+\.\d+)'\s*\)"),
        ):
            found = re.search(pattern, archive.read(name), re.MULTILINE)
            if not found or found.group(1).decode() != version:
                raise ValueError(f"Version mismatch in {name}")
        if "so-short-code/vendor/autoload.php" not in archive.namelist():
            raise ValueError("Composer dependencies are missing")


if __name__ == "__main__":
    parser = argparse.ArgumentParser()
    parser.add_argument("--version", required=True)
    parser.add_argument("--package", required=True)
    args = parser.parse_args()
    verify(args.version, args.package)
