<?php

	session_start();

	$ecpayShippingType = [
		'FAMI',
		'FAMI_Collection',
		'UNIMART' ,
		'UNIMART_Collection' ,
		'HILIFE',
		'HILIFE_Collection',
	];

	if (!empty($_POST['ecpayShippingType']) && in_array($_POST['ecpayShippingType'], $ecpayShippingType)) {
		$_SESSION['ecpayShippingType'] = $_POST['ecpayShippingType'];
	}
