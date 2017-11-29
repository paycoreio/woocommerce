<?php
/*
 * Plugin Name: WooCommerce PayCore.io Gateway
 * Plugin URI: https://dashboard.paycore.io/
 * Description: Take cashless payments on your store using PayCore.io.
 * Author: PayCore.io
 * Author URI: https://dashboard.paycore.io/
 * Version: 1.0.0
 * Requires at least: 4.4
 * Tested up to: 4.8
 * WC requires at least: 2.5
 * WC tested up to: 3.1
 * Text Domain: woocommerce-gateway-paycore
 * Domain Path: /languages
 *
 * Copyright (c) 2017 WooCommerce
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
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Required minimums and constants
 */
define( 'WC_PAYCORE_VERSION', '1.0.0' );
define( 'WC_PAYCORE_MIN_PHP_VER', '5.6.0' );
define( 'WC_PAYCORE_MIN_WC_VER', '2.5.0' );
define( 'WC_PAYCORE_MAIN_FILE', __FILE__ );
define( 'WC_PAYCORE_PLUGIN_URL', untrailingslashit( plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) ) ) );
define( 'WC_PAYCORE_PLUGIN_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );

if ( ! class_exists( 'WC_Paycore' ) ) :

	class WC_Paycore {

		/**
		 * @var Singleton The reference the *Singleton* instance of this class
		 */
		private static $instance;

		/**
		 * @var Reference to logging class.
		 */
		private static $log;

		/**
		 * Returns the *Singleton* instance of this class.
		 *
		 * @return Singleton The *Singleton* instance.
		 */
		public static function get_instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		/**
		 * Private clone method to prevent cloning of the instance of the
		 * *Singleton* instance.
		 *
		 * @return void
		 */
		private function __clone() {}

		/**
		 * Private unserialize method to prevent unserializing of the *Singleton*
		 * instance.
		 *
		 * @return void
		 */
		private function __wakeup() {}

		/**
		 * Notices (array)
		 * @var array
		 */
		public $notices = array();

		/**
		 * Protected constructor to prevent creating a new instance of the
		 * *Singleton* via the `new` operator from outside of this class.
		 */
		protected function __construct() {
			add_action( 'admin_init', array( $this, 'check_environment' ) );
			add_action( 'admin_notices', array( $this, 'admin_notices' ), 15 );
			add_action( 'plugins_loaded', array( $this, 'init' ) );
		}

		/**
		 * Init the plugin after plugins_loaded so environment variables are set.
		 */
		public function init() {
			// Don't hook anything else in the plugin if we're in an incompatible environment
			if ( self::get_environment_warning() ) {
				return;
			}

			// Init the gateway itself
			$this->init_gateways();

			add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_action_links' ) );
			add_action( 'wp_ajax_paycore_dismiss_request_api_notice', array( $this, 'dismiss_request_api_notice' ) );
		}

		/**
		 * Allow this class and other classes to add slug keyed notices (to avoid duplication)
		 */
		public function add_admin_notice( $slug, $class, $message ) {
			$this->notices[ $slug ] = array(
				'class'   => $class,
				'message' => $message,
			);
		}

		/**
		 * The backup sanity check, in case the plugin is activated in a weird way,
		 * or the environment changes after activation. Also handles upgrade routines.
		 */
		public function check_environment() {
			if ( ! defined( 'IFRAME_REQUEST' ) && ( WC_PAYCORE_VERSION !== get_option( 'wc_paycore_version' ) ) ) {
				$this->install();

				do_action( 'woocommerce_paycore_updated' );
			}

			$environment_warning = self::get_environment_warning();

			if ( $environment_warning && is_plugin_active( plugin_basename( __FILE__ ) ) ) {
				$this->add_admin_notice( 'bad_environment', 'error', $environment_warning );
			}

			// Check if secret key present. Otherwise prompt, via notice, to go to
			// setting.
			if ( ! class_exists( 'WC_Gateway_Paycore' ) && class_exists('WC_Payment_Gateway_CC')) {
				include_once( dirname( __FILE__ ) . '/includes/class-wc-gateway-paycore.php' );
				$secret = WC_Gateway_Paycore::get_secret_key();
			}

			if ( empty( $secret ) && ! ( isset( $_GET['page'], $_GET['section'] ) && 'wc-settings' === $_GET['page'] && 'paycore' === $_GET['section'] ) ) {
				$setting_link = $this->get_setting_link();
				$this->add_admin_notice( 'prompt_connect', 'notice notice-warning', sprintf( __( 'PayCore.io is almost ready. To get started, <a href="%s">set your PayCore.io account keys</a>.', 'woocommerce-gateway-paycore' ), $setting_link ) );
			}
		}

		/**
		 * Updates the plugin version in db
		 *
		 * @since 3.1.0
		 * @version 3.1.0
		 * @return bool
		 */
		private static function _update_plugin_version() {
			delete_option( 'wc_paycore_version' );
			update_option( 'wc_paycore_version', WC_PAYCORE_VERSION );

			return true;
		}

		/**
		 * Dismiss the Google Payment Request API Feature notice.
		 *
		 * @since 3.1.0
		 * @version 3.1.0
		 */
		public function dismiss_request_api_notice() {
			update_option( 'wc_paycore_show_request_api_notice', 'no' );
		}

		/**
		 * Handles upgrade routines.
		 *
		 * @since 3.1.0
		 * @version 3.1.0
		 */
		public function install() {
			if ( ! defined( 'WC_PAYCORE_INSTALLING' ) ) {
				define( 'WC_PAYCORE_INSTALLING', true );
			}

			$this->_update_plugin_version();
		}

		/**
		 * Checks the environment for compatibility problems.  Returns a string with the first incompatibility
		 * found or false if the environment has no problems.
		 */
		static function get_environment_warning() {
			if ( version_compare( phpversion(), WC_PAYCORE_MIN_PHP_VER, '<' ) ) {
				$message = __( 'WooCommerce PayCore.io - The minimum PHP version required for this plugin is %1$s. You are running %2$s.', 'woocommerce-gateway-paycore' );

				return sprintf( $message, WC_PAYCORE_MIN_PHP_VER, phpversion() );
			}

			if ( ! defined( 'WC_VERSION' ) ) {
				return __( 'WooCommerce PayCore.io requires WooCommerce to be activated to work.', 'woocommerce-gateway-paycore' );
			}

			if ( version_compare( WC_VERSION, WC_PAYCORE_MIN_WC_VER, '<' ) ) {
				$message = __( 'WooCommerce PayCore.io - The minimum WooCommerce version required for this plugin is %1$s. You are running %2$s.', 'woocommerce-gateway-paycore' );

				return sprintf( $message, WC_PAYCORE_MIN_WC_VER, WC_VERSION );
			}

			if ( ! function_exists( 'curl_init' ) ) {
				return __( 'WooCommerce PayCore.io - cURL is not installed.', 'woocommerce-gateway-paycore' );
			}

			return false;
		}

		/**
		 * Adds plugin action links
		 *
		 * @since 1.0.0
		 */
		public function plugin_action_links( $links ) {
			$setting_link = $this->get_setting_link();

			$plugin_links = array(
				'<a href="' . $setting_link . '">' . __( 'Settings', 'woocommerce-gateway-paycore' ) . '</a>',
				'<a href="https://docs.woocommerce.com/document/paycore/">' . __( 'Docs', 'woocommerce-gateway-paycore' ) . '</a>',
				'<a href="https://woocommerce.com/contact-us/">' . __( 'Support', 'woocommerce-gateway-paycore' ) . '</a>',
			);
			return array_merge( $plugin_links, $links );
		}

		/**
		 * Get setting link.
		 *
		 * @since 1.0.0
		 *
		 * @return string Setting link
		 */
		public function get_setting_link() {
			$use_id_as_section = function_exists( 'WC' ) ? version_compare( WC()->version, '2.6', '>=' ) : false;

			$section_slug = $use_id_as_section ? 'paycore' : strtolower( 'WC_Gateway_Paycore' );

			return admin_url( 'admin.php?page=wc-settings&tab=checkout&section=' . $section_slug );
		}

		/**
		 * Display any notices we've collected thus far (e.g. for connection, disconnection)
		 */
		public function admin_notices() {
			$show_request_api_notice = get_option( 'wc_paycore_show_request_api_notice' );

			if ( empty( $show_request_api_notice ) ) {
				// @TODO remove this notice in the future.
				?>

				<script type="application/javascript">
					jQuery( '.wc-paycore-request-api-notice' ).on( 'click', '.notice-dismiss', function() {
						var data = {
							action: 'paycore_dismiss_request_api_notice'
						};

						jQuery.post( '<?php echo admin_url( 'admin-ajax.php' ); ?>', data );
					});
				</script>

				<?php
			}

			foreach ( (array) $this->notices as $notice_key => $notice ) {
				echo "<div class='" . esc_attr( $notice['class'] ) . "'><p>";
				echo wp_kses( $notice['message'], array( 'a' => array( 'href' => array() ) ) );
				echo '</p></div>';
			}
		}

		/**
		 * Initialize the gateway. Called very early - in the context of the plugins_loaded action
		 *
		 * @since 1.0.0
		 */
		public function init_gateways() {
			if ( class_exists( 'WC_Subscriptions_Order' ) && function_exists( 'wcs_create_renewal_order' ) ) {
				$this->subscription_support_enabled = true;
			}

			if ( class_exists( 'WC_Pre_Orders_Order' ) ) {
				$this->pre_order_enabled = true;
			}

			if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
				return;
			}

			if ( class_exists( 'WC_Payment_Gateway_CC' ) ) {
				include_once( dirname( __FILE__ ) . '/includes/class-wc-gateway-paycore.php' );
			} else {
				include_once( dirname( __FILE__ ) . '/includes/legacy/class-wc-gateway-paycore.php' );
			}

			load_plugin_textdomain( 'woocommerce-gateway-paycore', false, plugin_basename( dirname( __FILE__ ) ) . '/languages' );
			add_filter( 'woocommerce_payment_gateways', array( $this, 'add_gateways' ) );
		}

		/**
		 * Add the gateways to WooCommerce
		 *
		 * @since 1.0.0
		 */
		public function add_gateways( $methods ) {
			if ( $this->subscription_support_enabled || $this->pre_order_enabled ) {
				$methods[] = 'WC_Gateway_Paycore_Addons';
			} else {
				$methods[] = 'WC_Gateway_Paycore';
			}
			return $methods;
		}

		/**
		 * List of currencies supported by Paycore that has no decimals.
		 *
		 * @return array $currencies
		 */
		public static function no_decimal_currencies() {
			return array(
				'bif', // Burundian Franc
				'djf', // Djiboutian Franc
				'jpy', // Japanese Yen
				'krw', // South Korean Won
				'pyg', // Paraguayan Guaraní
				'vnd', // Vietnamese Đồng
				'xaf', // Central African Cfa Franc
				'xpf', // Cfp Franc
				'clp', // Chilean Peso
				'gnf', // Guinean Franc
				'kmf', // Comorian Franc
				'mga', // Malagasy Ariary
				'rwf', // Rwandan Franc
				'vuv', // Vanuatu Vatu
				'xof', // West African Cfa Franc
			);
		}

		/**
		 * Paycore uses smallest denomination in currencies such as cents.
		 * We need to format the returned currency from Paycore into human readable form.
		 *
		 * @param object $balance_transaction
		 * @param string $type Type of number to format
		 */
		public static function format_number( $balance_transaction, $type = 'fee' ) {
			if ( ! is_object( $balance_transaction ) ) {
				return;
			}

			if ( in_array( strtolower( $balance_transaction->currency ), self::no_decimal_currencies() ) ) {
				if ( 'fee' === $type ) {
					return $balance_transaction->fee;
				}

				return $balance_transaction->net;
			}

			if ( 'fee' === $type ) {
				return number_format( $balance_transaction->fee / 100, 2, '.', '' );
			}

			return number_format( $balance_transaction->net / 100, 2, '.', '' );
		}

		/**
		 * Checks Paycore minimum order value authorized per currency
		 */
		public static function get_minimum_amount() {
			// Check order amount
			switch ( get_woocommerce_currency() ) {
				case 'USD':
				case 'CAD':
				case 'EUR':
				case 'CHF':
				case 'AUD':
				case 'SGD':
					$minimum_amount = 50;
					break;
				case 'GBP':
					$minimum_amount = 30;
					break;
				case 'DKK':
					$minimum_amount = 250;
					break;
				case 'NOK':
				case 'SEK':
					$minimum_amount = 300;
					break;
				case 'JPY':
					$minimum_amount = 5000;
					break;
				case 'MXN':
					$minimum_amount = 1000;
					break;
				case 'HKD':
					$minimum_amount = 400;
					break;
				default:
					$minimum_amount = 50;
					break;
			}

			return $minimum_amount;
		}

		/**
		 * What rolls down stairs
		 * alone or in pairs,
		 * and over your neighbor's dog?
		 * What's great for a snack,
		 * And fits on your back?
		 * It's log, log, log
		 */
		public static function log( $message ) {
			if ( empty( self::$log ) ) {
				self::$log = new WC_Logger();
			}

			self::$log->add( 'woocommerce-gateway-paycore', $message );
		}
	}

	$GLOBALS['WC_Paycore'] = WC_Paycore::get_instance();

endif;
