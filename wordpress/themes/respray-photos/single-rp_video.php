<?php
/**
 * A single video page (gives each video its own address to share).
 */

defined( 'ABSPATH' ) || exit;

get_header();

if ( ! rpt_ready() ) {
	rpt_plugin_missing();
	get_footer();
	return;
}

the_post();
?>
<main id="main" class="rpt-wrap rpt-page rpt-narrow">
	<?php rpt_breadcrumbs( array( array( 'Videos', get_post_type_archive_link( 'rp_video' ) ), array( get_the_title() ) ) ); ?>
	<h1 class="screen-reader-text"><?php the_title(); ?></h1>
	<?php echo rp_video_card( get_the_ID() ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
	<p><a class="rp-link" href="<?php echo esc_url( get_post_type_archive_link( 'rp_video' ) ); ?>">All videos</a></p>
</main>
<?php
get_footer();
