#!/usr/bin/env bash
#
# Build the .zip that gets uploaded to the WordPress.org Theme Directory — and
# refuse to build one that would be rejected.
#
# This exists for the same reason deploy.sh exists. The last time something left
# this repo for a live server it went by hand, and it took a production box down.
# A theme submission is lower stakes than a deploy, but it is the same failure
# shape: a human assembling an artifact by hand, from memory, and shipping it
# somewhere they cannot easily take it back from. A rejected submission costs a
# week of queue time, and the reviewer tells you one problem at a time.
#
# So the zip is built, then INSPECTED — the checks below are the ones the Theme
# Review team actually runs, applied to the exact bytes that would be uploaded,
# not to the working tree they were copied from.
#
# Usage:
#   ./scripts/package.sh              build + verify → build/<slug>-<version>.zip
#   ./scripts/package.sh --check      verify only, build nothing (CI uses this)

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SLUG="shadow-software-digest-theme-for-wordpress"
SRC="$ROOT/$SLUG"
OUT="$ROOT/build"

red()  { printf '\033[31m%s\033[0m\n' "$*"; }
grn()  { printf '\033[32m%s\033[0m\n' "$*"; }

CHECK_ONLY=0
[ "${1:-}" = "--check" ] && CHECK_ONLY=1

VERSION=$(grep -oP '^Version:\s*\K[0-9.]+' "$SRC/style.css")
[ -n "$VERSION" ] || { red "cannot read Version from style.css"; exit 1; }

echo "════ packaging $SLUG $VERSION"

# ---------------------------------------------------------------- staging ----
#
# Copy to a staging dir and delete what must not ship, rather than trusting a
# .distignore that only some tools read. The Theme Directory rejects a package
# carrying build config, tests or dev dotfiles, and the two dotfiles that used to
# live INSIDE the theme directory (.distignore, .stylelintrc.json) were shipping
# in every hand-made zip, because .distignore's own rules are anchored to the repo
# root and could never match them.

STAGE="$(mktemp -d)"
trap 'rm -rf "$STAGE"' EXIT
DIR="$STAGE/$SLUG"

cp -r "$SRC" "$DIR"

# Dev tooling and repo meta. None of this runs on a user's site.
find "$DIR" \( \
	-name '.distignore'      -o -name '.stylelintrc.json' -o \
	-name '.editorconfig'    -o -name '.gitignore'        -o \
	-name '.gitattributes'   -o -name 'composer.json'     -o \
	-name 'composer.lock'    -o -name 'package.json'      -o \
	-name 'package-lock.json' -o -name 'phpcs.xml.dist'   -o \
	-name 'phpunit.xml.dist' -o -name '.DS_Store'         -o \
	-name 'Thumbs.db'        -o -name '*.log'             -o \
	-name '*.zip'            -o -name '*.map' \
	\) -delete

find "$DIR" \( -name 'node_modules' -o -name 'vendor' -o -name '.git' \
	-o -name 'tests' -o -name '.phpunit.cache' \) -type d -prune -exec rm -rf {} +

# ------------------------------------------------------------------ checks ----
#
# Applied to the STAGED tree — the actual bytes that would be uploaded.

fail=0
note() { red "   ✗ $*"; fail=1; }
okay() { echo "   ✓ $*"; }

echo
echo "── required files"
for f in style.css index.php readme.txt screenshot.png LICENSE theme.json; do
	[ -f "$DIR/$f" ] && okay "$f" || note "missing required file: $f"
done

echo
echo "── no dev tooling survived into the package"
strays=$(find "$DIR" -name '.*' -not -name '.' -not -path "$DIR" 2>/dev/null || true)
if [ -n "$strays" ]; then
	while IFS= read -r s; do note "dotfile in package: ${s#"$DIR"/}"; done <<< "$strays"
else
	okay "no dotfiles"
fi

for bad in node_modules vendor composer.json package.json phpcs.xml.dist tests; do
	[ -e "$DIR/$bad" ] && note "dev artefact in package: $bad" || true
done
okay "no build config, no dependencies, no tests"

echo
echo "── no minified or third-party code (the Directory requires readable source)"
if find "$DIR" \( -name '*.min.js' -o -name '*.min.css' \) | grep -q .; then
	note "minified assets present"
else
	okay "unminified source only"
fi

echo
echo "── version is in lockstep"
v_style=$(grep -oP '^Version:\s*\K[0-9.]+'        "$DIR/style.css")
v_func=$(grep -oP  "DIGEST_VERSION', '\K[0-9.]+"  "$DIR/functions.php")
v_read=$(grep -oP  '^Stable tag:\s*\K[0-9.]+'     "$DIR/readme.txt")
if [ "$v_style" = "$v_func" ] && [ "$v_style" = "$v_read" ]; then
	okay "style.css = functions.php = readme.txt = $v_style"
else
	note "version mismatch: style.css=$v_style functions.php=$v_func readme.txt=$v_read"
fi

echo
echo "── screenshot is exactly 1200x900"
dims=$(file "$DIR/screenshot.png" | grep -oE '[0-9]+ x [0-9]+' | head -1)
[ "$dims" = "1200 x 900" ] && okay "screenshot.png $dims" || note "screenshot.png must be 1200x900, got $dims"

echo
echo "── licence declared"
# "GNU General Public License v2 or later" and "GPLv2 or later" are both valid and
# both common. Matching only the literal "GPL" rejected the former — which is what
# this theme actually says. A check that fails on correct input is how you end up
# editing correct code to satisfy a broken test.
grep -qiE 'License:.*(GPL|GNU General Public License)' "$DIR/style.css" \
	&& okay "style.css declares GPL" || note "style.css must declare a GPL licence"
grep -q '^License: *GPLv2'  "$DIR/readme.txt" && okay "readme.txt declares GPLv2" || note "readme.txt must declare GPLv2"
grep -q 'SIL Open Font'     "$DIR/readme.txt" && okay "bundled fonts attributed"  || note "readme.txt must attribute the bundled OFL fonts"
[ -f "$DIR/assets/fonts/LICENSE-OFL.txt" ] && okay "OFL licence text ships" || note "assets/fonts/LICENSE-OFL.txt is missing"

echo
echo "── nothing renders post content (the outage guard, on the packaged bytes)"
if php "$ROOT/scripts/guard-no-content-render.php" "$DIR" >/dev/null 2>&1; then
	okay "no content-rendering call"
else
	note "a live content-rendering call is in the package — see docs/INCIDENT-2026-07-13-vps-outage.md"
fi

echo
echo "── text domain matches the directory name (WordPress requires this)"
if grep -q "Text Domain: *$SLUG" "$DIR/style.css"; then
	okay "text domain = $SLUG"
else
	note "style.css Text Domain must equal the directory name: $SLUG"
fi

echo
if [ "$fail" -ne 0 ]; then
	red "════ NOT SHIPPABLE — fix the above before submitting."
	exit 1
fi
grn "════ package is clean"

[ "$CHECK_ONLY" -eq 1 ] && { echo "   (--check: no zip written)"; exit 0; }

# ------------------------------------------------------------------- write ----

mkdir -p "$OUT"
ZIP="$OUT/$SLUG-$VERSION.zip"
rm -f "$ZIP"
( cd "$STAGE" && zip -qr "$ZIP" "$SLUG" -x '*.DS_Store' )

echo
echo "════ $ZIP"
echo "   $(du -h "$ZIP" | cut -f1)  ·  $(unzip -l "$ZIP" | tail -1 | awk '{print $2}') files"
echo
echo "   Upload at https://wordpress.org/themes/upload/"
echo "   The zip's top-level directory is '$SLUG', which is the slug the"
echo "   Directory will publish under and the text domain the theme uses."
