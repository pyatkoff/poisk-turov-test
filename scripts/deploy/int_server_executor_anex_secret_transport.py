#!/usr/bin/env python3
from __future__ import annotations

import argparse
import importlib.util
import json
import os
from pathlib import Path
from types import ModuleType
from typing import Mapping

DIRECT_ANEX_MODES = frozenset({"anex-demand", "anex-range"})
TRANSPORT_API_KEY = "__int_anex_api_token"
TRANSPORT_B2B_KEY = "__int_anex_b2b_token"

_PAYLOAD_ANCHOR = "payload=json.loads(sys.stdin.read())\noperation=payload['operation_id']; mode=payload['mode']; source=payload['source_sha']\n"
_PAYLOAD_REPLACEMENT = (
    "payload=json.loads(sys.stdin.read())\n"
    "_transport_anex_api_token=payload.pop('" + TRANSPORT_API_KEY + "',None)\n"
    "_transport_anex_b2b_token=payload.pop('" + TRANSPORT_B2B_KEY + "',None)\n"
    "operation=payload['operation_id']; mode=payload['mode']; source=payload['source_sha']\n"
)
_ENV_ANCHOR = (
    "        env={k:v for k,v in os.environ.items() if k not in ('ANEX_API_TOKEN','ANEX_B2B_TOKEN')}\n"
    "        env['ANYTOUR_PROJECT_ROOT']=str(project)\n"
)
_ENV_REPLACEMENT = (
    "        env={k:v for k,v in os.environ.items() if k not in ('ANEX_API_TOKEN','ANEX_B2B_TOKEN')}\n"
    "        if mode in ('anex-demand','anex-range'):\n"
    "            if (not isinstance(_transport_anex_api_token,str) or not _transport_anex_api_token\n"
    "                    or not isinstance(_transport_anex_b2b_token,str) or not _transport_anex_b2b_token):\n"
    "                fail('anex_secret_transport_missing')\n"
    "            env['ANEX_API_TOKEN']=_transport_anex_api_token\n"
    "            env['ANEX_B2B_TOKEN']=_transport_anex_b2b_token\n"
    "        env['ANYTOUR_PROJECT_ROOT']=str(project)\n"
)


def load_executor() -> ModuleType:
    path = Path(__file__).with_name("int_server_executor.py")
    spec = importlib.util.spec_from_file_location("int_server_executor_base", path)
    if spec is None or spec.loader is None:
        raise RuntimeError("executor_import")
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


def patch_remote(remote: str) -> str:
    if remote.count(_PAYLOAD_ANCHOR) != 1:
        raise RuntimeError("secret_transport_payload_anchor")
    if remote.count(_ENV_ANCHOR) != 1:
        raise RuntimeError("secret_transport_env_anchor")
    patched = remote.replace(_PAYLOAD_ANCHOR, _PAYLOAD_REPLACEMENT, 1)
    patched = patched.replace(_ENV_ANCHOR, _ENV_REPLACEMENT, 1)
    compile(patched, "<int-server-executor-remote>", "exec")
    return patched


def attach_transport(command: dict, environ: Mapping[str, str]) -> dict:
    out = dict(command)
    if command.get("mode") not in DIRECT_ANEX_MODES:
        return out
    api_token = environ.get("ANEX_API_TOKEN", "").strip()
    b2b_token = environ.get("ANEX_B2B_TOKEN", "").strip()
    if not api_token or not b2b_token:
        raise RuntimeError("anex_secret_transport_missing")
    out[TRANSPORT_API_KEY] = api_token
    out[TRANSPORT_B2B_KEY] = b2b_token
    return out


def main() -> None:
    executor = load_executor()
    executor.REMOTE = patch_remote(executor.REMOTE)

    parser = argparse.ArgumentParser()
    parser.add_argument("--parse-only", action="store_true")
    parser.add_argument("--source-root", default="source")
    args = parser.parse_args()

    event = json.loads(Path(os.environ["GITHUB_EVENT_PATH"]).read_text())
    token = os.environ.get("GH_TOKEN", "")
    executor.need(bool(token), "gh_token")
    command = executor.checked_event(token, event, os.environ["GITHUB_SHA"])

    if args.parse_only:
        for key, value in command.items():
            print(f"{key}={value}")
        return

    if command["mode"] in (
        "anex-range",
        "match-tv942",
        "match-samo942",
        "match-tv234-secondary",
        "match-common4-acquire",
        "match-common4-continuation-acquire",
        "match-common4-continuation-resume-day",
        "match-common4-continuation-remainder",
        "program-fuel-probe",
    ):
        executor.ensure_supplier_slot(token)

    transported = attach_transport(command, os.environ)
    result = executor.execute(transported, Path(args.source_root))
    print(json.dumps(result, sort_keys=True))
    if result.get("status") not in ("complete", "reconciled_read_only", "installed"):
        raise SystemExit(1)


if __name__ == "__main__":
    main()
