#!/usr/bin/env bash
#
# Rename the theme slug, LOCALLY. Does not touch any live site.
#
#   digest  ->  shadow-software-digest-theme-for-wordpress
#
# WHY: `digest` is already taken in the WordPress.org theme directory. Shipping
# under it means WordPress tries to auto-update our theme with a stranger's on
# every site running it — including two live production sites.
#
# WHAT CHANGES, AND WHAT DOES NOT:
#
#   directory / slug   digest  ->  shadow-software-digest-theme-for-wordpress
#   text domain        'digest' -> 'shadow-software-digest-theme-for-wordpress'
#                      (WordPress REQUIRES the text domain to equal the dir name)
#   function prefix    digest_ ->  shadow_digest_
#   constant prefix    DIGEST_ ->  SHADOW_DIGEST_
#   block namespace    digest/ ->  shadow-digest/
#
#   The function and block prefixes deliberately do NOT match the slug. They only
#   need to be unique, and shadow_software_digest_theme_for_wordpress_get() is
#   unreadable. This mirrors shadow-software-crypto-for-woocommerce, which passed
#   .org review with slug `shadow-software-crypto-for-woocommerce` and prefix
#   `shadow_eth_`.
#
#   CSS classes (.digest-*) do NOT change. They are cosmetic, not a namespace,
#   and renaming them would churn the stylesheet for nothing.
#
#   The DISPLAY NAME stays "Digest". Only the slug moves.
#
# Migrating the LIVE sites is a separate step — see scripts/migrate-slug.php.
# Block names are stored inside post_content as `<!-- wp:digest/faq -->`, so a
# rename without that migration silently blanks every editorial block on both
# sites. Do not skip it.

set -euo pipefail
export PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

OLD_DIR=digest
NEW_DIR=shadow-software-digest-theme-for-wordpress

if [ ! -d "$OLD_DIR" ]; then
  echo "!! ./$OLD_DIR not found — already renamed?"
  exit 1
fi

echo "── 1. moving the theme directory"
git mv "$OLD_DIR" "$NEW_DIR" 2>/dev/null || mv "$OLD_DIR" "$NEW_DIR"

echo "── 2. rewriting identifiers"

# Order is load-bearing:
#   - Constants first (DIGEST_ is uppercase and unambiguous).
#   - Then the block namespace, in all the forms it appears.
#   - Then the text domain (quoted 'digest').
#   - Then function/hook names (digest_), last, because digest_ is a substring of
#     nothing else but WOULD match inside digest_render_* etc, which is what we want.
find "$NEW_DIR" -type f \( -name '*.php' -o -name '*.json' -o -name '*.js' -o -name '*.html' -o -name '*.css' -o -name '*.txt' -o -name '*.pot' \) -print0 |
while IFS= read -r -d '' f; do
  sed -i \
    -e 's/\bDIGEST_/SHADOW_DIGEST_/g' \
    -e 's|"digest/|"shadow-digest/|g' \
    -e 's|wp:digest/|wp:shadow-digest/|g' \
    -e 's|/wp:digest/|/wp:shadow-digest/|g' \
    -e "s|'digest/|'shadow-digest/|g" \
    -e "s/'digest'/'shadow-software-digest-theme-for-wordpress'/g" \
    -e 's/"digest"/"shadow-software-digest-theme-for-wordpress"/g' \
    -e 's/\bdigest_/shadow_digest_/g' \
    "$f"
done

echo "── 3. the theme headers"
# Text Domain must equal the directory name.
sed -i "s|^Text Domain: .*|Text Domain: $NEW_DIR|" "$NEW_DIR/style.css"
# The block editor category slug and the pattern category slugs.
sed -i "s|'slug'  => 'shadow-software-digest-theme-for-wordpress',|'slug'  => 'shadow-digest',|" "$NEW_DIR/inc/blocks.php" 2>/dev/null || true

echo "── 4. phpcs config"
sed -i \
  -e "s|<file>digest</file>|<file>$NEW_DIR</file>|" \
  -e "s|<config name=\"text_domain\" value=\"digest\"/>|<config name=\"text_domain\" value=\"$NEW_DIR\"/>|" \
  -e "s|<element value=\"digest\"/>|<element value=\"$NEW_DIR\"/>|" \
  -e "s|<element value=\"digest\"/>|<element value=\"shadow_digest\"/>|" \
  -e "s|<element value=\"DIGEST\"/>|<element value=\"SHADOW_DIGEST\"/>|" \
  phpcs.xml.dist 2>/dev/null || true

echo "── 5. scripts that reference the old path"
sed -i "s|/digest\b|/$NEW_DIR|g; s|\"\$ROOT/digest|\"\$ROOT/$NEW_DIR|g; s|themes/digest|themes/$NEW_DIR|g" \
  scripts/local-wp.sh scripts/deploy.sh scripts/local-smoke.sh 2>/dev/null || true

echo
echo "── 6. lint"
fail=0
while IFS= read -r f; do php -l "$f" >/dev/null 2>&1 || { echo "   PHP FAIL: $f"; fail=1; }; done < <(find "$NEW_DIR" -name '*.php')
while IFS= read -r f; do python3 -c "import json,sys;json.load(open(sys.argv[1]))" "$f" 2>/dev/null || { echo "   JSON FAIL: $f"; fail=1; }; done < <(find "$NEW_DIR" -name '*.json')
node --check "$NEW_DIR/assets/js/blocks.js" 2>/dev/null || { echo "   JS FAIL"; fail=1; }
[ "$fail" -eq 0 ] && echo "   ✓ clean" || { echo "   ✗ LINT FAILED"; exit 1; }

echo
echo "── 7. sanity: no stragglers"
echo "   bare 'digest_' functions left:  $(grep -rho '\bdigest_[a-z_]*' "$NEW_DIR" --include='*.php' | grep -v shadow_digest | sort -u | wc -l)"
echo "   bare DIGEST_ constants left:    $(grep -rho '\bDIGEST_[A-Z_]*' "$NEW_DIR" --include='*.php' | grep -v SHADOW_DIGEST | sort -u | wc -l)"
echo "   bare 'digest' text domain left: $(grep -rc "'digest'" "$NEW_DIR" --include='*.php' 2>/dev/null | awk -F: '{s+=$2} END {print s+0}')"
echo "   wp:digest/ left in templates:   $(grep -rho 'wp:digest/' "$NEW_DIR" --include='*.html' | wc -l)"

echo
echo "── done, locally. The live sites still run the OLD slug and are UNTOUCHED."
echo "   Next: ./scripts/local-wp.sh reset && ./scripts/local-smoke.sh && php scripts/local-assert.php"
echo "   Then migrate the live sites with scripts/migrate-slug.php — block names are"
echo "   stored in post_content, so they must be rewritten or every block goes blank."
