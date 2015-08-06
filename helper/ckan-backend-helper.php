<?php
/**
 * Helper function for this plugin
 *
 * @package CKAN\Backend
 */

/**
 * Class Ckan_Backend_Helper
 */
class Ckan_Backend_Helper {
	/**
	 * Sends a curl request with given data to specified CKAN endpoint.
	 *
	 * @param string $endpoint CKAN API endpoint which gets called.
	 * @param string $data JSON-encoded data to send.
	 *
	 * @return array The CKAN data as array
	 */
	public static function do_api_request( $endpoint, $data = '' ) {
		if ( is_array( $data ) ) {
			$data = wp_json_encode( $data );
		}

		$ch = curl_init( $endpoint );
		curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, 'POST' );
		curl_setopt( $ch, CURLOPT_POSTFIELDS, $data );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch, CURLOPT_HTTPHEADER, array( 'Authorization: ' . CKAN_API_KEY ) );

		// send request
		$response = curl_exec( $ch );
		$response = json_decode( $response, true );

		curl_close( $ch );

		return $response;
	}

	/**
	 * Validates CKAN API response
	 *
	 * @param array $response The JSON-decoded response from the CKAN API.
	 *
	 * @return array An Array with error messages if there where any.
	 */
	public static function check_response_for_errors( $response ) {
		$errors = array();
		if ( ! is_array( $response ) ) {
			$errors[] = 'There was a problem sending the request.';
		}

		if ( isset( $response['success'] ) && false === $response['success'] ) {
			if ( isset( $response['error'] ) && isset( $response['error']['message'] ) ) {
				$errors[] = $response['error']['message'];
			} else if ( isset( $response['error'] ) && isset( $response['error']['name'] ) && is_array( $response['error']['name'] ) ) {
				$errors[] = $response['error']['name'][0];
			} else if ( isset( $response['error'] ) && isset( $response['error']['id'] ) && is_array( $response['error']['id'] ) ) {
				$errors[] = $response['error']['id'][0];
			} else {
				$errors[] = 'API responded with unknown error.';
			}
		}

		return $errors;
	}

	/**
	 * Gets all group instances from CKAN and returns them in an array.
	 *
	 * @return array All group instances from CKAN
	 */
	public static function get_group_form_field_options() {
		return Ckan_Backend_Helper::get_form_field_options( 'group' );
	}

	/**
	 * Gets all organisation instances from CKAN and returns them in an array.
	 *
	 * @return array All organisation instances from CKAN
	 */
	public static function get_organisation_form_field_options() {
		return Ckan_Backend_Helper::get_form_field_options( 'organization' );
	}

	/**
	 * Gets all instances of given type from CKAN and returns them in an array.
	 *
	 * @param string $type Name of a CKAN type.
	 *
	 * @return array All instances from CKAN
	 */
	private static function get_form_field_options( $type ) {
		$available_types = array(
			'group',
			'organization',
		);
		if ( ! in_array( $type, $available_types ) ) {
			self::print_error_messages( array( 'Type not available!' ) );

			return false;
		}

		$options  = array();
		$endpoint = CKAN_API_ENDPOINT . 'action/' . $type . '_list';
		$data     = array(
			'all_fields' => true,
		);
		$data     = wp_json_encode( $data );

		$response = Ckan_Backend_Helper::do_api_request( $endpoint, $data );
		$errors   = Ckan_Backend_Helper::check_response_for_errors( $response );
		self::print_error_messages( $errors );

		foreach ( $response['result'] as $instance ) {
			$options[ $instance['name'] ] = $instance['title'];
		}

		return $options;
	}

	/**
	 * Checks if the group exsits.
	 *
	 * @param string $name The name of the group.
	 *
	 * @return bool
	 */
	public static function group_exists( $name ) {
		return Ckan_Backend_Helper::object_exists( 'group', $name );
	}

	/**
	 * Checks if the organization exists
	 *
	 * @param string $name The name of the organization.
	 *
	 * @return bool
	 */
	public static function organisation_exists( $name ) {
		return Ckan_Backend_Helper::object_exists( 'organization', $name );
	}

	/**
	 * Check if the object exists
	 *
	 * @param string $type Name of a CKAN type.
	 * @param string $name Name of the object.
	 *
	 * @return bool
	 */
	private static function object_exists( $type, $name ) {
		$available_types = array(
			'group',
			'organization',
		);
		if ( ! in_array( $type, $available_types ) ) {
			self::print_error_messages( array( 'Type not available!' ) );

			return false;
		}

		$endpoint = CKAN_API_ENDPOINT . 'action/' . $type . '_show';
		$data     = array(
			'id' => $name,
		);
		$data     = wp_json_encode( $data );
		$response = Ckan_Backend_Helper::do_api_request( $endpoint, $data );
		$errors   = Ckan_Backend_Helper::check_response_for_errors( $response );

		return count( $errors ) === 0;
	}

	/**
	 * Displays all admin notices
	 *
	 * @param array $errors Array of errors.
	 *
	 * @return string
	 */
	public static function print_error_messages( $errors ) {
		//print the message
		if ( is_array( $errors ) && count( $errors ) > 0 ) {
			foreach ( $errors as $key => $m ) {
				echo '<div class="error"><p>' . esc_html( $m ) . '</p></div>';
			}
		}

		return true;
	}

	/**
	 * Checks if a string starts with a given needle
	 *
	 * @param string $haystack String to search in.
	 * @param string $needle String to look for.
	 *
	 * @return bool
	 */
	public static function starts_with( $haystack, $needle ) {
		return '' === $needle || strrpos( $haystack, $needle, -strlen( $haystack ) ) !== false;
	}

	/**
	 * Returns metafield value from $_POST if available. Otherwise returns value from database.
	 *
	 * @param int    $post_id ID of current post.
	 * @param string $field_name Name of metafield.
	 *
	 * @return mixed
	 */
	public static function get_value_for_metafield( $post_id, $field_name ){
		if( isset( $_POST[$field_name] ) ) {
			return $_POST[$field_name];
		} else {
			return get_post_meta( $post_id, $field_name, true );
		}
	}
}
