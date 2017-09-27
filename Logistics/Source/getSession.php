<?php

    session_start();

    $ecpayShippingType = [
        'FAMI',
        'FAMI_Collection',
        'UNIMART' ,
        'UNIMART_Collection',
        'HILIFE',
        'HILIFE_Collection',
    ];

    $billing = [];
    $billing['first_name'] = filter_var($_REQUEST['billingData']['first_name'], FILTER_SANITIZE_STRING);
    $billing['last_name'] = filter_var($_REQUEST['billingData']['last_name'], FILTER_SANITIZE_STRING);
    $billing['company'] = filter_var($_REQUEST['billingData']['company'], FILTER_SANITIZE_STRING);
    $billing['phone'] = preg_match('/^09\d{8}$/', $_REQUEST['billingData']['phone']) ? $_REQUEST['billingData']['phone'] : '';
    $billing['email'] = filter_var($_REQUEST['billingData']['email'], FILTER_VALIDATE_EMAIL) ? $_REQUEST['billingData']['email'] : '';

    if (!empty($_REQUEST['ecpayShippingType']) && in_array($_REQUEST['ecpayShippingType'], $ecpayShippingType)) {
        $domain = str_replace((isset($_SERVER['HTTPS']) ? "https://" : "http://"), '', $_SERVER['HTTP_HOST']);
        $path = str_replace((isset($_SERVER['HTTPS']) ? "https://" : "http://") . $_SERVER['HTTP_HOST'], '', $_SERVER['HTTP_REFERER']);

        setcookie('ecpayShippingType', htmlspecialchars($_REQUEST['ecpayShippingType'], ENT_QUOTES, 'UTF-8'), 0, $path, $domain, true, true);
        foreach ($billing as $key => $value) {
            setcookie('billing_' . $key, htmlspecialchars($value, ENT_QUOTES, 'UTF-8'), 0, $path, $domain, true, true);
        }
    }