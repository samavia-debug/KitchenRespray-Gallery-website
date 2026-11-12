<?php
/**
 * Main project gallery: /gallery/ and every filtered view of it.
 */

defined( 'ABSPATH' ) || exit;

get_header();

if ( ! rpt_ready() ) {
	rpt_plugin_missing();
	get_footer();
	return;
}

$filters = rp_request_filters();
$summary = rp_gallery_heading( $filters );
?>
<main id="main" class="rpt-wrap rpt-page">
	<?php rpt_breadcrumbs( array( array( 'Gallery' ) ) ); ?>
	<header class="rpt-page-head">
		<h1 data-rp-heading><?php echo esc_html( $summary ); ?></h1>
		<p>Search or filter our completed work by colour, style, finish and service. Copy the link to share exactly what you see.</p>
	</header>

	<?php echo rp_filter_form( $filters ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
	<?php echo rp_render_results( $filters ); // phpcs:ignore WordPress.Security.EscapeOutput ?>

	<?php $collections = rp_collection_index(); ?>
	<?php if ( $collections ) : ?>
		<nav class="rpt-collections" aria-label="Popular collections">
			<h2>Popular collections</h2>
			<ul class="rp-pill-list">
				<?php foreach ( $collections as $collection ) : ?>
					<li><a href="<?php echo esc_url( $collection['url'] ); ?>"><?php echo esc_html( $collection['title'] ); ?></a></li>
				<?php endforeach; ?>
			</ul>
		</nav>
	<?php endif; ?>
</main>
<?php
get_footer();
