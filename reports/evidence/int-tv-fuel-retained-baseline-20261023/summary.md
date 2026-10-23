# Tourvisor fuel — retained public monitoring slice, 23 October 2026

Seven own contexts across three operators and four operator/country groups.
Observations1–5 are unchanged from [83bd1826](https://github.com/pyatkoff/poisk-turov-test/blob/83bd1826748f38ac472ab48b71ab405c1f4113d0/reports/evidence/int-tv-fuel-retained-baseline-20261023/summary.json).
Observation6 is the ordinary JIVA case after PR4835 installation. Observation7 is
the new two-infant DREAM LAGOON case after PR4837 consumer readiness; both exact
traces and frames are pinned in their case summaries.
This is a public evidence slice, not the private monitoring population.

| Context | Operator / country | Displayed fuel evidence | Numeric stage comparison |
| --- | --- | --- | --- |
| EMPIRE BEACH AQUA PARK | FUN&SUN / Egypt | Not shown | Unavailable |
| SUNNY DAYS MIRETTE | FUN&SUN / Egypt | Not shown | Unavailable |
| SWISS HEAVEN SHARMING INN | Biblio-Globus / Egypt | Flight14034 RUB only | Unavailable |
| BIENO SVS | Intourist / Turkey | Tour31418; flight25562 RUB | Different: flight minus tour -5856 RUB (-18.64% of tour fuel) |
| KLEOPATRA RAMIRA | Biblio-Globus / Turkey | Selected summary15739 RUB only | Unavailable |
| JIVA BEACH RESORT | Biblio-Globus / Turkey | Qualitative “Без доплаты по сбору”; numeric amount not retained | Unavailable |
| DREAM LAGOON & AQUA PARK RESORT | Biblio-Globus / Egypt | Flight13888 RUB; selected summary13888 RUB included | Unavailable: no numeric tour-stage fuel |

Fuel is described in5/7 contexts: four numeric and one qualitative-only.
Two show no fuel. Only one has both explicit numeric stage amounts; six numeric
comparisons remain unavailable. Three contexts include infants; zero expose an
isolated infant fee. “Zero exposed fees” is a count, never a fee amount.

The new JIVA case selected10–17April2027,7n/AI/`standart room land view plus`,
2adults plus child aged1, SU2122/SU2123,212106RUB for everyone, unchanged from listing.
The disclosure also says other possible surcharges need checking. Preserve that
qualification: the whole-party sum does not establish an isolated infant fee.
The no-surcharge UI label does not prove raw supplier zero, a successful backend
capture/header, or the SAMO/Biblio zero policy.

The one observed stage difference remains descriptive. It is not prediction
accuracy, a defect, a rejection threshold or a20% tolerance. No automatic pricing
rule, FX conversion, borrowed amount, double addition or final_verified promotion.

The new DREAM LAGOON case preserves two requested ages0/0 through the displayed
selection:10–17February2027/7n/comfort room/AI/Biblio-Globus, SU420/SU423,
161014→174902RUB for2adults+2children under1. The shown13888RUB fuel is included
in the applied total; do not add it again or call the price change an infant fee.
This UI observation does not prove typed raw supplier infant identity or isolated fees.

PR4835 source/CI/review/install and PR4837 consumer installation are accepted
and were not rerun. The existing console observer was read once for observation7
and returned no matching entry. That is not proof of a missing wire header,
failed capture or its root cause. No replacement reader, endpoint or extra probe.
Private cohort size,
post-install persistence count and80%accuracy remain NOT_MEASURED/null.
Offline consolidation added0supplierHTTP. Actual live HTTP counts and global
account remainder were not measured; UI action counts are not wire counts.

Next: consume authoritative natural observations through existing permitted
surfaces, with one INT owner and one handoff per case. Do not repeat this or the
five historical cases to fill missing fields.
