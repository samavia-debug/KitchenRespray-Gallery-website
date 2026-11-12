<?php
/**
 * Search-engine rules.
 *
 * Indexed:      /gallery/, landing pages (/gallery/black-kitchens/), every project.
 * Not indexed:  filter combinations (/gallery/?colour=black&...), which point
 *               their canonical at the matching landing page or at /gallery/.
 * Works on its own and alongside Rank Math.
 */

defined( 'ABSPATH' ) || exit;

function rp_has_seo_plugin() {
	return defined( 'RANK_MATH_VERSION' ) || defined( 'WPSEO_VERSION' );
}

/** @return array{noindex:bool,canonical:string}|null Null when not on a gallery view. */
function rp_gallery_seo() {
	if ( is_post_type_archive( 'rp_collection' ) ) {
		$filters = rp_request_filters();
		$match   = rp_match_collection( $filters );
		return array(
			'noindex'   => rp_has_filters( $filters ) || $filters['pg'] > 1,
			'canonical' => $match ? $match['url'] : rp_gallery_base_url(),
		);
	}
	if ( is_singular( 'rp_collection' ) ) {
		$filters = rp_request_filters();
		return array(
			'noindex'   => $filters['pg'] > 1,
			'canonical' => get_permalink(),
		);
	}
	return null;
}

add_filter(
	'wp_robots',
	function ( $robots ) {
		$seo = rp_gallery_seo();
		if ( $seo && $seo['noindex'] ) {
			unset( $robots['index'], $robots['max-image-preview'] );
			$robots['noindex'] = true;
			$robots['follow']  = true;
		}
		return $robots;
	}
);

add_filter(
	'rank_math/frontend/robots',
	function ( $robots ) {
		$seo = rp_gallery_seo();
		if ( $seo && $seo['noindex'] ) {
			$robots['index']  = 'noindex';
			$robots['follow'] = 'follow';
		}
		return $robots;
	}
);

add_filter(
	'rank_math/frontend/canonical',
	function ( $canonical ) {
		$seo = rp_gallery_seo();
		return $seo ? $seo['canonical'] : $canonical;
	}
);

add_action(
	'wp_head',
	function () {
		if ( rp_has_seo_plugin() ) {
			return;
		}
		$seo = rp_gallery_seo();
		if ( $seo ) {
			echo '<link rel="canonical" href="' . esc_url( $seo['canonical'] ) . '">' . "\n";
		}
		$description = rp_meta_description();
		if ( $description ) {
			echo '<meta name="description" content="' . esc_attr( $description ) . '">' . "\n";
		}
	},
	5
);

function rp_meta_description() {
	if ( is_front_page() ) {
		return rp_setting( 'subtext' );
	}
	if ( is_singular( 'projects' ) ) {
		$text = wp_strip_all_tags( get_post_field( 'post_content', get_the_ID() ) );
		if ( $text ) {
			return wp_trim_words( $text, 28, '…' );
		}
		$service = rp_first_term( get_the_ID(), 'room' );
		$labels  = rp_project_labels( get_the_ID() );
		return trim( get_the_title() . '. ' . ( $service ? $service->name . ' project' : 'Completed project' ) . ( $labels ? ' in ' . strtolower( implode( ', ', $labels ) ) : '' ) . '. See the photos.' );
	}
	if ( is_singular( 'rp_collection' ) ) {
		return wp_trim_words( wp_strip_all_tags( get_post_field( 'post_content', get_the_ID() ) ), 28, '…' );
	}
	if ( is_post_type_archive( 'rp_collection' ) ) {
		return 'Browse our completed respray projects by colour, style, finish and service.';
	}
	return '';
}

add_filter(
	'document_title_parts',
	function ( $parts ) {
		if ( is_post_type_archive( 'rp_collection' ) ) {
			$parts['title'] = rp_gallery_heading( rp_request_filters() );
		} elseif ( is_post_type_archive( 'rp_video' ) ) {
			$parts['title'] = 'Video Library';
		} elseif ( is_post_type_archive( 'rp_review' ) ) {
			$parts['title'] = 'Customer Reviews';
		} elseif ( is_post_type_archive( 'rp_resource' ) ) {
			$parts['title'] = 'Brochures and Resources';
		}
		return $parts;
	}
);

/** Reviews and brochures live on their list pages; single pages add nothing. */
add_action(
	'template_redirect',
	function () {
		foreach ( array( 'rp_review', 'rp_resource' ) as $type ) {
			if ( is_singular( $type ) ) {
				wp_safe_redirect( get_post_type_archive_link( $type ), 301 );
				exit;
			}
		}
	}
);

add_filter(
	'wp_sitemaps_post_types',
	function ( $types ) {
		unset( $types['rp_review'], $types['rp_resource'] );
		return $types;
	}
);

/** Structured data for a project: the photos and what they show. */
add_action(
	'wp_head',
	function () {
		if ( ! is_singular( 'projects' ) ) {
			return;
		}
		$id     = get_the_ID();
		$images = array();
		$ids    = array_unique( array_merge( array( (int) get_post_thumbnail_id( $id ) ), rp_get_photos( $id, 'after' ), rp_get_photos( $id, 'gallery' ), rp_get_photos( $id, 'before' ) ) );
		foreach ( array_filter( $ids ) as $image_id ) {
			$src = wp_get_attachment_image_src( $image_id, 'rp-wide' );
			if ( $src ) {
				$images[] = array(
					'@type'      => 'ImageObject',
					'contentUrl' => $src[0],
					'width'      => $src[1],
					'height'     => $src[2],
					'caption'    => (string) get_post_meta( $image_id, '_wp_attachment_image_alt', true ),
				);
			}
		}
		$data = array(
			'@context'      => 'https://schema.org',
			'@type'         => 'ImageGallery',
			'name'          => get_the_title( $id ),
			'url'           => get_permalink( $id ),
			'description'   => rp_meta_description(),
			'datePublished' => get_the_date( 'c', $id ),
			'image'         => $images,
		);
		$completed = get_post_meta( $id, '_rp_completed', true );
		if ( $completed ) {
			$data['dateCreated'] = $completed;
		}
		echo '<script type="application/ld+json">' . wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
	},
	20
);
