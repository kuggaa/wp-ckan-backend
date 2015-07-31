<?php
/**
 * Post type ckan-local-dataset-import-page
 *
 * @package CKAN\Backend
 */

/**
 * Class Ckan_Backend_Local_Dataset_Import
 */
class Ckan_Backend_Local_Dataset_Import {

	/**
	 * Menu slug.
	 * @var string
	 */
	public $menu_slug = 'ckan-local-dataset-import-page';

	/**
	 * Constructor of this class.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_submenu_page' ) );
	}

	/**
	 * Register a submenu page.
	 *
	 * @return void
	 */
	public function register_submenu_page() {
		add_submenu_page(
			'edit.php?post_type=' . Ckan_Backend_Local_Dataset::POST_TYPE,
			__( 'Import CKAN Dataset', 'ogdch' ),
			__( 'Import', 'ogdch' ),
			'manage_options',
			$this->menu_slug,
			array( $this, 'import_page_callback' )
		);
	}

	/**
	 * Callback for the import of a file.
	 *
	 * @return void
	 */
	public function import_page_callback() {
		// must check that the user has the required capability
		if ( ! current_user_can( 'create_datasets' ) ) {
			wp_die( esc_html( __( 'You do not have sufficient permissions to access this page.' ) ) );
		}

		$import_submit_hidden_field_name = 'ckan_local_dataset_import_submit';
		$file_field_name                 = 'ckan_local_dataset_import_file';

		// Handle import
		if ( isset( $_POST[ $import_submit_hidden_field_name ] ) && 'Y' === $_POST[ $import_submit_hidden_field_name ] ) {
			$dataset_id = false;
			if ( isset( $_FILES[ $file_field_name ] ) ) {
				$dataset_id = $this->handle_file_import( $_FILES[ $file_field_name ] );
			}

			if ( $dataset_id > 0 ) {
				echo '<div class="updated"><p><strong>' . esc_html( __( 'Import successful', 'ogdch' ) ) . '</strong></p></div>';
				// @codingStandardsIgnoreStart
				printf( __( 'Click <a href="%s">here</a> to see the imported dataset.', 'ogdch' ), esc_url( admin_url( 'post.php?post=' . esc_attr( $dataset_id ) . '&action=edit' ) ) );
				// @codingStandardsIgnoreEnd
			}
		} ?>
		<div class="wrap">
			<h2><?php esc_html_e( __( 'Import CKAN Dataset', 'ogdch' ) ); ?></h2>

			<form enctype="multipart/form-data" action="" method="POST">
				<input type="hidden" name="<?php esc_attr_e( $import_submit_hidden_field_name ); ?>" value="Y">

				<p><?php esc_html_e( __( 'File:', 'ogdch' ) ); ?>
					<input type="file" name="<?php esc_attr_e( $file_field_name ); ?>" value="" size="20">
				</p>
				<hr/>

				<p class="submit">
					<input type="submit" name="Submit" class="button-primary" value="<?php esc_attr_e( 'Import' ) ?>"/>
				</p>
			</form>
		</div>

		<?php
	}

	/**
	 * Handle to uploaded file.
	 *
	 * @param array $file Array with the information of the uploaded file.
	 *
	 * @return bool|int|WP_Error
	 *
	 * @throws RuntimeException If the file cannot be processed.
	 */
	public function handle_file_import( $file ) {
		try {
			// Undefined | Multiple Files | $_FILES Corruption Attack
			// If this request falls under any of them, treat it invalid.
			if (
				! isset( $file['error'] ) ||
				is_array( $file['error'] )
			) {
				throw new RuntimeException( 'Invalid parameters.' );
			}

			// Check $file['error'] value.
			switch ( $file['error'] ) {
				case UPLOAD_ERR_OK:
					break;
				case UPLOAD_ERR_NO_FILE:
					throw new RuntimeException( 'No file sent.' );
				case UPLOAD_ERR_INI_SIZE:
				case UPLOAD_ERR_FORM_SIZE:
					throw new RuntimeException( 'Exceeded filesize limit.' );
				default:
					throw new RuntimeException( 'Unknown errors.' );
			}

			$xml = simplexml_load_file( $file['tmp_name'] );
			if ( ! $xml ) {
				throw new RuntimeException( 'Uploaded file is not a vaild XML file' );
			}

			return $this->import_dataset( $xml );
		} catch ( RuntimeException $e ) {
			esc_html_e( $e->getMessage() );
		}
	}

	/**
	 * Imports a dataset from a given XML
	 *
	 * @param string $xml The XML to be imported.
	 *
	 * @return bool|int|WP_Error
	 */
	public function import_dataset( $xml ) {
		foreach ( $xml->groups->group as $group ) {
			if ( ! Ckan_Backend_Helper::group_exists( (string) $group ) ) {
				echo '<div class="error"><p>';
				// @codingStandardsIgnoreStart
				printf( __( 'Group %1$s does not exist! Import aborted.', 'ogdch' ), (string) $group );
				// @codingStandardsIgnoreEnd
				echo '</p></div>';

				return false;
			}
		}

		if ( ! Ckan_Backend_Helper::organisation_exists( (string) $xml->owner_org ) ) {
			echo '<div class="error"><p>';
			// @codingStandardsIgnoreStart
			printf( __( 'Organisation %1$s does not exist! Import aborted.', 'ogdch' ), (string) $xml->owner_org );
			// @codingStandardsIgnoreEnd
			echo '</p></div>';

			return false;
		}

		$custom_fields = $this->prepare_custom_fields( $xml );
		$resources = $this->prepare_resources( $xml );
		$groups = $this->prepare_groups( $xml );

		$this->prepare_post_fields( $xml, $custom_fields, $resources, $groups );

		$dataset_search_args = array(
			// @codingStandardsIgnoreStart
			'meta_key'    => Ckan_Backend_Local_Dataset::FIELD_PREFIX . 'masterid',
			'meta_value'  => (string) $xml->masterid,
			// @codingStandardsIgnoreEnd
			'post_type'   => Ckan_Backend_Local_Dataset::POST_TYPE,
			'post_status' => 'any',
		);
		$datasets            = get_posts( $dataset_search_args );

		if ( count( $datasets ) > 0 ) {
			// Dataset already exists -> update
			$dataset_id = $datasets[0]->ID;
			$this->update( $dataset_id, $xml, $custom_fields, $resources, $groups );
		} else {
			// Create new dataset
			$dataset_id = $this->insert( $xml, $custom_fields, $resources, $groups );
		}

		return $dataset_id;
	}

	/**
	 * Extracts the custom fields as key/values
	 *
	 * @param string $xml The XML to import.
	 *
	 * @return array
	 */
	protected function prepare_custom_fields( $xml ) {
		$custom_fields = array();
		foreach ( $xml->custom_fields->custom_field as $custom_field ) {
			$custom_fields[] = array(
				'key' => (string) $custom_field->key,
				'value' => (string) $custom_field->value,
			);
		}
		return $custom_fields;
	}

	/**
	 * Extracts the resources from the given XML
	 *
	 * @param string $xml The XML to import.
	 *
	 * @return array
	 */
	protected function prepare_resources( $xml ) {
		$resources = array();
		foreach ( $xml->resources->resource as $resource ) {
			$resources[] = array(
				'url' => (string) $resource->url,
				'title' => (string) $resource->title,
				'description_de' => (string) $resource->description,
			);
		}
		return $resources;
	}

	/**
	 * Extracts the groups from the given XML
	 *
	 * @param string $xml The XML to import.
	 *
	 * @return array
	 */
	protected function prepare_groups( $xml ) {
		$groups = array();
		foreach ( $xml->groups->group as $group ) {
			$groups[] = (string) $group;
		}
		return $groups;
	}

	/**
	 * Description of prepare_post_fields.
	 *
	 * @param string $xml           The XML to import.
	 * @param array  $custom_fields Array of custom fields.
	 * @param array  $resources     Array of resources.
	 * @param array  $groups        Array of groups.
	 *
	 * @return void
	 */
	protected function prepare_post_fields($xml, $custom_fields, $resources, $groups) {
		// simulate $_POST data to make post_save hook work correctly
		$_POST[ Ckan_Backend_Local_Dataset::FIELD_PREFIX . 'custom_fields' ]    = $custom_fields;
		$_POST[ Ckan_Backend_Local_Dataset::FIELD_PREFIX . 'resources' ]        = $resources;
		$_POST[ Ckan_Backend_Local_Dataset::FIELD_PREFIX . 'groups' ]           = $groups;
		$_POST[ Ckan_Backend_Local_Dataset::FIELD_PREFIX . 'name' ]             = (string) $xml->name;
		$_POST['post_title']                                                    = (string) $xml->title;
		$_POST[ Ckan_Backend_Local_Dataset::FIELD_PREFIX . 'maintainer' ]       = (string) $xml->maintainer;
		$_POST[ Ckan_Backend_Local_Dataset::FIELD_PREFIX . 'maintainer_email' ] = (string) $xml->maintainer_email;
		$_POST[ Ckan_Backend_Local_Dataset::FIELD_PREFIX . 'author' ]           = (string) $xml->author;
		$_POST[ Ckan_Backend_Local_Dataset::FIELD_PREFIX . 'author_email' ]     = (string) $xml->author_email;
		$_POST[ Ckan_Backend_Local_Dataset::FIELD_PREFIX . 'description_de' ]   = (string) $xml->description_de;
		$_POST[ Ckan_Backend_Local_Dataset::FIELD_PREFIX . 'version' ]          = (string) $xml->version;
		$_POST[ Ckan_Backend_Local_Dataset::FIELD_PREFIX . 'organisation' ]     = (string) $xml->owner_org;
	}

	/**
	 * Updated the dataset with the given ID
	 *
	 * @param integer $dataset_id    ID of the dataset.
	 * @param string  $xml           The XML to import.
	 * @param array   $custom_fields Array of custom fields.
	 * @param array   $resources     Array of resources.
	 * @param array   $groups        Array of groups.
	 *
	 * @return void
	 */
	protected function update( $dataset_id, $xml, $custom_fields, $resources, $groups ) {
		$_POST[ Ckan_Backend_Local_Dataset::FIELD_PREFIX . 'disabled' ]  = get_post_meta( $dataset_id, Ckan_Backend_Local_Dataset::FIELD_PREFIX . 'disabled', true );
		$_POST[ Ckan_Backend_Local_Dataset::FIELD_PREFIX . 'reference' ] = get_post_meta( $dataset_id, Ckan_Backend_Local_Dataset::FIELD_PREFIX . 'reference', true );

		$dataset_args = array(
			'ID'         => $dataset_id,
			'post_name'  => (string) $xml->name,
			'post_title' => (string) $xml->title,
		);

		wp_update_post( $dataset_args );

		// manually update all dataset metafields
		update_post_meta( $dataset_id, Ckan_Backend_Local_Dataset::FIELD_PREFIX . 'name_de', (string) $xml->title );
		update_post_meta( $dataset_id, Ckan_Backend_Local_Dataset::FIELD_PREFIX . 'description_de', (string) $xml->description_de );
		update_post_meta( $dataset_id, Ckan_Backend_Local_Dataset::FIELD_PREFIX . 'custom_fields', $custom_fields );
		update_post_meta( $dataset_id, Ckan_Backend_Local_Dataset::FIELD_PREFIX . 'resources', $resources );
		update_post_meta( $dataset_id, Ckan_Backend_Local_Dataset::FIELD_PREFIX . 'groups', $groups );
		update_post_meta( $dataset_id, Ckan_Backend_Local_Dataset::FIELD_PREFIX . 'organisation', (string) $xml->owner_org );
	}

	/**
	 * Inserts a new dataset
	 *
	 * @param string $xml           The XML to import.
	 * @param array  $custom_fields Array of custom fields.
	 * @param array  $resources     Array of resources.
	 * @param array  $groups        Array of groups.
	 *
	 * @return int|WP_Error
	 */
	protected function insert( $xml, $custom_fields, $resources, $groups ) {
		$_POST[ Ckan_Backend_Local_Dataset::FIELD_PREFIX . 'disabled' ] = '';

		$dataset_args = array(
			'post_name'    => (string) $xml->name,
			'post_title'   => (string) $xml->title,
			'post_status'  => 'publish',
			'post_type'    => Ckan_Backend_Local_Dataset::POST_TYPE,
			'post_excerpt' => '',
		);

		$dataset_id = wp_insert_post( $dataset_args );

		// manually insert all dataset metafields
		add_post_meta( $dataset_id, Ckan_Backend_Local_Dataset::FIELD_PREFIX . 'name_de', (string) $xml->title, true );
		add_post_meta( $dataset_id, Ckan_Backend_Local_Dataset::FIELD_PREFIX . 'description_de', (string) $xml->description_de, true );
		add_post_meta( $dataset_id, Ckan_Backend_Local_Dataset::FIELD_PREFIX . 'custom_fields', $custom_fields, true );
		add_post_meta( $dataset_id, Ckan_Backend_Local_Dataset::FIELD_PREFIX . 'resources', $resources, true );
		add_post_meta( $dataset_id, Ckan_Backend_Local_Dataset::FIELD_PREFIX . 'groups', $groups, true );
		add_post_meta( $dataset_id, Ckan_Backend_Local_Dataset::FIELD_PREFIX . 'organisation', (string) $xml->owner_org, true );

		return $dataset_id;
	}
}
