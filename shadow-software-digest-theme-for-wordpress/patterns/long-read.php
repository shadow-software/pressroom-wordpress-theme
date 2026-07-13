<?php
/**
 * Title: The long read
 * Slug: digest/long-read
 * Categories: digest-article
 * Description: The full furniture of a reported feature — the short answer, the takeaways and contents, the body, the FAQ, and the sources. Insert it once and write into it.
 * Keywords: article, feature, long read, skeleton, template
 * Block Types: core/post-content
 * Viewport Width: 800
 *
 * @package Digest
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

?>
<!-- wp:shadow-digest/short-answer -->
<!-- /wp:shadow-digest/short-answer -->

<!-- wp:group {"className":"digest-furniture","layout":{"type":"default"}} -->
<div class="wp-block-group digest-furniture">

	<!-- wp:shadow-digest/takeaways {"items":["","",""]} /-->

	<!-- wp:shadow-digest/toc /-->

</div>
<!-- /wp:group -->

<!-- wp:paragraph {"className":"digest-dropcap","placeholder":"<?php echo esc_attr__( 'Open with a scene, not a summary. The drop cap falls on this first letter.', 'shadow-software-digest-theme-for-wordpress' ); ?>"} -->
<p class="digest-dropcap"></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p></p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading"><?php echo esc_html__( 'The first thing to understand', 'shadow-software-digest-theme-for-wordpress' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p></p>
<!-- /wp:paragraph -->

<!-- wp:quote -->
<blockquote class="wp-block-quote">
	<!-- wp:paragraph -->
	<p><?php echo esc_html__( 'A line worth pulling out and setting large.', 'shadow-software-digest-theme-for-wordpress' ); ?></p>
	<!-- /wp:paragraph -->
	<cite><?php echo esc_html__( 'Someone worth quoting', 'shadow-software-digest-theme-for-wordpress' ); ?></cite>
</blockquote>
<!-- /wp:quote -->

<!-- wp:heading -->
<h2 class="wp-block-heading"><?php echo esc_html__( 'The second thing', 'shadow-software-digest-theme-for-wordpress' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p></p>
<!-- /wp:paragraph -->

<!-- wp:shadow-digest/faq {"items":[{"question":"","answer":""},{"question":"","answer":""}]} /-->

<!-- wp:shadow-digest/sources {"items":["",""]} /-->
