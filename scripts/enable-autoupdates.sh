#!/usr/bin/env bash
#
# Enable automatic updates on both Digest sites.
#
# ---------------------------------------------------------------------------
# WHY THE THEME IS DELIBERATELY EXCLUDED
#
# The Digest theme is NOT in the WordPress.org directory. It is deployed from this
# repo via scripts/deploy.sh, which gates it behind a local sandbox and 36
# correctness assertions.
#
# Auto-updating it would mean one of two bad things:
#
#   1. WordPress finds NOTHING at that slug on .org and does nothing — harmless
#      but pointless.
#   2. Someone later publishes a theme with our slug, and WordPress cheerfully
#      overwrites our production theme with a stranger's code.
#
# (2) is not hypothetical. Before the rename, the theme's slug was `digest` —
# which IS taken on .org — and `wp theme list` showed "update available → 1.0.4"
# on both live sites. Had auto-updates been on, WordPress would have replaced the
# theme with someone else's, on two production news sites, without asking.
#
# So: everything else auto-updates. The theme is updated by deploy.sh, which
# actually tests it first. That is the correct arrangement.
# ---------------------------------------------------------------------------
#
# Core: minor + security updates only. A major WordPress release can break a
# theme, and this box has a marketplace on it. Minors are security fixes and are
# safe; majors are a decision, taken deliberately, with the sandbox to test in.

set -euo pipefail
export PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin

KEY=/home/shadow/Source/000-creds/1984_ssh_key
HOST=root@93.95.229.147
SSH="ssh -i $KEY -o BatchMode=yes -o ConnectTimeout=15"

THEME=pressroom

for SITE in cannabisdigest.net marksmansdigest.com; do
  echo "════════ $SITE"

  $SSH "$HOST" "cd /var/www/$SITE/public && {

    # ---- Plugins: auto-update everything. -----------------------------------
    # These are all from .org and all maintained. An unpatched plugin is the most
    # common way a WordPress site gets compromised, and nothing here is so
    # bespoke that a point release will break it.
    wp plugin auto-updates enable --all --allow-root

    # ---- Themes: the STOCK ones only. ---------------------------------------
    # twentytwentyfive is the rollback target used by deploy.sh, so it must stay
    # current and working. The Digest theme is excluded — see the note at the top
    # of this script.
    for t in twentytwentyfive twentytwentyfour twentytwentythree; do
      wp theme auto-updates enable \$t --allow-root 2>/dev/null || true
    done
    wp theme auto-updates disable $THEME --allow-root 2>/dev/null || true

    # ---- Core: minor and security releases, automatically. -------------------
    # WP_AUTO_UPDATE_CORE = 'minor' is WordPress's own default and its safest
    # setting: 7.0.1 -> 7.0.2 happens on its own; 7.0 -> 8.0 does not.
    wp config set WP_AUTO_UPDATE_CORE minor --allow-root

    # Belt and braces: the constant above can be overridden by a filter, so set
    # the options too.
    wp option update auto_update_core_dev 0 --allow-root
    wp option update auto_update_core_major 0 --allow-root
    wp option update auto_update_core_minor 1 --allow-root

    # ---- Let WordPress actually run the updater. -----------------------------
    # DISALLOW_FILE_MODS would block every auto-update silently.
    wp config delete DISALLOW_FILE_MODS --allow-root 2>/dev/null || true
    wp config delete AUTOMATIC_UPDATER_DISABLED --allow-root 2>/dev/null || true

  } 2>&1" | grep -viE '^Deprecated|^PHP Deprecated|Colors\.php|react/promise|^$' | sed 's/^/  /'

  echo
done

echo "════════ VERIFY"
for SITE in cannabisdigest.net marksmansdigest.com; do
  echo "── $SITE"
  $SSH "$HOST" "cd /var/www/$SITE/public && {
    echo '   plugins:'
    wp plugin list --field=name --status=active --allow-root 2>/dev/null | while read p; do
      printf '     %-26s %s\n' \"\$p\" \"\$(wp plugin auto-updates status \$p --allow-root 2>/dev/null || echo '?')\"
    done
    echo '   themes:'
    wp theme list --fields=name,auto_update --allow-root 2>/dev/null
    echo -n '   core auto-update policy: '
    wp config get WP_AUTO_UPDATE_CORE --allow-root 2>/dev/null
  } 2>&1" | grep -viE '^Deprecated|^PHP Deprecated|Colors\.php|react/promise' | sed 's/^/ /'
  echo
done
