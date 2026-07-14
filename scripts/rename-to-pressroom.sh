#!/usr/bin/env bash
#
# Rename the theme:  Digest  ->  Pressroom.   LOCAL ONLY; touches no live site.
#
# WHY: the WordPress.org Theme Review team rejected the submission —
#
#     "There is already a theme called digest by a different author. Please
#      change the name of your theme in style.css and upload it again."
#
# and they are right: https://wordpress.org/themes/digest/ exists. Shipping under
# a name someone else owns is not merely a review failure, it is dangerous —
# WordPress matches update payloads by SLUG, so a site running our theme could be
# auto-updated to a stranger's, silently. That includes two live production sites.
#
# The reviewer said "change the name in style.css", but the fix is bigger than
# that line, because the handbook ties three things together:
#
#   * "Use the theme slug as the text-domain and add it to style.css."
#   * "The theme slug is the name of the theme in lower case, with spaces
#      replaced by a hyphen. It is also the folder name for the theme."
#   * Theme names "must not use: WordPress, Theme, Twenty*"
#
# The old slug `shadow-software-digest-theme-for-wordpress` therefore could not
# survive either: it contains BOTH "theme" and "wordpress". Changing only the
# display name would have earned a second rejection on the naming rule.
#
#     https://make.wordpress.org/themes/handbook/review/required/
#
# WHAT CHANGES
#
#   directory / slug   shadow-software-digest-theme-for-wordpress -> pressroom
#   text domain        (same string)                              -> pressroom
#   Theme Name         Digest                                     -> Pressroom
#   block namespace    shadow-digest/                             -> pressroom/
#
# WHAT DOES NOT CHANGE, AND WHY
#
#   function prefix    shadow_digest_    (325 refs)
#   constant prefix    SHADOW_DIGEST_    (16 refs)
#     A prefix only has to be UNIQUE; it does not have to equal the slug, and the
#     handbook does not ask it to. shadow-software-crypto-for-woocommerce passed
#     .org review with prefix `shadow_eth_`. Rewriting 341 identifiers buys zero
#     review benefit and is pure risk.
#
#   CSS classes        .digest-*         (830 refs)
#     Cosmetic, not a namespace. Renaming them churns the entire stylesheet — and
#     every template that references them — to change nothing a reviewer looks at.
#
# ⚠ THE TRAP: block names are stored INSIDE post_content, as literal text:
#
#       <!-- wp:shadow-digest/faq -->
#
#   Renaming the namespace without migrating that content makes every editorial
#   block on a live site render NOTHING — silently, with no error, no fatal, and
#   a 200 response. See scripts/migrate-slug.php, and CLAUDE.md. Theme mods are
#   likewise keyed by slug (theme_mods_<slug>), so a rename orphans every
#   Customizer setting and the site falls back to defaults — which are Marksman's.
#   See scripts/migrate-modkeys.php. DO NOT deploy this rename without both.
#
# Usage:  ./scripts/rename-to-pressroom.sh          (idempotent; safe to re-run)

set -euo pipefail
export PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

OLD_DIR="shadow-software-digest-theme-for-wordpress"
NEW_DIR="pressroom"
OLD_NS="shadow-digest"
NEW_NS="pressroom"

if [ ! -d "$OLD_DIR" ]; then
  if [ -d "$NEW_DIR" ]; then
    echo "── already renamed: ./$NEW_DIR exists. Nothing to do."
    exit 0
  fi
  echo "!! neither ./$OLD_DIR nor ./$NEW_DIR found."
  exit 1
fi

echo "════ Digest -> Pressroom"
echo

echo "── 1. moving the theme directory"
git mv "$OLD_DIR" "$NEW_DIR" 2>/dev/null || mv "$OLD_DIR" "$NEW_DIR"
echo "   ✓ $OLD_DIR/ -> $NEW_DIR/"

echo
echo "── 2. rewriting identifiers inside the theme"

# Only real source files. Never the fonts, never the screenshot, never a binary.
mapfile -t FILES < <(find "$NEW_DIR" -type f \
  \( -name '*.php' -o -name '*.css' -o -name '*.json' -o -name '*.html' \
     -o -name '*.js' -o -name '*.txt' -o -name '*.pot' \) )

# Order is load-bearing. The text domain is the LONG string and contains no other
# token we rewrite, so it goes first and cannot be corrupted by a later pass.
# The block namespace is rewritten only in its `shadow-digest/` form — bare
# "shadow-digest" without a slash is not a thing, and .digest-* CSS classes must
# survive untouched.
for f in "${FILES[@]}"; do
  sed -i \
    -e "s|${OLD_DIR}|${NEW_DIR}|g" \
    -e "s|${OLD_NS}/|${NEW_NS}/|g" \
    "$f"
done
echo "   ✓ text domain  '${OLD_DIR}' -> '${NEW_DIR}'"
echo "   ✓ block namespace  ${OLD_NS}/  ->  ${NEW_NS}/"

echo
echo "── 3. the display name"
# Theme Name in style.css, and the two user-facing strings that print it.
sed -i 's|^Theme Name: Digest$|Theme Name: Pressroom|' "$NEW_DIR/style.css"
sed -i "s|esc_html_e( 'Digest', |esc_html_e( 'Pressroom', |g" "$NEW_DIR/inc/blocks-masthead.php"
sed -i "s|__( 'Digest', |__( 'Pressroom', |g"                 "$NEW_DIR/inc/customizer.php"
echo "   ✓ Theme Name: Pressroom"

echo
echo "── 4. paths that referenced the old directory, OUTSIDE the theme"
for f in scripts/*.sh scripts/*.php .github/workflows/*.yml phpcs.xml.dist .distignore README.md CLAUDE.md; do
  [ -f "$f" ] || continue
  # Skip THIS script — it must keep the old name in its own documentation.
  [ "$(basename "$f")" = "rename-to-pressroom.sh" ] && continue
  if grep -q "$OLD_DIR" "$f" 2>/dev/null; then
    sed -i "s|${OLD_DIR}|${NEW_DIR}|g" "$f"
    echo "   ✓ $f"
  fi
done

echo
echo "── 5. what deliberately did NOT change"
echo "   · function prefix  shadow_digest_   ($(grep -ro 'shadow_digest_' "$NEW_DIR" | wc -l) refs)  — unique is enough"
echo "   · constant prefix  SHADOW_DIGEST_   ($(grep -ro 'SHADOW_DIGEST_' "$NEW_DIR" | wc -l) refs)"
echo "   · CSS classes      .digest-*        ($(grep -ro 'digest-' "$NEW_DIR" | wc -l) refs)  — cosmetic"

echo
echo "════ renamed."
echo
echo "   Verify:  ./scripts/local-wp.sh reset && ./scripts/local-smoke.sh && php scripts/local-assert.php"
echo
echo "   ⚠ The LIVE sites are NOT migrated. Block names live inside post_content"
echo "     (<!-- wp:${OLD_NS}/faq -->) and theme mods are keyed by slug. Deploying"
echo "     this without scripts/migrate-slug.php and scripts/migrate-modkeys.php"
echo "     silently blanks every editorial block and resets every Customizer setting."
