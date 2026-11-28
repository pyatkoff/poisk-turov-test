#!/usr/bin/env bash
set -euo pipefail
umask 077

data_dir="$1"
mode="$2"
days="$3"
state="$HOME/.anytour-price-result"
case "$mode" in
    coarse|both) ;;
    *) echo PRICE_DISPATCH_MODE_INVALID >&2; exit 2 ;;
esac
[[ "$days" =~ ^[0-9]{1,2}$ ]] && (( 10#$days >= 1 && 10#$days <= 31 )) || {
    echo PRICE_DISPATCH_WINDOW_INVALID >&2
    exit 2
}
test "$data_dir" = "$HOME/www/anytoour.ru/data"
test -d "$state" && test -O "$state" && test ! -L "$state"
exec 9>"$state/dispatch.lock"
flock -w 300 9

if test -s "$state/active-release"; then
    read -r release < "$state/active-release"
    [[ "$release" =~ ^[0-9a-f]{40}$ ]]
    package="$state/releases/$release"
    if test -r "$package/rolling-publication.py" &&
        time -p timeout 480 python3 "$package/rolling-publication.py" apply; then
        echo "PRICE_DISPATCH_REMOTE_OK release=$release caller=$mode"
        exit 0
    fi

    # Leave one owner for future legacy rollups if the checked delivery fails.
    suspended="$(mktemp "$state/suspended-release.XXXXXX")"
    mv -T -- "$state/active-release" "$suspended"
    echo "PRICE_DISPATCH_REMOTE_SUSPENDED release=$release caller=$mode" >&2
fi

php "$data_dir/rollup-price-daily-v1.php" "--days=$days"
if test "$mode" = both; then
    php "$data_dir/rollup-price-daily-exact-v1.php" "--days=$days"
fi
