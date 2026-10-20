#!/usr/bin/env python3
"""Render a self-contained review index for Search3 visual evidence."""

from __future__ import annotations

import argparse
import hashlib
import html
import json
import re
from pathlib import Path


def _verified_capture(root: Path, item: dict) -> dict:
    name = str(item.get("file", ""))
    if not re.fullmatch(r"[0-9A-Za-z][0-9A-Za-z._-]*\.png", name) or Path(name).name != name:
        raise ValueError(f"unsafe capture name: {name!r}")
    capture = root / name
    if not capture.is_file() or capture.is_symlink():
        raise ValueError(f"missing capture: {name}")
    digest = hashlib.sha256(capture.read_bytes()).hexdigest()
    if digest != item.get("sha256"):
        raise ValueError(f"capture digest mismatch: {name}")
    return {**item, "file": name, "sha256": digest}


def render(manifest_path: Path, output_dir: Path) -> tuple[Path, Path]:
    root = manifest_path.resolve().parent
    manifest = json.loads(manifest_path.read_text(encoding="utf-8"))
    if manifest.get("schemaVersion") != 2:
        raise ValueError("Search3 visual manifest schemaVersion must be 2")
    tier = str(manifest.get("visualTier", ""))
    if tier not in {"pr", "candidate"}:
        raise ValueError(f"unsupported visual tier: {tier!r}")
    source_sha = str(manifest.get("sourceSha", ""))
    tested_sha = str(manifest.get("testedSha", ""))
    if not re.fullmatch(r"[0-9a-f]{40}", source_sha) or not re.fullmatch(r"[0-9a-f]{40}", tested_sha):
        raise ValueError("sourceSha/testedSha must be full commit SHAs")
    if source_sha != tested_sha:
        raise ValueError("sourceSha/testedSha must identify the same exact checkout")

    captures = [
        _verified_capture(root, item)
        for item in [*manifest.get("screenshots", []), *manifest.get("presentationScreenshots", [])]
    ]
    if not captures:
        raise ValueError("visual manifest contains no captures")
    behaviors = manifest.get("behaviorStates", [])
    if not all(item.get("passed") is True for item in behaviors):
        raise ValueError("visual manifest contains a failed behavior state")

    output_dir.mkdir(parents=True, exist_ok=True)
    html_path = output_dir / "review.html"
    markdown_path = output_dir / "review.md"

    cards = []
    rows = []
    for item in captures:
        name = item["file"]
        geometry = item.get("geometry", {})
        width = geometry.get("viewportWidth", "presentation")
        state = geometry.get("state", "interaction")
        cards.append(
            '<figure><a href="{src}"><img loading="lazy" src="{src}" alt="{alt}"></a>'
            '<figcaption><strong>{name}</strong><span>{width} · {state}</span></figcaption></figure>'.format(
                src=html.escape(name, quote=True),
                alt=html.escape(f"Search3 {width} {state}", quote=True),
                name=html.escape(name),
                width=html.escape(str(width)),
                state=html.escape(str(state)),
            )
        )
        rows.append(f"| `{name}` | {width} | {state} | `{item['sha256']}` |")

    warning = ""
    baseline = manifest.get("visualBaseline", {})
    if baseline.get("ownerVisualApproval") is not True:
        warning = (
            '<p class="warning"><strong>Review status:</strong> automated checks passed, '
            'but owner visual approval is still required.</p>'
        )

    html_path.write_text(
        "<!doctype html><html lang=\"ru\"><head><meta charset=\"utf-8\">"
        "<meta name=\"viewport\" content=\"width=device-width,initial-scale=1\">"
        "<meta name=\"robots\" content=\"noindex,nofollow\"><title>Search3 visual review</title>"
        "<style>body{font:14px/1.5 system-ui;margin:0;background:#f4f6fb;color:#14213d}"
        "header{position:sticky;top:0;z-index:2;padding:16px 24px;background:#fff;border-bottom:1px solid #dfe5ef}"
        "h1{font-size:20px;margin:0 0 4px}.meta{color:#667085}.warning{padding:10px 12px;background:#fff3e8;border-radius:8px}"
        "main{display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:16px;padding:20px}"
        "figure{margin:0;padding:10px;background:#fff;border:1px solid #dfe5ef;border-radius:12px}"
        "img{display:block;width:100%;height:auto;border:1px solid #edf0f5}figcaption{display:flex;justify-content:space-between;gap:8px;padding-top:8px}"
        "figcaption span{color:#667085}@media(max-width:430px){header{padding:12px}main{grid-template-columns:1fr;padding:10px}}</style>"
        "</head><body><header><h1>Search3 visual review</h1>"
        f"<div class=\"meta\">tier={html.escape(tier)} · source={html.escape(source_sha)} · "
        f"tested={html.escape(tested_sha)} · captures={len(captures)}</div>{warning}</header>"
        f"<main>{''.join(cards)}</main></body></html>\n",
        encoding="utf-8",
    )

    markdown_path.write_text(
        "# Search3 visual review\n\n"
        f"- Tier: `{tier}`\n"
        f"- Source SHA: `{source_sha}`\n"
        f"- Tested SHA: `{tested_sha}`\n"
        f"- Captures: `{len(captures)}`\n"
        f"- Behavior states passed: `{len(behaviors)}`\n"
        f"- Owner visual approval: `{bool(baseline.get('ownerVisualApproval'))}`\n\n"
        "| Capture | Width | State | SHA-256 |\n"
        "| --- | ---: | --- | --- |\n"
        + "\n".join(rows)
        + "\n",
        encoding="utf-8",
    )
    return html_path, markdown_path


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--manifest", required=True, type=Path)
    parser.add_argument("--output-dir", required=True, type=Path)
    args = parser.parse_args()
    html_path, markdown_path = render(args.manifest, args.output_dir)
    print(f"SEARCH3_VISUAL_REVIEW_OK html={html_path} markdown={markdown_path}")


if __name__ == "__main__":
    main()
