<?php
/**
 * A shareable landing page such as /gallery/black-kitchens/.
 */

defined( 'ABSPATH' ) || exit;

get_header();

if ( ! rpt_ready() ) {
	rpt_plugin_missing();
	get_footer();
	return;
}

the_post();

$request        = rp_request_filters();
$filters        = rp_empty_filters();
$filters['tax'] = rp_collection_filters( get_the_ID() );
$filters['pg']  = $request['pg'];
$intro          = get_the_content();
?>
<main id="main" class="rpt-wrap rpt-page">
	<?php rpt_breadcrumbs( array( array( 'Gallery', rp_gallery_base_url() ), array( get_the_title() ) ) ); ?>
	<header class="rpt-page-head">
		<h1><?php the_title(); ?></h1>
		<?php if ( $intro ) : ?>
			<div class="rpt-intro"><?php echo wp_kses_post( wpautop( $intro ) ); ?></div>
		<?php endif; ?>
	</header>

	<?php echo rp_render_results( $filters, get_permalink() ); // phpcs:ignore WordPress.Security.EscapeOutput ?>

	<p class="rpt-refine"><a class="rp-link" href="<?php echo esc_url( rp_gallery_url( $filters ) ); ?>">Refine these results with more filters</a></p>

	<nav class="rpt-collections" aria-label="More collections">
		<h2>More collections</h2>
		<ul class="rp-pill-list">
			<?php foreach ( rp_collection_index() as $collection ) : ?>
				<?php if ( (int) $collection['id'] !== get_the_ID() ) : ?>
					<li><a href="<?php echo esc_url( $collection['url'] ); ?>"><?php echo esc_html( $collection['title'] ); ?></a></li>
				<?php endif; ?>
			<?php endforeach; ?>
		</ul>
	</nav>
</main>
<?php
get_footer();
