<?php
/**
 * Seed the sandbox with a REAL front page: sections, posts, and a nav menu.
 *
 * WHY THIS EXISTS, separately from local-seed.php:
 *
 * local-seed.php seeds the canary post — one category, one article — because its
 * job is to exercise the recursion bug. That is the right job and it should keep
 * doing only that.
 *
 * But it means the sandbox front page had one category and four posts, while a
 * live site has six of each. So the section grid rendered 2 columns of 6 and the
 * lead grid's rails ran short, and the homepage looked full of spacing holes that
 * do not exist on the real sites. Those "spacing bugs" were missing content.
 *
 * A layout can only be judged against the content it was designed for. This file
 * gives the sandbox that content, mirroring ../marksmansdigest.com/scripts/.
 *
 * Idempotent: keyed by slug/title, so re-running updates rather than duplicating.
 */

$sections = array(
	'Features'    => 'features',
	'Heritage'    => 'heritage',
	'Ballistics'  => 'ballistics',
	'Gear'        => 'gear',
	'Legislation' => 'legislation',
	'Events'      => 'events',
);

$term_ids = array();

foreach ( $sections as $label => $slug ) {
	$existing = get_term_by( 'slug', $slug, 'category' );

	if ( $existing instanceof WP_Term ) {
		$term_ids[ $slug ] = (int) $existing->term_id;
		continue;
	}

	$made = wp_insert_term( $label, 'category', array( 'slug' => $slug ) );

	if ( ! is_wp_error( $made ) ) {
		$term_ids[ $slug ] = (int) $made['term_id'];
	}
}

echo "sections: " . count( $term_ids ) . "\n";

/*
 * Three headlines per section — the section grid renders up to three, so this is
 * what a full column looks like. Anything less and the grid's rows go ragged.
 */
$headlines = array(
	'features'    => array(
		'The Gunsmiths Keeping Heirloom Rifles Alive',
		'Inside the Last Family Barrel Shop',
		"What the Champions Know That We Don't",
	),
	'heritage'    => array(
		'The Springfield That Won Camp Perry',
		'A Century of the Service Rifle',
		'The Landrace Rifles of the Frontier',
	),
	'ballistics'  => array(
		'Reading Mirage Without a Spotter',
		'Dialing In Your Cold-Bore Shot',
		'Why Your Groups Open Up at Distance',
	),
	'gear'        => array(
		'A New Generation of Rangefinding Optics',
		'Three Flagship Match Triggers, Tested',
		'What the Lab Certificate Actually Tells You',
	),
	'legislation' => array(
		'Senate Panel Weighs Suppressor Reclassification',
		'A State-by-State Carry Tracker',
		'Editorial: Regulate the Sale, Not the Sport',
	),
	'events'      => array(
		'Nationals Return to the Ohio Ranges',
		'Results — Regional Long-Range Cup',
		'How to Enter Your First Match',
	),
);

$author = get_user_by( 'login', 'e-vance' );
$author_id = $author instanceof WP_User ? (int) $author->ID : 1;

$made = 0;
$when = time();

foreach ( $headlines as $slug => $titles ) {
	if ( ! isset( $term_ids[ $slug ] ) ) {
		continue;
	}

	foreach ( $titles as $title ) {
		$found = get_page_by_title( $title, OBJECT, 'post' );

		if ( $found instanceof WP_Post ) {
			continue;
		}

		// Stagger the dates so "latest" ordering on the front page is stable and
		// the lead story is not chosen at random on every reseed.
		$when -= 3600;

		// Every seeded post needs its own excerpt, not a shared placeholder — a
		// meta description is generated from get_the_excerpt(), and eighteen
		// posts sharing one sentence made every one of them a Screaming Frog
		// "duplicate meta description" finding.
		$excerpt = sprintf(
			'A reported dispatch from the Digest newsroom on %s, filed this week from the range and the bench.',
			lcfirst( $title )
		);

		$id = wp_insert_post(
			array(
				'post_title'    => $title,
				'post_status'   => 'publish',
				'post_author'   => $author_id,
				'post_type'     => 'post',
				'post_date'     => gmdate( 'Y-m-d H:i:s', $when ),
				'post_excerpt'  => $excerpt,
				'post_content'  => "<!-- wp:paragraph -->\n<p>At first light the range is silent but for the flags, and the marksmen read them the way a sailor reads the sea. This is a craft older than the optics that now sit atop the rifles, and it survives because it is still, stubbornly, the thing that decides the shot.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p>A ten-mile-per-hour crosswind moves a match bullet roughly six feet at a thousand yards. That figure is not the hard part. The hard part is knowing, from the shimmer over the grass, that the wind is ten and not seven.</p>\n<!-- /wp:paragraph -->\n\n<!-- wp:paragraph -->\n<p>A modern match rifle will shoot smaller groups than its owner can hold, straight out of the box. The gap between the rifle and the shooter is real, measurable, and at a thousand yards it is smaller than the error in your wind call.</p>\n<!-- /wp:paragraph -->",
			)
		);

		if ( ! is_wp_error( $id ) ) {
			wp_set_post_categories( (int) $id, array( $term_ids[ $slug ] ) );
			++$made;
		}
	}
}

echo "posts: {$made} new\n";

/*
 * The navigation. A block theme's nav block stores its items as block markup in
 * a wp_navigation post; with no such post it falls back to listing every Page,
 * which is why the masthead read "SAMPLE PAGE" instead of the section list.
 */
$items = '';

foreach ( $sections as $label => $slug ) {
	if ( ! isset( $term_ids[ $slug ] ) ) {
		continue;
	}

	$items .= sprintf(
		'<!-- wp:navigation-link {"label":"%s","type":"category","id":%d,"url":"%s","kind":"taxonomy"} /-->' . "\n",
		esc_attr( $label ),
		$term_ids[ $slug ],
		esc_url( (string) get_category_link( $term_ids[ $slug ] ) )
	);
}

$existing_nav = get_posts(
	array(
		'post_type'      => 'wp_navigation',
		'posts_per_page' => 1,
		'post_status'    => 'any',
	)
);

$nav = array(
	'post_title'   => 'Sections',
	'post_content' => $items,
	'post_status'  => 'publish',
	'post_type'    => 'wp_navigation',
);

if ( $existing_nav ) {
	$nav['ID'] = (int) $existing_nav[0]->ID;
	wp_update_post( $nav );
	echo "nav: updated\n";
} else {
	wp_insert_post( $nav );
	echo "nav: created\n";
}

echo "done\n";
