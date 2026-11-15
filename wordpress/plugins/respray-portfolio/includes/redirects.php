<?php
/**
 * 301 redirects from the old site's addresses to their new homes, so links
 * already shared with customers and anything Google knows keep working.
 *
 * Old project addresses (/projects/<name>/) are unchanged and need no redirect.
 */

defined( 'ABSPATH' ) || exit;

/** Old "/<name>-collection/" pages => gallery landing page slug, or filters. */
function rp_old_collection_map() {
	return apply_filters(
		'rp_old_collection_map',
		array(
			'black'        => 'black-kitchens',
			'white'        => 'white-kitchens',
			'grey'         => 'grey-kitchens',
			'cream'        => 'cream-kitchens',
			'green'        => 'green-kitchens',
			'blue'         => 'blue-kitchens',
			'kitchen'      => 'kitchens',
			'wardrobe'     => 'wardrobes',
			'furniture'    => 'furniture',
			'commercial'   => 'commercial',
			'door'         => 'doors',
			'bathroom'     => 'bathrooms',
			'yellow'       => array( 'colour' => 'yellow' ),
			'oak'          => array( 'colour' => 'oak' ),
			'walnut'       => array( 'colour' => 'walnut' ),
			'bedroom'      => array( 'service' => 'bedroom' ),
			'living-room'  => array( 'service' => 'living-room' ),
			'office'       => array( 'service' => 'office' ),
			'garage'       => array( 'service' => 'garage' ),
			'farrow-ball'  => array( 'brand' => 'farrow-and-ball' ),
		)
	);
}

function rp_redirect_target( $path ) {
	$path = trim( strtolower( $path ), '/' );

	if ( 'collections' === $path || 'projects' === $path ) {
		return rp_gallery_base_url();
	}
	if ( 'colourtrend' === $path ) {
		return rp_gallery_base_url() . '?brand=colortrend-paints';
	}

	if ( preg_match( '~^([a-z0-9-]+)-collection$~', $path, $m ) ) {
		$map = rp_old_collection_map();
		if ( isset( $map[ $m[1] ] ) ) {
			$target = $map[ $m[1] ];
			if ( is_array( $target ) ) {
				return rp_gallery_base_url() . '?' . http_build_query( $target );
			}
			$page = get_page_by_path( $target, OBJECT, 'rp_collection' );
			return ( $page && 'publish' === $page->post_status ) ? get_permalink( $page ) : rp_gallery_base_url();
		}
		return rp_gallery_base_url();
	}

	// Old category archives: /colour/black/, /finish/matt/, /room/kitchen/ ...
	if ( preg_match( '~^(colour|finish|style|room|brand|material)/([a-z0-9-]+)$~', $path, $m ) ) {
		$name = 'room' === $m[1] ? 'service' : $m[1];
		$slug = 'yelllow' === $m[2] ? 'yellow' : $m[2];
		$filters                 = rp_empty_filters();
		$filters['tax'][ $name ] = array( $slug );
		$match                   = rp_match_collection( $filters );
		return $match ? $match['url'] : rp_gallery_url( $filters );
	}
	if ( preg_match( '~^(size|project-type)/~', $path ) ) {
		return rp_gallery_base_url();
	}
	return '';
}

add_action(
	'template_redirect',
	function () {
		$path   = (string) wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '', PHP_URL_PATH );
		$home   = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		if ( $home && 0 === strpos( $path, $home ) ) {
			$path = substr( $path, strlen( $home ) );
		}
		// When the new site runs on the old install, the old collection pages
		// still exist as WordPress pages and the old category addresses can
		// still be answered by leftover address rules, so these known old
		// addresses are redirected even when they are not "not found".
		$old_address = preg_match( '~^(collections|colourtrend|projects|[a-z0-9-]+-collection|(colour|finish|style|room|brand|material|size|project-type)/[a-z0-9-]+)$~', trim( strtolower( $path ), '/' ) );
		if ( ! is_404() && ! $old_address ) {
			return;
		}
		$target = rp_redirect_target( $path );
		if ( $target ) {
			wp_safe_redirect( $target, 301 );
			exit;
		}
	},
	1
);
