#!/usr/bin/env bash
set -euo pipefail

output="${1:-yandex-webmaster-query-analytics.json}"
token="${YANDEX_WEBMASTER_OAUTH_TOKEN:-}"
api='https://api.webmaster.yandex.net/v4'

if [[ -z "$token" ]]; then
  printf '%s\n' '{"state":"not_configured","domain":"anytoour.ru","reason":"YANDEX_WEBMASTER_OAUTH_TOKEN_missing","responses":[]}' > "$output"
  exit 0
fi

tmp="$(mktemp -d)"
trap 'rm -rf "$tmp"' EXIT
header=(-H "Authorization: OAuth $token" -H 'Accept: application/json')

curl -fsS --retry 3 --retry-all-errors --max-time 60 "${header[@]}" "$api/user" -o "$tmp/user.json"
user_id="$(php -r '$x=json_decode(file_get_contents($argv[1]),true); $id=$x["user_id"]??null; if(!is_int($id)&&!ctype_digit((string)$id)) exit(2); echo $id;' "$tmp/user.json")"

curl -fsS --retry 3 --retry-all-errors --max-time 60 "${header[@]}" "$api/user/$user_id/hosts" -o "$tmp/hosts.json"
host_id="$(php -r '
$x=json_decode(file_get_contents($argv[1]),true);
foreach(($x["hosts"]??[]) as $host){
  $url=(string)($host["ascii_host_url"]??"");
  $name=strtolower((string)(parse_url($url,PHP_URL_HOST)??""));
  if(in_array($name,["anytoour.ru","www.anytoour.ru"],true)&&($host["verified"]??false)===true){echo (string)($host["host_id"]??""); exit;}
}
exit(3);
' "$tmp/hosts.json")"
test -n "$host_id"
encoded_host="$(php -r 'echo rawurlencode($argv[1]);' "$host_id")"

limit=500
offset=0
pages=0
response_files=()
while :; do
  pages=$((pages+1))
  if (( pages > 50 )); then echo 'YANDEX_WEBMASTER_PAGINATION_LIMIT' >&2; exit 4; fi
  body="$(printf '{"offset":%d,"limit":%d,"device_type_indicator":"ALL","search_location":"WEB_LOCATION","text_indicator":"URL"}' "$offset" "$limit")"
  file="$tmp/response-$pages.json"
  curl -fsS --retry 3 --retry-all-errors --max-time 90 \
    "${header[@]}" -H 'Content-Type: application/json; charset=UTF-8' \
    --data "$body" \
    "$api/user/$user_id/hosts/$encoded_host/query-analytics/list" -o "$file"
  php -r '$x=json_decode(file_get_contents($argv[1]),true); if(!is_array($x)||isset($x["error_code"])) exit(2); if(!is_array($x["text_indicator_to_statistics"]??null)) exit(3);' "$file"
  response_files+=("$file")
  read -r count returned < <(php -r '$x=json_decode(file_get_contents($argv[1]),true); echo (int)($x["count"]??0)," ",count($x["text_indicator_to_statistics"]??[]);' "$file")
  if (( returned == 0 || offset + returned >= count )); then break; fi
  offset=$((offset+returned))
done

php -r '
$host=$argv[1]; $responses=[];
foreach(array_slice($argv,2) as $file){$x=json_decode(file_get_contents($file),true); if(!is_array($x)) exit(2); $responses[]=$x;}
echo json_encode(["state"=>"yandex_webmaster_raw_collected","domain"=>"anytoour.ru","host_id"=>$host,"responses"=>$responses],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR),"\n";
' "$host_id" "${response_files[@]}" > "$output"

echo "YANDEX_WEBMASTER_QUERY_ANALYTICS_COLLECTED pages=$pages host=$host_id" >&2
