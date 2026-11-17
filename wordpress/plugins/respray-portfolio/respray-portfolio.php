<?php
/**
 * Plugin Name: Respray Portfolio
 * Description: Project database, smart shareable galleries, videos, reviews and brochures for resprayphotos.com.
 * Version: 0.2.2
 * Requires PHP: 7.4
 * Author: Respray Photos
 */

defined( 'ABSPATH' ) || exit;

define( 'RP_VERSION', '0.2.2' );
define( 'RP_DIR', plugin_dir_path( __FILE__ ) );
define( 'RP_URL', plugin_dir_url( __FILE__ ) );

require_once RP_DIR . 'includes/content-types.php';
require_once RP_DIR . 'includes/roles.php';
require_once RP_DIR . 'includes/settings.php';
require_once RP_DIR . 'includes/project-fields.php';
require_once RP_DIR . 'includes/extras.php';
require_once RP_DIR . 'includes/gallery.php';
require_once RP_DIR . 'includes/render.php';
require_once RP_DIR . 'includes/seo.php';
require_once RP_DIR . 'includes/titles.php';
require_once RP_DIR . 'includes/redirects.php';
require_once RP_DIR . 'includes/bulk-add.php';
require_once RP_DIR . 'includes/importer.php';
require_once RP_DIR . 'includes/adopt.php';
require_once RP_DIR . 'includes/ai.php';

register_activation_hook( __FILE__, 'rp_activate' );

function rp_activate() {
	rp_register_content_types();
	rp_add_roles();
	rp_seed_terms();
	rp_seed_collections();
	rp_seed_pages();
	flush_rewrite_rules();
	update_option( 'rp_version', RP_VERSION );
}

register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );
