<?php
/**
 * Field mapping data class.
 *
 * @package   OpenID_Connect_Generic
 * @category  Settings
 * @license   http://www.gnu.org/licenses/gpl-2.0.txt GPL-2.0+
 */

/**
 * OpenId_Connect_Generic_Option_Mapping class.
 *
 * Field mapping data class.
 *
 * @package OpenID_Connect_Generic
 * @category  Settings
 */
class OpenID_Connect_Generic_Field_Mapping {

	/**
	 * WordPress option name/key.
	 *
	 * @var string
	 */
	private $option_name = '';

	/**
	 * Stored mapping array.
	 *
	 * @var array<string,array>
	 */
	private $mapping = array();

	/**
	 * The class constructor.
	 *
	 * @param string $option_name The option name/key.
	 */
	public function __construct( $option_name ) {
		$this->option_name = $option_name;

		if ( ! empty( $this->option_name ) ) {
			$this->mapping = (array) get_option( $this->option_name, array() );
		}
	}

	/**
	 * Magic getter for settings.
	 *
	 * @param string $key The array key/option name.
	 *
	 * @return mixed
	 */
	public function __get( $key ) {
		if ( isset( $this->mapping[ $key ] ) ) {
			return $this->mapping[ $key ];
		}
	}

	/**
	 * Magic setter for mapping.
	 *
	 * @param string $wordpress_key  The WordPress user meta key to set.
	 * @param array $value Associated data such as the Salesforce API field key.
	 *
	 * @return void
	 */
	public function __set( string $wordpress_key, array $value ) {
		$this->mapping[ $wordpress_key ] = $value;
	}

	/**
	 * Magic method to check is an attribute isset.
	 *
	 * @param string $key The WordPress user meta key.
	 *
	 * @return bool
	 */
	public function __isset( string $key ) {
		return isset( $this->mapping[ $key ] );
	}

	/**
	 * Magic method to clear an attribute.
	 *
	 * @param string $key The WordPress user meta key.
	 *
	 * @return void
	 */
	public function __unset( $key ) {
		unset( $this->mapping[ $key ] );
	}

	/**
	 * Get the field mapping array.
	 *
	 * @return array
	 */
	public function get_mapping() {
		return $this->mapping;
	}

	/**
	 * Get the plugin WordPress options name.
	 *
	 * @return string
	 */
	public function get_option_name() {
		return $this->option_name;
	}

	/**
	 * Save the plugin options to the WordPress options table.
	 *
	 * @return void
	 */
	public function save() {

		update_option( $this->option_name, $this->mapping );

	}
}
