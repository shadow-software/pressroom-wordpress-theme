#!/usr/bin/env bash
#
# Prove the guard still works.
#
# The previous guard was a grep that could not catch what it was written to catch,
# and nobody noticed for the guard's entire life — it failed the build every run,
# so "CI is red" stopped carrying information. A guard that is never tested is a
# guard you are trusting on faith.
#
# So: plant the actual outage bug, in several disguises, and assert the guard
# CATCHES each one. Then plant the innocent lookalikes — the warning comments, a
# string, a method of the same name, a different filter hook — and assert it stays
# QUIET. If the guard ever stops discriminating between these, this fails, loudly,
# in CI, before a human has to.
#
# Usage: ./scripts/guard-selftest.sh

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
GUARD="$ROOT/scripts/guard-no-content-render.php"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

pass=0
fail=0

# Assert the guard's exit code for a given snippet.
#   expect_caught <name> <php-source>   — the guard MUST reject this
#   expect_clean  <name> <php-source>   — the guard MUST accept this
check() {
	local want="$1" name="$2" src="$3"
	local dir="$TMP/$name"
	mkdir -p "$dir"
	printf '%s\n' "$src" > "$dir/case.php"

	local got=0
	php "$GUARD" "$dir" >/dev/null 2>&1 || got=1

	if [ "$got" -eq "$want" ]; then
		printf '   ✓ %-34s %s\n' "$name" "$([ "$want" -eq 1 ] && echo 'caught' || echo 'allowed')"
		pass=$((pass + 1))
	else
		printf '   ✗ %-34s expected %s, got %s\n' "$name" \
			"$([ "$want" -eq 1 ] && echo 'CAUGHT' || echo 'CLEAN')" \
			"$([ "$got"  -eq 1 ] && echo 'CAUGHT' || echo 'CLEAN')"
		fail=$((fail + 1))
	fi
}
expect_caught() { check 1 "$@"; }
expect_clean()  { check 0 "$@"; }

echo "── the guard must CATCH these (each is the outage, or a relative of it)"

expect_caught 'do_blocks'          '<?php $h = do_blocks( $post->post_content );'
expect_caught 'the_content-filter' "<?php return apply_filters( 'the_content', \$c );"
expect_caught 'the_content'        '<?php the_content();'
expect_caught 'get_the_content'    '<?php $c = get_the_content();'
expect_caught 'render_block'       '<?php echo render_block( $parsed );'
expect_caught 'do_shortcode'       '<?php echo do_shortcode( $content );'
expect_caught 'split-across-lines' '<?php $x = trim(
	do_blocks   (
		$content
	)
);'
expect_caught 'buried-mid-expression' '<?php $out = sprintf( "%s", strlen( do_blocks( $c ) ) );'

echo
echo "── the guard must STAY QUIET on these (it cried wolf on the first one for its whole life)"

expect_clean 'docblock-warning' '<?php
/**
 * DO NOT call do_blocks(), apply_filters( "the_content", ... ), or anything that
 * renders the post. This is the comment the old grep-based guard flagged as a
 * violation on every CI run.
 */
$x = 1;'
expect_clean 'line-comment'     '<?php // do_blocks( $content ); — deliberately not called
$x = 1;'
expect_clean 'hash-comment'     '<?php # get_the_content();
$x = 1;'
expect_clean 'name-in-a-string' "<?php \$fn = 'do_blocks'; \$msg = 'never call do_blocks()';"
expect_clean 'a-different-hook' "<?php \$x = apply_filters( 'the_excerpt', \$v );"
expect_clean 'a-method-call'    '<?php $obj->do_blocks( $x );'
expect_clean 'a-definition'     '<?php class R { public function render_block() { return 1; } }'
expect_clean 'the-real-theme'   '<?php // stand-in; the real theme is scanned by the other CI step.'

echo
if [ "$fail" -ne 0 ]; then
	printf '✗ guard self-test FAILED — %d passed, %d failed.\n' "$pass" "$fail" >&2
	echo >&2
	echo "The guard no longer tells the outage bug apart from a comment about it." >&2
	echo "Fix scripts/guard-no-content-render.php. Do not delete this test." >&2
	exit 1
fi

printf '✓ guard self-test passed — %d/%d.\n' "$pass" "$pass"
echo "  It catches the bug that caused the outage, and it does not cry wolf."
