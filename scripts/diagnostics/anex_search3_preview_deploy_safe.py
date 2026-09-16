"""Build the isolated ANEX preview private config from both GitHub secrets.

The preview needs two independent credentials: the public ANEX API token and the
B2B token used by AdditionalPricesDaily. Both are supplied by GitHub Actions and
written only into the ephemeral private transport archive. The B2B token is
removed from the publisher process environment before the base publisher starts,
and neither credential is emitted to stdout or copied into workflow artifacts.
"""
from __future__ import annotations

import base64
import importlib.util
import os
from pathlib import Path
import re


BASE_PATH = Path(__file__).with_name("anex_search3_preview_deploy.py")
SPEC = importlib.util.spec_from_file_location("anex_search3_preview_deploy_base", BASE_PATH)
if SPEC is None or SPEC.loader is None:
    raise RuntimeError("ANEX preview publisher import failed")
base = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(base)

_BASE_PRIVATE_CONFIG = base.private_config
_BASE_REMOTE_SCRIPT = base.remote_script
_B2B_ENV = "ANEX_B2B_TOKEN"
_B2B_TOKEN: str | None = None


def _validated_b2b_token(token: str) -> str:
    if (not isinstance(token, str) or token == "" or len(token) > 16384
            or token.lower().startswith("bearer ")
            or re.search(r"[\x00-\x20\x7f]", token)):
        raise ValueError("missing or invalid ANEX B2B token")
    return token


def private_config(api_token: str, source_sha: str, b2b_token: str) -> str:
    """Return the private config without ever embedding either raw token."""
    token = _validated_b2b_token(b2b_token)
    rendered = _BASE_PRIVATE_CONFIG(api_token, source_sha)
    if "ANEX_B2B_TOKEN" in rendered:
        raise ValueError("ANEX preview private config already defines B2B token")
    encoded = base64.b64encode(token.encode("utf-8")).decode("ascii")
    return rendered + "define('ANEX_B2B_TOKEN', base64_decode('" + encoded + "', true));\n"


def _private_config_from_memory(api_token: str, source_sha: str) -> str:
    if _B2B_TOKEN is None:
        raise ValueError("missing ANEX B2B token")
    return private_config(api_token, source_sha, _B2B_TOKEN)


def remote_script(release: str) -> str:
    # Keep the reviewed isolated target/rollback behavior. The previous private
    # config may be copied server-side only as rollback material; it is never
    # required or parsed to construct the new config.
    return _BASE_REMOTE_SCRIPT(release)


def main() -> int:
    global _B2B_TOKEN
    # Pop before the base publisher runs so subprocess/SSH environments cannot
    # inherit the B2B secret accidentally. Missing/invalid secret fails before
    # any server-side reservation or deployment performed by this publisher.
    _B2B_TOKEN = _validated_b2b_token(os.environ.pop(_B2B_ENV, ""))
    base.private_config = _private_config_from_memory
    base.remote_script = remote_script
    try:
        return base.main()
    finally:
        _B2B_TOKEN = None
        base.private_config = _BASE_PRIVATE_CONFIG
        base.remote_script = _BASE_REMOTE_SCRIPT


if __name__ == "__main__":
    raise SystemExit(main())
