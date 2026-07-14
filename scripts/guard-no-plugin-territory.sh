#!/usr/bin/env bash
#
# The theme must not do plugin things.
#
# WordPress.org rejected this theme's submission on exactly this:
#
#     REQUIRED: The theme uses the register_block_type() function in the file
#     inc/blocks.php. register_block_type() is plugin-territory functionality
#     and must not be used in themes. Use a plugin instead.
#
# The requirements page lists, verbatim, as plugin territory:
#
#     "Custom post types, Custom blocks, Custom roles, Custom user contact
#      methods, Custom mime types, Shortcodes, Functionality that is not related
#      to design and presentation"
#
# and separately: analytics/tracking, SEO options, contact forms, non-design meta
# boxes, resource caching, social share buttons, session tampering.
#
# The rule's test is a good one and worth internalising rather than working around:
#
#     Themes handle PRESENTATION. Plugins handle CONTENT.
#     Anything a user LOSES when they switch themes is plugin territory.
#
# So the 17 blocks moved to broadside-blocks/. This script makes sure they stay
# moved. It runs in CI and in package.sh, against the bytes actually being shipped.
#
# Usage:  ./scripts/guard-no-plugin-territory.sh [theme-dir]

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
THEME="${1:-$ROOT/broadside}"

[ -d "$THEME" ] || { echo "guard: not a directory: $THEME" >&2; exit 2; }

fail=0
hit() {
	printf '\033[31m   ✗ %s\033[0m\n' "$1"
	printf '     %s\n' "$2"
	fail=1
	if [ -n "${GITHUB_ACTIONS:-}" ]; then
		echo "::error::$1 — $2"
	fi
}

echo "── the theme must not do plugin things"

# The one that got us rejected. Comments are stripped first: a docblock EXPLAINING
# why we do not call register_block_type() must not itself trip the guard. That is
# the same mistake the old do_blocks() grep made, and it made CI permanently red.
scan() {
	local needle="$1"
	find "$THEME" -name '*.php' -print0 |
		xargs -0 -r php -r '
			foreach ( array_slice( $argv, 2 ) as $f ) {
				foreach ( token_get_all( file_get_contents( $f ) ) as $i => $t ) {
					if ( is_array( $t ) && T_STRING === $t[0] && $t[1] === $argv[1] ) {
						echo "$f:{$t[2]}\n";
					}
				}
			}
		' "$needle" 2>/dev/null
}

found=$(scan register_block_type)
if [ -n "$found" ]; then
	while IFS= read -r line; do
		hit "register_block_type() in ${line#"$ROOT/"}" \
			"Custom blocks are plugin territory. They live in broadside-blocks/."
	done <<< "$found"
else
	echo "   ✓ no register_block_type()"
fi

for fn in register_post_type register_taxonomy add_shortcode register_block_style_variation; do
	found=$(scan "$fn")
	if [ -n "$found" ]; then
		while IFS= read -r line; do
			hit "${fn}() in ${line#"$ROOT/"}" "Plugin territory — the theme must not create content types."
		done <<< "$found"
	fi
done
[ "$fail" -eq 0 ] && echo "   ✓ no custom post types, taxonomies or shortcodes"

# The blocks directory itself must not be in the theme.
if [ -d "$THEME/blocks" ]; then
	hit "$THEME/blocks/ exists" "The blocks belong to the plugin, not the theme."
else
	echo "   ✓ no blocks/ directory in the theme"
fi

echo
if [ "$fail" -ne 0 ]; then
	printf '\033[31m✗ the theme contains plugin-territory code — WordPress.org will reject it.\033[0m\n' >&2
	echo "  https://make.wordpress.org/themes/handbook/review/required/" >&2
	exit 1
fi

printf '\033[32m✓ the theme is a theme.\033[0m\n'
