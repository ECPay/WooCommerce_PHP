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

    $billingData = $_REQUEST['billingData'];

    $billing = [];
    $billing['first_name'] = filter_var($billingData['first_name'], FILTER_SANITIZE_STRING);
    $billing['last_name'] = filter_var($billingData['last_name'], FILTER_SANITIZE_STRING);
    $billing['company'] = filter_var($billingData['company'], FILTER_SANITIZE_STRING);
    $billing['phone'] = preg_match('/^09\d{8}$/', $billingData['phone']) ? $billingData['phone'] : '';
    $billing['email'] = filter_var($billingData['email'], FILTER_VALIDATE_EMAIL) ? $billingData['email'] : '';

    if (!empty($_REQUEST['ecpayShippingType']) && in_array($_REQUEST['ecpayShippingType'], $ecpayShippingType)) {
        $_SESSION['ecpayShippingType'] = $_REQUEST['ecpayShippingType'];
        $_SESSION['billingData'] = $billing;
    }
