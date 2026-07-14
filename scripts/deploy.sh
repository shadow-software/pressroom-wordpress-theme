#!/usr/bin/env bash
#
# Deploy the Digest theme to ONE site, safely.
#
# This script exists because the previous deploy procedure was `rsync`, run by
# hand, straight to two live sites, with a theme that had never been executed
# anywhere. It contained an infinite recursion and it took down a shared VPS —
# and with it a live firearms marketplace that had nothing to do with this theme.
# See docs/INCIDENT-2026-07-13-vps-outage.md.
#
# So this is not a convenience wrapper. It is a gate. It refuses to deploy unless:
#
#   1. The theme lints (PHP + JSON).
#   2. There is no live do_blocks() call in a render callback — the exact shape of
#      the bug that caused the outage.
#   3. The local sandbox is running.
#   4. local-smoke.sh passes    — every template renders, fast, no fatals.
#   5. local-assert.php passes  — the pages are CORRECT, not merely alive.
#      (Liveness alone is not enough: the re-entrancy guard makes the recursion
#      render fast-but-wrong instead of hanging, so a smoke test cannot see it.)
#
# It deploys to ONE site. Deploying to two sites at once is how one bad theme
# becomes two broken sites, and the incident is why that is no longer possible in
# a single command.
#
# After uploading it verifies the live site — including an article page, which is
# the page class that broke last time and which was never checked before.
#
# Usage:
#   ./scripts/deploy.sh cannabisdigest.net
#   ./scripts/deploy.sh marksmansdigest.com
#   ./scripts/deploy.sh <site> --rollback     # revert that site to the stock theme

set -euo pipefail

export PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
KEY=/home/shadow/Source/000-creds/1984_ssh_key
HOST=root@93.95.229.147
SSH="ssh -i $KEY -o BatchMode=yes -o ConnectTimeout=15"

SITE="${1:-}"
MODE="${2:-deploy}"

if [ -z "$SITE" ]; then
  echo "usage: $0 <cannabisdigest.net|marksmansdigest.com> [--rollback]"
  exit 1
fi

case "$SITE" in
  cannabisdigest.net|marksmansdigest.com) ;;
  *) echo "!! unknown site: $SITE"; exit 1 ;;
esac

red()   { printf '\033[31m%s\033[0m\n' "$*"; }
green() { printf '\032[32m%s\033[0m\n' "$*" 2>/dev/null || printf '%s\n' "$*"; }

# ---------------------------------------------------------------- rollback ----

if [ "$MODE" = "--rollback" ]; then
  echo "── ROLLBACK: switching $SITE to the stock theme"
  $SSH "$HOST" "cd /var/www/$SITE/public && wp theme activate twentytwentyfive --allow-root"
  echo "── verifying"
  curl -s -o /dev/null -w "   %{http_code} in %{time_total}s\n" --max-time 20 "https://$SITE/"
  echo "── rolled back."
  exit 0
fi

# ------------------------------------------------------------------- gates ----

echo "════ GATE 1/5 — lint"
fail=0
while IFS= read -r f; do
  php -l "$f" >/dev/null 2>&1 || { red "   PHP syntax error: $f"; fail=1; }
done < <(find "$ROOT/pressroom" -name '*.php')
while IFS= read -r f; do
  python3 -c "import json,sys;json.load(open(sys.argv[1]))" "$f" 2>/dev/null || { red "   bad JSON: $f"; fail=1; }
done < <(find "$ROOT/pressroom" -name '*.json')
[ "$fail" -eq 0 ] || { red "✗ lint failed — not deploying"; exit 1; }
echo "   ✓ clean"

echo
echo "════ GATE 2/5 — nothing renders post content (THE outage bug)"
# This was a grep. The grep was wrong — see the header of the guard script — and
# it could not reliably tell a live do_blocks() call from a comment about one.
# It now tokenizes the PHP, and CI runs the identical script, so the gate that
# blesses a deploy and the gate that blesses a commit cannot drift apart.
if ! php "$ROOT/scripts/guard-no-content-render.php" "$ROOT/pressroom"; then
  red "✗ Not deploying. Read docs/INCIDENT-2026-07-13-vps-outage.md."
  exit 1
fi

echo
echo "════ GATE 3/5 — local sandbox is up"
if ! curl -sf -o /dev/null --max-time 5 http://localhost:8080/ 2>/dev/null; then
  red "✗ the local sandbox is not running."
  echo "  Start it:  ./scripts/local-wp.sh up"
  echo "  Nothing deploys that has not rendered locally first. That rule is the"
  echo "  entire lesson of the incident."
  exit 1
fi
echo "   ✓ running on :8080"

echo
echo "════ GATE 4/5 — every template renders (liveness)"
"$ROOT/scripts/local-smoke.sh" >/tmp/pressroom-smoke.log 2>&1 || {
  red "✗ smoke test failed:"
  tail -20 /tmp/pressroom-smoke.log
  exit 1
}
echo "   ✓ all templates render"

echo
echo "════ GATE 5/5 — the pages are CORRECT (not merely alive)"
php "$ROOT/scripts/local-assert.php" >/tmp/pressroom-assert.log 2>&1 || {
  red "✗ correctness assertions failed:"
  grep -E "✗|FAILED" /tmp/pressroom-assert.log | head -10
  echo
  red "  A page can return 200 in 100ms and still be wrong. The re-entrancy"
  red "  guard makes the recursion render fast-but-wrong rather than hang, which"
  red "  is precisely why liveness checks alone cannot be trusted here."
  exit 1
}
echo "   ✓ $(grep -oE '[0-9]+ assertions passed' /tmp/pressroom-assert.log)"

# ------------------------------------------------------------------ deploy ----

echo
echo "════ DEPLOYING to $SITE (one site only)"
rsync -az --delete -e "$SSH" "$ROOT/pressroom/" "$HOST:/var/www/$SITE/public/wp-content/themes/pressroom/"
$SSH "$HOST" "chown -R nobody:nogroup /var/www/$SITE/public/wp-content/themes/pressroom"
echo "   ✓ uploaded"

$SSH "$HOST" "cd /var/www/$SITE/public && wp theme activate pressroom --allow-root" >/dev/null
echo "   ✓ activated"

# ------------------------------------------------------------------ verify ----

echo
echo "════ VERIFYING the live site"

# The article page is the one that broke last time and the one that was never
# checked. It is checked first, and with a hard timeout: if it hangs, we know
# within seconds and we roll back rather than leaving it to eat the box.
ARTICLE=$($SSH "$HOST" "cd /var/www/$SITE/public && wp post list --post_type=post --posts_per_page=1 --field=url --allow-root 2>/dev/null" | head -1)

probe() {
  local url="$1" label="$2"
  local start end ms code
  start=$(date +%s%N)
  code=$(curl -s -o /tmp/pressroom-live.html -w "%{http_code}" --max-time 20 "$url" 2>/dev/null || echo 000)
  end=$(date +%s%N)
  ms=$(( (end - start) / 1000000 ))

  printf "   %-14s %s  %5dms  " "$label" "$code" "$ms"

  if [ "$ms" -gt 10000 ] || [ "$code" = "000" ] || [ "$code" -ge 500 ]; then
    red "✗ FAILING"
    return 1
  fi
  echo "✓"
  return 0
}

ok=0
probe "https://$SITE/" "front page" || ok=1
[ -n "$ARTICLE" ] && { probe "$ARTICLE" "ARTICLE" || ok=1; }

if [ "$ok" -ne 0 ]; then
  echo
  red "════ LIVE SITE IS UNHEALTHY — ROLLING BACK IMMEDIATELY"
  $SSH "$HOST" "cd /var/www/$SITE/public && wp theme activate twentytwentyfive --allow-root" >/dev/null
  red "   reverted $SITE to the stock theme."
  red "   Do NOT retry the request. Read the code."
  exit 1
fi

echo
echo "════ ✓ $SITE deployed and healthy."
echo "   The other site is untouched. Deploy it separately, once you trust this one."
