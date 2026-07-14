<?php
/**
 * Re-seed a handful of posts with DELIBERATELY BADLY-SHAPED featured images.
 *
 * WHY THIS EXISTS: local-seed-images.php gives every post an 800x450 image. That
 * is a 16:9 fixture, and 16:9 is the one aspect ratio the article template
 * happens to look fine at. With every image the same shape, the sandbox is
 * structurally incapable of reproducing the bug it is supposed to catch — a
 * portrait upload rendering as a two-screen-tall wall of image, a panorama
 * rendering as a letterbox slit, a 320px thumbnail upscaled into a blurry
 * smear. Twenty-two identical fixtures is not twenty-two test cases; it is one
 * test case run twenty-two times.
 *
 * That is the same failure mode as the outage this sandbox was built for: a
 * check that is green because it never actually exercises the broken path.
 *
 * So: give the canary posts the shapes a real newsroom actually receives.
 *
 *   portrait  1600x2400  (2:3)   a phone photo, held upright
 *   panorama  2400x800   (3:1)   a landscape crop / a scoreboard shot
 *   square    1800x1800  (1:1)   an Instagram-sourced image
 *   tiny       320x180   (16:9)  an undersized upload — must NOT be upscaled
 *   tall      1200x2400  (1:2)   a full-length figure, the worst realistic case
 *
 * The four real ones are all >= 1200px wide ON PURPOSE. An earlier version of
 * this fixture made them 800-1000px, which is NARROWER than the 1120px article
 * sheet — so every single one tripped the theme's do-not-upscale rule and
 * rendered short of its own margins. That made a correct rule look broken and
 * sent me tuning CSS that was already right. Real editorial photography is
 * 1600-4000px wide and always fills the sheet; a fixture that isn't is not
 * testing the thing it claims to test. Only `tiny` is undersized, because that
 * is the one case the no-upscale rule actually exists for.
 *
 * Run AFTER local-seed-images.php; this replaces those posts' thumbnails.
 *
 * Idempotent: re-running regenerates the same five images deterministically.
 */

if ( ! function_exists( 'imagecreatetruecolor' ) ) {
	echo "GD not available — skipping shape seed\n";
	return;
}

/**
 * Draw a test image whose CONTENT makes cropping visible.
 *
 * A plain colour field cannot show you that `object-fit: cover` has silently
 * eaten someone's head. So each image gets: a 24px border inset from the edge, a
 * centre crosshair, and its own dimensions printed large in the middle. If the
 * border is clipped on two sides but the crosshair is centred, the crop is
 * behaving. If the text is cut in half, it is not.
 */
function shadow_digest_shape_image( string $path, int $w, int $h, array $rgb ): void {
	$img = imagecreatetruecolor( $w, $h );

	$paper = imagecolorallocate( $img, 245, 241, 232 );
	imagefill( $img, 0, 0, $paper );

	list( $r, $g, $b ) = $rgb;
	$ink   = imagecolorallocate( $img, (int) $r, (int) $g, (int) $b );
	$faint = imagecolorallocate( $img, (int) ( $r * 0.35 + 160 ), (int) ( $g * 0.35 + 160 ), (int) ( $b * 0.35 + 160 ) );

	// Diagonal hatching, so any stretch (as opposed to crop) is obvious: a
	// non-uniform scale turns 45-degree lines into some other angle.
	for ( $x = -$h; $x < $w; $x += 40 ) {
		imagefilledpolygon(
			$img,
			array( $x, 0, $x + 12, 0, $x + 12 + $h, $h, $x + $h, $h ),
			$faint
		);
	}

	// Safe-area border, inset 24px. Cropping eats this first, and asymmetrically
	// — which is exactly what you want to be able to see.
	imagesetthickness( $img, 6 );
	imagerectangle( $img, 24, 24, $w - 25, $h - 25, $ink );

	// Centre crosshair — proves the crop stayed centred.
	imagesetthickness( $img, 4 );
	imageline( $img, intdiv( $w, 2 ) - 60, intdiv( $h, 2 ), intdiv( $w, 2 ) + 60, intdiv( $h, 2 ), $ink );
	imageline( $img, intdiv( $w, 2 ), intdiv( $h, 2 ) - 60, intdiv( $w, 2 ), intdiv( $h, 2 ) + 60, $ink );

	// The dimensions, printed. GD's built-in font is tiny, so scale it up by
	// drawing to a small canvas and resampling — no font file needed, and this
	// script must never depend on one being installed.
	$label = $w . ' x ' . $h;
	$tw    = imagefontwidth( 5 ) * strlen( $label );
	$th    = imagefontheight( 5 );
	$strip = imagecreatetruecolor( $tw, $th );
	imagefill( $strip, 0, 0, imagecolorallocate( $strip, 245, 241, 232 ) );
	imagestring( $strip, 5, 0, 0, $label, imagecolorallocate( $strip, (int) $r, (int) $g, (int) $b ) );

	$scale = max( 2, intdiv( min( $w, $h ), 90 ) );
	imagecopyresized(
		$img,
		$strip,
		intdiv( $w, 2 ) - intdiv( $tw * $scale, 2 ),
		intdiv( $h, 2 ) + 80,
		0,
		0,
		$tw * $scale,
		$th * $scale,
		$tw,
		$th
	);
	imagedestroy( $strip );

	imagepng( $img, $path, 6 );
	imagedestroy( $img );
}

$shapes = array(
	array( 'portrait', 1600, 2400, array( 63, 58, 51 ) ),
	array( 'panorama', 2400, 800, array( 110, 103, 92 ) ),
	array( 'square', 1024, 1024, array( 63, 58, 51 ) ),
	array( 'tiny', 320, 180, array( 110, 103, 92 ) ),
	array( 'tall', 1200, 2400, array( 63, 58, 51 ) ),
);

// Take the five most recent posts — deterministic, and they are the ones a human
// clicks first from the front page.
$posts = get_posts(
	array(
		'numberposts' => 5,
		'post_status' => 'publish',
		'orderby'     => 'ID',
		'order'       => 'ASC',
	)
);

if ( count( $posts ) < count( $shapes ) ) {
	echo 'only ' . count( $posts ) . " posts found — run local-seed-front.php first\n";
	return;
}

$uploads = wp_upload_dir();

foreach ( $shapes as $i => $shape ) {
	list( $name, $w, $h, $rgb ) = $shape;
	$post = $posts[ $i ];

	$filename = "digest-shape-{$name}-{$w}x{$h}.png";
	$path     = trailingslashit( $uploads['path'] ) . $filename;

	shadow_digest_shape_image( $path, $w, $h, $rgb );

	$attachment_id = wp_insert_attachment(
		array(
			'post_mime_type' => 'image/png',
			'post_title'     => "Shape fixture: {$name} ({$w}x{$h})",
			'post_status'    => 'inherit',
		),
		$path,
		$post->ID
	);

	require_once ABSPATH . 'wp-admin/includes/image.php';
	wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $path ) );

	// An attachment with NO alt meta makes WordPress fall back to the post title —
	// raw, entities and all — so a title containing `&apos;` produces
	// alt="CNBC&amp;apos;s…" and a screen reader announces "ampersand apos
	// semicolon". Store decoded plain text, which is what the media library does.
	update_post_meta(
		$attachment_id,
		'_wp_attachment_image_alt',
		html_entity_decode( $post->post_title, ENT_QUOTES | ENT_HTML5, 'UTF-8' )
	);

	set_post_thumbnail( $post->ID, $attachment_id );

	echo "  {$post->post_name}: {$name} {$w}x{$h}\n";
}

echo "seeded " . count( $shapes ) . " shape fixtures\n";
