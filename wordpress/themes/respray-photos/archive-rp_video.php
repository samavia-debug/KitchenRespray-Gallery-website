<?php
/**
 * Video library: /videos/  (optional ?topic=<category>&q=<words>&pg=2)
 */

defined( 'ABSPATH' ) || exit;

get_header();

if ( ! rpt_ready() ) {
	rpt_plugin_missing();
	get_footer();
	return;
}

$base  = get_post_type_archive_link( 'rp_video' );
$topic = isset( $_GET['topic'] ) ? sanitize_title( wp_unslash( $_GET['topic'] ) ) : '';
$q     = isset( $_GET['q'] ) ? trim( mb_substr( sanitize_text_field( wp_unslash( $_GET['q'] ) ), 0, 80 ) ) : '';
$pg    = max( 1, absint( isset( $_GET['pg'] ) ? $_GET['pg'] : 1 ) );

$args = array( 'post_type' => 'rp_video', 'post_status' => 'publish', 'posts_per_page' => 12, 'paged' => $pg );
if ( $topic ) {
	$args['tax_query'] = array( array( 'taxonomy' => 'rp_video_cat', 'field' => 'slug', 'terms' => $topic ) );
}
if ( '' !== $q ) {
	$args['s'] = $q;
}
$query  = new WP_Query( $args );
$topics = get_terms( array( 'taxonomy' => 'rp_video_cat', 'hide_empty' => true ) );
$link   = function ( $extra ) use ( $base, $topic, $q ) {
	return add_query_arg( array_filter( array_merge( array( 'topic' => $topic, 'q' => $q ), $extra ) ), $base );
};
?>
<main id="main" class="rpt-wrap rpt-page">
	<?php rpt_breadcrumbs( array( array( 'Videos' ) ) ); ?>
	<header class="rpt-page-head">
		<h1>Video Library</h1>
		<p>Completed projects, walkthroughs and a look at how the work is done.</p>
	</header>

	<form class="rp-filters rp-filters-simple" method="get" action="<?php echo esc_url( $base ); ?>">
		<div class="rp-search">
			<label class="screen-reader-text" for="rp-vq">Search videos</label>
			<input type="search" id="rp-vq" name="q" value="<?php echo esc_attr( $q ); ?>" placeholder="Search videos">
			<?php if ( $topic ) : ?>
				<input type="hidden" name="topic" value="<?php echo esc_attr( $topic ); ?>">
			<?php endif; ?>
			<button type="submit" class="rp-btn rp-btn-dark">Search</button>
		</div>
		<?php if ( ! is_wp_error( $topics ) && $topics ) : ?>
			<ul class="rp-pill-list">
				<li><a href="<?php echo esc_url( $link( array( 'topic' => '' ) ) ); ?>" <?php echo $topic ? '' : 'aria-current="true"'; ?>>All</a></li>
				<?php foreach ( $topics as $term ) : ?>
					<li><a href="<?php echo esc_url( $link( array( 'topic' => $term->slug ) ) ); ?>" <?php echo $topic === $term->slug ? 'aria-current="true"' : ''; ?>><?php echo esc_html( $term->name ); ?></a></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</form>

	<?php if ( $query->have_posts() ) : ?>
		<div class="rpt-three">
			<?php
			foreach ( $query->posts as $video ) {
				echo rp_video_card( $video->ID ); // phpcs:ignore WordPress.Security.EscapeOutput
			}
			?>
		</div>
		<nav class="rp-more" aria-label="More videos">
			<?php if ( $pg > 1 ) : ?>
				<a class="rp-btn" rel="prev" href="<?php echo esc_url( $link( array( 'pg' => $pg - 1 ) ) ); ?>">Previous</a>
			<?php endif; ?>
			<?php if ( $pg < (int) $query->max_num_pages ) : ?>
				<a class="rp-btn rp-btn-dark" rel="next" href="<?php echo esc_url( $link( array( 'pg' => $pg + 1 ) ) ); ?>">More videos</a>
			<?php endif; ?>
		</nav>
	<?php else : ?>
		<div class="rp-empty">
			<h2><?php echo ( $topic || '' !== $q ) ? 'No videos match that search' : 'Videos are on their way'; ?></h2>
			<a class="rp-btn rp-btn-dark" href="<?php echo esc_url( rp_gallery_base_url() ); ?>">Explore Our Gallery</a>
		</div>
	<?php endif; ?>
</main>
<?php
get_footer();
