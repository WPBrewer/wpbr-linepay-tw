<?php
/**
 * LINEPay_TW_Request class file
 *
 * @package linepay
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Receive response from LINE Pay.
 */
class LINEPay_TW_Request {

	/**
	 * The gateway instance
	 *
	 * @var WC_Payment_Gateway
	 */
	protected $gateway;

	/**
	 * The constructor
	 *
	 * @param WC_Payment_Gateway $gateway the payment gateway.
	 */
	public function __construct( $gateway ) {
		$this->gateway = $gateway;
	}

	/**
	 * Call LINE Pay's reserve-api and return the result.
	 * Change the order status according to the api call result.
	 *
	 * Request successful
	 * -post-meta fixes
	 *
	 * Request failed
	 * -fix order_status
	 *
	 * @param int $order_id Order ID.
	 * @return array
	 */
	public function reserve( $order_id ) {

		try {
			$order        = wc_get_order( $order_id );
			$product_info = array( 'packages' => $this->get_product_info( $order ) );
			$order_id     = $order->get_id();
			$currency     = $order->get_currency();
			$std_amount   = $this->get_standardized( $order->get_total(), $currency );

			// Check if the currency is the accuracy of the $amount that can be expressed.
			if ( ! $this->valid_currency_scale( $std_amount ) ) {
				throw new WC_Gateway_LINEPay_Exception( sprintf( WC_Gateway_LINEPay_Const::LOG_TEMPLATE_RESERVE_UNVALID_CURRENCY_SCALE, $order_id, $std_amount, $currency, $this->get_currency_scale( $currency ), $this->get_amount_precision( $amount ) ) );
			}

			$url  = $this->get_request_url( WC_Gateway_LINEPay_Const::REQUEST_TYPE_RESERVE );
			$body = array(
				'orderId'  => $order_id,
				'amount'   => $std_amount,
				'currency' => $currency,
			);

			$redirect_urls = array(
				'redirectUrls' => array(
					'confirmUrl'     => esc_url_raw( add_query_arg( array( 'request_type' => WC_Gateway_LINEPay_Const::REQUEST_TYPE_CONFIRM, 'order_id' => $order_id ), home_url( WC_Gateway_LINEPay_Const::URI_CALLBACK_HANDLER ) ) ),
					'confirmUrlType' => WC_Gateway_LINEPay_Const::CONFIRM_URLTYPE_CLIENT, //使用者的畫面跳轉到商家confirmUrl，完成付款流程
					'cancelUrl'      => esc_url_raw( add_query_arg( array( 'request_type' => WC_Gateway_LINEPay_Const::REQUEST_TYPE_CANCEL, 'order_id' => $order_id ), home_url( WC_Gateway_LINEPay_Const::URI_CALLBACK_HANDLER ) ) ),
				),
			);

			$options = array(
				'options' => array(
					'payment' => array(
						'payType' => strtoupper( $this->gateway->payment_type ),
						'capture' => true,//boolean
					),
					'extra' => array(
						'branchName' => '',
					),
				),
			);

			$info = $this->execute($url, array_merge( $body, $product_info, $redirect_urls, $options ) );

			// On request failure.
			if ( is_wp_error( $info ) ) {
				throw new WC_Gateway_LINEPay_Exception( '', $info );
			}

			// Upon successful request.
			update_post_meta( $order_id, '_linepay_payment_status', WC_Gateway_LINEPay_Const::PAYMENT_STATUS_RESERVED );
			update_post_meta( $order_id, '_linepay_reserved_transaction_id', $info->transactionId );
			update_post_meta( $order_id, '_linepay_refund_info', array() );

			// 回傳 paymentUrl 導向付款頁面.
			return array(
				'result'   => 'success',
				'redirect' => $info->paymentUrl->web,
			);
		} catch ( WC_Gateway_LINEPay_Exception $e ) {
			$info	= $e->getInfo();
			// static::$logger->error( 'process_payment_reserve', ( is_wp_error( $info) ) ? $info : $e->getMessage() );

			wc_add_wp_error_notices( new WP_Error( 'process_payment_reserve', __( 'Unable to process payment request. Please try again.', 'woo-linepay-tw' ) ) );

			// Initialize order stored in session.
			WC()->session->set( 'order_awaiting_payment', false );

			// Failed when WC_Order exists.
			if ( $order instanceof WC_Order ) {
				$order->update_status( 'failed' );
			}

			update_post_meta( $order_id, '_linepay_payment_status', WC_Gateway_LINEPay_Const::PAYMENT_STATUS_FAILED );

			return array(
				'result'   => 'failure',
				'redirect' => wc_get_cart_url(),
			);
		}
	}


	/**
	 * Call confirm-api of LINE Pay and move to the page that matches the result.
	 *
	 * If reserve-api is successfully called, according to the registered confirmUrl
	 * Call handle_callback of woocommerce.
	 *
	 * Request successful
	 * -Go to order result page
	 *
	 * Request failed
	 * -Send failure message
	 * -Go to order detail page
	 *
	 * @see WC_Gateway_LINEPay_Handler->handle_callback()
	 * @param int $order_id Order ID.
	 */
	public function confirm( $order_id ) {

		try {
			$order    = wc_get_order( $order_id );
			$amount   = $order->get_total();
			$currency = $order->get_currency();

			// Direct access to DB to check whether order price information is altered.
			$reserved_std_amount = $this->get_standardized( get_post_meta( $order_id, '_order_total', true ), $currency );
			$std_amount          = $this->get_standardized( $amount );

			// 1st verification of the amount, confirm the requested amount Confirm the reserved amount
			if ( $std_amount !== $reserved_std_amount ) {
				throw new WC_Gateway_LINEPay_Exception( sprintf( WC_Gateway_LINEPay_Const::LOG_TEMPLATE_CONFIRM_FAILURE_MISMATCH_ORDER_AMOUNT, $std_amount, $reserved_std_amount ) );
			}

			// api call.
			$reserved_transaction_id = get_post_meta( $order_id, '_linepay_reserved_transaction_id', true );
			$url                     = $this->get_request_url( WC_Gateway_LINEPay_Const::REQUEST_TYPE_CONFIRM, array( 'transaction_id' => $reserved_transaction_id ) );
			$body                    = array(
				'amount'   => $std_amount,
				'currency' => $currency,
			);

			$info = $this->execute($url, $body, 40 );

			// On request failure.
			if ( is_wp_error( $info ) ) {
				throw new WC_Gateway_LINEPay_Exception( '', $info );
			}

			$confirmed_amount = 0;
			foreach ( $info->payInfo as $item ) {
				$confirmed_amount += $item->amount;
			}

			// Refunds will be processed if the amount at Reserve is different from the amount after Confirm.
			$std_confirmed_amount = $this->get_standardized( $confirmed_amount );

			if ( $std_amount !== $std_confirmed_amount ) {
				$refund_result = 'Refund Failure';
				if ( ! is_wp_error( $this->request_refund_api( $order, $reserved_transaction_id, $std_amount ) ) ) {
					$refund_result = 'Refund Success';
				}

				throw new WC_Gateway_LINEPay_Exception( sprintf( WC_Gateway_LINEPay_Const::LOG_TEMPLATE_REFUND_FAILURE_AFTER_CONFIRM, $order_id, $std_amount, $std_confirmed_amount, $refund_result ) );
			}

			$order->payment_complete( $info->transactionId );
			add_post_meta( $order_id, '_linepay_transaction_balanced_amount', $std_confirmed_amount, true );
			update_post_meta( $order_id, '_linepay_payment_status', WC_Gateway_LINEPay_Const::PAYMENT_STATUS_CONFIRMED );

			// cart initialization.
			WC()->cart->empty_cart();

			wp_safe_redirect( $this->get_return_url( $order ) );
			exit;
		} catch ( WC_Gateway_LINEPay_Exception $e ) {
			$info = $e->getInfo();
			// static::$logger->error( 'process_payment_confirm', ( is_wp_error( $info ) ) ? $info : $e->getMessage() );
			wc_add_wp_error_notices( new WP_Error( 'process_payment_confirm', __( 'Unable to confirm payment. Please contact support.', 'woo-linepay-tw' ) ) );

			// Initialize order stored in session.
			// FIXME: not sure the purpose.
			WC()->session->set( 'order_awaiting_payment', false );

			$reserved_transaction_id = get_post_meta( $order_id, '_linepay_reserved_transaction_id', true );
			$detail_url              = $this->get_request_url( WC_Gateway_LINEPay_Const::REQUEST_TYPE_DETAILS, array( 'transaction_id' => $reserved_transaction_id ) );

			$detail_body             = array( 'transactionId' => $reserved_transaction_id );

			$detail_info             = $this->execute($detail_url, http_build_query( $detail_body ), 20, 'GET' );

			if ( ! is_wp_error( $detail_info ) ) {

				$order = wc_get_order( $order_id );
				if ( $order ) {
					$pay_status = '';
					if ( is_array( $detail_info ) ) {
						$order_detail = $detail_info[0];
						$pay_status   = $order_detail->payStatus;
					}
					$order->update_status( 'on-hold', 'LINE Pay 執行 Confirm API 失敗，查詢 LINE Pay 付款狀態為：' . $pay_status );
					$order->set_transaction_id( $reserved_transaction_id );
				}
			} else {
				$order->update_status( 'on-hold', $detail_info->get_error_message() );
			}

			// FIXME:  not sure purpose.
			update_post_meta( $order_id, '_linepay_payment_status', WC_Gateway_LINEPay_Const::PAYMENT_STATUS_FAILED );

			WC()->cart->empty_cart();
			wp_safe_redirect( $this->get_return_url( $order ) );
			exit;
		}
	}

	/**
	 * When canceling after payment request, the information used for payment is initialized.
	 *
	 * If you cancel after calling reserve-api, according to the registered cancelUrl
	 * Call hanle_callback of woocommerce.
	 *
	 * @see		WC_Gateway_LINEPay_Handler->handle_callback()
	 * @param	int $order_id
	 */
	public function cancel( $order_id ) {
		$order                   = wc_get_order( $order_id );
		$reserved_transaction_id = get_post_meta( $order_id, '_linepay_reserved_transaction_id', true );

		update_post_meta( $order_id, '_linepay_payment_status', WC_Gateway_LINEPay_Const::PAYMENT_STATUS_CANCELLED );
		$order->update_status( 'cancelled' );

		// Initialize order stored in session.
		WC()->session->set( 'order_awaiting_payment', false );

		wc_add_wp_error_notices( new WP_Error( 'process_payment_cancel', __( 'Payment canceled.', 'woo-linepay-tw' ) ) );
		// static::$logger->error( 'process_payment_cancel', sprintf( WC_Gateway_LINEPay_Const::LOG_TEMPLATE_PAYMENT_CANCEL, $order_id, $reserved_transaction_id ) );

		wp_redirect( wc_get_cart_url() );
		exit;
	}

	/**
	 * Request LINEPay's refund-api and return the result.
	 *
	 * @param int $order_id
	 * @param string $refund_amount => wc_format_decimal()
	 * @param string $reason
	 * @return boolean(true) |WP_Error
	 */
	public function refund ($order_id, $refund_amount, $reason = '') {

		$order             = wc_get_order( $order_id );
		$std_amount        = $this->get_standardized( $order->get_total(), $order->get_currency() );
		$std_refund_amount = $this->get_standardized( $refund_amount );

		if ( false === $order) {

			return new WP_Error( 'process_refund_request', sprintf( __( 'Unable to find order #%s', 'woo-linepay-tw' ), $order_id ), array(
				'requester'     => $requester,
				'order_id'      => $order_id,
				'refund_amount' => $std_refund_amount,
			));
		}

		$transaction_id = $order->get_transaction_id();
		$order_id       = $order->get_id();
		$order_status   = $order->get_status();

		$result = $this->request_refund_api( $order, $transaction_id, $std_refund_amount, $requester );

		return $result;
	}

	/**
	 * Sends a request based on the transmitted information and returns the result.
	 * When requesting LINEPay, create a header to be used in common.
	 *
	 * @param string $url
	 * @param array $body
	 * @param int $timeout
	 * @return mixed|WP_Error Return info object or WP_Error
	 */
	private function execute($url, $body = null, $timeout = 20, $method = 'POST' ) {

		$channel_info = LINEPay_TW::get_channel_info();

		$request_time = self::generate_request_time();

		$request_body = '';
		if ( ! is_null( $body ) ) {
			if ( is_array( $body ) ) {
				$request_body = wp_json_encode( $body );
			} else {
				$request_body = $body;
			}
		}

		$headers = array(
			'content-type'               => 'application/json; charset=UTF-8',
			'X-LINE-ChannelId'           => $channel_info['channel_id'],
			'X-LINE-Authorization-Nonce' => $request_time,
			'X-LINE-Authorization'       => self::generate_signature( $channel_info['channel_secret'], $url, $request_body, $request_time ),
		);

		$request_args = array(
			'httpversion' => '1.1',
			'timeout'     => $timeout,
			'headers'     => $headers,
		);

		if ( is_array( $body ) ) {
			$request_args = array_merge( $request_args, array( 'body' => wp_json_encode( $body ) ) );
		}

		LINEPay_TW::log( '[request] http_request', 'url : '. $url );
		LINEPay_TW::log( '[request] http_request', 'http method is POST - '. json_encode( $request_args ) );

		if ( 'POST' === $method ) {
			$response = wp_remote_post( $url, $request_args );
		} elseif ( 'GET' === $method ) {
			$response = wp_remote_get( $url, $request_args );
		}

		LINEPay_TW::log( '[response] http_response_not_success', 'http response code is ' . $http_status . json_encode( $response) );

		// maybe timeout
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$http_status = (int) $response['response']['code'];
		if ( 200 !== $http_status ) {

			return new WP_Error(
				'[request] http_response_not_success',
				'http response code is ' . $http_status,
				array(
					'url' => $url,
				)
			);
		}

		$response_body       = self::json_custom_decode( wp_remote_retrieve_body( $response ) );
		$linepay_return_code = $response_body->returnCode;

		if ( '0000' !== $linepay_return_code) {
			return new WP_Error( '[request] linepay_response_failure', 'linepay return code is ' . $linepay_return_code, $response_body );
		}

		return $response_body->info;
	}

	/**
	 * Call the refund API and store the information DB according to the result.
	 * 1. Save refund information in the form of serialized array
	 * 2. After refund, the balance of the transaction amount is stored in string form.
	 *
	 * @param WC_Order $order_id
	 * @param string $transaction_id
	 * @param number|string $refund_amount
	 * @param string $requestrer
	 * @return boolean(true)|WP_Error
	 */
	private function request_refund_api( $order, $transaction_id, $refund_amount, $requester = null ) {

		$order_id          = $order->get_id();
		$std_refund_amount = $this->get_standardized( $refund_amount );

		$url  = $this->get_request_url( WC_Gateway_LINEPay_Const::REQUEST_TYPE_REFUND, array( 'transaction_id' => $transaction_id ) );
		$body = array( 'refundAmount' => $std_refund_amount );
		$info = $this->execute( $url, $body );

		// On request failure.
		if ( is_wp_error( $info ) ) {
			// static::$logger->error( 'request_refund_api', $info );
			return $info;
		}

		// Save refund transaction information.
		$refund_info                               = unserialize( get_post_meta( $order_id, '_linepay_refund_info', true ) );
		$refund_info[ $info->refundTransactionId ] = array(
			'requester' => $requester,
			'reason'    => $reason,
			'date'      => $info->refundTransactionDate,
		);
		update_post_meta( $order_id, '_linepay_refund_info', serialize( $refund_info ) );

		// Amount balance revision.
		$balanced_amount		= get_post_meta( $order_id, '_linepay_transaction_balanced_amount', true );
		$new_balanced_amount	= $this->get_standardized( floatval( $balanced_amount ) - floatval( $std_refund_amount ), $order->get_currency() );
		update_post_meta( $order_id, '_linepay_transaction_balanced_amount', $new_balanced_amount );

		return true;
	}

	/**
	 * Returns the array to be transferred to reserve-api based on the order information.
	 * The array contains productName and productImageUrl.
	 *
	 * productName
	 * -1: Name of the first item
	 * -2 or more: first item name + remaining items
	 *
	 * productImageUrl
	 * -URL information of the first item
	 *
	 * @param WC_Order $order
	 * @return array
	 */
	private function get_product_info( $order ) {
		$packages     = array();
		$items        = $order->get_items();
		$order_amount = 0;

		// item_lines.
		if ( count( $items ) > 0 ) {

			$products     = array();
			$total_amount = 0;

			$first_item = $items[ array_key_first( $items ) ];
			$wc_product = wc_get_product( $first_item->get_product_id() );

			$order_name = $wc_product->get_name();

			if ( count( $items ) > 1 ) {
				$order_name = $order_name . '等共' . $order->get_item_count() . '個商品';
			}

			$product = array(
				'id'       => $first_item->get_product_id(),
				'name'     => sanitize_text_field( $order_name ),
				'quantity' => 1,
				'price'    => $order->get_total(),
			);

			// 取第一個商品的圖案.
			$thumbnail_image_urls = wp_get_attachment_image_src( get_post_thumbnail_id( $first_item->get_product_id() ) );

			if ( isset( $thumbnail_image_urls[0] ) ) {
				$product['imageUrl'] = $thumbnail_image_urls[0];
			}

			array_push( $products, $product );

			array_push(
				$packages,
				array(
					'id'       => 'WC-ITEMS||' . $order->get_id(),
					'name'     => sanitize_text_field( 'WC_ITEMS' ),
					'amount'   => $this->get_standardized( $order->get_total() ),
					'products' => $products,
				)
			);
		} //end items.

		return $packages;
	}

	/**
	 * Returns the number_format for the currency
	 *
	 * @param number|string $amount
	 * @param string $currency
	 * @return string
	 */
	private function get_standardized( $amount, $currency = null ) {
		$scale = $this->get_currency_scale();

		if ( is_string( $amount ) ) {
			$amount = floatval( $amount );
		}

		return number_format( $amount, $scale, '.', '' );
	}

	/**
	 * Returns the scale of the received currency code
	 * Use BaseCurrencyCode when there is no information received
	 *
	 * @param string $currency_code The currency_code.
	 * @return number
	 */
	private function get_currency_scale( $currency_code = null ) {

		if ( null === $currency_code ) {
			$currency_code = get_woocommerce_currency();
		}

		$currency_code = strtoupper( $currency_code );

		if ( in_array( $currency_code, LINEPay_TW::$currency_scales, true ) ) {
			return LINEPay_TW::$currency_scales[ $currency_code ];
		} else {
			// Scale of unset currency is set to 0.
			return 0;
		}
	}

	/**
	 * Check if the scale of the $amount received based on the basic currency code is appropriate.
	 *
	 * @param number $amount
	 * @param $currency_code
	 * @return boolean
	 */
	private function valid_currency_scale( $amount, $currency_code = null ) {
		return ( $this->get_currency_scale( $currency_code)  >= $this->get_amount_precision( $amount ) );
	}

	/**
	 * Returns the URL that matches the request type.
	 *
	 * @param string $type	=> const:WC_Gateway_LINEPay_Const::REQUEST_TYPE_RESERVE|CONFIRM|CANCEL|REFUND
	 * @param array $args
	 * @return string
	 */
	private function get_request_url( $type, $args = array() ) {
		$host = $this->get_request_host();
		$uri  = $this->get_request_uri( $type, $args );

		return $host . $uri;
	}

	/**
	 * Returns HOST information that matches the environmental information of LINEPay Gateway.
	 *
	 * @return string
	 */
	private function get_request_host() {
		$host = '';

		switch ( LINEPay_TW::$env_status ) {
			case WC_Gateway_LINEPay_Const::ENV_SANDBOX:
				$host = WC_Gateway_LINEPay_Const::HOST_SANDBOX;
				break;

			case WC_Gateway_LINEPay_Const::ENV_REAL:
			default:
				$host = WC_Gateway_LINEPay_Const::HOST_REAL;
				break;
		}

		return $host;
	}

	/**
	 * Returns the uri that matches the request type.
	 * If the uri contains variables, it is combined with args to create a new uri.
	 *
	 * @param string $type	=> const:WC_Gateway_LINEPay_Const::REQUEST_TYPE_RESERVE|CONFIRM|CANCEL|REFUND
	 * @param array $args
	 * @return string
	 */
	private function get_request_uri( $type, $args ) {
		$uri = '';

		switch ($type) {
			case WC_Gateway_LINEPay_Const::REQUEST_TYPE_RESERVE:
				$uri = WC_Gateway_LINEPay_Const::URI_RESERVE;
				break;
			case WC_Gateway_LINEPay_Const::REQUEST_TYPE_CONFIRM:
				$uri = WC_Gateway_LINEPay_Const::URI_CONFIRM;
				break;
			case WC_Gateway_LINEPay_Const::REQUEST_TYPE_DETAILS:
				$uri = WC_Gateway_LINEPay_Const::URI_DETAILS;
				break;
			case WC_Gateway_LINEPay_Const::REQUEST_TYPE_REFUND:
				$uri = WC_Gateway_LINEPay_Const::URI_REFUND;
				break;
		}

		$new_uri = $uri;
		foreach ( $args as $key => $value ) {
			$new_uri = str_replace( '{' . $key . '}', $value, $new_uri );
		}

		return $new_uri;
	}

	/**
	 * Returns the decimal point accuracy of the passed $amount
	 *
	 * @param number $amount
	 * @return number
	 */
	private function get_amount_precision( $amount = 0) {
		if ( is_string( $amount ) ) {
			$amount = (float) $amount;
		}
		$strl = strlen( $amount);

		$strp = strpos( $amount, '.');
		$strp = ( false !== $strp ) ? $strp + 1 : $strl;

		return ( $strl - $strp );
	}

	/**
	 * Get the return url (thank you page).
	 *
	 * @param WC_Order|null $order Order object.
	 * @return string
	 */
	public function get_return_url( $order = null ) {
		if ( $order ) {
			$return_url = $order->get_checkout_order_received_url();
		} else {
			$return_url = wc_get_endpoint_url( 'order-received', '', wc_get_checkout_url() );
		}

		return apply_filters( 'woocommerce_get_return_url', $return_url, $order );
	}

	/**
	 * Hange large integer to json's string format.
	 *
	 * @param String $json The json string.
	 * @return mixed
	 */
	private static function json_custom_decode( $json ) {
		if ( version_compare( PHP_VERSION, '5.4.0', '>=' ) ) {
			return json_decode( $json, false, 512, JSON_BIGINT_AS_STRING );
		} else {
			return json_decode( preg_replace( '/:\s?(\d{14,})/', ': "${1}"', $json ) );
		}
	}

	/**
	* Generate signature
	*
	* @param [type] $channel_secret
	* @param [type] $url
	* @param [type] $request_body
	* @param [type] $nonce
	* @return void
	*/
	private static function generate_signature( $channel_secret, $url, $request_body, $nonce ) {
		$url_path = wp_parse_url( $url, PHP_URL_PATH );
		$data     = $channel_secret . $url_path . $request_body . $nonce;
		return base64_encode( hash_hmac( WC_Gateway_LINEPay_Const::AUTH_ALGRO, $data, $channel_secret, true ) );
	}

	private static function generate_request_time() {
		return date( WC_Gateway_LINEPay_Const::REQUEST_TIME_FORMAT) . '' . ( explode( '.', microtime( true ) )[1] );
	}

}