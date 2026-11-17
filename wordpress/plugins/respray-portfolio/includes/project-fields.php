<?php
/**
 * The project editing screen: photos (before / after / more), details,
 * colour selection, required-field checks and the project list columns.
 */

defined( 'ABSPATH' ) || exit;

/* ---------- Reading project data ---------- */

function rp_photo_groups() {
	return array(
		'before'  => array( 'Before photos', 'How it looked before the work.' ),
		'after'   => array( 'After photos', 'The finished result. The first one becomes the main photo if none is chosen.' ),
		'gallery' => array( 'More photos', 'Details, close-ups and anything else. New uploads can be dragged from here into Before or After.' ),
	);
}

/** @return int[] attachment IDs */
function rp_get_photos( $post_id, $group ) {
	$ids = get_post_meta( $post_id, '_rp_' . $group, true );
	return array_values( array_filter( array_map( 'absint', (array) $ids ) ) );
}

function rp_primary_colour( $post_id ) {
	$term_id = (int) get_post_meta( $post_id, '_rp_primary_colour', true );
	$term    = $term_id ? get_term( $term_id, 'colour' ) : null;
	if ( $term && ! is_wp_error( $term ) ) {
		return $term;
	}
	$terms = get_the_terms( $post_id, 'colour' );
	return ( $terms && ! is_wp_error( $terms ) ) ? $terms[0] : null;
}

function rp_first_term( $post_id, $taxonomy ) {
	$terms = get_the_terms( $post_id, $taxonomy );
	return ( $terms && ! is_wp_error( $terms ) ) ? $terms[0] : null;
}

/* ---------- Editor screen ---------- */

add_action(
	'add_meta_boxes_projects',
	function () {
		remove_meta_box( 'colourdiv', 'projects', 'side' );
		remove_meta_box( 'slugdiv', 'projects', 'normal' );
		if ( ! current_user_can( 'manage_options' ) ) {
			remove_meta_box( 'authordiv', 'projects', 'normal' );
		}
		add_meta_box( 'rp-photos', 'Project photos', 'rp_photos_box', 'projects', 'normal', 'high' );
		add_meta_box( 'rp-details', 'Project details', 'rp_details_box', 'projects', 'normal', 'high' );
		add_meta_box( 'rp-colour', 'Colour', 'rp_colour_box', 'projects', 'side', 'high' );
	}
);

add_action(
	'edit_form_after_title',
	function ( $post ) {
		if ( 'projects' === $post->post_type ) {
			echo '<p class="rp-editor-hint">Description: a few sentences about the project (optional).</p>';
		}
	}
);

function rp_photos_box( $post ) {
	wp_nonce_field( 'rp_save_project', 'rp_project_nonce' );
	echo '<p class="description">Click a button to upload or choose photos. You can select many at once, drag to reorder, and drag photos between the three boxes.</p>';
	echo '<div class="rp-photo-groups">';
	foreach ( rp_photo_groups() as $group => $labels ) {
		$ids = rp_get_photos( $post->ID, $group );
		printf( '<div class="rp-photos" data-rp-photos data-title="%s">', esc_attr( $labels[0] ) );
		printf( '<h4>%s</h4><p class="description">%s</p>', esc_html( $labels[0] ), esc_html( $labels[1] ) );
		printf( '<input type="hidden" name="rp_photos[%s]" value="%s">', esc_attr( $group ), esc_attr( implode( ',', $ids ) ) );
		echo '<ul class="rp-photo-list">';
		foreach ( $ids as $id ) {
			$src = wp_get_attachment_image_url( $id, 'thumbnail' );
			if ( $src ) {
				printf( '<li data-id="%d"><img src="%s" alt=""><button type="button" class="rp-remove" aria-label="Remove photo">&times;</button></li>', (int) $id, esc_url( $src ) );
			}
		}
		echo '</ul>';
		printf( '<button type="button" class="button" data-rp-add>Add %s</button>', esc_html( strtolower( $labels[0] ) ) );
		echo '</div>';
	}
	echo '</div>';
}

function rp_details_box( $post ) {
	$get = function ( $key ) use ( $post ) {
		return get_post_meta( $post->ID, '_rp_' . $key, true );
	};
	?>
	<table class="form-table rp-details" role="presentation">
		<tr>
			<th><label for="rp-original-colour">Original colour</label></th>
			<td><input type="text" class="regular-text" id="rp-original-colour" name="rp[original_colour]" value="<?php echo esc_attr( $get( 'original_colour' ) ); ?>" placeholder="e.g. Oak, cream"></td>
		</tr>
		<tr>
			<th><label for="rp-new-colour">New colour name</label></th>
			<td><input type="text" class="regular-text" id="rp-new-colour" name="rp[new_colour]" value="<?php echo esc_attr( $get( 'new_colour' ) ); ?>" placeholder="e.g. Farrow &amp; Ball Railings">
			<p class="description">The exact paint colour, if you want to show it. The colour group is chosen in the Colour box.</p></td>
		</tr>
		<tr>
			<th><label for="rp-location">Location</label></th>
			<td><input type="text" class="regular-text" id="rp-location" name="rp[location]" value="<?php echo esc_attr( $get( 'location' ) ); ?>" placeholder="Town or area only, never a street address"></td>
		</tr>
		<tr>
			<th><label for="rp-completed">Completion date</label></th>
			<td><input type="date" id="rp-completed" name="rp[completed]" value="<?php echo esc_attr( $get( 'completed' ) ); ?>"></td>
		</tr>
		<tr>
			<th><label for="rp-video">Video link</label></th>
			<td><input type="url" class="regular-text" id="rp-video" name="rp[video_url]" value="<?php echo esc_attr( $get( 'video_url' ) ); ?>" placeholder="YouTube or Vimeo link"></td>
		</tr>
		<tr>
			<th>Featured project</th>
			<td><label><input type="checkbox" name="rp[featured]" value="1" <?php checked( $get( 'featured' ), '1' ); ?>> Show on the homepage</label></td>
		</tr>
		<tr>
			<th><label for="rp-notes">Staff notes</label></th>
			<td><textarea class="large-text" rows="3" id="rp-notes" name="rp[notes]"><?php echo esc_textarea( $get( 'notes' ) ); ?></textarea>
			<p class="description">Private. Never shown on the website.</p></td>
		</tr>
	</table>
	<?php
}

function rp_colour_box( $post ) {
	$terms    = get_terms( array( 'taxonomy' => 'colour', 'hide_empty' => false ) );
	$primary  = rp_primary_colour( $post->ID );
	$primary  = ( $primary && 'auto-draft' !== $post->post_status ) ? (int) $primary->term_id : 0;
	$assigned = wp_get_object_terms( $post->ID, 'colour', array( 'fields' => 'ids' ) );
	$assigned = is_wp_error( $assigned ) ? array() : array_map( 'intval', $assigned );

	echo '<p><label for="rp-primary-colour"><strong>Main colour</strong> (required)</label><br>';
	echo '<select name="rp_primary_colour" id="rp-primary-colour" style="width:100%"><option value="0">Choose a colour</option>';
	foreach ( $terms as $term ) {
		printf( '<option value="%d" %s>%s</option>', (int) $term->term_id, selected( $primary, (int) $term->term_id, false ), esc_html( $term->name ) );
	}
	echo '</select></p>';
	echo '<p><strong>Other colours</strong> (if two-tone)</p><div class="rp-checklist">';
	foreach ( $terms as $term ) {
		$on = in_array( (int) $term->term_id, $assigned, true ) && (int) $term->term_id !== $primary;
		printf( '<label><input type="checkbox" name="rp_other_colours[]" value="%d" %s> %s</label><br>', (int) $term->term_id, checked( $on, true, false ), esc_html( $term->name ) );
	}
	echo '</div>';
}

add_action(
	'admin_enqueue_scripts',
	function ( $hook ) {
		$screen = get_current_screen();
		$types  = array( 'projects', 'rp_collection', 'rp_video', 'rp_review', 'rp_resource' );
		if ( ! $screen || ! in_array( $screen->post_type, $types, true ) ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_style( 'rp-admin', RP_URL . 'assets/admin.css', array(), RP_VERSION );
		wp_enqueue_script( 'rp-admin', RP_URL . 'assets/admin.js', array( 'jquery', 'jquery-ui-sortable' ), RP_VERSION, true );
	}
);

/* ---------- Required fields: a project cannot be published without them ---------- */

add_filter(
	'wp_insert_post_data',
	function ( $data, $postarr ) {
		if ( 'projects' !== $data['post_type'] || 'publish' !== $data['post_status'] ) {
			return $data;
		}
		// Only the editing screen is checked; imports and bulk tools set their own data.
		if ( empty( $_POST['rp_project_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['rp_project_nonce'] ), 'rp_save_project' ) ) {
			return $data;
		}

		$missing = array();
		if ( '' === trim( $data['post_title'] ) ) {
			$missing[] = 'a project title';
		}
		if ( empty( $_POST['rp_primary_colour'] ) ) {
			$missing[] = 'a main colour';
		}
		$service = isset( $_POST['tax_input']['room'] ) ? array_filter( array_map( 'absint', (array) $_POST['tax_input']['room'] ) ) : array();
		if ( ! $service ) {
			$missing[] = 'a service';
		}
		$photos = isset( $_POST['rp_photos'] ) ? (array) $_POST['rp_photos'] : array();
		$has    = ! empty( $_POST['_thumbnail_id'] ) && (int) $_POST['_thumbnail_id'] > 0;
		foreach ( array( 'after', 'gallery' ) as $group ) {
			if ( ! empty( $photos[ $group ] ) ) {
				$has = true;
			}
		}
		if ( ! $has ) {
			$missing[] = 'at least one photo (main, after or more photos)';
		}

		if ( $missing ) {
			$data['post_status'] = 'draft';
			rp_add_notice( 'error', 'Saved as a draft, not published. Please add ' . implode( ', ', $missing ) . ', then press Publish again.' );
		}
		return $data;
	},
	10,
	2
);

function rp_add_notice( $type, $message ) {
	$key     = 'rp_notices_' . get_current_user_id();
	$notices = (array) get_transient( $key );
	$notices[] = array( $type, $message );
	set_transient( $key, array_filter( $notices ), 120 );
}

add_action(
	'admin_notices',
	function () {
		$key     = 'rp_notices_' . get_current_user_id();
		$notices = get_transient( $key );
		if ( ! $notices ) {
			return;
		}
		delete_transient( $key );
		foreach ( (array) $notices as $notice ) {
			if ( is_array( $notice ) && 2 === count( $notice ) ) {
				printf( '<div class="notice notice-%s is-dismissible"><p>%s</p></div>', esc_attr( $notice[0] ), esc_html( $notice[1] ) );
			}
		}
	}
);

/** WordPress shows "Post published" even when we stepped back to draft; drop that message. */
add_filter(
	'redirect_post_location',
	function ( $location, $post_id ) {
		if ( 'projects' === get_post_type( $post_id ) && 'draft' === get_post_status( $post_id ) && get_transient( 'rp_notices_' . get_current_user_id() ) ) {
			$location = add_query_arg( 'message', 10, $location );
		}
		return $location;
	},
	10,
	2
);

add_filter(
	'post_updated_messages',
	function ( $messages ) {
		$post = get_post();
		if ( ! $post ) {
			return $messages;
		}
		$view = sprintf( ' <a href="%s">View project</a>', esc_url( get_permalink( $post ) ) );
		$messages['projects'] = array(
			1  => 'Project updated.' . $view,
			4  => 'Project updated.',
			6  => 'Project published.' . $view,
			7  => 'Project saved.',
			8  => 'Project submitted for review.',
			9  => 'Project scheduled.',
			10 => 'Project saved as a draft.',
		) + ( isset( $messages['post'] ) ? $messages['post'] : array() );
		return $messages;
	}
);

/* ---------- Saving ---------- */

add_action(
	'save_post_projects',
	function ( $post_id, $post ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( empty( $_POST['rp_project_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['rp_project_nonce'] ), 'rp_save_project' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// Photos. A photo dragged between boxes must end up in one box only.
		$seen   = array();
		$photos = isset( $_POST['rp_photos'] ) ? (array) wp_unslash( $_POST['rp_photos'] ) : array();
		foreach ( array_keys( rp_photo_groups() ) as $group ) {
			$ids = isset( $photos[ $group ] ) ? array_filter( array_map( 'absint', explode( ',', (string) $photos[ $group ] ) ) ) : array();
			$ids = array_values( array_diff( array_unique( $ids ), $seen ) );
			$ids = array_values( array_filter( $ids, 'wp_attachment_is_image' ) );
			$seen = array_merge( $seen, $ids );
			update_post_meta( $post_id, '_rp_' . $group, $ids );
		}

		// Details.
		$in = isset( $_POST['rp'] ) ? (array) wp_unslash( $_POST['rp'] ) : array();
		foreach ( array( 'original_colour', 'new_colour', 'location' ) as $key ) {
			update_post_meta( $post_id, '_rp_' . $key, sanitize_text_field( isset( $in[ $key ] ) ? $in[ $key ] : '' ) );
		}
		$completed = isset( $in['completed'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $in['completed'] ) ? $in['completed'] : '';
		update_post_meta( $post_id, '_rp_completed', $completed );
		update_post_meta( $post_id, '_rp_video_url', esc_url_raw( isset( $in['video_url'] ) ? trim( $in['video_url'] ) : '' ) );
		update_post_meta( $post_id, '_rp_featured', empty( $in['featured'] ) ? '' : '1' );
		update_post_meta( $post_id, '_rp_notes', sanitize_textarea_field( isset( $in['notes'] ) ? $in['notes'] : '' ) );

		// Colours: main colour plus any others.
		$primary = isset( $_POST['rp_primary_colour'] ) ? absint( $_POST['rp_primary_colour'] ) : 0;
		$others  = isset( $_POST['rp_other_colours'] ) ? array_map( 'absint', (array) $_POST['rp_other_colours'] ) : array();
		$all     = array_values( array_unique( array_filter( array_merge( array( $primary ), $others ) ) ) );
		wp_set_object_terms( $post_id, $all, 'colour' );
		update_post_meta( $post_id, '_rp_primary_colour', $primary );

		rp_refresh_project( $post_id );
		rp_warn_duplicate_photos( $post_id );
	},
	10,
	2
);

/**
 * Derived data kept on every project so gallery queries stay simple and fast:
 * main photo, before/after flag, completion year and photo alt text.
 */
function rp_refresh_project( $post_id ) {
	$before  = rp_get_photos( $post_id, 'before' );
	$after   = rp_get_photos( $post_id, 'after' );
	$gallery = rp_get_photos( $post_id, 'gallery' );

	if ( ! has_post_thumbnail( $post_id ) ) {
		$first = $after ? $after[0] : ( $gallery ? $gallery[0] : 0 );
		if ( $first ) {
			set_post_thumbnail( $post_id, $first );
		}
	}

	update_post_meta( $post_id, '_rp_has_ba', ( $before && ( $after || has_post_thumbnail( $post_id ) ) ) ? '1' : '' );

	$completed = (string) get_post_meta( $post_id, '_rp_completed', true );
	update_post_meta( $post_id, '_rp_year', $completed ? substr( $completed, 0, 4 ) : '' );

	// Alt text helps Google understand the photos; fill it in when staff have not.
	$title  = get_the_title( $post_id );
	$labels = array( 'before' => ' before respray', 'after' => ' after respray', 'gallery' => '' );
	$main   = (int) get_post_thumbnail_id( $post_id );
	$sets   = array( 'before' => $before, 'after' => $after, 'gallery' => $gallery );
	if ( $main && ! in_array( $main, array_merge( $before, $after, $gallery ), true ) ) {
		$sets['gallery'][] = $main;
	}
	foreach ( $sets as $group => $ids ) {
		foreach ( $ids as $id ) {
			if ( '' === trim( (string) get_post_meta( $id, '_wp_attachment_image_alt', true ) ) || get_post_meta( $id, '_rp_auto_alt', true ) ) {
				update_post_meta( $id, '_wp_attachment_image_alt', wp_strip_all_tags( $title . $labels[ $group ] ) );
				update_post_meta( $id, '_rp_auto_alt', '1' );
			}
		}
	}

	delete_transient( 'rp_colour_tiles' );
	delete_transient( 'rp_colour_tiles_kitchen' );
	delete_transient( 'rp_years' );
}

add_action(
	'transition_post_status',
	function ( $new, $old, $post ) {
		if ( 'projects' === $post->post_type && $new !== $old ) {
			delete_transient( 'rp_colour_tiles' );
			delete_transient( 'rp_colour_tiles_kitchen' );
			delete_transient( 'rp_years' );
		}
	},
	10,
	3
);

/* ---------- Duplicate photo warning ---------- */

add_action(
	'add_attachment',
	function ( $attachment_id ) {
		$file = get_attached_file( $attachment_id );
		if ( $file && is_readable( $file ) ) {
			update_post_meta( $attachment_id, '_rp_md5', md5_file( $file ) );
		}
	}
);

function rp_warn_duplicate_photos( $post_id ) {
	$ids = array_merge( rp_get_photos( $post_id, 'before' ), rp_get_photos( $post_id, 'after' ), rp_get_photos( $post_id, 'gallery' ) );
	$dupes = 0;
	foreach ( $ids as $id ) {
		$hash = get_post_meta( $id, '_rp_md5', true );
		if ( ! $hash ) {
			continue;
		}
		$same = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'post__not_in'   => array( $id ),
				'meta_key'       => '_rp_md5',
				'meta_value'     => $hash,
			)
		);
		if ( $same ) {
			$dupes++;
		}
	}
	if ( $dupes ) {
		rp_add_notice( 'warning', sprintf( '%d photo(s) in this project are identical to photos already in the media library. Please check the same picture has not been uploaded twice.', $dupes ) );
	}
}

/* ---------- Project list ---------- */

add_filter(
	'manage_projects_posts_columns',
	function ( $columns ) {
		$new = array();
		foreach ( $columns as $key => $label ) {
			if ( 'title' === $key ) {
				$new['rp_photo'] = 'Photo';
			}
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['rp_colour']   = 'Colour';
				$new['rp_service']  = 'Service';
				$new['rp_finish']   = 'Finish';
				$new['rp_photos']   = 'Photos';
				$new['rp_featured'] = 'Featured';
			}
		}
		return $new;
	}
);

add_action(
	'manage_projects_posts_custom_column',
	function ( $column, $post_id ) {
		switch ( $column ) {
			case 'rp_photo':
				echo get_the_post_thumbnail( $post_id, array( 60, 60 ) );
				break;
			case 'rp_colour':
				$term = rp_primary_colour( $post_id );
				echo $term ? esc_html( $term->name ) : '<span style="color:#b32d2e">Missing</span>';
				break;
			case 'rp_service':
				$term = rp_first_term( $post_id, 'room' );
				echo $term ? esc_html( $term->name ) : '<span style="color:#b32d2e">Missing</span>';
				break;
			case 'rp_finish':
				$term = rp_first_term( $post_id, 'finish' );
				echo $term ? esc_html( $term->name ) : '';
				break;
			case 'rp_photos':
				$b = count( rp_get_photos( $post_id, 'before' ) );
				$a = count( rp_get_photos( $post_id, 'after' ) );
				$g = count( rp_get_photos( $post_id, 'gallery' ) );
				echo esc_html( "{$b} before, {$a} after, {$g} more" );
				break;
			case 'rp_featured':
				echo get_post_meta( $post_id, '_rp_featured', true ) ? '&#9733;' : '';
				break;
		}
	},
	10,
	2
);

add_action(
	'restrict_manage_posts',
	function ( $post_type ) {
		if ( 'projects' !== $post_type ) {
			return;
		}
		foreach ( array( 'colour' => 'All colours', 'room' => 'All services', 'finish' => 'All finishes' ) as $taxonomy => $label ) {
			$current = isset( $_GET[ 'rp_f_' . $taxonomy ] ) ? sanitize_key( $_GET[ 'rp_f_' . $taxonomy ] ) : '';
			echo '<select name="rp_f_' . esc_attr( $taxonomy ) . '"><option value="">' . esc_html( $label ) . '</option>';
			foreach ( get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) ) as $term ) {
				printf( '<option value="%s" %s>%s</option>', esc_attr( $term->slug ), selected( $current, $term->slug, false ), esc_html( $term->name ) );
			}
			echo '</select>';
		}
	}
);

add_action(
	'pre_get_posts',
	function ( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() || 'projects' !== $query->get( 'post_type' ) ) {
			return;
		}
		$tax_query = array();
		foreach ( array( 'colour', 'room', 'finish' ) as $taxonomy ) {
			if ( ! empty( $_GET[ 'rp_f_' . $taxonomy ] ) ) {
				$tax_query[] = array(
					'taxonomy' => $taxonomy,
					'field'    => 'slug',
					'terms'    => sanitize_key( $_GET[ 'rp_f_' . $taxonomy ] ),
				);
			}
		}
		if ( $tax_query ) {
			$query->set( 'tax_query', $tax_query );
		}
	}
);
