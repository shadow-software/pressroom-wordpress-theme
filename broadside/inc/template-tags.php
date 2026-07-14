<?php
/**
 * Template tags — the small helpers the templates and patterns call.
 *
 * @package Broadside
 * @since   1.0.0
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

/**
 * Render a year as Roman numerals.
 *
 * The utility bar prints "Est. MCMXXVI" and the footer prints "© MMXXVI".
 * Roman numerals are a newspaper affectation and a nice one, but they must not
 * be the only place the year appears — screen readers and search engines both
 * want the digits, so callers pair this with a plain year in the markup.
 *
 * @since 1.0.0
 * @param int $year A year in the Gregorian calendar.
 * @return string The year in Roman numerals, or the empty string if out of range.
 */
function shadow_digest_roman( int $year ): string {
	if ( $year < 1 || $year > 3999 ) {
		return '';
	}

	$numerals = array(
		'M'  => 1000,
		'CM' => 900,
		'D'  => 500,
		'CD' => 400,
		'C'  => 100,
		'XC' => 90,
		'L'  => 50,
		'XL' => 40,
		'X'  => 10,
		'IX' => 9,
		'V'  => 5,
		'IV' => 4,
		'I'  => 1,
	);

	$roman = '';

	foreach ( $numerals as $letter => $value ) {
		while ( $year >= $value ) {
			$roman .= $letter;
			$year  -= $value;
		}
	}

	return $roman;
}

/**
 * Estimate a post's reading time.
 *
 * 220 words a minute is the figure most newsrooms use for considered,
 * non-technical prose. The result is deliberately coarse — it is a courtesy to
 * the reader, not a measurement.
 *
 * @since 1.0.0
 * @param int|WP_Post|null $post Optional. Post to measure. Defaults to the current post.
 * @return int Minutes, never less than one.
 */
function shadow_digest_reading_minutes( $post = null ): int {
	$post = get_post( $post );

	if ( ! $post instanceof WP_Post ) {
		return 1;
	}

	$words = str_word_count( wp_strip_all_tags( strip_shortcodes( $post->post_content ) ) );

	/**
	 * Filters the reading speed used to estimate reading time.
	 *
	 * @since 1.0.0
	 * @param int $wpm Words per minute.
	 */
	$wpm = (int) apply_filters( 'shadow_digest_words_per_minute', 220 );

	return max( 1, (int) ceil( $words / max( 1, $wpm ) ) );
}

/**
 * The publication's dateline, in the style a newspaper folio prints it.
 *
 * Uses the site's configured timezone and locale, so a site set to French
 * prints "SAMEDI 13 JUILLET 2026" without any extra work.
 *
 * @since 1.0.0
 * @return string An uppercase, localised long date.
 */
function shadow_digest_dateline(): string {
	$date = wp_date( (string) get_option( 'date_format', 'l, F j, Y' ) );

	if ( ! is_string( $date ) ) {
		return '';
	}

	// mb_strtoupper respects locale; strtoupper would mangle accented characters.
	return function_exists( 'mb_strtoupper' ) ? mb_strtoupper( $date ) : strtoupper( $date );
}

/**
 * The founding year as a plain integer.
 *
 * @since 1.0.0
 * @return int The configured year, or 0 if unset.
 */
function shadow_digest_founded(): int {
	return absint( shadow_digest_get( 'shadow_digest_founded' ) );
}

/**
 * Echo the utility bar — the thin uppercase strip above the nameplate.
 *
 * @since 1.0.0
 * @return void
 */
function shadow_digest_utility_bar(): void {
	$founded   = shadow_digest_founded();
	$city      = (string) shadow_digest_get( 'shadow_digest_city' );
	$strapline = (string) shadow_digest_get( 'shadow_digest_strapline' );
	?>
	<div class="digest-utility">
		<span class="digest-utility__est">
			<?php if ( $founded ) : ?>
				<?php
				printf(
					/* translators: 1: founding year in Roman numerals, 2: founding year in digits. */
					esc_html__( 'Est. %1$s', 'broadside' ),
					'<abbr title="' . esc_attr( (string) $founded ) . '">' . esc_html( shadow_digest_roman( $founded ) ) . '</abbr>'
				);
				?>
				<?php if ( '' !== $city ) : ?>
					<span aria-hidden="true"> · </span><?php echo esc_html( $city ); ?>
				<?php endif; ?>
			<?php elseif ( '' !== $city ) : ?>
				<?php echo esc_html( $city ); ?>
			<?php endif; ?>
		</span>

		<?php if ( '' !== $strapline ) : ?>
			<span class="digest-utility__strapline"><?php echo esc_html( $strapline ); ?></span>
		<?php endif; ?>

		<span class="digest-utility__links">
			<?php
			if ( has_nav_menu( 'utility' ) ) {
				wp_nav_menu(
					array(
						'theme_location' => 'utility',
						'container'      => false,
						'depth'          => 1,
						'items_wrap'     => '<ul class="digest-utility__menu">%3$s</ul>',
						'fallback_cb'    => false,
					)
				);
			}

			shadow_digest_shop_links();
			?>
		</span>
	</div>
	<?php
}

/**
 * Echo the WooCommerce account and cart links, in the utility bar.
 *
 * They go in the utility bar's third cell, which is otherwise EMPTY on both live
 * sites: it exists to hold a "utility" nav menu, and neither site has ever
 * assigned one. (That empty cell is also why the strapline used to sit 98px right
 * of centre — see the grid note on .digest-utility in digest.css.)
 *
 * EVERY WooCommerce call here is guarded, and the whole function is a no-op when
 * the plugin is absent. That is not defensiveness for its own sake: this theme is
 * going to the WordPress.org Theme Directory, which requires that it work on stock
 * WordPress with NO plugins installed. A theme that fatals — or even warns —
 * without WooCommerce could not be submitted at all. So:
 *
 *   - `class_exists( 'WooCommerce' )` gates the entire block.
 *   - wc_get_cart_url() / wc_get_page_permalink() are only ever called inside it.
 *   - The cart count comes from WC()->cart, which is null on some requests (REST,
 *     cron, and the admin), so it is null-checked rather than assumed.
 *
 * @since 1.0.10
 * @return void
 */
function shadow_digest_shop_links(): void {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	$account_url = wc_get_page_permalink( 'myaccount' );
	$cart_url    = wc_get_cart_url();

	// WC()->cart is not instantiated on every request — notably in the admin and
	// during REST/cron — so this must never be assumed to exist.
	$count = ( WC()->cart instanceof WC_Cart ) ? WC()->cart->get_cart_contents_count() : 0;
	?>
	<span class="digest-shop">
		<?php if ( $account_url ) : ?>
			<a class="digest-shop__link" href="<?php echo esc_url( $account_url ); ?>">
				<?php
				echo is_user_logged_in()
					? esc_html__( 'My Account', 'broadside' )
					: esc_html__( 'Log In', 'broadside' );
				?>
			</a>
		<?php endif; ?>

		<?php if ( $cart_url ) : ?>
			<a class="digest-shop__link digest-shop__cart" href="<?php echo esc_url( $cart_url ); ?>">
				<?php esc_html_e( 'Cart', 'broadside' ); ?>
				<?php if ( $count > 0 ) : ?>
					<span class="digest-shop__count"><?php echo esc_html( (string) $count ); ?></span>
				<?php endif; ?>
			</a>
		<?php endif; ?>
	</span>
	<?php
}

/**
 * Echo the folio rule — volume, dateline, motto, cover price.
 *
 * @since 1.0.0
 * @return void
 */
function shadow_digest_folio(): void {
	$volume = (string) shadow_digest_get( 'shadow_digest_volume' );
	$issue  = (string) shadow_digest_get( 'shadow_digest_issue' );
	$motto  = (string) shadow_digest_get( 'shadow_digest_motto' );
	$price  = (string) shadow_digest_get( 'shadow_digest_cover_price' );
	?>
	<div class="digest-folio">
		<span class="digest-folio__volume">
			<?php
			echo esc_html( trim( $volume . ( '' !== $volume && '' !== $issue ? ' · ' : '' ) . $issue ) );
			?>
		</span>

		<span class="digest-folio__dateline"><?php echo esc_html( shadow_digest_dateline() ); ?></span>

		<span class="digest-folio__motto">
			<?php
			echo esc_html( trim( $motto . ( '' !== $motto && '' !== $price ? ' · ' : '' ) . $price ) );
			?>
		</span>
	</div>
	<?php
}

/**
 * Echo the nameplate — the big blackletter title, or the site's custom logo.
 *
 * @since 1.0.0
 * @param bool $compact Whether to render the small nameplate used on article pages.
 * @return void
 */
function shadow_digest_nameplate( bool $compact = false ): void {
	$class = $compact ? 'digest-nameplate digest-nameplate--compact' : 'digest-nameplate';
	$name  = get_bloginfo( 'name', 'display' );

	// A custom logo, if one is set, replaces the type entirely.
	if ( has_custom_logo() ) {
		echo '<div class="' . esc_attr( $class ) . '">';
		the_custom_logo();
		echo '</div>';

		return;
	}

	// On the front page the nameplate is the h1; everywhere else the article
	// headline is, and the nameplate steps down to a link. Getting this wrong is
	// the single most common heading-order failure in newspaper themes.
	$tag = ( is_front_page() && ! $compact ) ? 'h1' : 'p';

	printf(
		'<%1$s class="%2$s"><a href="%3$s" rel="home">%4$s</a></%1$s>',
		esc_attr( $tag ),
		esc_attr( $class ),
		esc_url( home_url( '/' ) ),
		esc_html( $name )
	);
}

/**
 * Echo an entry's kicker — its primary category, set in small caps above the
 * headline.
 *
 * @since 1.0.0
 * @param int|WP_Post|null $post Optional. Defaults to the current post.
 * @return void
 */
function shadow_digest_kicker( $post = null ): void {
	$post = get_post( $post );

	if ( ! $post instanceof WP_Post ) {
		return;
	}

	$categories = get_the_category( $post->ID );

	if ( empty( $categories ) ) {
		return;
	}

	$primary = $categories[0];

	printf(
		'<a class="digest-kicker" href="%1$s">%2$s</a>',
		esc_url( (string) get_category_link( $primary->term_id ) ),
		esc_html( $primary->name )
	);
}

/**
 * Echo an author's avatar, without ever blocking the page on a third-party host.
 *
 * get_avatar() is a Gravatar call. On a server whose outbound HTTP is firewalled
 * — which is the normal, correct posture for a box behind a tunnel — that call
 * does not fail fast: it hangs until the socket times out. Two avatars on an
 * article page is two hangs, and the page 500s on a gateway timeout.
 *
 * So Broadside prefers, in order: a locally-uploaded avatar (a "shadow_digest_avatar"
 * attachment on the user), then Gravatar if the site has avatars switched on,
 * then a printed monogram — the author's initials in the display serif, which
 * costs nothing and looks deliberate rather than broken.
 *
 * @since 1.0.0
 * @param int    $user_id The author.
 * @param int    $size    The requested pixel size.
 * @param string $name    The author's display name, used for the alt text and the monogram.
 * @return void
 */
function shadow_digest_avatar( int $user_id, int $size, string $name ): void {

	// 1. A local upload always wins: it is fast, private, and cannot fail.
	$local = (int) get_user_meta( $user_id, 'shadow_digest_avatar', true );

	if ( $local ) {
		echo wp_get_attachment_image(
			$local,
			array( $size, $size ),
			false,
			array(
				'alt'     => $name,
				'loading' => 'lazy',
			)
		);

		return;
	}

	// 2. Gravatar, but only if the site actually wants it. get_option() is a
	// local read; get_avatar() is the network call, and it is only reached when
	// the site has opted in.
	if ( get_option( 'show_avatars' ) ) {
		$avatar = get_avatar( $user_id, $size, '', $name, array( 'loading' => 'lazy' ) );

		if ( is_string( $avatar ) && '' !== $avatar ) {
			echo $avatar; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_avatar() returns escaped markup.

			return;
		}
	}

	// 3. A monogram. Not a fallback so much as a house style.
	printf(
		'<span class="digest-monogram" aria-hidden="true">%s</span>',
		esc_html( shadow_digest_initials( $name ) )
	);
}

/**
 * Reduce a name to one or two initials.
 *
 * @since 1.0.0
 * @param string $name A display name.
 * @return string One or two uppercase letters.
 */
function shadow_digest_initials( string $name ): string {
	$parts = preg_split( '/\s+/', trim( $name ) );

	if ( ! is_array( $parts ) || empty( $parts[0] ) ) {
		return '';
	}

	$first = mb_substr( $parts[0], 0, 1 );
	$last  = count( $parts ) > 1 ? mb_substr( (string) end( $parts ), 0, 1 ) : '';

	return mb_strtoupper( $first . $last );
}

/**
 * Echo the newsletter box.
 *
 * Broadside renders the form and nothing else. It stores no addresses and sends no
 * mail: the form posts to whatever endpoint the publisher names in the
 * Customizer, and if they name none, no form is rendered. This is a deliberate
 * boundary — subscriber data is the publisher's to hold, and a theme that held
 * it would lose it the day the publisher switched themes.
 *
 * @since 1.0.0
 * @return void
 */
function shadow_digest_newsletter(): void {
	if ( ! shadow_digest_get( 'shadow_digest_newsletter_enable' ) ) {
		return;
	}

	$action = (string) shadow_digest_get( 'shadow_digest_newsletter_action' );
	$name   = (string) shadow_digest_get( 'shadow_digest_newsletter_name' );
	$blurb  = (string) shadow_digest_get( 'shadow_digest_newsletter_blurb' );
	$note   = (string) shadow_digest_get( 'shadow_digest_newsletter_note' );
	$count  = (string) shadow_digest_get( 'shadow_digest_newsletter_count' );
	$field  = (string) shadow_digest_get( 'shadow_digest_newsletter_field' );

	$id = wp_unique_id( 'digest-newsletter-' );
	?>
	<aside class="digest-newsletter" aria-labelledby="<?php echo esc_attr( $id ); ?>">
		<div class="digest-newsletter__pitch">
			<?php $eyebrow = (string) shadow_digest_get( 'shadow_digest_newsletter_eyebrow' ); ?>
			<?php if ( '' !== $eyebrow ) : ?>
				<p class="digest-newsletter__eyebrow"><?php echo esc_html( $eyebrow ); ?></p>
			<?php endif; ?>

			<h2 class="digest-newsletter__name" id="<?php echo esc_attr( $id ); ?>">
				<?php echo esc_html( $name ); ?>
			</h2>

			<?php if ( '' !== $blurb ) : ?>
				<p class="digest-newsletter__blurb"><?php echo esc_html( $blurb ); ?></p>
			<?php endif; ?>
		</div>

		<div class="digest-newsletter__signup">
			<?php if ( '' === $action ) : ?>

				<?php
				/*
				 * No endpoint configured. Rather than render a form that silently
				 * throws addresses away — which is what a decorative form does —
				 * Broadside renders nothing and, for an administrator who can fix it,
				 * says why.
				 */
				if ( current_user_can( 'edit_theme_options' ) ) :
					?>
					<p class="digest-newsletter__note">
						<?php esc_html_e( 'No signup endpoint is configured, so the form is hidden. Set one under Customizer → Broadside → Newsletter, or turn the box off.', 'broadside' ); ?>
					</p>
					<?php
				endif;
				?>

			<?php else : ?>

				<?php if ( '' !== $count ) : ?>
					<p class="digest-newsletter__count">
						<?php
						printf(
							/* translators: %s: a subscriber count, e.g. "48,000". */
							esc_html__( 'Join %s readers', 'broadside' ),
							esc_html( $count )
						);
						?>
					</p>
				<?php endif; ?>

				<form class="digest-newsletter__form" action="<?php echo esc_url( $action ); ?>" method="post">
					<label class="screen-reader-text" for="<?php echo esc_attr( $id ); ?>-email">
						<?php esc_html_e( 'Your email address', 'broadside' ); ?>
					</label>

					<input
						class="digest-newsletter__input"
						id="<?php echo esc_attr( $id ); ?>-email"
						type="email"
						name="<?php echo esc_attr( $field ); ?>"
						placeholder="<?php esc_attr_e( 'your@email.com', 'broadside' ); ?>"
						autocomplete="email"
						required
					/>

					<button class="digest-newsletter__button" type="submit">
						<?php esc_html_e( 'Subscribe', 'broadside' ); ?>
					</button>
				</form>

				<?php if ( '' !== $note ) : ?>
					<p class="digest-newsletter__note"><?php echo esc_html( $note ); ?></p>
				<?php endif; ?>

			<?php endif; ?>
		</div>
	</aside>
	<?php
}

/**
 * Echo the editorial-standards note printed at the foot of each article.
 *
 * @since 1.0.0
 * @return void
 */
function shadow_digest_standards_note(): void {
	$note = (string) shadow_digest_get( 'shadow_digest_standards' );

	if ( '' === trim( $note ) ) {
		return;
	}

	printf(
		'<p class="digest-standards">%s</p>',
		wp_kses_post( $note )
	);
}
