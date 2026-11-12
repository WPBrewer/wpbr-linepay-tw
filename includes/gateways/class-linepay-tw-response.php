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

		// handling callback from LINE Pay.
		add_action( 'woocommerce_api_linepay_payment', array( self::get_instance(), 'receive_payment_response' ) );
	}

	/**
	 *
	 * The callback can only handle the following states:
	 * payment status   : reserved
	 * -> request type  : confirm, cancel
	 *
	 * Payment status   : confirmed
	 *  -> request type : refund
	 *
	 * If it cannot be processed, an error log is left.
	 *
	 * @see woocommerce::action - woocommerce_api_
	 * @throws Exception Throws exception when order id is not found.
	 */
	public function receive_payment_response() {

		try {

			// phpcs:disable WordPress.Security.NonceVerification.Recommended
			// The callback is authenticated by the order key below, not by a nonce.
			$order_id       = isset( $_GET['order_id'] ) ? absint( wp_unslash( $_GET['order_id'] ) ) : 0;
			$request_type   = isset( $_GET['request_type'] ) ? sanitize_text_field( wp_unslash( $_GET['request_type'] ) ) : '';
			$order_key      = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : '';
			$transaction_id = isset( $_GET['transactionId'] ) ? sanitize_text_field( wp_unslash( $_GET['transactionId'] ) ) : '';
			$received_params = implode(
				',',
				array_map(
					function ( $name ) {
						return preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $name );
					},
					array_keys( $_GET )
				)
			);
			// phpcs:enable WordPress.Security.NonceVerification.Recommended

			// Record what LINE Pay sent back. Never log the order key value itself.
			LINEPay_TW::log(
				sprintf(
					'[callback][order_id:%s] request_type: %s, params: %s, key: %s, transactionId: %s',
					$order_id,
					$request_type,
					$received_params,
					'' === $order_key ? 'missing' : 'present',
					'' === $transaction_id ? 'missing' : $transaction_id
				)
			);

			if ( ! in_array( $request_type, array( WPBR_LINEPay_Const::REQUEST_TYPE_CONFIRM, WPBR_LINEPay_Const::REQUEST_TYPE_CANCEL ), true ) ) {
				throw new Exception( sprintf( 'invalid request type for order id: %s', $order_id ) );
			}

			$order = $order_id ? wc_get_order( $order_id ) : false;

			if ( ! $order || WPBR_LINEPay_Const::ID !== $order->get_payment_method() ) {
				throw new Exception( sprintf( WPBR_LINEPay_Const::LOG_TEMPLATE_HANDLE_CALLBANK_NOT_FOUND_ORDER_ID, $order_id, __( 'Unable to process callback.', 'wpbr-linepay-tw' ) ) );
			}

			// Verify the request comes from the order owner (via LINE Pay redirect) before doing anything else.
			if ( '' === $order_key || ! hash_equals( (string) $order->get_order_key(), $order_key ) ) {
				throw new Exception( sprintf( 'order key mismatch for order id: %s', $order_id ) );
			}

			// LINE Pay always appends transactionId to confirmUrl and cancelUrl; it must match the reserved transaction.
			if ( '' === $transaction_id || ! hash_equals( (string) $order->get_meta( '_linepay_reserved_transaction_id' ), $transaction_id ) ) {
				throw new Exception( sprintf( 'transaction id mismatch for order id: %s', $order_id ) );
			}

			$payment_status = $order->get_meta( '_linepay_payment_status' );

			if ( WPBR_LINEPay_Const::PAYMENT_STATUS_RESERVED !== $payment_status ) {
				// The requester already proved they hold the order key, so sending them to the order page leaks nothing.
				LINEPay_TW::log( sprintf( 'invalid status: %s to handle callback for order id: %s', $payment_status, $order_id ) );
				wp_safe_redirect( $order->get_checkout_order_received_url() );
				exit;
			}

			$gateway = new LINEPay_TW_Payment();
			$request = new LINEPay_TW_Request( $gateway );

			if ( WPBR_LINEPay_Const::REQUEST_TYPE_CONFIRM === $request_type ) {
				$request->confirm( $order_id );
			} else {
				$request->cancel( $order_id );
			}
		} catch ( Exception $e ) {
			LINEPay_TW::log( 'receive_payment_response rejected: ' . $e->getMessage() );
		}

		self::reject();
	}

	/**
	 * Send the same generic response for every rejected callback, so it neither leaks
	 * the order key nor reveals whether an order exists or what state it is in.
	 *
	 * @return void
	 */
	private static function reject() {
		wp_safe_redirect( wc_get_cart_url() );
		exit;
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
