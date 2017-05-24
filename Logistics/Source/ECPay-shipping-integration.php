<?php
    /**
     * @copyright  Copyright (c) 2015 EcPay (http://www.ecpay.com.tw)
     * @version 1.0.0106
     *
     * Plugin Name:  ecPay_shipping
     * Plugin URI: https://www.ecpay.com.tw/
     * Description: EcPay Shipping Integration Payment Gateway for WooCommerce
     * Version: 1.0.0106
     * Author: ECPay Green World FinTech Service Co., Ltd.
     * Author URI: http://www.ecpay.com.tw
     */

require_once 'ECPay.Logistics.Integration.php';


if (!class_exists('EcPay_Shipping_Options')) {
    
    function EcPay_Shipping_Options_init() {
    
        class EcPay_Shipping_Options extends WC_Shipping_Method {
      
            public $MerchantID;
            public $HashKey;
            public $HashIV;
            public $EcPay_Logistics = array(
                                            'B2C' => array(
                                                        'FAMI'              =>'全家',
                                                        'FAMI_Collection'   =>'全家取貨付款',
                                                        'UNIMART'           =>'統一超商',
                                                        'UNIMART_Collection'=>'統一超商寄貨便取貨付款'
                                                      ),
                                            'C2C' => array(
                                                        'FAMI'              =>'全家',
                                                        'FAMI_Collection'   =>'全家取貨付款',
                                                        'UNIMART'           =>'統一超商',
                                                        'UNIMART_Collection'=>'統一超商寄貨便取貨付款'
                                                      )
                                        );

            public $SenderName;
            public $SenderPhone;
            public $ecpaylogistic_min_amount;
            public $ecpaylogistic_max_amount;
            public $cartAmount;

            public function __construct() 
            {

                global $woocommerce;
                $chosen_methods = array();
                if(method_exists($woocommerce->session, 'get') &&($woocommerce->session->get( 'chosen_shipping_methods' )!=null)) $chosen_methods = $woocommerce->session->get( 'chosen_shipping_methods' );
    
                if(in_array('ecpay_shipping', $chosen_methods)){
                    add_filter( 'woocommerce_checkout_fields' , array(&$this, 'custom_override_checkout_fields'));
                }

                $this->id = 'ecpay_shipping';
                $this->method_title = "綠界科技超商取貨";
                $this->title = "綠界科技超商取貨";
                $this->options_array_label = '綠界科技超商取貨';
                $this->method_description = '';
                add_action('woocommerce_update_options_shipping_' . $this->id, array(&$this, 'process_admin_options'));
                add_action( 'woocommerce_update_options_shipping_' . $this->id, array(&$this, 'process_shipping_options' ) );

                $this->init();

                // add the action 
                add_action( 'woocommerce_admin_order_data_after_order_details', array(&$this,'action_woocommerce_admin_order_data_after_shipping_address' ));  
            }

            /**
             * Init settings
             *
             * @access public
             * @return void
             */
            function init() {
                // Load the settings API
                global $woocommerce;
                $this->init_form_fields();
                $this->init_settings();
                
                // Define user set variables
                $this->title        = $this->get_option('title' );
                $this->type         = $this->get_option('type' );
                $this->fee          = $this->get_option('fee' );
                $this->type         = $this->get_option('type' );
                $this->codes        = $this->get_option('codes' );
                $this->availability = $this->get_option('availability' );
                $this->testMode     = $this->get_option('testMode');
                $this->countries    = $this->get_option('countries' );
                $this->category     = $this->get_option('category');
                $this->MerchantID   = $this->get_option('ecpay_merchant_id');
                $this->HashKey      = $this->get_option('ecpay_hash_key');
                $this->HashIV       = $this->get_option('ecpay_hash_iv') ;
                $this->SenderName   = $this->get_option('sender_name');
                $this->SenderPhone  = $this->get_option('sender_phone');
                $this->SenderCellPhone = $this->get_option('sender_cell_phone');
                $this->ecpaylogistic_min_amount = $this->get_option('ecpaylogistic_min_amount');
                $this->ecpaylogistic_max_amount = $this->get_option('ecpaylogistic_max_amount');
                $this->orderStatus  = $this->get_option('ecpaylogistic_order_status'); 
                //$this->cartAmount   = $woocommerce->cart->cart_contents_total ;



                $this->get_shipping_options();
                
                add_filter('woocommerce_shipping_methods', array(&$this, 'add_wcso_shipping_methods'));
                add_action('woocommerce_cart_totals_after_shipping', array(&$this, 'wcso_review_order_shipping_options'));
                add_action('woocommerce_review_order_after_shipping', array(&$this, 'wcso_review_order_shipping_options'));
                add_action('woocommerce_checkout_update_order_meta', array(&$this, 'wcso_field_update_shipping_order_meta'), 10, 2);
                add_action( 'init', array(&$this, 'register_status'));
                add_filter( 'wc_order_statuses',array(&$this,'add_statuses'));
                
                
                if (is_admin()) {
                    add_action( 'woocommerce_admin_order_data_after_shipping_address', array(&$this, 'wcso_display_shipping_admin_order_meta'), 10, 2 );
                }
            }


            public function register_status() 
            {
                register_post_status('wc-ecpay', 
                                        array(
                                        'label'                  => '已出貨',
                                        'public'                 => true,
                                        'exclude_from_search'    => true,
                                        'show_in_admin_all_list'    => true,
                                        'show_in_admin_status_list' => true,
                                        'label_count'               => _n_noop( $this->orderStatus.' <span class="count">(%s)</span>', $this->orderStatus.'商品已出貨<span class="count">(%s)</span>' )
                                    ) 
                );
            }
       
            //custom order status
            public function add_statuses( $order_statuses  ) 
            {
                $new_order_statuses = array();
                // add new order status after processing
                foreach ( $order_statuses as $key => $status ) {
                    $new_order_statuses[ $key ] = $status;
                }
                $new_order_statuses['wc-ecpay'] = $this->orderStatus;
               
                return $new_order_statuses;
            }


            # 訂單詳細頁面的產生物流單按鈕
            function action_woocommerce_admin_order_data_after_shipping_address() { 
            try{

                global $woocommerce, $post;
                define('Plugin_URL', plugins_url());

                //訂單資訊
                $orderInfo = get_post_meta($post->ID);
                             
                //物流子類型
                $subType = "";
                if(array_key_exists('ecPay_shipping', $orderInfo) && $this->category =="B2C"){
                    if($orderInfo['ecPay_shipping'][0] == 'FAMI_Collection' || $orderInfo['ecPay_shipping'][0]=='FAMI') {
                        $subType = "FAMI";
                    }else{
                        $subType = "UNIMART";
                    }
                }else{
                    if($orderInfo['ecPay_shipping'][0] == 'FAMI_Collection' || $orderInfo['ecPay_shipping'][0] =='FAMI'){
                        $subType = "FAMIC2C";
                    }else{
                        $subType = "UNIMARTC2C";
                    }
                }

                //是否代收貨款
                $IsCollection = "N";
                if($orderInfo['ecPay_shipping'][0] == "FAMI_Collection" || $orderInfo['ecPay_shipping'][0]=="UNIMART_Collection"){
                    $IsCollection = "Y";
                }

                             
                $orderObj = new WC_Order($post->ID);
                $itemsInfo = $orderObj->get_items();

                //訂單的商品
                $items = array();

                /*
                foreach ($itemsInfo as $key => $value) {
                    $items[] = $value['name'];
                }
                */
                
                $items[] = '網路商品一批';


                //訂單金額
                $temp = explode('.', $orderInfo['_order_total'][0]);
                $totalPrice = $temp[0];
                
                
                $AL = new EcpayLogistics();
                $AL->HashKey = $this->HashKey;
                $AL->HashIV  = $this->HashIV;
                $AL->Send = array(
                                'MerchantID'           => $this->MerchantID,
                                'MerchantTradeNo'      => ($this->testMode == 'yes')? $post->ID.date("mdHis") : $post->ID,
                                'MerchantTradeDate'    => date('Y/m/d H:i:s'),
                                'LogisticsType'        => LogisticsType::CVS ,
                                'LogisticsSubType'     => $subType,
                                'GoodsAmount'          => (int)$totalPrice,
                                'CollectionAmount'     => (int)$totalPrice,
                                'IsCollection'         => $IsCollection,
                                'GoodsName'            => implode('#', $items),
                                'SenderName'           => $this->SenderName,
                                'SenderPhone'          => $this->SenderPhone,
                                'SenderCellPhone'      => $this->SenderCellPhone,
                                'ReceiverName'         => $orderInfo['_billing_first_name'][0].$orderInfo['_billing_last_name'][0],
                                'ReceiverPhone'        => $orderInfo['_billing_phone'][0],
                                'ReceiverCellPhone'    => $orderInfo['_billing_phone'][0],
                                'ReceiverEmail'        => $orderInfo['_billing_email'][0],
                                'TradeDesc'            => '',
                                'ServerReplyURL'       => add_query_arg('wc-api', 'WC_Gateway_Ecpay_Logis', home_url('/')),
                                'LogisticsC2CReplyURL' => add_query_arg('wc-api', 'WC_Gateway_Ecpay_Logis', home_url('/')),
                                'Remark'               => $orderObj->customer_message,
                                'PlatformID'           => ''
                            );

                
                $AL->SendExtend = array(
                                'ReceiverStoreID' => (array_key_exists('_billing_CVSStoreID', $orderInfo))? $orderInfo['_billing_CVSStoreID'][0] : $orderInfo['_CVSStoreID'][0],
                                'ReturnStoreID'   => (array_key_exists('_billing_CVSStoreID', $orderInfo))? $orderInfo['_billing_CVSStoreID'][0] : $orderInfo['_CVSStoreID'][0]
                            );

                

                
                       
                //狀態為完成or已出貨，後台隱藏建立物流單按鈕                        
                if($orderObj->post_status != 'wc-ecpay' && $orderObj->post_status != 'wc-completed'){
                    $html = $AL->CreateShippingOrder('物流訂單建立','Map');
                    echo "</form>".$html;
                    echo "<input class='button' type='button' value='建立物流訂單' onclick='create();'> ";
                
            ?>
                    <form id="ecpayChangeStoreForm" method="post" target="ecpay" action="https://logistics.ecpay.com.tw/Express/map" style="display:none">
                        <input type="hidden" id="MerchantID" name="MerchantID" value="<?php echo $this->MerchantID?>" /><br />
                        <input type="hidden" id="MerchantTradeNo" name="MerchantTradeNo" value="<?php echo $post->ID;?>" /><br />
                        <input type="hidden" id="LogisticsSubType" name="LogisticsSubType" value="<?php echo $subType;?>" /><br />
                        <input type="hidden" id="IsCollection" name="IsCollection" value="N" /><br />
                        <input type="hidden" id="ServerReplyURL" name="ServerReplyURL" value="<?php echo Plugin_URL ."/ecpay_shipping/getChangeResponse.php";?>" /><br />
                        <input type="hidden" id="ExtraData" name="ExtraData" value="" /><br />
                        <input type="hidden" id="Device" name="Device" value="0" /><br />
                        <input type="hidden" id="LogisticsType" name="LogisticsType" value="CVS" /><br />
                    </form>
            <?php
                    echo "<input class='button' type='button' onclick='changeStore();' value='變更門市' /><br />";
                }
            }catch(Exception $e){
                echo $e->getMessage();
            }
                
            ?>
            <script type="text/javascript">
                function create(){
                    var ecPayshipping = document.getElementById('ECPayForm');
                    map = window.open('','Map',config='height=500px,width=900px');
                    if(map){
                        ecPayshipping.submit();
                    }
                }

                function changeStore(){
                    var changeStore = document.getElementById('ecpayChangeStoreForm');
                    map = window.open('','ecpay',config='height=790px,width=1020px');
                    if(map){
                        changeStore.submit();
                    }
                }
                    
                (function(){
                    document.getElementById('__paymentButton').style.display = 'none';
                })();
            </script>
            <?php
            }

            function custom_override_checkout_fields($fields)
            {
                $fields['billing']['purchaserStore'] = array(
                    'label'         => '門市名稱',//名称
                    'required'      =>  true,//是否必填项
                    'clear'     => true
                );
                $fields['billing']['purchaserAddress'] = array(
                    'label'         => '門市地址',//名称
                    'required'      =>  true,//是否必填项
                    'clear'     => true
                );
                $fields['billing']['purchaserPhone'] = array(
                    'label'         => '門市電話',//名称
                    'clear'     => true
                );
                $fields['billing']['CVSStoreID'] = array(
                    'required'  =>  true,//是否必填项
                    'class'     => array('hidden')   
                );

                return $fields;
            } 


            /**
            * calculate_shipping function.
            *
            * @access public
            * @param array $package (default: array())
            * @return void
            */
            function calculate_shipping($package = array()) {
                $shipping_total = 0;
                $fee = ( trim($this->fee) == '' ) ? 0 : $this->fee;

/*
                if ($this->type == 'fixed')
                    $shipping_total = $this->fee;

                if ($this->type == 'percent')
                    $shipping_total = $package['contents_cost'] * ( $this->fee / 100 );

                if ($this->type == 'product') {
                    foreach ($package['contents'] as $item_id => $values) {
                        $_product = $values['data'];

                        if ($values['quantity'] > 0 && $_product->needs_shipping()) {
                            $shipping_total += $this->fee * $values['quantity'];
                        }
                    }
                }
*/

                $shipping_total = $fee ;

                $rate = array(
                    'id' => $this->id,
                    'label' => $this->title,
                    'cost' => $shipping_total
                );

                $this->add_rate($rate);
            }

            /**
             * init_form_fields function.
             *
             * @access public
             * @return void
             */
            function init_form_fields() {
                $this->form_fields = array(
                    'enabled' => array(
                        'title' => '是否啟用',
                        'type' => 'checkbox',
                        'label' => '啟用綠界科技超商取貨',
                        'default' => 'no'
                    ),
                    'title' => array(
                        'title' => '名稱',
                        'type' => 'text',
                        'description' => __('This controls the title which the user sees during checkout.', 'woocommerce'),
                        'default' => '綠界科技超商取貨',
                        'desc_tip' => true,
                    ),
                    // 'type' => array(
                    //     'title' => __('Fee Type', 'woocommerce'),
                    //     'type' => 'select',
                    //     'description' => __('How to calculate delivery charges', 'woocommerce'),
                    //     'default' => 'fixed',
                    //     'options' => array(
                    //         'fixed' => __('Fixed amount', 'woocommerce'),
                    //         'percent' => __('Percentage of cart total', 'woocommerce'),
                    //         'product' => __('Fixed amount per product', 'woocommerce'),
                    //     ),
                    //     'desc_tip' => true,
                    // ),
                    'testMode' => array(
                        'title' => "測試模式",
                        'type' => 'checkbox',
                        'label' => '啟用測試模式',
                        'default' => 'no'
                    ),
                    'category' => array(
                        'title' => "物流類型",
                        'type' => 'select',
                        'options' => array('B2C'=>'B2C','C2C'=>'C2C')
                    ),
                    'ecpay_merchant_id' => array(
                        'title' => "特店編號",
                        'type' => 'text',
                        'default' => '2000132'
                    ),
                    'ecpay_hash_key' => array(
                        'title' => "物流介接Hash_Key",
                        'type' => 'text',
                        'default' => '5294y06JbISpM5x9'
                    ),
                    'ecpay_hash_iv' => array(
                        'title' => "物流介接Hash_IV",
                        'type' => 'text',
                        'default' => 'v77hoKGq4kWxNNIS'
                    ),
                    'sender_name' => array(
                        'title' => "寄件人名稱",
                        'type' => 'text',
                        'default' => 'ECPAY'
                    ),
                    'sender_cell_phone' => array(
                        'title' => "寄件人手機",
                        'type' => 'text',
                        'default' => ''
                    ),
                    'sender_phone' => array(
                        'title' => "寄件人電話",
                        'type' => 'text',
                        'default' => ''
                    ),
                    'ecpaylogistic_min_amount' => array(
                        'title' => "超商取貨最低金額",
                        'type' => 'text',
                        'default' => '10'
                    ),
                    'ecpaylogistic_max_amount' => array(
                        'title' => "超商取貨最高金額",
                        'type' => 'text',
                        'default' => '19999'
                    ),
                    'ecpaylogistic_order_status' => array(
                        'title' => "自訂物流單訂單狀態",
                        'type' => 'text',
                        'default' => '商品已出貨'
                    ),

                    'fee' => array(
                        'title' => '運費',//__('Delivery Fee', 'woocommerce'),
                        'type' => 'price',
                        'description' => __('What fee do you want to charge for local delivery, disregarded if you choose free. Leave blank to disable.', 'woocommerce'),
                        'default' => '',
                        'desc_tip' => true,
                        'placeholder' => wc_format_localized_price(0)
                    ),
                    'shipping_options_table' => array(
                        'type' => 'shipping_options_table'
                    )                
                );
            }
            
            /**
            * admin_options function.
            *
            * @access public
            * @return void
            */
           function admin_options() 
           {
            ?>
                <h3><?php echo $this->method_title; ?></h3>
                <p><?php _e( 'Local delivery is a simple shipping method for delivering orders locally.', 'woocommerce' ); ?></p>
                <table class="form-table">
                    <?php $this->generate_settings_html(); ?>
                </table>
            <?php
           }
           
           /**
             * is_available function.
             *
             * @access public
             * @param array $package
             * @return bool
             */
            function is_available($package) {

                global $woocommerce;
               
                if(( $woocommerce->cart->cart_contents_total < $this->ecpaylogistic_min_amount) || ( $woocommerce->cart->cart_contents_total > $this->ecpaylogistic_max_amount))return false;

                //if($total > 100) return false;

                if ($this->enabled == "no")
                    return false;

                // If post codes are listed, let's use them.
                $codes = '';
                if ($this->codes != '') {
                    foreach (explode(',', $this->codes) as $code) {
                        $codes[] = $this->clean($code);
                    }
                }

                if (is_array($codes)) {

                    $found_match = false;

                    if (in_array($this->clean($package['destination']['postcode']), $codes)) {
                        $found_match = true;
                    }

                    // Pattern match
                    if (!$found_match) {
                        $customer_postcode = $this->clean($package['destination']['postcode']);
                        foreach ($codes as $c) {
                            $pattern = '/^' . str_replace('_', '[0-9a-zA-Z]', $c) . '$/i';
                            if (preg_match($pattern, $customer_postcode)) {
                                $found_match = true;
                                break;
                            }
                        }
                    }


                    // Wildcard search
                    if (!$found_match) {
                        $customer_postcode = $this->clean($package['destination']['postcode']);
                        $customer_postcode_length = strlen($customer_postcode);

                        for ($i = 0; $i <= $customer_postcode_length; $i++) {
                            if (in_array($customer_postcode, $codes)) {
                                $found_match = true;
                            }
                            $customer_postcode = substr($customer_postcode, 0, -2) . '*';
                        }
                    }

                    if (!$found_match) {
                        return false;
                    }
                }
                if ($this->availability == 'specific') {
                    $ship_to_countries = $this->countries;
                } else {
                    $ship_to_countries = array_keys(WC()->countries->get_shipping_countries());
                }

                if (is_array($ship_to_countries)) {
                    if (!in_array($package['destination']['country'], $ship_to_countries)) {
                        return false;
                    }
                }

                // Yay! We passed!
                return apply_filters('woocommerce_shipping_' . $this->id . '_is_available', true, $package);
            }

            /**
             * clean function.
             *
             * @access public
             * @param mixed $code
             * @return string
             */
            function clean($code) {
                return str_replace('-', '', sanitize_title($code)) . ( strstr($code, '*') ? '*' : '' );
            }
            
            /**
            * validate_shipping_options_table_field function.
            *
            * @access public
            * @param mixed $key
            * @return bool
            */
            function validate_shipping_options_table_field( $key ) {
                return false;
            }
            
            /**
             * generate_options_table_html function.
             *
             * @access public
             * @return string
             */
            function generate_shipping_options_table_html() {
                
                ob_start();
                ?>
                    <tr valign="top">
                        <th scope="row" class="titledesc">運送項目:</th>
                        <td class="forminp" id="<?php echo $this->id; ?>_options">
                        <table class="shippingrows widefat" cellspacing="0">
                            <tbody>
                            <?php
                                foreach ($this->EcPay_Logistics['B2C'] as $key => $value) {
                            ?>
                                <tr class="option-tr">
                                    <td><input type="checkbox" name="<?php echo $key;?>" value="<?php echo $key; ?>" <?php if(in_array($key, $this->shipping_options)) echo 'checked';?>> <?php echo $value; ?></td>
                                </tr>
                            <?php }?>
                            </tbody>
                        </table>
                        </td>
                    </tr>
                <?php
                return ob_get_clean();
            }
            
            /**
             * process_shipping_options function.
             *
             * @access public
             * @return void
             */
            function process_shipping_options() {
                
                $options = array();
                foreach ($this->EcPay_Logistics[$this->category] as $key => $value) {
                    if(array_key_exists($key, $_POST)) $options[] = $key ;    
                }
                
                update_option($this->id, $options);
                $this->get_shipping_options();
            }

            /**
            * get_shipping_options function.
            *
            * @access public
            * @return void
            */
            function get_shipping_options() {
                $this->shipping_options = array_filter( (array) get_option( $this->id ) );
            }
           
            //前台購物車顯示option
            function wcso_review_order_shipping_options() {
                global $woocommerce;
                try{
                    define('Plugin_URL', plugins_url());
                   

                    $chosen_method = $woocommerce->session->get('chosen_shipping_methods');
                   

                    if (is_array($chosen_method) && in_array($this->id, $chosen_method))
                    {
                        

                        if(is_checkout() && (($woocommerce->session->cart_contents_total >= $this->ecpaylogistic_min_amount) || ($woocommerce->session->cart_contents_total <= $this->ecpaylogistic_max_amount)))
                        {
                            $shipping_name = $this->EcPay_Logistics[$this->category];
                            
                            $cvsObj = new EcpayLogistics();
                            $cvsObj->Send = array(
                                                'MerchantID' => $this->MerchantID,
                                                'MerchantTradeNo' => 'no' . date('YmdHis'),
                                                'LogisticsSubType' => LogisticsSubType::UNIMART,
                                                'IsCollection' => IsCollection::NO,
                                                'ServerReplyURL' => Plugin_URL . '/ecpay_shipping/getResponse.php',
                                                'ExtraData' => '',
                                                'Device' => '0'
                                            );

                            // CvsMap(Button 名稱, Form target)
                            $html = $cvsObj->CvsMap('電子地圖','ecpay');
                            echo '<input type="hidden" id="category" value='.$this->category.'>';
                            echo '<tr class="shipping_option">';
                            echo '<th>' . $this->method_title . '</th>';
                            echo '<td><select name="shipping_option" class="input-select" id="shipping_option">';
                            echo '<option>------</option>';
                            foreach ($this->shipping_options as $option) {
                                echo '<option value="' . esc_attr($option) . '">' . $shipping_name[$option] . '</option>';
                            }
                            echo '</select>'.$html.'</td></tr>';

                            add_filter( 'woocommerce_checkout_fields' , 'custom_override_checkout_fields');                          
                           
                        }

                     ?>
                        <script>
                            
                            document.getElementById("__paymentButton").onclick=function(){
                                if(document.getElementById('shipping_option').value=="------"){ 
                                    alert('請選擇物流方式'); return false;
                                }
                                if(confirm('提醒您，因使用FB及LINE APP內建瀏覽器進行操作時會發生網頁空白的問題，建議您可先複製商品連結後使用其他瀏覽器重新購買。')){
                                    var Field = document.getElementById('purchaserStore');
                                    if(Field == null) location.reload();
                                    map = window.open('','ecpay',config='height=790px,width=1020px');
                                    if(map){
                                        document.getElementById("ECPayForm").submit();
                                    }
                                }else{
                                    return false;
                                }
                            };

                            document.getElementById("shipping_option").onchange=function(){

                                
                                var e = document.getElementById("shipping_option");
                                var shipping = e.options[e.selectedIndex].value;
                                var category = document.getElementById('category').value;
                                var payment = document.getElementsByName('payment_method');

                                if(category = 'C2C'){
                                    if(shipping =='FAMI' || shipping == 'FAMI_Collection'){
                                        document.getElementById('LogisticsSubType').value = 'FAMIC2C'; 
                                    }else{
                                        document.getElementById('LogisticsSubType').value = 'UNIMARTC2C';
                                    }
                                }else{
                                    if(shipping =='FAMI' || shipping == 'FAMI_Collection'){
                                        document.getElementById('LogisticsSubType').value = 'FAMI'; 
                                    }else{
                                        document.getElementById('LogisticsSubType').value = 'UNIMART';
                                    }
                                }



                                if(shipping == "FAMI_Collection" || shipping =="UNIMART_Collection"){
                                    var i;
                                   
                                    for (i = 0; i< payment.length; i++) {
                                        if(payment[i].id != 'payment_method_ecpay_shipping_pay')
                                        {
                                            payment[i].style.display="none";

                                            checkclass = document.getElementsByClassName("wc_payment_method "+payment[i].id).length;

                                            if(checkclass == 0)
                                            {
                                                var x = document.getElementsByClassName(payment[i].id);
                                                x[0].style.display = "none";  
                                            }
                                            else
                                            {
                                                var x = document.getElementsByClassName("wc_payment_method "+payment[i].id);
                                                x[0].style.display = "none";
                                            }

                                        }
                                        else
                                        {
                                            checkclass = document.getElementsByClassName("wc_payment_method "+payment[i].id).length;

                                            if(checkclass == 0)
                                            {
                                                var x = document.getElementsByClassName(payment[i].id);
                                                x[0].style.display = "";  
                                            }
                                            else
                                            {
                                                var x = document.getElementsByClassName("wc_payment_method "+payment[i].id);
                                                x[0].style.display = "";
                                            }
                                        }                                     
                                    }
                                    document.getElementById('payment_method_ecpay_shipping_pay').checked = true;
                                    document.getElementById('payment_method_ecpay_shipping_pay').style.display = '';

                                }else{
                                    var i;
                                    for (i = 0; i< payment.length; i++) {
                                        if(payment[i].id != 'payment_method_ecpay_shipping_pay')
                                        {
                                            payment[i].style.display=""; 

                                            checkclass = document.getElementsByClassName("wc_payment_method "+payment[i].id).length;

                                            if(checkclass == 0)
                                            {
                                                var x = document.getElementsByClassName(payment[i].id);
                                                x[0].style.display = "";  
                                            }
                                            else
                                            {
                                                var x = document.getElementsByClassName("wc_payment_method "+payment[i].id);
                                                x[0].style.display = "";
                                            }
                                        } 
                                        else
                                        {
                                            checkclass = document.getElementsByClassName("wc_payment_method "+payment[i].id).length;

                                            if(checkclass == 0)
                                            {
                                                var x = document.getElementsByClassName(payment[i].id);
                                                x[0].style.display = "none";  
                                            }
                                            else
                                            {
                                                var x = document.getElementsByClassName("wc_payment_method "+payment[i].id);
                                                x[0].style.display = "none";
                                            }


                                            document.getElementById('payment_method_ecpay_shipping_pay').checked = false;
                                            document.getElementById('payment_method_ecpay_shipping_pay').style.display = "none";
                                        }                                  
                                    }
                                    
                                }
                                                                
                            };

                        </script>
                    <?php
                    
                    }
                    
                    function filter_gateways( $gateways )
                    {
                        foreach ($gateways as $key => $value) 
                        {
                             if($key == "ecpay_shipping_pay")
                                {
                                    unset($gateways[$key]);
                                }
                        }

                        return $gateways;
                    }

                    if($chosen_method[0]!='ecpay_shipping')add_filter( 'woocommerce_available_payment_gateways', "filter_gateways" );
                }
                catch(Exception $e)
                {
                    echo $e->getMessage();
                }


            }
            
            function wcso_field_update_shipping_order_meta( $order_id, $posted ) {
                global $woocommerce;
                if (is_array($posted['shipping_method']) && in_array($this->id, $posted['shipping_method'])) {
                    if ( isset( $_POST['shipping_option'] ) && !empty( $_POST['shipping_option'] ) ) {
                        update_post_meta( $order_id, 'ecPay_shipping', sanitize_text_field( $_POST['shipping_option'] ) );
                        $woocommerce->session->_chosen_shipping_option = sanitize_text_field( $_POST['shipping_option'] );
                    }
                } else { //visible  in cart, hidden in checkout
                    $chosen_method = $woocommerce->session->get('chosen_shipping_methods');
                    $chosen_option= $woocommerce->session->_chosen_shipping_option;
                    if (is_array($chosen_method) && in_array($this->id, $chosen_method) && $chosen_option) {
                        update_post_meta( $order_id, 'wcso_shipping_option', $woocommerce->session->_chosen_shipping_option );
                    }
                }
            }
          
            function wcso_display_shipping_admin_order_meta($order){
                $selected_option = get_post_meta( $order->id);
                
                if ($selected_option) {
                    echo '<p><strong>' . $this->title . ':</strong> ' . get_post_meta( $order->id, 'ecPay_shipping', true ) . '</p>';
                }
            }
            
            function add_wcso_shipping_methods( $methods ) {
                $methods[] = $this; 
                return $methods;
            }
            
        }
        
        new EcPay_Shipping_Options();





    }


    add_action( 'wp_ajax_wcso_save_selected', 'save_selected' );  
    add_action( 'wp_ajax_nopriv_wcso_save_selected', 'save_selected' );
    
    function save_selected() {
        if ( isset( $_GET['shipping_option'] ) && !empty( $_GET['shipping_option'] ) ) {
            global $woocommerce;
            $selected_option = $_GET['shipping_option'];
            $woocommerce->session->_chosen_shipping_option = sanitize_text_field( $selected_option );
        }
        die();
    }
}
    
    if(is_admin()){
        add_action('plugins_loaded', 'EcPay_Shipping_Options_init');
        add_filter( 'woocommerce_admin_billing_fields', 'EcPay_custom_admin_billing_fields' );
    }else{
        add_action('woocommerce_shipping_init', 'EcPay_Shipping_Options_init');
    }

    function EcPay_custom_admin_billing_fields($fields){
        global $theorder;
        
  
        $fields['purchaserStore'] = array(
            'label' => __( '門市名稱', 'purchaserStore' ),
            'value' =>get_post_meta( $theorder->id, '_purchaserStore', true ),
            'show'  => true
        );
      
        $fields['purchaserAddress'] = array(
            'label' => __( '門市地址', 'purchaserAddress' ),
            'value' =>get_post_meta( $theorder->id, '_purchaserAddress', true ),
            'show'  => true
        );
      
        $fields['purchaserPhone'] = array(
            'label' => __( '門市電話', 'purchaserPhone' ),
            'value' =>get_post_meta( $theorder->id, '_purchaserPhone', true ),
            'show'  => true
        );

        $fields['CVSStoreID'] = array(
            'label' => __( '門市代號', 'CVSStoreID' ),
            'value' =>get_post_meta( $theorder->id, '_CVSStoreID', true ),
            'show'  => true
        );

      
        return $fields;
    }  


    function my_custom_checkout_field_save( $order_id )
    {
        // custom field
        $purchaserStore   = '_purchaserStore'  ;
        $purchaserAddress = '_purchaserAddress';
        $purchaserPhone   = '_purchaserPhone'  ;
        $CVSStoreID       = '_CVSStoreID'      ;
        // save custom field to order 
        if( !empty($_POST['purchaserStore']) && !empty($_POST['purchaserAddress']) ){
            update_post_meta( $order_id, $purchaserStore  , wc_clean( $_POST['purchaserStore'] ) );
            update_post_meta( $order_id, $purchaserAddress, wc_clean( $_POST['purchaserAddress'] ) );
            update_post_meta( $order_id, $purchaserPhone  , wc_clean( $_POST['purchaserPhone'] ) );
            update_post_meta( $order_id, $CVSStoreID  , wc_clean( $_POST['CVSStoreID'] ) );
        }    
    }

    add_action('woocommerce_checkout_update_order_meta', 'my_custom_checkout_field_save' );
    add_action('plugins_loaded', 'ecpay_integration_plugin_init2', 0);
    

    
    function ecpay_integration_plugin_init2() {
        # Make sure WooCommerce is setted.
        if (!class_exists('WC_Payment_Gateway')) {
            return;
        }

        class WC_Gateway_Ecpay_Logis extends WC_Payment_Gateway {
            
            public function __construct() {
                # Load the translation
                $this->id = 'ecpay_shipping_pay';
                $this->icon = '';
                $this->has_fields = false;
                $this->method_title = '綠界科技超商取貨付款';
                $this->method_description = "若使用綠界科技超商取貨，請開啟此付款方式";

                
                $this->init_form_fields();
                $this->init_settings();
                $this->title = $this->get_option( 'title' );

                $this->ecpay_payment_methods = $this->get_option('ecpay_payment_methods');
                
                # Register a action to save administrator settings
                add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
                
                # Register a action to redirect to ecPay payment center
                add_action( 'woocommerce_thankyou_cheque', array( $this, 'thankyou_page' ) );
                
                // Customer Emails
                add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 ); 
                               
                # Register a action to process the callback
                add_action('woocommerce_api_wc_gateway_ecpay_logis', array($this, 'receive_response'));

            }
            
            /**
             * Initialise Gateway Settings Form Fields
             */
            public function init_form_fields () {
                $this->form_fields = array(
                    'enabled' => array(
                        'title'   => __( 'Enable/Disable', 'woocommerce' ),
                        'type'    => 'checkbox',
                        'label'   => '啟用綠界科技超商取貨付款',
                        'default' => 'yes'
                    ),
                    'title' => array(
                        'title'       => __( 'Title', 'woocommerce' ),
                        'type'        => 'text',
                        'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
                        'default'     => "綠界科技超商取貨付款",
                        'desc_tip'    => true,
                    )
                );

            }
            
            
            /**
             * Check the payment method and the chosen payment
             */
            public function validate_fields() {
                return true;
            }
            
            /**
             * Process the payment
             */
            public function process_payment($order_id) {
                # Update order status
                $order = wc_get_order( $order_id );
                $order->update_status( 'on-hold', '綠界科技超商取貨' );
                $order->reduce_order_stock();
                WC()->cart->empty_cart();
               
                return array(
                    'result'    => 'success',
                    'redirect'  => $this->get_return_url( $order )
                );
            }
            
            
            /**
             * Process the callback
             */
            public function receive_response() {
                $response = $_REQUEST;

                //若為測試模式，拆除時間參數
                $MerchantTradeNo = (($response['MerchantID']=='2000132')||($response['MerchantID']=='2000933'))? strrev(substr(strrev($response['MerchantTradeNo']),10)) : $response['MerchantTradeNo'];
                
                if(!empty($response['CVSStoreName']) && !empty($response['CVSAddress']))
                    $this->receive_changeStore_response($response);
                
                if($response['RtnCode'] == '300'){
                    $order = wc_get_order( $MerchantTradeNo );
                    $order->update_status( 'ecpay', "商品已出貨" );
                }
                echo '1|OK';
                exit;
            }


            public function receive_changeStore_response($response = array()){
                //若為測試模式，拆除時間參數
                $MerchantTradeNo = (($response['MerchantID']=='2000132')||($response['MerchantID']=='2000933'))? strrev(substr(strrev($response['MerchantTradeNo']),10)) : $response['MerchantTradeNo'];
                
                $order = wc_get_order( $MerchantTradeNo );
                $order_status = $order->get_status();

                $order->add_order_note("會員已更換門市", 0 ,false );
                
                //訂單更新門市訊息
                update_post_meta($MerchantTradeNo, '_CVSStoreID', $response['CVSStoreID']);
                update_post_meta($MerchantTradeNo, '_purchaserStore', $response['CVSStoreName']);
                update_post_meta($MerchantTradeNo, '_purchaserAddress', $response['CVSAddress']);
                update_post_meta($MerchantTradeNo, '_purchaserPhone', $response['CVSTelephone']);
                update_post_meta($MerchantTradeNo, '_billing_CVSStoreID', $response['CVSStoreID']);
                update_post_meta($MerchantTradeNo, '_billing_purchaserStore', $response['CVSStoreName']);
                update_post_meta($MerchantTradeNo, '_billing_purchaserAddress', $response['CVSAddress']);
                update_post_meta($MerchantTradeNo, '_billing_purchaserPhone', $response['CVSTelephone']);

                ?>
                <script type="text/javascript">
                <!--
                    window.close();
                    alert('門市已更換，請重新整理頁面');
                //-->
                </script> 
                <?php
                exit;
            }
            
            
        }

        /**
         * Add the Gateway Plugin to WooCommerce
         * */
        function woocommerce_add_ecpay_plugin2($methods) {
            $methods[] = 'WC_Gateway_Ecpay_Logis';
            return $methods;
        }

        add_filter('woocommerce_payment_gateways', 'woocommerce_add_ecpay_plugin2');
    }


    add_action('woocommerce_checkout_process', 'my_custom_checkout_field_process');

    function my_custom_checkout_field_process() {
        // Check if set, if its not set add an error.
        global $woocommerce;
        $shipping_method = $woocommerce->session->get( 'chosen_shipping_methods' );

        if ($shipping_method[0] == "ecpay_shipping" && (! $_POST['purchaserStore']) )
            wc_add_notice( __( '請選擇取貨門市' ), 'error' );
    }



    add_action('woocommerce_order_details_after_order_table', 'my_custom_checkout_field_update_order_receipt', 10, 1 );
    function my_custom_checkout_field_update_order_receipt($order)
    {
        $obj = new WC_Order($order->post->ID);
        $shipping = $obj->get_items('shipping');
        
        $is_ecpayShipping = 'N';
        foreach ($shipping as $key => $value) {
            if($value['method_id'] == 'ecPay_shipping') $is_ecpayShipping = 'Y';
        }
        
        if ($is_ecpayShipping == 'N') return; 
        
        $map = '';
        switch (get_post_meta( $order->id,'ecPay_shipping' ,true)) {
            case 'variaFAMI_Collectionble':
            case 'FAMI_Collection':
                $LogisticsSubType = 'FAMI';
                break;
                
            default:
                $LogisticsSubType = 'UNIMART';
                break;
        }
        
        echo '門市代號: '; 
        echo  (array_key_exists('_billing_CVSStoreID', get_post_meta($order->id))) ?get_post_meta( $order->id, '_billing_CVSStoreID', true ) . '<br>' :get_post_meta( $order->id, '_CVSStoreID', true ) . '<br>';
        echo '門市名稱: ';
        echo  (array_key_exists('_billing_purchaserStore', get_post_meta($order->id))) ?get_post_meta( $order->id, '_billing_purchaserStore', true ) . '<br>' :get_post_meta( $order->id, '_purchaserStore', true ) . '<br>';
        echo '門市地址: '; 
        echo  (array_key_exists('_billing_purchaserAddress', get_post_meta($order->id))) ?get_post_meta( $order->id, '_billing_purchaserAddress', true ) . '<br>' :get_post_meta( $order->id, '_purchaserAddress', true ) . '<br>';
        echo '門市電話: ' ;
        echo  (array_key_exists('_billing_purchaserPhone', get_post_meta($order->id))) ?get_post_meta( $order->id, '_billing_purchaserPhone', true ) . '<br>' :get_post_meta( $order->id, '_purchaserPhone', true ) . '<br>';
            
        if(!is_checkout()) echo '<input type="button" id="changeStore" value="更換門市" >';

        ?>
        <form id="ecpay<?php echo $order->id;?>" method="post" target="ecpay" action="https://logistics.ecpay.com.tw/Express/map" style="display:none">
            <input type="hidden" id="MerchantID" name="MerchantID" value="2000132" /><br />
            <input type="hidden" id="MerchantTradeNo" name="MerchantTradeNo" value="<?php echo $order->id.date('mdHis');?>" /><br />
            <input type="hidden" id="LogisticsSubType" name="LogisticsSubType" value="<?php echo $LogisticsSubType;?>" /><br />
            <input type="hidden" id="IsCollection" name="IsCollection" value="N" /><br />
            <input type="hidden" id="ServerReplyURL" name="ServerReplyURL" value="<?php echo add_query_arg('wc-api', 'WC_Gateway_Ecpay_Logis', home_url('/'))?>" /><br />
            <input type="hidden" id="ExtraData" name="ExtraData" value="" /><br />
            <input type="hidden" id="Device" name="Device" value="0" /><br />
            <input type="hidden" id="LogisticsType" name="LogisticsType" value="CVS" /><br />
        </form>
        
        <script type="text/javascript">
            document.getElementById("changeStore").onclick=function(){
                map = window.open('','ecpay',config='height=790px,width=1020px');
                if(map){
                   document.getElementById("ecpay<?php echo $order->id;?>").submit();
                }
            }
        </script>
        <?php
    }
?>