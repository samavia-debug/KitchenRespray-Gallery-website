<?php
/**
 * HTML building blocks used by the theme: project cards, the filter bar,
 * gallery results, the before/after slider and video / review / brochure cards.
 * Everything is printed by the server so search engines see real content.
 */

defined( 'ABSPATH' ) || exit;

function rp_card_image( $attachment_id, $alt, $eager = false ) {
	return wp_get_attachment_image(
		$attachment_id,
		'rp-card',
		false,
		array(
			'alt'      => $alt,
			'loading'  => $eager ? 'eager' : 'lazy',
			'decoding' => 'async',
			'sizes'    => '(min-width: 1100px) 33vw, (min-width: 640px) 50vw, 100vw',
		)
	);
}

function rp_project_labels( $post_id ) {
	$labels = array();
	$colour = rp_primary_colour( $post_id );
	if ( $colour ) {
		$labels[] = $colour->name;
	}
	return $labels;
}

function rp_project_card( $post_id, $eager = false ) {
	$title  = get_the_title( $post_id );
	$thumb  = (int) get_post_thumbnail_id( $post_id );
	$labels = rp_project_labels( $post_id );
	$alt    = trim( (string) get_post_meta( $thumb, '_wp_attachment_image_alt', true ) );
	ob_start();
	?>
	<article class="rp-card">
		<a href="<?php echo esc_url( get_permalink( $post_id ) ); ?>">
			<figure class="rp-card-media">
				<?php echo $thumb ? rp_card_image( $thumb, $alt ? $alt : $title, $eager ) : '<span class="rp-card-empty"></span>'; // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<?php if ( get_post_meta( $post_id, '_rp_has_ba', true ) ) : ?>
					<span class="rp-badge">Before &amp; after</span>
				<?php endif; ?>
			</figure>
			<h3 class="rp-card-title"><?php echo esc_html( $title ); ?></h3>
			<?php if ( $labels ) : ?>
				<p class="rp-card-labels"><?php echo esc_html( implode( ' · ', $labels ) ); ?></p>
			<?php endif; ?>
		</a>
	</article>
	<?php
	return ob_get_clean();
}

/* ---------- Filter bar ---------- */

function rp_filter_form( $filters ) {
	$labels = rp_filter_labels();
	ob_start();
	?>
	<form class="rp-filters" method="get" action="<?php echo esc_url( rp_gallery_base_url() ); ?>" data-rp-filters>
		<div class="rp-search">
			<label class="screen-reader-text" for="rp-q">Search projects</label>
			<input type="search" id="rp-q" name="q" value="<?php echo esc_attr( $filters['q'] ); ?>" placeholder="Search, e.g. black shaker kitchen" autocomplete="off">
			<button type="submit" class="rp-btn rp-btn-dark">Search</button>
		</div>
		<div class="rp-filter-groups">
			<?php
			foreach ( rp_public_filter_map() as $name => $taxonomy ) :
				$terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => true ) );
				if ( is_wp_error( $terms ) || ! $terms ) {
					continue;
				}
				$active = isset( $filters['tax'][ $name ] ) ? $filters['tax'][ $name ] : array();
				?>
				<details class="rp-filter" <?php echo $active ? 'data-active' : ''; ?>>
					<summary><?php echo esc_html( $labels[ $name ] ); ?><?php echo $active ? ' <span class="rp-filter-count">' . count( $active ) . '</span>' : ''; ?></summary>
					<div class="rp-filter-options">
						<?php foreach ( $terms as $term ) : ?>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[]" value="<?php echo esc_attr( $term->slug ); ?>" <?php checked( in_array( $term->slug, $active, true ) ); ?>>
								<span><?php echo esc_html( $term->name ); ?></span>
							</label>
						<?php endforeach; ?>
					</div>
				</details>
			<?php endforeach; ?>

			<?php $years = rp_project_years(); ?>
			<?php if ( $years ) : ?>
				<label class="rp-filter-select">
					<span class="screen-reader-text">Year completed</span>
					<select name="completed">
						<option value="">Any year</option>
						<?php foreach ( $years as $year ) : ?>
							<option value="<?php echo (int) $year; ?>" <?php selected( $filters['completed'], $year ); ?>><?php echo (int) $year; ?></option>
						<?php endforeach; ?>
					</select>
				</label>
			<?php endif; ?>

			<label class="rp-filter-toggle">
				<input type="checkbox" name="ba" value="1" <?php checked( $filters['ba'] ); ?>>
				<span>Before &amp; after</span>
			</label>

			<noscript><button type="submit" class="rp-btn">Apply filters</button></noscript>
		</div>
	</form>
	<?php
	return ob_get_clean();
}

/** Chips for the filters in use, each with a link that removes just that one. */
function rp_active_chips( $filters ) {
	$chips = array();
	foreach ( rp_filter_map() as $name => $taxonomy ) {
		if ( empty( $filters['tax'][ $name ] ) ) {
			continue;
		}
		foreach ( $filters['tax'][ $name ] as $slug ) {
			$term = get_term_by( 'slug', $slug, $taxonomy );
			if ( ! $term ) {
				continue;
			}
			$without                 = $filters;
			$without['tax'][ $name ] = array_values( array_diff( $filters['tax'][ $name ], array( $slug ) ) );
			$chips[]                 = array( $term->name, rp_gallery_url( $without ) );
		}
	}
	if ( '' !== $filters['q'] ) {
		$without      = $filters;
		$without['q'] = '';
		$chips[]      = array( '"' . $filters['q'] . '"', rp_gallery_url( $without ) );
	}
	if ( $filters['ba'] ) {
		$without       = $filters;
		$without['ba'] = false;
		$chips[]       = array( 'Before & after', rp_gallery_url( $without ) );
	}
	if ( $filters['completed'] ) {
		$without              = $filters;
		$without['completed'] = 0;
		$chips[]              = array( (string) $filters['completed'], rp_gallery_url( $without ) );
	}
	return $chips;
}

/**
 * The results block. $collection_url is set on landing pages such as
 * /gallery/black-kitchens/, where paging stays on that address.
 */
function rp_render_results( $filters, $collection_url = null ) {
	$result = rp_run_gallery( $filters );
	$query  = $result['query'];
	$total  = (int) $query->found_posts;
	$pg     = max( 1, (int) $filters['pg'] );
	$share  = $collection_url ? $collection_url : rp_gallery_url( $filters );
	$match  = $collection_url ? null : rp_match_collection( $filters );
	if ( $match ) {
		$share = $match['url'];
	}

	ob_start();
	?>
	<div id="rp-results" class="rp-results" data-share="<?php echo esc_url( $share ); ?>">
		<div class="rp-results-head">
			<p class="rp-count" aria-live="polite">
				<?php echo esc_html( sprintf( _n( '%s project', '%s projects', $total ), number_format_i18n( $total ) ) ); ?>
			</p>
			<?php if ( ! $collection_url ) : ?>
				<ul class="rp-chips">
					<?php foreach ( rp_active_chips( $filters ) as $chip ) : ?>
						<li><a href="<?php echo esc_url( $chip[1] ); ?>" data-rp-nav title="Remove this filter"><?php echo esc_html( $chip[0] ); ?> <span aria-hidden="true">&times;</span></a></li>
					<?php endforeach; ?>
					<?php if ( rp_has_filters( $filters ) ) : ?>
						<li><a class="rp-clear" href="<?php echo esc_url( rp_gallery_base_url() ); ?>" data-rp-nav>Clear filters</a></li>
					<?php endif; ?>
				</ul>
			<?php endif; ?>
			<button type="button" class="rp-btn rp-copy" data-rp-copy="<?php echo esc_url( $share ); ?>">Copy Gallery Link</button>
		</div>

		<?php if ( $result['loose'] ) : ?>
			<p class="rp-notice">We could not match "<?php echo esc_html( $filters['q'] ); ?>" exactly. These projects match the rest of your search.</p>
		<?php endif; ?>

		<?php if ( $query->have_posts() ) : ?>
			<div class="rp-grid" data-rp-grid>
				<?php
				$i = 0;
				foreach ( $query->posts as $post ) {
					echo rp_project_card( $post->ID, 1 === $pg && $i < 3 ); // phpcs:ignore WordPress.Security.EscapeOutput
					$i++;
				}
				?>
			</div>
			<nav class="rp-more" aria-label="More projects" data-rp-more-wrap>
				<?php if ( $pg > 1 ) : ?>
					<a class="rp-btn" rel="prev" href="<?php echo esc_url( rp_gallery_url( $filters, $pg - 1, $collection_url ) ); ?>">Previous projects</a>
				<?php endif; ?>
				<?php if ( $pg < (int) $query->max_num_pages ) : ?>
					<a class="rp-btn rp-btn-dark" rel="next" data-rp-more href="<?php echo esc_url( rp_gallery_url( $filters, $pg + 1, $collection_url ) ); ?>">Load more projects</a>
				<?php endif; ?>
			</nav>
		<?php else : ?>
			<div class="rp-empty">
				<h2>No projects match those filters yet</h2>
				<p>Try removing a filter, or browse one of these collections.</p>
				<ul class="rp-pill-list">
					<?php foreach ( array_slice( rp_collection_index(), 0, 8 ) as $collection ) : ?>
						<li><a href="<?php echo esc_url( $collection['url'] ); ?>"><?php echo esc_html( $collection['title'] ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}

/* ---------- Before / after slider ---------- */

function rp_ba_slider( $before_id, $after_id, $title = '' ) {
	$attrs  = array( 'loading' => 'lazy', 'decoding' => 'async', 'sizes' => '(min-width: 900px) 900px, 100vw' );
	$before = wp_get_attachment_image( $before_id, 'large', false, $attrs + array( 'alt' => trim( $title . ' before respray' ) ) );
	$after  = wp_get_attachment_image( $after_id, 'large', false, $attrs + array( 'alt' => trim( $title . ' after respray' ) ) );
	if ( ! $before || ! $after ) {
		return '';
	}
	ob_start();
	?>
	<div class="rp-ba" data-rp-ba style="--pos:50%">
		<div class="rp-ba-layer rp-ba-after"><?php echo $after; // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
		<div class="rp-ba-layer rp-ba-before"><?php echo $before; // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
		<span class="rp-ba-tag rp-ba-tag-before">Before</span>
		<span class="rp-ba-tag rp-ba-tag-after">After</span>
		<span class="rp-ba-handle" aria-hidden="true"></span>
		<input type="range" min="0" max="100" value="50" step="0.5" aria-label="Drag to compare before and after">
	</div>
	<?php
	return ob_get_clean();
}

/** First before photo and best after photo for a project, or null. */
function rp_ba_pair( $post_id ) {
	$before = rp_get_photos( $post_id, 'before' );
	$after  = rp_get_photos( $post_id, 'after' );
	$after_id = $after ? $after[0] : (int) get_post_thumbnail_id( $post_id );
	return ( $before && $after_id ) ? array( $before[0], $after_id ) : null;
}

/* ---------- Videos, reviews, brochures ---------- */

function rp_video_card( $post_id ) {
	$url   = get_post_meta( $post_id, '_rp_video_url', true );
	$embed = rp_video_embed_url( $url );
	$thumb = rp_video_thumbnail( $post_id, $url );
	$title = get_the_title( $post_id );
	$terms = get_the_terms( $post_id, 'rp_video_cat' );
	$related = (int) get_post_meta( $post_id, '_rp_related_project', true );
	ob_start();
	?>
	<article class="rp-video">
		<div class="rp-video-frame">
			<?php if ( $embed ) : ?>
				<button type="button" class="rp-video-play" data-rp-embed="<?php echo esc_url( $embed ); ?>" aria-label="<?php echo esc_attr( 'Play video: ' . $title ); ?>">
					<?php if ( $thumb ) : ?>
						<img src="<?php echo esc_url( $thumb ); ?>" alt="" loading="lazy" decoding="async">
					<?php endif; ?>
					<span class="rp-play-icon" aria-hidden="true"></span>
				</button>
			<?php elseif ( $url ) : ?>
				<a class="rp-video-play" href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener">Watch video</a>
			<?php endif; ?>
		</div>
		<?php if ( $terms && ! is_wp_error( $terms ) ) : ?>
			<p class="rp-eyebrow"><?php echo esc_html( $terms[0]->name ); ?></p>
		<?php endif; ?>
		<h3><?php echo esc_html( $title ); ?></h3>
		<?php
		$text = wp_strip_all_tags( get_post_field( 'post_content', $post_id ) );
		if ( $text ) {
			echo '<p>' . esc_html( wp_trim_words( $text, 30 ) ) . '</p>';
		}
		if ( $related && 'publish' === get_post_status( $related ) ) {
			echo '<p><a class="rp-link" href="' . esc_url( get_permalink( $related ) ) . '">See the project</a></p>';
		}
		?>
	</article>
	<?php
	return ob_get_clean();
}

function rp_stars( $rating ) {
	$rating = max( 0, min( 5, (int) $rating ) );
	if ( ! $rating ) {
		return '';
	}
	return '<span class="rp-stars" role="img" aria-label="' . esc_attr( $rating . ' out of 5 stars' ) . '">' . str_repeat( '&#9733;', $rating ) . '<span class="rp-stars-off">' . str_repeat( '&#9733;', 5 - $rating ) . '</span></span>';
}

function rp_review_card( $post_id ) {
	$source  = get_post_meta( $post_id, '_rp_source', true );
	$related = (int) get_post_meta( $post_id, '_rp_related_project', true );
	ob_start();
	?>
	<figure class="rp-review">
		<?php echo rp_stars( get_post_meta( $post_id, '_rp_rating', true ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		<blockquote><?php echo wp_kses_post( wpautop( get_post_field( 'post_content', $post_id ) ) ); ?></blockquote>
		<figcaption>
			<strong><?php echo esc_html( get_the_title( $post_id ) ); ?></strong>
			<?php if ( $source ) : ?>
				<span><?php echo esc_html( $source . ' review' ); ?></span>
			<?php endif; ?>
		</figcaption>
		<?php if ( $related && 'publish' === get_post_status( $related ) && has_post_thumbnail( $related ) ) : ?>
			<a class="rp-review-project" href="<?php echo esc_url( get_permalink( $related ) ); ?>">
				<?php echo rp_card_image( (int) get_post_thumbnail_id( $related ), get_the_title( $related ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<span>See this project</span>
			</a>
		<?php endif; ?>
	</figure>
	<?php
	return ob_get_clean();
}

function rp_resource_card( $post_id ) {
	$file_id = (int) get_post_meta( $post_id, '_rp_file', true );
	$url     = $file_id ? wp_get_attachment_url( $file_id ) : '';
	if ( ! $url ) {
		return '';
	}
	$path  = get_attached_file( $file_id );
	$type  = strtoupper( pathinfo( (string) $path, PATHINFO_EXTENSION ) );
	$size  = ( $path && is_readable( $path ) ) ? size_format( filesize( $path ) ) : '';
	$terms = get_the_terms( $post_id, 'rp_resource_cat' );
	$title = get_the_title( $post_id );
	ob_start();
	?>
	<article class="rp-resource">
		<a class="rp-resource-cover" href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener" aria-label="<?php echo esc_attr( 'Open ' . $title ); ?>">
			<?php
			if ( has_post_thumbnail( $post_id ) ) {
				echo get_the_post_thumbnail( $post_id, 'medium_large', array( 'loading' => 'lazy', 'alt' => '' ) );
			} else {
				echo '<span class="rp-resource-type">' . esc_html( $type ) . '</span>';
			}
			?>
		</a>
		<div>
			<?php if ( $terms && ! is_wp_error( $terms ) ) : ?>
				<p class="rp-eyebrow"><?php echo esc_html( $terms[0]->name ); ?></p>
			<?php endif; ?>
			<h3><?php echo esc_html( $title ); ?></h3>
			<?php
			$text = wp_strip_all_tags( get_post_field( 'post_content', $post_id ) );
			if ( $text ) {
				echo '<p>' . esc_html( wp_trim_words( $text, 28 ) ) . '</p>';
			}
			?>
			<a class="rp-btn" href="<?php echo esc_url( $url ); ?>" download>Download <?php echo esc_html( trim( $type . ' ' . $size ) ); ?></a>
		</div>
	</article>
	<?php
	return ob_get_clean();
}

/* ---------- Homepage helpers ---------- */

/**
 * One tile per colour that has kitchen projects: name, link and a real photo.
 * Kitchens only, so doors, furniture and baths do not appear under a colour.
 */
function rp_colour_tiles() {
	$tiles = get_transient( 'rp_colour_tiles_kitchen' );
	if ( is_array( $tiles ) ) {
		return $tiles;
	}
	$tiles = array();
	$terms = get_terms( array( 'taxonomy' => 'colour', 'hide_empty' => true, 'orderby' => 'count', 'order' => 'DESC' ) );
	foreach ( is_wp_error( $terms ) ? array() : $terms as $term ) {
		if ( 'other' === $term->slug ) {
			continue;
		}
		$latest = new WP_Query(
			array(
				'post_type'      => 'projects',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => '_thumbnail_id',
				'tax_query'      => array(
					'relation' => 'AND',
					array( 'taxonomy' => 'colour', 'field' => 'term_id', 'terms' => $term->term_id ),
					array( 'taxonomy' => 'room', 'field' => 'slug', 'terms' => 'kitchen' ),
				),
			)
		);
		if ( ! $latest->posts ) {
			continue;
		}
		$filters                   = rp_empty_filters();
		$filters['tax']['colour']  = array( $term->slug );
		$filters['tax']['service'] = array( 'kitchen' );
		$match                     = rp_match_collection( $filters );
		$tiles[]                   = array(
			'name'  => $term->name,
			'count' => (int) $latest->found_posts,
			'url'   => $match ? $match['url'] : rp_gallery_url( $filters ),
			'image' => (int) get_post_thumbnail_id( $latest->posts[0] ),
		);
	}
	usort(
		$tiles,
		function ( $a, $b ) {
			return $b['count'] - $a['count'];
		}
	);
	set_transient( 'rp_colour_tiles_kitchen', $tiles, HOUR_IN_SECONDS );
	return $tiles;
}

function rp_featured_projects( $count = 6 ) {
	$ids = get_posts(
		array(
			'post_type'      => 'projects',
			'post_status'    => 'publish',
			'posts_per_page' => $count,
			'fields'         => 'ids',
			'meta_key'       => '_rp_featured',
			'meta_value'     => '1',
		)
	);
	if ( count( $ids ) < $count ) {
		$more = get_posts(
			array(
				'post_type'      => 'projects',
				'post_status'    => 'publish',
				'posts_per_page' => $count - count( $ids ),
				'fields'         => 'ids',
				'post__not_in'   => $ids ? $ids : array( 0 ),
			)
		);
		$ids = array_merge( $ids, $more );
	}
	return $ids;
}

function rp_transformation_projects( $count = 12, $paged = 1 ) {
	return new WP_Query(
		array(
			'post_type'      => 'projects',
			'post_status'    => 'publish',
			'posts_per_page' => $count,
			'paged'          => $paged,
			'meta_key'       => '_rp_has_ba',
			'meta_value'     => '1',
		)
	);
}

function rp_related_projects( $post_id, $count = 3 ) {
	$tax_query = array( 'relation' => 'OR' );
	foreach ( array( 'colour', 'room', 'style' ) as $taxonomy ) {
		$ids = wp_get_object_terms( $post_id, $taxonomy, array( 'fields' => 'ids' ) );
		if ( $ids && ! is_wp_error( $ids ) ) {
			$tax_query[] = array( 'taxonomy' => $taxonomy, 'field' => 'term_id', 'terms' => $ids );
		}
	}
	if ( count( $tax_query ) < 2 ) {
		return array();
	}
	// Prefer the same colour; fall back to same service or style.
	$colour = rp_primary_colour( $post_id );
	$found  = array();
	if ( $colour ) {
		$found = get_posts(
			array(
				'post_type'      => 'projects',
				'post_status'    => 'publish',
				'posts_per_page' => $count,
				'fields'         => 'ids',
				'post__not_in'   => array( $post_id ),
				'tax_query'      => array( array( 'taxonomy' => 'colour', 'field' => 'term_id', 'terms' => $colour->term_id ) ),
			)
		);
	}
	if ( count( $found ) < $count ) {
		$more = get_posts(
			array(
				'post_type'      => 'projects',
				'post_status'    => 'publish',
				'posts_per_page' => $count - count( $found ),
				'fields'         => 'ids',
				'post__not_in'   => array_merge( array( $post_id ), $found ),
				'tax_query'      => $tax_query,
			)
		);
		$found = array_merge( $found, $more );
	}
	return $found;
}

/**
 * Homepage sections for services other than kitchens. Each one is a gallery
 * page (Projects > Gallery pages); a section is left out when its page is
 * missing, unpublished or has no projects.
 *
 * @return array[] title, url, count, ids (up to $count project IDs)
 */
function rp_service_sections( $count = 3 ) {
	$slugs    = (array) apply_filters( 'rp_service_section_slugs', array( 'pvc', 'furniture', 'baths-and-shower-trays' ) );
	$sections = array();
	foreach ( $slugs as $slug ) {
		$page = get_page_by_path( $slug, OBJECT, 'rp_collection' );
		if ( ! $page || 'publish' !== $page->post_status ) {
			continue;
		}
		$filters        = rp_empty_filters();
		$filters['tax'] = rp_collection_filters( $page->ID );
		if ( ! $filters['tax'] ) {
			continue;
		}
		$query = new WP_Query( array_merge( rp_query_args( $filters, $count ), array( 'fields' => 'ids' ) ) );
		if ( ! $query->posts ) {
			continue;
		}
		$sections[] = array(
			'title' => get_the_title( $page ),
			'url'   => get_permalink( $page ),
			'count' => (int) $query->found_posts,
			'ids'   => $query->posts,
		);
	}
	return $sections;
}
