<?php
/**
 * Title: The masthead (staff page)
 * Slug: digest/masthead-page
 * Categories: digest-page
 * Description: The page that lists who makes the publication. Search engines read it as a trust signal; readers read it to know who is talking to them.
 * Keywords: masthead, staff, about, team, editorial
 * Block Types: core/post-content
 * Viewport Width: 800
 *
 * @package Digest
 * @since   1.0.0
 */

defined( 'ABSPATH' ) || exit;

?>
<!-- wp:paragraph {"className":"digest-eyebrow"} -->
<p class="digest-eyebrow"><?php echo esc_html__( 'Who we are', 'shadow-software-digest-theme-for-wordpress' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph {"fontSize":"prose"} -->
<p class="has-prose-font-size"><?php echo esc_html__( 'Say, in a paragraph, what this publication is for and who it serves. Readers arriving here are deciding whether to trust you; the answer should not make them work for it.', 'shadow-software-digest-theme-for-wordpress' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading"><?php echo esc_html__( 'Editorial', 'shadow-software-digest-theme-for-wordpress' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:list -->
<ul class="wp-block-list">
	<!-- wp:list-item -->
	<li><strong><?php echo esc_html__( 'Name', 'shadow-software-digest-theme-for-wordpress' ); ?></strong> — <?php echo esc_html__( 'Editor in Chief', 'shadow-software-digest-theme-for-wordpress' ); ?></li>
	<!-- /wp:list-item -->
	<!-- wp:list-item -->
	<li><strong><?php echo esc_html__( 'Name', 'shadow-software-digest-theme-for-wordpress' ); ?></strong> — <?php echo esc_html__( 'Chief Correspondent', 'shadow-software-digest-theme-for-wordpress' ); ?></li>
	<!-- /wp:list-item -->
	<!-- wp:list-item -->
	<li><strong><?php echo esc_html__( 'Name', 'shadow-software-digest-theme-for-wordpress' ); ?></strong> — <?php echo esc_html__( 'Science Editor', 'shadow-software-digest-theme-for-wordpress' ); ?></li>
	<!-- /wp:list-item -->
</ul>
<!-- /wp:list -->

<!-- wp:heading -->
<h2 class="wp-block-heading"><?php echo esc_html__( 'How we report', 'shadow-software-digest-theme-for-wordpress' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p><?php echo esc_html__( 'Describe your fact-checking process, how you handle corrections, and how commercial relationships are kept away from editorial. Be specific — a vague promise reads as no promise.', 'shadow-software-digest-theme-for-wordpress' ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2 class="wp-block-heading"><?php echo esc_html__( 'Corrections', 'shadow-software-digest-theme-for-wordpress' ); ?></h2>
<!-- /wp:heading -->

<!-- wp:paragraph -->
<p><?php echo esc_html__( 'Tell readers how to report an error, and what you do when they do.', 'shadow-software-digest-theme-for-wordpress' ); ?></p>
<!-- /wp:paragraph -->
