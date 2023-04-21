<?php
/**
 * WC_Settings_Tab_LINEPay_TW setting tab file
 *
 * @package linepay_tw
 */

defined( 'ABSPATH' ) || exit;

/**
 * Settings class.
 */
class WC_Settings_Tab_LINEPay_TW extends WC_Settings_Page {
	/**
	 * Setting constructor.
	 */
	public function __construct() {

		$this->id    = 'linepay-tw';
		$this->label = __( 'LINE Pay TW', 'woo-linepay-tw' );

		add_action( 'woocommerce_settings_' . $this->id, array( $this, 'output' ) );
		add_action( 'woocommerce_settings_save_' . $this->id, array( $this, 'save' ) );
		add_action( 'woocommerce_sections_' . $this->id, array( $this, 'output_sections' ) );

		parent::__construct();
	}

	/**
	 * Get setting sections
	 *
	 * @return array
	 */
	public function get_sections() {

		$sections = array(
			'' => __( 'Payment Settings', 'woo-linepay-tw' ),
		);

		return apply_filters( 'woocommerce_get_sections_' . $this->id, $sections );
	}


	/**
	 * Get all the settings for this plugin for @see woocommerce_admin_fields() function.
	 *
	 * @param string $current_section The current section name.
	 * @return array Array of settings for @see woocommerce_admin_fields() function.
	 */
	public function get_settings( $current_section = '' ) {

			$settings = apply_filters(
				'linepay_tw_payment_settings',
				array(
					array(
						'title' => __( 'General Payment Settings', 'woo-linepay-tw' ),
						'type'  => 'title',
						'id'    => 'linepay_tw_general_setting',
					),
					array(
						'title'   => __( 'Debug Log', 'woo-linepay-tw' ),
						'type'    => 'checkbox',
						'default' => 'no',
						'desc'    => sprintf( __( 'Log LINE Pay payment message, inside <code>%s</code>', 'woo-linepay-tw' ), wc_get_log_file_path( 'wpbr-linepay-tw' ) ),
						'id'      => 'linepay_tw_debug_log_enabled',
					),
					array(
						'title'   => __( 'Display Logo', 'woo-linepay-tw' ),
						'type'    => 'checkbox',
						'default' => 'no',
						'desc'    => __( 'Display logo on checkout page', 'woo-linepay-tw' ),
						'id'      => 'linepay_tw_display_logo_enabled',
					),
					array(
						'type' => 'sectionend',
						'id'   => 'linepay_tw_general_setting',
					),
					array(
						'title' => __( 'API Settings', 'woo-linepay-tw' ),
						'type'  => 'title',
						'desc'  => __( 'Enter your LINE Pay API credentials', 'woo-linepay-tw' ),
						'id'    => 'linepay_tw_api_settings',
					),
					array(
						'title'   => __( 'Sandbox Mode', 'woo-linepay-tw' ),
						'type'    => 'checkbox',
						'label'   => __( 'Enable sandbox mode.', 'woo-linepay-tw' ),
						'desc'    => '',
						'default' => 'no',
						'desc'    => __( 'When enabled, you need to use the test-only data below.', 'woo-linepay-tw' ),
						'id'      => 'linepay_tw_sandboxmode_enabled',
					),
					array(
						'title'    => __( 'Sandbox Channel ID', 'woo-linepay-tw' ),
						'type'     => 'text',
						'desc'     => __( 'Enter your Channel ID.', 'woo-linepay-tw' ),
						'desc_tip' => true,
						'default'  => '',
						'id'       => 'linepay_tw_sandbox_channel_id',
					),
					array(
						'title'    => __( 'Sandbox Channel Secret Key', 'woo-linepay-tw' ),
						'type'     => 'text',
						'desc'     => __( 'Enter your Channel SecretKey.', 'woo-linepay-tw' ),
						'desc_tip' => true,
						'default'  => '',
						'id'       => 'linepay_tw_sandbox_channel_secret',
					),
					array(
						'title'    => __( 'Channel ID', 'woo-linepay-tw' ),
						'type'     => 'text',
						'desc'     => __( 'Enter your Channel ID.', 'woo-linepay-tw' ),
						'desc_tip' => true,
						'default'  => '',
						'id'       => 'linepay_tw_channel_id',
					),
					array(
						'title'    => __( 'Channel Secret Key', 'woo-linepay-tw' ),
						'type'     => 'text',
						'desc'     => __( 'Enter your Channel SecretKey.', 'woo-linepay-tw' ),
						'desc_tip' => true,
						'default'  => '',
						'id'       => 'linepay_tw_channel_secret',
					),
					array(
						'type' => 'sectionend',
						'id'   => 'linepay_tw_api_settings',
					),
				)
			);

		return apply_filters( 'woocommerce_get_settings_' . $this->id, $settings, $current_section );
	}

	/**
	 * Output the setting tab
	 *
	 * @return void
	 */
	public function output() {
		global $current_section;
		$settings = $this->get_settings( $current_section );
		WC_Admin_Settings::output_fields( $settings );
	}

	/**
	 * Save the settings
	 *
	 * @return void
	 */
	public function save() {
		global $current_section;
		$settings = $this->get_settings( $current_section );
		WC_Admin_Settings::save_fields( $settings );
	}
}
