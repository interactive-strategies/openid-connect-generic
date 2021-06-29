<?php
/**
 * Plugin Community Hub data sync class.
 *
 * @package   OpenID_Connect_Generic
 * @license   http://www.gnu.org/licenses/gpl-2.0.txt GPL-2.0+
 */

/**
 * OpenID_Connect_CommunityHub_Sync class.
 *
 * Plugin OIDC/oAuth client class.
 *
 * @package  OpenID_Connect_Generic
 * @category Authentication
 */
class OpenID_Connect_CommunityHub_Sync {

	/**
	 * Check whether Salesforce/CommunityHub sync is enabled.
	 *
	 * @return bool
	 */
	public static function is_sync_enabled(): bool {
		$options = get_option( 'openid_connect_generic_settings', array() );
		return ! empty( $options['nimble_sync_enabled'] );
	}

	/**
	 * Action method for user creation to trigger a Nimble data sync.
	 *
	 * @param WP_User $user
	 * @param array   $user_claim
	 */
	public static function on_user_create( WP_User $user, $user_claim ): void {
		$sync_instance = new static();
		$sync_instance->sync_user_data( $user );
	}

	/**
	 * Action method for when an existing WP user is linked to a Nimble account.
	 *
	 * @param int $uid
	 */
	public static function on_existing_user_connect( int $uid ): void {
		$sync_instance = new static();
		$sync_instance->sync_user_data( $uid );
	}

	/**
	 * Action method for user update to trigger a Nimble data sync.
	 *
	 * @param WP_User $user       The logged in user object.
	 * @param array   $user_claim The OpenID claim object.
	 */
	public static function on_user_update_with_current_claim( WP_User $user, $user_claim ): void {
		$sync_instance = new static();
		$sync_instance->sync_user_data( $user );
	}

	/**
	 * Syncs user data from Community Hub.
	 *
	 * @param mixed $user Either a WP_User object or a user ID.
	 *
	 * @return bool
	 */
	public function sync_user_data( $user ): bool {
		if ( ! $user instanceof WP_User && is_numeric( $user ) ) {
			$user = get_user_by( 'id', $user );
		}

		if ( ! $user instanceof WP_User ) {
			return false;
		}

		$nimble_id = $this->determine_nimble_id( $user );
		if ( empty( $user->nimble_id ) ) {
			return false;
		}

		$account = $this->get_account( $nimble_id );
		if ( ! $account ) {
			return false;
		}

		// TODO: store entire account object?
		// update_user_meta( get_current_user_id(), 'nimble_person', $account );

		$mapping = new OpenID_Connect_Generic_Field_Mapping( 'openid_connect_generic_salesforce_field_mapping' );
		foreach ( $mapping->get_mapping() as $wordpress_field => $value ) {
			$salesforce_field = $value['salesforce_key'] ?? null;
			if ( $salesforce_field && ! empty( $account->$salesforce_field ) ) {
				update_user_meta( $user->ID, $wordpress_field, $account->$salesforce_field );
			}
		}

		return true;
	}

	/**
	 * Get the account object from Nimble based off its Nimble ID.
	 *
	 * @param string $account_id The Salesforce ID for the Nimble account to retrieve.
	 *
	 * @return null|object
	 */
	protected function get_account( string $account_id ) {
		$api_version = $this->get_api_version();

		$curl = $this->init_curl( '/services/data/v' . $api_version . '/sobjects/Account/' . $account_id );
		if ( ! $curl ) {
			return null;
		}
		$result = json_decode( curl_exec( $curl ) );
		$response_code = curl_getinfo( $curl, CURLINFO_RESPONSE_CODE );

		if ( 200 !== $response_code ) {
			if ( isset( $result[0]->errorCode ) && 'INVALID_SESSION_ID' === $result[0]->errorCode ) {
				$re_token_success = $this->set_curl_headers( $curl, true );
				if ( ! $re_token_success ) {
					return null;
				}
				$result = json_decode( curl_exec( $curl ) );
				$response_code = curl_getinfo( $curl, CURLINFO_RESPONSE_CODE );
			}
		}

		curl_close( $curl );
		return 200 === $response_code ? $result : null;
	}

	/**
	 * Get a Salesforce account ID based off a User ID.
	 *
	 * @param string $user_id The Salesforce ID for the User object.
	 *
	 * @return null|object
	 */
	protected function get_account_id_from_user_id( string $user_id ): ?string {
		$api_version = $this->get_api_version();
		$curl = $this->init_curl( '/services/data/v' . $api_version . "/query/?q=SELECT+AccountId+from+User+WHERE+Id+%3d+'" . $user_id . "'" );
		if ( ! $curl ) {
			return null;
		}
		$result = json_decode( curl_exec( $curl ) );
		$response_code = curl_getinfo( $curl, CURLINFO_RESPONSE_CODE );

		if ( 200 !== $response_code ) {
			if ( isset( $result[0]->errorCode ) && 'INVALID_SESSION_ID' === $result[0]->errorCode ) {
				$re_token_success = $this->set_curl_headers( $curl, true );
				if ( ! $re_token_success ) {
					return null;
				}
				$result = json_decode( curl_exec( $curl ) );
				$response_code = curl_getinfo( $curl, CURLINFO_RESPONSE_CODE );
			}
		}

		curl_close( $curl );
		if ( 200 === $response_code && ! empty( $result->records[0]->AccountId ) ) {
			return $result->records[0]->AccountId;
		}
		return null;
	}

	/**
	 * Given a WP user, get their Nimble/Salesforce Account ID if possible.
	 *
	 * If the user's nimble_id meta is not already set, it will be set.
	 *
	 * @param mixed $user Either a WP_User object or a user ID.
	 *
	 * @return null|string
	 *   Null if the user's account ID could not be determined, or the account ID
	 *   if it was already set or could be determined.
	 */
	protected function determine_nimble_id( $user ): ?string {
		if ( ! $user instanceof WP_User && is_numeric( $user ) ) {
			$user = get_user_by( 'id', $user );
		}

		if ( ! $user instanceof WP_User ) {
			return null;
		}

		$nimble_id = $user->get( 'nimble_id' );
		if ( $nimble_id ) {
			return $nimble_id;
		}

		$identity = $user->get( 'openid-connect-generic-subject-identity' );
		if ( $identity ) {
			$user_id = explode( '/', $identity );
			$user_id = end( $user_id );
			if ( $user_id ) {
				$account_id = $this->get_account_id_from_user_id( $user_id );
				if ( $account_id ) {
					$user->nimble_id = $account_id;
					return $account_id;
				}
			}
		}

		return null;
	}

	/**
	 * Prepares a new curl instance for a given Salesforce API URL.
	 *
	 * @param string $url The endpoint URL. Domain may be omitted, and the Salesforce instance domain will be automatically used if URL starts with a slash.
	 *
	 * @return CurlHandle|resource|null
	 */
	protected function init_curl( string $url = null ) {
		$curl = curl_init();
		curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true );
		if ( ! $this->set_curl_headers( $curl ) ) {
			return null;
		}

		if ( $url && substr( $url, 0, 1 ) === '/' ) {
			$instance_url = $this->get_instance_url();
			if ( ! $instance_url ) {
				return null;
			}
			$url = $instance_url . $url;
		}
		if ( $url ) {
			curl_setopt( $curl, CURLOPT_URL, $url );
		}

		return $curl;
	}

	/**
	 * Set headers for a Salesforce API request, including authorization.
	 *
	 * @param CurlHandle|resource $curl
	 * @param bool                $force_new_token if true, obtain a new access token. Defaults to false.
	 *
	 * @return CurlHandle|resource
	 */
	protected function set_curl_headers( $curl, bool $force_new_token = false ): bool {
		$access_token = $this->get_access_token( $force_new_token );
		if ( ! $access_token ) {
			return false;
		}

		return curl_setopt(
			$curl,
			CURLOPT_HTTPHEADER,
			array(
				"Authorization: Bearer {$access_token}",
				'Content-Type: application/json',
			)
		);
	}

	/**
	 * Get an access token for Salesforce API calls.
	 *
	 * @param bool $force_new If true, forces the function to obtain a new token from Salesforce. Default false.
	 *
	 * @return string
	 */
	protected function get_access_token( bool $force_new = false ): string {
		if ( ! $force_new ) {
			$current_token = get_option( 'communityhub_sync_access_token' );
			if ( $current_token ) {
				return $current_token;
			}
		}

		$api_creds = $this->get_api_credentials();
		if ( ! $api_creds ) {
			return '';
		}

		$curl = curl_init();
		curl_setopt_array(
			$curl,
			array(
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_URL            => $api_creds['url'],
				CURLOPT_POST           => true,
				CURLOPT_POSTFIELDS     => http_build_query(
					array(
						'grant_type'    => 'password',
						'client_id'     => $api_creds['client_id'],
						'client_secret' => $api_creds['client_secret'],
						'username'      => $api_creds['username'],
						'password'      => $api_creds['password'],
					)
				),
			)
		);

		$response = curl_exec( $curl );
		$response_code = curl_getinfo( $curl, CURLINFO_RESPONSE_CODE );
		if ( 200 === $response_code ) {
			$response = json_decode( $response );
			if ( ! empty( $response->instance_url ) ) {
				update_option( 'communityhub_sync_instance_url', $response->instance_url, true );
			}
			if ( ! empty( $response->access_token ) ) {
				update_option( 'communityhub_sync_access_token', $response->access_token, true );
				return $response->access_token;
			}
		}

		return '';
	}

	/**
	 * Get the API credentials, if they are all present.
	 *
	 * @return array|null Null if not all credentials are present, or an array if they are containing properties client_id, client_secret, username, password.
	 */
	protected function get_api_credentials(): ?array {
		$options = get_option( 'openid_connect_generic_settings', array() );
		if (
			! empty( $options['nimble_login_url'] ) &&
			! empty( $options['nimble_client_id'] ) &&
			! empty( $options['nimble_client_secret'] ) &&
			! empty( $options['nimble_username'] ) &&
			! empty( $options['nimble_password'] )
		) {
			return array(
				'url' => $options['nimble_login_url'],
				'client_id' => $options['nimble_client_id'],
				'client_secret' => $options['nimble_client_secret'],
				'username' => $options['nimble_username'],
				'password' => $options['nimble_password'],
			);
		}

		return null;
	}

	/**
	 * Get the version of the Salesforce API that should be used.
	 */
	protected function get_api_version(): string {
		// TODO: make configurable?
		return '46.0';
	}

	/**
	 * Get the current Salesforce instance URL.
	 */
	protected function get_instance_url(): string {
		$instance_url = get_option( 'communityhub_sync_instance_url', '' );
		return $instance_url;
	}

}

if ( OpenID_Connect_CommunityHub_Sync::is_sync_enabled() ) {
	add_action(
		'openid-connect-generic-user-create',
		'OpenID_Connect_CommunityHub_Sync::on_user_create',
		0,
		2
	);

	add_action(
		'openid-connect-generic-user-update',
		'OpenID_Connect_CommunityHub_Sync::on_existing_user_connect',
		0,
		1
	);

	add_action(
		'openid-connect-generic-update-user-using-current-claim',
		'OpenID_Connect_CommunityHub_Sync::on_user_update_with_current_claim',
		0,
		2
	);
}
