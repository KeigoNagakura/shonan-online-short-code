"""Regression checks for duplicate release version selection."""

import sys
import unittest
import subprocess
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[1] / "scripts"))
from next_github_release import distributable_tree, next_version
from build_update_package import SOURCE, source_files


class NextVersionTest(unittest.TestCase):
    def test_same_commit_skips(self):
        self.assertEqual((None, None), next_version("3.8.5", {"target_commitish": "a"}, "a"))

    def test_same_tree_skips_even_with_new_commit(self):
        self.assertEqual((None, None), next_version("3.8.5", {"target_commitish": "a"}, "b", "tree1", "tree1"))

    def test_changed_tree_increments(self):
        latest = {"target_commitish": "a", "tag_name": "v3.8.8"}
        self.assertEqual(("3.8.9", "v3.8.9"), next_version("3.8.5", latest, "b", "tree2", "tree1"))

    def test_missing_tree_comparison_fails_closed(self):
        latest = {"target_commitish": "a", "tag_name": "v3.8.8"}
        with self.assertRaisesRegex(ValueError, "source tree comparison"):
            next_version("3.8.5", latest, "b")

    def test_first_release_uses_source_version(self):
        self.assertEqual(("3.8.6", "v3.8.6"), next_version("3.8.5", None, "b"))

    def test_excluded_source_change_does_not_change_release(self):
        base = [{"path": "src/so-short-code/so_short_code.php", "type": "blob", "sha": "code"}]
        changed = base + [{"path": "src/so-short-code/update/so-short-code.json", "type": "blob", "sha": "metadata"}]
        self.assertEqual(distributable_tree(base), distributable_tree(changed))
        self.assertEqual((None, None), next_version("3.8.5", {"target_commitish": "a"}, "b", distributable_tree(base), distributable_tree(changed)))

    def test_distributable_change_creates_release(self):
        old = [{"path": "src/so-short-code/so_short_code.php", "type": "blob", "sha": "old"}]
        new = [{"path": "src/so-short-code/so_short_code.php", "type": "blob", "sha": "new"}]
        self.assertEqual(("3.8.9", "v3.8.9"), next_version("3.8.5", {"target_commitish": "a", "tag_name": "v3.8.8"}, "b", distributable_tree(new), distributable_tree(old)))

    def test_tracked_release_files_match_zip_selection(self):
        raw = subprocess.check_output(["git", "ls-tree", "-r", "-z", "HEAD", "--", "src/so-short-code"], cwd=SOURCE.parents[1])
        entries = []
        for record in raw.split(b"\0"):
            if not record:
                continue
            header, path = record.split(b"\t", 1)
            mode, kind, sha = header.decode("ascii").split()
            entries.append({"type": kind, "sha": sha, "path": path.decode("utf-8")})
        actual = set(distributable_tree(entries))
        expected = {"src/so-short-code/" + path.relative_to(SOURCE).as_posix()
                    for path in source_files() if "vendor" not in path.relative_to(SOURCE).parts}
        self.assertEqual(expected, actual)


if __name__ == "__main__":
    unittest.main()
