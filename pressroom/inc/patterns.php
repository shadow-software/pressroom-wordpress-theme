<?php
/**
 * Block patterns.
 *
 * WordPress registers everything in patterns/ automatically from its file
 * headers, so this file only does the two things that cannot be declared there:
 * it registers the theme's pattern categories, and it retires the core patterns
 * that would look wrong in a broadsheet.
 *
 * @package Pressroom
 * @since   1.0.0
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

/**
 * Register the pattern categories the theme's patterns file themselves under.
 *
 * @since 1.0.0
 * @return void
 */
function shadow_digest_register_pattern_categories(): void {
	register_block_pattern_category(
		'digest-article',
		array(
			'label'       => __( 'Pressroom — article furniture', 'pressroom' ),
			'description' => __( 'The parts of a long read: the opening, the answer box, the contents, the FAQ, the sources.', 'pressroom' ),
		)
	);

	register_block_pattern_category(
		'digest-page',
		array(
			'label'       => __( 'Pressroom — pages', 'pressroom' ),
			'description' => __( 'Whole pages a publication needs: a masthead page, an ethics policy, a contact page.', 'pressroom' ),
		)
	);
}
add_action( 'init', 'shadow_digest_register_pattern_categories' );

/**
 * Remove the core patterns that have no place in a newspaper.
 *
 * Core ships patterns built for marketing sites — hero banners with rounded
 * buttons, "our team" grids, pricing tables. Offering them inside a broadsheet
 * theme invites an editor to build a page that fights the design, and then blame
 * the theme. Removing them is a kindness.
 *
 * @since 1.0.0
 * @return void
 */
function shadow_digest_unregister_core_patterns(): void {
	remove_theme_support( 'core-block-patterns' );
}
add_action( 'after_setup_theme', 'shadow_digest_unregister_core_patterns' );
