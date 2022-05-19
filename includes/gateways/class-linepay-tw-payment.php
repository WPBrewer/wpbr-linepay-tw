<?php

/**
 * LINEPay Payment Gateway
 *
 * @class WC_Gateway_LINEPay
 * @extends WC_Payment_Gateway
 * @version 3.0.0
 * @author LINEPay
 */
class LINEPay_TW_Payment extends WC_Payment_Gateway {

	/**
	 * LINEPay Gateway
	 */
	public function __construct() {

		$this->id                 = WC_Gateway_LINEPay_Const::ID;
		$this->icon               = $this->get_icon();
		$this->title              = $this->get_option( 'title' );
		$this->description        = $this->get_option( 'description' );
		$this->has_fields         = false;
		$this->order_button_text  = __( 'Pay with LINE Pay', 'woo-linepay-tw' );
		$this->method_title       = WC_Gateway_LINEPay_Const::TITLE;
		$this->method_description = WC_Gateway_LINEPay_Const::DESC;

		/**
		 * Initialize the information to be supported by LINEPay Gateway.
		 * -Purchase or refund
		 * -Supported countries
		 * -Support currency
		 * -Information on refund status of manager and buyer
		 */
		// Support refund function.
		$this->supports = array(
			'products',
			'refunds',
		);

		// LINE Pay supported currency.
		$this->supported_currencies     = array( 'TWD' );

		// Define form field to show in admin setting.
		$this->init_form_fields();
		$this->init_settings();

		$this->init_merchant_data();

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

	}

	/**
	 * Payment gateway icon output
	 *
	 * @return string
	 */
	public function get_icon() {
		$icon_url = '<img src="' . LINEPAY_TW_PLUGIN_URL . 'assets/images/logo/linepay_logo_74x24.png">';
		return apply_filters( 'woocommerce_gateway_icon', $icon_url, $this->id );
	}

	/**
	 * Form fields Initialize the information.
	 *
	 * @see WC_Settings_API::init_form_fields()
	 * @see WC_Gateway_LINEPay_Settings->get_form_fields()
	 */
	public function init_form_fields() {
		$this->form_fields = include LINEPAY_TW_PLUGIN_DIR . 'includes/settings/settings-linepay-tw-payment.php';
	}

	/**
	 * Initialize the information registered in form fields to the fields of LINEPay Gateway.
	 * Fields newly defined for LINEPay are separated by prefixing with linepay_.
	 */
	protected function init_merchant_data() {

		// $this->linepay_lang_cd        = $this->get_option( 'lang_cd' );

		$this->payment_type   = 'NORMAL';
		$this->payment_action = get_option( 'linepay_tw_payment_action' );

		// LINEPay Gateway Check whether it is used.
		$this->linepay_is_valid = $this->is_valid_for_use();
		if ( is_wp_error( $this->linepay_is_valid ) ) {
			$this->enabled = 'no';
		}
	}

	/**
	 * Process payments and return results.
	 * To pay with LINE Pay, reserve-api is first called.
	 * Override the parent process_payment function.
	 * return the success and redirect in an array. e.g:
	 * return array(
	 *    'result'   => 'success',
	 *    'redirect' => $this->get_return_url( $order )
	 * );
	 *
	 * @param int $order_id Order ID.
	 * @return array
	 */
	public function process_payment( $order_id ) {
		add_post_meta( $order_id, '_linepay_payment_status', null, true );
		add_post_meta( $order_id, '_linepay_reserved_transaction_id', null, true );

		// reserve.
		$request = new LINEPay_TW_Request( $this );
		return $request->reserve( $order_id );
	}

	/**
	 * Process the refund and return the result.
	 *
	 * This method is called only when the administrator processes it.
	 * When the administrator requests a refund, the process_refund() method is called through WC_AJAX::refund_line_items().
	 *
	 * The woocommerce_delete_shop_order_transients action
	 * This action occurs immediately before woocommerce completes the refund process when a manager requests a refund and gives a json response.
	 *
	 * @see WC_AJAX::refund_line_items()
	 * @see	woocommerce::action - woocommerce_delete_shop_order_transients
	 * @param int $order_id
	 * @param float $amount
	 * @param string $reason
	 * @return bool|WP_Error
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		$request = new LINEPay_TW_Request( $this );
		return $request->refund( $order_id, $amount, $reason );
	}

	/**
	 * Returns whether the currency is supported.
	 *
	 * @param	string $currency
	 * @return	boolean
	 */
	private function is_supported_currency( $currency ) {
		return in_array( $currency, $this->supported_currencies );
	}

	/**
	 * Returns whether LINEPay Gateway can be used.
	 * -Accepted currency
	 * -Input channel information
	 *
	 * @return boolean|WP_Error
	 */
	private function is_valid_for_use() {

		// Return if not already used.
		if ( ! $this->enabled ) {
			return 'no';
		}

		// Accepted Currency.
		$cur_currency = get_woocommerce_currency();
		if ( ! $this->is_supported_currency( $cur_currency ) ) {
			return new WP_Error( 'linepay_not_supported_currency', sprintf( '[%s] ' . __( 'Unsupported currency.', 'woo-linepay-tw' ), $cur_currency ), $cur_currency );
		}

		// Channel information by usage environment.
		$channel_info = LINEPay_TW::get_channel_info();
		if ( empty( $channel_info['channel_id'] ) || empty( $channel_info['channel_secret'] ) ) {

			return new WP_Error( 'linepay_empty_channel_info', sprintf( '[%s] ' . __( 'You have not entered your channel information.', 'woo-linepay-tw' ), LINEPay_TW::$env_status ) );
		}

		return 'yes';
	}

}
