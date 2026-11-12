<?php
/**
 * Editing screens for gallery pages, videos, reviews and brochures.
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'add_meta_boxes',
	function () {
		add_meta_box( 'rp-collection', 'Which projects does this page show?', 'rp_collection_box', 'rp_collection', 'normal', 'high' );
		add_meta_box( 'rp-video', 'Video', 'rp_video_box', 'rp_video', 'normal', 'high' );
		add_meta_box( 'rp-review', 'Review details', 'rp_review_box', 'rp_review', 'normal', 'high' );
		add_meta_box( 'rp-resource', 'File', 'rp_resource_box', 'rp_resource', 'normal', 'high' );
	}
);

/* ---------- Gallery pages ---------- */

function rp_collection_filters( $post_id ) {
	$filters = get_post_meta( $post_id, '_rp_filters', true );
	$clean   = array();
	foreach ( rp_filter_map() as $name => $taxonomy ) {
		if ( ! empty( $filters[ $name ] ) ) {
			$clean[ $name ] = array_values( array_map( 'sanitize_title', (array) $filters[ $name ] ) );
		}
	}
	return $clean;
}

function rp_collection_box( $post ) {
	wp_nonce_field( 'rp_save_extra', 'rp_extra_nonce' );
	$current = rp_collection_filters( $post->ID );
	$labels  = rp_filter_labels();
	echo '<p class="description">Tick what the projects must match. Example: Colour "Black" and Service "Kitchen Respray" makes a Black Kitchens page. The page address comes from the title, e.g. /gallery/black-kitchens/. The text you write above appears as the introduction.</p>';
	echo '<div class="rp-filter-grid">';
	foreach ( rp_filter_map() as $name => $taxonomy ) {
		echo '<fieldset><legend>' . esc_html( $labels[ $name ] ) . '</legend>';
		foreach ( get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) ) as $term ) {
			$on = isset( $current[ $name ] ) && in_array( $term->slug, $current[ $name ], true );
			printf( '<label><input type="checkbox" name="rp_filters[%s][]" value="%s" %s> %s</label><br>', esc_attr( $name ), esc_attr( $term->slug ), checked( $on, true, false ), esc_html( $term->name ) );
		}
		echo '</fieldset>';
	}
	echo '</div>';
}

/* ---------- Videos ---------- */

function rp_video_box( $post ) {
	wp_nonce_field( 'rp_save_extra', 'rp_extra_nonce' );
	$url     = get_post_meta( $post->ID, '_rp_video_url', true );
	$related = (int) get_post_meta( $post->ID, '_rp_related_project', true );
	?>
	<p><label for="rp-video-url"><strong>Video link</strong> (YouTube or Vimeo)</label><br>
	<input type="url" class="large-text" id="rp-video-url" name="rp_video_url" value="<?php echo esc_attr( $url ); ?>" placeholder="https://www.youtube.com/watch?v=..."></p>
	<p class="description">Upload the video to YouTube or Vimeo first, then paste its link here. The thumbnail is taken from YouTube unless you set a featured image.</p>
	<?php
	rp_related_project_field( $related );
}

function rp_related_project_field( $related ) {
	?>
	<p><label for="rp-related"><strong>Related project</strong> (optional)</label><br>
	<input type="text" class="large-text" id="rp-related" name="rp_related_project" value="<?php echo $related ? esc_attr( get_permalink( $related ) ) : ''; ?>" placeholder="Paste the project's web address"></p>
	<?php
}

/* ---------- Reviews ---------- */

function rp_review_box( $post ) {
	wp_nonce_field( 'rp_save_extra', 'rp_extra_nonce' );
	$rating  = (int) get_post_meta( $post->ID, '_rp_rating', true );
	$source  = get_post_meta( $post->ID, '_rp_source', true );
	$related = (int) get_post_meta( $post->ID, '_rp_related_project', true );
	?>
	<p class="description">Title = the customer's name as it should appear. Main box = their words, exactly as written. Only add genuine reviews.</p>
	<p><label for="rp-rating"><strong>Star rating</strong></label><br>
	<select id="rp-rating" name="rp_rating">
		<option value="0">No rating</option>
		<?php for ( $i = 5; $i >= 1; $i-- ) : ?>
			<option value="<?php echo (int) $i; ?>" <?php selected( $rating, $i ); ?>><?php echo (int) $i; ?> stars</option>
		<?php endfor; ?>
	</select></p>
	<p><label for="rp-source"><strong>Where the review was left</strong></label><br>
	<input type="text" class="regular-text" id="rp-source" name="rp_source" value="<?php echo esc_attr( $source ); ?>" placeholder="Google, Facebook, email..."></p>
	<?php
	rp_related_project_field( $related );
	echo '<p class="description">Link a project only if the customer agreed to their photos being shown with the review.</p>';
}

/* ---------- Brochures ---------- */

function rp_resource_box( $post ) {
	wp_nonce_field( 'rp_save_extra', 'rp_extra_nonce' );
	$file_id = (int) get_post_meta( $post->ID, '_rp_file', true );
	$name    = $file_id ? wp_basename( (string) get_attached_file( $file_id ) ) : 'No file chosen';
	?>
	<div data-rp-file>
		<input type="hidden" name="rp_file" value="<?php echo $file_id ? (int) $file_id : ''; ?>">
		<p><strong data-rp-filename><?php echo esc_html( $name ); ?></strong></p>
		<button type="button" class="button" data-rp-pick>Choose or upload file</button>
		<button type="button" class="button-link" data-rp-clear>Remove</button>
		<p class="description">Usually a PDF. Title and description go in the boxes above; the cover picture is the featured image.</p>
	</div>
	<?php
}

/* ---------- Saving ---------- */

add_action(
	'save_post',
	function ( $post_id, $post ) {
		if ( ! in_array( $post->post_type, array( 'rp_collection', 'rp_video', 'rp_review', 'rp_resource' ), true ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( empty( $_POST['rp_extra_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['rp_extra_nonce'] ), 'rp_save_extra' ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( 'rp_collection' === $post->post_type ) {
			$in      = isset( $_POST['rp_filters'] ) ? (array) wp_unslash( $_POST['rp_filters'] ) : array();
			$filters = array();
			foreach ( rp_filter_map() as $name => $taxonomy ) {
				if ( ! empty( $in[ $name ] ) ) {
					$filters[ $name ] = array_values( array_map( 'sanitize_title', (array) $in[ $name ] ) );
				}
			}
			update_post_meta( $post_id, '_rp_filters', $filters );
			delete_transient( 'rp_collection_index' );
		}

		if ( 'rp_video' === $post->post_type ) {
			update_post_meta( $post_id, '_rp_video_url', esc_url_raw( trim( (string) wp_unslash( isset( $_POST['rp_video_url'] ) ? $_POST['rp_video_url'] : '' ) ) ) );
		}

		if ( 'rp_review' === $post->post_type ) {
			update_post_meta( $post_id, '_rp_rating', min( 5, absint( isset( $_POST['rp_rating'] ) ? $_POST['rp_rating'] : 0 ) ) );
			update_post_meta( $post_id, '_rp_source', sanitize_text_field( wp_unslash( isset( $_POST['rp_source'] ) ? $_POST['rp_source'] : '' ) ) );
		}

		if ( 'rp_resource' === $post->post_type ) {
			update_post_meta( $post_id, '_rp_file', absint( isset( $_POST['rp_file'] ) ? $_POST['rp_file'] : 0 ) );
		}

		if ( isset( $_POST['rp_related_project'] ) ) {
			$raw     = trim( (string) wp_unslash( $_POST['rp_related_project'] ) );
			$related = is_numeric( $raw ) ? absint( $raw ) : url_to_postid( esc_url_raw( $raw ) );
			update_post_meta( $post_id, '_rp_related_project', ( $related && 'projects' === get_post_type( $related ) ) ? $related : 0 );
		}
	},
	10,
	2
);

add_action(
	'deleted_post',
	function () {
		delete_transient( 'rp_collection_index' );
	}
);

/* ---------- Video helpers ---------- */

/** @return array{provider:string,id:string}|null */
function rp_parse_video( $url ) {
	$url = trim( (string) $url );
	if ( preg_match( '~(?:youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/)|youtu\.be/)([A-Za-z0-9_-]{6,20})~', $url, $m ) ) {
		return array( 'provider' => 'youtube', 'id' => $m[1] );
	}
	if ( preg_match( '~vimeo\.com/(?:video/)?(\d+)~', $url, $m ) ) {
		return array( 'provider' => 'vimeo', 'id' => $m[1] );
	}
	return null;
}

function rp_video_embed_url( $url ) {
	$video = rp_parse_video( $url );
	if ( ! $video ) {
		return '';
	}
	return 'youtube' === $video['provider']
		? 'https://www.youtube-nocookie.com/embed/' . $video['id'] . '?autoplay=1&rel=0'
		: 'https://player.vimeo.com/video/' . $video['id'] . '?autoplay=1';
}

function rp_video_thumbnail( $post_id, $url ) {
	if ( $post_id && has_post_thumbnail( $post_id ) ) {
		return get_the_post_thumbnail_url( $post_id, 'rp-card' );
	}
	$video = rp_parse_video( $url );
	return ( $video && 'youtube' === $video['provider'] ) ? 'https://i.ytimg.com/vi/' . $video['id'] . '/hqdefault.jpg' : '';
}
