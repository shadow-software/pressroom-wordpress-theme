<?php
/**
 * Theme supports, asset enqueueing, and editor wiring.
 *
 * @package Digest
 * @since   1.0.0
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

/**
 * Declare what the theme supports.
 *
 * A block theme gets most of this for free, but the ones declared here are
 * either not implied by theme.json (title tag, HTML5 markup, post thumbnails)
 * or are opt-in behaviours the Theme Directory expects to see declared.
 *
 * @since 1.0.0
 * @return void
 */
function shadow_digest_setup(): void {
	/*
	 * Translations. Digest ships a .pot in languages/ and honours any .mo a
	 * translator drops in, or a translation installed from translate.wordpress.org.
	 */
	load_theme_textdomain( 'shadow-software-digest-theme-for-wordpress', SHADOW_DIGEST_PATH . 'languages' );

	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'editor-styles' );
	add_theme_support( 'wp-block-styles' );

	add_theme_support(
		'html5',
		array(
			'search-form',
			'comment-form',
			'comment-list',
			'gallery',
			'caption',
			'style',
			'script',
		)
	);

	/*
	 * The masthead is a wordmark, not a logo — but a publisher may still want to
	 * swap the blackletter type for a real logotype. A wide, short crop suits a
	 * newspaper nameplate.
	 */
	add_theme_support(
		'custom-logo',
		array(
			'height'               => 120,
			'width'                => 600,
			'flex-height'          => true,
			'flex-width'           => true,
			'unlink-homepage-logo' => true,
		)
	);

	/*
	 * The editor loads the same stylesheet the front end does, so the drop cap,
	 * the column rules and the paper texture all render inside the editor canvas.
	 * What an editor sees is what a reader gets.
	 */
	add_editor_style( 'assets/css/digest.css' );
}
add_action( 'after_setup_theme', 'shadow_digest_setup' );

/**
 * Register the two navigation menus the templates use.
 *
 * Block themes normally use the navigation block, which stores its own menu.
 * Digest registers classic locations as well so a publisher migrating an
 * existing site can point their current menus at the masthead without rebuilding
 * them in the site editor.
 *
 * @since 1.0.0
 * @return void
 */
function shadow_digest_register_menus(): void {
	register_nav_menus(
		array(
			'sections' => __( 'Section navigation (masthead)', 'shadow-software-digest-theme-for-wordpress' ),
			'utility'  => __( 'Utility bar (subscribe, sign in)', 'shadow-software-digest-theme-for-wordpress' ),
		)
	);
}
add_action( 'init', 'shadow_digest_register_menus' );

/**
 * Enqueue the front-end stylesheet.
 *
 * Digest deliberately ships exactly one stylesheet and no front-end JavaScript
 * framework. The only script is a ~1KB progressive enhancement for the table of
 * contents, and it is loaded only on singular views that actually contain one.
 *
 * @since 1.0.0
 * @return void
 */
function shadow_digest_enqueue_assets(): void {
	// style.css is not enqueued here. WordPress reads its header comment block
	// (Theme Name, Version, …) directly via get_file_data() to register the
	// theme, and the WPTR requires only that the file exist with a valid header —
	// nothing requires it be loaded as a front-end stylesheet. It carries no CSS
	// rules of its own (see the doc comment in style.css), so enqueueing it was
	// a render-blocking HTTP request that bought nothing.

	// The real stylesheet: everything theme.json cannot express.
	wp_enqueue_style(
		'shadow-software-digest-theme-for-wordpress',
		SHADOW_DIGEST_URL . 'assets/css/digest.css',
		array(),
		SHADOW_DIGEST_VERSION
	);

	// Per-site branding, injected as custom properties. See inc/customizer.php.
	wp_add_inline_style( 'shadow-software-digest-theme-for-wordpress', shadow_digest_custom_properties() );
}
add_action( 'wp_enqueue_scripts', 'shadow_digest_enqueue_assets' );

/**
 * Push the same custom properties into the block editor.
 *
 * Without this the editor would render the accent colour as the theme's default
 * oxblood even on a site the publisher has set to green — the editor would lie
 * about what the post will look like.
 *
 * @since 1.0.0
 * @return void
 */
function shadow_digest_enqueue_editor_assets(): void {
	wp_register_style( 'digest-editor-vars', false, array(), SHADOW_DIGEST_VERSION );
	wp_enqueue_style( 'digest-editor-vars' );
	wp_add_inline_style( 'digest-editor-vars', shadow_digest_custom_properties() );
}
add_action( 'enqueue_block_assets', 'shadow_digest_enqueue_editor_assets' );

/**
 * Remove WordPress's default block-library duotone and global-styles SVG dump.
 *
 * Digest defines no duotones, so the filter definitions WordPress prints in the
 * footer are dead weight on every page load.
 *
 * @since 1.0.0
 * @return void
 */
function shadow_digest_trim_head(): void {
	remove_action( 'wp_body_open', 'wp_global_styles_render_svg_filters' );
	remove_action( 'in_admin_header', 'wp_global_styles_render_svg_filters' );
}
add_action( 'init', 'shadow_digest_trim_head' );

/**
 * Give the body a class describing the current section, so the stylesheet can
 * key off it without inline styles.
 *
 * @since 1.0.0
 * @param string[] $classes Existing body classes.
 * @return string[] Filtered body classes.
 */
function shadow_digest_body_classes( array $classes ): array {
	if ( is_singular() && ! is_front_page() ) {
		$classes[] = 'digest-reading';
	}

	if ( ! is_active_sidebar( 'sidebar-1' ) ) {
		$classes[] = 'digest-no-sidebar';
	}

	return $classes;
}
add_filter( 'body_class', 'shadow_digest_body_classes' );

/**
 * Register the sidebar used by the front page's left rail ("Along the Firing
 * Line" / "Along the Grow Line").
 *
 * The rail is a widget area rather than a hard-coded query so a publisher can
 * put anything there — a query loop of briefs, a promo, a weather box — without
 * editing a template.
 *
 * @since 1.0.0
 * @return void
 */
function shadow_digest_register_sidebars(): void {
	register_sidebar(
		array(
			'name'          => __( 'Front page rail', 'shadow-software-digest-theme-for-wordpress' ),
			'id'            => 'sidebar-1',
			'description'   => __( 'The narrow left-hand column on the front page, beside the lead story. Traditionally short news briefs.', 'shadow-software-digest-theme-for-wordpress' ),
			'before_widget' => '<div id="%1$s" class="digest-rail__widget %2$s">',
			'after_widget'  => '</div>',
			'before_title'  => '<h2 class="digest-rail__title">',
			'after_title'   => '</h2>',
		)
	);
}
add_action( 'widgets_init', 'shadow_digest_register_sidebars' );

/**
 * Declare the catalogue plate to WooCommerce.
 *
 * OPTIONAL: this is a no-op on a site with no shop. Digest depends on no plugin
 * and must run on stock WordPress — declaring support for a plugin that is not
 * there costs nothing and changes nothing.
 *
 * WHY THIS IS NOT JUST CSS. WooCommerce's default catalogue thumbnail is a 300px
 * HARD SQUARE CROP (woocommerce_thumbnail_cropping defaults to 1:1). The
 * stylesheet then crops those thumbnails again to the 4:5 plate the grid is ruled
 * for — so a wide product photograph is cropped twice, first to a square by the
 * server and then to a portrait by the browser, and the second crop can only
 * choose from what the first one left. A panoramic shot came out as a picture of
 * the middle of itself with the subject cut off both sides.
 *
 * Cropping twice is not a styling problem and cannot be fixed in the stylesheet.
 * So the theme states the shape it wants ONCE, here, and WooCommerce generates
 * that file. The CSS in §13 then only has to cover the images Woo has NOT
 * regenerated — the ones uploaded before the theme was activated.
 *
 * (After changing these, a shop with existing products needs its thumbnails
 * regenerated: `wp wc product regenerate_images`, or Tools -> Regenerate.)
 *
 * @since 1.0.11
 * @return void
 */
function shadow_digest_woocommerce_setup(): void {
	add_theme_support( 'woocommerce' );

	// The gallery lightbox and zoom are WooCommerce's own, and are what a reader
	// expects from a shop. Declaring them costs one line and nothing else.
	add_theme_support( 'wc-product-gallery-zoom' );
	add_theme_support( 'wc-product-gallery-lightbox' );
	add_theme_support( 'wc-product-gallery-slider' );

	/*
	 * The catalogue plate: 4:5, the proportion of a printed catalogue engraving.
	 * `cropping => custom` with a 4:5 ratio, NOT the 1:1 default — see above.
	 */
	add_theme_support(
		'woocommerce',
		array(
			'thumbnail_image_width' => 480,
			'single_image_width'    => 960,
			'product_grid'          => array(
				'default_rows'    => 3,
				'default_columns' => 3,
			),
		)
	);
}
add_action( 'after_setup_theme', 'shadow_digest_woocommerce_setup' );

/**
 * Crop the catalogue thumbnail to the plate the grid is ruled for.
 *
 * WooCommerce stores the catalogue crop as an option, not as theme support, and
 * offers `woocommerce_get_image_size_<size>` to override it in code. Digest sets
 * it here so the shape lives in the theme (where the grid that depends on it
 * lives) rather than in a database row a future admin can silently change.
 *
 * @since 1.0.11
 * @param array $size The image size WooCommerce is about to use.
 * @return array The size, cropped to 4:5.
 */
function shadow_digest_woocommerce_thumbnail_size( array $size ): array {
	$size['width']  = 480;
	$size['height'] = 600;
	$size['crop']   = 1;

	return $size;
}
add_filter( 'woocommerce_get_image_size_thumbnail', 'shadow_digest_woocommerce_thumbnail_size' );
add_filter( 'woocommerce_get_image_size_gallery_thumbnail', 'shadow_digest_woocommerce_thumbnail_size' );

/**
 * Keep WooCommerce's Customer Account block out of the masthead.
 *
 * WHY: on activation, WooCommerce auto-injects a `woocommerce/customer-account`
 * block into a block theme's header — at runtime, through its own template
 * registry, and NOT by writing to the database (there is no wp_template_part row
 * to edit; that was checked before writing this). On Digest it surfaced as a
 * stray, unstyled person-icon floating below the section navigation, outside the
 * masthead's grid and belonging to nothing.
 *
 * Digest already renders account and cart links, deliberately placed and styled
 * to match, in the utility bar — see shadow_digest_shop_links(). Two account
 * links in one header, one of them uninvited and misplaced, is worse than either
 * alone.
 *
 * This drops the block wherever it renders, which is the honest thing to write:
 * WooCommerce only ever auto-inserts it into the header, and Digest's own header
 * is the only place it has ever appeared. Should an editor one day want the block
 * on a page deliberately, this is the one line to revisit.
 *
 * Harmless with no WooCommerce installed: the block name simply never appears.
 *
 * @since 1.0.10
 *
 * @param string $block_content The block's rendered HTML.
 * @param array  $block         The parsed block.
 * @return string Empty for WooCommerce's account block; the content untouched otherwise.
 */
function shadow_digest_drop_woo_account_block( string $block_content, array $block ): string {
	if ( ( $block['blockName'] ?? '' ) === 'woocommerce/customer-account' ) {
		return '';
	}

	return $block_content;
}
add_filter( 'render_block', 'shadow_digest_drop_woo_account_block', 10, 2 );
