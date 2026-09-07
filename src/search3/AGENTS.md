# Search3 source ownership

- Edit behavior/style modules here. Do not independently patch generated public
  `v2/search3-*` files: rebuild them with `python3 scripts/build/search3_assets.py --write`.
- Keep manifest order and IIFE boundaries unless the task explicitly changes
  lifecycle/cascade ownership and supplies relevant regression evidence.
- Keep the existing public paths and eight-asset import contract.
- A source edit is incomplete until `--check`, relevant tests and the import hash
  check pass. Commit sources and generated files together.
- Do not change Tourvisor, price arithmetic, lead delivery/mapping or analytics
  as part of presentation refactoring.
