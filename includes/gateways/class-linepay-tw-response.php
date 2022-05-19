
<?php
/**
 * LINEPay_TW_Response class file
 *
 * @package linepay
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Receive response from LINE Pay.
 */
class LINEPay_TW_Response {

	/**
	 * Class instance
	 *
	 * @var LINEPay_TW_Response
	 */
	private static $instance;

	/**
	 * Init the instance
	 *
	 * @return void
	 */
	public static function init() {
		self::get_instance();

		// the result_url, get data from LINE Pay.
		add_action( 'woocommerce_api_linepay_payment', array( self::get_instance(), 'receive_payment_response' ) );
	}

	/**
	 * LINEPay payment provider
	 *
	 * The callback can only handle the following states:
	 * payment status	: reserved
	 * -> request type	: confirm, cancel
	 *
	 * payment status	: confirmed
	 * 	-> request type	: refund
	 *
	 * If it cannot be processed, an error log is left.
	 *
	 * @see woocommerce::action - woocommerce_api_
	 */
	public function receive_payment_response() {

		try {
			$order_id = wp_unslash( $_GET['order_id'] );
			if ( empty( $order_id ) ) {
				throw new Exception( sprintf( WC_Gateway_LINEPay_Const::LOG_TEMPLATE_HANDLE_CALLBANK_NOT_FOUND_ORDER_ID, $order_id, __( 'Unable to process callback.', 'woocommerce_gateway_linepay' ) ) );
			}

			$request_type   = wp_unslash( $_GET['request_type'] );
			$payment_status = get_post_meta( $order_id, '_linepay_payment_status', true );

			$gateway = new LINEPay_TW_Payment();
			$request = new LINEPay_TW_Request( $gateway );

			if ( WC_Gateway_LINEPay_Const::PAYMENT_STATUS_RESERVED === $payment_status ) {

				switch ( $request_type ) {
					case WC_Gateway_LINEPay_Const::REQUEST_TYPE_CONFIRM:
						LINEPay_TW::log( 'process_payment_confirm' );
						$request->confirm( $order_id );
						break;
					case WC_Gateway_LINEPay_Const::REQUEST_TYPE_CANCEL:
						$request->cancel( $order_id );
						break;
				}

			} elseif ( WC_Gateway_LINEPay_Const::PAYMENT_STATUS_CONFIRMED === $payment_status ) {

				switch ( $request_type ) {
					case WC_Gateway_LINEPay_Const::REQUEST_TYPE_REFUND:
						$this->process_refund_by_customer( $gateway, $order_id );
						break;
				}

			}

			// LINEPay_TW::log( 'handle_callback', sprintf( WC_Gateway_LINEPay_Const::LOG_TEMPLATE_HANDLE_CALLBANK_NOT_FOUND_REQUREST, $order_id, $payment_status, $request_type, __( 'Unable to process callback.', 'woocommerce_gateway_linepay' ) ) );
		} catch ( Exception $e ) {
			// Leave error log.
			LINEPay_TW::log( 'handle_callback: ' . $e->getMessage() );
		}

	}

	/**
	 * Get instance
	 *
	 * @return LINEPay_TW_Response
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

}