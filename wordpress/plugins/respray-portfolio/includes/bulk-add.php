<?php
/**
 * Projects > Bulk add: choose many photos at once and create one draft
 * project per photo, already tagged. Staff then open each draft to finish it.
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'admin_menu',
	function () {
		add_submenu_page(
			'edit.php?post_type=projects',
			'Bulk add projects',
			'Bulk add',
			'edit_rp_projects',
			'rp-bulk-add',
			'rp_bulk_add_page'
		);
	}
);

function rp_bulk_add_page() {
	$created = 0;
	if ( isset( $_POST['rp_bulk_nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['rp_bulk_nonce'] ), 'rp_bulk_add' ) && current_user_can( 'edit_rp_projects' ) ) {
		$created = rp_bulk_add_handle();
	}
	$taxonomies = array( 'colour' => 'Main colour', 'room' => 'Service', 'style' => 'Style', 'finish' => 'Finish' );
	?>
	<div class="wrap">
		<h1>Bulk add projects</h1>
		<?php if ( $created ) : ?>
			<div class="notice notice-success"><p><?php echo esc_html( $created ); ?> draft project(s) created. <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=projects&post_status=draft' ) ); ?>">Open the drafts</a> to check and publish them.</p></div>
		<?php endif; ?>
		<p>Use this when you have many finished jobs with one photo each. Every photo becomes its own draft project with the choices below. Nothing goes on the website until you publish each draft.</p>
		<form method="post">
			<?php wp_nonce_field( 'rp_bulk_add', 'rp_bulk_nonce' ); ?>
			<div class="rp-photos" data-rp-photos data-title="Photos" style="max-width:900px">
				<h4>Photos</h4>
				<input type="hidden" name="rp_bulk_photos" value="">
				<ul class="rp-photo-list"></ul>
				<button type="button" class="button" data-rp-add>Upload or choose photos</button>
			</div>
			<table class="form-table" role="presentation" style="max-width:900px">
				<tr>
					<th><label for="rp-bulk-title">Title for each project</label></th>
					<td><input type="text" class="regular-text" id="rp-bulk-title" name="rp_bulk_title" placeholder="e.g. Black Shaker Kitchen Respray">
					<p class="description">A number is added automatically if you leave the titles the same. You can rename each one later.</p></td>
				</tr>
				<?php foreach ( $taxonomies as $taxonomy => $label ) : ?>
					<tr>
						<th><label for="rp-bulk-<?php echo esc_attr( $taxonomy ); ?>"><?php echo esc_html( $label ); ?></label></th>
						<td><select id="rp-bulk-<?php echo esc_attr( $taxonomy ); ?>" name="rp_bulk_terms[<?php echo esc_attr( $taxonomy ); ?>]">
							<option value="0">Choose</option>
							<?php foreach ( get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) ) as $term ) : ?>
								<option value="<?php echo (int) $term->term_id; ?>"><?php echo esc_html( $term->name ); ?></option>
							<?php endforeach; ?>
						</select></td>
					</tr>
				<?php endforeach; ?>
			</table>
			<?php submit_button( 'Create draft projects' ); ?>
		</form>
	</div>
	<?php
}

function rp_bulk_add_handle() {
	$ids   = array_filter( array_map( 'absint', explode( ',', (string) wp_unslash( isset( $_POST['rp_bulk_photos'] ) ? $_POST['rp_bulk_photos'] : '' ) ) ) );
	$ids   = array_slice( array_values( array_filter( array_unique( $ids ), 'wp_attachment_is_image' ) ), 0, 200 );
	$title = sanitize_text_field( wp_unslash( isset( $_POST['rp_bulk_title'] ) ? $_POST['rp_bulk_title'] : '' ) );
	$terms = isset( $_POST['rp_bulk_terms'] ) ? array_map( 'absint', (array) $_POST['rp_bulk_terms'] ) : array();
	$title = '' !== $title ? $title : 'New project';

	$created = 0;
	foreach ( $ids as $n => $attachment_id ) {
		$post_id = wp_insert_post(
			array(
				'post_type'   => 'projects',
				'post_status' => 'draft',
				'post_title'  => count( $ids ) > 1 ? $title . ' ' . ( $n + 1 ) : $title,
			)
		);
		if ( ! $post_id || is_wp_error( $post_id ) ) {
			continue;
		}
		foreach ( array( 'colour', 'room', 'style', 'finish' ) as $taxonomy ) {
			if ( ! empty( $terms[ $taxonomy ] ) ) {
				wp_set_object_terms( $post_id, array( $terms[ $taxonomy ] ), $taxonomy );
			}
		}
		if ( ! empty( $terms['colour'] ) ) {
			update_post_meta( $post_id, '_rp_primary_colour', $terms['colour'] );
		}
		update_post_meta( $post_id, '_rp_after', array( $attachment_id ) );
		set_post_thumbnail( $post_id, $attachment_id );
		rp_refresh_project( $post_id );
		$created++;
	}
	return $created;
}
