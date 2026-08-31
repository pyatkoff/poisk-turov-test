# Design-system version guard

`validate_design_system_version.py` scans tracked UTF-8 repository text for the superseded design-system generation label and fails CI if it reappears.

The canonical product design-system generation is 2.0. Module/file suffixes such as `-v1` are separate implementation-generation identifiers and are intentionally outside this guard.
