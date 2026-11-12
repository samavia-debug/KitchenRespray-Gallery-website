<?php
/**
 * Fallback template: ordinary pages (About, Privacy...) and anything without
 * its own template.
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<main id="main" class="rpt-wrap rpt-page rpt-narrow">
	<?php if ( is_404() || ! have_posts() ) : ?>
		<header class="rpt-page-head">
			<h1>Page not found</h1>
			<p>That page has moved or no longer exists.</p>
		</header>
		<p><a class="rp-btn rp-btn-dark" href="<?php echo esc_url( home_url( '/gallery/' ) ); ?>">Explore Our Gallery</a></p>
	<?php else : ?>
		<?php while ( have_posts() ) : ?>
			<?php the_post(); ?>
			<?php
			if ( is_singular() ) {
				rpt_breadcrumbs( array( array( get_the_title() ) ) );
			}
			?>
			<article>
				<header class="rpt-page-head">
					<?php if ( is_singular() ) : ?>
						<h1><?php the_title(); ?></h1>
					<?php else : ?>
						<h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
					<?php endif; ?>
				</header>
				<div class="rpt-prose"><?php the_content(); ?></div>
			</article>
		<?php endwhile; ?>
		<?php if ( is_page( 'about' ) && rpt_ready() ) : ?>
			<p><a class="rp-btn rp-btn-dark" href="<?php echo esc_url( rp_gallery_base_url() ); ?>">See our completed work</a></p>
		<?php endif; ?>
	<?php endif; ?>
</main>
<?php
get_footer();
