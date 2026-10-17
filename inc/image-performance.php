<?php
/**
 * Image loading and sizing optimizations.
 *
 * Keeps the largest above-the-fold image eager and high priority, and makes sure
 * content images always carry intrinsic dimensions so they never shift the layout.
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package WordPress
 * @subpackage Twenty_Twenty_Five
 * @since Emilias theme 1.0
 */

if ( ! function_exists( 'emilias_theme_image_dimensions' ) ) :
	/**
	 * Resolves the intrinsic dimensions of an image URL.
	 *
	 * Falls back to reading the header of the attached file when the stored attachment
	 * metadata is missing or incomplete, which happens when thumbnail generation failed
	 * at upload time. Without a fallback those images are emitted without `width` and
	 * `height`, which both breaks the largest contentful paint heuristics and causes
	 * layout shift once the image finally arrives.
	 *
	 * @since Emilias theme 1.0
	 *
	 * @param string $src           Image URL, optionally with a query string.
	 * @param int    $attachment_id Optional. Attachment ID. Default 0.
	 * @return int[]|null Array of width and height, or null when the dimensions are unknown.
	 */
	function emilias_theme_image_dimensions( $src, $attachment_id = 0 ) {
		$src = is_string( $src ) ? trim( $src ) : '';

		if ( '' === $src ) {
			return null;
		}

		list( $src ) = explode( '?', $src );
		$attachment_id = absint( $attachment_id );

		if ( $attachment_id > 0 ) {
			$image_meta = wp_get_attachment_metadata( $attachment_id );
			$size_array = wp_image_src_get_dimensions( $src, $image_meta, $attachment_id );

			if ( $size_array && ! empty( $size_array[0] ) && ! empty( $size_array[1] ) ) {
				return array( (int) $size_array[0], (int) $size_array[1] );
			}

			/*
			 * Only consult the attached file when the requested URL actually points at
			 * that file. Anything else is a generated size we have no metadata for.
			 */
			$file = get_attached_file( $attachment_id );

			if ( $file && wp_basename( $file ) === wp_basename( $src ) && file_exists( $file ) ) {
				$size = wp_getimagesize( $file );

				if ( $size && ! empty( $size[0] ) && ! empty( $size[1] ) ) {
					return array( (int) $size[0], (int) $size[1] );
				}
			}
		}

		return null;
	}
endif;

if ( ! function_exists( 'emilias_theme_improve_first_image' ) ) :
	/**
	 * Loads the first image eagerly and adds intrinsic dimensions to every image.
	 *
	 * WordPress only adds `loading` and `fetchpriority` attributes to images it can
	 * resolve intrinsic dimensions for. Images whose metadata failed to generate are
	 * therefore served with neither an eager load nor a size hint, and are skipped by
	 * the largest contentful paint heuristics.
	 *
	 * @since Emilias theme 1.0
	 *
	 * @param string $content Rendered markup containing at least one image.
	 * @return string Updated markup.
	 */
	function emilias_theme_improve_first_image( $content ) {
		if ( ! is_string( $content ) || '' === $content || false === strpos( $content, '<img' ) ) {
			return $content;
		}

		if ( ! class_exists( 'WP_HTML_Tag_Processor' ) ) {
			return $content;
		}

		$processor = new WP_HTML_Tag_Processor( $content );
		$is_first  = true;

		while ( $processor->next_tag( array( 'tag_name' => 'IMG' ) ) ) {
			if ( $is_first ) {
				$is_first = false;

				$loading = $processor->get_attribute( 'loading' );

				if ( 'lazy' === $loading ) {
					$processor->remove_attribute( 'loading' );
				}

				$processor->set_attribute( 'fetchpriority', 'high' );
			}

			if ( null !== $processor->get_attribute( 'width' ) && null !== $processor->get_attribute( 'height' ) ) {
				continue;
			}

			$src = $processor->get_attribute( 'src' );
			$src = is_string( $src ) ? $src : '';

			if ( '' === $src ) {
				continue;
			}

			$attachment_id = emilias_theme_attachment_id_from_img_class( $processor->get_attribute( 'class' ) );

			$dimensions = emilias_theme_image_dimensions( $src, $attachment_id );

			if ( ! $dimensions ) {
				continue;
			}

			if ( null === $processor->get_attribute( 'width' ) ) {
				$processor->set_attribute( 'width', (string) $dimensions[0] );
			}

			if ( null === $processor->get_attribute( 'height' ) ) {
				$processor->set_attribute( 'height', (string) $dimensions[1] );
			}
		}

		return $processor->get_updated_html();
	}
endif;

if ( ! function_exists( 'emilias_theme_attachment_id_from_img_class' ) ) :
	/**
	 * Reads the attachment ID from a `wp-image-{id}` class name.
	 *
	 * @since Emilias theme 1.0
	 *
	 * @param mixed $classes Class attribute value.
	 * @return int Attachment ID, or 0 when the class name carries none.
	 */
	function emilias_theme_attachment_id_from_img_class( $classes ) {
		if ( ! is_string( $classes ) || '' === $classes ) {
			return 0;
		}

		if ( ! preg_match( '/(?:^|\s)wp-image-([0-9]+)(?:\s|$)/', $classes, $matches ) ) {
			return 0;
		}

		return absint( $matches[1] );
	}
endif;

if ( ! function_exists( 'emilias_theme_improve_the_content_images' ) ) :
	/**
	 * Applies image optimizations to the rendered post content.
	 *
	 * Runs after `wp_filter_content_tags()` so it can repair the images core had to skip.
	 *
	 * @since Emilias theme 1.0
	 *
	 * @param string $content Post content.
	 * @return string Updated post content.
	 */
	function emilias_theme_improve_the_content_images( $content ) {
		return emilias_theme_improve_first_image( $content );
	}
endif;
add_filter( 'the_content', 'emilias_theme_improve_the_content_images', 20 );

if ( ! function_exists( 'emilias_theme_improve_excerpt_images' ) ) :
	/**
	 * Applies image optimizations to manually trimmed excerpts.
	 *
	 * @since Emilias theme 1.0
	 *
	 * @param string $content Excerpt markup.
	 * @return string Updated excerpt markup.
	 */
	function emilias_theme_improve_excerpt_images( $content ) {
		return emilias_theme_improve_first_image( $content );
	}
endif;
add_filter( 'get_the_excerpt', 'emilias_theme_improve_excerpt_images', 20 );

if ( ! function_exists( 'emilias_theme_eager_masonry_leading_image' ) ) :
	/**
	 * Loads the leading masonry thumbnail eagerly.
	 *
	 * A masonry Post Template runs as a secondary query, so WordPress never counts its
	 * images towards the `wp_omit_loading_attr_threshold()` budget and marks every
	 * thumbnail `loading="lazy"`, including the one that opens the grid and becomes the
	 * largest contentful paint.
	 *
	 * @since Emilias theme 1.0
	 *
	 * @param string $block_content The rendered block content.
	 * @param array  $block         The parsed block.
	 * @return string The rendered block content.
	 */
	function emilias_theme_eager_masonry_leading_image( $block_content, $block ) {
		$class_name = isset( $block['attrs']['className'] ) ? $block['attrs']['className'] : '';

		if ( ! is_string( $class_name ) || ! emilias_theme_is_masonry_post_template_class( $class_name ) ) {
			return $block_content;
		}

		return emilias_theme_improve_first_image( $block_content );
	}
endif;
add_filter( 'render_block_core/post-template', 'emilias_theme_eager_masonry_leading_image', 20, 2 );