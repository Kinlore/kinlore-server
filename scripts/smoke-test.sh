#!/usr/bin/env bash
# End-to-end check of a running Kinlore API against docs/API_CONTRACT.md.
# Usage: scripts/smoke-test.sh <base url> <admin email> <admin password>
#   e.g. scripts/smoke-test.sh http://localhost:8088 admin@example.test 'TestPassword123!'
# Needs: curl, jq. Optional: ffmpeg (video checks are skipped without it).
# It creates test data (kids, moments, one invited user). Use it on a test instance only.
set -uo pipefail

BASE="${1:?base url}"; EMAIL="${2:?admin email}"; PASSWORD="${3:?admin password}"
API="$BASE/api"
WORK="$(mktemp -d)"; trap 'rm -rf "$WORK"' EXIT
PASS=0; FAIL=0
ok()   { PASS=$((PASS+1)); printf '  ok    %s\n' "$1"; }
bad()  { FAIL=$((FAIL+1)); printf '  FAIL  %s\n        %s\n' "$1" "${2:-}"; }
# check <name> <jq expression that must be true> <json>
check() { if echo "$3" | jq -e "$2" >/dev/null 2>&1; then ok "$1"; else bad "$1" "$(echo "$3" | head -c 400)"; fi; }
# req <method> <path> [token] [curl args...] -> prints "<status> <body>"
req() {
  local m="$1" p="$2" t="${3:-}"; shift 3 2>/dev/null || shift $#
  local auth=(); [ -n "$t" ] && auth=(-H "Authorization: Bearer $t")
  curl -s -o "$WORK/body" -w '%{http_code}' -X "$m" -H 'Accept: application/json' "${auth[@]}" "$@" "$API$p" > "$WORK/code"
  echo "$(cat "$WORK/code") $(cat "$WORK/body")"
}
status() { echo "${1%% *}"; }
body()   { echo "${1#* }"; }
TODAY=$(date +%F)

echo "== Health and setup"
r=$(req GET /health); check "health ok" '.status=="ok"' "$(body "$r")"
r=$(req GET /auth/setup-status); check "setup-status shape" 'has("needs_setup") and has("setup_token_required")' "$(body "$r")"

echo "== Admin login"
r=$(req POST /auth/login "" -H 'Content-Type: application/json' -d "{\"email\":\"$EMAIL\",\"password\":\"$PASSWORD\"}")
ADMIN=$(body "$r" | jq -r '.data.token // empty')
[ -n "$ADMIN" ] && ok "login" || { bad "login" "$(body "$r")"; echo "Cannot continue."; exit 1; }
check "login user has role admin" '.data.user.role=="admin" and .data.user.is_admin==true' "$(body "$r")"
r=$(req GET /auth/me "$ADMIN"); check "me has permissions and family counts" '.data.permissions.download==true and (.data|has("my_moments_count"))' "$(body "$r")"

echo "== Kids"
r=$(req POST /v1/kids "$ADMIN" -H 'Content-Type: application/json' -d '{"name":"Smoke Mia","date_of_birth":"2023-04-02"}')
KID=$(body "$r" | jq -r '.data.id // empty'); check "create born kid" '.data.age|type=="number"' "$(body "$r")"
r=$(req POST /v1/kids "$ADMIN" -H 'Content-Type: application/json' -d '{"name":"Bump","due_date":"2027-03-01"}')
check "create expecting kid" '.data.is_expecting==true and .data.age==null' "$(body "$r")"
r=$(req POST /v1/kids "$ADMIN" -H 'Content-Type: application/json' -d "{\"name\":\"Today\",\"date_of_birth\":\"$TODAY\"}")
[ "$(status "$r")" = "201" ] && ok "kid born today accepted" || bad "kid born today accepted" "$(body "$r")"

echo "== Moments"
# 64x48 JPEG made with PHP GD
echo '/9j/4AAQSkZJRgABAQEAYABgAAD//gA7Q1JFQVRPUjogZ2QtanBlZyB2MS4wICh1c2luZyBJSkcgSlBFRyB2NjIpLCBxdWFsaXR5ID0gODAK/9sAQwAGBAUGBQQGBgUGBwcGCAoQCgoJCQoUDg8MEBcUGBgXFBYWGh0lHxobIxwWFiAsICMmJykqKRkfLTAtKDAlKCko/9sAQwEHBwcKCAoTCgoTKBoWGigoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoKCgo/8AAEQgAMABAAwEiAAIRAQMRAf/EAB8AAAEFAQEBAQEBAAAAAAAAAAABAgMEBQYHCAkKC//EALUQAAIBAwMCBAMFBQQEAAABfQECAwAEEQUSITFBBhNRYQcicRQygZGhCCNCscEVUtHwJDNicoIJChYXGBkaJSYnKCkqNDU2Nzg5OkNERUZHSElKU1RVVldYWVpjZGVmZ2hpanN0dXZ3eHl6g4SFhoeIiYqSk5SVlpeYmZqio6Slpqeoqaqys7S1tre4ubrCw8TFxsfIycrS09TV1tfY2drh4uPk5ebn6Onq8fLz9PX29/j5+v/EAB8BAAMBAQEBAQEBAQEAAAAAAAABAgMEBQYHCAkKC//EALURAAIBAgQEAwQHBQQEAAECdwABAgMRBAUhMQYSQVEHYXETIjKBCBRCkaGxwQkjM1LwFWJy0QoWJDThJfEXGBkaJicoKSo1Njc4OTpDREVGR0hJSlNUVVZXWFlaY2RlZmdoaWpzdHV2d3h5eoKDhIWGh4iJipKTlJWWl5iZmqKjpKWmp6ipqrKztLW2t7i5usLDxMXGx8jJytLT1NXW19jZ2uLj5OXm5+jp6vLz9PX29/j5+v/aAAwDAQACEQMRAD8AoUUUV8afoYUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFABRRRQAUUUUAFFFFAH//Z' | base64 -d > "$WORK/p.jpg"
r=$(req POST /v1/moments "$ADMIN" -F "kid_id=$KID" -F "file=@$WORK/p.jpg;type=image/jpeg" -F device_asset_id=smoke-1 -F "exif_date=$(( $(date +%Y) - 1 ))-$(date +%m-%d)T10:00:00Z")
PHOTO=$(body "$r" | jq -r '.data.moment.id // empty')
check "upload photo, uploader set" '.data.moment.user_id!=null and .data.moment.moment_type=="photo"' "$(body "$r")"
r=$(req POST /v1/moments "$ADMIN" -H 'Content-Type: application/json' -d "{\"kid_id\":\"$KID\",\"moment_type\":\"story\",\"title\":\"First words\",\"description\":\"She said cat.\"}")
STORY=$(body "$r" | jq -r '.data.moment.id // empty'); check "story title stored" '.data.moment.title=="First words"' "$(body "$r")"
r=$(req POST /v1/moments "$ADMIN" -H 'Content-Type: application/json' -d "{\"kid_id\":\"$KID\",\"moment_type\":\"growth\",\"exif_date\":\"$TODAY\",\"height_cm\":92.5,\"weight_kg\":13.4}")
check "growth entry" '.data.moment.moment_type=="growth" and (.data.moment.height_cm|tonumber)==92.5' "$(body "$r")"
r=$(req POST /v1/moments "$ADMIN" -H 'Content-Type: application/json' -d "{\"kid_id\":\"$KID\",\"moment_type\":\"growth\",\"exif_date\":\"$TODAY\"}")
[ "$(status "$r")" = "422" ] && ok "growth without measurement rejected" || bad "growth without measurement rejected" "$(body "$r")"
r=$(req PUT "/v1/moments/$STORY" "$ADMIN" -H 'Content-Type: application/json' -d '{"description":"She said cat!","exif_date":"2025-01-02"}')
check "update moment with exif_date" '.success==true' "$(body "$r")"
r=$(req GET "/v1/moments?kid_id=$KID&moment_type=story,growth" "$ADMIN")
check "list with type filter" '(.data|length)==2 and (.data|all(.moment_type=="story" or .moment_type=="growth"))' "$(body "$r")"
r=$(req POST "/v1/moments/$PHOTO/like" "$ADMIN"); [ "${r%% *}" -lt 300 ] && ok "like" || bad "like" "$r"
r=$(req GET "/v1/moments?liked=true" "$ADMIN"); check "liked filter" '(.data|map(.id)|index("'"$PHOTO"'"))!=null' "$(body "$r")"
r=$(req GET "/v1/moments/$PHOTO" "$ADMIN"); check "moment counts" '.data.likes_count==1 and .data.is_liked==true' "$(body "$r")"
r=$(req GET "/v1/moments/$PHOTO/file/thumbnail" "$ADMIN"); [ "$(status "$r")" = "200" ] && ok "thumbnail served" || bad "thumbnail served" "$(status "$r")"

if command -v ffmpeg >/dev/null; then
  ffmpeg -loglevel error -f lavfi -i testsrc=duration=2:size=320x240:rate=15 -pix_fmt yuv420p "$WORK/v.mp4"
  r=$(req POST /v1/moments "$ADMIN" -F "kid_id=$KID" -F "file=@$WORK/v.mp4;type=video/mp4")
  VIDEO=$(body "$r" | jq -r '.data.moment.id // empty')
  check "video upload returns at once" '.data.moment.video_status=="processing" or .data.moment.video_status=="ready"' "$(body "$r")"
  for _ in $(seq 1 30); do
    s=$(body "$(req GET "/v1/moments/$VIDEO" "$ADMIN")" | jq -r '.data.video_status'); [ "$s" = "ready" ] || [ "$s" = "failed" ] && break; sleep 2
  done
  [ "$s" = "ready" ] && ok "video processed" || bad "video processed" "status=$s"
  r=$(req GET "/v1/moments/$VIDEO/playback" "$ADMIN"); HLS=$(body "$r" | jq -r '.data.hls_url // empty')
  [ -n "$HLS" ] && [ "$(curl -s -o "$WORK/m3u8" -w '%{http_code}' "$HLS")" = "200" ] && ok "signed playlist" || bad "signed playlist" "$r"
  SEG=$(grep -v '^#' "$WORK/m3u8" | head -1)
  [ -n "$SEG" ] && [ "$(curl -s -o /dev/null -w '%{http_code}' "$SEG")" = "200" ] && ok "playlist lines are signed" || bad "playlist lines are signed" "$SEG"
  [ "$(curl -s -o /dev/null -w '%{http_code}' "${HLS%%\?*}")" = "403" ] && ok "unsigned HLS refused" || bad "unsigned HLS refused"
else
  echo "  skip  video checks (ffmpeg not installed here)"
fi

echo "== Invitations and members"
r=$(req POST /v1/invitations "$ADMIN" -H 'Content-Type: application/json' -d '{"role":"family","permissions":{"comment":false}}')
INV=$(body "$r" | jq -r '.data.token // empty')
check "create invitation with links" '(.data.invite_link|test("^kinlore://invite/.+\\?server=")) and (.data.invite_url|test("/invite/"))' "$(body "$r")"
r=$(req GET "/auth/invitations/$INV"); check "public invitation check" '.data.valid==true and .data.role=="family"' "$(body "$r")"
[ "$(curl -s -o /dev/null -w '%{http_code}' "$BASE/invite/$INV")" = "200" ] && ok "invite page" || bad "invite page"
[ "$(curl -s -o /dev/null -w '%{http_code}' "$BASE/invite/not-a-token")" = "404" ] && ok "invite page unknown token 404" || bad "invite page unknown token 404"
FEMAIL="smoke-$(date +%s)@example.test"
r=$(req POST /auth/register "" -H 'Content-Type: application/json' -d "{\"name\":\"Grandma\",\"email\":\"$FEMAIL\",\"password\":\"Password123!\",\"password_confirmation\":\"Password123!\",\"invite_token\":\"$INV\"}")
FAM=$(body "$r" | jq -r '.data.token // empty'); FAMID=$(body "$r" | jq -r '.data.user.id // empty')
check "register with invitation gets role" '.data.user.role=="family" and .data.user.permissions.comment==false' "$(body "$r")"
r=$(req GET "/auth/invitations/$INV"); [ "$(status "$r")" = "404" ] && ok "used invitation is no longer valid" || bad "used invitation is no longer valid" "$r"
r=$(req GET /v1/kids "$FAM"); check "family sees kids" '(.data|length)>=1' "$(body "$r")"
r=$(req POST "/v1/moments/$PHOTO/comments" "$FAM" -H 'Content-Type: application/json' -d '{"content":"Cute"}')
[ "$(status "$r")" = "403" ] && ok "comment blocked by permission" || bad "comment blocked by permission" "$r"
r=$(req POST /v1/kids "$FAM" -H 'Content-Type: application/json' -d '{"name":"X","date_of_birth":"2020-01-01"}')
[ "$(status "$r")" = "403" ] && ok "family cannot create kid" || bad "family cannot create kid" "$r"
r=$(req POST /v1/moments "$FAM" -H 'Content-Type: application/json' -d "{\"kid_id\":\"$KID\",\"moment_type\":\"story\",\"description\":\"From grandma\"}")
GSTORY=$(body "$r" | jq -r '.data.moment.id // empty'); [ -n "$GSTORY" ] && ok "family can add content" || bad "family can add content" "$r"
r=$(req DELETE "/v1/moments/$STORY" "$FAM"); [ "$(status "$r")" = "403" ] && ok "family cannot delete others' moment" || bad "family cannot delete others' moment" "$r"
r=$(req GET /v1/members "$FAM"); [ "$(status "$r")" = "403" ] && ok "members list is admin only" || bad "members list is admin only" "$r"
r=$(req GET /v1/members "$ADMIN"); check "members list" '(.data|length)>=2' "$(body "$r")"
r=$(req PATCH "/v1/members/$FAMID" "$ADMIN" -H 'Content-Type: application/json' -d '{"permissions":{"comment":true}}')
check "update member permissions" '.data.permissions.comment==true' "$(body "$r")"
r=$(req POST "/v1/moments/$PHOTO/comments" "$FAM" -H 'Content-Type: application/json' -d '{"content":"Cute"}')
[ "${r%% *}" -lt 300 ] && ok "comment allowed after update" || bad "comment allowed after update" "$r"

echo "== Sync"
NOW_MS=$(( $(date +%s) * 1000 ))
OKID=$(uuidgen | tr 'A-Z' 'a-z'); OMOM=$(uuidgen | tr 'A-Z' 'a-z')
r=$(req POST /v1/sync/push "$ADMIN" -H 'Content-Type: application/json' -d "{\"lastPulledAt\":$NOW_MS,\"changes\":{\"kids\":{\"created\":[{\"id\":\"$OKID\",\"name\":\"Offline kid\",\"date_of_birth\":1588464000000,\"due_date\":null,\"avatar_color\":\"#88aaff\"}],\"updated\":[],\"deleted\":[]},\"moments\":{\"created\":[{\"id\":\"$OMOM\",\"kid_id\":\"$OKID\",\"moment_type\":\"story\",\"title\":\"Offline\",\"description\":\"Written offline\",\"exif_date\":$NOW_MS}],\"updated\":[],\"deleted\":[]}}}")
check "push keeps ids" '.data.kids_created[0].id=="'"$OKID"'" and .data.moments_created[0].id=="'"$OMOM"'"' "$(body "$r")"
r=$(req GET "/v1/kids/$OKID" "$ADMIN"); check "pushed kid date stored as a date" '.data.date_of_birth=="2020-05-03"' "$(body "$r")"
r=$(req POST /v1/sync/pull "$ADMIN" -H 'Content-Type: application/json' -d '{"lastPulledAt":null,"schemaVersion":2,"migration":null}')
P="$(body "$r")"
check "pull shape and ms timestamp" '(.timestamp|type=="number") and .timestamp>1700000000000' "$P"
check "pull moment rows carry contract fields" '[.changes.moments.created[],.changes.moments.updated[]]|map(select(.id=="'"$PHOTO"'"))[0]|has("likes_count") and has("is_liked") and has("uploader_name") and has("video_status")' "$P"
check "pull kid has due_date" '[.changes.kids.created[],.changes.kids.updated[]]|all(has("due_date"))' "$P"
r=$(req POST /v1/sync/push "$FAM" -H 'Content-Type: application/json' -d "{\"lastPulledAt\":$NOW_MS,\"changes\":{\"kids\":{\"created\":[],\"updated\":[],\"deleted\":[\"$KID\"]},\"moments\":{\"created\":[],\"updated\":[],\"deleted\":[]}}}")
check "push of a forbidden change is rejected, not applied" '(.data.rejected|length)==1' "$(body "$r")"

echo "== Look-back"
r=$(req GET "/v1/lookback?date=$TODAY" "$ADMIN")
check "on this day finds last year's photo" '.data.on_this_day|map(.moments[].id)|index("'"$PHOTO"'")!=null' "$(body "$r")"
check "then_and_now present" '.data|has("then_and_now")' "$(body "$r")"

echo "== Export"
r=$(req POST /v1/exports "$ADMIN"); EXP=$(body "$r" | jq -r '.data.id // empty')
[ "$(status "$r")" = "202" ] && ok "export started" || bad "export started" "$r"
for _ in $(seq 1 30); do
  E=$(body "$(req GET /v1/exports "$ADMIN")" | jq -c "[.data[]|select(.id==\"$EXP\")][0]")
  s=$(echo "$E" | jq -r .status); [ "$s" = "ready" ] || [ "$s" = "failed" ] && break; sleep 2
done
[ "$s" = "ready" ] && ok "export ready" || bad "export ready" "$E"
URL=$(echo "$E" | jq -r '.download_url // empty')
if [ -n "$URL" ] && curl -s -o "$WORK/e.zip" "$URL" && unzip -l "$WORK/e.zip" > "$WORK/zl" 2>/dev/null; then
  grep -q "kinlore-export.json" "$WORK/zl" && grep -q "Smoke Mia/memories.json" "$WORK/zl" && ok "export ZIP layout" || bad "export ZIP layout" "$(head -20 "$WORK/zl")"
else bad "export download" "$URL"; fi
[ "$(curl -s -o /dev/null -w '%{http_code}' "$API/v1/exports/$EXP/download")" = "403" ] && ok "unsigned export download refused" || bad "unsigned export download refused"

echo "== Member removal keeps data"
r=$(req DELETE "/v1/members/$FAMID" "$ADMIN"); [ "${r%% *}" -lt 300 ] && ok "remove member" || bad "remove member" "$r"
r=$(req GET "/v1/moments/$GSTORY" "$ADMIN"); check "removed member's moment kept" '.data.user_id==null' "$(body "$r")"
r=$(req GET /auth/me "$FAM"); [ "$(status "$r")" = "401" ] && ok "removed member's token revoked" || bad "removed member's token revoked" "$r"

echo
echo "Passed: $PASS  Failed: $FAIL"
[ "$FAIL" -eq 0 ]
