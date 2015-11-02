<?php
/**
 * Taxonomy ckan-keyword
 *
 * @package CKAN\Backend
 */

/**
 * Class Ckan_Backend_Keyword
 */
abstract class Ckan_Backend_Keyword {
	/**
	 * Constructor
	 */
	public function __construct() {
		$this->register_taxonomy();
	}

	/**
	 * Registers taxonomy
	 */
	public function register_taxonomy() {
		$labels = array(
			'name'          => __( 'Keywords', 'ogdch' ) . ' (' .  $this->get_language_suffix() . ')',
			'singular_name' => __( 'Keyword', 'ogdch' ) . ' (' .  $this->get_language_suffix() . ')',
			'all_items'     => __( 'All Keywords', 'ogdch' ) . ' (' .  $this->get_language_suffix() . ')',
			'edit_item'     => __( 'Edit Keywords', 'ogdch' ) . ' (' .  $this->get_language_suffix() . ')',
			'view_item'     => __( 'View Keyword', 'ogdch' ) . ' (' .  $this->get_language_suffix() . ')',
			'update_item'   => __( 'Update Keyword', 'ogdch' ) . ' (' .  $this->get_language_suffix() . ')',
			'add_new_item'  => __( 'Add New Keyword', 'ogdch' ) . ' (' .  $this->get_language_suffix() . ')',
			'new_item_name' => __( 'New Keyword Name', 'ogdch' ) . ' (' .  $this->get_language_suffix() . ')',
		);

		$capabilities = array(
			'manage_terms' => 'manage_keywords',
			'edit_terms'   => 'edit_keywords',
			'delete_terms' => 'delete_keywords',
			'assign_terms' => 'assign_keywords',
		);

		$args = array(
			'label'                 => __( 'Keywords', 'ogdch' ) . ' (' .  $this->get_language_suffix() . ')',
			'labels'                => $labels,
			'description'           => __( 'Keywords for CKAN datasets', 'ogdch' ),
			'hierarchical'          => false,
			'update_count_callback' => '_update_post_term_count',
			'capabilities'          => $capabilities,
		);

		register_taxonomy(
			$this->get_taxonomy(),
			Ckan_Backend_Local_Dataset::POST_TYPE,
			$args
		);

		register_taxonomy_for_object_type( $this->get_taxonomy(), Ckan_Backend_Local_Dataset::POST_TYPE );
	}

	/**
	 * Returns taxonomy name
	 */
	public abstract function get_taxonomy();

	/**
	 * Returns language suffix of taxonomy
	 */
	public abstract function get_language_suffix();
}