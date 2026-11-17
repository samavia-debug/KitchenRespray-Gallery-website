<?php defined( 'ABSPATH' ) || exit; ?>
<footer class="rpt-footer">
	<div class="rpt-wrap rpt-footer-grid">
		<div>
			<p class="rpt-footer-brand"><?php bloginfo( 'name' ); ?></p>
			<?php if ( rpt_setting( 'footer_text' ) ) : ?>
				<p><?php echo nl2br( esc_html( rpt_setting( 'footer_text' ) ) ); ?></p>
			<?php endif; ?>
		</div>
		<?php if ( rpt_ready() && rp_collection_index() ) : ?>
			<nav aria-label="Collections">
				<h2>Collections</h2>
				<ul>
					<?php foreach ( array_slice( rp_collection_index(), 0, 10 ) as $collection ) : ?>
						<li><a href="<?php echo esc_url( $collection['url'] ); ?>"><?php echo esc_html( $collection['title'] ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			</nav>
		<?php endif; ?>
		<nav aria-label="Footer">
			<h2>Explore</h2>
			<?php
			if ( has_nav_menu( 'footer' ) ) {
				wp_nav_menu( array( 'theme_location' => 'footer', 'container' => false, 'depth' => 1 ) );
			} else {
				rpt_default_menu();
			}
			?>
		</nav>
	</div>
	<div class="rpt-wrap rpt-footer-base">
		<p>&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> <?php bloginfo( 'name' ); ?>. All rights reserved. Made by SAM.</p>
	</div>
</footer>

<div class="rp-lightbox" data-rp-lightbox hidden>
	<button type="button" class="rp-lightbox-close" data-rp-lightbox-close aria-label="Close">&times;</button>
	<button type="button" class="rp-lightbox-prev" data-rp-lightbox-prev aria-label="Previous photo">&#8249;</button>
	<img src="" alt="">
	<button type="button" class="rp-lightbox-next" data-rp-lightbox-next aria-label="Next photo">&#8250;</button>
</div>
<div class="rp-toast" data-rp-toast role="status" aria-live="polite" hidden></div>
<?php wp_footer(); ?>
</body>
</html>
