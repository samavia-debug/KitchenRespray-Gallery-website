<?php
/**
 * Customer reviews: /reviews/
 * Only reviews entered by staff are shown. No ratings are calculated or invented.
 */

defined( 'ABSPATH' ) || exit;

get_header();

if ( ! rpt_ready() ) {
	rpt_plugin_missing();
	get_footer();
	return;
}

$pg    = max( 1, absint( isset( $_GET['pg'] ) ? $_GET['pg'] : 1 ) );
$base  = get_post_type_archive_link( 'rp_review' );
$query = new WP_Query( array( 'post_type' => 'rp_review', 'post_status' => 'publish', 'posts_per_page' => 12, 'paged' => $pg ) );
$google = rp_setting( 'reviews_url' );
?>
<main id="main" class="rpt-wrap rpt-page">
	<?php rpt_breadcrumbs( array( array( 'Reviews' ) ) ); ?>
	<header class="rpt-page-head">
		<h1>Customer Reviews</h1>
		<p>What our customers say about their finished projects.</p>
		<?php if ( $google ) : ?>
			<p><a class="rp-btn" href="<?php echo esc_url( $google ); ?>" target="_blank" rel="noopener">Read our reviews on Google</a></p>
		<?php endif; ?>
	</header>

	<?php if ( $query->have_posts() ) : ?>
		<div class="rpt-three">
			<?php
			foreach ( $query->posts as $review ) {
				echo rp_review_card( $review->ID ); // phpcs:ignore WordPress.Security.EscapeOutput
			}
			?>
		</div>
		<nav class="rp-more" aria-label="More reviews">
			<?php if ( $pg > 1 ) : ?>
				<a class="rp-btn" rel="prev" href="<?php echo esc_url( add_query_arg( 'pg', $pg - 1, $base ) ); ?>">Previous</a>
			<?php endif; ?>
			<?php if ( $pg < (int) $query->max_num_pages ) : ?>
				<a class="rp-btn rp-btn-dark" rel="next" href="<?php echo esc_url( add_query_arg( 'pg', $pg + 1, $base ) ); ?>">More reviews</a>
			<?php endif; ?>
		</nav>
	<?php else : ?>
		<div class="rp-empty">
			<h2>Reviews will appear here soon</h2>
			<a class="rp-btn rp-btn-dark" href="<?php echo esc_url( rp_gallery_base_url() ); ?>">Explore Our Gallery</a>
		</div>
	<?php endif; ?>
</main>
<?php
get_footer();
