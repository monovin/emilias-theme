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
			true
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

		if ( false !== strpos( ' ' . $class_name . ' ', ' is-style-masonry ' ) ) {
			emilias_theme_enqueue_post_template_masonry_assets();
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
