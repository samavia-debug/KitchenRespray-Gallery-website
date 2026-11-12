<?php
/**
 * Respray Photos theme. Layout and styling only: all content, queries and
 * gallery logic come from the Respray Portfolio plugin.
 */

defined( 'ABSPATH' ) || exit;

define( 'RPT_VERSION', '0.1.0' );

add_action(
	'after_setup_theme',
	function () {
		add_theme_support( 'title-tag' );
		add_theme_support( 'post-thumbnails' );
		add_theme_support( 'custom-logo', array( 'height' => 80, 'width' => 320, 'flex-width' => true, 'flex-height' => true ) );
		add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script' ) );
		register_nav_menus( array( 'primary' => 'Main menu', 'footer' => 'Footer menu' ) );
	}
);

add_action(
	'wp_enqueue_scripts',
	function () {
		wp_enqueue_style( 'rpt-main', get_template_directory_uri() . '/assets/css/main.css', array(), RPT_VERSION );
		wp_enqueue_script( 'rpt-main', get_template_directory_uri() . '/assets/js/main.js', array(), RPT_VERSION, array( 'strategy' => 'defer', 'in_footer' => true ) );
		// Nothing on this site uses the block library styles or emoji script.
		wp_dequeue_style( 'wp-block-library' );
		wp_dequeue_style( 'global-styles' );
		wp_dequeue_style( 'classic-theme-styles' );
	},
	20
);
remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
remove_action( 'wp_print_styles', 'print_emoji_styles' );

/** True when the Respray Portfolio plugin is active. */
function rpt_ready() {
	return function_exists( 'rp_render_results' );
}

function rpt_setting( $key ) {
	return function_exists( 'rp_setting' ) ? rp_setting( $key ) : '';
}

/** Menu shown until a custom menu is set under Appearance > Menus. */
function rpt_default_menu() {
	$items = array(
		'Gallery'         => home_url( '/gallery/' ),
		'Transformations' => home_url( '/transformations/' ),
		'Videos'          => home_url( '/videos/' ),
		'Reviews'         => home_url( '/reviews/' ),
		'Brochures'       => home_url( '/resources/' ),
		'About'           => home_url( '/about/' ),
	);
	$path = trailingslashit( (string) wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '', PHP_URL_PATH ) );
	echo '<ul class="rpt-menu">';
	foreach ( $items as $label => $url ) {
		$current = 0 === strpos( $path, trailingslashit( (string) wp_parse_url( $url, PHP_URL_PATH ) ) );
		printf( '<li><a href="%s"%s>%s</a></li>', esc_url( $url ), $current ? ' aria-current="page"' : '', esc_html( $label ) );
	}
	echo '</ul>';
}

/**
 * Breadcrumb trail. $items = array( array( label, url ), ..., array( label ) ).
 * Also printed as structured data for search engines.
 */
function rpt_breadcrumbs( $items ) {
	array_unshift( $items, array( 'Home', home_url( '/' ) ) );
	$schema = array();
	echo '<nav class="rpt-crumbs" aria-label="Breadcrumb"><ol>';
	foreach ( $items as $i => $item ) {
		$is_last = $i === count( $items ) - 1;
		if ( ! $is_last && ! empty( $item[1] ) ) {
			printf( '<li><a href="%s">%s</a></li>', esc_url( $item[1] ), esc_html( $item[0] ) );
		} else {
			printf( '<li aria-current="page">%s</li>', esc_html( $item[0] ) );
		}
		$entry = array( '@type' => 'ListItem', 'position' => $i + 1, 'name' => $item[0] );
		if ( ! empty( $item[1] ) ) {
			$entry['item'] = $item[1];
		}
		$schema[] = $entry;
	}
	echo '</ol></nav>';
	if ( ! defined( 'RANK_MATH_VERSION' ) ) {
		echo '<script type="application/ld+json">' . wp_json_encode( array( '@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $schema ), JSON_UNESCAPED_SLASHES ) . '</script>';
	}
}

function rpt_section_head( $eyebrow, $title, $link_label = '', $link_url = '' ) {
	echo '<header class="rpt-section-head"><div>';
	if ( $eyebrow ) {
		echo '<p class="rp-eyebrow">' . esc_html( $eyebrow ) . '</p>';
	}
	echo '<h2>' . esc_html( $title ) . '</h2></div>';
	if ( $link_label && $link_url ) {
		printf( '<a class="rp-link" href="%s">%s</a>', esc_url( $link_url ), esc_html( $link_label ) );
	}
	echo '</header>';
}

function rpt_plugin_missing() {
	echo '<main class="rpt-wrap rpt-page"><h1>Almost there</h1><p>Please activate the Respray Portfolio plugin to show the gallery.</p></main>';
}
