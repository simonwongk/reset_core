<?php
if(!defined('IN')) exit('Access Denied');

use PayPal\Auth\OAuthTokenCredential;
use PayPal\Rest\ApiContext;

$_cfg = _cfg('paypal');
$_paypal = new ApiContext(new OAuthTokenCredential($_cfg['id'], $_cfg['key']));
$_paypal->setConfig([
	'mode' => 'live',
	'log.LogEnabled' => true,
	'log.FileName' => '/usr/local/lsws/www/PayPal.log',
	'log.LogLevel' => 'INFO', // PLEASE USE `INFO` LEVEL FOR LOGGING IN LIVE ENVIRONMENTS
	'cache.enabled' => false,
]);

