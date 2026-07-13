<?php
/**
 * Migrate a live site from the `digest` slug to `shadow-software-digest-theme-for-wordpress`.
 *
 * Run on the SERVER, against ONE site:
 *   wp eval-file migrate-slug.php --allow-root
 *   wp eval-file migrate-slug.php --allow-root -- --dry-run
 *
 * ---------------------------------------------------------------------------
 * WHY THIS IS NOT OPTIONAL
 *
 * A block's name is stored inside the post content as an HTML comment:
 *
 *     <!-- wp:digest/faq {"items":[...]} /-->
 *
 * Rename the theme and register the block as `shadow-digest/faq`, and WordPress
 * no longer knows what `digest/faq` is. It does not error. It renders NOTHING.
 * Every short answer, every FAQ, every table of contents, every sources list on
 * both live sites silently disappears, and the only symptom is a shorter page.
 *
 * The same is true of theme_mods: they are stored under the option key
 * `theme_mods_digest`. A renamed theme reads `theme_mods_shadow-software-digest-theme-for-wordpress`,
 * finds nothing, and every site falls back to the theme's defaults — which means
 * both publications turn oxblood and lose their founding year, their city, their
 * motto and their newsletter name.
 *
 * So this script rewrites both, in one pass, before the new theme is activated.
 * ---------------------------------------------------------------------------
 *
 * It is idempotent and it takes a backup of every post it touches.
 */

$dry_run = in_array( '--dry-run', (array) ( $args ?? array() ), true )
	|| in_array( '--dry-run', (array) ( $_SERVER['argv'] ?? array() ), true );

$old_slug = 'digest';
$new_slug = 'shadow-software-digest-theme-for-wordpress';
$old_ns   = 'wp:digest/';
$new_ns   = 'wp:shadow-digest/';

echo $dry_run ? "DRY RUN — nothing will be written.\n\n" : "MIGRATING.\n\n";

/* -------------------------------------------------------------------------- *
 * 1. Theme mods. Without this both sites lose their entire identity.
 * -------------------------------------------------------------------------- */

$mods = get_option( 'theme_mods_' . $old_slug );

if ( is_array( $mods ) && ! empty( $mods ) ) {
	echo '1. theme_mods: ' . count( $mods ) . " settings found under the old slug.\n";

	$existing = get_option( 'theme_mods_' . $new_slug );

	if ( is_array( $existing ) && ! empty( $existing ) ) {
		echo "   (the new slug already has mods — merging, old values win)\n";
		$mods = array_merge( $existing, $mods );
	}

	if ( ! $dry_run ) {
		update_option( 'theme_mods_' . $new_slug, $mods );
		echo "   ✓ copied to theme_mods_{$new_slug}\n";
	}

	// Show what would be preserved, so a human can eyeball it.
	foreach ( array( 'digest_accent', 'digest_strapline', 'digest_founded', 'digest_newsletter_name' ) as $k ) {
		if ( isset( $mods[ $k ] ) ) {
			echo "     {$k} = " . ( is_scalar( $mods[ $k ] ) ? $mods[ $k ] : '(array)' ) . "\n";
		}
	}
} else {
	echo "1. theme_mods: none found under the old slug (nothing to migrate).\n";
}

echo "\n";

/* -------------------------------------------------------------------------- *
 * 2. Block names inside post content. This is the one that silently eats
 *    content if it is skipped.
 * -------------------------------------------------------------------------- */

global $wpdb;

// Every post whose content mentions a Digest block, in any post type — posts,
// pages, AND wp_template / wp_template_part / wp_block, which is where a site
// editor stores template overrides.
$posts = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT ID, post_type, post_title, post_content
		   FROM {$wpdb->posts}
		  WHERE post_content LIKE %s
		    AND post_status NOT IN ('auto-draft')",
		'%' . $wpdb->esc_like( $old_ns ) . '%'
	)
);

echo '2. post content: ' . count( $posts ) . " posts reference a digest/* block.\n";

$changed = 0;

foreach ( $posts as $post ) {
	$before = (string) $post->post_content;

	// Rewrite both the opening and the self-closing/closing forms:
	//   <!-- wp:digest/faq {...} /-->
	//   <!-- wp:digest/faq -->  ...  <!-- /wp:digest/faq -->
	$after = str_replace(
		array( '<!-- wp:digest/', '<!-- /wp:digest/' ),
		array( '<!-- wp:shadow-digest/', '<!-- /wp:shadow-digest/' ),
		$before
	);

	if ( $after === $before ) {
		continue;
	}

	++$changed;

	printf(
		"   %-16s #%-5d %s\n",
		$post->post_type,
		$post->ID,
		mb_substr( wp_strip_all_tags( (string) $post->post_title ), 0, 42 )
	);

	if ( $dry_run ) {
		continue;
	}

	// Keep the original content, so this is reversible without a database dump.
	update_post_meta( $post->ID, '_digest_premigration_content', $before );

	// Update the row directly. wp_update_post() would run the content through
	// kses and the block parser, which can normalise markup we did not ask it to
	// touch; a targeted column update changes exactly what we intend and nothing
	// else.
	$wpdb->update(
		$wpdb->posts,
		array( 'post_content' => $after ),
		array( 'ID' => $post->ID ),
		array( '%s' ),
		array( '%d' )
	);

	clean_post_cache( $post->ID );
}

echo "   {$changed} posts " . ( $dry_run ? 'would be' : 'were' ) . " rewritten.\n\n";

/* -------------------------------------------------------------------------- *
 * 3. The navigation post and the widget/sidebar options reference nothing
 *    theme-specific, so they survive a rename untouched. Nav menu LOCATIONS,
 *    however, are stored inside theme_mods — which step 1 already carried over.
 * -------------------------------------------------------------------------- */

echo "3. nav menu locations travel inside theme_mods — handled in step 1.\n\n";

if ( $dry_run ) {
	echo "DRY RUN complete. Re-run without --dry-run to apply.\n";
	return;
}

echo "Migration complete.\n";
echo "Now activate the new theme:  wp theme activate {$new_slug}\n";
echo "\nTo roll back a post: its original content is in the post meta\n";
echo "  _digest_premigration_content\n";
