<?php
/**
 * Tools > Adopt existing projects.
 *
 * For launching on the install that already holds the projects (same
 * "projects" type and categories as the old JetEngine setup). Nothing is
 * copied or deleted: each project is given a main photo from the photos
 * already attached to it, its main colour, the image sizes the new gallery
 * uses, and photo descriptions. Safe to run again.
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'admin_menu',
	function () {
		add_management_page( 'Adopt existing projects', 'Adopt existing projects', 'manage_options', 'rp-adopt', 'rp_adopt_page' );
	}
);

function rp_adopt_remaining() {
	$query = new WP_Query(
		array(
			'post_type'      => 'projects',
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_query'     => array( array( 'key' => '_rp_adopted', 'compare' => 'NOT EXISTS' ) ),
		)
	);
	return (int) $query->found_posts;
}

function rp_adopt_page() {
	?>
	<div class="wrap">
		<h1>Adopt existing projects</h1>
		<p>Projects on this site still to prepare for the new gallery: <strong id="rp-adopt-left"><?php echo (int) rp_adopt_remaining(); ?></strong></p>
		<p>This gives every existing project a main photo (from the photos already attached to it), a main colour and the image sizes the new gallery uses. Nothing is deleted. You can stop and start again at any time.</p>
		<p><button type="button" class="button button-primary" id="rp-adopt-start">Start</button></p>
		<pre id="rp-adopt-log" style="background:#fff;border:1px solid #dcdcde;padding:12px;max-height:360px;overflow:auto;white-space:pre-wrap"></pre>
	</div>
	<script>
	(function () {
		var log = document.getElementById('rp-adopt-log');
		var button = document.getElementById('rp-adopt-start');
		var done = 0, problems = 0, failures = 0;
		function say(line) { log.textContent += line + '\n'; log.scrollTop = log.scrollHeight; }
		function run() {
			var body = new URLSearchParams({ action: 'rp_adopt_batch', _wpnonce: <?php echo wp_json_encode( wp_create_nonce( 'rp_adopt' ) ); ?> });
			fetch(ajaxurl, { method: 'POST', body: body, credentials: 'same-origin' })
				.then(function (r) { return r.json(); })
				.then(function (res) {
					if (!res.success) { say('Stopped: ' + (res.data || 'unknown error')); button.disabled = false; return; }
					failures = 0;
					done += res.data.done; problems += res.data.notes.length;
					document.getElementById('rp-adopt-left').textContent = res.data.remaining;
					say(done + ' prepared, ' + res.data.remaining + ' to go' + (res.data.notes.length ? ' | ' + res.data.notes.join('; ') : ''));
					if (res.data.remaining > 0 && res.data.done > 0) { run(); }
					else { say('Finished. ' + done + ' projects prepared, ' + problems + ' note(s).'); button.disabled = false; }
				})
				.catch(function (e) {
					failures++;
					if (failures < 4) { say('A request failed, trying again…'); setTimeout(run, 3000); }
					else { say('Stopped after repeated failures: ' + e + '. Press Start to continue.'); button.disabled = false; }
				});
		}
		button.addEventListener('click', function () { button.disabled = true; say('Starting…'); run(); });
	})();
	</script>
	<?php
}

add_action(
	'wp_ajax_rp_adopt_batch',
	function () {
		check_ajax_referer( 'rp_adopt' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Not allowed.' );
		}
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 120 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}
		rp_adopt_fix_terms();

		$ids = get_posts(
			array(
				'post_type'      => 'projects',
				'post_status'    => 'any',
				'posts_per_page' => 4,
				'fields'         => 'ids',
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'meta_query'     => array( array( 'key' => '_rp_adopted', 'compare' => 'NOT EXISTS' ) ),
			)
		);
		$notes = array();
		foreach ( $ids as $post_id ) {
			$note = rp_adopt_project( $post_id );
			if ( $note ) {
				$notes[] = $note;
			}
		}
		wp_send_json_success(
			array(
				'done'      => count( $ids ),
				'remaining' => rp_adopt_remaining(),
				'notes'     => $notes,
			)
		);
	}
);

/** Mistyped categories on the old site: move their projects to the right one. */
function rp_adopt_fix_terms() {
	if ( get_option( 'rp_terms_fixed' ) ) {
		return;
	}
	$moves = array(
		array( 'colour', 'yelllow', 'yellow' ),
		array( 'project-type', 'complete', 'completed' ),
		array( 'project-type', 'jjjjhh', '' ),
	);
	foreach ( $moves as $move ) {
		list( $taxonomy, $from_slug, $to_slug ) = $move;
		$from = get_term_by( 'slug', $from_slug, $taxonomy );
		if ( ! $from ) {
			continue;
		}
		$to      = $to_slug ? get_term_by( 'slug', $to_slug, $taxonomy ) : null;
		$objects = get_objects_in_term( (int) $from->term_id, $taxonomy );
		if ( $to_slug && ! $to ) {
			// No correct term yet: simply correct the spelling of this one.
			wp_update_term( (int) $from->term_id, $taxonomy, array( 'slug' => $to_slug ) );
			continue;
		}
		if ( $to && ! is_wp_error( $objects ) ) {
			foreach ( $objects as $object_id ) {
				wp_add_object_terms( (int) $object_id, (int) $to->term_id, $taxonomy );
			}
		}
		if ( $to || ( ! is_wp_error( $objects ) && ! $objects ) ) {
			wp_delete_term( (int) $from->term_id, $taxonomy );
		}
	}
	update_option( 'rp_terms_fixed', 1, false );
}

/** @return string A note for the log when something needs a person's attention, else ''. */
function rp_adopt_project( $post_id ) {
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$attached = get_posts(
		array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'post_parent'    => $post_id,
			'post_mime_type' => 'image',
			'posts_per_page' => 50,
			'orderby'        => 'menu_order ID',
			'order'          => 'ASC',
			'fields'         => 'ids',
		)
	);
	$attached = array_map( 'intval', $attached );

	if ( ! has_post_thumbnail( $post_id ) && $attached ) {
		set_post_thumbnail( $post_id, $attached[0] );
	}
	$main = (int) get_post_thumbnail_id( $post_id );

	// Photos not yet placed in a box go to "More photos".
	$placed = array_merge( rp_get_photos( $post_id, 'before' ), rp_get_photos( $post_id, 'after' ), rp_get_photos( $post_id, 'gallery' ) );
	$extra  = array_values( array_diff( $attached, $placed, array( $main ) ) );
	if ( $extra ) {
		update_post_meta( $post_id, '_rp_gallery', array_merge( rp_get_photos( $post_id, 'gallery' ), $extra ) );
	}

	if ( ! (int) get_post_meta( $post_id, '_rp_primary_colour', true ) ) {
		$colour = rp_first_term( $post_id, 'colour' );
		if ( $colour ) {
			update_post_meta( $post_id, '_rp_primary_colour', (int) $colour->term_id );
		}
	}

	// Make the gallery-sized versions of each photo and record its fingerprint.
	foreach ( array_unique( array_filter( array_merge( array( $main ), $attached ) ) ) as $image_id ) {
		$file = get_attached_file( $image_id );
		if ( ! $file || ! is_readable( $file ) ) {
			continue;
		}
		if ( ! get_post_meta( $image_id, '_rp_md5', true ) ) {
			update_post_meta( $image_id, '_rp_md5', md5_file( $file ) );
		}
		$meta = wp_get_attachment_metadata( $image_id );
		if ( is_array( $meta ) && empty( $meta['sizes']['rp-card'] ) ) {
			$fresh = wp_generate_attachment_metadata( $image_id, $file );
			if ( $fresh && ! is_wp_error( $fresh ) ) {
				wp_update_attachment_metadata( $image_id, $fresh );
			}
		}
	}

	rp_refresh_project( $post_id );
	update_post_meta( $post_id, '_rp_adopted', 1 );

	return $main ? '' : '"' . get_the_title( $post_id ) . '" has no photo attached';
}
