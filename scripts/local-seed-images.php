<?php
/**
 * Give every seeded post a featured image.
 *
 * WHY THIS EXISTS: local-seed.php and local-seed-front.php between them produce
 * a full front page and a canary article, but neither attaches an image to
 * anything. That leaves post-featured-image (wired up in templates/single.html
 * and the front-page lead grid) rendering nothing, which means the sandbox has
 * never once exercised responsive image markup, srcset generation, or the
 * image-heavy paths of a Lighthouse/Screaming Frog audit — the exact gap that
 * caused the front page to "render perfectly" during the 2026-07-13 outage
 * while being subtly wrong everywhere else.
 *
 * Images are generated locally with GD — abstract halftone/rule compositions in
 * the theme's own accent colour, not stock photos standing in for real
 * editorial photography. That is a deliberate choice: this script must never
 * reach out over the network (see the get_avatar() gotcha in CLAUDE.md — this
 * box's outbound HTTP is not to be relied on), and inventing fake photojournalism
 * to dress a test fixture would be worse than an honest abstraction.
 *
 * Idempotent: skips any post that already has a featured image.
 */

if ( ! function_exists( 'imagecreatetruecolor' ) ) {
	echo "GD not available — skipping image seed\n";
	return;
}

/**
 * Draw one procedural editorial image and return its local file path.
 *
 * A halftone-dot field plus a folio rule, in the accent colour derived from the
 * post's section — enough visual variety across posts to be a meaningful
 * Lighthouke/CLS/LCP fixture without pretending to be a photograph.
 */
function shadow_digest_seed_generate_image( string $path, string $seed, array $rgb ): void {
	// 800x450, not 1600x900: this sandbox runs every seed script under a 10s
	// max_execution_time (see local-wp.sh) specifically so a real recursion dies
	// fast instead of hanging a production box. At 1600x900 with a 28px halftone
	// cell, 22 images cost ~1,800 imagefilledellipse() calls apiece — about 9.5s
	// of real CPU, which silently ate the whole budget and killed the seed step
	// with no error. Half the linear dimensions is a quarter the fill calls, and
	// still plenty of detail for a fixture nobody views above ~800px wide.
	$width  = 800;
	$height = 450;

	$img = imagecreatetruecolor( $width, $height );

	$paper = imagecolorallocate( $img, 245, 241, 232 );
	imagefill( $img, 0, 0, $paper );

	list($r, $g, $b) = $rgb;
	$ink  = imagecolorallocate( $img, (int) $r, (int) $g, (int) $b );
	$dark = imagecolorallocate( $img, (int) ( $r * 0.5 ), (int) ( $g * 0.5 ), (int) ( $b * 0.5 ) );

	// A deterministic pseudo-random stream, seeded from the post slug, so the
	// same post always regenerates the same image instead of a new one on every
	// reseed (a diff-noisy attachments table makes local-assert.php's job harder
	// for no benefit).
	$state = crc32( $seed );
	$rand  = static function () use ( &$state ) {
		$state = ( $state * 1103515245 + 12345 ) & 0x7FFFFFFF;
		return $state / 0x7FFFFFFF;
	};

	// Halftone dot field: bigger, denser dots toward one corner, echoing the
	// newspaper halftone texture already used in the theme's own CSS.
	$cell = 28;
	for ( $y = 0; $y < $height; $y += $cell ) {
		for ( $x = 0; $x < $width; $x += $cell ) {
			$fade = 1 - ( ( $x + $y ) / ( $width + $height ) );
			$r2   = ( $cell / 2 ) * $fade * ( 0.35 + 0.65 * $rand() );

			if ( $r2 < 1 ) {
				continue;
			}

			imagefilledellipse(
				$img,
				(int) ( $x + $cell / 2 ),
				(int) ( $y + $cell / 2 ),
				(int) ( $r2 * 2 ),
				(int) ( $r2 * 2 ),
				$ink
			);
		}
	}

	// A folio rule, top and bottom, matching the masthead's double-rule motif.
	imagefilledrectangle( $img, 0, 0, $width, 6, $dark );
	imagefilledrectangle( $img, 0, $height - 6, $width, $height, $dark );

	// JPEG, not PNG: a dense field of small filled circles is exactly the kind of
	// high-frequency detail lossless PNG compresses badly — these came out to
	// ~190KB each as PNG, more than a real optimised editorial photo, which made
	// the featured-image fixture a worse LCP citizen than genuine content would
	// be. JPEG at quality 82 renders this indistinguishably and lands under 40KB.
	imagejpeg( $img, $path, 82 );
	imagedestroy( $img );
}

$section_colours = array(
	'features'    => array( 107, 31, 31 ),
	'heritage'    => array( 90, 74, 46 ),
	'ballistics'  => array( 47, 62, 58 ),
	'gear'        => array( 58, 58, 70 ),
	'legislation' => array( 74, 46, 46 ),
	'events'      => array( 46, 66, 90 ),
	'uncategorized' => array( 80, 80, 80 ),
);

$upload_dir = wp_upload_dir();

if ( ! empty( $upload_dir['error'] ) ) {
	echo "upload dir error: {$upload_dir['error']}\n";
	return;
}

$posts = get_posts(
	array(
		'post_type'      => 'post',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
	)
);

/**
 * Draw a simple monogram avatar and return its local file path.
 *
 * Exercises the local-upload branch of shadow_digest_avatar() (see
 * inc/template-tags.php) so the sandbox never falls through to the Gravatar
 * network call that branch exists specifically to avoid.
 */
function shadow_digest_seed_generate_avatar( string $path, string $initials, array $rgb ): void {
	$size = 400;
	$img  = imagecreatetruecolor( $size, $size );

	list($r, $g, $b) = $rgb;
	$ink = imagecolorallocate( $img, (int) $r, (int) $g, (int) $b );
	imagefill( $img, 0, 0, $ink );

	$paper = imagecolorallocate( $img, 245, 241, 232 );
	$font  = 5; // GD's largest built-in bitmap font.
	$tw    = imagefontwidth( $font ) * strlen( $initials );
	$th    = imagefontheight( $font );

	imagestring( $img, $font, (int) ( ( $size - $tw ) / 2 ), (int) ( ( $size - $th ) / 2 ), $initials, $paper );

	imagepng( $img, $path, 6 );
	imagedestroy( $img );
}

$authors = get_users(
	array(
		'role__in' => array( 'administrator', 'editor', 'author', 'contributor' ),
	)
);

$avatars = 0;

foreach ( $authors as $author ) {
	if ( (int) get_user_meta( $author->ID, 'shadow_digest_avatar', true ) ) {
		continue;
	}

	$names    = preg_split( '/\s+/', trim( $author->display_name ) );
	$initials = strtoupper( ( $names[0][0] ?? '' ) . ( $names[ count( $names ) - 1 ][0] ?? '' ) );

	$filename = 'digest-seed-avatar-' . $author->user_nicename . '.png';
	$tmp_path = trailingslashit( $upload_dir['path'] ) . $filename;

	shadow_digest_seed_generate_avatar( $tmp_path, $initials, array( 107, 31, 31 ) );

	$filetype = wp_check_filetype( $filename, null );

	$attachment_id = wp_insert_attachment(
		array(
			'post_mime_type' => $filetype['type'],
			'post_title'     => $author->display_name . ' avatar',
			'post_content'   => '',
			'post_status'    => 'inherit',
		),
		$tmp_path
	);

	if ( is_wp_error( $attachment_id ) ) {
		echo "  ✗ avatar for {$author->user_nicename}: " . $attachment_id->get_error_message() . "\n";
		continue;
	}

	require_once ABSPATH . 'wp-admin/includes/image.php';

	$attachment_data = wp_generate_attachment_metadata( $attachment_id, $tmp_path );
	wp_update_attachment_metadata( $attachment_id, $attachment_data );

	update_user_meta( $author->ID, 'shadow_digest_avatar', $attachment_id );
	++$avatars;
}

echo "avatars: {$avatars} attached\n";

$attached = 0;
$skipped  = 0;

foreach ( $posts as $post ) {
	if ( has_post_thumbnail( $post ) ) {
		++$skipped;
		continue;
	}

	$categories = get_the_category( $post->ID );
	$slug       = $categories ? $categories[0]->slug : 'uncategorized';
	$rgb        = $section_colours[ $slug ] ?? array( 90, 90, 90 );

	$filename = 'digest-seed-' . $post->post_name . '.jpg';
	$tmp_path = trailingslashit( $upload_dir['path'] ) . $filename;

	shadow_digest_seed_generate_image( $tmp_path, $post->post_name, $rgb );

	$filetype = wp_check_filetype( $filename, null );

	$attachment_id = wp_insert_attachment(
		array(
			'post_mime_type' => $filetype['type'],
			'post_title'     => $post->post_title,
			'post_content'   => '',
			'post_status'    => 'inherit',
			'post_parent'    => $post->ID,
		),
		$tmp_path,
		$post->ID
	);

	if ( is_wp_error( $attachment_id ) ) {
		echo "  ✗ {$post->post_name}: " . $attachment_id->get_error_message() . "\n";
		continue;
	}

	require_once ABSPATH . 'wp-admin/includes/image.php';

	$attachment_data = wp_generate_attachment_metadata( $attachment_id, $tmp_path );
	wp_update_attachment_metadata( $attachment_id, $attachment_data );

	// Decoded, not raw. alt text is a plain-text attribute: WordPress escapes it on
	// output, so storing `CNBC&apos;s` here yields alt="CNBC&amp;apos;s" and a
	// screen reader announces "ampersand apos semicolon". The media library stores
	// decoded text and so does every real publisher; the seeder should too, or the
	// sandbox invents a bug that production does not have.
	update_post_meta(
		$attachment_id,
		'_wp_attachment_image_alt',
		sprintf(
			/* translators: %s: article headline. */
			__( 'Editorial illustration for "%s"', 'broadside' ),
			html_entity_decode( $post->post_title, ENT_QUOTES | ENT_HTML5, 'UTF-8' )
		)
	);

	set_post_thumbnail( $post->ID, $attachment_id );
	++$attached;
}

echo "images: {$attached} attached, {$skipped} already had one\n";
