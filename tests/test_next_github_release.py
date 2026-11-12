"""Regression checks for duplicate release version selection."""

import sys
import unittest
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[1] / "scripts"))
from next_github_release import next_version


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


if __name__ == "__main__":
    unittest.main()
