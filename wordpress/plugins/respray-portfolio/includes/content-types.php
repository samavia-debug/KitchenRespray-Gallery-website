<?php
/**
 * Content types and the fixed category lists.
 *
 * The `projects` post type and the colour / finish / style / room / brand
 * taxonomy keys match the old JetEngine site, so existing project URLs
 * (/projects/<name>/) and category data carry over unchanged. `room` is shown
 * to staff and visitors as "Service".
 */

defined( 'ABSPATH' ) || exit;

/** Filter name used in gallery URLs => taxonomy key. */
function rp_filter_map() {
	return array(
		'colour'   => 'colour',
		'service'  => 'room',
		'style'    => 'style',
		'finish'   => 'finish',
		'material' => 'material',
		'brand'    => 'brand',
	);
}

function rp_filter_labels() {
	return array(
		'colour'   => 'Colour',
		'service'  => 'Service',
		'style'    => 'Style',
		'finish'   => 'Finish',
		'material' => 'Material',
		'brand'    => 'Paint brand',
	);
}

function rp_post_type_caps() {
	return array(
		'capability_type' => array( 'rp_project', 'rp_projects' ),
		'map_meta_cap'    => true,
	);
}

function rp_register_content_types() {
	$caps = rp_post_type_caps();

	register_post_type(
		'projects',
		array_merge(
			$caps,
			array(
				'labels'       => array(
					'name'               => 'Projects',
					'singular_name'      => 'Project',
					'add_new'            => 'Add New Project',
					'add_new_item'       => 'Add New Project',
					'edit_item'          => 'Edit Project',
					'new_item'           => 'New Project',
					'view_item'          => 'View Project',
					'search_items'       => 'Search Projects',
					'not_found'          => 'No projects found',
					'all_items'          => 'All Projects',
					'featured_image'     => 'Main photo',
					'set_featured_image' => 'Choose main photo',
					'remove_featured_image' => 'Remove main photo',
					'use_featured_image' => 'Use as main photo',
				),
				'public'       => true,
				'has_archive'  => false,
				'rewrite'      => array( 'slug' => 'projects', 'with_front' => false ),
				'menu_icon'    => 'dashicons-format-gallery',
				'menu_position' => 5,
				'supports'     => array( 'title', 'editor', 'thumbnail', 'revisions', 'author' ),
				'show_in_rest' => true,
			)
		)
	);

	// Landing pages such as /gallery/black-kitchens/. The archive is the main gallery.
	register_post_type(
		'rp_collection',
		array_merge(
			$caps,
			array(
				'labels'       => array(
					'name'          => 'Gallery pages',
					'singular_name' => 'Gallery page',
					'add_new'       => 'Add gallery page',
					'add_new_item'  => 'Add gallery page',
					'edit_item'     => 'Edit gallery page',
					'all_items'     => 'Gallery pages',
				),
				'public'       => true,
				'has_archive'  => 'gallery',
				'rewrite'      => array( 'slug' => 'gallery', 'with_front' => false ),
				'show_in_menu' => 'edit.php?post_type=projects',
				'supports'     => array( 'title', 'editor', 'thumbnail' ),
				'show_in_rest' => true,
			)
		)
	);

	register_post_type(
		'rp_video',
		array_merge(
			$caps,
			array(
				'labels'       => array(
					'name'          => 'Videos',
					'singular_name' => 'Video',
					'add_new'       => 'Add video',
					'add_new_item'  => 'Add video',
					'edit_item'     => 'Edit video',
					'all_items'     => 'All videos',
				),
				'public'       => true,
				'has_archive'  => 'videos',
				'rewrite'      => array( 'slug' => 'videos', 'with_front' => false ),
				'menu_icon'    => 'dashicons-video-alt3',
				'menu_position' => 6,
				'supports'     => array( 'title', 'editor', 'thumbnail' ),
				'show_in_rest' => true,
			)
		)
	);

	register_post_type(
		'rp_review',
		array_merge(
			$caps,
			array(
				'labels'       => array(
					'name'          => 'Reviews',
					'singular_name' => 'Review',
					'add_new'       => 'Add review',
					'add_new_item'  => 'Add review',
					'edit_item'     => 'Edit review',
					'all_items'     => 'All reviews',
				),
				'public'       => true,
				'has_archive'  => 'reviews',
				'rewrite'      => array( 'slug' => 'reviews', 'with_front' => false ),
				'menu_icon'    => 'dashicons-star-filled',
				'menu_position' => 7,
				'supports'     => array( 'title', 'editor' ),
				'show_in_rest' => true,
			)
		)
	);

	register_post_type(
		'rp_resource',
		array_merge(
			$caps,
			array(
				'labels'       => array(
					'name'          => 'Brochures',
					'singular_name' => 'Brochure',
					'add_new'       => 'Add brochure or resource',
					'add_new_item'  => 'Add brochure or resource',
					'edit_item'     => 'Edit brochure',
					'all_items'     => 'All brochures',
					'featured_image' => 'Cover image',
					'set_featured_image' => 'Choose cover image',
				),
				'public'       => true,
				'has_archive'  => 'resources',
				'rewrite'      => array( 'slug' => 'resources', 'with_front' => false ),
				'menu_icon'    => 'dashicons-media-document',
				'menu_position' => 8,
				'supports'     => array( 'title', 'editor', 'thumbnail' ),
				'show_in_rest' => true,
			)
		)
	);

	// Staff pick from fixed lists; only administrators can add or rename options.
	$tax_caps = array(
		'manage_terms' => 'manage_options',
		'edit_terms'   => 'manage_options',
		'delete_terms' => 'manage_options',
		'assign_terms' => 'edit_rp_projects',
	);

	$project_taxonomies = array(
		'colour'   => array( 'Colours', 'Colour' ),
		'room'     => array( 'Services', 'Service' ),
		'style'    => array( 'Styles', 'Style' ),
		'finish'   => array( 'Finishes', 'Finish' ),
		'material' => array( 'Materials', 'Material' ),
		'brand'    => array( 'Paint brands', 'Paint brand' ),
	);

	foreach ( $project_taxonomies as $key => $names ) {
		register_taxonomy(
			$key,
			'projects',
			array(
				'labels'             => array(
					'name'          => $names[0],
					'singular_name' => $names[1],
					'add_new_item'  => 'Add ' . strtolower( $names[1] ),
					'edit_item'     => 'Edit ' . strtolower( $names[1] ),
					'all_items'     => 'All ' . strtolower( $names[0] ),
				),
				'hierarchical'       => true, // Tick-box list in the editor.
				'public'             => false,
				'show_ui'            => true,
				'show_in_rest'       => true,
				'show_admin_column'  => false,
				// Gallery URLs use ?colour=black etc. themselves, so WordPress
				// must not treat those as taxonomy archive requests.
				'query_var'          => false,
				'rewrite'            => false,
				'capabilities'       => $tax_caps,
			)
		);
	}

	// Kept only so data from the old site is not lost on import.
	foreach ( array( 'size' => 'Size', 'project-type' => 'Project type' ) as $key => $name ) {
		register_taxonomy(
			$key,
			'projects',
			array(
				'labels'       => array( 'name' => $name, 'singular_name' => $name ),
				'public'       => false,
				'show_ui'      => false,
				'show_in_rest' => true,
				'query_var'    => false,
				'rewrite'      => false,
				'capabilities' => $tax_caps,
			)
		);
	}

	foreach ( array( 'rp_video_cat' => 'rp_video', 'rp_resource_cat' => 'rp_resource' ) as $key => $type ) {
		register_taxonomy(
			$key,
			$type,
			array(
				'labels'            => array( 'name' => 'Categories', 'singular_name' => 'Category' ),
				'hierarchical'      => true,
				'public'            => false,
				'show_ui'           => true,
				'show_in_rest'      => true,
				'show_admin_column' => true,
				'query_var'         => false,
				'rewrite'           => false,
				'capabilities'      => $tax_caps,
			)
		);
	}

	add_image_size( 'rp-card', 720, 540, true );
	add_image_size( 'rp-card-2x', 1440, 1080, true );
	add_image_size( 'rp-wide', 1600, 0 );
}
add_action( 'init', 'rp_register_content_types' );

/** Plain editor for every portfolio content type: one simple screen for staff. */
add_filter(
	'use_block_editor_for_post_type',
	function ( $use, $type ) {
		return in_array( $type, array( 'projects', 'rp_collection', 'rp_video', 'rp_review', 'rp_resource' ), true ) ? false : $use;
	},
	10,
	2
);

/** Let the 'rp-card' sizes appear in srcset so phones get small files. */
add_filter(
	'image_size_names_choose',
	function ( $sizes ) {
		return $sizes + array( 'rp-wide' => 'Portfolio wide' );
	}
);

function rp_default_terms() {
	return array(
		'colour'          => array(
			'black'       => 'Black',
			'white'       => 'White',
			'grey'        => 'Grey',
			'cream'       => 'Cream',
			'beige'       => 'Beige',
			'blue'        => 'Blue',
			'navy-blue'   => 'Navy Blue',
			'green'       => 'Green',
			'anthracite'  => 'Anthracite',
			'walnut'      => 'Walnut',
			'oak'         => 'Oak',
			'yellow'      => 'Yellow',
			'other'       => 'Other Colours',
		),
		'room'            => array(
			'kitchen'    => 'Kitchen Respray',
			'cabinet'    => 'Cabinet Respray',
			'wardrobe'   => 'Wardrobe Respray',
			'furniture'  => 'Furniture Respray',
			'commercial' => 'Commercial Respray',
			'door'       => 'Door & Window Respray',
			'bathroom'   => 'Bathroom Respray',
			'other'      => 'Other Spray Services',
		),
		'style'           => array(
			'modern'       => 'Modern',
			'traditional'  => 'Traditional',
			'contemporary' => 'Contemporary',
			'shaker'       => 'Shaker',
			'handleless'   => 'Handleless',
			'minimal'      => 'Minimal',
		),
		'finish'          => array(
			'matt'     => 'Matt',
			'satin'    => 'Satin',
			'gloss'    => 'Gloss',
			'metallic' => 'Metallic',
			'special'  => 'Special Finishes',
		),
		'material'        => array(
			'solid-wood' => 'Solid Wood',
			'mdf'        => 'MDF',
			'laminate'   => 'Laminate',
			'vinyl-wrap' => 'Vinyl Wrap',
			'upvc'       => 'uPVC',
			'other'      => 'Other',
		),
		'brand'           => array(
			'colortrend-paints' => 'Colortrend Paints',
			'farrow-and-ball'   => 'Farrow & Ball',
		),
		'rp_video_cat'    => array(
			'completed-kitchens'  => 'Completed Kitchens',
			'walkthroughs'        => 'Project Walkthroughs',
			'before-and-after'    => 'Before and After',
			'testimonials'        => 'Customer Testimonials',
			'workshop'            => 'Workshop',
			'application'         => 'Application Demonstrations',
			'finishing'           => 'Finishing Demonstrations',
			'promotional'         => 'Company Videos',
		),
		'rp_resource_cat' => array(
			'company-brochures' => 'Company Brochures',
			'service-brochures' => 'Service Brochures',
			'colour-guides'     => 'Colour Guides',
			'finish-guides'     => 'Finish Guides',
			'product-info'      => 'Product Information',
			'catalogues'        => 'Catalogues',
		),
	);
}

function rp_seed_terms() {
	foreach ( rp_default_terms() as $taxonomy => $terms ) {
		foreach ( $terms as $slug => $name ) {
			if ( ! get_term_by( 'slug', $slug, $taxonomy ) ) {
				wp_insert_term( $name, $taxonomy, array( 'slug' => $slug ) );
			}
		}
	}
}

/** Shareable landing pages created on first activation. Staff can add more. */
function rp_default_collections() {
	return array(
		'black-kitchens'  => array( 'Black Kitchens', array( 'colour' => array( 'black' ), 'service' => array( 'kitchen' ) ) ),
		'white-kitchens'  => array( 'White Kitchens', array( 'colour' => array( 'white' ), 'service' => array( 'kitchen' ) ) ),
		'grey-kitchens'   => array( 'Grey Kitchens', array( 'colour' => array( 'grey' ), 'service' => array( 'kitchen' ) ) ),
		'cream-kitchens'  => array( 'Cream Kitchens', array( 'colour' => array( 'cream' ), 'service' => array( 'kitchen' ) ) ),
		'green-kitchens'  => array( 'Green Kitchens', array( 'colour' => array( 'green' ), 'service' => array( 'kitchen' ) ) ),
		'blue-kitchens'   => array( 'Blue Kitchens', array( 'colour' => array( 'blue', 'navy-blue' ), 'service' => array( 'kitchen' ) ) ),
		'shaker-kitchens' => array( 'Shaker Kitchens', array( 'style' => array( 'shaker' ), 'service' => array( 'kitchen' ) ) ),
		'kitchens'        => array( 'Kitchen Resprays', array( 'service' => array( 'kitchen' ) ) ),
		'wardrobes'       => array( 'Wardrobe Resprays', array( 'service' => array( 'wardrobe' ) ) ),
		'furniture'       => array( 'Furniture Resprays', array( 'service' => array( 'furniture' ) ) ),
		'commercial'      => array( 'Commercial Resprays', array( 'service' => array( 'commercial' ) ) ),
		'doors'           => array( 'Door and Window Resprays', array( 'service' => array( 'door' ) ) ),
		'bathrooms'       => array( 'Bathroom Resprays', array( 'service' => array( 'bathroom' ) ) ),
		'matt-finishes'   => array( 'Matt Finishes', array( 'finish' => array( 'matt' ) ) ),
		'gloss-finishes'  => array( 'Gloss Finishes', array( 'finish' => array( 'gloss' ) ) ),
	);
}

function rp_seed_collections() {
	foreach ( rp_default_collections() as $slug => $def ) {
		if ( get_page_by_path( $slug, OBJECT, 'rp_collection' ) ) {
			continue;
		}
		$id = wp_insert_post(
			array(
				'post_type'    => 'rp_collection',
				'post_status'  => 'publish',
				'post_title'   => $def[0],
				'post_name'    => $slug,
				'post_content' => 'Browse our completed ' . strtolower( $def[0] ) . '. Every photograph shows real work carried out by our team.',
			)
		);
		if ( $id && ! is_wp_error( $id ) ) {
			update_post_meta( $id, '_rp_filters', $def[1] );
		}
	}
}

function rp_seed_pages() {
	$pages = array(
		'transformations' => array( 'Before and After Transformations', '' ),
		'about'           => array( 'Our Experience', '' ),
	);
	foreach ( $pages as $slug => $def ) {
		if ( ! get_page_by_path( $slug ) ) {
			wp_insert_post(
				array(
					'post_type'    => 'page',
					'post_status'  => 'publish',
					'post_title'   => $def[0],
					'post_name'    => $slug,
					'post_content' => $def[1],
				)
			);
		}
	}
}
