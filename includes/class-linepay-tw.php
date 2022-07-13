<?php
/**
 * LINEPay_TW class file
 *
 * @package linepay
 */

defined( 'ABSPATH' ) || exit;

/**
 * LINEPay_TW main class for handling all checkout related process.
 */
class LINEPay_TW {

	/**
	 * Class instance
	 *
	 * @var LINEPay_TW
	 */
	private static $instance;

	/**
	 * Whether or not logging is enabled.
	 *
	 * @var boolean
	 */
	public static $log_enabled = false;

	/**
	 * WC_Logger instance.
	 *
	 * @var WC_Logger Logger instance
	 * */
	public static $log = false;

	/**
	 * Suppoeted payment gateways
	 *
	 * @var array
	 * */
	public static $allowed_payments;

	/**
	 * Enable sandbox mode
	 *
	 * @var boolean
	 */
	public static $enable_sandbox;

	/**
	 * LINE Pay API environment
	 *
	 * @var string
	 */
	public static $env_status;

	/**
	 * LINE Pay API channel id and channel secret
	 *
	 * @var array
	 */
	public static $channel_info;

	/**
	 * The currency scale of supported currencies.
	 *
	 * @var array
	 */
	public static $currency_scales;

	/**
	 * Initialize class and add hooks
	 *
	 * @return void
	 */
	public static function init() {

		self::get_instance();

		require_once LINEPAY_TW_PLUGIN_DIR . 'includes/gateways/class-linepay-tw-payment.php';
		require_once LINEPAY_TW_PLUGIN_DIR . 'includes/gateways/class-linepay-tw-request.php';
		require_once LINEPAY_TW_PLUGIN_DIR . 'includes/gateways/class-linepay-tw-response.php';
		require_once LINEPAY_TW_PLUGIN_DIR . 'includes/utils/class-wc-gateway-linepay-const.php';
		require_once LINEPAY_TW_PLUGIN_DIR . 'includes/admin/meta-boxes/class-linepay-tw-order-meta-boxes.php';

		self::$log_enabled = 'yes' === get_option( 'linepay_tw_debug_log_enabled', 'no' );

		self::$enable_sandbox = wc_string_to_bool( get_option( 'linepay_tw_sandboxmode_enabled' ) );

		self::$env_status = ( self::$enable_sandbox ) ? WC_Gateway_LINEPay_Const::ENV_SANDBOX : WC_Gateway_LINEPay_Const::ENV_REAL;

		self::$channel_info = array(
			WC_Gateway_LINEPay_Const::ENV_REAL    => array(
				'channel_id'     => get_option( 'linepay_tw_channel_id' ),
				'channel_secret' => get_option( 'linepay_tw_channel_secret' ),
			),
			WC_Gateway_LINEPay_Const::ENV_SANDBOX => array(
				'channel_id'     => get_option( 'linepay_tw_sandbox_channel_id' ),
				'channel_secret' => get_option( 'linepay_tw_sandbox_channel_secret' ),
			),
		);

		self::$currency_scales = array(
			'TWD' => 0,
		);

		self::$allowed_payments = array(
			'linepay-tw' => 'LINEPay_TW_Payment',
		);

		LINEPay_TW_Response::init();
		LINEPay_TW_Order_Meta_Boxes::init();

		load_plugin_textdomain( 'woo-linepay-tw', false, trailingslashit( dirname( LINEPAY_TW_BASENAME ) . '/languages' ) );

		add_filter( 'woocommerce_get_settings_pages', array( self::get_instance(), 'linepay_tw_add_settings' ), 15 );
		add_filter( 'woocommerce_payment_gateways', array( self::get_instance(), 'add_linepay_tw_payment_gateway' ) );
		add_action( 'wp_enqueue_scripts', array( self::get_instance(), 'linepay_tw_enqueue_scripts' ), 9 );
		add_action( 'admin_enqueue_scripts', array( self::get_instance(), 'linepay_tw_admin_scripts' ), 9 );

		add_action( 'wp_ajax_linepay_confirm', array( self::get_instance(), 'linepay_tw_ajax_confirm_payment' ) );
		add_filter( 'plugin_action_links_' . LINEPAY_TW_BASENAME, array( self::get_instance(), 'linepay_tw_add_action_links' ) );

	}

	/**
	 * Handle of confirm payment request via wp-admin.
	 *
	 * @return void
	 */
	public function linepay_tw_ajax_confirm_payment() {

		$posted = wp_unslash( $_POST );

		if ( ! array_key_exists( 'security', $posted ) || ! wp_verify_nonce( $posted['security'], 'linepay-confirm' ) ) {
			$return = array(
				'success' => false,
				'message' => __( 'Unsecure AJAX call', 'woo-linepay-tw' ),
			);
			wp_send_json( $return );
		}

		$order_id = $posted['post_id'];
		$order    = wc_get_order( $order_id );

		if ( ! $order ) {
			$return = array(
				'success' => false,
				'message' => __( 'No such order id', 'woo-linepay-tw' ),
			);
			wp_send_json( $return );
		}

		$reserved_transaction_id = $order->get_transaction_id();
		$gateway                 = new LINEPay_TW_Payment();
		$request                 = new LINEPay_TW_Request( $gateway );
		try {

			if ( $request->confirm( $order->get_id(), false ) ) {
				$order->add_order_note( __( 'LINE Pay Confirm Succeed!', 'woo-linepay-tw' ) );
				$return = array(
					'success' => true,
					'message' => __( 'Confirm succeed', 'woo-linepay-tw' ),
				);
				wp_send_json( $return );
			}
		} catch ( Exception $e ) {

			$order->add_order_note( __( 'LINE Pay Confirm Failed!', 'woo-linepay-tw' ) . $e->getMessage() );
			$return = array(
				'success' => false,
				'message' => $e->getMessage(),
			);
			wp_send_json( $return );

		}

	}
	/**
	 * Returns channel information that matches the environment information of LINEPay Gateway.
	 *
	 * @return array
	 */
	public static function get_channel_info() {
		return self::$channel_info[ self::$env_status ];
	}

	/**
	 * Add payment gateways
	 *
	 * @param array $methods LINE Pay Payment gateways.
	 * @return array
	 */
	public function add_linepay_tw_payment_gateway( $methods ) {
		$merged_methods = array_merge( $methods, self::$allowed_payments );
		return $merged_methods;
	}

	/**
	 * Enqueue public css style.
	 *
	 * @return void
	 */
	public function linepay_tw_enqueue_scripts() {
		wp_enqueue_style( 'linepay-tw', LINEPAY_TW_PLUGIN_URL . 'assets/css/linepay-tw-public.css', array(), '1.0' );
	}

	/**
	 * Enqueue admin scripts.
	 *
	 * @return void
	 */
	public function linepay_tw_admin_scripts() {

		wp_enqueue_script( 'linepay-tw', LINEPAY_TW_PLUGIN_URL . 'assets/js/linepay-tw-admin.js', array(), '1.0', true );
		wp_enqueue_style( 'linepay-tw', LINEPAY_TW_PLUGIN_URL . 'assets/css/linepay-tw-admin.css', array(), '1.0' );

		wp_localize_script(
			'linepay-tw',
			'linepay_object',
			array(
				'ajax_url'      => admin_url( 'admin-ajax.php' ),
				'confirm_nonce' => wp_create_nonce( 'linepay-confirm' ),
			)
		);

	}

	/**
	 * Plugin action links
	 *
	 * @param array $links The action links array.
	 * @return array
	 */
	public function linepay_tw_add_action_links( $links ) {
		$setting_links = array(
			'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=linepay-tw' ) . '">' . __( 'General Settings', 'woo-linepay-tw' ) . '</a>',
			'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout' ) . '">' . __( 'Payment Settings', 'woo-linepay-tw' ) . '</a>',
		);
		return array_merge( $links, $setting_links );
	}


	/**
	 * Add settings tab
	 *
	 * @return WC_Settings_Tab_LINEPay_TW
	 */
	public function linepay_tw_add_settings() {
		require_once LINEPAY_TW_PLUGIN_DIR . 'includes/settings/class-linepay-tw-settings-tab.php';
		return new WC_Settings_Tab_LINEPay_TW();
	}

	/**
	 * Log method.
	 *
	 * @param string $message The message to be logged.
	 * @param string $level The log level. Optional. Default 'info'. Possible values: emergency|alert|critical|error|warning|notice|info|debug.
	 * @return void
	 */
	public static function log( $message, $level = 'info' ) {
		if ( self::$log_enabled ) {
			if ( empty( self::$log ) ) {
				self::$log = new WC_Logger();
			}
			self::$log->log( $level, $message, array( 'source' => 'woo-linepay-tw' ) );
		}
	}

	/**
	 * Constructor
	 */
	public function __construct() {
		// do nothing.
	}

	/**
	 * Returns the single instance of the LINEPay_TW object
	 *
	 * @return LINEPay_TW
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

}
