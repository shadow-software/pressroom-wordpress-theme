<?php
/**
 * Pressroom — theme bootstrap.
 *
 * Pressroom is a block theme. Layout lives in templates/ and parts/, design tokens
 * in theme.json. This file loads the small amount of PHP the theme needs: asset
 * enqueueing, the Customizer settings that let one codebase dress two different
 * mastheads, the editorial blocks, and the block patterns.
 *
 * Everything a publisher would want to change between two Pressroom sites — the
 * accent colour, the founding year, the city, the section names, the strapline —
 * is a Customizer setting, never a hard-coded string. That is what makes the
 * theme reusable rather than a one-off.
 *
 * @package Pressroom
 * @since   1.0.0
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

/**
 * Theme version. Kept in lockstep with the "Version:" header in style.css and
 * the "Stable tag:" in readme.txt. Used to bust asset caches on upgrade.
 */
define( 'SHADOW_DIGEST_VERSION', '1.1.0' );

/**
 * Absolute path to the theme directory, with a trailing slash.
 */
define( 'SHADOW_DIGEST_PATH', trailingslashit( get_template_directory() ) );

/**
 * Public URL of the theme directory, with a trailing slash.
 */
define( 'SHADOW_DIGEST_URL', trailingslashit( get_template_directory_uri() ) );

require_once SHADOW_DIGEST_PATH . 'inc/setup.php';
require_once SHADOW_DIGEST_PATH . 'inc/customizer.php';
require_once SHADOW_DIGEST_PATH . 'inc/template-tags.php';
require_once SHADOW_DIGEST_PATH . 'inc/blocks.php';
require_once SHADOW_DIGEST_PATH . 'inc/blocks-masthead.php';
require_once SHADOW_DIGEST_PATH . 'inc/patterns.php';
require_once SHADOW_DIGEST_PATH . 'inc/schema.php';
