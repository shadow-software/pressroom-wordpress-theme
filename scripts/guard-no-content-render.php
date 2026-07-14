<?php
/**
 * The guard that matters most.
 *
 * On 2026-07-13 a block render callback in this theme called do_blocks() on the
 * post content to find its headings. That re-renders every block in the content
 * — including the block that called it — with no base case. Requests spun
 * forever, hung every PHP-FPM worker on a shared box, and starved it until it
 * could not fork sshd. americanguntrader.com, a live marketplace, went down with
 * it. See docs/INCIDENT-2026-07-13-vps-outage.md.
 *
 * This script fails the build if any function that renders post content comes
 * back into the theme. CI runs it and deploy.sh runs it.
 *
 * It TOKENIZES the PHP rather than grepping it. That is not fussiness — the
 * previous grep-based guard was broken for its entire life:
 *
 *     grep -rnE "apply_filters\(\s*'the_content'" theme/ | grep -vE '^\s*\*|//'
 *
 * grep -n prefixes every hit with "file:line:", so a comment line arrives at the
 * second grep as "inc/blocks.php:645: *   DO NOT call ..." — the "*" is no longer
 * at the start of the line, ^\s*\* never matches, and the comment is reported as
 * a violation. CI failed on every run, and what it was flagging was the comment
 * warning people not to do the thing. A guard that cries wolf is a guard people
 * learn to ignore, which is worse than no guard at all.
 *
 * A tokenizer has no opinion about line shape. A mention inside a comment or a
 * string is a T_COMMENT / T_CONSTANT_ENCAPSED_STRING and is skipped; a real call
 * is a T_STRING in call position and is caught, even if it is split across lines
 * or buried mid-expression.
 *
 * Usage:  php scripts/guard-no-content-render.php [theme-dir]
 * Exit:   0 = clean, 1 = a live content-rendering call is present.
 *
 * @package Shadow_Software_Digest_Theme_For_WordPress
 */

declare( strict_types = 1 );

/**
 * Functions that render post content. Calling any of these from a render callback
 * re-enters the block renderer, and if the block is inside the content it is
 * re-entering itself.
 */
const BANNED_CALLS = array(
	'do_blocks'          => 'renders every block in the content, including the one that called it',
	'the_content'        => 'echoes the fully-rendered post content',
	'get_the_content'    => 'runs the block parser via the_content filters',
	'do_shortcode'       => 'renders shortcodes in the content, which can nest back into blocks',
	'render_block'       => 'renders a parsed block — the recursion, one level down',
	'do_block'           => 'alias-shaped; render post content by any other name',
);

/**
 * apply_filters() is only banned for one specific hook: 'the_content'. Every
 * other filter is fine, so this cannot be a flat name match.
 */
const BANNED_FILTERS = array( 'the_content' );

$theme = $argv[1] ?? dirname( __DIR__ ) . '/shadow-software-digest-theme-for-wordpress';

if ( ! is_dir( $theme ) ) {
	fwrite( STDERR, "guard: not a directory: {$theme}\n" );
	exit( 2 );
}

$violations = array();
$scanned    = 0;

$files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $theme, FilesystemIterator::SKIP_DOTS ) );

foreach ( $files as $file ) {
	if ( 'php' !== strtolower( $file->getExtension() ) ) {
		continue;
	}

	++$scanned;
	$path   = $file->getPathname();
	$tokens = token_get_all( (string) file_get_contents( $path ) );

	foreach ( $tokens as $i => $token ) {
		// Comments, docblocks, strings, whitespace: not code. This is the whole
		// point of tokenizing — the warning comments about do_blocks() live in
		// T_COMMENT and can never trip this guard.
		if ( ! is_array( $token ) || T_STRING !== $token[0] ) {
			continue;
		}

		$name = $token[1];
		$line = $token[2];

		// Only a call. `$do_blocks`, `'do_blocks'`, `function do_blocks()` and a
		// method named ->do_blocks() are all something else.
		if ( ! shadow_digest_guard_is_call( $tokens, $i ) ) {
			continue;
		}

		if ( isset( BANNED_CALLS[ $name ] ) ) {
			$violations[] = array( $path, $line, $name . '()', BANNED_CALLS[ $name ] );
			continue;
		}

		// apply_filters( 'the_content', … ) — banned by hook, not by name.
		if ( 'apply_filters' === $name ) {
			$hook = shadow_digest_guard_first_string_arg( $tokens, $i );
			if ( null !== $hook && in_array( $hook, BANNED_FILTERS, true ) ) {
				$violations[] = array(
					$path,
					$line,
					"apply_filters( '{$hook}', … )",
					'runs the whole content filter chain, block parser included',
				);
			}
		}
	}
}

/**
 * Is the T_STRING at $i actually being CALLED?
 *
 * Requires a following "(", and rejects a preceding "->", "::", "function" or
 * "$" — a definition, a method, or a variable is not a call to the global.
 *
 * @param array<int, array{0:int,1:string,2:int}|string> $tokens Token stream.
 * @param int                                            $i      Index of the T_STRING.
 * @return bool
 */
function shadow_digest_guard_is_call( array $tokens, int $i ): bool {
	$next = shadow_digest_guard_next( $tokens, $i );
	if ( null === $next || '(' !== $tokens[ $next ] ) {
		return false;
	}

	$prev = shadow_digest_guard_prev( $tokens, $i );
	if ( null === $prev ) {
		return true;
	}

	$p = $tokens[ $prev ];

	if ( is_array( $p ) && in_array( $p[0], array( T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION, T_NEW ), true ) ) {
		return false;
	}

	// `?->` on PHP 8.
	if ( is_array( $p ) && defined( 'T_NULLSAFE_OBJECT_OPERATOR' ) && T_NULLSAFE_OBJECT_OPERATOR === $p[0] ) {
		return false;
	}

	return true;
}

/**
 * The literal value of the first argument, if it is a plain quoted string.
 *
 * Used to read the hook name out of apply_filters( 'the_content', … ).
 *
 * @param array<int, array{0:int,1:string,2:int}|string> $tokens Token stream.
 * @param int                                            $i      Index of the function-name T_STRING.
 * @return string|null The unquoted hook name, or null if it is not a literal.
 */
function shadow_digest_guard_first_string_arg( array $tokens, int $i ): ?string {
	$paren = shadow_digest_guard_next( $tokens, $i );
	if ( null === $paren ) {
		return null;
	}

	$arg = shadow_digest_guard_next( $tokens, $paren );
	if ( null === $arg || ! is_array( $tokens[ $arg ] ) || T_CONSTANT_ENCAPSED_STRING !== $tokens[ $arg ][0] ) {
		return null;
	}

	// "'the_content'" → "the_content".
	return trim( $tokens[ $arg ][1], "'\"" );
}

/**
 * Index of the next token that is not whitespace or a comment.
 *
 * @param array<int, array{0:int,1:string,2:int}|string> $tokens Token stream.
 * @param int                                            $i      Starting index.
 * @return int|null
 */
function shadow_digest_guard_next( array $tokens, int $i ): ?int {
	$n = count( $tokens );
	for ( $j = $i + 1; $j < $n; $j++ ) {
		if ( ! shadow_digest_guard_is_skippable( $tokens[ $j ] ) ) {
			return $j;
		}
	}
	return null;
}

/**
 * Index of the previous token that is not whitespace or a comment.
 *
 * @param array<int, array{0:int,1:string,2:int}|string> $tokens Token stream.
 * @param int                                            $i      Starting index.
 * @return int|null
 */
function shadow_digest_guard_prev( array $tokens, int $i ): ?int {
	for ( $j = $i - 1; $j >= 0; $j-- ) {
		if ( ! shadow_digest_guard_is_skippable( $tokens[ $j ] ) ) {
			return $j;
		}
	}
	return null;
}

/**
 * Whitespace, comments and docblocks carry no meaning for this guard.
 *
 * @param array{0:int,1:string,2:int}|string $token A single token.
 * @return bool
 */
function shadow_digest_guard_is_skippable( $token ): bool {
	return is_array( $token ) && in_array( $token[0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true );
}

// ------------------------------------------------------------------ report ----

if ( array() === $violations ) {
	echo "guard: OK — {$scanned} PHP files scanned, nothing renders post content.\n";
	exit( 0 );
}

fwrite( STDERR, "\n" );
fwrite( STDERR, "A live content-rendering call is present in the theme.\n" );
fwrite( STDERR, "This is the construct that took down a shared production server on 2026-07-13.\n" );
fwrite( STDERR, "Read docs/INCIDENT-2026-07-13-vps-outage.md before you argue with this.\n\n" );

foreach ( $violations as list( $path, $line, $call, $why ) ) {
	$rel = str_replace( dirname( __DIR__ ) . '/', '', $path );

	// GitHub Actions renders this as an inline annotation on the offending line.
	if ( getenv( 'GITHUB_ACTIONS' ) ) {
		fwrite( STDOUT, "::error file={$rel},line={$line}::{$call} — {$why}\n" );
	}

	fwrite( STDERR, "  {$rel}:{$line}\n" );
	fwrite( STDERR, "      {$call} — {$why}\n\n" );
}

$n = count( $violations );
fwrite( STDERR, "{$n} violation(s). Read the RAW post_content instead; a core block saves its\n" );
fwrite( STDERR, "own markup verbatim, so the HTML you want is already there unrendered.\n\n" );

exit( 1 );
