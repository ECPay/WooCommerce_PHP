<?php

defined( 'ABSPATH' ) or exit;

/**
 * Settings
 *
 * Adds UX for adding/modifying model
 *
 * @since 2.0.0
 */
class WC_ECPayinvoice_Settings extends WC_Settings_Page {


	/**
	 * Add various admin hooks/filters
	 *
	 * @since 2.0.0
	 */
	public function __construct() {

		$this->id    = 'ecpayinvoice';
		$this->label = __( 'ECPay電子發票', 'woocommerce-ecpayinvoice' );

		parent::__construct();

		$this->model = get_option( 'wc_ecpayinvoice_active_model', array() );
	}

	/**
	 * Get sections
	 *
	 * @return array
	 */
	public function get_sections() {

		return array(
			'config'    => __( 'ECPay電子發票', 'woocommerce-ecpayinvoice' )
		);
	}

	/**
	 * Render the settings for the current section
	 *
	 * @since 2.0.0
	 */
	public function output() {

		$settings = $this->get_settings();

		// inject the actual setting value before outputting the fields
		// ::output_fields() uses get_option() but model are stored
		// in a single option so this dynamically returns the correct value
		foreach ( $this->model as $filter => $value ) {

			add_filter( "pre_option_{$filter}", array( $this, 'get_customization' ) );
		}

		WC_Admin_Settings::output_fields( $settings );
	}


	/**
	 * Return the customization value for the given filter
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function get_customization() {

		$filter = str_replace( 'pre_option_', '', current_filter() );

		return isset( $this->model[ $filter ] ) ? $this->model[ $filter ] : '';
	}


	/**
	 * Save the model
	 *
	 * @since 2.0.0
	 */
	public function save() {

		foreach ( $this->get_settings() as $field ) {

			// skip titles, etc
			if ( ! isset( $field['id'] ) ) {
				continue;
			}

			if ( ! empty( $_POST[ $field['id'] ] ) ) {

				$this->model[ $field['id'] ] = wp_kses_post( stripslashes( $_POST[ $field['id'] ] ) );

			} elseif ( isset( $this->model[ $field['id'] ] ) ) {

				unset( $this->model[ $field['id'] ] );
			}
		}

		update_option( 'wc_ecpayinvoice_active_model', $this->model );
	}


	/**
	 * Return admin fields in proper format for outputting / saving
	 *
	 * @since 1.1
	 * @return array
	 */
	public function get_settings() {

		$settings = array(

			'config' =>

				array(

					array(
						'title' => __( '介接參數設定', 'woocommerce-ecpayinvoice' ),
						'type'  => 'title'
					),

					array(
						'id'       => 'wc_ecpay_invoice_enabled',
						'title'    => __( '是否啟用' ),
						'desc_tip' => __( '啟用ECPay電子發票', 'woocommerce-ecpayinvoice' ),
						'type'     => 'select',
						'options'  => array(
							'enable'  => __( '啟用', 'woocommerce-ecpayinvoice' ),
							'disable' => __( '停用', 'woocommerce-ecpayinvoice' ),
						),
						'default'  => 'manual',
					),

					array(
						'id'       => 'wc_ecpay_invoice_testmode',
						'title'    => __( '測試模式' ),
						'desc_tip' => __( '啟用測試模式', 'woocommerce-ecpayinvoice' ),
						'type'     => 'select',
						'options'  => array(
							'enable_testmode'  => __( '啟用', 'woocommerce-ecpayinvoice' ),
							'disable_testmode' => __( '停用', 'woocommerce-ecpayinvoice' ),
						),
						'default'  => 'disable_testmode',
					),

					array(
						'id'       => 'wc_ecpay_invoice_merchantid',
						'title'    => __( '商家編號', 'woocommerce-ecpayinvoice' ),
						'desc_tip' => __( 'MerchantID', 'woocommerce-ecpayinvoice' ),
						'type'     => 'text',
						'default' => '2000132'
					),

					array(
						'id'       => 'wc_ecpay_invoice_hashkey',
						'title'    => __( 'HashKey', 'woocommerce-ecpayinvoice' ),
						'desc_tip' => __( '請輸入ECPay所提供的HashKey。', 'woocommerce-ecpayinvoice' ),
						'type'     => 'text',
						'default' => 'ejCk326UnaZWKisg'
					),

					array(
						'id'       => 'wc_ecpay_invoice_hashiv',
						'title'    => __( 'HashIV', 'woocommerce-ecpayinvoice' ),
						'desc_tip' => __( '請輸入ECPay所提供的HashIV。', 'woocommerce-ecpayinvoice' ),
						'type'     => 'text',
						'default' => 'q9jcZX8Ib9LM8wYk'
					),

					array(
						'id'       => 'wc_ecpay_invoice_auto',
						'title'    => __( '發票開立方式' ),
						'desc_tip' => __( '請選擇開立方式，啟用自動開立，當使用ECAPY金流付款完成後，將自動開立電子發票', 'woocommerce-ecpayinvoice' ),
						'type'     => 'select',
						'options'  => array(
							'manual'  => __( '手動開立', 'woocommerce-ecpayinvoice' ),
							'auto' => __( '自動開立', 'woocommerce-ecpayinvoice' ),
						),
						'default'  => 'manual',
					),

					array( 'type' => 'sectionend' )

				),
		);

		$current_section = isset( $GLOBALS['current_section'] ) ? $GLOBALS['current_section'] : 'config';

		return isset( $settings[ $current_section ] ) ?  $settings[ $current_section ] : $settings['config'];
	}


}

// setup settings
return wc_ecpayinvoice()->settings = new WC_ECPayinvoice_Settings();
