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

if ( ! function_exists( 'emilias_theme_max_content_image_width' ) ) :
	/**
	 * Returns the widest image a content image is allowed to fall back to.
	 *
	 * Used when repairing an image whose source cannot be resolved against its
	 * attachment metadata and which therefore carries no srcset at all.
	 *
	 * @since Emilias theme 1.0
	 *
	 * @return int Maximum width in pixels.
	 */
	function emilias_theme_max_content_image_width() {
		/**
		 * Filters the maximum width used when rebuilding an unresolvable content image.
		 *
		 * @since Emilias theme 1.0
		 *
		 * @param int $width Maximum width in pixels.
		 */
		return (int) apply_filters( 'emilias_theme_max_content_image_width', 1700 );
	}
endif;

if ( ! function_exists( 'emilias_theme_attachment_image_candidates' ) ) :
	/**
	 * Collects the generated sizes of an attachment as srcset candidates.
	 *
	 * Sizes are resolved through wp_get_attachment_image_src() so that URL construction
	 * runs through the same filters as a normal WordPress image. Any candidate that
	 * resolves back to the unscaled original is discarded, so an unresolvable source
	 * is never rebuilt into the very file it was meant to avoid.
	 *
	 * @since Emilias theme 1.0
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array Candidate URLs keyed by width.
	 */
	function emilias_theme_attachment_image_candidates( $attachment_id ) {
		$attachment_id = absint( $attachment_id );

		if ( ! $attachment_id ) {
			return array();
		}

		$metadata = wp_get_attachment_metadata( $attachment_id );

		if ( empty( $metadata['sizes'] ) || ! is_array( $metadata['sizes'] ) ) {
			return array();
		}

		$original_url = wp_get_attachment_url( $attachment_id );
		$original     = $original_url ? wp_basename( $original_url ) : '';
		$candidates   = array();

		foreach ( array_keys( $metadata['sizes'] ) as $size_name ) {
			$image = wp_get_attachment_image_src( $attachment_id, $size_name );

			if ( ! $image || empty( $image[0] ) || empty( $image[1] ) || empty( $image[2] ) ) {
				continue;
			}

			// Never point back at the original, which is what this repair exists to avoid.
			if ( '' !== $original && wp_basename( $image[0] ) === $original ) {
				continue;
			}

			$candidates[ (int) $image[1] ] = $image;
		}

		ksort( $candidates );

		return $candidates;
	}
endif;

if ( ! function_exists( 'emilias_theme_repair_unresolvable_image' ) ) :
	/**
	 * Rebuilds a content image that WordPress could not resolve against its metadata.
	 *
	 * When a stored source does not match the attachment's registered file or any of
	 * its generated sizes, wp_image_add_srcset_and_sizes() gives up: the image is emitted
	 * with no srcset, no sizes and no dimensions, and whatever URL it points at is
	 * downloaded at full size. A block that has drifted onto the unscaled original is
	 * therefore served as a bare multi-megabyte image.
	 *
	 * This only runs when core has already failed to produce a srcset. An image with any
	 * srcset is left exactly as WordPress resolved it.
	 *
	 * @since Emilias theme 1.0
	 *
	 * @param WP_HTML_Tag_Processor $processor     Processor positioned on the image.
	 * @param int                   $attachment_id Attachment ID taken from the class list.
	 * @return bool Whether the image was rebuilt.
	 */
	function emilias_theme_repair_unresolvable_image( $processor, $attachment_id ) {
		if ( null !== $processor->get_attribute( 'srcset' ) || ! $attachment_id ) {
			return false;
		}

		$src = $processor->get_attribute( 'src' );
		$src = is_string( $src ) ? $src : '';

		if ( '' === $src ) {
			return false;
		}

		list( $src ) = explode( '?', $src );
		$metadata = wp_get_attachment_metadata( $attachment_id );

		// Core resolves this successfully whenever the metadata is intact, so bail early.
		if ( ! is_array( $metadata ) || wp_image_src_get_dimensions( $src, $metadata, $attachment_id ) ) {
			return false;
		}

		$candidates = emilias_theme_attachment_image_candidates( $attachment_id );

		if ( ! $candidates ) {
			return false;
		}

		$max_width = emilias_theme_max_content_image_width();
		$srcset    = array();

		foreach ( $candidates as $width => $image ) {
			$srcset[] = $image[0] . ' ' . $width . 'w';
		}

		/*
		 * Prefer the widest candidate that still fits the allowed maximum, so the
		 * default src never exceeds it. Otherwise take the narrowest candidate that
		 * still covers the maximum, so the image is not upscaled on large displays.
		 */
		$chosen = null;

		foreach ( $candidates as $width => $image ) {
			if ( $width <= $max_width ) {
				$chosen = $image;
			}
		}

		if ( null === $chosen ) {
			$chosen = reset( $candidates );
		}

		$processor->set_attribute( 'src', $chosen[0] );
		$processor->set_attribute( 'srcset', implode( ', ', $srcset ) );

		if ( null === $processor->get_attribute( 'sizes' ) ) {
			$sizes = wp_calculate_image_sizes( array( (int) $chosen[1], (int) $chosen[2] ), $chosen[0], $metadata, $attachment_id );

			if ( $sizes ) {
				$processor->set_attribute( 'sizes', $sizes );
			}
		}

		if ( null === $processor->get_attribute( 'width' ) ) {
			$processor->set_attribute( 'width', (string) (int) $chosen[1] );
		}

		if ( null === $processor->get_attribute( 'height' ) ) {
			$processor->set_attribute( 'height', (string) (int) $chosen[2] );
		}

		return true;
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

			emilias_theme_repair_unresolvable_image( $processor, $attachment_id );

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

if ( ! function_exists( 'emilias_theme_masonry_card_sizes' ) ) :
	/**
	 * Builds a sizes attribute for a masonry grid card.
	 *
	 * The masonry grid is positioned by JavaScript: cards are laid out full width by CSS
	 * until the script measures the grid and writes a column width per card. An image
	 * that loads eagerly cannot wait for that, so a browser relying on sizes="auto"
	 * measures the card while it is still full width and picks a far larger candidate
	 * than the card renders at. Stating the card width outright removes that dependency.
	 *
	 * The grid is repeat(auto-fill, minmax(max(min(20rem, 100%), quarter), 1fr)) inside
	 * the wide size, which gives three bands rather than a single card width:
	 *
	 *   360-720px   one column,  card 320-676px
	 *   760-1080px  two columns, card 335-492px
	 *   1120px and up, three columns, card 324-411px
	 *
	 * The values below track that. 50vw covers the two column band within about 10% and
	 * never falls short of the real card, and the fixed 411px is the widest a card gets
	 * in the three column band. They were measured against this grid CSS, so revisit them
	 * if the column width, gap, content size or wide size changes.
	 *
	 * @since Emilias theme 1.0
	 *
	 * @return string A sizes attribute value.
	 */
	function emilias_theme_masonry_card_sizes() {
		$sizes_value = '(max-width: 719px) 100vw, (max-width: 1119px) 50vw, 411px';

		/**
		 * Filters the sizes attribute used for the leading masonry grid card.
		 *
		 * Only applied to the leading card, which is the one image that loads eagerly
		 * and therefore cannot benefit from layout measurement.
		 *
		 * @since Emilias theme 1.0
		 *
		 * @param string $sizes_value The sizes attribute value.
		 */
		return (string) apply_filters( 'emilias_theme_masonry_card_sizes', $sizes_value );
	}
endif;

if ( ! function_exists( 'emilias_theme_set_leading_masonry_card_sizes' ) ) :
	/**
	 * States the card width on the leading masonry image.
	 *
	 * Replaces the sizes attribute of the first image in a masonry Post Template. Later
	 * images keep sizes="auto", which measures accurately because they are lazy loaded
	 * and so resolve after the masonry script has positioned the grid.
	 *
	 * @since Emilias theme 1.0
	 *
	 * @param string $block_content The rendered block content.
	 * @return string The rendered block content.
	 */
	function emilias_theme_set_leading_masonry_card_sizes( $block_content ) {
		if ( ! class_exists( 'WP_HTML_Tag_Processor' ) ) {
			return $block_content;
		}

		$sizes = emilias_theme_masonry_card_sizes();

		if ( '' === $sizes ) {
			return $block_content;
		}

		$processor = new WP_HTML_Tag_Processor( $block_content );

		if ( ! $processor->next_tag( array( 'tag_name' => 'IMG' ) ) ) {
			return $block_content;
		}

		$processor->set_attribute( 'sizes', $sizes );

		return $processor->get_updated_html();
	}
endif;

if ( ! function_exists( 'emilias_theme_eager_masonry_leading_image' ) ) :
	/**
	 * Loads the leading masonry thumbnail eagerly.
	 *
	 * A masonry Post Template runs as a secondary query, so WordPress never counts its
	 * images towards the `wp_omit_loading_attr_threshold()` budget and marks every
	 * thumbnail `loading="lazy"`, including the one that opens the grid and becomes the
	 * largest contentful paint.
	 *
	 * Because that leading image loads eagerly, it also has to be told how wide its card
	 * will be. The masonry script has not positioned the grid at the point the browser
	 * resolves an eagerly loaded image, so it cannot measure the card itself.
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

		$block_content = emilias_theme_improve_first_image( $block_content );

		return emilias_theme_set_leading_masonry_card_sizes( $block_content );
	}
endif;
add_filter( 'render_block_core/post-template', 'emilias_theme_eager_masonry_leading_image', 20, 2 );