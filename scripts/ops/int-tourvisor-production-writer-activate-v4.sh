#!/usr/bin/env bash
set -euo pipefail

: "${BASE_SHA:?}"
: "${OPERATION_ID:?}"
: "${ANYTOOUR_DEPLOY_SSH_KEY:?}"
: "${ANYTOOUR_DEPLOY_HOST:?}"
: "${ANYTOOUR_DEPLOY_USER:?}"
: "${RUNNER_TEMP:?}"
: "${GITHUB_RUN_ID:?}"

stage="$RUNNER_TEMP/writer-runtime-v3"
mkdir -p "$stage/app" "$stage/v2"
cp v2/api-v2.php "$stage/api-v2.php"
cp -a app/integrations "$stage/app/integrations"
cp -a v2/data "$stage/v2/data"
find "$stage/app/integrations" "$stage/v2/data" -type f -name '*.php' -print0 | xargs -0 -r -n 1 -P 2 php -l >/dev/null
php -l "$stage/api-v2.php" >/dev/null
api_sha="$(sha256sum "$stage/api-v2.php" | awk '{print $1}')"
(cd "$stage" && find . -type f -print0 | LC_ALL=C sort -z | xargs -0 sha256sum) > "$RUNNER_TEMP/payload-v3.sha256"
payload_sha="$(sha256sum "$RUNNER_TEMP/payload-v3.sha256" | awk '{print $1}')"
archive="$RUNNER_TEMP/writer-runtime-v3.tar.gz"
tar -C "$stage" -czf "$archive" .
archive_sha="$(sha256sum "$archive" | awk '{print $1}')"
echo "PRODUCTION_WRITER_V3_PAYLOAD_OK api=$api_sha payload=$payload_sha archive=$archive_sha"

key="$RUNNER_TEMP/anytoour_key"
if [[ "$ANYTOOUR_DEPLOY_SSH_KEY" == *"PRIVATE KEY"* ]]; then
  printf '%s\n' "$ANYTOOUR_DEPLOY_SSH_KEY" | tr -d '\r' > "$key"
else
  printf '%s' "$ANYTOOUR_DEPLOY_SSH_KEY" | base64 --decode > "$key"
fi
chmod 600 "$key"
ssh-keygen -y -f "$key" >/dev/null

opts=(-T -i "$key" -o IdentitiesOnly=yes -o BatchMode=yes -o StrictHostKeyChecking=accept-new -o UserKnownHostsFile="$RUNNER_TEMP/known_hosts" -o ConnectTimeout=15 -o ServerAliveInterval=15 -o ServerAliveCountMax=3)
scp_opts=(-i "$key" -o IdentitiesOnly=yes -o BatchMode=yes -o StrictHostKeyChecking=accept-new -o UserKnownHostsFile="$RUNNER_TEMP/known_hosts" -o ConnectTimeout=15)
remote="$ANYTOOUR_DEPLOY_USER@$ANYTOOUR_DEPLOY_HOST"
remote_archive="/tmp/${OPERATION_ID}.${GITHUB_RUN_ID}.tar.gz"
scp "${scp_opts[@]}" "$archive" "$remote:$remote_archive"

set +e
ssh "${opts[@]}" "$remote" bash -s -- "$OPERATION_ID" "$remote_archive" "$BASE_SHA" "$api_sha" "$archive_sha" <<'REMOTE'
set -euo pipefail
operation="$1"; archive="$2"; source_sha="$3"; expected_api_sha="$4"; expected_archive_sha="$5"
site="$HOME/www/anytoour.ru"
parent="$HOME/www"
runtime_app="$parent/app"
runtime_v2="$parent/v2"
ledger="$HOME/.anytour-ops/$operation"
work=''
app_created=0
v2_created=0
api_activated=0
success=0

finish(){
  rc=$?
  if [ "$success" -ne 1 ]; then
    if [ "$api_activated" -eq 1 ] && [ -s "$ledger/api-v2.before.php" ]; then
      cp "$ledger/api-v2.before.php" "$site/api-v2.php.rollback"
      chmod 644 "$site/api-v2.php.rollback"
      mv -f "$site/api-v2.php.rollback" "$site/api-v2.php"
      php -l "$site/api-v2.php" >/dev/null || true
    fi
    if [ "$app_created" -eq 1 ]; then rm -rf "$runtime_app"; fi
    if [ "$v2_created" -eq 1 ]; then rm -rf "$runtime_v2"; fi
    if [ -d "$ledger" ]; then
      printf '%s\n' 'failed_rolled_back_terminal_no_replay' > "$ledger/state"
      printf '{"status":"failed_rolled_back_terminal_no_replay","operation":"%s","release_sha":"%s"}\n' "$operation" "$source_sha" > "$ledger/failure.json"
    fi
  fi
  [ -n "$work" ] && rm -rf "$work" || true
  rm -f "$archive"
  trap - EXIT
  exit "$rc"
}
trap finish EXIT

test -d "$site" && test -s "$site/config.php" && test -s "$site/api-v2.php"
test ! -e "$runtime_app" && test ! -L "$runtime_app"
test ! -e "$runtime_v2" && test ! -L "$runtime_v2"
test ! -e "$ledger"
test "$(sha256sum "$archive" | awk '{print $1}')" = "$expected_archive_sha"
php -l "$site/api-v2.php" >/dev/null
curl -fsS --max-time 45 'https://anytoour.ru/api-v2.php?action=health' | grep -q 'tourvisor-direct'

mkdir -p "$HOME/.anytour-ops"
mkdir -m 700 "$ledger"
printf '%s\n' 'reserved' > "$ledger/state"
cp "$site/api-v2.php" "$ledger/api-v2.before.php"
before_api_sha="$(sha256sum "$ledger/api-v2.before.php" | awk '{print $1}')"

before_json="$(DOCUMENT_ROOT="$site" php -r '
  $_SERVER["DOCUMENT_ROOT"]=getenv("DOCUMENT_ROOT");
  require $_SERVER["DOCUMENT_ROOT"]."/data/db-v1.php";
  $db=v2_data_db();
  $q=function($sql)use($db){return (int)$db->query($sql)->fetchColumn();};
  echo json_encode([
    "refreshes"=>$q("SELECT COUNT(*) FROM anytour_offer_refreshes WHERE provider=\"tourvisor\" AND status=\"complete\""),
    "offers"=>$q("SELECT COUNT(*) FROM anytour_offers WHERE provider=\"tourvisor\" AND is_active=1"),
    "ready"=>$q("SELECT COUNT(*) FROM anytour_offers WHERE provider=\"tourvisor\" AND is_active=1 AND final_price_ready=1 AND currency=\"RUB\"")
  ],JSON_THROW_ON_ERROR);
')"
printf '%s' "$before_json" > "$ledger/before.json"

work="$(mktemp -d "/tmp/${operation}.XXXXXX")"
tar -C "$work" -xzf "$archive"
test "$(sha256sum "$work/api-v2.php" | awk '{print $1}')" = "$expected_api_sha"
php -l "$work/api-v2.php" >/dev/null
find "$work/app/integrations" "$work/v2/data" -type f -name '*.php' -print0 | xargs -0 -r -n 1 -P 2 php -l >/dev/null

mv "$work/app" "$runtime_app"
app_created=1
mv "$work/v2" "$runtime_v2"
v2_created=1
test -s "$runtime_app/integrations/tourvisor-anytour-offer-autosave.php"
test -s "$runtime_v2/data/anytour-offer-snapshot-ingest-v1.php"

cp "$work/api-v2.php" "$site/api-v2.php.new"
chmod 644 "$site/api-v2.php.new"
php -l "$site/api-v2.php.new" >/dev/null
mv -f "$site/api-v2.php.new" "$site/api-v2.php"
api_activated=1
test "$(sha256sum "$site/api-v2.php" | awk '{print $1}')" = "$expected_api_sha"

curl -fsS --max-time 50 'https://anytoour.ru/api-v2.php?action=health' -o "$ledger/health.json"
grep -q 'tourvisor-direct' "$ledger/health.json"

from='2026-10-12'; till='2026-10-12'
curl -fsS --max-time 50 --get \
  --data-urlencode 'action=search_start' \
  --data-urlencode 'departureId=1' \
  --data-urlencode 'countryId=4' \
  --data-urlencode "dateFrom=$from" \
  --data-urlencode "dateTo=$till" \
  --data-urlencode 'nightsFrom=7' \
  --data-urlencode 'nightsTo=7' \
  --data-urlencode 'adults=2' \
  --data-urlencode 'currency=RUB' \
  'https://anytoour.ru/api-v2.php' -o "$ledger/search-start.json"
search_id="$(php -r '$d=json_decode(file_get_contents($argv[1]),true,32,JSON_THROW_ON_ERROR);$id=(int)($d["searchId"]??0);if($id<1)exit(2);echo $id;' "$ledger/search-start.json")"
complete=0
for attempt in $(seq 1 24); do
  sleep 2
  curl -fsS --max-time 45 --get --data-urlencode 'action=search_status' --data-urlencode "searchId=$search_id" 'https://anytoour.ru/api-v2.php' -o "$ledger/search-status.json"
  if php -r '$d=json_decode(file_get_contents($argv[1]),true,32,JSON_THROW_ON_ERROR);exit((($d["progress"]??0)==100||($d["status"]??"")==="complete")?0:1);' "$ledger/search-status.json"; then complete=1; break; fi
done
test "$complete" = 1
curl -fsS --max-time 50 --get --data-urlencode 'action=search_results' --data-urlencode "searchId=$search_id" --data-urlencode 'limit=100' 'https://anytoour.ru/api-v2.php' -o "$ledger/search-results.json"
result_hotels="$(php -r '$d=json_decode(file_get_contents($argv[1]),true,64,JSON_THROW_ON_ERROR);if(!is_array($d)||!array_is_list($d)||count($d)<1)exit(2);echo count($d);' "$ledger/search-results.json")"

after_json="$(DOCUMENT_ROOT="$site" php -r '
  $_SERVER["DOCUMENT_ROOT"]=getenv("DOCUMENT_ROOT");
  require $_SERVER["DOCUMENT_ROOT"]."/data/db-v1.php";
  $db=v2_data_db();
  $q=function($sql)use($db){return (int)$db->query($sql)->fetchColumn();};
  echo json_encode([
    "refreshes"=>$q("SELECT COUNT(*) FROM anytour_offer_refreshes WHERE provider=\"tourvisor\" AND status=\"complete\""),
    "offers"=>$q("SELECT COUNT(*) FROM anytour_offers WHERE provider=\"tourvisor\" AND is_active=1"),
    "ready"=>$q("SELECT COUNT(*) FROM anytour_offers WHERE provider=\"tourvisor\" AND is_active=1 AND final_price_ready=1 AND currency=\"RUB\""),
    "latest_scopes"=>$q("SELECT COUNT(*) FROM anytour_offer_scope_state WHERE latest_complete_refresh_token IS NOT NULL")
  ],JSON_THROW_ON_ERROR);
')"
printf '%s' "$after_json" > "$ledger/after.json"
BEFORE_JSON="$before_json" AFTER_JSON="$after_json" php -r '
  $b=json_decode(getenv("BEFORE_JSON"),true,16,JSON_THROW_ON_ERROR);
  $a=json_decode(getenv("AFTER_JSON"),true,16,JSON_THROW_ON_ERROR);
  if(($a["refreshes"]??0) <= ($b["refreshes"]??0)) exit(10);
  if(($a["offers"]??0) < 1 || ($a["ready"]??0) < 1) exit(11);
'

receipt="$(SOURCE_SHA="$source_sha" BEFORE_API_SHA="$before_api_sha" API_SHA="$expected_api_sha" SEARCH_ID="$search_id" RESULT_HOTELS="$result_hotels" BEFORE_JSON="$before_json" AFTER_JSON="$after_json" php -r '
  $out=[
    "schema_version"=>1,
    "operation"=>"int-tourvisor-production-writer-20260917-v3",
    "status"=>"completed",
    "release_sha"=>getenv("SOURCE_SHA"),
    "before_api_sha256"=>getenv("BEFORE_API_SHA"),
    "after_api_sha256"=>getenv("API_SHA"),
    "search_id"=>(int)getenv("SEARCH_ID"),
    "result_hotels"=>(int)getenv("RESULT_HOTELS"),
    "before"=>json_decode(getenv("BEFORE_JSON"),true),
    "after"=>json_decode(getenv("AFTER_JSON"),true),
    "public_file_writes"=>["api-v2.php"],
    "private_runtime"=>["../app/integrations","../v2/data"],
    "lead_writes"=>0,"metrika_writes"=>0,"booking_calls"=>0,"mapping_writes"=>0,
    "rollback_retained"=>true
  ];
  echo json_encode($out,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
')"
printf '%s\n' "$receipt" > "$ledger/receipt.json"
printf '%s\n' 'completed' > "$ledger/state"
success=1
cat "$ledger/receipt.json"
REMOTE
remote_rc=$?
set -e

if [ "$remote_rc" -eq 0 ]; then
  ssh "${opts[@]}" "$remote" "cat \"\$HOME/.anytour-ops/$OPERATION_ID/receipt.json\"" > "$RUNNER_TEMP/receipt.json"
  jq -e '.status == "completed" and (.after.refreshes > .before.refreshes) and (.after.offers > 0) and (.after.ready > 0) and .lead_writes == 0 and .metrika_writes == 0 and .booking_calls == 0 and .mapping_writes == 0' "$RUNNER_TEMP/receipt.json" >/dev/null
  cat "$RUNNER_TEMP/receipt.json"
  exit 0
fi

ssh "${opts[@]}" "$remote" "cat \"\$HOME/.anytour-ops/$OPERATION_ID/failure.json\" 2>/dev/null || true" > "$RUNNER_TEMP/receipt.json" || true
if [ ! -s "$RUNNER_TEMP/receipt.json" ]; then
  printf '{"status":"runner_no_receipt","operation":"%s","remote_rc":%s}\n' "$OPERATION_ID" "$remote_rc" > "$RUNNER_TEMP/receipt.json"
fi
cat "$RUNNER_TEMP/receipt.json"
exit "$remote_rc"
