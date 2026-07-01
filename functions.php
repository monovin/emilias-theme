<?php
/**
 * Emilias theme functions and definitions.
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package WordPress
 * @subpackage Twenty_Twenty_Five
 * @since Emilias theme 1.0
 */

require_once get_parent_theme_file_path( 'inc/lqip.php' );

if ( ! function_exists( 'emilias_theme_post_format_setup' ) ) :
	/**
	 * Adds theme support for post formats.
	 *
	 * @since Emilias theme 1.0
	 *
	 * @return void
	 */
	function emilias_theme_post_format_setup() {
		add_theme_support( 'post-formats', array( 'aside', 'audio', 'chat', 'gallery', 'image', 'link', 'quote', 'status', 'video' ) );
	}
endif;

if ( ! function_exists( 'emilias_theme_image_sizes_setup' ) ) :
	/**
	 * Registers theme image sizes for responsive artwork layouts.
	 *
	 * @since Emilias theme 1.0
	 *
	 * @return void
	 */
	function emilias_theme_image_sizes_setup() {
		add_image_size( 'emilias-grid', 1024, 0, false );
		add_image_size( 'emilias-content', 1700, 0, false );
		add_image_size( 'emilias-wide', 2560, 0, false );
	}
endif;

if ( ! function_exists( 'emilias_theme_image_size_names' ) ) :
	/**
	 * Adds theme image sizes to the editor size choices.
	 *
	 * @since Emilias theme 1.0
	 *
	 * @param array $sizes Registered image size labels keyed by size name.
	 * @return array Updated image size labels.
	 */
	function emilias_theme_image_size_names( $sizes ) {
		return array_merge(
			$sizes,
			array(
				'emilias-grid'    => __( 'Emilia grid', 'emilias-theme' ),
				'emilias-content' => __( 'Emilia content', 'emilias-theme' ),
				'emilias-wide'    => __( 'Emilia wide', 'emilias-theme' ),
			)
		);
	}
endif;

if ( ! function_exists( 'emilias_theme_copyright_year' ) ) :
	/**
	 * Returns the current year for the copyright shortcode.
	 *
	 * @since Emilias theme 1.0
	 *
	 * @return string Current year in the site's timezone.
	 */
	function emilias_theme_copyright_year() {
		return esc_html( wp_date( 'Y' ) );
	}
endif;
add_shortcode( 'copyright_year', 'emilias_theme_copyright_year' );
add_action( 'after_setup_theme', 'emilias_theme_post_format_setup' );
add_action( 'after_setup_theme', 'emilias_theme_image_sizes_setup' );
add_filter( 'image_size_names_choose', 'emilias_theme_image_size_names' );

if ( ! function_exists( 'emilias_theme_editor_style' ) ) :
	/**
	 * Enqueues editor-style.css in the editors.
	 *
	 * @since Emilias theme 1.0
	 *
	 * @return void
	 */
	function emilias_theme_editor_style() {
		add_editor_style( 'assets/css/editor-style.css' );
	}
endif;
add_action( 'after_setup_theme', 'emilias_theme_editor_style' );

if ( ! function_exists( 'emilias_theme_enqueue_styles' ) ) :
	/**
	 * Enqueues the theme stylesheet on the front.
	 *
	 * @since Emilias theme 1.0
	 *
	 * @return void
	 */
	function emilias_theme_enqueue_styles() {
		$suffix = SCRIPT_DEBUG ? '' : '.min';
		$src    = 'style' . $suffix . '.css';

		wp_enqueue_style(
			'emilias-theme-style',
			get_parent_theme_file_uri( $src ),
			array(),
			wp_get_theme()->get( 'Version' )
		);
		wp_style_add_data(
			'emilias-theme-style',
			'path',
			get_parent_theme_file_path( $src )
		);
	}
endif;
add_action( 'wp_enqueue_scripts', 'emilias_theme_enqueue_styles' );

if ( ! function_exists( 'emilias_theme_block_styles' ) ) :
	/**
	 * Registers custom block styles.
	 *
	 * @since Emilias theme 1.0
	 *
	 * @return void
	 */
	function emilias_theme_block_styles() {
		$masonry_style_path  = get_theme_file_path( 'assets/css/post-template-masonry.css' );
		$masonry_script_path = get_theme_file_path( 'assets/js/post-template-masonry.js' );

		wp_register_style(
			'emilias-theme-post-template-masonry',
			get_theme_file_uri( 'assets/css/post-template-masonry.css' ),
			array(),
			file_exists( $masonry_style_path ) ? filemtime( $masonry_style_path ) : wp_get_theme()->get( 'Version' )
		);

		wp_register_script(
			'emilias-theme-post-template-masonry',
			get_theme_file_uri( 'assets/js/post-template-masonry.js' ),
			array(),
			file_exists( $masonry_script_path ) ? filemtime( $masonry_script_path ) : wp_get_theme()->get( 'Version' ),
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);

		register_block_style(
			'core/list',
			array(
				'name'         => 'checkmark-list',
				'label'        => __( 'Checkmark', 'emilias-theme' ),
				'inline_style' => '
				ul.is-style-checkmark-list {
					list-style-type: "\2713";
				}

				ul.is-style-checkmark-list li {
					padding-inline-start: 1ch;
				}',
			)
		);

		register_block_style(
			'core/post-template',
			array(
				'name'         => 'masonry',
				'label'        => __( 'Masonry', 'emilias-theme' ),
				'style_handle' => 'emilias-theme-post-template-masonry',
			)
		);

		register_block_style(
			'core/post-template',
			array(
				'name'         => 'masonry-collage',
				'label'        => __( 'Collage Masonry', 'emilias-theme' ),
				'style_handle' => 'emilias-theme-post-template-masonry',
			)
		);
	}
endif;
add_action( 'init', 'emilias_theme_block_styles' );

if ( ! function_exists( 'emilias_theme_enqueue_post_template_masonry_assets' ) ) :
	/**
	 * Enqueues the Post Template masonry enhancement.
	 *
	 * @since Emilias theme 1.0
	 *
	 * @return void
	 */
	function emilias_theme_enqueue_post_template_masonry_assets() {
		wp_enqueue_style( 'emilias-theme-post-template-masonry' );
		wp_enqueue_script( 'emilias-theme-post-template-masonry' );
	}
endif;

if ( ! function_exists( 'emilias_theme_is_masonry_post_template_class' ) ) :
	/**
	 * Checks whether a Post Template class list has a masonry style.
	 *
	 * @since Emilias theme 1.0
	 *
	 * @param string $class_name Block class name attribute.
	 * @return bool Whether the block has a masonry style class.
	 */
	function emilias_theme_is_masonry_post_template_class( $class_name ) {
		$styles = array( 'is-style-masonry', 'is-style-masonry-collage' );

		foreach ( $styles as $style ) {
			if ( false !== strpos( ' ' . $class_name . ' ', ' ' . $style . ' ' ) ) {
				return true;
			}
		}

		return false;
	}
endif;

if ( ! function_exists( 'emilias_theme_sanitize_masonry_css_length' ) ) :
	/**
	 * Sanitizes a CSS length for the masonry column-width custom property.
	 *
	 * @since Emilias theme 1.0
	 *
	 * @param mixed $value CSS length value.
	 * @return string Sanitized CSS length, or an empty string when invalid.
	 */
	function emilias_theme_sanitize_masonry_css_length( $value ) {
		if ( ! is_string( $value ) ) {
			return '';
		}

		$value = trim( $value );

		if ( '' === $value || preg_match( '/[;"\'{}<>]/', $value ) ) {
			return '';
		}

		if ( 0 === strpos( $value, 'var:preset|spacing|' ) ) {
			$slug = substr( $value, strlen( 'var:preset|spacing|' ) );

			return 'var(--wp--preset--spacing--' . sanitize_title( $slug ) . ')';
		}

		if ( preg_match( '/^(?:0|(?:\d+|\d*\.\d+)(?:px|em|rem|vw|vh|vmin|vmax|ch|ex|%))$/i', $value ) ) {
			return $value;
		}

		if ( preg_match( '/^(?:var|calc|clamp|min|max)\([a-z0-9\s+\-*\/.,()%:_-]+\)$/i', $value ) ) {
			return $value;
		}

		return '';
	}
endif;

if ( ! function_exists( 'emilias_theme_add_masonry_minimum_column_width' ) ) :
	/**
	 * Adds the saved grid minimum column width as a masonry CSS variable.
	 *
	 * @since Emilias theme 1.0
	 *
	 * @param string $block_content       The rendered block content.
	 * @param string $minimum_column_width The sanitized minimum column width.
	 * @return string The updated block content.
	 */
	function emilias_theme_add_masonry_minimum_column_width( $block_content, $minimum_column_width ) {
		if ( ! class_exists( 'WP_HTML_Tag_Processor' ) ) {
			return $block_content;
		}

		$processor = new WP_HTML_Tag_Processor( $block_content );

		if ( ! $processor->next_tag( array( 'class_name' => 'wp-block-post-template' ) ) ) {
			return $block_content;
		}

		$style = $processor->get_attribute( 'style' );
		$style = is_string( $style ) ? trim( $style ) : '';

		if ( '' !== $style && ';' !== substr( $style, -1 ) ) {
			$style .= ';';
		}

		$style .= '--masonry-min-column-width:' . $minimum_column_width . ';';
		$processor->set_attribute( 'style', $style );

		return $processor->get_updated_html();
	}
endif;

if ( ! function_exists( 'emilias_theme_enqueue_rendered_masonry_assets' ) ) :
	/**
	 * Enqueues masonry assets only when a masonry Post Template is rendered.
	 *
	 * @since Emilias theme 1.0
	 *
	 * @param string $block_content The rendered block content.
	 * @param array  $block         The parsed block.
	 * @return string The rendered block content.
	 */
	function emilias_theme_enqueue_rendered_masonry_assets( $block_content, $block ) {
		$class_name = isset( $block['attrs']['className'] ) ? $block['attrs']['className'] : '';

		if ( ! emilias_theme_is_masonry_post_template_class( $class_name ) ) {
			return $block_content;
		}

		emilias_theme_enqueue_post_template_masonry_assets();

		$minimum_column_width = isset( $block['attrs']['layout']['minimumColumnWidth'] )
			? emilias_theme_sanitize_masonry_css_length( $block['attrs']['layout']['minimumColumnWidth'] )
			: '';

		if ( '' !== $minimum_column_width ) {
			$block_content = emilias_theme_add_masonry_minimum_column_width( $block_content, $minimum_column_width );
		}

		return $block_content;
	}
endif;
add_filter( 'render_block_core/post-template', 'emilias_theme_enqueue_rendered_masonry_assets', 10, 2 );

if ( ! function_exists( 'emilias_theme_enqueue_masonry_editor_assets' ) ) :
	/**
	 * Enqueues the masonry enhancement in the block editor for live previews.
	 *
	 * @since Emilias theme 1.0
	 *
	 * @return void
	 */
	function emilias_theme_enqueue_masonry_editor_assets() {
		emilias_theme_enqueue_post_template_masonry_assets();
	}
endif;
add_action( 'enqueue_block_editor_assets', 'emilias_theme_enqueue_masonry_editor_assets' );

if ( ! function_exists( 'emilias_theme_pattern_categories' ) ) :
	/**
	 * Registers pattern categories.
	 *
	 * @since Emilias theme 1.0
	 *
	 * @return void
	 */
	function emilias_theme_pattern_categories() {

		register_block_pattern_category(
			'emilias_theme_page',
			array(
				'label'       => __( 'Pages', 'emilias-theme' ),
				'description' => __( 'A collection of full page layouts.', 'emilias-theme' ),
			)
		);

		register_block_pattern_category(
			'emilias_theme_post-format',
			array(
				'label'       => __( 'Post formats', 'emilias-theme' ),
				'description' => __( 'A collection of post format patterns.', 'emilias-theme' ),
			)
		);
	}
endif;
add_action( 'init', 'emilias_theme_pattern_categories' );

if ( ! function_exists( 'emilias_theme_register_block_bindings' ) ) :
	/**
	 * Registers the post format block binding source.
	 *
	 * @since Emilias theme 1.0
	 *
	 * @return void
	 */
	function emilias_theme_register_block_bindings() {
		register_block_bindings_source(
			'emilias-theme/format',
			array(
				'label'              => _x( 'Post format name', 'Label for the block binding placeholder in the editor', 'emilias-theme' ),
				'get_value_callback' => 'emilias_theme_format_binding',
			)
		);
	}
endif;
add_action( 'init', 'emilias_theme_register_block_bindings' );

if ( ! function_exists( 'emilias_theme_format_binding' ) ) :
	/**
	 * Callback function for the post format name block binding source.
	 *
	 * @since Emilias theme 1.0
	 *
	 * @return string|void Post format name, or nothing if the format is 'standard'.
	 */
	function emilias_theme_format_binding() {
		$post_format_slug = get_post_format();

		if ( $post_format_slug && 'standard' !== $post_format_slug ) {
			return get_post_format_string( $post_format_slug );
		}
	}
endif;
