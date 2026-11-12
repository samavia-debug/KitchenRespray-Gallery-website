<?php
/**
 * Gallery engine: reads filters from the address, turns typed searches into
 * filters, runs the project query and builds shareable links.
 *
 * Address format:  /gallery/?colour=black&style=shaker&finish=matt
 * Several values:  /gallery/?colour=black,grey
 * Other options:   q= (search words)  ba=1 (before & after only)
 *                  completed=2026 (year)  pg=2 (page)
 */

defined( 'ABSPATH' ) || exit;

const RP_PER_PAGE = 24;

function rp_gallery_base_url() {
	return get_post_type_archive_link( 'rp_collection' );
}

function rp_empty_filters() {
	return array(
		'tax'       => array(),
		'q'         => '',
		'ba'        => false,
		'completed' => 0,
		'pg'        => 1,
	);
}

/** Read and clean the filters in a request. */
function rp_request_filters( $source = null ) {
	$source  = null === $source ? wp_unslash( $_GET ) : $source;
	$filters = rp_empty_filters();

	foreach ( rp_filter_map() as $name => $taxonomy ) {
		if ( empty( $source[ $name ] ) ) {
			continue;
		}
		$values = is_array( $source[ $name ] ) ? $source[ $name ] : explode( ',', (string) $source[ $name ] );
		$slugs  = array_values( array_unique( array_filter( array_map( 'sanitize_title', $values ) ) ) );
		if ( $slugs ) {
			$filters['tax'][ $name ] = array_slice( $slugs, 0, 12 );
		}
	}

	if ( ! empty( $source['q'] ) && is_string( $source['q'] ) ) {
		$filters['q'] = trim( mb_substr( sanitize_text_field( $source['q'] ), 0, 120 ) );
	}
	$filters['ba'] = ! empty( $source['ba'] );
	if ( ! empty( $source['completed'] ) && preg_match( '/^(19|20)\d{2}$/', (string) $source['completed'] ) ) {
		$filters['completed'] = (int) $source['completed'];
	}
	if ( ! empty( $source['pg'] ) ) {
		$filters['pg'] = max( 1, absint( $source['pg'] ) );
	}
	return $filters;
}

function rp_has_filters( $filters ) {
	return $filters['tax'] || '' !== $filters['q'] || $filters['ba'] || $filters['completed'];
}

/** Build the shareable address for a set of filters. */
function rp_gallery_url( $filters, $pg = 1, $base = null ) {
	$parts = array();
	if ( null === $base ) {
		$base = rp_gallery_base_url();
		foreach ( rp_filter_map() as $name => $taxonomy ) {
			if ( ! empty( $filters['tax'][ $name ] ) ) {
				$parts[] = $name . '=' . implode( ',', array_map( 'rawurlencode', $filters['tax'][ $name ] ) );
			}
		}
		if ( '' !== $filters['q'] ) {
			$parts[] = 'q=' . rawurlencode( $filters['q'] );
		}
		if ( $filters['ba'] ) {
			$parts[] = 'ba=1';
		}
		if ( $filters['completed'] ) {
			$parts[] = 'completed=' . (int) $filters['completed'];
		}
	}
	if ( $pg > 1 ) {
		$parts[] = 'pg=' . (int) $pg;
	}
	return $parts ? $base . ( false === strpos( $base, '?' ) ? '?' : '&' ) . implode( '&', $parts ) : $base;
}

function rp_query_args( $filters, $per_page = RP_PER_PAGE ) {
	$args = array(
		'post_type'           => 'projects',
		'post_status'         => 'publish',
		'posts_per_page'      => $per_page,
		'paged'               => max( 1, (int) $filters['pg'] ),
		'ignore_sticky_posts' => true,
		'orderby'             => 'date',
		'order'               => 'DESC',
	);

	$map       = rp_filter_map();
	$tax_query = array();
	foreach ( $filters['tax'] as $name => $slugs ) {
		if ( isset( $map[ $name ] ) ) {
			$tax_query[] = array(
				'taxonomy' => $map[ $name ],
				'field'    => 'slug',
				'terms'    => $slugs,
				'operator' => 'IN',
			);
		}
	}
	if ( $tax_query ) {
		$tax_query['relation'] = 'AND';
		$args['tax_query']     = $tax_query;
	}

	$meta_query = array();
	if ( $filters['ba'] ) {
		$meta_query[] = array( 'key' => '_rp_has_ba', 'value' => '1' );
	}
	if ( $filters['completed'] ) {
		$meta_query[] = array( 'key' => '_rp_year', 'value' => (string) $filters['completed'] );
	}
	if ( $meta_query ) {
		$args['meta_query'] = $meta_query;
	}

	if ( '' !== $filters['q'] ) {
		$args['s'] = $filters['q'];
	}
	return $args;
}

/**
 * Run the gallery query.
 * If the typed words match nothing but the recognised filters do, the filter
 * results are returned and flagged as "loose" so the page can say so honestly.
 *
 * @return array{query:WP_Query,loose:bool}
 */
function rp_run_gallery( $filters ) {
	$query = new WP_Query( rp_query_args( $filters ) );
	$loose = false;
	if ( ! $query->have_posts() && '' !== $filters['q'] && $filters['tax'] && 1 === (int) $filters['pg'] ) {
		$relaxed      = $filters;
		$relaxed['q'] = '';
		$retry        = new WP_Query( rp_query_args( $relaxed ) );
		if ( $retry->have_posts() ) {
			$query = $retry;
			$loose = true;
		}
	}
	return array( 'query' => $query, 'loose' => $loose );
}

/* ---------- Turning typed words into filters (works without AI) ---------- */

function rp_normalise_words( $text ) {
	$text   = strtolower( html_entity_decode( (string) $text, ENT_QUOTES, 'UTF-8' ) );
	$text   = preg_replace( '/[^a-z0-9]+/', ' ', $text );
	$tokens = array();
	foreach ( array_filter( explode( ' ', $text ), 'strlen' ) as $token ) {
		// kitchens -> kitchen, wardrobes -> wardrobe; leaves "gloss" alone.
		if ( strlen( $token ) > 3 && 's' === substr( $token, -1 ) && 'ss' !== substr( $token, -2 ) ) {
			$token = substr( $token, 0, -1 );
		}
		$tokens[] = $token;
	}
	return $tokens;
}

/** phrase (normalised) => array( filter name, term slug ) */
function rp_search_vocabulary() {
	static $vocabulary = null;
	if ( null !== $vocabulary ) {
		return $vocabulary;
	}
	$vocabulary = array();
	$exists     = array();
	foreach ( rp_filter_map() as $name => $taxonomy ) {
		$terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) );
		if ( is_wp_error( $terms ) ) {
			continue;
		}
		foreach ( $terms as $term ) {
			if ( 'other' === $term->slug || 'special' === $term->slug ) {
				continue;
			}
			$exists[ $name . ':' . $term->slug ] = true;
			foreach ( array( $term->name, str_replace( '-', ' ', $term->slug ) ) as $phrase ) {
				$phrase = implode( ' ', rp_normalise_words( $phrase ) );
				if ( '' !== $phrase && ! isset( $vocabulary[ $phrase ] ) ) {
					$vocabulary[ $phrase ] = array( $name, $term->slug );
				}
			}
		}
	}

	$synonyms = array(
		'gray'       => array( 'colour', 'grey' ),
		'navy'       => array( 'colour', 'navy-blue' ),
		'ivory'      => array( 'colour', 'cream' ),
		'matte'      => array( 'finish', 'matt' ),
		'glossy'     => array( 'finish', 'gloss' ),
		'high gloss' => array( 'finish', 'gloss' ),
		'cupboard'   => array( 'service', 'cabinet' ),
		'window'     => array( 'service', 'door' ),
		'bath'       => array( 'service', 'bathroom' ),
		'office'     => array( 'service', 'commercial' ),
		'shop'       => array( 'service', 'commercial' ),
		'table'      => array( 'service', 'furniture' ),
		'chair'      => array( 'service', 'furniture' ),
	);
	foreach ( apply_filters( 'rp_search_synonyms', $synonyms ) as $phrase => $target ) {
		if ( isset( $exists[ $target[0] . ':' . $target[1] ] ) && ! isset( $vocabulary[ $phrase ] ) ) {
			$vocabulary[ $phrase ] = $target;
		}
	}

	// Longest phrases first so "navy blue" wins over "blue".
	uksort(
		$vocabulary,
		function ( $a, $b ) {
			return strlen( $b ) - strlen( $a );
		}
	);
	return $vocabulary;
}

/**
 * "show me black shaker kitchens with gold handles"
 *   => tax: colour=black, style=shaker, service=kitchen; rest: "gold handles"
 *
 * @return array{tax:array,rest:string}
 */
function rp_parse_text( $text ) {
	$haystack = ' ' . implode( ' ', rp_normalise_words( $text ) ) . ' ';
	$tax      = array();
	foreach ( rp_search_vocabulary() as $phrase => $target ) {
		$needle = ' ' . $phrase . ' ';
		if ( false !== strpos( $haystack, $needle ) ) {
			$tax[ $target[0] ][] = $target[1];
			$haystack            = str_replace( $needle, ' ', $haystack );
		}
	}
	foreach ( $tax as $name => $slugs ) {
		$tax[ $name ] = array_values( array_unique( $slugs ) );
	}

	$stop = array( 'show', 'me', 'find', 'do', 'did', 'you', 'have', 'has', 'had', 'any', 'with', 'a', 'an', 'the', 'in', 'of', 'and', 'or', 'finish', 'finishe', 'colour', 'color', 'coloured', 'style', 'completed', 'complete', 'done', 'some', 'please', 'can', 'could', 'i', 'we', 'see', 'look', 'looking', 'for', 'like', 'that', 'are', 'is', 'there', 'picture', 'photo', 'image', 'example', 'your', 'our', 'work', 'similar', 'to', 'thi', 'this', 'ever', 'respray', 'resprayed', 'spray', 'sprayed', 'painted', 'project', 'job', 'want', 'need', 'get', 'what', 'about', 'on', 'it', 'my', 'one' );
	$rest = array_diff( array_filter( explode( ' ', $haystack ), 'strlen' ), $stop );

	return array(
		'tax'  => $tax,
		'rest' => implode( ' ', $rest ),
	);
}

/**
 * A typed search on the gallery is rewritten to its filter address, so the
 * link a visitor (or staff member) copies is always the clean, shareable one.
 */
add_action(
	'template_redirect',
	function () {
		if ( ! is_post_type_archive( 'rp_collection' ) ) {
			return;
		}
		$filters = rp_request_filters();
		// "i=1" marks words that have already been interpreted, so they are not sent round again.
		if ( '' === $filters['q'] || isset( $_GET['i'] ) ) {
			return;
		}
		// The AI module (when switched on) hooks in here for searches the
		// built-in matcher only partly understands. See includes/ai.php.
		$parsed = apply_filters( 'rp_interpret_search', rp_parse_text( $filters['q'] ), $filters['q'] );
		if ( empty( $parsed['tax'] ) && empty( $parsed['ba'] ) ) {
			return;
		}
		if ( ! empty( $parsed['ba'] ) ) {
			$filters['ba'] = true;
		}
		foreach ( $parsed['tax'] as $name => $slugs ) {
			$existing                = isset( $filters['tax'][ $name ] ) ? $filters['tax'][ $name ] : array();
			$filters['tax'][ $name ] = array_values( array_unique( array_merge( $existing, $slugs ) ) );
		}
		$filters['q'] = $parsed['rest'];
		wp_safe_redirect( rp_gallery_url( $filters ) . ( '' !== $filters['q'] ? '&i=1' : '' ), 302 );
		exit;
	}
);

/* ---------- Gallery landing pages ---------- */

function rp_collection_index() {
	$index = get_transient( 'rp_collection_index' );
	if ( is_array( $index ) ) {
		return $index;
	}
	$index = array();
	$posts = get_posts(
		array(
			'post_type'      => 'rp_collection',
			'post_status'    => 'publish',
			'posts_per_page' => 200,
			'orderby'        => 'menu_order title',
			'order'          => 'ASC',
		)
	);
	foreach ( $posts as $post ) {
		$index[] = array(
			'id'      => $post->ID,
			'title'   => $post->post_title,
			'url'     => get_permalink( $post ),
			'filters' => rp_collection_filters( $post->ID ),
		);
	}
	set_transient( 'rp_collection_index', $index, DAY_IN_SECONDS );
	return $index;
}

function rp_tax_signature( $tax ) {
	$out = array();
	foreach ( rp_filter_map() as $name => $taxonomy ) {
		if ( ! empty( $tax[ $name ] ) ) {
			$slugs = $tax[ $name ];
			sort( $slugs );
			$out[] = $name . '=' . implode( ',', $slugs );
		}
	}
	return implode( '&', $out );
}

/** The landing page whose filters are exactly these, if there is one. */
function rp_match_collection( $filters ) {
	if ( ! $filters['tax'] || '' !== $filters['q'] || $filters['ba'] || $filters['completed'] ) {
		return null;
	}
	$signature = rp_tax_signature( $filters['tax'] );
	foreach ( rp_collection_index() as $collection ) {
		if ( rp_tax_signature( $collection['filters'] ) === $signature ) {
			return $collection;
		}
	}
	return null;
}

add_action(
	'save_post_rp_collection',
	function () {
		delete_transient( 'rp_collection_index' );
	}
);

/** Years that have at least one published project. */
function rp_project_years() {
	$years = get_transient( 'rp_years' );
	if ( is_array( $years ) ) {
		return $years;
	}
	global $wpdb;
	$years = $wpdb->get_col(
		"SELECT DISTINCT pm.meta_value FROM {$wpdb->postmeta} pm
		 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
		 WHERE pm.meta_key = '_rp_year' AND pm.meta_value <> '' AND p.post_status = 'publish' AND p.post_type = 'projects'
		 ORDER BY pm.meta_value DESC"
	);
	$years = array_map( 'intval', (array) $years );
	set_transient( 'rp_years', $years, DAY_IN_SECONDS );
	return $years;
}

/** Page heading for a gallery view: the landing page's own title when the filters match one. */
function rp_gallery_heading( $filters ) {
	$match = rp_match_collection( $filters );
	if ( $match ) {
		return $match['title'];
	}
	$summary = rp_filters_summary( $filters );
	return $summary ? $summary . ' Projects' : 'Project Gallery';
}

/** Words for the page title, e.g. "Black, Shaker, Matt". */
function rp_filters_summary( $filters ) {
	$names = array();
	foreach ( rp_filter_map() as $name => $taxonomy ) {
		if ( empty( $filters['tax'][ $name ] ) ) {
			continue;
		}
		foreach ( $filters['tax'][ $name ] as $slug ) {
			$term = get_term_by( 'slug', $slug, $taxonomy );
			if ( $term ) {
				$names[] = $term->name;
			}
		}
	}
	return implode( ', ', $names );
}
