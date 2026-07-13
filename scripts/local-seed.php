<?php
/**
 * Seed the local sandbox with content that EXERCISES THE BUG.
 *
 * This is not decorative content. The post below contains a digest/toc block —
 * the exact block whose render callback recursed and took down a production
 * server on 2026-07-13. If this theme ever regresses to rendering post content
 * inside a render callback, loading this post locally will hang for ten seconds
 * and then die with a stack trace, instead of hanging a production box forever.
 *
 * The front page is NOT sufficient to catch this. The front page has no table of
 * contents on it, which is exactly why it rendered perfectly while the theme was
 * fatally broken. Any local test that only checks the front page reproduces the
 * original mistake.
 */

$cat = wp_insert_term( 'Features', 'category', array( 'slug' => 'features' ) );
$cat_id = is_wp_error( $cat ) ? (int) get_cat_ID( 'Features' ) : (int) $cat['term_id'];

$author = wp_insert_user(
	array(
		'user_login'   => 'e-vance',
		'user_pass'    => wp_generate_password( 20 ),
		'display_name' => 'Eleanor Vance',
		'role'         => 'author',
		'description'  => 'Chief Correspondent. Eighteen years covering the shooting sports.',
	)
);
$author_id = is_wp_error( $author ) ? 1 : (int) $author;
update_user_meta( $author_id, 'shadow_digest_role', 'Chief Correspondent' );

/*
 * THE CANARY POST.
 *
 * Every Digest block, in one post — most importantly digest/toc, which must
 * coexist with headings without the two rendering each other in a loop.
 */
$canary = wp_insert_post(
	array(
		'post_title'    => 'The Thousand-Yard Question',
		'post_name'     => 'the-thousand-yard-question',
		'post_status'   => 'publish',
		'post_type'     => 'post',
		'post_author'   => $author_id,
		'post_category' => array( $cat_id ),
		'post_excerpt'  => 'A quiet revival of long-range match shooting is drawing a new generation to a century-old discipline.',
		'post_content'  => '<!-- wp:shadow-digest/short-answer {"answer":"Long-range shooting is a test of reading wind honestly, not of equipment."} /-->

<!-- wp:group {"className":"digest-furniture"} -->
<div class="wp-block-group digest-furniture">
<!-- wp:shadow-digest/takeaways {"items":["Wind reading, not equipment, separates competitors.","Mirage tells you more than a flag, and it is free.","The discipline cannot be rushed or faked."]} /-->

<!-- wp:shadow-digest/toc /-->
</div>
<!-- /wp:group -->

<!-- wp:paragraph {"className":"digest-dropcap"} -->
<p class="digest-dropcap">At first light the range is silent but for the flags, and the marksmen read them the way a sailor reads the sea.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading">What the Wind Actually Does</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>A ten-mile-per-hour crosswind moves a match bullet roughly six feet at a thousand yards. That figure is not the hard part.</p>
<!-- /wp:paragraph -->

<!-- wp:quote -->
<blockquote class="wp-block-quote"><!-- wp:paragraph -->
<p>The rifle is honest. The wind is honest. There is nowhere to hide.</p>
<!-- /wp:paragraph --><cite>A range master of thirty years</cite></blockquote>
<!-- /wp:quote -->

<!-- wp:heading -->
<h2 class="wp-block-heading">Why the Rifle Is Rarely the Problem</h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>A modern match rifle will shoot smaller groups than its owner can hold, straight out of the box.</p>
<!-- /wp:paragraph -->

<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">A Word on Coriolis</h3>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p>Real, measurable, and at a thousand yards smaller than the error in your wind call.</p>
<!-- /wp:paragraph -->

<!-- wp:shadow-digest/disclosure-table {"rows":[{"label":"Mirage","detail":"Shows the wind between you and the target.","partner":"Example Optics","url":"https://example.com"},{"label":"Position","detail":"Build it so the rifle returns to the target on its own.","partner":"","url":""}]} /-->

<!-- wp:shadow-digest/faq {"items":[{"question":"Do I need an expensive rifle to start?","answer":"No. A modern match rifle out-shoots its owner for a long time, and most clubs will lend a newcomer one."},{"question":"What is mirage?","answer":"The visible shimmer of heated air, which reveals the wind between you and the target."}]} /-->

<!-- wp:shadow-digest/sources {"items":["Interviews at twelve ranges, November 2025 to June 2026.","Ballistic figures cross-checked against published tables."]} /-->',
	)
);

echo "  canary post: {$canary} (contains digest/toc + headings — the recursion path)\n";

// A few more so the front page grid, the briefs rail and the related block have
// something to chew on.
$filler = array(
	'Senate Panel Weighs Suppressor Reclassification',
	'The Springfield That Won Camp Perry',
	'A New Generation of Rangefinding Optics',
	'Nationals Return to the Ohio Ranges',
	"A Marksman's Eye, at Ninety-Four",
	'Reading Mirage Without a Spotting Scope',
);

foreach ( $filler as $i => $title ) {
	wp_insert_post(
		array(
			'post_title'    => $title,
			'post_status'   => 'publish',
			'post_type'     => 'post',
			'post_author'   => $author_id,
			'post_category' => array( $cat_id ),
			'post_excerpt'  => 'From the Digest newsroom.',
			'post_content'  => '<!-- wp:paragraph --><p>Reported copy.</p><!-- /wp:paragraph -->',
			'post_date'     => gmdate( 'Y-m-d H:i:s', strtotime( '-' . ( $i + 2 ) . ' days' ) ),
		)
	);
}

// Give the theme its identity so the masthead renders like the real sites.
$mods = array(
	'shadow_digest_accent' => '#6b1f1f',
	'shadow_digest_accent_soft' => '#c99a5b',
	'shadow_digest_strapline' => 'The Journal of Record for the American Marksman',
	'shadow_digest_founded' => '1926',
	'shadow_digest_city' => 'New York',
	'shadow_digest_motto' => 'Steady Hands, Straight Talk',
	'shadow_digest_newsletter_name' => 'The Weekly Dispatch',
	'shadow_digest_standards' => 'Digest reports independently and has done so since 1926.',
);

foreach ( $mods as $k => $v ) {
	set_theme_mod( $k, $v );
}

update_option( 'blogname', "Marksman's Digest" );
update_option( 'permalink_structure', '/%postname%/' );
flush_rewrite_rules( false );

echo "  seeded " . ( count( $filler ) + 1 ) . " posts, theme mods, permalinks\n";
