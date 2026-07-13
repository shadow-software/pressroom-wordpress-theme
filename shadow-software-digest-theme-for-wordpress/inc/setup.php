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
 * Hand the featured image's true aspect ratio to CSS.
 *
 * WHY: templates/single.html taming of the hero (assets/css/digest.css, "THE
 * HERO") clamps the image's proportions into a band — wide panoramas stop being
 * letterbox slits, tall portraits stop being two-screen walls — while leaving
 * anything already reasonable completely untouched. To leave it untouched, the
 * CSS has to KNOW the image's real ratio, and CSS cannot read it: attr() is not
 * usable in arbitrary properties in any shipping browser, and the alternative —
 * hard-coding a fallback ratio — would silently force every image to that one
 * shape, which is the exact opposite of the intent.
 *
 * PHP already knows the dimensions, so it prints them as a custom property and
 * lets clamp() do the rest. No JavaScript, no layout shift: the ratio is in the
 * HTML on first paint, so the box reserves its final height before the image
 * has loaded a single byte. That is a CLS win, not just a cosmetic one.
 *
 * SAFETY: this reads attachment METADATA only. It never touches post content,
 * so it cannot re-enter the block parser — the failure that took the VPS down
 * on 2026-07-13 (see docs/INCIDENT-2026-07-13-vps-outage.md, and gate 2 of
 * scripts/deploy.sh, which greps for exactly that).
 *
 * @since 1.0.9
 *
 * @param string $block_content The block's rendered HTML.
 * @param array  $block         The parsed block.
 * @return string The block HTML, with --digest-hero-natural set on the figure.
 */
function shadow_digest_hero_ratio( string $block_content, array $block ): string {
	if ( ( $block['blockName'] ?? '' ) !== 'core/post-featured-image' ) {
		return $block_content;
	}

	if ( '' === trim( $block_content ) || ! is_singular() ) {
		return $block_content;
	}

	$thumbnail_id = get_post_thumbnail_id();

	if ( ! $thumbnail_id ) {
		return $block_content;
	}

	$meta = wp_get_attachment_metadata( $thumbnail_id );

	$width  = (int) ( $meta['width'] ?? 0 );
	$height = (int) ( $meta['height'] ?? 0 );

	if ( $width < 1 || $height < 1 ) {
		return $block_content;
	}

	$ratio = round( $width / $height, 4 );

	// Only the <figure> is targeted, and only its first occurrence — the block
	// renders exactly one. Merge onto any style attribute the block already
	// carries rather than clobbering it.
	$processor = new WP_HTML_Tag_Processor( $block_content );

	if ( ! $processor->next_tag( array( 'tag_name' => 'FIGURE' ) ) ) {
		return $block_content;
	}

	$existing = (string) $processor->get_attribute( 'style' );

	// The intrinsic width goes out too, so CSS can refuse to upscale. A 320x180
	// thumbnail was being stretched across the full 1120px sheet — a 3.5x blow-up,
	// visibly soft — and nothing stopped it, because CSS has no way to ask an
	// image how big it really is.
	$declaration = '--digest-hero-natural:' . $ratio . ';'
		. '--digest-hero-natural-w:' . $width . 'px;';

	$processor->set_attribute(
		'style',
		'' === trim( $existing ) ? $declaration : rtrim( $existing, '; ' ) . ';' . $declaration
	);

	return $processor->get_updated_html();
}
add_filter( 'render_block', 'shadow_digest_hero_ratio', 10, 2 );
