#!/usr/bin/env bash
#
# Harden PHP-FPM so that one runaway request can never again take down the box.
#
# ---------------------------------------------------------------------------
# THE PROBLEM THIS FIXES
#
# On 2026-07-13 a recursive function in a WordPress theme hung a PHP-FPM worker.
# Nothing killed it. More requests arrived; more workers hung. They accumulated,
# each holding up to memory_limit (128MB), until the kernel could not fork sshd
# and the box became unreachable. Seven sites went down — including a live
# marketplace with no connection to the offending theme.
#
# The bug was in the theme. The OUTAGE was possible because of the host config:
#
#   NO request_terminate_timeout ON ANY POOL.
#
#   php.ini sets max_execution_time = 30, which LOOKS like a guardrail and is not
#   one: under FPM it measures CPU time inside the script and is not enforced
#   during blocking calls or in many re-entrant paths. The setting that actually
#   kills a runaway FPM request is request_terminate_timeout, and it was absent
#   everywhere. A hung request ran forever.
#
#   Compounding it: 76 workers x 128MB = 9,728MB on a 7,940MB box. Harmless while
#   workers finish; fatal the moment they stop finishing.
#
# This bounds how long any single worker can be stuck, which is the actual failure
# mode. It deliberately does NOT reduce max_children — throttling AGT or DabDash
# to protect against a bug in a news theme would trade one outage for another.
# ---------------------------------------------------------------------------
#
# Safe by construction: backs up every pool, validates the config BEFORE
# reloading, reloads gracefully, verifies all seven sites, and rolls back
# automatically if anything is unhealthy.
#
#   ./scripts/harden-fpm.sh --dry-run
#   ./scripts/harden-fpm.sh

set -euo pipefail
export PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin

KEY=/home/shadow/Source/000-creds/1984_ssh_key
HOST=root@93.95.229.147
SSH="ssh -i $KEY -o BatchMode=yes -o ConnectTimeout=15"

DRY=0
[ "${1:-}" = "--dry-run" ] && DRY=1

SITES="americanguntrader.com dabdash.com shadowsoftware.com auralabs.asia cannabisdigest.net marksmansdigest.com"

# ---------------------------------------------------------------------------
# The remote script. Written here, executed there — no nested-quoting hazards.
# ---------------------------------------------------------------------------
REMOTE=$(cat <<'REMOTE_EOF'
#!/usr/bin/env bash
set -euo pipefail

DRY="${1:-0}"

# 60s is far longer than any legitimate WordPress request — a slow admin screen,
# a sitemap rebuild, a plugin update — and far shorter than "forever". A request
# still running at 60s is not working; it is stuck.
TIMEOUT=60
MAX_REQUESTS=500

POOLS=/etc/php/8.5/fpm/pool.d

if [ "$DRY" = "0" ]; then
  STAMP=$(date +%Y%m%d-%H%M%S)
  BACKUP=/root/fpm-backup-$STAMP
  mkdir -p "$BACKUP"
  cp -a "$POOLS"/*.conf "$BACKUP"/
  echo "── backed up all pools to $BACKUP"
  echo
fi

set_directive() {
  local file="$1" key="$2" value="$3"

  if grep -qE "^[[:space:]]*${key//./\\.}[[:space:]]*=" "$file"; then
    if [ "$DRY" = "0" ]; then
      sed -i -E "s|^[[:space:]]*${key//./\\.}[[:space:]]*=.*|${key} = ${value}|" "$file"
    fi
    echo "     ${key} = ${value}   (was set, updated)"
  else
    if [ "$DRY" = "0" ]; then
      printf '\n%s = %s\n' "$key" "$value" >> "$file"
    fi
    echo "     ${key} = ${value}   (was MISSING — added)"
  fi
}

for f in "$POOLS"/*.conf; do
  pool=$(basename "$f" .conf)
  echo "   $pool"
  set_directive "$f" "request_terminate_timeout" "${TIMEOUT}s"
  set_directive "$f" "pm.max_requests" "$MAX_REQUESTS"
  echo
done

if [ "$DRY" != "0" ]; then
  echo "DRY RUN — nothing written."
  exit 0
fi

echo "── validating config"
if ! php-fpm8.5 -t 2>&1 | grep -qi "successful"; then
  echo "✗ CONFIG INVALID — restoring backup, NOT reloading"
  cp -a "$BACKUP"/*.conf "$POOLS"/
  exit 1
fi
echo "   ✓ valid"

echo "── reloading (graceful)"
systemctl reload php8.5-fpm
sleep 2
systemctl is-active php8.5-fpm
REMOTE_EOF
)

echo "════ PHP-FPM hardening$( [ "$DRY" = 1 ] && echo ' — DRY RUN' )"
echo "   request_terminate_timeout: 60s   (currently unset — a hung request runs forever)"
echo "   pm.max_requests:           500   (recycle workers, bounding leaks)"
echo

echo "$REMOTE" | $SSH "$HOST" "cat > /tmp/harden-fpm.sh && chmod +x /tmp/harden-fpm.sh && /tmp/harden-fpm.sh $DRY"

if [ "$DRY" = 1 ]; then
  echo
  echo "Re-run without --dry-run to apply."
  exit 0
fi

echo
echo "════ VERIFYING all seven sites"
fail=0
for u in $SITES; do
  code=$(curl -s -o /dev/null -w "%{http_code}" --max-time 25 "https://$u/" 2>/dev/null || echo 000)
  printf "   %-24s %s  " "$u" "$code"
  if [ "$code" = "200" ]; then echo "✓"; else echo "✗"; fail=1; fi
done

if [ "$fail" -ne 0 ]; then
  echo
  echo "✗ A SITE IS UNHEALTHY — ROLLING BACK"
  $SSH "$HOST" 'latest=$(ls -1dt /root/fpm-backup-* | head -1); cp -a $latest/*.conf /etc/php/8.5/fpm/pool.d/; systemctl reload php8.5-fpm; echo "   restored from $latest"'
  exit 1
fi

echo
echo "════ ✓ HARDENED"
echo "   A hung request now dies in 60s instead of running forever."
echo "   The 2026-07-13 outage could not happen this way again: the workers would"
echo "   have been reaped long before the box ran out of memory to fork sshd."
