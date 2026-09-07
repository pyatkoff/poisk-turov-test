#!/usr/bin/env python3
from __future__ import annotations

import json
import os
import stat
import subprocess
import sys
import tempfile
import unittest
from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]
SCRIPT = ROOT / "scripts/deploy/search3_production_snapshot.py"


class ProductionSnapshotTest(unittest.TestCase):
    def run_tool(self, *args: str, ok: bool = True) -> subprocess.CompletedProcess[str]:
        result = subprocess.run([sys.executable, str(SCRIPT), *args], text=True, capture_output=True)
        if ok and result.returncode != 0:
            self.fail(f"command failed: {result.stderr}")
        if not ok and result.returncode == 0:
            self.fail("unsafe command unexpectedly succeeded")
        return result

    def test_snapshot_verify_and_isolated_restore_preserve_bytes_and_modes(self) -> None:
        with tempfile.TemporaryDirectory() as tmp:
            base = Path(tmp)
            root = base / "served-root"
            backup = base / "private-backups"
            inventory = base / "inventory.txt"
            files = {
                "index.php": (b"<?php echo 'old release';\n", 0o644),
                "poisk-turov/index.php": (b"<?php echo 'old search';\n", 0o644),
                "config.php": (b"<?php $password='DB_SECRET_VALUE';\n", 0o600),
                ".anytoour-bridge-secret": (b"BRIDGE_SECRET_VALUE\n", 0o600),
                "images/logo.svg": (b"<svg/>\n", 0o644),
            }
            for relative, (content, mode) in files.items():
                path = root / relative
                path.parent.mkdir(parents=True, exist_ok=True)
                path.write_bytes(content)
                os.chmod(path, mode)
            inventory.write_text("\n".join(files) + "\n", encoding="utf-8")

            created = self.run_tool(
                "snapshot", "--root", str(root), "--backup-dir", str(backup),
                "--inventory", str(inventory), "--snapshot-id", "before-search3-test",
                "--source-sha", "fa58a0cb",
            )
            public_output = created.stdout + created.stderr
            self.assertNotIn("DB_SECRET_VALUE", public_output)
            self.assertNotIn("BRIDGE_SECRET_VALUE", public_output)
            summary = json.loads(created.stdout)
            self.assertEqual(summary["status"], "snapshot_verified")
            self.assertEqual(summary["file_count"], len(files))

            snapshot_dir = backup / "before-search3-test"
            manifest_text = (snapshot_dir / "manifest.json").read_text(encoding="utf-8")
            self.assertNotIn("DB_SECRET_VALUE", manifest_text)
            self.assertNotIn("BRIDGE_SECRET_VALUE", manifest_text)
            os.chmod(root / "config.php", 0o644)
            (root / "config.php").write_text("changed", encoding="utf-8")

            restored = base / "isolated-restore"
            result = self.run_tool("restore", "--snapshot-dir", str(snapshot_dir), "--target-root", str(restored))
            self.assertEqual(json.loads(result.stdout)["status"], "restore_drill_verified")
            for relative, (content, mode) in files.items():
                path = restored / relative
                self.assertEqual(path.read_bytes(), content)
                self.assertEqual(stat.S_IMODE(path.stat().st_mode), mode)

    def test_rejects_traversal_symlinks_and_nonempty_restore_target(self) -> None:
        with tempfile.TemporaryDirectory() as tmp:
            base = Path(tmp)
            root = base / "root"
            root.mkdir()
            outside = base / "outside.txt"
            outside.write_text("outside", encoding="utf-8")
            (root / "link").symlink_to(outside)
            bad_inventory = base / "bad.txt"
            bad_inventory.write_text("../outside.txt\n", encoding="utf-8")
            result = self.run_tool(
                "snapshot", "--root", str(root), "--backup-dir", str(base / "backup-a"),
                "--inventory", str(bad_inventory), "--snapshot-id", "bad-a", "--source-sha", "old",
                ok=False,
            )
            self.assertIn("unsafe segment", result.stderr)

            link_inventory = base / "link.txt"
            link_inventory.write_text("link\n", encoding="utf-8")
            result = self.run_tool(
                "snapshot", "--root", str(root), "--backup-dir", str(base / "backup-b"),
                "--inventory", str(link_inventory), "--snapshot-id", "bad-b", "--source-sha", "old",
                ok=False,
            )
            self.assertIn("symlink", result.stderr)

            regular = root / "index.php"
            regular.write_text("old", encoding="utf-8")
            inventory = base / "good.txt"
            inventory.write_text("index.php\n", encoding="utf-8")
            self.run_tool(
                "snapshot", "--root", str(root), "--backup-dir", str(base / "backup-c"),
                "--inventory", str(inventory), "--snapshot-id", "good", "--source-sha", "old",
            )
            target = base / "nonempty"
            target.mkdir()
            (target / "keep.txt").write_text("do not overwrite", encoding="utf-8")
            result = self.run_tool(
                "restore", "--snapshot-dir", str(base / "backup-c/good"), "--target-root", str(target),
                ok=False,
            )
            self.assertIn("must be empty", result.stderr)
            self.assertEqual((target / "keep.txt").read_text(encoding="utf-8"), "do not overwrite")


if __name__ == "__main__":
    unittest.main()
