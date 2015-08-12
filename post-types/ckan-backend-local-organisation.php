<?php
/**
 * Post type ckan-local-org
 *
 * @package CKAN\Backend
 */

/**
 * Class Ckan_Backend_Local_Organisation
 */
class Ckan_Backend_Local_Organisation {

	// Be careful max. 20 characters allowed!
	const POST_TYPE = 'ckan-local-org';
	const FIELD_PREFIX = '_ckan_local_org_';

	/**
	 * Constructor of this class
	 */
	public function __construct() {
		$this->register_post_type();
		add_action( 'cmb2_init', array( $this, 'define_fields' ) );

		// render additional field after main cmb2 form is rendered
		add_action( 'cmb2_after_post_form_' . self::POST_TYPE . '-box', array( $this, 'render_addition_fields' ) );

		// initialize local organisation sync
		new Ckan_Backend_Sync_Local_Organisation( self::POST_TYPE, self::FIELD_PREFIX );
	}

	/**
	 * Renders additional fields which aren't saved in database.
	 */
	public function render_addition_fields() {
		// Field shows that the metadata is not yet saved in database -> get values from $_POST array
		echo '<input type="hidden" id="metadata_not_in_db" name="metadata_not_in_db" value="1" />';
	}

	/**
	 * Registers a new post type
	 *
	 * @return void
	 */
	public function register_post_type() {
		$labels = array(
			'name'               => __( 'CKAN Organisations', 'ogdch' ),
			'singular_name'      => __( 'CKAN Organisation', 'ogdch' ),
			'menu_name'          => __( 'CKAN Organisation', 'ogdch' ),
			'name_admin_bar'     => __( 'CKAN Organisation', 'ogdch' ),
			'parent_item_colon'  => __( 'Parent CKAN Organisation:', 'ogdch' ),
			'all_items'          => __( 'All CKAN Organisations', 'ogdch' ),
			'add_new_item'       => __( 'Add New CKAN Organisation', 'ogdch' ),
			'add_new'            => __( 'Add New', 'ogdch' ),
			'new_item'           => __( 'New CKAN Organisation', 'ogdch' ),
			'edit_item'          => __( 'Edit CKAN Organisation', 'ogdch' ),
			'update_item'        => __( 'Update CKAN Organisation', 'ogdch' ),
			'view_item'          => __( 'View CKAN Organisation', 'ogdch' ),
			'search_items'       => __( 'Search CKAN Organisations', 'ogdch' ),
			'not_found'          => __( 'Not found', 'ogdch' ),
			'not_found_in_trash' => __( 'Not found in Trash', 'ogdch' ),
		);

		$args = array(
			'label'               => __( 'CKAN', 'ogdch' ),
			'description'         => __( 'Contains Data from the CKAN Instance', 'ogdch' ),
			'labels'              => $labels,
			'supports'            => array( 'title' ),
			'hierarchical'        => false,
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'menu_position'       => 5,
			'menu_icon'           => 'dashicons-category',
			'show_in_admin_bar'   => true,
			'show_in_nav_menus'   => true,
			'can_export'          => true,
			'has_archive'         => false,
			'exclude_from_search' => false,
			'publicly_queryable'  => false,
			'map_meta_cap'        => true,
			'capability_type'     => 'dataset',
			'capabilities'        => array(
				'edit_posts'             => 'edit_datasets',
				'edit_others_posts'      => 'edit_others_datasets',
				'publish_posts'          => 'publish_datasets',
				'read_private_posts'     => 'read_private_datasets',
				'delete_posts'           => 'delete_datasets',
				'delete_private_posts'   => 'delete_private_datasets',
				'delete_published_posts' => 'delete_published_datasets',
				'delete_others_posts'    => 'delete_others_datasets',
				'edit_private_posts'     => 'edit_private_datasets',
				'edit_published_posts'   => 'edit_published_datasets',
				'create_posts'           => 'create_datasets',
				// Meta capabilites assigned by WordPress. Do not give to any role.
				'edit_post'              => 'edit_dataset',
				'read_post'              => 'read_dataset',
				'delete_post'            => 'delete_dataset',
			),
		);
		register_post_type( self::POST_TYPE, $args );
	}

	/**
	 * Define all custom fields of this post type
	 *
	 * @return void
	 */
	public function define_fields() {
		global $language_priority;

		$cmb = new_cmb2_box( array(
			'id'           => self::POST_TYPE . '-box',
			'title'        => __( 'Organisation Data', 'ogdch' ),
			'object_types' => array( self::POST_TYPE ),
			'context'      => 'normal',
			'priority'     => 'high',
			'show_names'   => true,
		) );

		/* Title */
		$cmb->add_field( array(
			'name' => __( 'Organisation Title', 'ogdch' ),
			'type' => 'title',
			'id'   => 'title_title',
		) );

		foreach ( $language_priority as $lang ) {
			$cmb->add_field( array(
				'name'       => __( 'Title', 'ogdch' ) . ' (' . strtoupper( $lang ) . ')',
				'id'         => self::FIELD_PREFIX . 'title_' . $lang,
				'type'       => 'text',
				'attributes' => array(
					'placeholder' => __( 'e.g. Awesome organisation', 'ogdch' ),
				),
			) );
		}

		/* Description */
		$cmb->add_field( array(
			'name' => __( 'Organisation Description', 'ogdch' ),
			'type' => 'title',
			'id'   => 'description_title',
			'desc' => __( 'Markdown Syntax can be used to format the description.', 'ogdch' ),
		) );

		foreach ( $language_priority as $lang ) {
			$cmb->add_field( array(
				'name'       => __( 'Description', 'ogdch' ) . ' (' . strtoupper( $lang ) . ')',
				'id'         => self::FIELD_PREFIX . 'description_' . $lang,
				'type'       => 'textarea',
				'attributes' => array( 'rows' => 3 ),
			) );
		}

		/* Parent */
		$cmb->add_field( array(
			'name' => __( 'Parent Organisation', 'ogdch' ),
			'type' => 'title',
			'id'   => 'parent_title',
		) );

		$cmb->add_field( array(
			'name'             => __( 'Parent', 'ogdch' ),
			'id'               => self::FIELD_PREFIX . 'parent',
			'type'             => 'select',
			'show_option_none' => __( 'None - top level', 'ogdch' ),
			'options'          => array( $this, 'get_parent_options' ),
		) );

		/* Image */
		$cmb->add_field( array(
			'name' => __( 'Organisation Image', 'ogdch' ),
			'type' => 'title',
			'id'   => 'image_title',
		) );

		$cmb->add_field( array(
			'name' => __( 'Image', 'ogdch' ),
			'id'   => self::FIELD_PREFIX . 'image',
			'type' => 'file',
		) );

		$cmb_side_ckan = new_cmb2_box( array(
			'id'           => self::POST_TYPE . '-sidebox',
			'title'        => __( 'CKAN Data', 'ogdch' ),
			'object_types' => array( self::POST_TYPE ),
			'context'      => 'side',
			'priority'     => 'low',
			'show_names'   => true,
		) );

		/* Ckan id (If Set -> update. Set on first save) */
		$cmb_side_ckan->add_field( array(
			'name'       => __( 'CKAN ID', 'ogdch' ),
			'id'         => self::FIELD_PREFIX . 'ckan_id',
			'type'       => 'text',
			'attributes' => array(
				'readonly' => 'readonly',
			),
		) );

		/* Ckan name */
		$cmb_side_ckan->add_field( array(
			'name'       => __( 'CKAN Name (Slug)', 'ogdch' ),
			'id'         => self::FIELD_PREFIX . 'ckan_name',
			'type'       => 'text',
			'attributes' => array(
				'readonly' => 'readonly',
			),
		) );
	}

	/**
	 * Gets all possible parent organisations from CKAN and returns them in an array.
	 *
	 * @return array All possbile parent organisations
	 */
	public function get_parent_options() {
		$organisations = Ckan_Backend_Helper::get_organisation_form_field_options();
		// remove current organisation from result (current organisation can't be its on parent)
		if ( isset( $_GET['post'] ) ) {
			$current_organisation_name = get_post_meta( $_GET['post'], self::FIELD_PREFIX . 'ckan_name', true );
			if ( array_key_exists( $current_organisation_name, $organisations ) ) {
				unset( $organisations[ $current_organisation_name ] );
			}
		}

		return $organisations;
	}
}
