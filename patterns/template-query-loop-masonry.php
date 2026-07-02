<?php
/**
 * Title: Masonry posts
 * Slug: emilias-theme/template-query-loop-masonry
 * Categories: query
 * Block Types: core/query
 * Description: A responsive masonry layout for posts using the core Query Loop and Post Template blocks.
 *
 * @package WordPress
 * @subpackage Twenty_Twenty_Five
 * @since Emilias theme 1.0
 */

?>
<!-- wp:query {"query":{"perPage":10,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":true,"taxQuery":null,"parents":[]},"tagName":"main","align":"wide","style":{"spacing":{"margin":{"top":"var:preset|spacing|60"}}},"layout":{"type":"default"}} -->
<main class="wp-block-query alignwide" style="margin-top:var(--wp--preset--spacing--60)">
	<!-- wp:group {"layout":{"type":"constrained"}} -->
	<div class="wp-block-group">
		<!-- wp:query-no-results {"align":"wide","fontSize":"medium"} -->
			<!-- wp:paragraph -->
			<p><?php echo esc_html_x( 'Sorry, but nothing was found. Please try a search with different keywords.', 'Message explaining that there are no results returned from a search.', 'emilias-theme' ); ?></p>
			<!-- /wp:paragraph -->
		<!-- /wp:query-no-results -->
	</div>
	<!-- /wp:group -->

	<!-- wp:post-template {"className":"is-style-masonry-collage","style":{"spacing":{"blockGap":"var:preset|spacing|50"}},"layout":{"type":"grid","columnCount":null,"minimumColumnWidth":"23rem"}} -->
		<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|30"}},"layout":{"type":"default"}} -->
		<div class="wp-block-group">
			<!-- wp:post-featured-image {"isLink":true,"aspectRatio":"4/3"} /-->

			<!-- wp:group {"style":{"spacing":{"blockGap":"0.5rem"}},"layout":{"type":"flex","flexWrap":"wrap"}} -->
			<div class="wp-block-group">
				<!-- wp:post-date {"isLink":true,"fontSize":"small"} /-->
				<!-- wp:post-terms {"term":"category","style":{"typography":{"textTransform":"uppercase","letterSpacing":"1.4px"}},"fontSize":"small"} /-->
			</div>
			<!-- /wp:group -->

			<!-- wp:post-title {"isLink":true,"fontSize":"x-large"} /-->
			<!-- wp:post-excerpt {"moreText":"","showMoreOnNewLine":false,"excerptLength":28,"fontSize":"medium"} /-->
		</div>
		<!-- /wp:group -->
	<!-- /wp:post-template -->

	<!-- wp:group {"align":"full","style":{"spacing":{"margin":{"top":"var:preset|spacing|60","bottom":"var:preset|spacing|40"}}},"layout":{"type":"constrained"}} -->
	<div class="wp-block-group alignfull" style="margin-top:var(--wp--preset--spacing--60);margin-bottom:var(--wp--preset--spacing--40)">
		<!-- wp:query-pagination {"paginationArrow":"arrow","align":"wide","layout":{"type":"flex","justifyContent":"space-between","flexWrap":"wrap"}} -->
			<!-- wp:query-pagination-previous /-->
			<!-- wp:query-pagination-numbers /-->
			<!-- wp:query-pagination-next /-->
		<!-- /wp:query-pagination -->
	</div>
	<!-- /wp:group -->
</main>
<!-- /wp:query -->
