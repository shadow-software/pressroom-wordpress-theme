<?php
/**
 * Customizer settings — the theme's per-site branding surface.
 *
 * This file is the reason Pressroom can dress two different publications from one
 * codebase. Everything that differs between, say, a cannabis trade journal and a
 * shooting-sports journal — the accent colour, the founding year, the city of
 * record, the strapline, the newsletter's name, the cover price — is registered
 * here as a Customizer setting with a sensible default.
 *
 * No brand name, no colour and no section list is hard-coded anywhere else in
 * the theme. If you find yourself wanting to add one, add a setting here instead.
 *
 * @package Pressroom
 * @since   1.0.0
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

/**
 * The theme's settings and their defaults, in one place.
 *
 * Keyed by setting id. Each entry declares the default value, the sanitiser, the
 * control type, the section it belongs to, its label and its help text. The
 * registration function below walks this array, so adding a setting is a
 * one-line change rather than four.
 *
 * @since 1.0.0
 * @return array<string, array<string, mixed>> The setting definitions.
 */
function shadow_digest_settings(): array {
	$settings = array(

		/*
		 * ----------------------------------------------------------------
		 * Identity — who the publication is.
		 * ----------------------------------------------------------------
		 */

		'shadow_digest_strapline'          => array(
			'default'   => __( 'The Journal of Record', 'pressroom' ),
			'sanitize'  => 'sanitize_text_field',
			'type'      => 'text',
			'section'   => 'shadow_digest_identity',
			'label'     => __( 'Strapline', 'pressroom' ),
			'help'      => __( 'Sits in the centre of the utility bar, above the masthead. Example: “The Journal of Record for the American Marksman”.', 'pressroom' ),
			'transport' => 'postMessage',
		),

		'shadow_digest_founded'            => array(
			'default'   => '1926',
			'sanitize'  => 'shadow_digest_sanitize_year',
			'type'      => 'number',
			'section'   => 'shadow_digest_identity',
			'label'     => __( 'Year founded', 'pressroom' ),
			'help'      => __( 'A four-digit year. The theme renders it as Roman numerals in the utility bar (“Est. MCMXXVI”) and in plain digits in the footer.', 'pressroom' ),
			'transport' => 'postMessage',
		),

		'shadow_digest_city'               => array(
			'default'   => __( 'New York', 'pressroom' ),
			'sanitize'  => 'sanitize_text_field',
			'type'      => 'text',
			'section'   => 'shadow_digest_identity',
			'label'     => __( 'City of record', 'pressroom' ),
			'help'      => __( 'Printed beside the founding year: “Est. MCMXXVI · New York”.', 'pressroom' ),
			'transport' => 'postMessage',
		),

		'shadow_digest_motto'              => array(
			'default'   => __( 'Steady Hands, Straight Talk', 'pressroom' ),
			'sanitize'  => 'sanitize_text_field',
			'type'      => 'text',
			'section'   => 'shadow_digest_identity',
			'label'     => __( 'Motto', 'pressroom' ),
			'help'      => __( 'Right-hand side of the folio rule, beneath the masthead.', 'pressroom' ),
			'transport' => 'postMessage',
		),

		'shadow_digest_cover_price'        => array(
			'default'   => '$4.00',
			'sanitize'  => 'sanitize_text_field',
			'type'      => 'text',
			'section'   => 'shadow_digest_identity',
			'label'     => __( 'Cover price', 'pressroom' ),
			'help'      => __( 'Printed at the end of the folio rule. Leave empty to omit it.', 'pressroom' ),
			'transport' => 'postMessage',
		),

		/*
		 * ----------------------------------------------------------------
		 * Masthead — the two ears either side of the nameplate.
		 * ----------------------------------------------------------------
		 */

		'shadow_digest_ear_left_title'     => array(
			'default'   => __( 'Weekend Field Report', 'pressroom' ),
			'sanitize'  => 'sanitize_text_field',
			'type'      => 'text',
			'section'   => 'shadow_digest_masthead',
			'label'     => __( 'Left ear — heading', 'pressroom' ),
			'help'      => __( 'The small block to the left of the nameplate. Traditionally a weather or conditions report.', 'pressroom' ),
			'transport' => 'postMessage',
		),

		'shadow_digest_ear_left_body'      => array(
			'default'   => __( "Clear, wind 4–7 mph out of the west.\nIdeal conditions across the Northeast ranges.", 'pressroom' ),
			'sanitize'  => 'shadow_digest_sanitize_multiline',
			'type'      => 'textarea',
			'section'   => 'shadow_digest_masthead',
			'label'     => __( 'Left ear — text', 'pressroom' ),
			'help'      => __( 'Two short lines read best. Line breaks are preserved.', 'pressroom' ),
			'transport' => 'postMessage',
		),

		'shadow_digest_ear_right_title'    => array(
			'default'   => __( 'One Hundredth Year', 'pressroom' ),
			'sanitize'  => 'sanitize_text_field',
			'type'      => 'text',
			'section'   => 'shadow_digest_masthead',
			'label'     => __( 'Right ear — heading', 'pressroom' ),
			'help'      => __( 'The small block to the right of the nameplate. Traditionally the volume or anniversary.', 'pressroom' ),
			'transport' => 'postMessage',
		),

		'shadow_digest_ear_right_body'     => array(
			'default'   => __( "A century in the service of\nprecision, heritage & sport.", 'pressroom' ),
			'sanitize'  => 'shadow_digest_sanitize_multiline',
			'type'      => 'textarea',
			'section'   => 'shadow_digest_masthead',
			'label'     => __( 'Right ear — text', 'pressroom' ),
			'help'      => __( 'Two short lines read best. Line breaks are preserved.', 'pressroom' ),
			'transport' => 'postMessage',
		),

		'shadow_digest_volume'             => array(
			'default'   => __( 'Vol. C', 'pressroom' ),
			'sanitize'  => 'sanitize_text_field',
			'type'      => 'text',
			'section'   => 'shadow_digest_masthead',
			'label'     => __( 'Volume', 'pressroom' ),
			'help'      => __( 'Left-hand side of the folio rule. Paired with the issue number.', 'pressroom' ),
			'transport' => 'postMessage',
		),

		'shadow_digest_issue'              => array(
			'default'   => __( 'No. 28', 'pressroom' ),
			'sanitize'  => 'sanitize_text_field',
			'type'      => 'text',
			'section'   => 'shadow_digest_masthead',
			'label'     => __( 'Issue number', 'pressroom' ),
			'help'      => __( 'Printed after the volume: “Vol. C · No. 28”.', 'pressroom' ),
			'transport' => 'postMessage',
		),

		'shadow_digest_wordmark_font'      => array(
			'default'  => 'masthead',
			'sanitize' => 'shadow_digest_sanitize_wordmark_font',
			'type'     => 'select',
			'section'  => 'shadow_digest_masthead',
			'label'    => __( 'Nameplate typeface', 'pressroom' ),
			'help'     => __( 'Blackletter is the traditional newspaper nameplate. Choose the display serif for a cleaner, more modern masthead — or upload a custom logo under Site Identity to replace the type entirely.', 'pressroom' ),
			'choices'  => array(
				'masthead' => __( 'Blackletter (UnifrakturMaguntia)', 'pressroom' ),
				'display'  => __( 'Display serif (Libre Caslon)', 'pressroom' ),
			),
		),

		/*
		 * ----------------------------------------------------------------
		 * Colour — the one setting that most changes the theme's character.
		 * ----------------------------------------------------------------
		 */

		'shadow_digest_accent'             => array(
			'default'   => '#6b1f1f',
			'sanitize'  => 'sanitize_hex_color',
			'type'      => 'color',
			'section'   => 'shadow_digest_colour',
			'label'     => __( 'Accent', 'pressroom' ),
			'help'      => __( 'Rules, kickers, the drop cap, links and the subscribe button. Pick something dark enough to pass contrast on the paper background — the theme warns you below if it does not.', 'pressroom' ),
			'transport' => 'postMessage',
		),

		'shadow_digest_accent_soft'        => array(
			'default'   => '#c99a5b',
			'sanitize'  => 'sanitize_hex_color',
			'type'      => 'color',
			'section'   => 'shadow_digest_colour',
			'label'     => __( 'Accent, soft', 'pressroom' ),
			'help'      => __( 'Used only on the dark newsletter panel, where the main accent would be too dark to read. Pick a lighter tint of the accent.', 'pressroom' ),
			'transport' => 'postMessage',
		),

		'shadow_digest_kicker'             => array(
			'default'   => '#8a5a2a',
			'sanitize'  => 'sanitize_hex_color',
			'type'      => 'color',
			'section'   => 'shadow_digest_colour',
			'label'     => __( 'Kicker', 'pressroom' ),
			'help'      => __( 'The small uppercase category labels above headlines — “Legislation”, “History”, “Profile”.', 'pressroom' ),
			'transport' => 'postMessage',
		),

		'shadow_digest_paper'              => array(
			'default'   => '#f4efe4',
			'sanitize'  => 'sanitize_hex_color',
			'type'      => 'color',
			'section'   => 'shadow_digest_colour',
			'label'     => __( 'Paper', 'pressroom' ),
			'help'      => __( 'The colour of the printed sheet itself.', 'pressroom' ),
			'transport' => 'postMessage',
		),

		'shadow_digest_paper_shade'        => array(
			'default'   => '#e7dfce',
			'sanitize'  => 'sanitize_hex_color',
			'type'      => 'color',
			'section'   => 'shadow_digest_colour',
			'label'     => __( 'Desk', 'pressroom' ),
			'help'      => __( 'The darker surface the sheet sits on, visible around the page edges.', 'pressroom' ),
			'transport' => 'postMessage',
		),

		'shadow_digest_paper_tint'         => array(
			'default'   => '#eef0e6',
			'sanitize'  => 'sanitize_hex_color',
			'type'      => 'color',
			'section'   => 'shadow_digest_colour',
			'label'     => __( 'Tinted panel', 'pressroom' ),
			'help'      => __( 'Background of the short-answer box and the author bio. A barely-there tint of the accent reads best.', 'pressroom' ),
			'transport' => 'postMessage',
		),

		'shadow_digest_texture'            => array(
			'default'  => true,
			'sanitize' => 'shadow_digest_sanitize_checkbox',
			'type'     => 'checkbox',
			'section'  => 'shadow_digest_colour',
			'label'    => __( 'Halftone paper texture', 'pressroom' ),
			'help'     => __( 'A faint dot screen over the sheet, like newsprint under a loupe. Costs nothing — it is a CSS gradient, not an image.', 'pressroom' ),
		),

		/*
		 * ----------------------------------------------------------------
		 * Newsletter — presentation only. See inc/template-tags.php.
		 * ----------------------------------------------------------------
		 */

		'shadow_digest_newsletter_enable'  => array(
			'default'  => true,
			'sanitize' => 'shadow_digest_sanitize_checkbox',
			'type'     => 'checkbox',
			'section'  => 'shadow_digest_newsletter',
			'label'    => __( 'Show the newsletter box', 'pressroom' ),
			'help'     => __( 'The dark panel on the front page and beneath each article.', 'pressroom' ),
		),

		'shadow_digest_newsletter_name'    => array(
			'default'   => __( 'The Weekly Dispatch', 'pressroom' ),
			'sanitize'  => 'sanitize_text_field',
			'type'      => 'text',
			'section'   => 'shadow_digest_newsletter',
			'label'     => __( 'Newsletter name', 'pressroom' ),
			'help'      => __( 'Set in the display serif, large. Example: “The Weekly Dispatch”.', 'pressroom' ),
			'transport' => 'postMessage',
		),

		'shadow_digest_newsletter_eyebrow' => array(
			'default'   => __( 'The Flagship Newsletter · Every Thursday', 'pressroom' ),
			'sanitize'  => 'sanitize_text_field',
			'type'      => 'text',
			'section'   => 'shadow_digest_newsletter',
			'label'     => __( 'Newsletter eyebrow', 'pressroom' ),
			'help'      => __( 'The small line above the newsletter name.', 'pressroom' ),
			'transport' => 'postMessage',
		),

		'shadow_digest_newsletter_blurb'   => array(
			'default'   => __( 'Field notes, match results, and the week in marksmanship — reported and written by the newsroom, delivered to your inbox before dawn on Thursday.', 'pressroom' ),
			'sanitize'  => 'shadow_digest_sanitize_multiline',
			'type'      => 'textarea',
			'section'   => 'shadow_digest_newsletter',
			'label'     => __( 'Newsletter description', 'pressroom' ),
			'help'      => __( 'One or two sentences. Set in italic beside the signup field.', 'pressroom' ),
			'transport' => 'postMessage',
		),

		'shadow_digest_newsletter_note'    => array(
			'default'   => __( 'Free weekly. Unsubscribe anytime.', 'pressroom' ),
			'sanitize'  => 'sanitize_text_field',
			'type'      => 'text',
			'section'   => 'shadow_digest_newsletter',
			'label'     => __( 'Fine print', 'pressroom' ),
			'help'      => __( 'Sits under the signup field. A good place for your unsubscribe promise.', 'pressroom' ),
			'transport' => 'postMessage',
		),

		'shadow_digest_newsletter_count'   => array(
			'default'   => '',
			'sanitize'  => 'sanitize_text_field',
			'type'      => 'text',
			'section'   => 'shadow_digest_newsletter',
			'label'     => __( 'Subscriber count', 'pressroom' ),
			'help'      => __( 'Shown as “Join 48,000 readers”. Leave empty to hide the line — an honest empty is better than an invented number.', 'pressroom' ),
			'transport' => 'postMessage',
		),

		'shadow_digest_newsletter_action'  => array(
			'default'  => '',
			'sanitize' => 'esc_url_raw',
			'type'     => 'url',
			'section'  => 'shadow_digest_newsletter',
			'label'    => __( 'Form endpoint', 'pressroom' ),
			'help'     => __( 'Where the signup form posts. Pressroom stores no subscribers and sends no mail — it renders the form and hands the address to whatever service you name here (a webhook, an automation, a mailing-list provider). Leave empty and the form is not rendered at all.', 'pressroom' ),
		),

		'shadow_digest_newsletter_field'   => array(
			'default'  => 'email',
			'sanitize' => 'shadow_digest_sanitize_field_name',
			'type'     => 'text',
			'section'  => 'shadow_digest_newsletter',
			'label'    => __( 'Email field name', 'pressroom' ),
			'help'     => __( 'The name attribute of the email input. Whatever your endpoint expects — most want “email”.', 'pressroom' ),
		),

		/*
		 * ----------------------------------------------------------------
		 * Article furniture.
		 * ----------------------------------------------------------------
		 */

		'shadow_digest_dropcap'            => array(
			'default'  => true,
			'sanitize' => 'shadow_digest_sanitize_checkbox',
			'type'     => 'checkbox',
			'section'  => 'shadow_digest_article',
			'label'    => __( 'Drop cap on the opening paragraph', 'pressroom' ),
			'help'     => __( 'Applied automatically to the first paragraph of every article.', 'pressroom' ),
		),

		'shadow_digest_reading_time'       => array(
			'default'  => true,
			'sanitize' => 'shadow_digest_sanitize_checkbox',
			'type'     => 'checkbox',
			'section'  => 'shadow_digest_article',
			'label'    => __( 'Show reading time', 'pressroom' ),
			'help'     => __( 'Estimated from the word count at 220 words a minute.', 'pressroom' ),
		),

		'shadow_digest_standards'          => array(
			'default'   => '',
			'sanitize'  => 'wp_kses_post',
			'type'      => 'textarea',
			'section'   => 'shadow_digest_article',
			'label'     => __( 'Editorial standards note', 'pressroom' ),
			'help'      => __( 'Printed in small italics at the foot of every article, above the related stories. A good place for your fact-checking policy, an age warning, or a link to your ethics page. Basic links and emphasis are allowed. Leave empty to omit it.', 'pressroom' ),
			'transport' => 'postMessage',
		),
	);

	/**
	 * Filters the theme's Customizer setting definitions.
	 *
	 * A child theme can add, remove or re-default any setting without touching
	 * this file.
	 *
	 * @since 1.0.0
	 * @param array<string, array<string, mixed>> $settings The setting definitions.
	 */
	return apply_filters( 'shadow_digest_settings', $settings );
}

/**
 * Read one Pressroom setting, falling back to its declared default.
 *
 * Always use this rather than get_theme_mod() directly: it guarantees the
 * default in shadow_digest_settings() is the single source of truth, so a default can
 * never drift between the Customizer and the templates that read it.
 *
 * @since 1.0.0
 * @param string $key The setting id, without the shadow_digest_ prefix being optional — pass the full id.
 * @return mixed The stored value, or the declared default.
 */
function shadow_digest_get( string $key ) {
	$settings = shadow_digest_settings();

	if ( ! isset( $settings[ $key ] ) ) {
		return null;
	}

	return get_theme_mod( $key, $settings[ $key ]['default'] );
}

/**
 * Register every setting and control declared in shadow_digest_settings().
 *
 * @since 1.0.0
 * @param WP_Customize_Manager $wp_customize The Customizer manager.
 * @return void
 */
function shadow_digest_customize_register( WP_Customize_Manager $wp_customize ): void {

	$panel = 'shadow_digest_panel';

	$wp_customize->add_panel(
		$panel,
		array(
			'title'       => __( 'Pressroom', 'pressroom' ),
			'description' => __( 'Everything that makes this publication yours. Pressroom hard-codes no brand: the name, the colours, the founding year and the section list all live here, so the same theme can dress two entirely different journals.', 'pressroom' ),
			'priority'    => 25,
		)
	);

	$sections = array(
		'shadow_digest_identity'   => array(
			'title'       => __( 'Identity', 'pressroom' ),
			'description' => __( 'Who the publication is, and what it prints on its folio rule.', 'pressroom' ),
		),
		'shadow_digest_masthead'   => array(
			'title'       => __( 'Masthead', 'pressroom' ),
			'description' => __( 'The nameplate and the two “ears” either side of it.', 'pressroom' ),
		),
		'shadow_digest_colour'     => array(
			'title'       => __( 'Colour & paper', 'pressroom' ),
			'description' => __( 'The accent colour does most of the work. Everything else is the paper it is printed on.', 'pressroom' ),
		),
		'shadow_digest_newsletter' => array(
			'title'       => __( 'Newsletter', 'pressroom' ),
			'description' => __( 'Pressroom renders the signup form. It does not store subscribers or send mail — point the form at your own service.', 'pressroom' ),
		),
		'shadow_digest_article'    => array(
			'title'       => __( 'Article furniture', 'pressroom' ),
			'description' => __( 'The details of the long-read layout.', 'pressroom' ),
		),
	);

	foreach ( $sections as $id => $section ) {
		$wp_customize->add_section(
			$id,
			array(
				'title'       => $section['title'],
				'description' => $section['description'],
				'panel'       => $panel,
			)
		);
	}

	foreach ( shadow_digest_settings() as $id => $args ) {

		$wp_customize->add_setting(
			$id,
			array(
				'default'           => $args['default'],
				'sanitize_callback' => $args['sanitize'],
				'transport'         => $args['transport'] ?? 'refresh',
			)
		);

		$control = array(
			'label'       => $args['label'],
			'description' => $args['help'] ?? '',
			'section'     => $args['section'],
			'settings'    => $id,
			'type'        => $args['type'],
		);

		if ( isset( $args['choices'] ) ) {
			$control['choices'] = $args['choices'];
		}

		// The colour picker needs a dedicated control class; everything else is
		// served by the default one.
		if ( 'color' === $args['type'] ) {
			$wp_customize->add_control(
				new WP_Customize_Color_Control(
					$wp_customize,
					$id,
					array(
						'label'       => $args['label'],
						'description' => $args['help'] ?? '',
						'section'     => $args['section'],
						'settings'    => $id,
					)
				)
			);
			continue;
		}

		if ( 'number' === $args['type'] ) {
			$control['input_attrs'] = array(
				'min'  => 1000,
				'max'  => (int) gmdate( 'Y' ),
				'step' => 1,
			);
		}

		$wp_customize->add_control( $id, $control );
	}

	// Live-preview the text settings without a full page reload. Selective
	// refresh keeps the Customizer honest and fast.
	if ( isset( $wp_customize->selective_refresh ) ) {
		$partials = array(
			'shadow_digest_strapline'        => '.digest-utility__strapline',
			'shadow_digest_motto'            => '.digest-folio__motto',
			'shadow_digest_volume'           => '.digest-folio__volume',
			'shadow_digest_newsletter_name'  => '.digest-newsletter__name',
			'shadow_digest_newsletter_blurb' => '.digest-newsletter__blurb',
		);

		foreach ( $partials as $setting => $selector ) {
			$wp_customize->selective_refresh->add_partial(
				$setting,
				array(
					'selector'        => $selector,
					'render_callback' => static function () use ( $setting ) {
						return esc_html( (string) shadow_digest_get( $setting ) );
					},
				)
			);
		}
	}
}
add_action( 'customize_register', 'shadow_digest_customize_register' );

/**
 * Compile the Customizer colour settings into CSS custom properties.
 *
 * These override the theme.json presets of the same name, which is what lets a
 * publisher recolour the entire theme — including the blocks, which reference
 * the presets — from four colour pickers.
 *
 * @since 1.0.0
 * @return string A :root rule, ready to be inlined.
 */
function shadow_digest_custom_properties(): string {
	$accent      = (string) shadow_digest_get( 'shadow_digest_accent' );
	$accent_soft = (string) shadow_digest_get( 'shadow_digest_accent_soft' );
	$kicker      = (string) shadow_digest_get( 'shadow_digest_kicker' );
	$paper       = (string) shadow_digest_get( 'shadow_digest_paper' );
	$shade       = (string) shadow_digest_get( 'shadow_digest_paper_shade' );
	$tint        = (string) shadow_digest_get( 'shadow_digest_paper_tint' );

	$wordmark = 'display' === shadow_digest_get( 'shadow_digest_wordmark_font' )
		? 'var(--wp--preset--font-family--display)'
		: 'var(--wp--preset--font-family--masthead)';

	$texture = shadow_digest_get( 'shadow_digest_texture' )
		? 'radial-gradient(rgba(28,26,23,.014) 1px, transparent 1px)'
		: 'none';

	$css = ':root{';

	// Overriding the theme.json presets means every block, pattern and template
	// part that already references them picks the new colour up for free.
	$css .= '--wp--preset--color--accent:' . $accent . ';';
	$css .= '--wp--preset--color--accent-soft:' . $accent_soft . ';';
	$css .= '--wp--preset--color--kicker:' . $kicker . ';';
	$css .= '--wp--preset--color--paper:' . $paper . ';';
	$css .= '--wp--preset--color--paper-shade:' . $shade . ';';
	$css .= '--wp--preset--color--paper-tint:' . $tint . ';';

	$css .= '--digest-wordmark-font:' . $wordmark . ';';
	$css .= '--digest-texture:' . $texture . ';';

	$css .= '}';

	/**
	 * Filters the inline custom properties.
	 *
	 * @since 1.0.0
	 * @param string $css The compiled :root rule.
	 */
	return apply_filters( 'shadow_digest_custom_properties', $css );
}

/*
 * --------------------------------------------------------------------------
 * Sanitisers.
 *
 * Every setting above names one of these. The Theme Directory requires that no
 * Customizer value reach the database unsanitised, and these are the callbacks
 * that guarantee it.
 * --------------------------------------------------------------------------
 */

/**
 * Sanitise a checkbox to a real boolean.
 *
 * @since 1.0.0
 * @param mixed $value The submitted value.
 * @return bool
 */
function shadow_digest_sanitize_checkbox( $value ): bool {
	return (bool) $value;
}

/**
 * Sanitise a four-digit year, clamped to something a publication could plausibly
 * claim.
 *
 * @since 1.0.0
 * @param mixed $value The submitted value.
 * @return string A four-digit year, or the empty string.
 */
function shadow_digest_sanitize_year( $value ): string {
	$year = absint( $value );

	if ( $year < 1000 || $year > (int) gmdate( 'Y' ) ) {
		return '';
	}

	return (string) $year;
}

/**
 * Sanitise a multi-line text field, keeping the line breaks but nothing else.
 *
 * sanitize_text_field() strips newlines, which would collapse the masthead ears
 * onto one line. sanitize_textarea_field() keeps them.
 *
 * @since 1.0.0
 * @param mixed $value The submitted value.
 * @return string
 */
function shadow_digest_sanitize_multiline( $value ): string {
	return sanitize_textarea_field( (string) $value );
}

/**
 * Sanitise the nameplate typeface choice against the list the control offers.
 *
 * @since 1.0.0
 * @param mixed $value The submitted value.
 * @return string Either 'masthead' or 'display'.
 */
function shadow_digest_sanitize_wordmark_font( $value ): string {
	return in_array( $value, array( 'masthead', 'display' ), true ) ? (string) $value : 'masthead';
}

/**
 * Sanitise an HTML form field name.
 *
 * @since 1.0.0
 * @param mixed $value The submitted value.
 * @return string A safe name attribute, defaulting to 'email'.
 */
function shadow_digest_sanitize_field_name( $value ): string {
	$name = preg_replace( '/[^A-Za-z0-9_\-\[\]]/', '', (string) $value );

	return ( is_string( $name ) && '' !== $name ) ? $name : 'email';
}
