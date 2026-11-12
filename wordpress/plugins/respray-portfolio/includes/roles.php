<?php
/**
 * "Portfolio Editor" role for day-to-day staff (Joanna, Sajjad).
 * They can add, edit and publish portfolio content and upload media, and
 * cannot reach plugins, themes, users or site settings.
 */

defined( 'ABSPATH' ) || exit;

function rp_content_caps() {
	return array(
		'edit_rp_project',
		'read_rp_project',
		'delete_rp_project',
		'edit_rp_projects',
		'edit_others_rp_projects',
		'publish_rp_projects',
		'read_private_rp_projects',
		'delete_rp_projects',
		'delete_private_rp_projects',
		'delete_published_rp_projects',
		'delete_others_rp_projects',
		'edit_private_rp_projects',
		'edit_published_rp_projects',
	);
}

function rp_add_roles() {
	$caps = array(
		'read'         => true,
		'upload_files' => true,
	);
	foreach ( rp_content_caps() as $cap ) {
		$caps[ $cap ] = true;
	}

	remove_role( 'rp_portfolio_editor' );
	add_role( 'rp_portfolio_editor', 'Portfolio Editor', $caps );

	foreach ( array( 'administrator', 'editor' ) as $role_name ) {
		$role = get_role( $role_name );
		if ( $role ) {
			foreach ( rp_content_caps() as $cap ) {
				$role->add_cap( $cap );
			}
		}
	}
}

/** Re-apply capabilities after a plugin update without needing re-activation. */
add_action(
	'admin_init',
	function () {
		if ( get_option( 'rp_version' ) !== RP_VERSION ) {
			rp_add_roles();
			rp_seed_terms();
			flush_rewrite_rules();
			update_option( 'rp_version', RP_VERSION );
		}
	}
);

/** Portfolio editors land on the project list and see only what they need. */
add_action(
	'admin_menu',
	function () {
		$user = wp_get_current_user();
		if ( in_array( 'rp_portfolio_editor', (array) $user->roles, true ) ) {
			remove_menu_page( 'index.php' );
			remove_menu_page( 'tools.php' );
			remove_menu_page( 'edit-comments.php' );
		}
	},
	999
);

add_filter(
	'login_redirect',
	function ( $to, $requested, $user ) {
		if ( $user instanceof WP_User && in_array( 'rp_portfolio_editor', (array) $user->roles, true ) ) {
			return admin_url( 'edit.php?post_type=projects' );
		}
		return $to;
	},
	10,
	3
);
