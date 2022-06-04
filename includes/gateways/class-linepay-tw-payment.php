<?php

/**
 * LINEPay Payment Gateway
 *
 * @class LINEPay_TW_Payment
 * @extends WC_Payment_Gateway
 * @version 1.0.0
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

		$this->payment_type       = 'NORMAL';
		$this->payment_action     = get_option( 'linepay_tw_payment_action' );

		// Support refund function.
		$this->supports = array(
			'products',
			'refunds',
		);

		// Supported currency.
		$this->supported_currencies     = array( 'TWD' );

		// Define form field to show in admin setting.
		$this->init_form_fields();
		$this->init_settings();

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_filter( 'woocommerce_thankyou_order_received_text', array( $this, 'thankyou_order_on_hold_message'), 10, 2 );
		add_action( 'woocommerce_order_details_before_order_table', array( $this, 'display_on_hold_message_on_order_details' ));

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
	 * Process payments and return results.
	 * To pay with LINE Pay, request-api is first called.
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

		WC()->cart->empty_cart();

		$linepay_request = new LINEPay_TW_Request( $this );
		return $linepay_request->request( $order_id );
	}

	/**
	 * Process the refund and return the result.
	 *
	 * This method is called only when the administrator processes it.
	 * When the administrator requests a refund, the process_refund() method is called through WC_AJAX::refund_line_items().
	 *
	 * @see WC_AJAX::refund_line_items()
	 * @see	woocommerce::action - woocommerce_delete_shop_order_transients
	 * @param int $order_id
	 * @param float $amount
	 * @param string $reason
	 * @return bool|WP_Error
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		$linepay_request = new LINEPay_TW_Request( $this );
		return $linepay_request->refund( $order_id, $amount, $reason );
	}

	/**
	 * Check if the gateway is available for use.
	 *
	 * @return bool
	 */
	public function is_available() {
		$is_available = ( 'yes' === $this->enabled );

		// Channel information by usage environment.
		$channel_info = LINEPay_TW::get_channel_info();
		if ( empty( $channel_info['channel_id'] ) || empty( $channel_info['channel_secret'] ) ) {
			$is_available = false;
		}

		// Accepted Currency.
		$cur_currency = get_woocommerce_currency();
		if ( ! in_array( $cur_currency, $this->supported_currencies ) ) {
			$is_available = false;
		}

		if ( WC()->cart && 0 < $this->get_order_total() && 0 < $this->max_amount && $this->max_amount < $this->get_order_total() ) {
			$is_available = false;
		}

		return $is_available;
	}

	function thankyou_order_on_hold_message( $text, $order ) {

		if ( $order ) {
			if ( $order->get_payment_method() !== $this->id ) {
				return $text;
			}

			if ( $order->get_status() == 'on-hold' ) {
				$text = '<span class="linepay-order-onhold">'. esc_html__('We have received your order, but the payment status need to be confirmed. Please contact the support.', 'woo-linepay-tw') .'</span>';
			}

			if ( $order->get_status() == 'pending' ) {
				$text = '<span class="linepay-order-onhold">'. esc_html__('We have received your order, but the order is awaiting payment. Please pay again.', 'woo-linepay-tw') .'</span>';
			}

		}

        return $text;
	}

	function display_on_hold_message_on_order_details( $order ) {

		if ( is_checkout()) {
			return;
		}
		if ( $order->get_payment_method() !== $this->id ) {
			return;
		}

		if ( $order->get_status() == 'on-hold' ) {
			echo '<div class="linepay-order-onhold">'. esc_html__('We have received your order, but the payment status need to be confirmed. Please contact the support.', 'woo-linepay-tw') .'</div>';
		}

		if ( $order->get_status() == 'pending' ) {
			echo '<div class="linepay-order-onhold">'. esc_html__('We have received your order, but the order is awaiting payment. Please pay again', 'woo-linepay-tw') .'</div>';
		}

	}

}
