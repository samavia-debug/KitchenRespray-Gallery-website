<?php
/**
 * Before-and-after gallery: /transformations/
 * Lists every project that has both a before and an after photo.
 */

defined( 'ABSPATH' ) || exit;

get_header();

if ( ! rpt_ready() ) {
	rpt_plugin_missing();
	get_footer();
	return;
}

the_post();
$pg    = max( 1, absint( isset( $_GET['pg'] ) ? $_GET['pg'] : 1 ) );
$query = rp_transformation_projects( 8, $pg );
?>
<main id="main" class="rpt-wrap rpt-page">
	<?php rpt_breadcrumbs( array( array( get_the_title() ) ) ); ?>
	<header class="rpt-page-head">
		<h1><?php the_title(); ?></h1>
		<?php if ( get_the_content() ) : ?>
			<div class="rpt-intro"><?php the_content(); ?></div>
		<?php else : ?>
			<p>Drag the handle on any photo to compare the original with the finished result.</p>
		<?php endif; ?>
	</header>

	<?php if ( $query->have_posts() ) : ?>
		<div class="rpt-ba-grid">
			<?php foreach ( $query->posts as $project ) : ?>
				<?php
				$pair = rp_ba_pair( $project->ID );
				if ( ! $pair ) {
					continue;
				}
				$labels = rp_project_labels( $project->ID );
				$text   = wp_trim_words( wp_strip_all_tags( $project->post_content ), 30 );
				?>
				<article>
					<?php echo rp_ba_slider( $pair[0], $pair[1], get_the_title( $project ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<?php if ( $labels ) : ?>
						<p class="rp-eyebrow"><?php echo esc_html( implode( ' · ', $labels ) ); ?></p>
					<?php endif; ?>
					<h2><a href="<?php echo esc_url( get_permalink( $project ) ); ?>"><?php echo esc_html( get_the_title( $project ) ); ?></a></h2>
					<?php if ( $text ) : ?>
						<p><?php echo esc_html( $text ); ?></p>
					<?php endif; ?>
					<a class="rp-link" href="<?php echo esc_url( get_permalink( $project ) ); ?>">View the full project</a>
				</article>
			<?php endforeach; ?>
		</div>
		<nav class="rp-more" aria-label="More transformations">
			<?php if ( $pg > 1 ) : ?>
				<a class="rp-btn" rel="prev" href="<?php echo esc_url( add_query_arg( 'pg', $pg - 1, get_permalink() ) ); ?>">Previous</a>
			<?php endif; ?>
			<?php if ( $pg < (int) $query->max_num_pages ) : ?>
				<a class="rp-btn rp-btn-dark" rel="next" href="<?php echo esc_url( add_query_arg( 'pg', $pg + 1, get_permalink() ) ); ?>">More transformations</a>
			<?php endif; ?>
		</nav>
	<?php else : ?>
		<div class="rp-empty">
			<h2>Before-and-after projects are on their way</h2>
			<p>In the meantime, browse the full gallery of completed work.</p>
			<a class="rp-btn rp-btn-dark" href="<?php echo esc_url( rp_gallery_base_url() ); ?>">Explore Our Gallery</a>
		</div>
	<?php endif; ?>
</main>
<?php
get_footer();
