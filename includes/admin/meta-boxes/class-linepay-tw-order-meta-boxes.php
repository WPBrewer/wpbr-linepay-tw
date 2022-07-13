<?php
/**
 * LINEPay_TW_Order_Meta_Boxes class file
 *
 * @package linepay_tw
 */

defined( 'ABSPATH' ) || exit;

/**
 * LINEPay_TW_Order_Meta_Boxes class
 */
class LINEPay_TW_Order_Meta_Boxes {

	/**
	 * The singleton instance
	 *
	 * @var LINEPay_TW_Order_Meta_Boxes
	 */
	private static $instance;

	/**
	 * Init the class
	 *
	 * @return void
	 */
	public static function init() {
		self::get_instance();

		add_action( 'add_meta_boxes', array( self::get_instance(), 'linepay_add_meta_boxes' ) );

	}

	/**
	 * Add admin meta box
	 *
	 * @param object $post The post object.
	 * @return void
	 */
	public function linepay_add_meta_boxes( $post ) {

		global $post;

		if ( array_key_exists( get_post_meta( $post->ID, '_payment_method', true ), LINEPay_TW::$allowed_payments ) ) {
			add_meta_box(
				'woocommerce-linepay-meta-boxes',
				__( 'LINE Pay Details', 'woo-linepay-tw' ),
				array(
					self::get_instance(),
					'linepay_admin_meta',
				),
				'shop_order',
				'side',
				'default'
			);
		}

	}


	/**
	 * Display metabox
	 *
	 * @param object $post Post object.
	 * @return void
	 */
	public function linepay_admin_meta( $post ) {

		global $theorder;

		if ( ! is_object( $theorder ) ) {
			$theorder = wc_get_order( $post->ID );
		}

		if ( array_key_exists( get_post_meta( $post->ID, '_payment_method', true ), LINEPay_TW::$allowed_payments ) ) {

			echo '<table>';
			echo '<tr><th><div id="order-id" data-order-id="' . esc_html( $post->ID ) . '">' . esc_html__( 'Transaction ID', 'woo-linepay-tw' ) . '</div></th><td>' . esc_html( $theorder->get_meta( '_linepay_reserved_transaction_id' ) ) . '</td></tr>';
			echo '<tr><th><div>' . esc_html__( 'Payment Status', 'woo-linepay-tw' ) . '</div></th><td>' . esc_html( $theorder->get_meta( '_linepay_payment_status' ) ) . '</td></tr>';

			if ( $theorder->get_meta( '_linepay_payment_status' ) === WC_Gateway_LINEPay_Const::PAYMENT_STATUS_AUTHED ) {
				echo '<tr id="linepay-action"><th>付款動作</th><td><button class="button linepay-confirm-btn" data-id=' . esc_html( $post->ID ) . ' data-service="' . esc_html( $service_id ) . '">付款確認</button></tr>';
			}

			echo '</table>';
		}
	}

	/**
	 * Get instance
	 *
	 * @return LINEPay_TW_Order_Meta_Boxes
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * The constructor
	 */
	public function __construct() {
		// do nothing.
	}

}
