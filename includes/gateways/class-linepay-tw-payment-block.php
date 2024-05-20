<?php
use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * Class LINEPay_Block
 */
final class LINEPay_Block extends AbstractPaymentMethodType {


	/**
	 * The payment method ID
	 *
	 * @var string
	 */
	protected $name = WPBR_LINEPay_Const::ID;

	/**
	 * The payment gateway instance
	 *
	 * @var LINEPay_TW_Payment
	 */
	private $gateway;

	/**
	 * Initialize the payment method
	 */
	public function initialize() {
		$this->settings = get_option( 'woocommerce_linepay-tw_settings', array() );
		$gateways       = WC()->payment_gateways->payment_gateways();
		$this->gateway  = $gateways[ $this->name ];
	}

	/**
	 * Is the payment method available?
	 */
	public function is_active() {
		return $this->gateway->is_available();
	}

	public function get_payment_method_script_handles() {

		wp_register_script(
			'wpbr-linepay-block',
			WPBR_LINEPAY_PLUGIN_URL . 'assets/js/blocks/linepay.js',
			array(
				'wc-blocks-registry',
				'wc-settings',
				'wp-element',
				'wp-html-entities',
			),
			null,
			true
		);

		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( 'wpbr-linepay-block' );

		}

		return array( 'wpbr-linepay-block' );
	}

	/**
	 * Get the payment method data and settings.
	 *
	 * @return array
	 */
	public function get_payment_method_data() {
		return array(
			'title'        => $this->get_setting( 'title' ),
			'description'  => $this->get_setting( 'description' ),
			'button_title' => $this->gateway->order_button_text,
			'supports'    => array_filter( $this->gateway->supports, array( $this->gateway, 'supports' ) ),
		);
	}
}
