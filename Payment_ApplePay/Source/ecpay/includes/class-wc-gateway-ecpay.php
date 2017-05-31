<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_Gateway_Ecpay extends WC_Payment_Gateway {

	var $ecpay_test_mode;
	var $ecpay_merchant_id;
	var $ecpay_hash_key;
	var $ecpay_hash_iv;
	var $ecpay_choose_payment;
	var $ecpay_payment_methods;


	public function __construct() {

		$this->id = 'ecpay';
		$this->method_title = __('ECPay', 'ecpay');
		$this->method_description   =  __('ECPay is the most popular payment gateway for online shopping in Taiwan', 'ecpay');
		$this->has_fields = true;
		$this->icon = apply_filters('woocommerce_ecpay_icon', plugins_url('images/icon.png', dirname( __FILE__ )));
		
		# Load the form fields
		$this->init_form_fields();
		
		# Load the administrator settings
		$this->init_settings();

		$this->title 			= $this->get_option('title');
		$this->description 		= $this->get_option('description');
		$this->ecpay_test_mode 		= $this->get_option('ecpay_test_mode');
		$this->ecpay_merchant_id 	= $this->get_option('ecpay_merchant_id');
		$this->ecpay_hash_key 		= $this->get_option('ecpay_hash_key');
		$this->ecpay_hash_iv 		= $this->get_option('ecpay_hash_iv');
		$this->ecpay_payment_methods 	= $this->get_option('ecpay_payment_methods');

		//$this->ecpay_apple_pay 		= 'yes' === $this->get_option( 'apple_pay', 'yes' );
		$this->ecpay_apple_pay_ca_path	= 'yes' === $this->get_option( 'apple_pay_domain_set', 'no' );
		$this->ecpay_apple_pay_button	= $this->get_option( 'apple_pay_button', 'black' );
		
		# Register a action to save administrator settings
		add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
		

		# Register a action to redirect to ECPay payment center
		add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));
		
		# Register a action to process the callback
		add_action('woocommerce_api_wc_gateway_' . $this->id, array($this, 'receive_response'));

		
		
	}


	/**
	* 載入參數設定欄位
	*/
	public function init_form_fields () {
		$this->form_fields = include( untrailingslashit( plugin_dir_path( WC_ECPAY_MAIN_FILE ) ) . '/includes/settings-ecpay.php' );
	}

	/**
	 * Display the form when chooses ECPay payment
	 */
	public function payment_fields() {
		if (!empty($this->description)) {
			echo $this->add_next_line($this->description . '<br /><br />');
		}
		echo __('Payment Method', 'ecpay') . ' : ';
		echo $this->add_next_line('<select name="ecpay_choose_payment">');
		foreach ($this->ecpay_payment_methods as $payment_method) {
			echo $this->add_next_line('  <option value="' . $payment_method . '">');
			echo $this->add_next_line('    ' . $this->get_payment_desc($payment_method));
			echo $this->add_next_line('  </option>');
		}
		echo $this->add_next_line('</select>');
	}




	/**
	 * Check the payment method and the chosen payment
	 */
	public function validate_fields() {
		$choose_payment = $_POST['ecpay_choose_payment'];
		$payment_desc = $this->get_payment_desc($choose_payment);
		if ($_POST['payment_method'] == $this->id && !empty($payment_desc)) {
			$this->ecpay_choose_payment = $choose_payment;
			return true;
		} else {
			wc_add_notice( __( 'Invalid payment method.' ).$payment_desc, 'error' );
			return false;
		}
	}

	/**
	 * Process the payment
	 */
	public function process_payment($order_id) {
		# Update order status
		$order = new WC_Order($order_id);
		$order->update_status('pending', __('Awaiting ECPay payment', 'ecpay'));
		
		# Set the ECPay payment type to the order note
		$order->add_order_note($this->ecpay_choose_payment, true);
		
		return array(
			'result' => 'success',
			'redirect' => $order->get_checkout_payment_url(true)
		);
	}

	/**
	 * Redirect to ECPay
	 */
	public function receipt_page($order_id) {
		# Clean the cart
		global $woocommerce;
		$woocommerce->cart->empty_cart();
		
		//找出訂單資訊
		$order = new WC_Order($order_id);
		$notes = $order->get_customer_order_notes();
		
		if($notes[0]->comment_content == 'ApplePay')
		{
			$gateway_settings = get_option( 'woocommerce_ecpay_settings', '' );

			// 載入CSS
			wp_enqueue_style( 'ecpay_apple_pay', plugins_url( 'assets/css/ecpay-apple-pay.css', WC_ECPAY_MAIN_FILE ), array());

			// 載入JS
			wp_enqueue_script( 'ecpay_apple_pay', plugins_url( 'assets/js/ecpay-apple-pay.js', WC_ECPAY_MAIN_FILE ), array(), null, true);

			// 參數往前端送
			$ecpay_params = array(
				'lable'         => get_option('blogname'),
				'ajaxurl'       => admin_url().'admin-ajax.php',
				'total' 	=> $order->get_total(),
				'display_name' 	=> $gateway_settings['ecpay_apple_display_name'],
				'order_id' 	=> $order_id,
				'site_url' 	=> get_site_url(),
				'server_https' 	=> $_SERVER['HTTPS'],
				'test_mode' 	=> $this->ecpay_test_mode
			);

			wp_localize_script( 'ecpay_apple_pay', 'wc_ecpay_apple_pay_params', $ecpay_params );

			?>
			<div class="apple-pay-button-wrapper">
				<button class="apple-pay-button" id="apple-pay-button" style="display: none;" lang="<?php echo esc_attr( $this->apple_pay_button_lang ); ?>" style="-webkit-appearance: -apple-pay-button; -apple-pay-button-type: buy; -apple-pay-button-style: <?php echo esc_attr( $this->apple_pay_button ); ?>;" ></button>
			</div>

			<?php

			
		}
		else
		{

			try {

				$aio = new ECPay_AllInOne();
				$aio->Send['MerchantTradeNo'] = '';
				$service_url = '';
				if ($this->ecpay_test_mode == 'yes') {
					$service_url = 'https://payment-stage.ecpay.com.tw/Cashier/AioCheckOut/v2';
					$aio->Send['MerchantTradeNo'] = date('YmdHis');
				} else {
					$service_url = 'https://payment.ecpay.com.tw/Cashier/AioCheckOut/v2';
				}
				$aio->MerchantID 		= $this->ecpay_merchant_id;
				$aio->HashKey 			= $this->ecpay_hash_key;
				$aio->HashIV 			= $this->ecpay_hash_iv;
				$aio->ServiceURL 		= $service_url;
				$aio->Send['ReturnURL'] 	= add_query_arg('wc-api', 'WC_Gateway_ECPay', home_url('/'));
				$aio->Send['ClientBackURL'] 	= home_url('?page_id=' . get_option('woocommerce_myaccount_page_id') . '&view-order=' . $order->id);;
				$aio->Send['MerchantTradeNo'] 	.= $order->id;
				$aio->Send['MerchantTradeDate'] = date('Y/m/d H:i:s');
				
				
				# Set the product info
				$aio->Send['TotalAmount'] = $order->get_total();
				array_push(
					$aio->Send['Items'],
					array(
						'Name' 		=> '網路商品一批',
						'Price' 	=> $aio->Send['TotalAmount'],
						'Currency' 	=> $order->get_order_currency(),
						'Quantity' 	=> 1
					)
				);
				
				$aio->Send['TradeDesc'] = 'ecpay_module_woocommerce_v1.0.10420';
				
				# Get the chosen payment and installment
				$notes = $order->get_customer_order_notes();
				$choose_payment = '';
				$choose_installment = '';
				if (isset($notes[0])) {
					list($choose_payment, $choose_installment) = explode('_', $notes[0]->comment_content);
				}
				$aio->Send['ChoosePayment'] = $choose_payment;
				
				# Set the extend information
				switch ($aio->Send['ChoosePayment']) {
					case 'Credit':
						# Do not support UnionPay
						$aio->SendExtend['UnionPay'] = false;
						
						# Credit installment parameters
						if (!empty($choose_installment)) {
							$aio->SendExtend['CreditInstallment'] = $choose_installment;
							$aio->SendExtend['InstallmentAmount'] = $aio->Send['TotalAmount'];
							$aio->SendExtend['Redeem'] = false;
						}
						break;
					case 'WebATM':
						break;
					case 'ATM':
						$aio->SendExtend['ExpireDate'] = 3;
						$aio->SendExtend['PaymentInfoURL'] = $aio->Send['ReturnURL'];
						break;
					case 'CVS':
					case 'BARCODE':
						$aio->SendExtend['Desc_1'] = '';
						$aio->SendExtend['Desc_2'] = '';
						$aio->SendExtend['Desc_3'] = '';
						$aio->SendExtend['Desc_4'] = '';
						$aio->SendExtend['PaymentInfoURL'] = $aio->Send['ReturnURL'];
						break;
					default:
						throw new Exception(__('Invalid payment method.', 'ecpay')); 
						break;
				}
				$aio->CheckOut();
				exit;
			} catch(Exception $e) {
				wc_add_notice($e->getMessage(), 'error');
			}
		}
		
	}

	/**
	 * Process the callback
	 */
	public function receive_response() {
		$result_msg = '1|OK';
		$order = null;
		try {
			# Retrieve the check out result

			$aio = new ECPay_AllInOne();
			$aio->HashKey = $this->ecpay_hash_key;
			$aio->HashIV = $this->ecpay_hash_iv;
			$aio->MerchantID = $this->ecpay_merchant_id;
			$ecpay_feedback = $aio->CheckOutFeedback();
			unset($aio);
			if(count($ecpay_feedback) < 1) {
				throw new Exception('Get ECPay feedback failed.');
			} else {
				# Get the cart order id
				$cart_order_id = $ecpay_feedback['MerchantTradeNo'];
				if ($this->ecpay_test_mode == 'yes') {
					$cart_order_id = substr($ecpay_feedback['MerchantTradeNo'], 14);
				}
				


				# Get the cart order amount
				$order = new WC_Order($cart_order_id);
				$cart_amount = $order->get_total();

				# Check the amounts
				$ecpay_amount = $ecpay_feedback['TradeAmt'];
				if (round($cart_amount) != $ecpay_amount) {
					throw new Exception('Order ' . $cart_order_id . ' amount are not identical.');
				}
				else
				{
					# Set the common comments
					$comments = sprintf(
						__('Payment Method : %s<br />Trade Time : %s<br />', 'ecpay'), 
						$ecpay_feedback['PaymentType'],
						$ecpay_feedback['TradeDate']
					);
					
					# Set the getting code comments
					$return_code = $ecpay_feedback['RtnCode'];
					$return_message = $ecpay_feedback['RtnMsg'];
					$get_code_result_comments = sprintf(
						__('Getting Code Result : (%s)%s', 'ecpay'),
						$return_code,
						$return_message
					);
					
					# Set the payment result comments
					$payment_result_comments = sprintf(
						__('Payment Result : (%s)%s', 'ecpay'),
						$return_code,
						$return_message
					);
					
					# Set the fail message
					$fail_message = sprintf('Order %s Exception.(%s: %s)', $cart_order_id, $return_code, $return_message);
					
					# Get ECPay payment method
					$ecpay_payment_method = $this->get_payment_method($ecpay_feedback['PaymentType']);

					# Set the order comments
					switch($ecpay_payment_method) {
						case ECPay_PaymentMethod::Credit:
						case ECPay_PaymentMethod::WebATM:
							if ($return_code != 1 and $return_code != 800) {
								throw new Exception($fail_msg);
							} else {
								if (!$this->is_order_complete($order)) {
									$this->confirm_order($order, $payment_result_comments, $ecpay_feedback);
								} else {
									# The order already paid or not in the standard procedure, do nothing
								}
							}
							break;
						case ECPay_PaymentMethod::ATM:
							if ($return_code != 1 and $return_code != 2 and $return_code != 800) {
								throw new Exception($fail_msg);
							} else {
								if ($return_code == 2) {
									# Set the getting code result
									$comments .= $this->get_order_comments($ecpay_feedback);
									$comments .= $get_code_result_comments;
									$order->add_order_note($comments);
								} else {
									if (!$this->is_order_complete($order)) {
										$this->confirm_order($order, $payment_result_comments, $ecpay_feedback);
									} else {
										# The order already paid or not in the standard procedure, do nothing
									}
								}
							}
							break;
						case ECPay_PaymentMethod::CVS:
						case ECPay_PaymentMethod::BARCODE:
							if ($return_code != 1 and $return_code != 800 and $return_code != 10100073) {
								throw new Exception($fail_msg);
							} else {
								if ($return_code == 10100073) {
									# Set the getting code result
									$comments .= $this->get_order_comments($ecpay_feedback);
									$comments .= $get_code_result_comments;
									$order->add_order_note($comments);
								} else {
									if (!$this->is_order_complete($order)) {
										$this->confirm_order($order, $payment_result_comments, $ecpay_feedback);
									} else {
										# The order already paid or not in the standard procedure, do nothing
									}
								}
							}
							break;
						default:
							throw new Exception('Invalid payment method of the order ' . $cart_order_id . '.');
							break;
					}
				}
			}
		} catch (Exception $e) {
			$error = $e->getMessage();
			if (!empty($order)) {
				$comments .= sprintf(__('Faild To Pay<br />Error : %s<br />', 'ecpay'), $error);
				$order->update_status('failed', $comments);
			}
			
			# Set the failure result
			$result_msg = '0|' . $error;
		}
		echo $result_msg;
		exit;
	}


	# Custom function

	/**
	 * Get the payment method description
	 * @param  string   payment name
	 * @return string   payment method description
	 */
	private function get_payment_desc($payment_name) {
		$payment_desc = array(
			'Credit' 	=> __('Credit', 'ecpay'),
			'Credit_3' 	=> __('Credit(3 Installments)', 'ecpay'),
			'Credit_6' 	=> __('Credit(6 Installments)', 'ecpay'),
			'Credit_12' 	=> __('Credit(12 Installments)', 'ecpay'),
			'Credit_18' 	=> __('Credit(18 Installments)', 'ecpay'),
			'Credit_24' 	=> __('Credit(24 Installments)', 'ecpay'),
			'WebATM' 	=> __('WEB-ATM', 'ecpay'),
			'ATM' 		=> __('ATM', 'ecpay'),
			'CVS' 		=> __('CVS', 'ecpay'),
			'BARCODE' 	=> __('BARCODE', 'ecpay'),
			'ApplePay' 	=> __('ApplePay', 'ecpay')
		);
		
		return $payment_desc[$payment_name];
	}

	/**
	 * Add a next line character
	 * @param  string   content
	 * @return string   content with next line character
	 */
	private function add_next_line($content) {
		return $content . "\n";
	}


	/**
	 * Format the version description
	 * @param  string   version string
	 * @return string   version description
	 */
	private function format_version_desc($version) {
		return str_replace('.', '_', $version);
	}

	/**
	 * Check if the order status is complete
	 * @param  object   order
	 * @return boolean  is the order complete
	 */
	private function is_order_complete($order) {
		$status = '';
		$status = (method_exists($Order,'get_status') == true )? $order->get_status(): $order->status;

		if ($status == 'pending') {
			return false;
		} else {
			return true;
		}
	}

	/**
	 * Get the payment method from the payment_type
	 * @param  string   payment type
	 * @return string   payment method
	 */
	private function get_payment_method($payment_type) {
		$info_pieces = explode('_', $payment_type);
		
		return $info_pieces[0];
	}

	/**
	 * Get the order comments
	 * @param  array    ECPay feedback
	 * @return string   order comments
	 */
	function get_order_comments($ecpay_feedback)
	{
		$comments = array(
			'ATM' => 
				sprintf(
				  	__('Bank Code : %s<br />Virtual Account : %s<br />Payment Deadline : %s<br />', 'ecpay'),  
					$ecpay_feedback['BankCode'],
					$ecpay_feedback['vAccount'],
					$ecpay_feedback['ExpireDate']
				),
			'CVS' => 
				sprintf(
					__('Trade Code : %s<br />Payment Deadline : %s<br />', 'ecpay'),
					$ecpay_feedback['PaymentNo'],
					$ecpay_feedback['ExpireDate']
				),
			'BARCODE' => 
				sprintf(
					__('Payment Deadline : %s<br />BARCODE 1 : %s<br />BARCODE 2 : %s<br />BARCODE 3 : %s<br />', 'ecpay'),
					$ecpay_feedback['ExpireDate'],
					$ecpay_feedback['Barcode1'],
					$ecpay_feedback['Barcode2'],
					$ecpay_feedback['Barcode3']
				)
		);
		$payment_method = $this->get_payment_method($ecpay_feedback['PaymentType']);
		
		return $comments[$payment_method];
	}

	/**
	 * Complete the order and add the comments
	 * @param  object   order
	 */
	function confirm_order($order, $comments, $ecpay_feedback) {
		$order->add_order_note($comments, true);
		
		$order->payment_complete();

		// call invoice model
		$invoice_active_ecpay 	= 0 ;
		$invoice_active_allpay 	= 0 ;

		$active_plugins = (array) get_option( 'active_plugins', array() );

		$active_plugins = array_merge( $active_plugins, get_site_option( 'active_sitewide_plugins', array() ) );

		foreach($active_plugins as $key => $value)
		{
			if ( (strpos($value,'/woocommerce-ecpayinvoice.php') !== false))
	                {
	                        $invoice_active_ecpay = 1;
	                }

	                if ( (strpos($value,'/woocommerce-allpayinvoice.php') !== false))
	                {
	                        $invoice_active_allpay = 1;
	                }			
		}

		if($invoice_active_ecpay == 0 && $invoice_active_allpay == 1)		// allpay
		{
			if( is_file( get_home_path().'/wp-content/plugins/allpay_invoice/woocommerce-allpayinvoice.php') )
			{
				$aConfig_Invoice = get_option('wc_allpayinvoice_active_model') ;

				if(isset($aConfig_Invoice) && $aConfig_Invoice['wc_allpay_invoice_enabled'] == 'enable' && $aConfig_Invoice['wc_allpay_invoice_auto'] == 'auto' )
				{
					do_action('allpay_auto_invoice', $order->id, $ecpay_feedback['SimulatePaid']);
				}
			}
		}
		elseif($invoice_active_ecpay == 1 && $invoice_active_allpay == 0)	//ecpay
		{
			if( is_file( get_home_path().'/wp-content/plugins/ecpay_invoice/woocommerce-ecpayinvoice.php') )
			{
				$aConfig_Invoice = get_option('wc_ecpayinvoice_active_model') ;

				if(isset($aConfig_Invoice) && $aConfig_Invoice['wc_ecpay_invoice_enabled'] == 'enable' && $aConfig_Invoice['wc_ecpay_invoice_auto'] == 'auto' )
				{
					do_action('ecpay_auto_invoice', $order->id, $ecpay_feedback['SimulatePaid']);
				}
			}
		}
	}

	
}

?>