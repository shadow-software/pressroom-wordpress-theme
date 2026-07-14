<?php
/**
 * Seed the sandbox's WooCommerce with products, so the shop pages can be judged.
 *
 * WHY THIS EXISTS: an empty shop, an empty cart and a logged-out My Account are
 * three pages with almost no markup in them. Styling against those is styling
 * against nothing — the product grid, the quantity stepper, the coupon row, the
 * cart totals table and the order-review table would all ship completely
 * unexercised, exactly the way the featured image shipped unexercised against 22
 * identical 16:9 fixtures. A fixture that cannot show you the bug is not a test.
 *
 * So: give the sandbox a shop with the things that actually break.
 *
 *   - a product with a SALE price      (two <del>/<ins> prices in one line)
 *   - a product that is OUT OF STOCK   (a stock badge, a disabled button)
 *   - a product with NO IMAGE          (Woo prints a grey placeholder)
 *   - a LONG product name              (wraps, and must not blow the grid)
 *   - a product with a real image       (the ordinary case)
 *   - a VARIABLE product                (a <select>, a price RANGE)
 *
 * Idempotent: re-running updates the same products rather than duplicating them,
 * so this is safe to call from local-wp.sh on every build.
 *
 * Run AFTER WooCommerce is installed and activated.
 */

if ( ! class_exists( 'WooCommerce' ) ) {
	echo "WooCommerce not active — skipping shop seed\n";
	return;
}

/**
 * Draw a product photograph.
 *
 * Not a colour swatch: a plate with a border, a maker's mark and a printed name,
 * so that a wrong crop or a stretched aspect ratio is VISIBLE rather than merely
 * measurable. Product images are the one part of a shop the theme does not
 * control the shape of.
 */
function shadow_digest_woo_image( string $path, int $w, int $h, string $label, array $rgb ): void {
	$img = imagecreatetruecolor( $w, $h );

	$paper = imagecolorallocate( $img, 231, 223, 206 );
	imagefill( $img, 0, 0, $paper );

	list( $r, $g, $b ) = $rgb;
	$ink   = imagecolorallocate( $img, (int) $r, (int) $g, (int) $b );
	$faint = imagecolorallocate( $img, (int) ( $r * 0.2 + 190 ), (int) ( $g * 0.2 + 190 ), (int) ( $b * 0.2 + 190 ) );

	// A plate: concentric rules, the way a catalogue engraving is framed.
	imagesetthickness( $img, 3 );
	imagerectangle( $img, 18, 18, $w - 19, $h - 19, $faint );
	imagesetthickness( $img, 6 );
	imagerectangle( $img, 30, 30, $w - 31, $h - 31, $ink );

	/*
	 * A centred device, so a crop that is off-centre shows immediately.
	 *
	 * It is drawn SOLID, and large. The first version of this fixture drew a thin
	 * outline ring on an otherwise empty middle, with all its real detail — the
	 * frame, the label — around the edges. WooCommerce's catalogue thumbnail is a
	 * centre crop, so it threw away every edge and kept the empty middle: the shop
	 * rendered a grid of blank beige rectangles, and it took a screenshot to notice,
	 * because the CSS was measurably correct the whole time. A fixture whose subject
	 * is not where the subject goes is not a fixture.
	 */
	$cx = intdiv( $w, 2 );
	$cy = intdiv( $h, 2 );
	$rad = intdiv( min( $w, $h ), 2 );

	imagefilledellipse( $img, $cx, $cy, $rad, $rad, $faint );
	imagesetthickness( $img, 8 );
	imageellipse( $img, $cx, $cy, $rad, $rad, $ink );
	imageellipse( $img, $cx, $cy, intdiv( $rad * 2, 3 ), intdiv( $rad * 2, 3 ), $ink );
	imagesetthickness( $img, 5 );
	imageline( $img, $cx - $rad, $cy, $cx + $rad, $cy, $ink );
	imageline( $img, $cx, $cy - $rad, $cx, $cy + $rad, $ink );

	// The label, printed. GD's bitmap font is tiny; scale it by resampling, so
	// this script never needs a TTF to be installed on the machine.
	$tw    = imagefontwidth( 5 ) * strlen( $label );
	$th    = imagefontheight( 5 );
	$strip = imagecreatetruecolor( $tw, $th );
	imagefill( $strip, 0, 0, imagecolorallocate( $strip, 231, 223, 206 ) );
	imagestring( $strip, 5, 0, 0, $label, imagecolorallocate( $strip, (int) $r, (int) $g, (int) $b ) );

	// Centred, and inside the crop box — not down at the foot of the plate where a
	// centre crop will simply remove it.
	$scale = max( 2, intdiv( min( $w, $h ), 130 ) );
	imagecopyresized(
		$img,
		$strip,
		$cx - intdiv( $tw * $scale, 2 ),
		$cy + intdiv( $rad, 3 ),
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

/**
 * Attach a generated image to a product, or return 0 to leave it imageless.
 */
function shadow_digest_woo_attach( int $product_id, string $slug, int $w, int $h, string $label, array $rgb ): int {
	$uploads  = wp_upload_dir();
	$filename = "digest-shop-{$slug}.png";
	$path     = trailingslashit( $uploads['path'] ) . $filename;

	shadow_digest_woo_image( $path, $w, $h, $label, $rgb );

	$attachment_id = wp_insert_attachment(
		array(
			'post_mime_type' => 'image/png',
			'post_title'     => $label,
			'post_status'    => 'inherit',
		),
		$path,
		$product_id
	);

	require_once ABSPATH . 'wp-admin/includes/image.php';
	wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $path ) );

	return (int) $attachment_id;
}

/*
 * The shop's stock. Deliberately awkward — see the header. Product images are
 * given DIFFERENT aspect ratios on purpose (square, portrait, panorama): Woo's
 * grid puts them side by side, and a theme that does not normalise them gets a
 * ragged row of different-height cards.
 */
$catalogue = array(
	array(
		'slug'    => 'ranging-scope',
		'name'    => 'The Ranging Scope, Mk. IV',
		'price'   => '389.00',
		'sale'    => '',
		'stock'   => 'instock',
		'image'   => array( 1200, 1200, 'MK IV', array( 63, 58, 51 ) ),
		'excerpt' => 'A field scope of the old school: brass, glass, and no electronics to fail you in the wet.',
	),
	array(
		// A sale price prints <del> and <ins> in the same line. Untreated, they
		// look like a mistake rather than a markdown.
		'slug'    => 'field-notebook',
		'name'    => 'Field Notebook, Waxed',
		'price'   => '24.00',
		'sale'    => '18.00',
		'stock'   => 'instock',
		'image'   => array( 1000, 1400, 'NOTEBOOK', array( 110, 103, 92 ) ),
		'excerpt' => 'Ninety-six pages, waxed cover, sewn spine. Takes a pencil in the rain.',
	),
	array(
		// Out of stock: a badge over the image, and a button that must not look
		// like it is waiting to be pressed.
		'slug'    => 'brass-caliper',
		'name'    => 'Brass Caliper',
		'price'   => '62.00',
		'sale'    => '',
		'stock'   => 'outofstock',
		'image'   => array( 1400, 900, 'CALIPER', array( 63, 58, 51 ) ),
		'excerpt' => 'Machined brass, vernier scale, in a fitted case.',
	),
	array(
		// No image at all — Woo substitutes a grey placeholder, which on a paper
		// theme is a hole punched in the page unless it is handled.
		'slug'    => 'annual-subscription',
		'name'    => 'Annual Subscription — Print & Digital, Delivered Weekly',
		'price'   => '96.00',
		'sale'    => '',
		'stock'   => 'instock',
		'image'   => null,
		'excerpt' => 'Fifty-two issues, posted. Includes the digital archive back to the first number.',
	),
	array(
		'slug'    => 'canvas-satchel',
		'name'    => 'Canvas Satchel',
		'price'   => '145.00',
		'sale'    => '',
		'stock'   => 'instock',
		'image'   => array( 1200, 1200, 'SATCHEL', array( 107, 31, 31 ) ),
		'excerpt' => 'Waxed cotton duck, bridle leather straps, solid brass furniture.',
	),
	array(
		'slug'    => 'bound-volume',
		'name'    => 'Bound Volume of the Year',
		'price'   => '210.00',
		'sale'    => '',
		'stock'   => 'instock',
		'image'   => array( 1000, 1500, 'VOLUME', array( 63, 58, 51 ) ),
		'excerpt' => 'Every issue of the year, cloth-bound and gilt-stamped.',
	),
);

$made = 0;

foreach ( $catalogue as $item ) {
	// Idempotent: reuse the product if this slug already exists.
	$existing = get_page_by_path( $item['slug'], OBJECT, 'product' );

	$product = $existing ? new WC_Product_Simple( $existing->ID ) : new WC_Product_Simple();

	$product->set_name( $item['name'] );
	$product->set_slug( $item['slug'] );
	$product->set_status( 'publish' );
	$product->set_catalog_visibility( 'visible' );
	$product->set_regular_price( $item['price'] );
	$product->set_sale_price( $item['sale'] );
	$product->set_short_description( $item['excerpt'] );
	$product->set_description(
		$item['excerpt'] . ' Made to a specification that has not changed in forty years, because it did not need to.'
	);
	$product->set_stock_status( $item['stock'] );
	$product->set_sku( strtoupper( str_replace( '-', '', $item['slug'] ) ) );

	$product_id = $product->save();

	/*
	 * Attach an image if the product HAS NO USABLE ONE.
	 *
	 * "Usable" is the operative word, and the reason this is not simply
	 * `! $product->get_image_id()`. That was the original guard, and it is a trap:
	 * an image ID can survive on a product whose attachment (and whose FILE) has
	 * been deleted. When that happens the guard sees an ID, concludes there is
	 * already an image, and skips drawing — so re-seeding a shop whose uploads had
	 * been cleared produced seven products with no pictures at all, reported
	 * "seeded 7 products", and exited 0.
	 *
	 * A fixture script that cheerfully reports success while producing an empty
	 * shop is worse than one that fails, because you believe it. Ask the question
	 * that actually matters: is there a file on disk?
	 */
	$image_id   = $product->get_image_id();
	$has_image  = $image_id && file_exists( (string) get_attached_file( $image_id ) );

	if ( $item['image'] && ! $has_image ) {
		list( $w, $h, $label, $rgb ) = $item['image'];
		$attachment_id               = shadow_digest_woo_attach( $product_id, $item['slug'], $w, $h, $label, $rgb );
		$product->set_image_id( $attachment_id );
		$product->save();
	}

	++$made;
	echo "  product: {$item['slug']} ({$item['name']})\n";
}

/*
 * A variable product — a price RANGE and a <select>, neither of which any simple
 * product will ever produce. Woo's variation form is one of the ugliest things it
 * prints; leaving it out of the fixture guarantees leaving it out of the theme.
 */
$existing_var = get_page_by_path( 'shooting-jacket', OBJECT, 'product' );
$variable     = $existing_var ? new WC_Product_Variable( $existing_var->ID ) : new WC_Product_Variable();

$variable->set_name( 'Shooting Jacket' );
$variable->set_slug( 'shooting-jacket' );
$variable->set_status( 'publish' );
$variable->set_catalog_visibility( 'visible' );
$variable->set_short_description( 'Moleskin, leather-faced at the shoulder. Cut for a stock, not a catwalk.' );
$variable->set_description( 'Moleskin, leather-faced at the shoulder. Cut for a stock, not a catwalk.' );
$variable->set_sku( 'JACKET' );

$attribute = new WC_Product_Attribute();
$attribute->set_name( 'Size' );
$attribute->set_options( array( 'Small', 'Medium', 'Large' ) );
$attribute->set_visible( true );
$attribute->set_variation( true );
$variable->set_attributes( array( $attribute ) );

$variable_id = $variable->save();

$jacket_id  = $variable->get_image_id();
$jacket_has = $jacket_id && file_exists( (string) get_attached_file( $jacket_id ) );

if ( ! $jacket_has ) {
	$variable->set_image_id( shadow_digest_woo_attach( $variable_id, 'shooting-jacket', 1200, 1500, 'JACKET', array( 107, 31, 31 ) ) );
	$variable->save();
}

// Prices differ across variations, so the catalogue prints a RANGE ("£180 – £210").
$sizes = array(
	'Small'  => '180.00',
	'Medium' => '195.00',
	'Large'  => '210.00',
);

foreach ( $sizes as $size => $price ) {
	$slug     = 'shooting-jacket-' . strtolower( $size );
	$existing = get_page_by_path( $slug, OBJECT, 'product_variation' );

	$variation = $existing ? new WC_Product_Variation( $existing->ID ) : new WC_Product_Variation();
	$variation->set_parent_id( $variable_id );
	$variation->set_slug( $slug );
	$variation->set_attributes( array( 'size' => $size ) );
	$variation->set_regular_price( $price );
	$variation->set_status( 'publish' );
	$variation->save();
}

WC_Product_Variable::sync( $variable_id );
++$made;
echo "  product: shooting-jacket (Shooting Jacket — 3 variations)\n";

/*
 * A coupon, so the cart's coupon row is exercised rather than assumed.
 */
if ( ! get_page_by_title( 'SUBSCRIBER', OBJECT, 'shop_coupon' ) ) {
	$coupon = new WC_Coupon();
	$coupon->set_code( 'SUBSCRIBER' );
	$coupon->set_discount_type( 'percent' );
	$coupon->set_amount( 10 );
	$coupon->set_description( 'Ten per cent, for subscribers.' );
	$coupon->save();
	echo "  coupon: SUBSCRIBER (10%)\n";
}

/*
 * Sell to somewhere, or Woo prints no prices at all and every page above renders
 * a shop with blank price columns — which would look like a CSS bug and is not.
 */
update_option( 'woocommerce_store_address', '1 Press Row' );
update_option( 'woocommerce_store_city', 'Chicago' );
update_option( 'woocommerce_default_country', 'US:IL' );
update_option( 'woocommerce_store_postcode', '60601' );
update_option( 'woocommerce_currency', 'USD' );
update_option( 'woocommerce_enable_coupons', 'yes' );

// Guest checkout, so /checkout/ can be screenshotted without a login.
update_option( 'woocommerce_enable_guest_checkout', 'yes' );

// The block cart/checkout are the modern default and are what BOTH live sites
// run, so the sandbox must run them too. install_pages already used the block
// markup; this only makes the intent explicit and re-asserts it on reseed.
update_option( 'woocommerce_feature_cart_checkout_blocks_enabled', 'yes' );

/*
 * Prove it. This script's whole job is to make the shop LOOK at, and it has
 * already once produced seven products with no pictures whatsoever and reported
 * "seeded 7 products" with an exit code of 0. Never again: count the products that
 * are supposed to have an image and actually do, and say so out loud.
 */
$want    = 0;
$have    = 0;
$missing = array();

foreach ( wc_get_products( array( 'limit' => -1, 'status' => 'publish' ) ) as $p ) {
	if ( 'annual-subscription' === $p->get_slug() ) {
		continue; // Deliberately imageless — it is the placeholder fixture.
	}

	++$want;
	$id = $p->get_image_id();

	if ( $id && file_exists( (string) get_attached_file( $id ) ) ) {
		++$have;
	} else {
		$missing[] = $p->get_slug();
	}
}

echo "seeded {$made} products, 1 coupon\n";
echo "images: {$have}/{$want} products have a file on disk\n";

if ( $missing ) {
	echo '!! NO IMAGE FILE: ' . implode( ', ', $missing ) . "\n";
	echo "!! the shop will render as empty plates — this is a BROKEN fixture, not a CSS bug\n";
}
