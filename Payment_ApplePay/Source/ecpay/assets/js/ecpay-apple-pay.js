var $ = jQuery.noConflict();



console.log(wc_ecpay_apple_pay_params.site_url+'/index.php/checkout/order-received/');

jQuery(document).ready(function($){

    /* 檢查當前瀏覽器是否可支援Apple Pay */
	jQuery("#apple-pay-button").hide();
	
	if(wc_ecpay_apple_pay_params.server_https == 'on')
	{
		if(wc_ecpay_apple_pay_params.test_mode == 'yes')
	        {
	        	console.log('s0:檢查是否為https');
	        	console.log(wc_ecpay_apple_pay_params.server_https);
	        }


	        if (window.ApplePaySession) {

			var merchantIdentifier = wc_ecpay_apple_pay_params.display_name;  
			var promise = ApplePaySession.canMakePaymentsWithActiveCard(merchantIdentifier);
			

			if(wc_ecpay_apple_pay_params.test_mode == 'yes')
		        {
		        	console.log('s0:檢查 canMakePaymentsWithActiveCard');
		        	console.log(promise);
		        }


			promise.then(function (canMakePayments) {
			    	if (canMakePayments) {
			        	
					jQuery("#apple-pay-button").show();
			        	jQuery("#apple-pay-button").click(beginPayment);

			    	}
			});

		}else {

			jQuery(".apple-pay-button-wrapper").html("<span style='font-size: 12px; font-weight: bold;'>您使用的瀏覽器不支援Apple Pay</span>");
		}
	}
	else
	{
		if(wc_ecpay_apple_pay_params.test_mode == 'yes')
	        {
	        	console.log('s0:檢查是否為https');
	        	console.log(wc_ecpay_apple_pay_params.server_https);
	        }

	        jQuery(".apple-pay-button-wrapper").html("<span style='font-size: 12px; font-weight: bold;'>此網站不支援HTTPS瀏覽，請與網站提供者聯繫。</span>");	
	}
	
}); 



function beginPayment() {
    	
    	/* 建立 PaymentRequest */
    	/* 參考來源: https://developer.apple.com/reference/applepayjs/paymentrequest */

	var request = {
	        countryCode: 'TW',	    				/* 交易地點 */
	        currencyCode: 'TWD',					/* 交易幣別 */
	        supportedNetworks: ['visa', 'masterCard'],		/* 支援卡別 */
	        merchantCapabilities: ['supports3DS'],			
		total: { 
	        	label: wc_ecpay_apple_pay_params.lable,
	        	amount: wc_ecpay_apple_pay_params.total 	/* amount為交易金額，商家需自行帶入此次交易的金額 */
	        }

	};


	/* 建立 ApplePaySession */
	/* 參考來源: https://developer.apple.com/reference/applepayjs/applepaysession/2320659-applepaysession */
	var session = new ApplePaySession(1, request);

	/* 商店驗證事件 */
	session.onvalidatemerchant = function (event) {

	        var data = {
			action: "apple_pay_check_vendor",
			validationURL: event.validationURL
	        };

	        if(wc_ecpay_apple_pay_params.test_mode == 'yes')
	        {
	        	console.log('s2:準備進行商店驗證，傳入資訊');
	        	console.log(data);
	        }
	        
	        // 將validationURL拋到Server端，由Server端與Apple Server做商店驗證
		jQuery.ajax({
                        type: "POST",
                        url: wc_ecpay_apple_pay_params.ajaxurl,
                        data: data,
                        dataType: "json",
                        success: function (merchantSession){

				//alert(merchantSession);

				if(wc_ecpay_apple_pay_params.test_mode == 'yes')
			        {
			        	console.log('s3:商店驗證回傳結果');
			        	console.log(merchantSession);
			        	console.log(JSON.parse(merchantSession));
			        }

			        if(wc_ecpay_apple_pay_params.test_mode == 'yes')
			        {
			        	console.log('s4:提示付款，按壓指紋');
			        }

				// 驗證成功提示按壓指紋
				session.completeMerchantValidation(JSON.parse(merchantSession));
                        },
                        error: function (sMsg1, sMsg2){
                               //alert('fail2');
                        }
                });

	};


	/* 付款授權事件 */
	session.onpaymentauthorized = function (event) {

	        var send = {
			action: "apple_pay_check_out",
			payment: JSON.stringify(event.payment),
			order_id: wc_ecpay_apple_pay_params.order_id
	        };

	        if(wc_ecpay_apple_pay_params.test_mode == 'yes')
	        {
	        	console.log('s5:進行付款，送出付款資訊到綠界');
	        	console.log(send);
	        }

	        /* 將payment物件拋至Server端，由Server端處理交易授權 */
		jQuery.ajax({
                        type: "POST",
                        url: wc_ecpay_apple_pay_params.ajaxurl,
                        data: send,
                        dataType: "json",
                        success: function (merchantSession){

				if(wc_ecpay_apple_pay_params.test_mode == 'yes')
			        {

			        	console.log('s9:取得授權結果');
			        	console.log(merchantSession);
			        }

				if(merchantSession.RtnCode == 1)
				{
					if(wc_ecpay_apple_pay_params.test_mode == 'yes')
				        {
				        	console.log('s10:授權成功');
				        	console.log(merchantSession);
				        }


				        session.completePayment(JSON.parse(ApplePaySession.STATUS_SUCCESS));
					window.location.href = wc_ecpay_apple_pay_params.site_url+"/index.php/checkout/order-received/";
				}
				else
				{
					if(wc_ecpay_apple_pay_params.test_mode == 'yes')
				        {
				        	console.log('s10:授權失敗');
				        	console.log(merchantSession);
				        }

				        session.completePayment(JSON.parse(ApplePaySession.STATUS_FAILURE));
				}
				
				
                        },
                        error: function (sMsg1, sMsg2){
                               //alert('fail3');
                        }
                });

	}


	/*


	*/

	/* 啟用ApplePay session */
	session.begin();

	return false;
}
