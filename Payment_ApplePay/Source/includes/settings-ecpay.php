<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return apply_filters( 'wc_ecpay_payment_settings',
	array(
		'enabled' => array(
			'title' 	=> __( 'Enable/Disable', 'ecpay' ),
			'type' 		=> 'checkbox',
			'label' 	=> __( 'Enable', 'ecpay' ),
			'default' 	=> 'no'
		),
		'title' => array(
			'title' 	=> __( 'Title', 'ecpay' ),
			'type' 		=> 'text',
			'description' 	=> __( 'This controls the title which the user sees during checkout.', 'ecpay' ),
			'default' 	=> __( 'ECPay', 'ecpay' ),
			'desc_tip'    => true,
		),
		'description' => array(
			'title' 	=> __( 'Description', 'ecpay' ),
			'type' 		=> 'textarea',
			'description' 	=> __( 'This controls the description which the user sees during checkout.', 'ecpay' ),
			'desc_tip'    => true,
		),
		'ecpay_test_mode' => array(
			'title' 	=> __( 'Test Mode', 'ecpay' ),
			'label' 	=> __( 'Enable', 'ecpay' ),
			'type' 		=> 'checkbox',
			'description' 	=> __( 'Test order will add date as prefix.', 'ecpay' ),
			'default' 	=> 'no',
			'desc_tip'    => true,
		),
		'ecpay_merchant_id' => array(
			'title' 	=> __( 'Merchant ID', 'ecpay' ),
			'type' 		=> 'text',
			'default' 	=> '2000132'
		),
		'ecpay_hash_key' => array(
			'title' 	=> __( 'Hash Key', 'ecpay' ),
			'type' 		=> 'text',
			'default' 	=> '5294y06JbISpM5x9'
		),
		'ecpay_hash_iv' => array(
			'title' 	=> __( 'Hash IV', 'ecpay' ),
			'type' 		=> 'text',
			'default' 	=> 'v77hoKGq4kWxNNIS'
		),
		'ecpay_payment_methods' => array(
			'title' 	=> __( 'Payment Method', 'ecpay' ),
			'type' 		=> 'multiselect',
			'description' 	=> __( 'Press CTRL and the right button on the mouse to select multi payments.', 'ecpay' ),
			'options' 	=> array(
				'Credit' 	=> $this->get_payment_desc('Credit'),
				'Credit_3' 	=> $this->get_payment_desc('Credit_3'),
				'Credit_6' 	=> $this->get_payment_desc('Credit_6'),
				'Credit_12' 	=> $this->get_payment_desc('Credit_12'),
				'Credit_18' 	=> $this->get_payment_desc('Credit_18'),
				'Credit_24' 	=> $this->get_payment_desc('Credit_24'),
				'WebATM' 	=> $this->get_payment_desc('WebATM'),
				'ATM' 		=> $this->get_payment_desc('ATM'),
				'CVS' 		=> $this->get_payment_desc('CVS'),
				'BARCODE' 	=> $this->get_payment_desc('BARCODE'),
				'ApplePay' 	=> $this->get_payment_desc('ApplePay')
			),
			'desc_tip'    => true,
		),
		'apple_pay_advanced' => array(
	                'title'       => __( 'Apple Pay設定', 'ecpay' ),
	                'type'        => 'title',
	                'description' => '',
	        ),
	        'apple_pay_check_button' => array(
	                'title'       => __( '<button type="button" id="apple_pay_ca_test">測試憑證</button>', 'ecpay' ),
	                'type'        => 'title',
	                'description' => '',
	        ),
		'ecpay_apple_pay_key_path' => array(
			'title'		=> __( 'key憑證路徑', 'ecpay' ),
			'type' 		=> 'text',
			'description' 	=> __( 'Apple Pay 憑證安裝絕對路徑，請勿安裝在public目錄中以防憑證遭竊', 'ecpay' ),
			'default' 	=> '/etc/httpd/ca/path/',
			'desc_tip'    	=> true,
		),
		'ecpay_apple_pay_crt_path' => array(
			'title'		=> __( 'crt憑證路徑', 'ecpay' ),
			'type' 		=> 'text',
			'description' 	=> __( 'Apple Pay 憑證安裝絕對路徑，請勿安裝在public目錄中以防憑證遭竊', 'ecpay' ),
			'default' 	=> '/etc/httpd/ca/path/',
			'desc_tip'    	=> true,
		),
		'ecpay_apple_pay_key_pass' => array(
			'title'		=> __( '憑證密碼', 'ecpay' ),
			'type' 		=> 'password',
			'description' 	=> __( 'Apple Pay 憑證密碼', 'ecpay' ),
			'default' 	=> '',
			'desc_tip'    	=> true,
		),
		'ecpay_apple_display_name' => array(
			'title'		=> __( '註冊名稱', 'ecpay' ),
			'type' 		=> 'text',
			'description' 	=> __( 'Apple Pay 註冊名稱', 'ecpay' ),
			'default' 	=> '',
			'desc_tip'    	=> true,
		)
		/*
		,'ecpay_apple_pay_button' => array(
			'title'       	=> __( 'Apple Pay Button Style', 'ecpay' ),
			'label'       	=> __( 'Button Style', 'ecpay' ),
			'type'        	=> 'select',
			'description' 	=> __( 'Select the button style you would like to show.', 'ecpay' ),
			'default'     	=> 'black',
			'desc_tip'    	=> true,
			'options'     	=> array(
				'black' => __( 'Black', 'ecpay' ),
				'white' => __( 'White', 'ecpay' ),
			),
		),
		*/
	)
);
