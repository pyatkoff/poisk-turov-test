#!/usr/bin/env python3
"""MATCH orchestration only: Tourvisor evidence, then SAMO evidence.

No HTTP client, credentials, database writer or quota initializer is supplied.
Gateway implementations must use the existing authorized execution boundary,
CURRENT claims/history, provider-specific normalization, and shared account/day
ledger. Each send is exactly ONE HTTP attempt (no retries or redirects). A CSV
census is NOT a runnable Task list: real account/operator/context binding and
CURRENT admission must be supplied independently. Evidence is never acceptance.
"""
from __future__ import annotations

from collections import defaultdict
from dataclasses import asdict, dataclass, field
import hashlib
import json
import re
from typing import Any, Iterable, Protocol

FAMILIES = frozenset({"anex", "funsun", "biblio", "intourist"})
SOURCES = frozenset({"anex", "samo"})


def canonical(value: Any) -> str:
    return json.dumps(value, ensure_ascii=False, sort_keys=True,
                      separators=(",", ":"), allow_nan=False)


def digest(value: Any) -> str:
    return hashlib.sha256(canonical(value).encode()).hexdigest()


def positive(value: Any) -> bool:
    return type(value) is int and 0 < value <= 2147483647


@dataclass(frozen=True)
class Evidence:
    reference: str
    sha256: str
    state: str = "ready"

    def __post_init__(self) -> None:
        if (not self.reference or not re.fullmatch(r"[a-f0-9]{64}", self.sha256)
                or self.state not in {"ready", "hold"}):
            raise ValueError("invalid_evidence_reference")


@dataclass(frozen=True)
class Task:
    hotel_id: int
    operator_id: int
    family: str
    account: str
    # Exact provider-bound internal context, not a guessed wire schema.
    context_json: str
    missing: frozenset[str]
    retained_tour_id: str | None = None
    saved: Evidence | None = None

    def __post_init__(self) -> None:
        if (not positive(self.hotel_id) or not positive(self.operator_id)
                or self.family not in FAMILIES or not self.account
                or not isinstance(self.missing, frozenset)
                or not self.missing <= SOURCES):
            raise ValueError("invalid_task_identity")
        context = json.loads(self.context_json)
        if (not isinstance(context, dict) or not positive(context.get("country_id"))
                or not isinstance(context.get("observation_reference"), str)
                or not context["observation_reference"]
                or not isinstance(context.get("request_scope"), dict)
                or not context["request_scope"]):
            raise ValueError("unbound_request_context")
        object.__setattr__(self, "context_json", canonical(context))
        if self.retained_tour_id is not None and not re.fullmatch(r"[0-9]{1,32}", self.retained_tour_id):
            raise ValueError("invalid_retained_tour_id")

    @property
    def key(self) -> str:
        return digest([self.hotel_id, self.operator_id, self.family, self.account,
                       self.context_json])

    @property
    def group(self) -> tuple[str, int, str, str]:
        # Observation provenance can differ; ALL actual request fields must match.
        context = json.loads(self.context_json)
        scope = {k: v for k, v in context.items() if k != "observation_reference"}
        return self.account, self.operator_id, self.family, canonical(scope)


@dataclass(frozen=True)
class Request:
    action: str
    tasks: tuple[Task, ...]
    reference: str | None = None
    evidence: Evidence | None = None

    @property
    def provider(self) -> str:
        return "tourvisor" if self.action.startswith("tv.") else "samo"

    @property
    def key(self) -> str:
        return digest([self.action, [t.key for t in self.tasks], self.reference,
                       asdict(self.evidence) if self.evidence else None])


@dataclass(frozen=True)
class Reservation:
    reference: str
    request_key: str
    provider: str


@dataclass(frozen=True)
class Reply:
    status: int
    raw: bytes
    normalized_json: str


class Gateway(Protocol):
    """All methods fail closed. No method silently retries a provider request.

    begin: exclusive durable operation reservation; reused/unknown IDs must fail.
    eligible: fresh claims/history/identity/context/account binding check; False
    means excluded, occupied, already complete, blocked, terminal, or unavailable.
    authorize/reserve: recheck request authority and atomically charge the EXISTING
    account/day ledger before send. Unknown baseline is not zero or a new budget.
    persist: retain original reply bytes (also failures) durably; return their hash.
    record/finish: durable progress/terminal receipt. No mapping writes allowed.
    """
    def begin(self, operation: str, tasks: tuple[Task, ...]) -> None: ...
    def eligible(self, task: Task, phase: str) -> bool: ...
    def authorize(self, request: Request) -> bool: ...
    def reserve(self, request: Request) -> Reservation: ...
    def send(self, request: Request, reservation: Reservation) -> Reply: ...
    def persist(self, request: Request, reservation: Reservation, reply: Reply) -> Evidence: ...
    def record(self, event: str, data: dict[str, Any]) -> None: ...
    def finish(self, summary: dict[str, Any]) -> None: ...
    def pause(self, seconds: float) -> None: ...


@dataclass
class Summary:
    operation: str
    state: str = "running"
    phase: str = "tourvisor"
    unique_tasks: int = 0
    attempted_http: dict[str, int] = field(default_factory=lambda: {"tourvisor": 0, "samo": 0})
    saved_reused: int = 0
    outcomes: dict[str, dict[str, str]] = field(default_factory=dict)
    mapping_writes: int = 0


class RunStopped(RuntimeError):
    def __init__(self, summary: Summary) -> None:
        super().__init__("match_run_stopped_no_replay")
        self.summary = summary


class BatchEngine:
    def __init__(self, gateway: Gateway, *, polls: int = 5, poll_seconds: float = 3.0) -> None:
        if type(polls) is not int or not 1 <= polls <= 20 or not 0 < poll_seconds <= 60:
            raise ValueError("invalid_poll_bound")
        self.gateway = gateway
        self.polls = polls
        self.poll_seconds = poll_seconds
        self._used = False
        self._reservations: set[str] = set()

    def _exchange(self, request: Request) -> tuple[dict[str, Any], Evidence]:
        if self.gateway.authorize(request) is not True:
            raise RuntimeError("request_not_authorized")
        permit = self.gateway.reserve(request)
        if (not isinstance(permit, Reservation) or not permit.reference
                or permit.request_key != request.key or permit.provider != request.provider
                or permit.reference in self._reservations):
            raise RuntimeError("invalid_durable_reservation")
        self._reservations.add(permit.reference)
        self.summary.attempted_http[request.provider] += 1
        reply = self.gateway.send(request, permit)
        if not isinstance(reply, Reply) or not isinstance(reply.raw, bytes):
            raise RuntimeError("invalid_raw_reply")
        receipt = self.gateway.persist(request, permit, reply)
        if (not isinstance(receipt, Evidence) or receipt.state != "ready"
                or receipt.sha256 != hashlib.sha256(reply.raw).hexdigest()):
            raise RuntimeError("raw_reply_not_durably_verified")
        # Failed replies are saved before stopping; do not treat 404/429 as empty.
        if reply.status < 200 or reply.status >= 300:
            raise RuntimeError("provider_http_error")
        payload = json.loads(reply.normalized_json)
        if not isinstance(payload, dict):
            raise RuntimeError("invalid_normalized_reply")
        return payload, receipt

    def _outcome(self, task: Task, phase: str, state: str) -> None:
        self.gateway.record("task", {"task": task.key, "phase": phase, "state": state})
        self.summary.outcomes.setdefault(task.key, {})[phase] = state

    def _capture(self, task: Task, row: dict[str, Any], receipt: Evidence) -> Evidence | None:
        context = json.loads(task.context_json)
        if (row.get("hotel_id") != task.hotel_id or type(row.get("hotel_id")) is not int
                or row.get("operator_id") != task.operator_id or type(row.get("operator_id")) is not int
                or row.get("family") != task.family or type(row.get("country_id")) is not int
                or row.get("country_id") != context["country_id"]):
            self._outcome(task, "tourvisor", "identity_conflict")
            return None
        state = row.get("evidence_state")
        if state not in {"ready", "hold"}:
            self._outcome(task, "tourvisor", "link_missing")
            return None
        # The provider-specific adapter marks even ambiguous links HOLD; raw URL
        # tokens stay in the sealed reply. This module never extracts native IDs.
        evidence = Evidence(receipt.reference, receipt.sha256, state)
        self._outcome(task, "tourvisor", "captured_" + state)
        return evidence

    def _detail(self, task: Task, tour_id: str) -> Evidence | None:
        data, receipt = self._exchange(Request("tv.detail", (task,), tour_id))
        if data.get("tour_id") != tour_id:
            self._outcome(task, "tourvisor", "tour_identity_conflict")
            return None
        return self._capture(task, data, receipt)

    def _search(self, batch: tuple[Task, ...], evidence: dict[str, Evidence]) -> None:
        started, _ = self._exchange(Request("tv.search", batch))
        sid = started.get("search_id")
        if not isinstance(sid, str) or not sid:
            raise RuntimeError("search_id_missing")
        for _ in range(self.polls):
            self.gateway.pause(self.poll_seconds)
            status, _ = self._exchange(Request("tv.status", batch, sid))
            if status.get("search_id") != sid or type(status.get("complete")) is not bool:
                raise RuntimeError("search_status_binding")
            if status["complete"]:
                break
        else:
            for task in batch:
                self._outcome(task, "tourvisor", "search_not_ready_no_replay")
            return
        result, result_receipt = self._exchange(Request("tv.results", batch, sid))
        if result.get("search_id") != sid or not isinstance(result.get("rows"), list):
            raise RuntimeError("search_result_binding")
        by_hotel: dict[int, list[dict[str, Any]]] = defaultdict(list)
        for row in result["rows"]:
            if not isinstance(row, dict) or not positive(row.get("hotel_id")):
                raise RuntimeError("malformed_search_row")
            by_hotel[row["hotel_id"]].append(row)
        for task in batch:
            rows = by_hotel.get(task.hotel_id, [])
            matches = [r for r in rows if r.get("operator_id") == task.operator_id
                       and r.get("family") == task.family]
            if not matches:
                self._outcome(task, "tourvisor", "not_found_in_context")
                continue
            # A saved link (including HOLD) takes precedence over listing-only rows.
            captured = [r for r in matches if r.get("evidence_state") in {"ready", "hold"}]
            if captured:
                # Multiple raw captures need adapter adjudication, never first-link wins.
                if len(captured) > 1:
                    ref = Evidence(result_receipt.reference, result_receipt.sha256, "hold")
                    self._outcome(task, "tourvisor", "multiple_captures_hold")
                else:
                    ref = self._capture(task, captured[0], result_receipt)
            else:
                tour = matches[0].get("tour_id")
                if not isinstance(tour, str) or not re.fullmatch(r"[0-9]{1,32}", tour):
                    self._outcome(task, "tourvisor", "tour_missing")
                    continue
                if self.gateway.eligible(task, "tourvisor") is not True:
                    self._outcome(task, "tourvisor", "excluded_current")
                    continue
                ref = self._detail(task, tour)
            if ref is not None:
                evidence[task.key] = ref

    def run(self, operation: str, items: Iterable[Task]) -> Summary:
        if self._used or not re.fullmatch(r"[A-Za-z0-9_-]{1,160}", operation):
            raise ValueError("invalid_or_reused_operation")
        unique: dict[str, Task] = {}
        edges: set[tuple[int, int, str]] = set()
        for task in items:
            if not isinstance(task, Task):
                raise ValueError("task_type")
            if task.key in unique:
                if task != unique[task.key]:
                    raise ValueError("conflicting_duplicate_task")
                continue
            # One current context per hotel/operator per operation; another date
            # requires an independently justified task, not automatic retries.
            edge = task.hotel_id, task.operator_id, task.family
            if edge in edges:
                raise ValueError("multiple_contexts_for_same_edge")
            edges.add(edge)
            unique[task.key] = task
        tasks = tuple(unique.values())
        self._used = True
        self.summary = Summary(operation, unique_tasks=len(tasks))
        self.gateway.begin(operation, tasks)
        evidence: dict[str, Evidence] = {}
        try:
            groups: dict[tuple[str, int, str, str], list[Task]] = defaultdict(list)
            for task in tasks:
                if not task.missing or self.gateway.eligible(task, "tourvisor") is not True:
                    self._outcome(task, "tourvisor", "excluded_current")
                    continue
                if task.saved is not None:
                    evidence[task.key] = task.saved
                    self.summary.saved_reused += 1
                    self._outcome(task, "tourvisor", "saved_" + task.saved.state)
                elif task.retained_tour_id is not None:
                    ref = self._detail(task, task.retained_tour_id)
                    if ref is not None:
                        evidence[task.key] = ref
                else:
                    groups[task.group].append(task)
            for group in groups.values():
                # Recheck after retained-detail work, before building each search.
                for offset in range(0, len(group), 30):
                    allowed = []
                    for task in group[offset:offset + 30]:
                        if self.gateway.eligible(task, "tourvisor") is True:
                            allowed.append(task)
                        else:
                            self._outcome(task, "tourvisor", "excluded_current")
                    if allowed:
                        self._search(tuple(allowed), evidence)
            self.gateway.record("tourvisor_complete", {"evidence_count": len(evidence)})
            self.summary.phase = "samo"
            samo_captured: set[int] = set()
            for task in tasks:
                if "samo" not in task.missing:
                    continue
                if task.hotel_id in samo_captured:
                    self._outcome(task, "samo", "already_captured_this_operation")
                    continue
                ref = evidence.get(task.key)
                if ref is None or ref.state != "ready":
                    self._outcome(task, "samo", "evidence_review_required")
                    continue
                if self.gateway.eligible(task, "samo") is not True:
                    self._outcome(task, "samo", "excluded_current")
                    continue
                # Gateway must resolve a concrete namespace/catalog/context. It
                # must decline, not send, when only a fuzzy name or TV ID exists.
                data, _ = self._exchange(Request("samo.identity", (task,), evidence=ref))
                if (type(data.get("hotel_id")) is not int or data["hotel_id"] != task.hotel_id
                        or data.get("evidence_state") not in {"ready", "hold", "not_found"}):
                    raise RuntimeError("samo_evidence_binding")
                self._outcome(task, "samo", "captured_" + data["evidence_state"])
                if data["evidence_state"] == "ready":
                    samo_captured.add(task.hotel_id)
            self.summary.phase = "complete"
            self.summary.state = "evidence_complete_not_accepted"
            self.gateway.finish(asdict(self.summary))
            return self.summary
        except Exception as exc:
            self.summary.state = "stopped_no_replay"
            # Do not expose exception strings that may contain provider secrets.
            # Failure to write a terminal receipt remains unknown; never rerun.
            try:
                self.gateway.finish(asdict(self.summary))
            except Exception:
                self.summary.state = "receipt_unknown_no_replay"
            raise RunStopped(self.summary) from exc
