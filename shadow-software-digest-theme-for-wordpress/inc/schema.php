<?php
/**
 * Structured data (JSON-LD).
 *
 * A journal of record lives or dies by how well search engines and answer
 * engines understand it, so Digest emits NewsArticle, Organization and
 * BreadcrumbList markup derived entirely from post data.
 *
 * It emits nothing at all when an SEO plugin is active. Two competing
 * NewsArticle graphs on one page is worse than none — search engines pick one
 * arbitrarily, and the publisher has no idea which. If Yoast, Rank Math, SEOPress
 * or All in One SEO is running, they own the graph and Digest gets out of the way.
 *
 * @package Digest
 * @since   1.0.0
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || exit;

/**
 * Whether Digest should emit structured data at all.
 *
 * @since 1.0.0
 * @return bool True when no SEO plugin is handling schema.
 */
function shadow_digest_should_emit_schema(): bool {

	// Each of these ships its own complete schema graph.
	$seo_plugins = array(
		'WPSEO_VERSION',              // Yoast SEO.
		'RANK_MATH_VERSION',          // Rank Math.
		'SEOPRESS_VERSION',           // SEOPress.
		'AIOSEO_VERSION',             // All in One SEO.
	);

	foreach ( $seo_plugins as $constant ) {
		if ( defined( $constant ) ) {
			return false;
		}
	}

	// The Yoast and SEOFramework classes, for versions that do not define a
	// version constant.
	if ( class_exists( 'WPSEO_Frontend' ) || function_exists( 'the_seo_framework' ) ) {
		return false;
	}

	/**
	 * Filters whether Digest emits its own JSON-LD.
	 *
	 * Return false to silence the theme's structured data entirely — for
	 * instance if you emit your own from a plugin or a mu-plugin.
	 *
	 * @since 1.0.0
	 * @param bool $emit Whether to emit structured data.
	 */
	return (bool) apply_filters( 'shadow_digest_emit_schema', true );
}

/**
 * Print the JSON-LD graph in the document head.
 *
 * @since 1.0.0
 * @return void
 */
function shadow_digest_print_schema(): void {
	if ( ! shadow_digest_should_emit_schema() ) {
		return;
	}

	$graph = array();

	$publisher = shadow_digest_schema_publisher();

	$graph[] = $publisher;
	$graph[] = shadow_digest_schema_website( $publisher['@id'] );

	if ( is_singular( 'post' ) ) {
		$article = shadow_digest_schema_article( $publisher['@id'] );

		if ( ! empty( $article ) ) {
			$graph[] = $article;
		}

		$breadcrumbs = shadow_digest_schema_breadcrumbs();

		if ( ! empty( $breadcrumbs ) ) {
			$graph[] = $breadcrumbs;
		}
	}

	/**
	 * Filters the complete JSON-LD graph before it is printed.
	 *
	 * @since 1.0.0
	 * @param array<int, array<string, mixed>> $graph The schema.org nodes.
	 */
	$graph = apply_filters( 'shadow_digest_schema_graph', $graph );

	if ( empty( $graph ) ) {
		return;
	}

	$json = wp_json_encode(
		array(
			'@context' => 'https://schema.org',
			'@graph'   => array_values( $graph ),
		),
		JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
	);

	if ( false === $json ) {
		return;
	}

	printf(
		'<script type="application/ld+json">%s</script>' . "\n",
		$json // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_json_encode() output, inside a JSON-LD script element, is escaped by construction.
	);
}
add_action( 'wp_head', 'shadow_digest_print_schema', 20 );

/**
 * The publication itself, as a NewsMediaOrganization.
 *
 * @since 1.0.0
 * @return array<string, mixed> The Organization node.
 */
function shadow_digest_schema_publisher(): array {
	$home = home_url( '/' );

	$node = array(
		'@type' => 'NewsMediaOrganization',
		'@id'   => $home . '#organization',
		'name'  => get_bloginfo( 'name', 'display' ),
		'url'   => $home,
	);

	$strapline = (string) shadow_digest_get( 'shadow_digest_strapline' );

	if ( '' !== $strapline ) {
		$node['slogan'] = $strapline;
	}

	$founded = shadow_digest_founded();

	if ( $founded ) {
		$node['foundingDate'] = (string) $founded;
	}

	$logo_id = (int) get_theme_mod( 'custom_logo' );

	if ( $logo_id ) {
		$logo = wp_get_attachment_image_src( $logo_id, 'full' );

		if ( is_array( $logo ) ) {
			$node['logo'] = array(
				'@type'  => 'ImageObject',
				'url'    => $logo[0],
				'width'  => $logo[1],
				'height' => $logo[2],
			);
		}
	}

	return $node;
}

/**
 * The site, with its search action.
 *
 * @since 1.0.0
 * @param string $publisher_id The @id of the Organization node.
 * @return array<string, mixed> The WebSite node.
 */
function shadow_digest_schema_website( string $publisher_id ): array {
	$home = home_url( '/' );

	return array(
		'@type'           => 'WebSite',
		'@id'             => $home . '#website',
		'url'             => $home,
		'name'            => get_bloginfo( 'name', 'display' ),
		'description'     => get_bloginfo( 'description', 'display' ),
		'inLanguage'      => get_bloginfo( 'language' ),
		'publisher'       => array( '@id' => $publisher_id ),
		'potentialAction' => array(
			'@type'       => 'SearchAction',
			'target'      => array(
				'@type'       => 'EntryPoint',
				'urlTemplate' => $home . '?s={search_term_string}',
			),
			'query-input' => 'required name=search_term_string',
		),
	);
}

/**
 * The current post, as a NewsArticle.
 *
 * @since 1.0.0
 * @param string $publisher_id The @id of the Organization node.
 * @return array<string, mixed> The NewsArticle node, or an empty array.
 */
function shadow_digest_schema_article( string $publisher_id ): array {
	$post = get_post();

	if ( ! $post instanceof WP_Post ) {
		return array();
	}

	$permalink = (string) get_permalink( $post );

	$node = array(
		'@type'            => 'NewsArticle',
		'@id'              => $permalink . '#article',
		'url'              => $permalink,
		'headline'         => wp_strip_all_tags( get_the_title( $post ) ),
		'datePublished'    => (string) get_the_date( DATE_W3C, $post ),
		'dateModified'     => (string) get_the_modified_date( DATE_W3C, $post ),
		'inLanguage'       => get_bloginfo( 'language' ),
		'wordCount'        => str_word_count( wp_strip_all_tags( strip_shortcodes( $post->post_content ) ) ),
		'mainEntityOfPage' => array(
			'@type' => 'WebPage',
			'@id'   => $permalink,
		),
		'publisher'        => array( '@id' => $publisher_id ),
	);

	$excerpt = get_the_excerpt( $post );

	if ( '' !== $excerpt ) {
		$node['description'] = wp_strip_all_tags( $excerpt );
	}

	$author_id = (int) $post->post_author;

	if ( $author_id ) {
		$author = array(
			'@type' => 'Person',
			'name'  => (string) get_the_author_meta( 'display_name', $author_id ),
			'url'   => (string) get_author_posts_url( $author_id ),
		);

		$bio = (string) get_the_author_meta( 'description', $author_id );

		if ( '' !== $bio ) {
			$author['description'] = wp_strip_all_tags( $bio );
		}

		$node['author'] = $author;
	}

	$categories = get_the_category( $post->ID );

	if ( ! empty( $categories ) ) {
		$node['articleSection'] = $categories[0]->name;
	}

	if ( has_post_thumbnail( $post ) ) {
		$image = wp_get_attachment_image_src( (int) get_post_thumbnail_id( $post ), 'full' );

		if ( is_array( $image ) ) {
			$node['image'] = array(
				'@type'  => 'ImageObject',
				'url'    => $image[0],
				'width'  => $image[1],
				'height' => $image[2],
			);
		}
	}

	return $node;
}

/**
 * Breadcrumbs for the current post: Home / Section / Headline.
 *
 * @since 1.0.0
 * @return array<string, mixed> The BreadcrumbList node, or an empty array.
 */
function shadow_digest_schema_breadcrumbs(): array {
	$post = get_post();

	if ( ! $post instanceof WP_Post ) {
		return array();
	}

	$items = array(
		array(
			'@type'    => 'ListItem',
			'position' => 1,
			'name'     => __( 'Home', 'shadow-software-digest-theme-for-wordpress' ),
			'item'     => home_url( '/' ),
		),
	);

	$categories = get_the_category( $post->ID );

	if ( ! empty( $categories ) ) {
		$items[] = array(
			'@type'    => 'ListItem',
			'position' => 2,
			'name'     => $categories[0]->name,
			'item'     => (string) get_category_link( $categories[0]->term_id ),
		);
	}

	$items[] = array(
		'@type'    => 'ListItem',
		'position' => count( $items ) + 1,
		'name'     => wp_strip_all_tags( get_the_title( $post ) ),
		'item'     => (string) get_permalink( $post ),
	);

	return array(
		'@type'           => 'BreadcrumbList',
		'@id'             => get_permalink( $post ) . '#breadcrumbs',
		'itemListElement' => $items,
	);
}

/**
 * Print a meta description in the document head.
 *
 * Stock WordPress prints no meta description at all. Digest ships no SEO
 * plugin dependency, so without this every page on a site running the theme
 * unmodified is missing one — and search engines are left to invent a snippet
 * from whatever text happens to be first on the page. Silent, like the JSON-LD
 * above, when an SEO plugin already owns this.
 *
 * @since 1.0.0
 * @return void
 */
function shadow_digest_print_meta(): void {
	if ( ! shadow_digest_should_emit_schema() ) {
		return;
	}

	$description = shadow_digest_meta_description();

	if ( '' !== $description ) {
		printf(
			'<meta name="description" content="%s" />' . "\n",
			esc_attr( $description )
		);
	}
}
add_action( 'wp_head', 'shadow_digest_print_meta', 1 );

/**
 * Print a canonical link for the request types WordPress core leaves uncovered.
 *
 * Core's rel_canonical() (wp-includes/link-template.php) bails out immediately
 * for anything that is not is_singular() — it never emits a canonical for the
 * front page, a category or author archive, or their paginated pages. Digest
 * fills exactly that gap and no more: is_singular() requests are left entirely
 * to core, so this never produces the duplicate <link rel="canonical"> a naive
 * "cover every page type" version would.
 *
 * @since 1.0.0
 * @return void
 */
function shadow_digest_print_archive_canonical(): void {
	if ( ! shadow_digest_should_emit_schema() ) {
		return;
	}

	if ( is_singular() ) {
		return;
	}

	$canonical = shadow_digest_archive_canonical_url();

	if ( '' !== $canonical ) {
		printf(
			'<link rel="canonical" href="%s" />' . "\n",
			esc_url( $canonical )
		);
	}
}
add_action( 'wp_head', 'shadow_digest_print_archive_canonical', 1 );

/**
 * The canonical URL for a non-singular request (front page, archives).
 *
 * Paginated archive pages (page/2/, etc.) canonicalise to themselves, not back
 * to page 1 — they are genuinely different result sets, not duplicates of it.
 *
 * @since 1.0.0
 * @return string The canonical URL, or an empty string when none applies.
 */
function shadow_digest_archive_canonical_url(): string {
	$base = '';

	if ( is_category() || is_tag() || is_tax() ) {
		$term = get_queried_object();
		$base = $term instanceof WP_Term ? (string) get_term_link( $term ) : '';
	} elseif ( is_author() ) {
		$author = get_queried_object();
		$base   = $author instanceof WP_User ? (string) get_author_posts_url( $author->ID ) : '';
	} elseif ( is_front_page() || is_home() ) {
		$base = home_url( '/' );
	}

	if ( '' === $base ) {
		return '';
	}

	$page = get_query_var( 'paged' );

	if ( $page && (int) $page > 1 ) {
		$paged_url = get_pagenum_link( (int) $page );

		return $paged_url ? $paged_url : $base;
	}

	return $base;
}

/**
 * The meta description for the current request.
 *
 * @since 1.0.0
 * @return string The description, or an empty string when none applies.
 */
function shadow_digest_meta_description(): string {
	if ( is_singular() ) {
		$excerpt = get_the_excerpt();

		return '' !== $excerpt ? wp_strip_all_tags( $excerpt ) : '';
	}

	if ( is_category() || is_tag() || is_tax() ) {
		$description = term_description();

		if ( '' !== $description ) {
			return wp_strip_all_tags( $description );
		}

		return sprintf(
			/* translators: %s: category, tag or term name. */
			__( 'Articles filed under %s.', 'shadow-software-digest-theme-for-wordpress' ),
			single_term_title( '', false )
		);
	}

	if ( is_author() ) {
		$bio = get_the_author_meta( 'description' );

		if ( '' !== $bio ) {
			return wp_strip_all_tags( $bio );
		}

		return sprintf(
			/* translators: %s: author display name. */
			__( 'Articles by %s.', 'shadow-software-digest-theme-for-wordpress' ),
			get_the_author_meta( 'display_name' )
		);
	}

	if ( is_search() ) {
		return sprintf(
			/* translators: %s: the search query. */
			__( 'Search results for %s.', 'shadow-software-digest-theme-for-wordpress' ),
			get_search_query()
		);
	}

	if ( is_front_page() || is_home() ) {
		$strapline = (string) shadow_digest_get( 'shadow_digest_strapline' );

		if ( '' !== $strapline ) {
			return $strapline;
		}

		$tagline = get_bloginfo( 'description', 'display' );

		return '' !== $tagline ? wp_strip_all_tags( $tagline ) : '';
	}

	return '';
}
