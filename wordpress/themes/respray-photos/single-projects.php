<?php
/**
 * A single project: /projects/<name>/
 */

defined( 'ABSPATH' ) || exit;

get_header();

if ( ! rpt_ready() ) {
	rpt_plugin_missing();
	get_footer();
	return;
}

the_post();

$project_id = get_the_ID();
$title      = get_the_title();
$main       = (int) get_post_thumbnail_id( $project_id );
$before     = rp_get_photos( $project_id, 'before' );
$after      = rp_get_photos( $project_id, 'after' );
$more       = rp_get_photos( $project_id, 'gallery' );
$pair       = rp_ba_pair( $project_id );
$video_url  = get_post_meta( $project_id, '_rp_video_url', true );
$share_url  = get_permalink();

// Detail rows; each links to the gallery filtered the same way.
$details = array();
$get     = function ( $key ) use ( $project_id ) {
	return trim( (string) get_post_meta( $project_id, '_rp_' . $key, true ) );
};
$term_row = function ( $label, $filter, $taxonomy ) use ( $project_id, &$details ) {
	$terms = get_the_terms( $project_id, $taxonomy );
	if ( ! $terms || is_wp_error( $terms ) ) {
		return;
	}
	$links = array();
	foreach ( $terms as $term ) {
		$filters                   = rp_empty_filters();
		$filters['tax'][ $filter ] = array( $term->slug );
		$links[]                   = '<a href="' . esc_url( rp_gallery_url( $filters ) ) . '">' . esc_html( $term->name ) . '</a>';
	}
	$details[ $label ] = implode( ', ', $links );
};

$term_row( 'Service', 'service', 'room' );
if ( $get( 'original_colour' ) ) {
	$details['Original colour'] = esc_html( $get( 'original_colour' ) );
}
if ( $get( 'new_colour' ) ) {
	$details['New colour'] = esc_html( $get( 'new_colour' ) );
}
$term_row( 'Colour', 'colour', 'colour' );
$term_row( 'Finish', 'finish', 'finish' );
$term_row( 'Style', 'style', 'style' );
$term_row( 'Material', 'material', 'material' );
$term_row( 'Paint brand', 'brand', 'brand' );
if ( $get( 'location' ) ) {
	$details['Location'] = esc_html( $get( 'location' ) );
}
if ( $get( 'completed' ) ) {
	$details['Completed'] = esc_html( date_i18n( 'F Y', strtotime( $get( 'completed' ) ) ) );
}

// Photos for the gallery: finished work first. The main photo is not repeated.
$photos = array();
foreach ( array( 'After' => $after, '' => $more, 'Before' => $before ) as $label => $ids ) {
	foreach ( $ids as $photo_id ) {
		if ( $photo_id !== $main ) {
			$photos[ $photo_id ] = $label;
		}
	}
}

$service  = rp_first_term( $project_id, 'room' );
$crumbs   = array( array( 'Gallery', rp_gallery_base_url() ) );
if ( $service ) {
	$filters                   = rp_empty_filters();
	$filters['tax']['service'] = array( $service->slug );
	$match                     = rp_match_collection( $filters );
	$crumbs[]                  = array( $service->name, $match ? $match['url'] : rp_gallery_url( $filters ) );
}
$crumbs[] = array( $title );
?>
<main id="main" class="rpt-wrap rpt-page rpt-project">
	<?php rpt_breadcrumbs( $crumbs ); ?>

	<header class="rpt-page-head">
		<?php $labels = rp_project_labels( $project_id ); ?>
		<?php if ( $labels ) : ?>
			<p class="rp-eyebrow"><?php echo esc_html( implode( ' · ', $labels ) ); ?></p>
		<?php endif; ?>
		<h1><?php echo esc_html( $title ); ?></h1>
	</header>

	<?php if ( $main ) : ?>
		<?php $full = wp_get_attachment_image_src( $main, 'rp-wide' ); ?>
		<a class="rpt-project-main" href="<?php echo esc_url( $full ? $full[0] : '' ); ?>" data-rp-zoom>
			<?php echo wp_get_attachment_image( $main, 'rp-wide', false, array( 'loading' => 'eager', 'fetchpriority' => 'high', 'sizes' => '(min-width: 1240px) 1200px, 100vw' ) ); ?>
		</a>
	<?php endif; ?>

	<div class="rpt-project-body">
		<div class="rpt-project-text">
			<?php if ( get_the_content() ) : ?>
				<div class="rpt-prose"><?php the_content(); ?></div>
			<?php endif; ?>

			<?php if ( $pair ) : ?>
				<section class="rpt-project-ba">
					<h2>Before and after</h2>
					<?php echo rp_ba_slider( $pair[0], $pair[1], $title ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<p class="rpt-hint">Drag the handle to compare.</p>
				</section>
			<?php endif; ?>
		</div>

		<aside class="rpt-project-side">
			<?php if ( $details ) : ?>
				<h2>Project details</h2>
				<dl class="rpt-details">
					<?php foreach ( $details as $label => $value ) : ?>
						<div><dt><?php echo esc_html( $label ); ?></dt><dd><?php echo wp_kses_post( $value ); ?></dd></div>
					<?php endforeach; ?>
				</dl>
			<?php endif; ?>

			<div class="rpt-share">
				<h2>Share this project</h2>
				<button type="button" class="rp-btn rp-btn-dark" data-rp-copy="<?php echo esc_url( $share_url ); ?>">Copy link</button>
				<a class="rp-btn" href="<?php echo esc_url( 'https://wa.me/?text=' . rawurlencode( $title . ' ' . $share_url ) ); ?>" target="_blank" rel="noopener">WhatsApp</a>
				<a class="rp-btn" href="<?php echo esc_url( 'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode( $share_url ) ); ?>" target="_blank" rel="noopener">Facebook</a>
				<a class="rp-btn" href="<?php echo esc_url( 'mailto:?subject=' . rawurlencode( $title ) . '&body=' . rawurlencode( $share_url ) ); ?>">Email</a>
			</div>

			<?php if ( rpt_setting( 'contact_url' ) ) : ?>
				<a class="rp-btn rp-btn-dark rpt-project-contact" href="<?php echo esc_url( rpt_setting( 'contact_url' ) ); ?>"><?php echo esc_html( rpt_setting( 'contact_label' ) ); ?></a>
			<?php endif; ?>
		</aside>
	</div>

	<?php if ( $photos ) : ?>
		<section class="rpt-project-photos">
			<h2>Project photos</h2>
			<div class="rpt-photo-grid">
				<?php foreach ( $photos as $photo_id => $label ) : ?>
					<?php $full = wp_get_attachment_image_src( $photo_id, 'rp-wide' ); ?>
					<?php if ( $full ) : ?>
						<a href="<?php echo esc_url( $full[0] ); ?>" data-rp-zoom>
							<?php echo rp_card_image( $photo_id, (string) get_post_meta( $photo_id, '_wp_attachment_image_alt', true ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
							<?php if ( $label ) : ?>
								<span class="rp-badge"><?php echo esc_html( $label ); ?></span>
							<?php endif; ?>
						</a>
					<?php endif; ?>
				<?php endforeach; ?>
			</div>
		</section>
	<?php endif; ?>

	<?php $embed = $video_url ? rp_video_embed_url( $video_url ) : ''; ?>
	<?php if ( $embed ) : ?>
		<section class="rpt-project-video">
			<h2>Project video</h2>
			<div class="rp-video-frame">
				<button type="button" class="rp-video-play" data-rp-embed="<?php echo esc_url( $embed ); ?>" aria-label="Play project video">
					<?php $thumb = rp_video_thumbnail( 0, $video_url ); ?>
					<?php if ( $thumb ) : ?>
						<img src="<?php echo esc_url( $thumb ); ?>" alt="" loading="lazy">
					<?php endif; ?>
					<span class="rp-play-icon" aria-hidden="true"></span>
				</button>
			</div>
		</section>
	<?php endif; ?>

	<?php $related = rp_related_projects( $project_id, 3 ); ?>
	<?php if ( $related ) : ?>
		<section class="rpt-related">
			<?php rpt_section_head( '', 'Related projects', 'Back to the gallery', rp_gallery_base_url() ); ?>
			<div class="rp-grid">
				<?php
				foreach ( $related as $related_id ) {
					echo rp_project_card( $related_id ); // phpcs:ignore WordPress.Security.EscapeOutput
				}
				?>
			</div>
		</section>
	<?php else : ?>
		<p><a class="rp-link" href="<?php echo esc_url( rp_gallery_base_url() ); ?>">Back to the gallery</a></p>
	<?php endif; ?>
</main>
<?php
get_footer();
