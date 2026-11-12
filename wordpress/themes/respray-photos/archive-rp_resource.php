<?php
/**
 * Brochures and downloads: /resources/  (optional ?topic=<category>)
 * No sign-up is needed to download anything.
 */

defined( 'ABSPATH' ) || exit;

get_header();

if ( ! rpt_ready() ) {
	rpt_plugin_missing();
	get_footer();
	return;
}

$base  = get_post_type_archive_link( 'rp_resource' );
$topic = isset( $_GET['topic'] ) ? sanitize_title( wp_unslash( $_GET['topic'] ) ) : '';
$args  = array( 'post_type' => 'rp_resource', 'post_status' => 'publish', 'posts_per_page' => 60, 'orderby' => 'menu_order date' );
if ( $topic ) {
	$args['tax_query'] = array( array( 'taxonomy' => 'rp_resource_cat', 'field' => 'slug', 'terms' => $topic ) );
}
$query  = new WP_Query( $args );
$topics = get_terms( array( 'taxonomy' => 'rp_resource_cat', 'hide_empty' => true ) );
?>
<main id="main" class="rpt-wrap rpt-page">
	<?php rpt_breadcrumbs( array( array( 'Brochures' ) ) ); ?>
	<header class="rpt-page-head">
		<h1>Brochures and Resources</h1>
		<p>Colour guides, finish guides and brochures to download and keep.</p>
	</header>

	<?php if ( ! is_wp_error( $topics ) && $topics ) : ?>
		<ul class="rp-pill-list">
			<li><a href="<?php echo esc_url( $base ); ?>" <?php echo $topic ? '' : 'aria-current="true"'; ?>>All</a></li>
			<?php foreach ( $topics as $term ) : ?>
				<li><a href="<?php echo esc_url( add_query_arg( 'topic', $term->slug, $base ) ); ?>" <?php echo $topic === $term->slug ? 'aria-current="true"' : ''; ?>><?php echo esc_html( $term->name ); ?></a></li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>

	<?php if ( $query->have_posts() ) : ?>
		<div class="rpt-resources">
			<?php
			foreach ( $query->posts as $resource ) {
				echo rp_resource_card( $resource->ID ); // phpcs:ignore WordPress.Security.EscapeOutput
			}
			?>
		</div>
	<?php else : ?>
		<div class="rp-empty">
			<h2>Brochures will appear here soon</h2>
			<a class="rp-btn rp-btn-dark" href="<?php echo esc_url( rp_gallery_base_url() ); ?>">Explore Our Gallery</a>
		</div>
	<?php endif; ?>
</main>
<?php
get_footer();
