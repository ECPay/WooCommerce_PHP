<?php
/*
Apple Pay 串接ECPAY SDK
版本:V1.0.0414b
@author Wesley
*/

abstract class ECPay_ApplePay_EncryptType {
    // MD5(預設)
    const ENC_MD5 = 0;
    
    // SHA256
    const ENC_SHA256 = 1;
}

class Ecpay_ApplePay
{   
    public $ServiceURL = 'ServiceURL';
    public $HashKey = 'HashKey';
    public $HashIV = 'HashIV';
    public $MerchantID = 'MerchantID';
    public $Send = 'Send';
    public $EncryptType = ECPay_ApplePay_EncryptType::ENC_SHA256;
      
    function __construct()
    {

        $this->Send = array(
            "MerchantTradeNo"   => '',
            "MerchantTradeDate" => '',
            "TotalAmount"       => '',
            "CurrencyCode"      => 'TWD',
            "ItemName"          => array(),
            "PlatformID"        => '',
            "TradeDesc"         => '',
            "PaymentToken"      => '',
            "TradeType"         => 2
        );
      
    }

    /**
    *建立訂單
    */
    public function Check_Out()
    {       

        // 變數宣告
        $arErrors               = '' ;
        $aSend_Info             = array() ; // 送出參數
        $aSend_CheckMac_Info    = array() ; // 送出檢查碼用陣列
        
        $aReturn_Info           = array() ; // 回傳參數
        $aReturn_CheckMacValue  = array() ; // 回傳檢查碼用陣列
        $sReturn_CheckMacValue  = '' ;      // 回傳回來的CheckMacValue
    
        
        // 參數檢查
        $arErrors = $this->Check_String() ;

        // 1.有錯誤變數
        if(sizeof($arErrors) > 0)
        {
            throw new Exception(join('<br>', $arErrors));     
        }
        else
        {
            // 2.整理必要參數
            $aSend_Info = $this->Send ;
                 
            $aSend_Info['MerchantID'] = $this->MerchantID ;

            // 3.產生檢查碼
            $aSend_CheckMac_Info = $aSend_Info ;

            // 略過不需要驗證的參數
            unset($aSend_CheckMac_Info['PaymentToken']);
            
            // 產生檢查碼
            $aSend_Info['CheckMacValue'] = $this->GenerateCheckMacValue($aSend_CheckMac_Info) ; 

            // PaymentToken加密
            $aSend_Info['PaymentToken'] = $this->encrypt_data($aSend_Info['PaymentToken'], $this->HashKey, $this->HashIV) ;

            $this->decrypt_data($aSend_Info['PaymentToken'], $this->HashKey, $this->HashIV);

            // 4.送出資訊
            if(true)
            {
                $sReturn_Info = $this->ServerPost($aSend_Info);
                $aReturn_Info = json_decode($sReturn_Info, true) ;

            }
             
            // 5.狀態回傳驗證
            if(true)
            {

                if(count($aReturn_Info) > 0)
                {
                    $aReturn_CheckMacValue = $aReturn_Info ;
                    $sReturn_CheckMacValue = $aReturn_Info['CheckMacValue'] ;
                    unset($aReturn_CheckMacValue['CheckMacValue']) ;
                    
                    $sCheckMacValueGen = $this->GenerateCheckMacValue($aReturn_CheckMacValue) ;
                    
                    if($sCheckMacValueGen != $sReturn_CheckMacValue )
                    {
                        //array_push($arErrors, '1000001 CheckMacValue verify fail.');
                        array_push($arErrors, print_r($aReturn_Info, true));
                    }

                }   
                else
                {
                    // 傳出參數錯誤，查無資料
                    array_push($arErrors, 'Error:10100050');
                }
                
                return (count($arErrors) > 0) ? $arErrors : $aReturn_Info ; 
            }   
        }
        
        exit;
    }

    /**
    * 送出憑證測試
    */
    public function check_apple_ca($sCa_Key_Path = '', $sCa_Crt_Path = '', $sCa_Key_Pass = '', $sDisplayname = 'Displayname', $sValid_Url = 'https://apple-pay-gateway-cert.apple.com/paymentservices/startSession', $sCurrency = 'TWD', $sCountry = 'TW')
    {

        if( "https" == parse_url($sValid_Url, PHP_URL_SCHEME) && substr( parse_url($sValid_Url, PHP_URL_HOST), -10 )  == ".apple.com" )
        {

            $sMerchantIdentifier =  openssl_x509_parse( file_get_contents( $sCa_Crt_Path ))['subject']['UID'] ;

            // create a new cURL resource
            $ch = curl_init();

            $data = '{"merchantIdentifier":"'.$sMerchantIdentifier.'", "domainName":"'.$_SERVER["SERVER_NAME"].'", "displayName":"'.$sDisplayname.'"}';
            
            //echo 'data sent to applePay server ' . $data ;

            curl_setopt($ch, CURLOPT_URL, $sValid_Url);
            curl_setopt($ch, CURLOPT_SSLCERT, $sCa_Crt_Path);
            curl_setopt($ch, CURLOPT_SSLKEY, $sCa_Key_Path);
            curl_setopt($ch, CURLOPT_SSLKEYPASSWD, $sCa_Key_Pass);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

            //debug options
            //curl_setopt($ch, CURLOPT_HEADER, true);
            curl_setopt($ch, CURLOPT_VERBOSE, true);
            $verbose = fopen('php://temp', 'w+');
            curl_setopt($ch, CURLOPT_STDERR, $verbose);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            
            $result = curl_exec($ch);

            if( $result === false)
            {
                echo curl_errno($ch) . " - " . curl_error($ch);   
            }
            else
            {    
                echo 'applePay server response ' . $result ;
            }

            rewind($verbose);
            $verboseLog = stream_get_contents($verbose);
            
            echo htmlspecialchars($verboseLog);

            curl_close($ch);
        }
    }

    /**
    * 檢查憑證
    */
    public function check_vendor_ca($sCa_Key_Path = '', $sCa_Crt_Path = '', $sCa_Key_Pass = '', $sDisplayname = 'Displayname', $sValid_Url = 'https://apple-pay-gateway-cert.apple.com/paymentservices/startSession', $sCurrency = 'TWD', $sCountry = 'TW')
    {

        if( "https" == parse_url($sValid_Url, PHP_URL_SCHEME) && substr( parse_url($sValid_Url, PHP_URL_HOST), -10 )  == ".apple.com" )
        {

            $sMerchantIdentifier =  openssl_x509_parse( file_get_contents( $sCa_Crt_Path ))['subject']['UID'] ;

            // create a new cURL resource
            $ch = curl_init();

            $data = '{"merchantIdentifier":"'.$sMerchantIdentifier.'", "domainName":"'.$_SERVER["SERVER_NAME"].'", "displayName":"'.$sDisplayname.'"}';
            
            //echo 'data sent to applePay server ' . $data ;

            curl_setopt($ch, CURLOPT_URL, $sValid_Url);
            curl_setopt($ch, CURLOPT_SSLCERT, $sCa_Crt_Path);
            curl_setopt($ch, CURLOPT_SSLKEY, $sCa_Key_Path);
            curl_setopt($ch, CURLOPT_SSLKEYPASSWD, $sCa_Key_Pass);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

            //debug options
            //curl_setopt($ch, CURLOPT_HEADER, true);
            curl_setopt($ch, CURLOPT_VERBOSE, true);
            $verbose = fopen('php://temp', 'w+');
            curl_setopt($ch, CURLOPT_STDERR, $verbose);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            
            $result = curl_exec($ch);
        
            curl_close($ch);  

            return json_encode($result);  
        }
    }

        
    /**
    * 檢查各個參數是否符合規格
    */
    public function Check_String()
    {


        $arErrors = array();
       
        // 檢查是否有傳入MerchantID
        if(strlen($this->MerchantID) == 0)
        {
            array_push($arErrors, 'MerchantID is required.');
        }

        if(strlen($this->MerchantID) > 10)
        {
            array_push($arErrors, 'MerchantID max langth as 10.');
        }
            
        // 檢查是否有傳入HashKey
        if(strlen($this->HashKey) == 0)
        {
                array_push($arErrors, 'HashKey is required.');
        }
        
        // 檢查是否有傳入HashIV
        if(strlen($this->HashIV) == 0)
        {
                array_push($arErrors, 'HashIV is required.');
        }
        
        // 檢查是否有傳送網址
        if(strlen($this->ServiceURL) == 0)
        {
                array_push($arErrors, 'Url is required.');
        }

        // *金額不可為0元
        if($this->Send['TotalAmount'] == 0)
        {
            array_push($arErrors, 'TotalAmount is required.');
        }

        return $arErrors ;  
    }
        
    /**
    * 產生檢查碼
    * 傳入    $arParameters   各參數
    * 傳出    $sMacValue      檢查碼
    */
    public function GenerateCheckMacValue($arParameters)
    {
        $sMacValue = '' ;
        
        if(isset($arParameters))
        {
            // 資料排序
            // php 5.3以下不支援
            // ksort($arParameters, SORT_NATURAL | SORT_FLAG_CASE);
            uksort($arParameters, array('Ecpay_ApplePay','merchantSort'));

            // 開始組合字串
            $sMacValue = 'HashKey=' . $this->HashKey ;
            foreach($arParameters as $key => $value)
            {
                $sMacValue .= '&' . $key . '=' . $value ;
            }
            
            $sMacValue .= '&HashIV=' . $this->HashIV ;  

            // URL Encode編碼     
            $sMacValue = urlencode($sMacValue); 
            
            // 轉成小寫
            $sMacValue = strtolower($sMacValue);        
            
            // 取代為與 dotNet 相符的字元
            $sMacValue = str_replace('%2d', '-', $sMacValue);
            $sMacValue = str_replace('%5f', '_', $sMacValue);
            $sMacValue = str_replace('%2e', '.', $sMacValue);
            $sMacValue = str_replace('%21', '!', $sMacValue);
            $sMacValue = str_replace('%2a', '*', $sMacValue);
            $sMacValue = str_replace('%28', '(', $sMacValue);
            $sMacValue = str_replace('%29', ')', $sMacValue);
                                
            // MD5編碼
            $sMacValue = hash('sha256', $sMacValue, false);
            $sMacValue = strtoupper($sMacValue);
        }
        
        return $sMacValue ;

    }

    /**
    * 參數內特殊字元取代
    * 傳入    $sParameters    參數
    * 傳出    $sReturn_Info   回傳取代後變數
    */
    public function Replace_Symbol($sParameters)
    {
        if(!empty($sParameters))
        {
            $sParameters = str_replace('%2D', '-', $sParameters);
            $sParameters = str_replace('%2d', '-', $sParameters);
            $sParameters = str_replace('%5F', '_', $sParameters);
            $sParameters = str_replace('%5f', '_', $sParameters);
            $sParameters = str_replace('%2E', '.', $sParameters);
            $sParameters = str_replace('%2e', '.', $sParameters);
            $sParameters = str_replace('%21', '!', $sParameters);
            $sParameters = str_replace('%2A', '*', $sParameters);
            $sParameters = str_replace('%2a', '*', $sParameters);
            $sParameters = str_replace('%28', '(', $sParameters);
            $sParameters = str_replace('%29', ')', $sParameters);
        }
        return $sParameters ;
    }
    
    /**
    * 自訂排序使用
    */
    private static function merchantSort($a,$b)
    {
        return strcasecmp($a, $b);
    }

    /**
    * 資料傳輸加密 2017-04-18 wesley
    * @param        string  $sPost_Data     DATA
    * @param        string  $sKey           KEY
    * @param        string  $sIv            IV
    */
    public function encrypt_data($sPost_Data = '', $sKey = '', $sIv = '')
    {

        $sPost_Data = $this->addpadding($sPost_Data);   // PaddingMODE
        $encrypted = mcrypt_encrypt(MCRYPT_RIJNDAEL_128, $sKey, $sPost_Data, MCRYPT_MODE_CBC, $sIv);    // ACE加密
        $encrypted = base64_encode($encrypted);  //Base64編碼
        $encrypted = urlencode($encrypted); // urlencode

        // 取代為與 dotNet 相符的字元
        $encrypted = str_replace('%2B', '%2b', $encrypted);
        $encrypted = str_replace('%2F', '%2f', $encrypted);
        $encrypted = str_replace('%3D', '%3d', $encrypted);

        return $encrypted;
    }

    /**
    * 資料傳輸解密 2017-04-19
    * @param        string  $sPost_Data     DATA
    * @param        string  $sKey           KEY
    * @param        string  $sIv            IV
    */
    public function decrypt_data($sPost_Data = '', $sKey = '', $sIv = '')
    {

        // 取代為與 dotNet 相符的字元
        $sPost_Data = str_replace('%2b', '%2B', $sPost_Data);
        $sPost_Data = str_replace('%2f', '%2F', $sPost_Data);
        $sPost_Data = str_replace('%3d', '%3D', $sPost_Data);

        $sPost_Data = urldecode($sPost_Data);   // urldecode

        //Base64解碼
        $sPost_Data = base64_decode($sPost_Data);
       
        //ace解碼
        $decrypted = mcrypt_decrypt( MCRYPT_RIJNDAEL_128, $sKey, $sPost_Data, MCRYPT_MODE_CBC, $sIv);

        // 除去pkcs7
        $decrypted = $this->stripPkcs7Padding($decrypted);
        return $decrypted ;
    }


    //Padding PKCS7的Function
    public function addpadding($string)
    {
            //Pkcs7
            $blocksize = mcrypt_get_block_size(MCRYPT_RIJNDAEL_128, MCRYPT_MODE_CBC);
            $len = strlen($string); 
            $pad = $blocksize - ($len % $blocksize);
            $string .= str_repeat(chr($pad), $pad);
            return $string;
    }

    /**
    * 除去pkcs7 padding
    * @param String 解密後结果
    * @return String
    */
    public function stripPkcs7Padding($string)
    {
        $slast = ord(substr($string, -1));
        $slastc = chr($slast);
        $pcheck = substr($string, -$slast);
        if(preg_match("/$slastc{".$slast."}/", $string))
        {
            $string = substr($string, 0, strlen($string)-$slast);
            return $string;
        }
        else
        {
            return false;
        }
    }

    /**
    *接收 Apple Pay in-app 參數
    */
    public function GetAppPost($arPostData)
    {
        foreach ($arPostData as $key => $value) 
        {
            $arPostData[$key] = trim($arPostData[$key]);
        }

        $arErrors = array();

        //檢查參數 - MerchantTradeNo
        if ($this->IsNullOrEmptyString($arPostData['MerchantTradeNo']))
        {
            array_push($arErrors, 'MerchantTradeNo is required.');
        }
        if (strlen($arPostData['MerchantTradeNo']) > 20)
        {
            array_push($arErrors, 'MerchantTradeNo max length as 20.');
        }
        //檢查參數 - MerchantTradeDate
        if ($this->IsNullOrEmptyString($arPostData['MerchantTradeDate']))
        {
            array_push($arErrors, 'MerchantTradeDate is required.');
        }
        if (!preg_match("/^(\d{4})\/(\d{2})\/(\d{2}) (\d{2}):(\d{2}):(\d{2})$/", $arPostData['MerchantTradeDate']))
        {
            array_push($arErrors, 'MerchantTradeDate format as yyyy/MM/dd HH:mm:ss.');
        }
        //檢查參數 - TotalAmount
        if ($this->IsNullOrEmptyString($arPostData['TotalAmount']) || $arPostData['TotalAmount'] == 0)
        {
            array_push($arErrors, 'TotalAmount is required.');
        }
        //檢查參數 - CurrencyCode
        if ($this->IsNullOrEmptyString($arPostData['CurrencyCode']))
        {
            array_push($arErrors, 'CurrencyCode is required.');
        }
        //檢查參數 - ItemName
        if ($this->IsNullOrEmptyString($arPostData['ItemName']))
        {
            array_push($arErrors, 'ItemName is required.');
        }
        if (strlen($arPostData['ItemName']) > 200)
        {
            array_push($arErrors, 'ItemName max length as 200.');
        }
        //檢查參數 - TradeDesc
        if (!isset($arPostData['TradeDesc']))
        {
            $arPostData['TradeDesc'] = '';
        }
        if (strlen($arPostData['TradeDesc']) > 200)
        {
            array_push($arErrors, 'TradeDesc max length as 200.');
        }
        //檢查參數 - PaymentToken
        if ($this->IsNullOrEmptyString($arPostData['PaymentToken']))
        {
            array_push($arErrors, 'PaymentToken is required.');
        }

        if (sizeof($arErrors) > 0) 
        {
            throw new Exception(join('- ', $arErrors));
            return;
        }

        //整理參數
        $this->Send['MerchantTradeNo']      = $arPostData['MerchantTradeNo'];
        $this->Send['MerchantTradeDate']    = $arPostData['MerchantTradeDate'];
        $this->Send['TotalAmount']          = $arPostData['TotalAmount'];
        $this->Send['CurrencyCode']         = $arPostData['CurrencyCode'];
        $this->Send['ItemName']             = $arPostData['ItemName'];
        $this->Send['TradeDesc']            = $arPostData['TradeDesc'];
        $this->Send['PaymentToken']         = $arPostData['PaymentToken'];
        $this->Send['TradeType']            = $arPostData['TradeType'];
    }

    /**
    * 幕後送出參數
    * 傳入    $aSend_Info     送出參數
    * 傳出    $sReturn_Info   回傳參數 json格式
    */
    public function ServerPost($aSend_Info)
    {
        // 變數宣告
        $sSend_Info         = '' ;
        $sReturn_Info       = '' ;
        $aReturn_Info       = array() ;
        
        // 組合字串
        foreach($aSend_Info as $key => $value)
        {
            if( $sSend_Info == '')
            {
                $sSend_Info .= $key . '=' . $value ;
            }
            else
            {
                $sSend_Info .= '&' . $key . '=' . $value ;
            }
        }

        // 送出參數
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->ServiceURL);
        curl_setopt($ch, CURLOPT_HEADER, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, TRUE);
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $sSend_Info);

        //curl_setopt($ch, CURLOPT_VERBOSE, true);
        //$verbose = fopen('php://temp', 'w+');
        //curl_setopt($ch, CURLOPT_STDERR, $verbose);
        //curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        // 回傳參數
        $sReturn_Info = curl_exec($ch);

        curl_close($ch);

        //rewind($verbose);
        //$verboseLog = stream_get_contents($verbose);

        //echo "<fieldset style='padding:1em;margin:1em'><legend> Verbose information </legend>";
        //echo htmlspecialchars($verboseLog);
        //echo "</fieldset>";
    
        // 轉結果為陣列。
        //parse_str($sReturn_Info, $aReturn_Info);
            
        return $sReturn_Info;

    }  

    private function IsNullOrEmptyString($sArg)
    {
        return (!isset($sArg) || trim($sArg)==='');
    }     
}

?>