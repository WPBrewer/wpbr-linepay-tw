<?php
/**
 * Plugin Name: Pay with LINE Pay
 * Plugin URI: https://wpbrewer.com/product/wpbr-linepay-tw/
 * Description: Provides LINE Pay for your WooCommerce store
 * Author: WPBrewer
 * Author URI: https://wpbrewer.com
 * Version: 1.1.1
 * Text Domain: wpbr-linepay-tw
 * Domain Path: /languages
 *
 * WC requires at least: 3.0.0
 * WC tested up to: 7.6.0
 *
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 * @package wpbrewer
 */

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

define( 'WPBR_LINEPAY_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WPBR_LINEPAY_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPBR_LINEPAY_BASENAME', plugin_basename( __FILE__ ) );
define( 'WPBR_LINEPAY_VERSION', '1.1.1' );

/**
 * Display a notice if WooCommerce is not installed and activated
 *
 * @return void
 */
function wpbr_linepay_tw_needs_woocommerce() {

	echo '<div id="message" class="error">';
	echo '  <p>' . esc_html( __( 'Pay with LINE Pay needs WooCommerce, please intall and activate WooCommerce first!', 'wpbr-linepay-tw' ) ) . '</p>';
	echo '</div>';

}

function linepay_tw_previous_deactivate() {
	echo '<div id="message" class="error">';
	echo '  <p>' . esc_html( __( 'We deactivate plugin LINE Pay Taiwan for WooCommerce to avoid conflict with the new version.', 'wpbr-linepay-tw' ) ) . '</p>';
	echo '</div>';
}

/**
 * Run the plugin.
 *
 * @return void
 */
function run_wpbr_linepay() {

	// check if previous version install
	if ( is_plugin_active( 'woo-linepay-tw/woo-linepay-tw.php' ) ) {
		deactivate_plugins( 'woo-linepay-tw/woo-linepay-tw.php' );
		add_action( 'admin_notices', 'linepay_tw_previous_deactivate' );
		return;
	}

	/**
	 * Check if WooCommerce is installed and activated.
	 *
	 * @since 1.0.0
	 */
	if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ), true ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		if ( is_plugin_active( 'wpbr-linepay-tw/wpbr-linepay-tw.php' ) ) {
			deactivate_plugins( WPBR_LINEPAY_BASENAME );
			add_action( 'admin_notices', 'wpbr_linepay_tw_needs_woocommerce' );
			return;
		}
	}

	require_once WPBR_LINEPAY_PLUGIN_DIR . 'includes/class-wpbr-linepay-tw.php';
	LINEPay_TW::init();

}
add_action( 'plugins_loaded', 'run_wpbr_linepay' );
