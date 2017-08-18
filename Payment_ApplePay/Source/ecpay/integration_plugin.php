<?php
/**
 * @copyright Copyright (c) 2016 Green World FinTech Service Co., Ltd. (https://www.ecpay.com.tw)
 * @version 1.1.0801
 *
 * Plugin Name: ECPay Payment
 * Plugin URI: https://www.ecpay.com.tw
 * Description: ECPay Integration Payment Gateway for WooCommerce
 * Version: 1.1.0801
 * Author: ECPay Green World FinTech Service Co., Ltd. 
 * Author URI: https://www.ecpay.com.tw
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Required minimums and constants
 */
define( 'WC_ECPAY_VERSION', '3.1.6' );
define( 'WC_ECPAY_MIN_PHP_VER', '5.0.0' );
define( 'WC_ECPAY_MIN_WC_VER', '2.5.0' );
define( 'WC_ECPAY_MAIN_FILE', __FILE__ );
define( 'WC_ECPAY_PLUGIN_URL', untrailingslashit( plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) ) ) );
define( 'WC_ECPAY_PLUGIN_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );

if ( ! class_exists( 'WC_Ecpay_Payment' ) )
{

	class WC_Ecpay_Payment {
		
		/**
		*
		*/
		private static $instance;

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
		 * Notices (array)
		 * @var array
		 */
		public $notices = array();

		protected function __construct() {
			add_action( 'admin_init', array( $this, 'check_environment' ) );
			add_action( 'admin_notices', array( $this, 'admin_notices' ), 15 );
			add_action( 'plugins_loaded', array( $this, 'init' ) );

			add_action('wp_footer', array( $this, 'ecpay_integration_plugin_init_payment_method' ) ); 
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
		* check_environment
		*/
		public function check_environment() {
			$environment_warning = self::get_environment_warning();

			if ( $environment_warning && is_plugin_active( plugin_basename( __FILE__ ) ) ) {
				$this->add_admin_notice( 'bad_environment', 'error', $environment_warning );
			}
		}

		/**
		 * Checks the environment for compatibility problems.  Returns a string with the first incompatibility
		 * found or false if the environment has no problems.
		 */
		static function get_environment_warning() {

			if ( version_compare( phpversion(), WC_ECPAY_MIN_PHP_VER, '<' ) ) {
				$message = __( 'WooCommerce Ecpay Gateway - The minimum PHP version required for this plugin is %1$s. You are running %2$s.', 'ecpay' );

				return sprintf( $message, WC_ECPAY_MIN_PHP_VER, phpversion() );
			}

			if ( ! defined( 'WC_VERSION' ) ) {
				return __( 'WooCommerce Ecpay Gateway  requires WooCommerce to be activated to work.', 'ecpay' );
			}

			if ( version_compare( WC_VERSION, WC_ECPAY_MIN_WC_VER, '<' ) ) {
				$message = __( 'WooCommerce Ecpay Gateway  - The minimum WooCommerce version required for this plugin is %1$s. You are running %2$s.', 'ecpay' );

				return sprintf( $message, WC_ECPAY_MIN_WC_VER, WC_VERSION );
			}

			if ( ! function_exists( 'curl_init' ) ) {
				return __( 'WooCommerce Ecpay Gateway  - cURL is not installed.', 'ecpay' );
			}

			return false;
		}

		/**
		 * Display any notices we've collected thus far (e.g. for connection, disconnection)
		 */
		public function admin_notices() {

			foreach ( (array) $this->notices as $notice_key => $notice ) {
				echo "<div class='" . esc_attr( $notice['class'] ) . "'><p>";
				echo wp_kses( $notice['message'], array( 'a' => array( 'href' => array() ) ) );
				echo '</p></div>';
			}
		}

		/**
		*
		*/
		public function init_gateways() {

			if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
				return;
			}

			if ( class_exists( 'WC_Payment_Gateway_CC' ) ) {
				include_once( dirname( __FILE__ ) . '/includes/ECPay.Payment.Integration.php' );	// 載入SDK
				include_once( dirname( __FILE__ ) . '/includes/class-wc-gateway-ecpay.php' );
				include_once( dirname( __FILE__ ) . '/includes/class-wc-ecpay-apple-pay.php' );
			}

			// 載入語系檔
			load_plugin_textdomain( 'ecpay', false, plugin_basename( dirname( __FILE__ ) ) . '/languages' );

			add_filter( 'woocommerce_payment_gateways', array( $this, 'add_gateways' ) );
		}

		/**
		 * Add the gateways to WooCommerce
		 */
		public function add_gateways( $methods ) {
			$methods[] = 'WC_Gateway_Ecpay';
			return $methods;
		}

		public function ecpay_integration_plugin_init_payment_method() {
			?>
			<script>
				(function(){
	                if (
	                    document.getElementById("shipping_option") !== null && 
	                    typeof document.getElementById("shipping_option") !== "undefined"
	                ) {
	                    if (window.addEventListener) {
					        window.addEventListener('DOMContentLoaded', initPaymentMethod, false);
					    } else {
					        window.attachEvent('onload', initPaymentMethod);
					    }
	                }
				})();
				function initPaymentMethod() {
				    var e = document.getElementById("shipping_option");
				    var shipping = e.options[e.selectedIndex].value;
				    var payment = document.getElementsByName('payment_method');
				    
				    if (
	                    shipping == "HILIFE_Collection" ||
	                    shipping == "FAMI_Collection" ||
	                    shipping == "UNIMART_Collection"
	                ) {
	                    var i;
	                   
	                    for (i = 0; i< payment.length; i++) {
	                        if (payment[i].id != 'payment_method_ecpay_shipping_pay') {
	                            payment[i].style.display="none";

	                            checkclass = document.getElementsByClassName("wc_payment_method " + payment[i].id).length;

	                            if (checkclass == 0) {
	                                var x = document.getElementsByClassName(payment[i].id);
	                                x[0].style.display = "none";
	                            } else {
	                                var x = document.getElementsByClassName("wc_payment_method " + payment[i].id);
	                                x[0].style.display = "none";
	                            }
	                        } else {
	                            checkclass = document.getElementsByClassName("wc_payment_method " + payment[i].id).length;

	                            if (checkclass == 0) {
	                                var x = document.getElementsByClassName(payment[i].id);
	                                x[0].style.display = "";
	                            } else {
	                                var x = document.getElementsByClassName("wc_payment_method " + payment[i].id);
	                                x[0].style.display = "";
	                            }
	                        }
	                    }
	                    document.getElementById('payment_method_ecpay').checked = false;
	                    document.getElementById('payment_method_ecpay_shipping_pay').checked = true;
	                    document.getElementById('payment_method_ecpay_shipping_pay').style.display = '';
	                } else {
	                    var i;
	                    for (i = 0; i< payment.length; i++) {
	                        if (payment[i].id != 'payment_method_ecpay_shipping_pay') {
	                            payment[i].style.display=""; 

	                            checkclass = document.getElementsByClassName("wc_payment_method " + payment[i].id).length;

	                            if (checkclass == 0) {
	                                var x = document.getElementsByClassName(payment[i].id);
	                                x[0].style.display = "";
	                            } else {
	                                var x = document.getElementsByClassName("wc_payment_method " + payment[i].id);
	                                x[0].style.display = "";
	                            }
	                        } else {
	                            checkclass = document.getElementsByClassName("wc_payment_method " + payment[i].id).length;

	                            if (checkclass == 0) {
	                                var x = document.getElementsByClassName(payment[i].id);
	                                x[0].style.display = "none";
	                            } else {
	                                var x = document.getElementsByClassName("wc_payment_method " + payment[i].id);
	                                x[0].style.display = "none";
	                            }

	                            document.getElementById('payment_method_ecpay').checked = true;
	                            document.getElementById('payment_method_ecpay_shipping_pay').checked = false;
	                            document.getElementById('payment_method_ecpay_shipping_pay').style.display = "none";
	                        }
	                    }
	                }
				}
			</script>
			<?php
		}
	}

	$GLOBALS['wc_ecpay_payment'] = WC_Ecpay_Payment::get_instance();
}

?>