<?php
/**
 * Plugin Admin Salesforce field mapping page class.
 *
 * @package   OpenID_Connect_Generic
 * @category  Settings
 * @license   http://www.gnu.org/licenses/gpl-2.0.txt GPL-2.0+
 */

/**
 * OpenID_Connect_Generic_Mapping_Page class.
 *
 * Admin salesforce field mapping page.
 *
 * @package OpenID_Connect_Generic
 * @category  Settings
 */
class OpenID_Connect_Generic_Mapping_Page {

	/**
	 * Local copy of the field mapping settings.
	 *
	 * @var OpenID_Connect_Generic_Field_Mapping
	 */
	private $mapping = array();

	/**
	 * Options page slug.
	 *
	 * @var string
	 */
	private $options_page_name = 'openid-connect-generic-mapping';

	/**
	 * Options page settings group name.
	 *
	 * @var string
	 */
	private $settings_field_group = 'openid_connect_generic_salesforce_mapping';

	/**
	 * The field name for the mapping array "field."
	 *
	 * @var string
	 */
	private $mapping_field_name = 'openid_connect_salesforce_field_mapping';

	/**
	 * Settings page class constructor.
	 *
	 * @param OpenID_Connect_Generic_Field_Mapping $mapping
	 */
	public function __construct( OpenID_Connect_Generic_Field_Mapping $mapping ) {
		$this->mapping = $mapping;
	}

	/**
	 * Hook the settings page into WordPress.
	 *
	 * @param OpenID_Connect_Generic_Field_Mapping $mapping A plugin settings object instance.
	 *
	 * @return void
	 */
	public static function register( OpenID_Connect_Generic_Field_Mapping $mapping ): void {
		$settings_page = new self( $mapping );

		// Add our options page the the admin menu.
		add_action( 'admin_menu', array( $settings_page, 'admin_menu' ) );

		// Register our settings.
		add_action( 'admin_init', array( $settings_page, 'admin_init' ) );
	}

	/**
	 * Implements hook admin_menu to add our options/settings page to the
	 *  dashboard menu.
	 *
	 * @return void
	 */
	public function admin_menu(): void {
		add_options_page(
			__( 'OpenID Connect - Salesforce Fields Mapping', 'daggerhart-openid-connect-generic' ),
			__( 'OpenID Connect Field Mapping', 'daggerhart-openid-connect-generic' ),
			'manage_options',
			$this->options_page_name,
			array( $this, 'settings_page' )
		);
	}

	/**
	 * Implements hook admin_init to register our settings.
	 *
	 * @return void
	 */
	public function admin_init(): void {
		register_setting(
			$this->settings_field_group,
			$this->mapping->get_option_name(),
			array(
				$this,
				'format_settings',
			)
		);

		add_settings_section(
			'mapping_settings',
			__( 'Field Mapping Settings', 'daggerhart-openid-connect-generic' ),
			array( $this, 'mapping_settings_description' ),
			$this->options_page_name
		);

		add_settings_field(
			$this->mapping_field_name,
			__( 'Salesforce Field Mapping', 'daggerhart-openid-connect-generic' ),
			array( $this, 'do_mapping_fields' ),
			$this->options_page_name,
			'mapping_settings'
		);
	}

	/**
	 * Formatting and sanitization callback for settings/option page.
	 *
	 * @param array $input The submitted settings values.
	 *
	 * @return array
	 */
	public function format_settings( $input ): array {
		$mapping = array();

		if (
			! isset( $input[ $this->mapping_field_name ] ) ||
		  ! is_array( $input[ $this->mapping_field_name ] )
		) {
			return $mapping;
		}

		foreach ( $input[ $this->mapping_field_name ] as $keys ) {
			foreach ( array( 'wordpress_key', 'salesforce_key' ) as $key_name ) {
				if ( ! isset( $keys[ $key_name ] ) ) {
					continue 2;
				}
				$keys[ $key_name ] = sanitize_text_field( trim( $keys[ $key_name ] ) );
				if ( ! $keys[ $key_name ] ) {
					continue 2;
				}
			}

			$mapping[ $keys['wordpress_key'] ] = array(
				'salesforce_key' => $keys['salesforce_key'],
			);
		}

		return $mapping;
	}

	/**
	 * Output the options/settings page.
	 *
	 * @return void
	 */
	public function settings_page(): void {
		?>
		<div class="wrap">
			<h2><?php print esc_html( get_admin_page_title() ); ?></h2>

			<form method="post" action="options.php">
				<?php
				settings_fields( $this->settings_field_group );
				do_settings_sections( $this->options_page_name );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Output all rows for the Salesforce field mapping.
	 *
	 * @return void
	 */
	public function do_mapping_fields(): void {
		$index = 0;
		foreach ( $this->mapping->get_mapping() as $wordpress_key => $value ) {
			$this->do_mapping_field( $index, $wordpress_key, $value );
			$index++;
		}
		// Always output a blank row at the end.
		$this->do_mapping_field( $index );
	}

	/**
	 * Output a row for the Salesforce field mapping.
	 *
	 * @param int    $index         The numeric index of this row in the field mapping.
	 * @param string $wordpress_key (Optional) The WordPress user meta key, if this is an existing row.
	 * @param array  $value         (Optional) The values associated with this user meta key, if this is an existing row.
	 */
	public function do_mapping_field( int $index, string $wordpress_key = '', array $value = array() ): void {
		$option_name = $this->mapping->get_option_name();
		$wp_id = "{$option_name}[{$this->mapping_field_name}][{$index}][wordpress_key]";
		$sf_id = "{$option_name}[{$this->mapping_field_name}][{$index}][salesforce_key]";
		?>
		<div style="margin-bottom: 16px;">
			<label for="<?php print esc_attr( $wp_id ); ?>"><?php esc_html_e( 'WordPress Key', 'daggerhart-openid-connect-generic' ); ?></label>
			<input type="text"
					id="<?php print esc_attr( $wp_id ); ?>"
					class="large-text"
					name="<?php print esc_attr( $wp_id ); ?>"
					value="<?php print esc_attr( $wordpress_key ); ?>"
			>

			<label for="<?php print esc_attr( $sf_id ); ?>"><?php esc_html_e( 'SalesForce Key', 'daggerhart-openid-connect-generic' ); ?></label>
			<input type="text"
					id="<?php print esc_attr( $sf_id ); ?>"
					class="large-text"
					name="<?php print esc_attr( $sf_id ); ?>"
					value="<?php print esc_attr( $value['salesforce_key'] ?? '' ); ?>"
			>
		</div>
		<?php
	}

	/**
	 * Output the 'Mapping Settings' plugin setting section description.
	 *
	 * @return void
	 */
	public function mapping_settings_description(): void {
		esc_html_e( 'Enter your OpenID Connect identity provider settings.', 'daggerhart-openid-connect-generic' );
	}

}
