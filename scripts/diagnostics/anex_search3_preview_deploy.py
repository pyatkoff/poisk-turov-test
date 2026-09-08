"""Publish the owner-authorized ANEX test search in one confined preview directory.

Production entry points and the existing Search3 preview are never deployment
targets. Credentials travel only in an ephemeral SSH stdin archive; the public
payload and saved manifest contain no token.
"""
from __future__ import annotations

import base64
import hashlib
import importlib.util
import json
import os
from pathlib import Path
import re
import shlex
import shutil
import subprocess
import sys
import tarfile
import tempfile

PREVIEW_ROUTE = "/_preview/search3-anex-candidate/"
EXCLUDED_NAMES = {
    "_preview", ".git", ".env", ".anex-private.php", "config.php", "localconfig.php", "api.php", "api-v2.php",
    "lead-adapter.php", "lead-adapter-v2.php", "lead-bridge-v1.php",
    "lead-receiver-v1.php", "lead-price-v1.php", "lead-idempotency-v1.php",
}
HTACCESS = '''Options -Indexes
<IfModule mod_headers.c>
  Header always set X-Robots-Tag "noindex, nofollow"
</IfModule>
<FilesMatch "\\.php$">
  Require all denied
</FilesMatch>
<Files "index.php">
  Require all granted
</Files>
<Files "bundle-v1.php">
  Require all granted
</Files>
<Files "preview-lead-disabled.php">
  Require all granted
</Files>
<Files "api-anex-search3-preview.php">
  Require all granted
</Files>
'''


def copy_public_tree(source: Path, target: Path) -> None:
    if not source.is_dir() or source.is_symlink():
        raise ValueError("missing or linked preview source")
    target.mkdir(parents=True, exist_ok=True)
    for path in sorted(source.rglob("*")):
        relative = path.relative_to(source)
        if any(part in EXCLUDED_NAMES or part.startswith(".env") for part in relative.parts):
            continue
        if path.is_symlink():
            raise ValueError("preview source contains a symbolic link")
        if path.is_dir():
            continue
        if path.suffix.lower() == ".md" or path.name == ".htaccess":
            continue
        if not path.is_file():
            raise ValueError("preview source is not a regular file")
        destination = target / relative
        destination.parent.mkdir(parents=True, exist_ok=True)
        shutil.copyfile(path, destination)
        destination.chmod(0o644)


def build_payload(repo: Path, payload: Path, source_sha: str) -> dict:
    if not re.fullmatch(r"[0-9a-f]{40}", source_sha):
        raise ValueError("a full checked source SHA is required")
    if payload.exists():
        raise ValueError("preview payload must be a new directory")
    copy_public_tree(repo / "v2", payload)
    copy_public_tree(repo / "app" / "integrations", payload / "app" / "integrations")
    shutil.copyfile(payload / "index.php", payload / "search-page-v2.php")
    shutil.copyfile(payload / "home-entry-v1.php", payload / "index.php")
    isolation_path = repo / "scripts" / "build" / "search3_site_preview_isolation.py"
    spec = importlib.util.spec_from_file_location("anex_preview_isolation", isolation_path)
    isolation = importlib.util.module_from_spec(spec)
    assert spec.loader
    spec.loader.exec_module(isolation)
    isolation.isolate(payload)
    isolation.replace_once(payload / "site-path-v1.php",
                           "#^(/_preview/search3-site-candidate)(?:/|$)#",
                           "#^(/_preview/search3-anex-candidate)(?:/|$)#")
    search = payload / "search-page-v2.php"
    html = search.read_text(encoding="utf-8")
    if html.count("</body>") != 1:
        raise ValueError("preview search script insertion source drift")
    script = '<script src="' + PREVIEW_ROUTE + 'anex-search3-preview-v1.js"></script>'
    search.write_text(html.replace("</body>", script + "</body>"), encoding="utf-8")
    (payload / ".htaccess").write_text(HTACCESS, encoding="utf-8")
    # This denied stub contains no secret and works even when PHP clears HOME.
    (payload / ".anex-private.php").write_text(
        "<?php\nrequire_once dirname(__DIR__, 4) . '/.anytoour-anex/search3-preview.php';\n",
        encoding="utf-8")
    # PHP includes remain readable by the interpreter, never routable directly.
    (payload / "app" / ".htaccess").write_text("Require all denied\n", encoding="utf-8")
    required = ("poisk-turov/index.php", "api-anex-search3-preview.php",
                "anex-search3-preview-v1.js", "preview-lead-disabled.php",
                "app/integrations/anex-search-mapping-registry.php")
    if any(not (payload / name).is_file() for name in required):
        raise ValueError("preview runtime dependency is missing")
    files = [{"path": path.relative_to(payload).as_posix(),
              "sha256": hashlib.sha256(path.read_bytes()).hexdigest(),
              "bytes": path.stat().st_size}
             for path in sorted(payload.rglob("*")) if path.is_file()]
    manifest = {"schema_version": 1, "source_sha": source_sha,
                "route": PREVIEW_ROUTE, "file_count": len(files), "files": files,
                "production_entry_changes": False, "production_lead_delivery": False,
                "preview_metrika_counter": 0, "mapping_scope": "preview"}
    (payload / "anex-preview-manifest.json").write_text(
        json.dumps(manifest, ensure_ascii=False, sort_keys=True, indent=2) + "\n", encoding="utf-8")
    return manifest


def syntax_preflight(payload: Path) -> None:
    for suffix, command in ((".php", ["php", "-l"]), (".js", ["node", "--check"])):
        for path in sorted(payload.rglob("*" + suffix)):
            result = subprocess.run(command + [str(path)], capture_output=True, timeout=30)
            if result.returncode:
                raise ValueError("preview syntax check failed: " + path.relative_to(payload).as_posix())


def private_config(token: str, source_sha: str) -> str:
    if not token.strip() or len(token) > 16384 or "\x00" in token:
        raise ValueError("missing or invalid ANEX token")
    if not re.fullmatch(r"[0-9a-f]{40}", source_sha):
        raise ValueError("invalid source SHA")
    encoded = base64.b64encode(token.encode("utf-8")).decode("ascii")
    return ("<?php\n"
            "define('ANYTOUR_ANEX_PREVIEW_ENABLED', true);\n"
            "define('ANEX_API_TOKEN', base64_decode('" + encoded + "', true));\n"
            "define('ANEX_PREVIEW_SOURCE_SHA', '" + source_sha + "');\n")


def remote_script(release: str) -> str:
    if not re.fullmatch(r"[0-9a-f]{40}-[0-9a-f]{12}", release):
        raise ValueError("invalid preview release identifier")
    # The archive was generated from validated regular files only. It extracts
    # outside the web root; no arbitrary target path is accepted from input.
    return '''set -eu
umask 077
project="$HOME/www/anytoour.ru"
private="$HOME/.anytoour-anex"
test -d "$project"
test ! -L "$project"
test "$(basename "$(cd "$project" && pwd -P)")" = anytoour.ru
test ! -L "$private"
mkdir -p "$private"
chmod 700 "$private"
mkdir "$private/preview-deploy.lock"
trap 'rmdir "$private/preview-deploy.lock"' EXIT HUP INT TERM
release=''' + shlex.quote(release) + '''
work="$private/deploy-$release"
test ! -e "$work"
mkdir "$work"
tar -xzf - -C "$work"
test -f "$work/payload/anex-preview-manifest.json"
test -f "$work/search3-preview.php"
test ! -L "$project/_preview"
mkdir -p "$project/_preview"
target="$project/_preview/search3-anex-candidate"
stage="$project/_preview/.search3-anex-$release"
backup="$private/backup-$release"
test ! -L "$target"
test ! -e "$stage"
test ! -e "$backup"
if test -e "$target"; then
  test -d "$target"
  test -f "$target/anex-preview-manifest.json"
fi
cp -R "$work/payload" "$stage"
find "$stage" -type d -exec chmod 755 {} +
find "$stage" -type f -exec chmod 644 {} +
chmod 600 "$work/search3-preview.php"
if test -f "$private/search3-preview.php"; then
  cp -p "$private/search3-preview.php" "$work/previous-search3-preview.php"
fi
mv "$work/search3-preview.php" "$private/search3-preview.php"
if test -e "$target"; then mv "$target" "$backup"; fi
if ! mv "$stage" "$target"; then
  if test -d "$backup"; then mv "$backup" "$target"; fi
  if test -f "$work/previous-search3-preview.php"; then
    mv "$work/previous-search3-preview.php" "$private/search3-preview.php"
  fi
  exit 1
fi
test -f "$target/api-anex-search3-preview.php"
printf '%s\\n' 'ANEX_PREVIEW_DEPLOYED'
'''


def ssh_deploy(payload: Path, manifest: dict) -> dict:
    credential_names = ("ANYTOOUR_DEPLOY_SSH_KEY", "ANYTOOUR_DEPLOY_HOST", "ANYTOOUR_DEPLOY_USER", "ANEX_API_TOKEN")
    if any(not os.environ.get(name, "").strip() for name in credential_names):
        raise ValueError("missing ANEX preview deployment configuration")
    host, user = (os.environ[name].strip() for name in credential_names[1:3])
    if host.startswith("-") or any(char.isspace() for char in host + user):
        raise ValueError("invalid SSH target")
    source_sha = manifest["source_sha"]
    release = source_sha + "-" + os.urandom(6).hex()
    with tempfile.TemporaryDirectory(prefix="anex-preview-secret-", dir=os.environ.get("RUNNER_TEMP")) as temporary:
        temp = Path(temporary)
        key = temp / "ssh_key"
        key.write_text(os.environ[credential_names[0]].rstrip() + "\n", encoding="utf-8")
        key.chmod(0o600)
        config = temp / "search3-preview.php"
        config.write_text(private_config(os.environ["ANEX_API_TOKEN"], source_sha), encoding="utf-8")
        config.chmod(0o600)
        archive = temp / "private-transport.tar.gz"
        with tarfile.open(archive, "w:gz") as tar:
            tar.add(payload, arcname="payload")
            tar.add(config, arcname="search3-preview.php")
        archive.chmod(0o600)
        command = ["ssh", "-T", "-i", str(key), "-o", "IdentitiesOnly=yes", "-o", "BatchMode=yes",
                   "-o", "StrictHostKeyChecking=accept-new", "-o", "UserKnownHostsFile=" + str(temp / "known_hosts"),
                   "-o", "ConnectTimeout=15", "-o", "ServerAliveInterval=15", "-o", "ServerAliveCountMax=2",
                   "-o", "LogLevel=ERROR", "-l", user, host, "bash -c " + shlex.quote(remote_script(release))]
        with archive.open("rb") as handle:
            result = subprocess.run(command, stdin=handle, capture_output=True, timeout=600,
                                    env={k: v for k, v in os.environ.items() if k not in credential_names})
        if result.returncode or result.stdout.strip() != b"ANEX_PREVIEW_DEPLOYED":
            raise RuntimeError("confined ANEX preview deployment was not confirmed")
    return {"status": "deployed", "source_sha": source_sha, "route": PREVIEW_ROUTE,
            "url": "https://anytoour.ru" + PREVIEW_ROUTE + "poisk-turov/",
            "file_count": manifest["file_count"], "release": release,
            "rollback_backup": ".anytoour-anex/backup-" + release,
            "production_entry_changes": False}


def main() -> int:
    phase = "build"
    try:
        repo = Path(__file__).resolve().parents[2]
        artifact = Path(os.environ["ANEX_CATALOG_ARTIFACT_DIR"])
        source_sha = os.environ.get("SOURCE_SHA", os.environ.get("GITHUB_SHA", ""))
        with tempfile.TemporaryDirectory(prefix="anex-preview-build-", dir=os.environ.get("RUNNER_TEMP")) as directory:
            payload = Path(directory) / "payload"
            manifest = build_payload(repo, payload, source_sha)
            phase = "syntax_preflight"
            syntax_preflight(payload)
            (artifact / "anex-search3-preview-manifest.json").write_text(
                json.dumps(manifest, ensure_ascii=False, sort_keys=True, indent=2) + "\n", encoding="utf-8")
            phase = "confined_deploy"
            report = ssh_deploy(payload, manifest)
        phase = "save_report"
        (artifact / "anex-search3-preview-deploy.json").write_text(
            json.dumps(report, sort_keys=True, indent=2) + "\n", encoding="utf-8")
        print(json.dumps(report, sort_keys=True))
        return 0
    except Exception as exc:
        reason = str(exc)[:180] if type(exc) in (ValueError, RuntimeError) else type(exc).__name__
        print(json.dumps({"status": "anex_preview_deploy_failed", "phase": phase, "reason": reason}))
        return 1


if __name__ == "__main__":
    sys.exit(main())
