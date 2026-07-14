#!/usr/bin/env bash
#
# Migrate ONE live site from the `digest` slug to
# `pressroom`.
#
# ORDER IS LOAD-BEARING. Do not reorder these steps:
#
#   1. Upload the new theme directory (but do NOT activate it).
#   2. Migrate the data — theme_mods and the block names inside post_content —
#      while the OLD theme is still the active one.
#   3. Activate the new theme.
#   4. Verify. Roll back to the old theme on any failure.
#
# Why: a block's name is stored inside post_content as `<!-- wp:digest/faq -->`.
# Activate the renamed theme before rewriting those, and WordPress no longer
# recognises the block. It does not error — it renders NOTHING. Every FAQ, table
# of contents, short answer and sources list on the site silently disappears.
#
# The old theme directory is left in place until the new one is verified, so the
# rollback is always one wp-cli call away.

set -euo pipefail
export PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
KEY=/home/shadow/Source/000-creds/1984_ssh_key
HOST=root@93.95.229.147
SSH="ssh -i $KEY -o BatchMode=yes -o ConnectTimeout=15"

NEW=pressroom
OLD=digest

SITE="${1:-}"
case "$SITE" in
  cannabisdigest.net|marksmansdigest.com) ;;
  *) echo "usage: $0 <cannabisdigest.net|marksmansdigest.com>"; exit 1 ;;
esac

red() { printf '\033[31m%s\033[0m\n' "$*"; }

# wp-cli on PHP 8.5 floods stderr with its own deprecation notices, which drowns
# real output and can make a successful command look like a silent failure.
wp_quiet() {
  $SSH "$HOST" "cd /var/www/$SITE/public && wp $* --allow-root 2>&1" \
    | grep -viE '^Deprecated|^PHP Deprecated|Colors\.php|react/promise' || true
}

echo "════ PRE-FLIGHT — the gate still applies"
"$ROOT/scripts/local-smoke.sh" >/dev/null 2>&1 || { red "✗ smoke test failed"; exit 1; }
php "$ROOT/scripts/local-assert.php" >/dev/null 2>&1 || { red "✗ assertions failed"; exit 1; }
echo "   ✓ local sandbox green"

echo
echo "════ 1/4 — uploading the new theme (NOT activating yet)"
rsync -az --delete -e "$SSH" "$ROOT/$NEW/" "$HOST:/var/www/$SITE/public/wp-content/themes/$NEW/"
$SSH "$HOST" "chown -R nobody:nogroup /var/www/$SITE/public/wp-content/themes/$NEW"
echo "   ✓ uploaded"

echo
echo "════ 2/4 — migrating data while the OLD theme is still active"
scp -q -i "$KEY" "$ROOT/scripts/migrate-slug.php" "$HOST:/tmp/migrate-slug.php"
$SSH "$HOST" "cd /var/www/$SITE/public && wp eval-file /tmp/migrate-slug.php --allow-root 2>&1" \
  | grep -viE '^Deprecated|^PHP Deprecated|Colors\.php|react/promise|^$' | sed 's/^/   /'

echo
echo "════ 3/4 — activating $NEW"
wp_quiet "theme activate $NEW" | sed 's/^/   /'

echo
echo "════ 4/4 — verifying"
ARTICLE=$($SSH "$HOST" "cd /var/www/$SITE/public && wp post list --post_type=post --posts_per_page=1 --field=url --allow-root 2>/dev/null" | head -1)

ok=0
for pair in "https://$SITE/|front" "${ARTICLE}|ARTICLE"; do
  url="${pair%%|*}"; label="${pair##*|}"
  [ -z "$url" ] && continue
  code=$(curl -s -o /tmp/rn.html -w "%{http_code}" --max-time 25 "$url" || echo 000)
  printf "   %-8s %s  " "$label" "$code"
  if [ "$code" != "200" ]; then red "✗"; ok=1; continue; fi

  # The blocks must still be there. This is the whole point of the migration.
  if [ "$label" = "ARTICLE" ]; then
    n=$(grep -oc 'wp-block-digest-' /tmp/rn.html 2>/dev/null || echo 0)
    if [ "$n" -lt 3 ]; then
      red "✗ only $n digest blocks rendered — the content migration FAILED"
      ok=1
      continue
    fi
    printf "%s blocks  " "$n"
  fi
  echo "✓"
done

if [ "$ok" -ne 0 ]; then
  echo
  red "════ ROLLING BACK to the old theme"
  wp_quiet "theme activate $OLD"
  red "   $SITE reverted to '$OLD'. Data migration is reversible via the"
  red "   _digest_premigration_content post meta."
  exit 1
fi

echo
echo "════ ✓ $SITE migrated to $NEW and verified."
