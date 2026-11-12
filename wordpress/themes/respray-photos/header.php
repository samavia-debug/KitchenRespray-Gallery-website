<?php defined( 'ABSPATH' ) || exit; ?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="screen-reader-text rpt-skip" href="#main">Skip to content</a>
<header class="rpt-header">
	<div class="rpt-wrap rpt-header-inner">
		<div class="rpt-brand">
			<?php if ( has_custom_logo() ) : ?>
				<?php the_custom_logo(); ?>
			<?php else : ?>
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home"><?php bloginfo( 'name' ); ?></a>
			<?php endif; ?>
		</div>
		<button type="button" class="rpt-nav-toggle" aria-expanded="false" aria-controls="rpt-nav" data-rpt-nav-toggle>
			<span class="screen-reader-text">Menu</span><span class="rpt-burger" aria-hidden="true"></span>
		</button>
		<nav id="rpt-nav" class="rpt-nav" aria-label="Main">
			<?php
			if ( has_nav_menu( 'primary' ) ) {
				wp_nav_menu( array( 'theme_location' => 'primary', 'container' => false, 'menu_class' => 'rpt-menu', 'depth' => 1 ) );
			} else {
				rpt_default_menu();
			}
			$contact = rpt_setting( 'contact_url' );
			if ( $contact ) {
				printf( '<a class="rp-btn rp-btn-dark rpt-nav-cta" href="%s">%s</a>', esc_url( $contact ), esc_html( rpt_setting( 'contact_label' ) ) );
			}
			?>
		</nav>
	</div>
</header>
