"""Source-specific operator binding. Pure identity helpers; no I/O or execution.

TV uses 25/43 where SAMO uses 315/342. These are provider operator IDs,
not hotel native IDs. Numeric equality in unrelated hotel namespaces is not proof.
"""
from __future__ import annotations
import re
from typing import Any

TV_OPERATORS = {'anex': 13, 'bgoperator': 18, 'operator_315': 25, 'operator_342': 43}
SAMO_OPERATORS = {'operator_5': 5, 'operator_115': 115, 'operator_315': 315, 'operator_342': 342}
DIRECT_COMMON = ('operator_315', 'operator_342')


def operator_binding_matches(source: str, namespace: str, operator_id: Any) -> bool:
    """Never apply TV operator numbers to a SAMO observation, or vice versa."""
    if type(operator_id) is not int:
        return False
    if source == 'tv':
        return TV_OPERATORS.get(namespace) == operator_id
    if source == 'samo':
        return SAMO_OPERATORS.get(namespace) == operator_id
    return False


def identifier(value: Any) -> str:
    """A canonical native identity must be positive and retain its exact digits."""
    if type(value) not in (str, int):
        raise ValueError('identifier_type')
    if re.fullmatch(r'[1-9][0-9]{0,21}', str(value)) is None:
        raise ValueError('identifier_value')
    return str(value)


def source_identifier(value: Any) -> str:
    """Preserve signed observation IDs; a negative ID is not native proof."""
    if type(value) not in (str, int):
        raise ValueError('source_identifier_type')
    if re.fullmatch(r'-?[1-9][0-9]{0,21}', str(value)) is None:
        raise ValueError('source_identifier_value')
    return str(value)
