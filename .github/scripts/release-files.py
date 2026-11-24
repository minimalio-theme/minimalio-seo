#!/usr/bin/env python3
"""Write update.json and release notes for one version.

Usage: release-files.py VERSION REPO OUT_DIR

Reads requirements from the plugin header and readme.txt, and the changelog
entry "= VERSION =" from readme.txt. update.json is what installed copies of
the plugin read to offer the update (see includes/class-updater.php).
"""

import html
import json
import re
import sys
from datetime import datetime, timezone
from pathlib import Path

version, repo, out_dir = sys.argv[1], sys.argv[2], Path(sys.argv[3])
root = Path(__file__).resolve().parents[2]

plugin = (root / "minimalio-seo.php").read_text(encoding="utf-8")
readme = (root / "readme.txt").read_text(encoding="utf-8")


def header(text, name):
    match = re.search(r"^[ \t/*#@]*" + re.escape(name) + r":\s*(.+?)\s*$", text, re.M)
    return match.group(1) if match else ""


# Changelog lines for this version: everything between "= VERSION =" and the next "= x =".
section = re.search(r"^= " + re.escape(version) + r" =\s*$(.*?)(?=^= |\Z)", readme, re.M | re.S)
if not section:
    sys.exit(f"readme.txt has no changelog entry '= {version} ='")
items = [line[2:].strip() for line in section.group(1).splitlines() if line.startswith("* ")]

base = f"https://github.com/{repo}/releases"
manifest = {
    "version": version,
    "package": f"{base}/download/v{version}/minimalio-seo.zip",
    "url": f"{base}/tag/v{version}",
    "requires": header(plugin, "Requires at least"),
    "requires_php": header(plugin, "Requires PHP"),
    "tested": header(readme, "Tested up to"),
    "last_updated": datetime.now(timezone.utc).strftime("%Y-%m-%d %H:%M:%S"),
    "changelog": f"<h4>{html.escape(version)}</h4><ul>"
    + "".join(f"<li>{html.escape(item)}</li>" for item in items)
    + "</ul>",
}

out_dir.mkdir(parents=True, exist_ok=True)
(out_dir / "update.json").write_text(json.dumps(manifest, indent=2) + "\n", encoding="utf-8")
(out_dir / "notes.md").write_text("\n".join(f"- {item}" for item in items) + "\n", encoding="utf-8")
print(json.dumps(manifest, indent=2))
