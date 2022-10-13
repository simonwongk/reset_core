<?php
/**
 *	Init
 *
 * @since  180731 Reformed
 * @since  Aug/23/2018 const SUBMIT got $submit val; Added const ID;
 *
 */
error_reporting( E_ALL );

! defined( '_SYS' ) && define( '_SYS', str_replace( '\\', '/', dirname( __FILE__ ) ) . '/' );
! defined( 'IN' ) && define( 'IN', true );

//define('DBUTF8', true);
$starttime = microtime( 1 );

ini_set( 'date.timezone', 'America/New_York' );
ini_set( 'error_log', '/var/www/_errorPHP.log' );
ini_set( 'log_errors', 1 );

date_default_timezone_set( 'America/New_York' );

define( 'TS', time() );
define( 'DBUTF8', 1 );

require_once _SYS . 'init.func.php';

spl_autoload_register('_clsLoad');

if ( _get( 'noheader' ) === false ) {
	header( 'Content-Type: text/html; charset=utf-8' );//header("Cache-Control: no-cache");
	ob_start();
}

// User IP
! defined( 'IP' ) && define( 'IP', ip::me() );
! defined( '_SALT' ) && define( '_SALT', _cfg( 'env', 'salt' ) );
! defined( '_ENV' ) && define( '_ENV', _cfg( 'env', 'server' ) );
! defined( '_COOKIE' ) && define( '_COOKIE', _cfg( 'locale', 'cookie', true ) ?: 'v2' );

// Unique ID
$__sid = _cookie( '__sid' );
if ( empty( $__sid ) ) {
	$__sid = md5( IP . s::rrand( 20 ) );
	cookie( '__sid', $__sid, 86400 * 365, '/' );
}
$__sid = md5( $__sid );
! defined( 'SID' ) && define( 'SID', $__sid );

$_tTime = tTime();

$action = _get_post( 'action' );
! defined( 'ACTION' ) && define( 'ACTION', $action );

$step = _get_post( 'step' );
! defined( 'ACTION_STEP' ) && define( 'ACTION_STEP', $step );

// Language init
$lang = _cookie( '_lang' );
if ( _get( '_lang' ) ) {
	$lang = _get( '_lang' );
	cookie( '_lang', $lang, 86400 * 365, '/' );
	j( 2 );
}
if ( ! in_array( $lang, [ 'en', 'zh' ] ) ) {
	$lang = 'en';
}
! defined( 'LANG' ) && define( 'LANG', $lang );
$_lang_set = null;

$id = (int)_get_post( 'id' );
if ( $id && ! isint( $id ) ) {
	$id = 0;
}
! defined( 'ID' ) && define( 'ID', $id );

$submit = _get_post( 'submit' ) ?: _post( 'submit2' );
! defined( 'SUBMIT' ) && define( 'SUBMIT', $submit );

_get_post( '__' ) && ! defined( 'AJAX' ) && define( 'AJAX', true );

$json = array();

$_assign = array(
	'TS' => TS,
);

$_p = [];
$p = false;
