<?php

class Ckan_Backend_Local_Organisation {

	// Be careful max. 20 characters allowed!
	const POST_TYPE = 'ckan-local-org';
	const FIELD_PREFIX = '_ckan_local_org_';

	public function __construct() {
		add_action( 'init', array( $this, 'register_post_type' ), 0 );
		add_action( 'cmb2_init', array( $this, 'define_fields' ) );
		// initialize local organisation sync
		$ckan_backend_sync_local_organisation = new Ckan_Backend_Sync_Local_Organisation();
	}

	public function register_post_type() {
		$labels = array(
			'name'               => __( 'CKAN local Organisations', 'ogdch' ),
			'singular_name'      => __( 'CKAN local Organisation', 'ogdch' ),
			'menu_name'          => __( 'CKAN local Organisation', 'ogdch' ),
			'name_admin_bar'     => __( 'CKAN local Organisation', 'ogdch' ),
			'parent_item_colon'  => __( 'Parent Organisation:', 'ogdch' ),
			'all_items'          => __( 'All local Organisations', 'ogdch' ),
			'add_new_item'       => __( 'Add New Organisation', 'ogdch' ),
			'add_new'            => __( 'Add New', 'ogdch' ),
			'new_item'           => __( 'New local Organisation', 'ogdch' ),
			'edit_item'          => __( 'Edit local Organisation', 'ogdch' ),
			'update_item'        => __( 'Update local Organisation', 'ogdch' ),
			'view_item'          => __( 'View Organisation', 'ogdch' ),
			'search_items'       => __( 'Search Organisation', 'ogdch' ),
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
			'capability_type'     => 'page',
		);
		register_post_type( self::POST_TYPE, $args );
	}

	public function define_fields() {
		global $language_priority;

		$cmb = new_cmb2_box( array(
			'id'           => self::POST_TYPE . '-box',
			'title'        => __( 'Organisation Data', 'ogdch' ),
			'object_types' => array( self::POST_TYPE, ),
			'context'      => 'normal',
			'priority'     => 'high',
			'show_names'   => true,
		) );

		/* Visibility */
		$cmb->add_field( array(
			'name'    => __( 'Visibility', 'ogdch' ),
			'desc'    => __( 'Select the visibility of the Dataset', 'ogdch' ),
			'id'      => self::FIELD_PREFIX . 'visibility',
			'type'    => 'radio',
			'default' => 'active',
			'options' => array(
				'active'  => __( 'Active', 'ogdch' ),
				'deleted' => __( 'Deleted', 'ogdch' ),
			),
		) );

		/* Title */
		$cmb->add_field( array(
			'name' => __( 'Organisation Title', 'ogdch' ),
			'type' => 'title',
			'id'   => 'title_title'
		) );

		foreach ( $language_priority as $lang ) {
			$cmb->add_field( array(
				'name'       => __( 'Title', 'ogdch' ) . ' (' . strtoupper( $lang ) . ')',
				'id'         => self::FIELD_PREFIX . 'name_' . $lang,
				'type'       => 'text',
				'attributes' => array(
					'placeholder' => __( 'e.g. Awesome dataset', 'ogdch' )
				)
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
			'options'          => array($this, 'get_parent_options'),
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
			'type' => 'file'
		) );

		$cmb_side = new_cmb2_box( array(
			'id'           => self::POST_TYPE . '-sidebox',
			'title'        => __( 'CKAN Data', 'ogdch' ),
			'object_types' => array( self::POST_TYPE, ),
			'context'      => 'side',
			'priority'     => 'low',
			'show_names'   => true,
		) );

		/* CKAN Ref ID (If Set.. update.. set on first save) */
		$cmb_side->add_field( array(
			'name'       => __( 'Reference ID', 'ogdch' ),
			'id'         => self::FIELD_PREFIX . 'reference',
			'type'       => 'text',
			'attributes' => array(
				'readonly' => 'readonly',
			),
		) );

		/* Permalink */
		$cmb_side->add_field( array(
			'name'       => __( 'Name (Slug)', 'ogdch' ),
			'id'         => self::FIELD_PREFIX . 'name',
			'type'       => 'text',
			'attributes' => array(
				'readonly'    => 'readonly',
			),
		) );
	}

	/**
	 * Gets all possible parent organisations from CKAN and returns them in an array.
	 *
	 * @return array All possbile parent organisations
	 */
	public function get_parent_options() {
		$organisations = Ckan_Backend_Helper::get_form_field_options('organization');
		// remove current organisation from result (current organisation can't be its on parent)
		if(isset($_GET['post'])) {
			$current_organisation_name = get_post_meta($_GET['post'], Ckan_Backend_Local_Organisation::FIELD_PREFIX . 'name', true);
			if(array_key_exists($current_organisation_name, $organisations)) {
				unset($organisations[$current_organisation_name]);
			}
		}
		return $organisations;
	}


}