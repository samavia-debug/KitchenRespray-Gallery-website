<?php
/**
 * Tools > Import old site.
 *
 * Copies projects, their categories and their photos from the old
 * resprayphotos.com through its public data feed. Only content is copied:
 * no plugins, themes, settings or database tables from the old install.
 * Safe to run again; projects already imported are skipped.
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'admin_menu',
	function () {
		add_management_page( 'Import old Respray Photos site', 'Import old site', 'manage_options', 'rp-import', 'rp_import_page' );
	}
);

function rp_import_page() {
	$count = wp_count_posts( 'projects' );
	?>
	<div class="wrap">
		<h1>Import projects from the old site</h1>
		<p>Projects on this site now: <strong><?php echo (int) $count->publish; ?></strong> published, <strong><?php echo (int) $count->draft; ?></strong> drafts.</p>
		<table class="form-table" role="presentation">
			<tr>
				<th><label for="rp-import-source">Old site address</label></th>
				<td><input type="url" class="regular-text" id="rp-import-source" value="https://resprayphotos.com"></td>
			</tr>
			<tr>
				<th><label for="rp-import-limit">How many projects</label></th>
				<td><input type="number" id="rp-import-limit" value="0" min="0" step="1" class="small-text"> <span class="description">0 = all of them</span></td>
			</tr>
			<tr>
				<th>Import as</th>
				<td><label><input type="radio" name="rp-import-status" value="publish" checked> Published (same as on the old site)</label><br>
				<label><input type="radio" name="rp-import-status" value="draft"> Drafts (review each before it goes live)</label></td>
			</tr>
		</table>
		<p><button type="button" class="button button-primary" id="rp-import-start">Start import</button></p>
		<pre id="rp-import-log" style="background:#fff;border:1px solid #dcdcde;padding:12px;max-height:360px;overflow:auto;white-space:pre-wrap"></pre>
	</div>
	<script>
	(function () {
		var log = document.getElementById('rp-import-log');
		var button = document.getElementById('rp-import-start');
		var totals = { imported: 0, skipped: 0, failed: 0 };
		function say(line) { log.textContent += line + '\n'; log.scrollTop = log.scrollHeight; }
		function run(page) {
			var limit = parseInt(document.getElementById('rp-import-limit').value, 10) || 0;
			var body = new URLSearchParams({
				action: 'rp_import_batch',
				_wpnonce: <?php echo wp_json_encode( wp_create_nonce( 'rp_import' ) ); ?>,
				source: document.getElementById('rp-import-source').value,
				status: document.querySelector('input[name="rp-import-status"]:checked').value,
				page: page
			});
			fetch(ajaxurl, { method: 'POST', body: body, credentials: 'same-origin' })
				.then(function (r) { return r.json(); })
				.then(function (res) {
					if (!res.success) { say('Stopped: ' + (res.data || 'unknown error')); button.disabled = false; return; }
					var d = res.data;
					totals.imported += d.imported; totals.skipped += d.skipped; totals.failed += d.errors.length;
					say('Batch ' + page + ' of ' + d.pages + ': ' + d.imported + ' imported, ' + d.skipped + ' already here' + (d.errors.length ? ', problems: ' + d.errors.join('; ') : ''));
					var reached = limit && (totals.imported + totals.skipped) >= limit;
					if (page < d.pages && !reached) { run(page + 1); }
					else { say('Finished. Imported ' + totals.imported + ', skipped ' + totals.skipped + ', problems ' + totals.failed + '.'); button.disabled = false; }
				})
				.catch(function (e) { say('Stopped: ' + e); button.disabled = false; });
		}
		button.addEventListener('click', function () {
			button.disabled = true; totals = { imported: 0, skipped: 0, failed: 0 };
			say('Starting…'); run(1);
		});
	})();
	</script>
	<?php
}

add_action(
	'wp_ajax_rp_import_batch',
	function () {
		check_ajax_referer( 'rp_import' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Not allowed.' );
		}
		$source = untrailingslashit( esc_url_raw( wp_unslash( isset( $_POST['source'] ) ? $_POST['source'] : '' ) ) );
		if ( ! $source || 0 !== strpos( $source, 'https://' ) ) {
			wp_send_json_error( 'The old site address must start with https://' );
		}
		$status = ( isset( $_POST['status'] ) && 'draft' === $_POST['status'] ) ? 'draft' : 'publish';
		$page   = max( 1, absint( isset( $_POST['page'] ) ? $_POST['page'] : 1 ) );

		$response = wp_safe_remote_get(
			add_query_arg(
				array( 'per_page' => 4, 'page' => $page, 'orderby' => 'id', 'order' => 'asc', '_embed' => 'wp:term' ),
				$source . '/wp-json/wp/v2/projects'
			),
			array( 'timeout' => 30 )
		);
		if ( is_wp_error( $response ) ) {
			wp_send_json_error( $response->get_error_message() );
		}
		$items = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( 200 !== wp_remote_retrieve_response_code( $response ) || ! is_array( $items ) ) {
			wp_send_json_error( 'The old site did not return a project list.' );
		}

		$out = array(
			'pages'    => max( 1, (int) wp_remote_retrieve_header( $response, 'x-wp-totalpages' ) ),
			'imported' => 0,
			'skipped'  => 0,
			'errors'   => array(),
		);
		foreach ( $items as $item ) {
			$result = rp_import_project( $item, $source, $status );
			if ( is_wp_error( $result ) ) {
				$out['errors'][] = $result->get_error_message();
			} elseif ( 'skipped' === $result ) {
				$out['skipped']++;
			} else {
				$out['imported']++;
			}
		}
		wp_send_json_success( $out );
	}
);

/** Tidy-ups for mistakes in the old site's categories. */
function rp_import_fix_term( $taxonomy, $slug ) {
	$fixes = array(
		'colour:yelllow'        => 'yellow',
		'project-type:complete' => 'completed',
		'project-type:jjjjhh'   => '',
	);
	$key = $taxonomy . ':' . $slug;
	return isset( $fixes[ $key ] ) ? $fixes[ $key ] : $slug;
}

function rp_import_project( $item, $source, $status ) {
	if ( empty( $item['id'] ) || empty( $item['slug'] ) ) {
		return new WP_Error( 'rp_import', 'A project had no ID.' );
	}
	$existing = get_posts(
		array(
			'post_type'      => 'projects',
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'meta_key'       => '_rp_source_id',
			'meta_value'     => (int) $item['id'],
		)
	);
	if ( $existing ) {
		return 'skipped';
	}

	$title   = html_entity_decode( wp_strip_all_tags( isset( $item['title']['rendered'] ) ? $item['title']['rendered'] : '' ), ENT_QUOTES, 'UTF-8' );
	$post_id = wp_insert_post(
		array(
			'post_type'    => 'projects',
			'post_status'  => $status,
			'post_title'   => $title,
			'post_name'    => sanitize_title( $item['slug'] ),
			'post_date'    => isset( $item['date'] ) ? str_replace( 'T', ' ', $item['date'] ) : '',
			'post_content' => wp_kses_post( isset( $item['content']['rendered'] ) ? $item['content']['rendered'] : '' ),
		),
		true
	);
	if ( is_wp_error( $post_id ) ) {
		return $post_id;
	}
	update_post_meta( $post_id, '_rp_source_id', (int) $item['id'] );
	update_post_meta( $post_id, '_rp_source_url', esc_url_raw( isset( $item['link'] ) ? $item['link'] : '' ) );

	// Categories.
	$by_taxonomy = array();
	$groups      = isset( $item['_embedded']['wp:term'] ) ? (array) $item['_embedded']['wp:term'] : array();
	foreach ( $groups as $group ) {
		foreach ( (array) $group as $remote_term ) {
			if ( empty( $remote_term['taxonomy'] ) || empty( $remote_term['slug'] ) || ! taxonomy_exists( $remote_term['taxonomy'] ) ) {
				continue;
			}
			$taxonomy = $remote_term['taxonomy'];
			$slug     = rp_import_fix_term( $taxonomy, sanitize_title( $remote_term['slug'] ) );
			if ( '' === $slug ) {
				continue;
			}
			$term = get_term_by( 'slug', $slug, $taxonomy );
			if ( ! $term ) {
				$name    = html_entity_decode( isset( $remote_term['name'] ) ? $remote_term['name'] : $slug, ENT_QUOTES, 'UTF-8' );
				$created = wp_insert_term( $name, $taxonomy, array( 'slug' => $slug ) );
				$term    = is_wp_error( $created ) ? null : get_term( $created['term_id'], $taxonomy );
			}
			if ( $term ) {
				$by_taxonomy[ $taxonomy ][] = (int) $term->term_id;
			}
		}
	}
	foreach ( $by_taxonomy as $taxonomy => $term_ids ) {
		wp_set_object_terms( $post_id, array_unique( $term_ids ), $taxonomy );
	}
	if ( ! empty( $by_taxonomy['colour'] ) ) {
		update_post_meta( $post_id, '_rp_primary_colour', $by_taxonomy['colour'][0] );
	}

	// Photos: on the old site each photo is attached to its project.
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$media = wp_safe_remote_get(
		add_query_arg(
			array( 'parent' => (int) $item['id'], 'per_page' => 50, '_fields' => 'id,source_url,mime_type' ),
			$source . '/wp-json/wp/v2/media'
		),
		array( 'timeout' => 30 )
	);
	$files   = is_wp_error( $media ) ? array() : json_decode( wp_remote_retrieve_body( $media ), true );
	$gallery = array();
	foreach ( is_array( $files ) ? $files : array() as $file ) {
		if ( empty( $file['source_url'] ) || 0 !== strpos( (string) $file['mime_type'], 'image/' ) ) {
			continue;
		}
		$attachment_id = media_sideload_image( esc_url_raw( $file['source_url'] ), $post_id, $title, 'id' );
		if ( is_wp_error( $attachment_id ) ) {
			return new WP_Error( 'rp_import', $title . ': photo could not be copied (' . $attachment_id->get_error_message() . ')' );
		}
		if ( ! has_post_thumbnail( $post_id ) ) {
			set_post_thumbnail( $post_id, $attachment_id );
		} else {
			$gallery[] = (int) $attachment_id;
		}
	}
	update_post_meta( $post_id, '_rp_gallery', $gallery );
	rp_refresh_project( $post_id );

	if ( ! has_post_thumbnail( $post_id ) ) {
		return new WP_Error( 'rp_import', $title . ': imported without a photo (none attached on the old site)' );
	}
	return 'imported';
}
