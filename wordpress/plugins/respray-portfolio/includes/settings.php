<?php
/**
 * Portfolio settings: homepage wording, hero image and company links.
 */

defined( 'ABSPATH' ) || exit;

function rp_setting_defaults() {
	return array(
		'headline'        => 'Real Projects. Beautiful Transformations. Proven Results.',
		'subtext'         => 'Explore our portfolio of completed respray projects, discover colours and finishes, and see the quality of our workmanship.',
		'hero_image'      => 0,
		'about_heading'   => 'Our Experience',
		'about_text'      => '',
		'reviews_url'     => '',
		'contact_url'     => '',
		'contact_label'   => 'Get in touch',
		'footer_text'     => '',
	);
}

function rp_setting( $key ) {
	$all = wp_parse_args( (array) get_option( 'rp_settings', array() ), rp_setting_defaults() );
	return isset( $all[ $key ] ) ? $all[ $key ] : '';
}

add_action(
	'admin_menu',
	function () {
		add_submenu_page(
			'edit.php?post_type=projects',
			'Portfolio settings',
			'Portfolio settings',
			'manage_options',
			'rp-settings',
			'rp_settings_page'
		);
	}
);

add_action(
	'admin_init',
	function () {
		register_setting(
			'rp_settings',
			'rp_settings',
			array(
				'sanitize_callback' => function ( $in ) {
					$in  = (array) $in;
					$out = array();
					foreach ( rp_setting_defaults() as $key => $default ) {
						$value = isset( $in[ $key ] ) ? $in[ $key ] : '';
						if ( 'hero_image' === $key ) {
							$out[ $key ] = absint( $value );
						} elseif ( in_array( $key, array( 'reviews_url', 'contact_url' ), true ) ) {
							$out[ $key ] = esc_url_raw( trim( $value ) );
						} elseif ( in_array( $key, array( 'about_text', 'subtext', 'footer_text' ), true ) ) {
							$out[ $key ] = sanitize_textarea_field( $value );
						} else {
							$out[ $key ] = sanitize_text_field( $value );
						}
					}
					return $out;
				},
			)
		);
	}
);

function rp_settings_page() {
	$fields = array(
		'headline'      => array( 'Homepage headline', 'text' ),
		'subtext'       => array( 'Homepage introduction', 'textarea' ),
		'hero_image'    => array( 'Homepage main image', 'image' ),
		'about_heading' => array( 'About section heading', 'text' ),
		'about_text'    => array( 'About section text (homepage). Leave empty to hide the section.', 'textarea' ),
		'reviews_url'   => array( 'Link to your Google reviews', 'url' ),
		'contact_url'   => array( 'Contact / enquiry link (shown in the menu and on project pages)', 'url' ),
		'contact_label' => array( 'Contact button wording', 'text' ),
		'footer_text'   => array( 'Footer text', 'textarea' ),
	);
	wp_enqueue_media();
	?>
	<div class="wrap">
		<h1>Portfolio settings</h1>
		<form method="post" action="options.php">
			<?php settings_fields( 'rp_settings' ); ?>
			<table class="form-table" role="presentation">
				<?php foreach ( $fields as $key => $def ) : ?>
					<tr>
						<th scope="row"><label for="rp-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $def[0] ); ?></label></th>
						<td>
							<?php
							$name  = 'rp_settings[' . $key . ']';
							$value = rp_setting( $key );
							if ( 'textarea' === $def[1] ) {
								printf( '<textarea class="large-text" rows="4" id="rp-%s" name="%s">%s</textarea>', esc_attr( $key ), esc_attr( $name ), esc_textarea( $value ) );
							} elseif ( 'image' === $def[1] ) {
								$src = $value ? wp_get_attachment_image_url( (int) $value, 'medium' ) : '';
								printf(
									'<div class="rp-single-image" data-rp-single-image><input type="hidden" id="rp-%1$s" name="%2$s" value="%3$s"><img src="%4$s" alt="" style="max-width:240px;height:auto;display:%5$s;margin-bottom:8px"><br><button type="button" class="button" data-rp-pick>Choose image</button> <button type="button" class="button-link" data-rp-clear>Remove</button><p class="description">Leave empty to use the newest featured project.</p></div>',
									esc_attr( $key ),
									esc_attr( $name ),
									esc_attr( $value ),
									esc_url( $src ),
									$src ? 'block' : 'none'
								);
							} else {
								printf( '<input class="regular-text" type="%s" id="rp-%s" name="%s" value="%s">', esc_attr( $def[1] ), esc_attr( $key ), esc_attr( $name ), esc_attr( $value ) );
							}
							?>
						</td>
					</tr>
				<?php endforeach; ?>
			</table>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}
