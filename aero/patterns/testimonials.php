<?php
/**
 * Title: Customer testimonials
 * Slug: aero/testimonials
 * Categories: aero, testimonials
 * Block Types: core/post-content
 * Description: Three short customer quotes with attribution. Replace with your own.
 * Keywords: testimonials, reviews, social proof, quotes
 * Viewport Width: 1280
 */
?>
<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"top":"var:preset|spacing|2-xl","bottom":"var:preset|spacing|2-xl","left":"var:preset|spacing|lg","right":"var:preset|spacing|lg"}}},"layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull" style="padding-top:var(--wp--preset--spacing--2-xl);padding-right:var(--wp--preset--spacing--lg);padding-bottom:var(--wp--preset--spacing--2-xl);padding-left:var(--wp--preset--spacing--lg)">
	<!-- wp:heading {"textAlign":"center"} -->
	<h2 class="wp-block-heading has-text-align-center"><?php esc_html_e( 'What the shimmer-pilled say', 'aero' ); ?></h2>
	<!-- /wp:heading -->

	<!-- wp:columns {"align":"wide","style":{"spacing":{"margin":{"top":"var:preset|spacing|xl"}}}} -->
	<div class="wp-block-columns alignwide" style="margin-top:var(--wp--preset--spacing--xl)">
		<!-- wp:column -->
		<div class="wp-block-column">
			<!-- wp:group {"className":"is-style-panel","style":{"spacing":{"blockGap":"var:preset|spacing|sm"}}} -->
			<div class="wp-block-group is-style-panel">
				<!-- wp:quote -->
				<blockquote class="wp-block-quote">
					<!-- wp:paragraph -->
					<p><?php esc_html_e( 'Opened the box. Closed the curtains. Watched it shimmer for an embarrassingly long time.', 'aero' ); ?></p>
					<!-- /wp:paragraph -->
					<cite><?php esc_html_e( 'Mira L.', 'aero' ); ?></cite>
				</blockquote>
				<!-- /wp:quote -->
			</div>
			<!-- /wp:group -->
		</div>
		<!-- /wp:column -->

		<!-- wp:column -->
		<div class="wp-block-column">
			<!-- wp:group {"className":"is-style-panel","style":{"spacing":{"blockGap":"var:preset|spacing|sm"}}} -->
			<div class="wp-block-group is-style-panel">
				<!-- wp:quote -->
				<blockquote class="wp-block-quote">
					<!-- wp:paragraph -->
					<p><?php esc_html_e( 'Came wrapped in tissue paper that I refused to throw away. Even the box was a vibe.', 'aero' ); ?></p>
					<!-- /wp:paragraph -->
					<cite><?php esc_html_e( 'Theo R.', 'aero' ); ?></cite>
				</blockquote>
				<!-- /wp:quote -->
			</div>
			<!-- /wp:group -->
		</div>
		<!-- /wp:column -->

		<!-- wp:column -->
		<div class="wp-block-column">
			<!-- wp:group {"className":"is-style-panel","style":{"spacing":{"blockGap":"var:preset|spacing|sm"}}} -->
			<div class="wp-block-group is-style-panel">
				<!-- wp:quote -->
				<blockquote class="wp-block-quote">
					<!-- wp:paragraph -->
					<p><?php esc_html_e( 'A whole year in and it still catches the light like the day it arrived. Slightly haunted, fully obsessed.', 'aero' ); ?></p>
					<!-- /wp:paragraph -->
					<cite><?php esc_html_e( 'Iris N.', 'aero' ); ?></cite>
				</blockquote>
				<!-- /wp:quote -->
			</div>
			<!-- /wp:group -->
		</div>
		<!-- /wp:column -->
	</div>
	<!-- /wp:columns -->
</div>
<!-- /wp:group -->
