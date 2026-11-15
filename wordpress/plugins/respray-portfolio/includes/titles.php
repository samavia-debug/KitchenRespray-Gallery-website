<?php
/**
 * Browser-tab titles and search-result descriptions for the pages this
 * plugin creates, when Rank Math is active.
 *
 * Without these, Rank Math falls back to generic wording such as
 * "Gallery pages Archive", or to the title of whichever page happens to be
 * set as the front page.
 */

defined( 'ABSPATH' ) || exit;

/** @return string Title for the current view, or '' to leave it alone. */
function rp_view_title() {
	$site = get_bloginfo( 'name' );
	if ( is_front_page() ) {
		return $site . ' | Completed Respray Projects, Colours and Finishes';
	}
	if ( is_post_type_archive( 'rp_collection' ) ) {
		return rp_gallery_heading( rp_request_filters() ) . ' | ' . $site;
	}
	if ( is_post_type_archive( 'rp_video' ) ) {
		return 'Video Library | ' . $site;
	}
	if ( is_post_type_archive( 'rp_review' ) ) {
		return 'Customer Reviews | ' . $site;
	}
	if ( is_post_type_archive( 'rp_resource' ) ) {
		return 'Brochures and Resources | ' . $site;
	}
	return '';
}

add_filter(
	'rank_math/frontend/title',
	function ( $title ) {
		$ours = rp_view_title();
		return '' !== $ours ? $ours : $title;
	}
);

add_filter(
	'rank_math/frontend/description',
	function ( $description ) {
		if ( is_front_page() || is_post_type_archive( 'rp_collection' ) ) {
			$ours = rp_meta_description();
			return $ours ? $ours : $description;
		}
		if ( is_singular( array( 'projects', 'rp_collection' ) ) && '' === trim( (string) $description ) ) {
			return rp_meta_description();
		}
		return $description;
	}
);

/** Social-share titles follow the same wording. */
add_filter(
	'rank_math/opengraph/facebook/og_title',
	function ( $title ) {
		$ours = rp_view_title();
		return '' !== $ours ? $ours : $title;
	}
);
