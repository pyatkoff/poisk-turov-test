#!/usr/bin/env bash
set -euo pipefail
umask 077
release="$1"
[[ "$release" =~ ^[0-9a-f]{40}$ ]]
test "$(id -un)" = anytoour
test "$HOME" = "$(getent passwd anytoour | cut -d: -f6)"
state="$HOME/.anytour-price-result"
test -d "$state" && test ! -L "$state" && test -O "$state"
test -f "$state/return_ed25519" && test ! -L "$state/return_ed25519"
test "$(stat -c %a "$state/return_ed25519")" = 600
exec 9>"$state/dispatch.lock"
flock -w 10 9
files=(rolling-publication.py rolling-digest.php apply-rolling-result.php return-vps-host-key.pub)
for file in "${files[@]}" dispatch-rolling-price.sh; do
    test -s "$file" && test ! -L "$file"
done
php -l rolling-digest.php
php -l apply-rolling-result.php
python3 -m py_compile rolling-publication.py
bash -n dispatch-rolling-price.sh
test ! -L "$state/releases"
if ! test -d "$state/releases"; then mkdir -m 700 "$state/releases"; fi
test -O "$state/releases"
test ! -L "$state/dispatch-rolling-price.sh"
if test -e "$state/dispatch-rolling-price.sh"; then
    cmp -s dispatch-rolling-price.sh "$state/dispatch-rolling-price.sh"
fi
target="$state/releases/$release"
test ! -L "$target"
if test -d "$target"; then
    for file in "${files[@]}"; do cmp -s "$file" "$target/$file"; done
else
    incoming="$(mktemp -d "$state/releases/.incoming.XXXXXX")"
    trap 'rm -rf -- "$incoming"' EXIT
    install -m 600 -- "${files[@]}" "$incoming"
    mv -T -- "$incoming" "$target"
fi
if ! test -e "$state/dispatch-rolling-price.sh"; then
    staged_dispatch="$(mktemp "$state/.dispatch.XXXXXX")"
    install -m 600 dispatch-rolling-price.sh "$staged_dispatch"
    mv -T -- "$staged_dispatch" "$state/dispatch-rolling-price.sh"
fi
if test -e "$state/active-release"; then
    echo "PRICE_RUNTIME_STAGED release=$release active_state=present"
else
    echo "PRICE_RUNTIME_STAGED release=$release active_state=absent"
fi
