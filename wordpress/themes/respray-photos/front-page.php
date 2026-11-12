<?php
/**
 * Homepage. Every section is filled from real content and is left out
 * entirely when there is none yet (no placeholders, no stock photos).
 */

defined( 'ABSPATH' ) || exit;

get_header();

if ( ! rpt_ready() ) {
	rpt_plugin_missing();
	get_footer();
	return;
}

$featured   = rp_featured_projects( 6 );
$hero_image = (int) rp_setting( 'hero_image' );
if ( ! $hero_image && $featured ) {
	$hero_image = (int) get_post_thumbnail_id( $featured[0] );
}
$gallery_url = rp_gallery_base_url();
?>
<main id="main">

	<section class="rpt-hero<?php echo $hero_image ? '' : ' rpt-hero-plain'; ?>">
		<?php
		if ( $hero_image ) {
			echo wp_get_attachment_image( $hero_image, 'full', false, array( 'class' => 'rpt-hero-image', 'loading' => 'eager', 'fetchpriority' => 'high', 'sizes' => '100vw', 'alt' => '' ) );
		}
		?>
		<div class="rpt-wrap rpt-hero-content">
			<h1><?php echo esc_html( rp_setting( 'headline' ) ); ?></h1>
			<p class="rpt-hero-sub"><?php echo esc_html( rp_setting( 'subtext' ) ); ?></p>
			<form class="rpt-hero-search" method="get" action="<?php echo esc_url( $gallery_url ); ?>" role="search">
				<label class="screen-reader-text" for="rpt-hero-q">Search projects</label>
				<input type="search" id="rpt-hero-q" name="q" placeholder="Try &ldquo;black shaker kitchen&rdquo;" autocomplete="off">
				<button type="submit">Search</button>
			</form>
			<p class="rpt-hero-actions">
				<a class="rp-btn rp-btn-light" href="<?php echo esc_url( $gallery_url ); ?>">Explore Our Gallery</a>
				<a class="rp-btn rp-btn-ghost" href="<?php echo esc_url( home_url( '/transformations/' ) ); ?>">Discover Our Transformations</a>
			</p>
		</div>
	</section>

	<?php $tiles = rp_colour_tiles(); ?>
	<?php if ( $tiles ) : ?>
		<section class="rpt-section">
			<div class="rpt-wrap">
				<?php rpt_section_head( 'Browse', 'Explore Projects by Colour', 'All projects', $gallery_url ); ?>
				<div class="rpt-colours">
					<?php foreach ( array_slice( $tiles, 0, 8 ) as $tile ) : ?>
						<a class="rpt-colour" href="<?php echo esc_url( $tile['url'] ); ?>">
							<?php echo rp_card_image( $tile['image'], $tile['name'] . ' respray project' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
							<span class="rpt-colour-name"><?php echo esc_html( $tile['name'] ); ?></span>
							<span class="rpt-colour-count"><?php echo esc_html( sprintf( _n( '%s project', '%s projects', $tile['count'] ), number_format_i18n( $tile['count'] ) ) ); ?></span>
						</a>
					<?php endforeach; ?>
				</div>
			</div>
		</section>
	<?php endif; ?>

	<?php if ( $featured ) : ?>
		<section class="rpt-section rpt-section-tint">
			<div class="rpt-wrap">
				<?php rpt_section_head( 'Portfolio', 'Discover Our Latest Transformations', 'View the full gallery', $gallery_url ); ?>
				<div class="rp-grid">
					<?php
					foreach ( $featured as $project_id ) {
						echo rp_project_card( $project_id ); // phpcs:ignore WordPress.Security.EscapeOutput
					}
					?>
				</div>
			</div>
		</section>
	<?php endif; ?>

	<?php $transformations = rp_transformation_projects( 2 ); ?>
	<?php if ( $transformations->have_posts() ) : ?>
		<section class="rpt-section">
			<div class="rpt-wrap">
				<?php rpt_section_head( 'Before and after', 'See the Difference', 'More transformations', home_url( '/transformations/' ) ); ?>
				<div class="rpt-ba-grid">
					<?php foreach ( $transformations->posts as $project ) : ?>
						<?php $pair = rp_ba_pair( $project->ID ); ?>
						<?php if ( $pair ) : ?>
							<article>
								<?php echo rp_ba_slider( $pair[0], $pair[1], get_the_title( $project ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
								<h3><a href="<?php echo esc_url( get_permalink( $project ) ); ?>"><?php echo esc_html( get_the_title( $project ) ); ?></a></h3>
							</article>
						<?php endif; ?>
					<?php endforeach; ?>
				</div>
			</div>
		</section>
	<?php endif; ?>

	<?php $videos = get_posts( array( 'post_type' => 'rp_video', 'posts_per_page' => 3, 'fields' => 'ids' ) ); ?>
	<?php if ( $videos ) : ?>
		<section class="rpt-section rpt-section-dark">
			<div class="rpt-wrap">
				<?php rpt_section_head( 'Watch', 'Explore Our Video Collection', 'All videos', get_post_type_archive_link( 'rp_video' ) ); ?>
				<div class="rpt-three">
					<?php
					foreach ( $videos as $video_id ) {
						echo rp_video_card( $video_id ); // phpcs:ignore WordPress.Security.EscapeOutput
					}
					?>
				</div>
			</div>
		</section>
	<?php endif; ?>

	<?php $reviews = get_posts( array( 'post_type' => 'rp_review', 'posts_per_page' => 3, 'fields' => 'ids' ) ); ?>
	<?php if ( $reviews ) : ?>
		<section class="rpt-section">
			<div class="rpt-wrap">
				<?php rpt_section_head( 'Reviews', 'See What Our Customers Say', 'All reviews', get_post_type_archive_link( 'rp_review' ) ); ?>
				<div class="rpt-three">
					<?php
					foreach ( $reviews as $review_id ) {
						echo rp_review_card( $review_id ); // phpcs:ignore WordPress.Security.EscapeOutput
					}
					?>
				</div>
			</div>
		</section>
	<?php endif; ?>

	<?php $resources = get_posts( array( 'post_type' => 'rp_resource', 'posts_per_page' => 4, 'fields' => 'ids' ) ); ?>
	<?php if ( $resources ) : ?>
		<section class="rpt-section rpt-section-tint">
			<div class="rpt-wrap">
				<?php rpt_section_head( 'Resources', 'Download Our Brochures', 'All brochures', get_post_type_archive_link( 'rp_resource' ) ); ?>
				<div class="rpt-resources">
					<?php
					foreach ( $resources as $resource_id ) {
						echo rp_resource_card( $resource_id ); // phpcs:ignore WordPress.Security.EscapeOutput
					}
					?>
				</div>
			</div>
		</section>
	<?php endif; ?>

	<?php if ( rp_setting( 'about_text' ) ) : ?>
		<section class="rpt-section">
			<div class="rpt-wrap rpt-about">
				<div>
					<p class="rp-eyebrow">About us</p>
					<h2><?php echo esc_html( rp_setting( 'about_heading' ) ); ?></h2>
				</div>
				<div>
					<?php echo wp_kses_post( wpautop( esc_html( rp_setting( 'about_text' ) ) ) ); ?>
					<p><a class="rp-link" href="<?php echo esc_url( home_url( '/about/' ) ); ?>">More about our work</a></p>
				</div>
			</div>
		</section>
	<?php endif; ?>

	<section class="rpt-cta">
		<div class="rpt-wrap">
			<h2>Looking for a particular colour or style?</h2>
			<p>Search the full portfolio and share any collection with a single link.</p>
			<a class="rp-btn rp-btn-light" href="<?php echo esc_url( $gallery_url ); ?>">Explore Our Gallery</a>
		</div>
	</section>

</main>
<?php
get_footer();
