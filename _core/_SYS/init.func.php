<?php
/**
 *	Func lib
 *
 * @since 180528 Added e404()
 * @since 180629 _cfg support #; Added lock func.
 * @since 180818 Added num2str() and str2num().
 * @since 180821 checkstop() used debug() instead of debug2().
 * @since 181030 rw_lock() -> rw_try_lock() improved.
 * @since 190325 Used json_encode for str2arr/arr2str
 * @since 180329 Added xcache() from et.
 * @since Apr/25/2019 Compabitlity of b() when no _header() defination.
 * @since May/30/2019 `b()` added ajax _err format
 * @since  Oct/8/2020 Used const for $_p/$id
 * @since  Dec/1/2020 Cookie name plaintext, value included IP
 * @since  Dec/3/2020 Cookie will delete if value=0/NULL, so don't use value 0 when setting a useful cookie
 * @since  Dec/9/2020 Action=add goon will carry on potential ID for parent ID cases
 * @since  Dec/28/2020 `cookie()` added name validation
 * @since  Jan/12/2021 `cookie()` used ip geo_md5 for validation
 * @since  Jan/16/2021 `j()` checked HTTP_REFERER modal=1 automatically
 * @since  Jan/21/2021 `cookie()` whitelist IP NJ&NY
 * @since  May/7/2021 Added `ttrim()`
 * @since  Oct/10/2021 Added `ip_interval()`
 * @since  Oct/10/2021 latest
 */
/**

CREATE TABLE `lang` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL DEFAULT '',
  `lang_zh` varchar(255) NOT NULL DEFAULT '',
  `dateline` int(11) DEFAULT NULL,
  `lastacp_id` int(11) DEFAULT NULL,
  `lastacp_truename` varchar(255) DEFAULT NULL,
  `lastdateline` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `title` (`title`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

 */
defined( 'IN' ) || exit( 'Access Denied' );

/**
 * Translate
 */
function __( $title ) {
	global $_lang_set;

	require_once _SYS . 'lang.lib.php';
	$msg_uniq = strtolower( $title );
	// Translation existed, no need to record
	if ( LANG == 'en' && isset( LANG_SET[ $msg_uniq ] ) ) {
		return $title;
	}
	// Do system translation
	if ( ! empty( LANG_SET[ $msg_uniq ] ) ) {
		return LANG_SET[ $msg_uniq ];
	}

	// Load lang set
	if ( $_lang_set === null ) {
		$_lang_set = __build_lang_set();
	}

	if ( ! empty( $_lang_set[ $msg_uniq ] ) ) {
		return $_lang_set[ $msg_uniq ];
	}
	if ( isset( $_lang_set[ $msg_uniq ] ) || oc::get( 'lang.' . $msg_uniq ) ) {
		return $title;
	}

	// Save to db (may hit exception when string existed)
	try {
		db::i( 'acp.lang', [ 'title' => $title, 'dateline' => TS ] );
	}
	catch ( \Exception $e ) {
		debug( 'insert lang failed ' . $e->getMessage() );
	}
	oc::set( 'lang.' . $msg_uniq, 1, 120 );
	return $title;
}

/**
 * Translation build lang set
 */
function __build_lang_set() {
	$_lang_set = [];
	$list = db::msa( [ 'title, lang_zh', 'acp.lang' ], null, false, false, false, 60 );
	foreach ( $list as $v ) {
		$_lang_set[ strtolower( $v[ 'title' ] ) ] = ! empty( $v[ 'lang_' . LANG ] ) ? s::html( $v[ 'lang_' . LANG ] ) : false;
	}
	return $_lang_set;
}

/**
 * Check if hit IP operation interval or not
 */
function ip_interval( $action, $interval ) {
	$curr_interval = oc::get( $action . '.' . IP );
	if ( $curr_interval ) {
		$curr_interval = $curr_interval + $interval;
		// $curr_interval *= ceil( $curr_interval / $interval );
		oc::set( $action . '.' . IP, $curr_interval, $curr_interval );
		b( 'Please try after ' . seconds_to_readable( $curr_interval ) );
	}

	oc::set( $action . '.' . IP, $interval, $interval );
}

function xcache( $ttl = 86400 ) {
	header( 'X-LiteSpeed-Cache-Control: public, max-age=' . $ttl );
}

function xtag( $tag ) {
	header( 'X-LiteSpeed-Tag: ' . md5( $tag ) );
}

function xpurge( $tag ) {
	if ( is_array( $tag ) ) {
		$purge = implode( ',', array_map( 'md5', $tag ) );
	}
	else {
		$purge = md5( $tag );
	}
	header( 'X-LiteSpeed-Purge: ' . $purge );
}

/**
 * recursive trim
 */
function ttrim( $arr ) {
	if ( is_array( $arr ) ) {
		return array_map( 'ttrim', $arr );
	}

	return trim( $arr );
}

/**
 * Validate a date is correct or not, e.g. 2021-05-31
 */
function validate_date( $date ) {
	if ( ! $date || $date == '0000-00-00' ) {
		return true;
	}

	return $date == date( 'Y-m-d', strtotime( $date ) );
}

/**
 * Keep certain decimal places for float
 */
function dec( $number, $decimals = 2 ) {
	if ( ! $number) {
		return 0;
	}

	$number = $number * pow(10, $decimals);
	$number = intval( round( $number ) );
	$number = $number / pow(10, $decimals);
	return $number;
}

/**
 * Convert decimal number to string
 *
 * @since  Aug/18/2018
 */
function num2str( $v ) {
	return strtoupper( base_convert( $v, 10, 36 ) );
}

function str2num( $v ) {
	return base_convert( $v, 36, 10 );
}

/**
 * Update last runtime of cron
 */
function update_runtime() {
	$tag = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS );
	$tag = basename( $tag[ 0 ][ 'file' ] );

	f::write( "/tmp/$tag.data", time() );
}

/**
 * Check if need to stop cron
 */
function check_stop( $step = 0 ) {
	if ( file_exists( '/tmp/cronstop' ) ) {
		debug( 'cron stopped by /tmp/cronstop' );
		exit;
	}

	if ( file_exists( '/tmp/cronstop' . $step ) ) {
		debug( 'cron stopped by /tmp/cronstop' . $step );
		exit;
	}

	// If other cron is running, stop this too
	$runtime_file = '/tmp/cron_time.' . $step . '.data';
	if ( is_file( $runtime_file ) && time() - filemtime( $runtime_file ) < 3 && file_get_contents( $runtime_file ) != TS ) {
		debug( 'cron stopped by duplicate running ' . $runtime_file );
		exit;
	}
	file_put_contents( $runtime_file, TS );
}

/**
 * Try to lock a process with timeout
 */
function rw_try_lock( $step = 0, $expire = 30 ) {
	$file = '/tmp/lock_' . $step;

	if ( file_exists( $file ) && filemtime( $file ) + $expire > time() ) {
		return false;
	}

	debug( 'Lock ' . $step );

	touch( $file );

	return true;
}

function rw_unlock( $step = 0 ) {
	$file = '/tmp/lock_' . $step;
	file_exists( $file ) && unlink( $file );

	debug( 'Unlock ' . $step );
}

/**
 * Readable time (long)
 */
function seconds_to_readable( $seconds ) {
	$res = '';
	if ( $seconds > 86400 ) {
		$num = floor( $seconds / 86400 );
		$res .= $num . ( $num > 1 ? ' days' : ' day' );
		$seconds %= 86400;
	}

	if ( $seconds > 3600 ) {
		if ( $res ) {
			$res .= ', ';
		}
		$num = floor( $seconds / 3600 );
		$res .= $num . ( $num > 1 ? ' hours' : ' hour' );
		$seconds %= 3600;
	}

	if ( $seconds > 60 ) {
		if ( $res ) {
			$res .= ', ';
		}
		$num = floor( $seconds / 60 );
		$res .= $num . ( $num > 1 ? ' minutes' : ' minute' );
		$seconds %= 60;
	}

	if ( $seconds > 0 ) {
		if ( $res ) {
			$res .= ' and ';
		}
		$res .= $seconds . ( $seconds > 1 ? ' seconds' : ' second' );
	}

	return $res;
}

/**
 * Readable time (short)
 */
function readable_time_short( $seconds, $backward = true ) {
	if ( ! $seconds ) {
		return 'now';
	}

	$res = '';
	if ( $seconds > 86400 ) {
		$num = floor( $seconds / 86400 );
		$res .= $num . 'd';
		$seconds %= 86400;
	}

	if ( $seconds > 3600 ) {
		if ( $res ) {
			$res .= ' ';
		}
		$num = floor( $seconds / 3600 );
		$res .= $num . 'h';
		$seconds %= 3600;
	}

	if ( $seconds > 60 ) {
		if ( $res ) {
			$res .= ' ';
		}
		$num = floor( $seconds / 60 );
		$res .= $num . 'm';
		$seconds %= 60;
	}

	if ( $seconds > 0 ) {
		if ( $res ) {
			$res .= ' ';
		}
		$res .= $seconds . 's';
	}

	if ( $backward ) {
		$res .= ' ago';
	}

	return $res;
}

/**
 * Parse cfg under HOME folder
 */
function _cfg( $section = 'aws', $key = false, $dry_run = false ) {
	$iniFile = '/var/www/.nobody_home/.api';

	if ( ! is_readable( $iniFile ) ) {
		exit( 'no cfg file ' . $iniFile );
	}

	if ( defined( 'CFG_PREFIX' ) && ! in_array( $section, [ 'redis', 'mysql', 'env' ] ) ) {
		$section .= '_' . strtoupper( CFG_PREFIX );
	}

	$cfg = parse_ini_file( $iniFile, true );

	if ( empty( $cfg[ $section ] ) ) {
		if ( $dry_run ) {
			return false;
		}
		exit( 'no cfg con: .api:' . $section );
	}

	if ( $key ) {
		if ( array_key_exists( $key, $cfg[ $section ] ) ) {
			return $cfg[ $section ][ $key ];
		}

		return false;
	}

	$res = [];
	foreach ( $cfg[ $section ] as $k => $v ) {

		if ( substr( $k, 0, 1 ) == '#' ) {
			continue;
		}

		$res[ $k ] = $v;
	}
	return $res;
}

// Send out 404 error
function e404( $con = false ) {
	http_response_code( 404 );

	exit( $con );
}

// Send out 304
function e304( $con = false ) {
	http_response_code( 304 );

	exit( $con );
}

// Send out 500 error
function e500( $con = false, $notice = false ) {
	$_logNotice = '/tmp/err500.log';
	//
	if ( $notice && ( ! file_exists( $_logNotice ) || TS - filemtime( $_logNotice ) > 120 ) ) {
		f::write( $_logNotice, date( "Y-m-d H:i:s", TS ) . "\t$con\n", true );
		// sms::send('19299005513', 'Alert:500 '.date('H:i:s m/d/y')." ".substr($con, 0, 20));
	}

	http_response_code( 500 );

	exit( $con );
}

/**
 *	debug
 */
$__debug_hash = null;
function debug( $info, $add_on = null ) {
	if ( $add_on !== null ) {
		$info .= ' --- ' . var_export( $add_on, true );
	}

	_debug( $info, '/var/www/_debug' . ( defined( 'DEBUG_LEVEL' ) ? DEBUG_LEVEL : '' ) . '.log' );
}

/**
 * Log more detailed info
 */
function debug2( $info, $add_on = null ) {
	if ( $add_on !== null ) {
		$info .= ' --- ' . var_export( $add_on, true );
	}

	_debug( $info, '/var/www/_debug2.log' );
}

function _debug( $info, $file ) {
	global $__debug_hash;
	$new_line = '';
	if ( $__debug_hash === null ) {
		$__debug_hash = s::rrand( 3 );
		$new_line = "\n";

		if ( file_exists( $file ) && time() - filemtime( $file ) > 2 ) {
			f::write( $file, "\n\n\n", 1 );
		}
	}


	list( $usec, $sec ) = explode(' ', microtime() );
	$ts = date( 'H:i:s', $sec ) . substr( $usec, 1, 4 ) . date( ' m/d', $sec );// m/d/y
	$ts .= ' #' . $__debug_hash;

	if ( PHP_SAPI == 'cli' ) {
		$ts .= ' [CLI';
		if ( isset( $_SERVER[ 'USER' ] ) ) {
			$ts .= '-' . $_SERVER[ 'USER' ];
		}
		$ts .= ']';
	}


	if ( IP ) {
		$ts .= ' [' . IP . ']';
	}

	$info = $new_line . $ts . "\t" . $info . "\n";

	if ( file_exists( $file ) && filesize( $file ) > 3000000 ) {
		f::write( $file, $info );
		return;
	}

	f::write( $file, $info , 1 );
}

function debug_reset() {
	global $__debug_hash;
	$__debug_hash = null;
}

/**
 *	auto load
 *
 */
function _clsLoad( $cls ) {
	if ( preg_match( '/\W/', $cls ) ) {
		return;
	}

	$cls = strtolower( $cls );
	if( file_exists( _SYS . "$cls.cls.php" ) ) {
		require_once _SYS . "$cls.cls.php";
	}
}

//------------------------------Array func-------------------------------
/**
 *	serialize arr
 *
 */
function arr2str( $arr = [] ) {
	if( ! is_array( $arr ) ) $arr = [];
	return json_encode( $arr );
}

/**
 *	string to array
 *
 */
function str2arr( $str = '' ) {
	if ( ! $str ) {
		return [];
	}

	if ( is_array( $str ) ) {
		throw new \Exception( 'Data error: already array' );
	}

	$res = json_decode( $str, true );
	if ( $res ) {
		return $res;
	}

	return str2arr( base64_decode( $str ) );
}

/**
 *	sort 2 dimentions array
 *
 */
function arrSort($arr, $field, $order = 1) {
	$tmp = array();
	foreach($arr as $key => $val){
		$tmp[$key] = $val[$field];
	}
	$order ? arsort($tmp) : asort($tmp);
	$arr2 = array();
	foreach($tmp as $key => $val){
		$arr2[] = $arr[$key];
	}
	return $arr2;
}

//--------------------------------basic func----------------------------------

/**
 *	ip check
 *
 */
function ipAccess( $ial ) {
	$uip = explode( '.', IP );
	if(empty($uip) || count($uip) != 4) return false;
	if(!$ial) return false;
	if(!is_array($ial)) $ial = explode("\n", $ial);
	foreach($ial as $key => $ip) $ial[$key] = explode('.', trim($ip));
	foreach($ial as $key => $ip) {
		if(count($ip) != 4) continue;
		for($i = 0; $i <= 3; $i++) if($ip[$i] == '*') $ial[$key][$i] = $uip[$i];
	}
	return in_array($uip, $ial);
}

/**
 *	today's 0 time
 *
 */
function tTime($ts = TS){
	if($ts == TS) return strtotime('today 00:00:00');
	$times = explode('-', date('Y-m-d',$ts));
	return mktime(0, 0, 0, $times[1], $times[2], $times[0]);
}


//------------------------notice func-----------------------------
/**
 * Return success msg
 *
 * @since  1.0
 */
function msg( $msg, $msg_title = 'Message', $links = false, $type = 'success' ) {
	if ( defined( 'AJAX' ) ) {
		json( [ '_msg' => $msg ] );
	}
	if ( defined( 'EXIT' ) || ! function_exists( '_header' ) ) {
		exit( $msg );
	}

	// show error
	$showMenu = _get_post( 'modal' ) ? 0 : 1;
	_header( '', $showMenu );
	$tpl = new t( 'msg', _SYS );
	$tpl->assign( [
		'msg_title' => $msg_title,
		'msg' => $msg,
		'type' => $type,
		'links' => $links,
	] );
	$tpl->output();
	_footer( ! $showMenu );

}


/**
 *	err
 *
 */
function b( $msg, $links = false ) {
	if ( $msg === 404 ) {
		header( "Content-Type: image/svg+xml" );
		exit( 'data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iaXNvLTg4NTktMSI/Pg0KPCEtLSBHZW5lcmF0b3I6IEFkb2JlIElsbHVzdHJhdG9yIDE5LjAuMCwgU1ZHIEV4cG9ydCBQbHVnLUluIC4gU1ZHIFZlcnNpb246IDYuMDAgQnVpbGQgMCkgIC0tPg0KPHN2ZyB2ZXJzaW9uPSIxLjEiIGlkPSJDYXBhXzEiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyIgeG1sbnM6eGxpbms9Imh0dHA6Ly93d3cudzMub3JnLzE5OTkveGxpbmsiIHg9IjBweCIgeT0iMHB4Ig0KCSB2aWV3Qm94PSIwIDAgNTAzLjMyMiA1MDMuMzIyIiBzdHlsZT0iZW5hYmxlLWJhY2tncm91bmQ6bmV3IDAgMCA1MDMuMzIyIDUwMy4zMjI7IiB4bWw6c3BhY2U9InByZXNlcnZlIj4NCjxnPg0KCTxyZWN0IHg9IjE4Mi4xOTQiIHk9IjQwOC43MTEiIHN0eWxlPSJmaWxsOiM5NUE1QTU7IiB3aWR0aD0iMTM4Ljg0NyIgaGVpZ2h0PSI3Ny4wMTciLz4NCgk8cGF0aCBzdHlsZT0iZmlsbDojM0Y1QzZDOyIgZD0iTTE2Ni42MTcsMC4xOTVIMzQuNzEyQzE1LjUzNCwwLjE5NSwwLDE1LjEyMSwwLDQzLjE5NXYzMzguNDQxYzAsOC41NDgsMTUuNTM0LDIzLjQ3NCwzNC43MTIsMjMuNDc0DQoJCUg0NjguNjFjMTkuMTc4LDAsMzQuNzEyLTE0LjkyNiwzNC43MTItMjMuNDc0VjQzLjE5NWMwLTI4LjA3My0xNS41MzQtNDIuOTk5LTM0LjcxMi00Mi45OTlIMTY2LjYxN3oiLz4NCgk8Zz4NCgkJPHBhdGggc3R5bGU9ImZpbGw6I0JEQzNDNzsiIGQ9Ik0xMzguODQ3LDQ2OC40MTVoMjI1LjYyN2MxNC4zNzgsMCwyNi4wMzQsMTEuNjU2LDI2LjAzNCwyNi4wMzR2OC42NzhIMTEyLjgxNHYtOC42NzgNCgkJCUMxMTIuODE0LDQ4MC4wNzEsMTI0LjQ2OSw0NjguNDE1LDEzOC44NDcsNDY4LjQxNXoiLz4NCgkJPHBhdGggc3R5bGU9ImZpbGw6I0JEQzNDNzsiIGQ9Ik01MDMuMzIyLDM2NS4zNjR2MjcuNTA5Yy0wLjQzLDE4Ljc1NC0xNS45NTcsMzMuNjIxLTM0LjcxMiwzMy4yMzdIMzQuNzEyDQoJCQlDMTUuOTU3LDQyNi40OTUsMC40Myw0MTEuNjI3LDAsMzkyLjg3M3YtMjcuNTA5SDUwMy4zMjJ6Ii8+DQoJPC9nPg0KCTxwYXRoIHN0eWxlPSJmaWxsOiNEMjU2Mjc7IiBkPSJNMzQ2LjYxMiw2OS4yMjhoNDUuOTMyYzE4LjI4LDAuOTEyLDMyLjM4NiwxNi40MjgsMzEuNTU3LDM0LjcxMnY4Ni43OA0KCQljMC44MjksMTguMjg0LTEzLjI3NiwzMy44LTMxLjU1NiwzNC43MTJIMTA4LjUzOGMtMTguMjgtMC45MTItMzIuMzg1LTE2LjQyOC0zMS41NTYtMzQuNzEydi04Ni43OA0KCQljLTAuODI5LTE4LjI4NCwxMy4yNzYtMzMuOCwzMS41NTYtMzQuNzEySDM0Ni42MTJ6Ii8+DQoJPGc+DQoJCTxwYXRoIHN0eWxlPSJmaWxsOiNFQ0YwRjE7IiBkPSJNMTk4LjQ3NSwxNTYuMDA4aC04LjY3OHYtOC42NzhjMC00Ljc5My0zLjg4NS04LjY3OC04LjY3OC04LjY3OGMtNC43OTMsMC04LjY3OCwzLjg4NS04LjY3OCw4LjY3OA0KCQkJdjguNjc4aC0xOS43NTRsMjcuMjg4LTQ3Ljc2M2MyLjM2Ni00LjE2LDAuOTE3LTkuNDQ5LTMuMjM4LTExLjgyM2MtNC4xNTUtMi4zNzQtOS40NDctMC45MzctMTEuODMsMy4yMTNsLTM0LjcxMiw2MC43NDYNCgkJCWMtMS41MzcsMi42ODUtMS41MjcsNS45ODYsMC4wMjYsOC42NjJjMS41NTMsMi42NzYsNC40MTQsNC4zMjMsNy41MDgsNC4zMjFoMzQuNzEydjE3LjM1NmMwLDQuNzkzLDMuODg1LDguNjc4LDguNjc4LDguNjc4DQoJCQljNC43OTMsMCw4LjY3OC0zLjg4NSw4LjY3OC04LjY3OHYtMTcuMzU2aDguNjc4YzQuNzkzLDAsOC42NzgtMy44ODUsOC42NzgtOC42NzgNCgkJCUMyMDcuMTUzLDE1OS44OTMsMjAzLjI2NywxNTYuMDA4LDE5OC40NzUsMTU2LjAwOHoiLz4NCgkJPHBhdGggc3R5bGU9ImZpbGw6I0VDRjBGMTsiIGQ9Ik0zNjMuMzU2LDE1Ni4wMDhoLTguNjc4di04LjY3OGMwLTQuNzkzLTMuODg1LTguNjc4LTguNjc4LTguNjc4cy04LjY3OCwzLjg4NS04LjY3OCw4LjY3OHY4LjY3OA0KCQkJaC0xOS43NTRsMjcuMjg4LTQ3Ljc2M2MyLjM2Ni00LjE2LDAuOTE3LTkuNDQ5LTMuMjM4LTExLjgyM2MtNC4xNTUtMi4zNzQtOS40NDctMC45MzctMTEuODMsMy4yMTNsLTM0LjcxMiw2MC43NDYNCgkJCWMtMS41MzcsMi42ODUtMS41MjcsNS45ODYsMC4wMjYsOC42NjJjMS41NTMsMi42NzYsNC40MTQsNC4zMjMsNy41MDgsNC4zMjFoMzQuNzEydjE3LjM1NmMwLDQuNzkzLDMuODg1LDguNjc4LDguNjc4LDguNjc4DQoJCQlzOC42NzgtMy44ODUsOC42NzgtOC42Nzh2LTE3LjM1Nmg4LjY3OGM0Ljc5MywwLDguNjc4LTMuODg1LDguNjc4LTguNjc4QzM3Mi4wMzQsMTU5Ljg5MywzNjguMTQ5LDE1Ni4wMDgsMzYzLjM1NiwxNTYuMDA4eiIvPg0KCQk8cGF0aCBzdHlsZT0iZmlsbDojRUNGMEYxOyIgZD0iTTI1OS4yMiw5NS4yNjJoLTE3LjM1NmMtMTQuMzcyLDAuMDE2LTI2LjAxOCwxMS42NjItMjYuMDM0LDI2LjAzNHY1Mi4wNjgNCgkJCWMwLjAxNiwxNC4zNzIsMTEuNjYyLDI2LjAxOCwyNi4wMzQsMjYuMDM0aDE3LjM1NmMxNC4zNzItMC4wMTYsMjYuMDE4LTExLjY2MiwyNi4wMzQtMjYuMDM0di01Mi4wNjgNCgkJCUMyODUuMjM5LDEwNi45MjUsMjczLjU5Miw5NS4yNzgsMjU5LjIyLDk1LjI2MnogTTI2Ny44OTgsMTczLjM2NGMtMC4wMDUsNC43OTEtMy44ODcsOC42NzMtOC42NzgsOC42NzhoLTE3LjM1Ng0KCQkJYy00Ljc5MS0wLjAwNS04LjY3My0zLjg4Ny04LjY3OC04LjY3OHYtNTIuMDY4YzAuMDA1LTQuNzkxLDMuODg3LTguNjczLDguNjc4LTguNjc4aDE3LjM1NmM0Ljc5MSwwLjAwNSw4LjY3MywzLjg4Nyw4LjY3OCw4LjY3OA0KCQkJVjE3My4zNjR6Ii8+DQoJPC9nPg0KCTxwYXRoIHN0eWxlPSJmaWxsOiNEMjU2Mjc7IiBkPSJNMTM3Ljc2MywyNjguODIybC0zNC43MTIsNTIuMDY4aC04LjY3OGMtOS41NzQtMC4wMjgtMTcuMzI4LTcuNzgyLTE3LjM1Ni0xNy4zNTZ2LTE3LjM1Ng0KCQljMC4wMjgtOS41NzQsNy43ODItMTcuMzI4LDE3LjM1Ni0xNy4zNTZIMTM3Ljc2M3oiLz4NCgk8Zz4NCgkJPHBhdGggc3R5bGU9ImZpbGw6I0U1N0UyNTsiIGQ9Ik00MjQuMTM2LDI4Ni4xNzh2MTcuMzU2Yy0wLjAyOCw5LjU3NC03Ljc4MiwxNy4zMjgtMTcuMzU2LDE3LjM1NmgtNDMuMzlsMzQuNzEyLTUyLjA2OGg4LjY3OA0KCQkJQzQxNi4zNTMsMjY4Ljg1LDQyNC4xMDgsMjc2LjYwNCw0MjQuMTM2LDI4Ni4xNzh6Ii8+DQoJCTxwb2x5Z29uIHN0eWxlPSJmaWxsOiNFNTdFMjU7IiBwb2ludHM9IjE4MS4xNTMsMjY4LjgyMiAxNDYuNDQxLDMyMC44ODkgMTAzLjA1MSwzMjAuODg5IDEzNy43NjMsMjY4LjgyMiAJCSIvPg0KCTwvZz4NCgk8cG9seWdvbiBzdHlsZT0iZmlsbDojRDI1NjI3OyIgcG9pbnRzPSIyMjQuNTQyLDI2OC44MjIgMTg5LjgzMSwzMjAuODg5IDE0Ni40NDEsMzIwLjg4OSAxODEuMTUzLDI2OC44MjIgCSIvPg0KCTxwb2x5Z29uIHN0eWxlPSJmaWxsOiNFNTdFMjU7IiBwb2ludHM9IjI2Ny45MzIsMjY4LjgyMiAyMzMuMjIsMzIwLjg4OSAxODkuODMxLDMyMC44ODkgMjI0LjU0MiwyNjguODIyIAkiLz4NCgk8cG9seWdvbiBzdHlsZT0iZmlsbDojRDI1NjI3OyIgcG9pbnRzPSIzMTEuMzIyLDI2OC44MjIgMjc2LjYxLDMyMC44ODkgMjMzLjIyLDMyMC44ODkgMjY3LjkzMiwyNjguODIyIAkiLz4NCgk8cG9seWdvbiBzdHlsZT0iZmlsbDojRTU3RTI1OyIgcG9pbnRzPSIzNTQuNzEyLDI2OC44MjIgMzIwLDMyMC44ODkgMjc2LjYxLDMyMC44ODkgMzExLjMyMiwyNjguODIyIAkiLz4NCgk8cG9seWdvbiBzdHlsZT0iZmlsbDojRDI1NjI3OyIgcG9pbnRzPSIzOTguMTAyLDI2OC44MjIgMzYzLjM5LDMyMC44ODkgMzIwLDMyMC44ODkgMzU0LjcxMiwyNjguODIyIAkiLz4NCjwvZz4NCjxnPg0KPC9nPg0KPGc+DQo8L2c+DQo8Zz4NCjwvZz4NCjxnPg0KPC9nPg0KPGc+DQo8L2c+DQo8Zz4NCjwvZz4NCjxnPg0KPC9nPg0KPGc+DQo8L2c+DQo8Zz4NCjwvZz4NCjxnPg0KPC9nPg0KPGc+DQo8L2c+DQo8Zz4NCjwvZz4NCjxnPg0KPC9nPg0KPGc+DQo8L2c+DQo8Zz4NCjwvZz4NCjwvc3ZnPg0K' );
	}

	if ( defined( 'AJAX' ) ) {
		err( $msg );
	}

	if ( defined('EXIT') || ! function_exists( '_header' ) ) {
		exit( $msg );
	}

	// show error
	$showMenu = _get_post( 'modal' ) ? 0 : 1;
	_header( 'Error Page ', $showMenu );
	$tpl = new t( 'b', _SYS );
	$tpl->assign( [
		'msg' => $msg,
		'links' => $links,
	] );
	$tpl->output();
	_footer( ! $showMenu );
}

/**
 *	close modal and refresh main page
 */
function jx( $list = false ) {
	! defined( 'P' ) && define( 'P', false );

	$url = '';
	if ( $list === 1 ) {
		$url = '/' . P;
		if ( $_page = _get_post( '_page' ) ) {
			$url .= '?_page=' . $_page;
		}
	}
	elseif ( $list ) {
		$url = $list;
	}

	$tpl = new t( 'jx', _SYS );
	$tpl->assign( [
		'url'	=> $url,
	] );
	$tpl->output();
	exit();
}

/**
 * If the current page request is from modal or not (including referer page)
 */
function from_modal() {
	return _get_post( 'modal' ) || ( ! empty( $_SERVER[ 'HTTP_REFERER' ] ) && strpos( $_SERVER[ 'HTTP_REFERER' ], 'modal=1' ) );
}

/**
 *	redirect
 *
 */
function j( $url = '', $append_url = false, $sleep = false ) {
	! defined( 'P' ) && define( 'P', false );

	if ( ! $url || $url == 1 ) {
		if( defined( 'IN_ACP' ) ) {
			if ( defined( 'GOON' ) ) {
				$url = '/' . P . '/add?goon=1';
				if ( $append_url ) {
					$url .= "&$append_url";
				}
				if ( from_modal() ) {
					$url .= ( strpos( $url, '?' ) ? '&' : '?' ) . 'modal_parent=refresh&modal=1';
				}
				if ( ID ) {
					$url .= '&id=' . ID;
				}
			}
			elseif ( ! $url ) {
				$url = '/' . P;
				if ( $_page = _get_post( '_page' ) ) {
					$url .= '?_page=' . $_page;
				}
			}
			else {
				$url = '/' . P . '/' . ( defined( 'ID2' ) ? ID2 : ID );
				if ( $_page = _get_post( '_page' ) ) {
					$url .= '?_page=' . $_page;
				}
				if ( from_modal() ) {
					$url .= ( strpos( $url, '?' ) ? '&' : '?' ) . 'modal_parent=refresh&modal=1';
				}
				if ( $anchor = _get_post( '_anchor' ) ) {
					$url .= '&_anchor=' . $anchor . '#' . $anchor;
				}
			}
		} else $url = $_SERVER[ 'PHP_SELF' ];
	}

	if ( $url === 2 ) {
		$url = $_SERVER[ 'HTTP_REFERER' ];
	}

	exit( "<meta http-equiv='refresh' content='" . (int)$sleep . ";url=$url'>" );
}

function j301($url){
	header("Location: $url",TRUE,301);
	exit;
}

/**
 * Tmp redirect
 */
function j302( $url ) {
	header( "Location: $url", true, 302 );
	exit;
}

function x(){
	echo "Done. Please close this window.<script>open(location, '_self').close();</script>";
	exit;
}

/**
 *	addslashes recursive
 *
 */
function _addslashes($string){
	if(is_array($string)) {
		foreach($string as $k => $v) {
			$string[$k] = _addslashes($v);
		}
	}else {
		$string = addslashes($string);
	}

	return $string;
}

/**
 *	stripslashes recursive
 *
 */
function _stripslashes($string){
	if(is_array($string)) {
		foreach($string as $k => $v) {
			$string[$k] = _stripslashes($v);
		}
	}else {
		$string = stripslashes($string);
	}

	return $string;
}

//--------------------------------COOKIE func----------------------------------
/**
 *	store cookie
 *
 */
function cookie( $name, $value = false, $expire = 864000, $root_path = false ) {
	$name = _COOKIE . '_' . ( _ENV == 'prod' ? '' : _ENV . '_' ) . str_replace( '.', '__', $name );

	// Special handler for value 0/NULL, so don't use value 0 when setting a useful cookie
	if( $value ) {
		$value = [ $name, ip::geo_md5( IP ), $value ];
		$value = s::encrypt( arr2str( $value ) );

		$expire += TS;
	}
	else {
		if ( ! isset( $_COOKIE[ $name ] ) ) {
			return;
		}
		$value = false;
		$expire = 1;
	}

	$path = '/';
	if ( ! $root_path && defined( 'P' ) ) {
		$path = '/' . P;
	}

	$domain = '';
	if ( ! empty( $_SERVER[ 'HTTP_HOST' ] ) ) {
		$domain = '.' . $_SERVER[ 'HTTP_HOST' ];
	}

	setcookie( $name, $value, $expire, $path, $domain );
}

/**
 *	read cookie
 *
 */
function _cookie( $name ) {
	$name = _COOKIE . '_' . ( _ENV == 'prod' ? '' : _ENV . '_' ) . str_replace( '.', '__', $name );

	if ( ! isset( $_COOKIE[ $name ] ) ) {
		return null;
	}
	$cookie = $_COOKIE[ $name ];

	$cookie = str2arr( s::decrypt( $cookie ) ); //stripslashes

	if ( count( $cookie ) != 3 ) {
		return false;
	}

	if ( empty( $cookie[ 0 ] ) || $cookie[ 0 ] !== $name ) {
		return false;
	}

	if ( empty( $cookie[ 1 ] ) ) {
		return false;
	}

	if ( $cookie[ 1 ] !== ip::geo_md5( IP ) ) {
		// If IP is in trust list, allow and update cookie
		if ( ! auth::cls()->is_auth_ip() ) {
			return false;
		}
		cookie( $name, $cookie[ 2 ] );
	}

	return $cookie[ 2 ];
}

/**
 *	_GET
 *
 */
function _get($var){
	return isset($_GET[$var]) ? $_GET[$var] : false;
}

/**
 *	_POST
 *
 */
function _post($var){
	return isset($_POST[$var]) ? $_POST[$var] : false;
}

/**
 *	_GET or _POST
 *
 */
function _get_post($var){
	if(($var2 = _get($var)) !== false) return $var2;
	if(($var2 = _post($var)) !== false) return $var2;
	return false;
}

/**
 *	json return
 *
 */
function json($con = ''){
	if(!$con) $con = array();
	exit(json_encode($con));
}

function jsonp($con = ''){
	if(!$con) $con = array();
	exit(_get('callback').'('.json_encode($con).')');
}

/**
 * Return succeeded response
 *
 * @since  3.0
 */
function ok( $data = [] ) {
	// $data && debug( 'ok', $data );

	$data[ '_res' ] = 'ok';

	json( $data );
}

/**
 * Return error
 *
 * @since  3.0
 */
function err( $code, $data = [] ) {
	debug( 'client err: ' . $code );

	$data[ '_res' ] = 'err';
	$data[ '_msg' ] = $code;

	json( $data );
}

/**
 *	check if is int
 *
 */
function isInt($var){
	return is_numeric($var) && floor($var) == $var;
}

if ( ! function_exists('http_build_url') ) {
	if ( ! defined( 'HTTP_URL_REPLACE' ) ) 			define('HTTP_URL_REPLACE', 1);              // Replace every part of the first URL when there's one of the second URL
	if ( ! defined( 'HTTP_URL_JOIN_PATH' ) ) 		define('HTTP_URL_JOIN_PATH', 2);            // Join relative paths
	if ( ! defined( 'HTTP_URL_JOIN_QUERY' ) ) 		define('HTTP_URL_JOIN_QUERY', 4);           // Join query strings
	if ( ! defined( 'HTTP_URL_STRIP_USER' ) ) 		define('HTTP_URL_STRIP_USER', 8);           // Strip any user authentication information
	if ( ! defined( 'HTTP_URL_STRIP_PASS' ) ) 		define('HTTP_URL_STRIP_PASS', 16);          // Strip any password authentication information
	if ( ! defined( 'HTTP_URL_STRIP_AUTH' ) ) 		define('HTTP_URL_STRIP_AUTH', 32);          // Strip any authentication information
	if ( ! defined( 'HTTP_URL_STRIP_PORT' ) ) 		define('HTTP_URL_STRIP_PORT', 64);          // Strip explicit port numbers
	if ( ! defined( 'HTTP_URL_STRIP_PATH' ) ) 		define('HTTP_URL_STRIP_PATH', 128);         // Strip complete path
	if ( ! defined( 'HTTP_URL_STRIP_QUERY' ) ) 		define('HTTP_URL_STRIP_QUERY', 256);        // Strip query string
	if ( ! defined( 'HTTP_URL_STRIP_FRAGMENT' ) ) 	define('HTTP_URL_STRIP_FRAGMENT', 512);     // Strip any fragments (#identifier)
	if ( ! defined( 'HTTP_URL_STRIP_ALL' ) ) 		define('HTTP_URL_STRIP_ALL', 1024);         // Strip anything but scheme and host

	// Build an URL
	// The parts of the second URL will be merged into the first according to the flags argument.
	//
	// @param   mixed           (Part(s) of) an URL in form of a string or associative array like parse_url() returns
	// @param   mixed           Same as the first argument
	// @param   int             A bitmask of binary or'ed HTTP_URL constants (Optional)HTTP_URL_REPLACE is the default
	// @param   array           If set, it will be filled with the parts of the composed url like parse_url() would return
	function http_build_url($url, $parts = array(), $flags = HTTP_URL_REPLACE, &$new_url = false) {
		$keys = array('user','pass','port','path','query','fragment');

		// HTTP_URL_STRIP_ALL becomes all the HTTP_URL_STRIP_Xs
		if ( $flags & HTTP_URL_STRIP_ALL ) {
			$flags |= HTTP_URL_STRIP_USER;
			$flags |= HTTP_URL_STRIP_PASS;
			$flags |= HTTP_URL_STRIP_PORT;
			$flags |= HTTP_URL_STRIP_PATH;
			$flags |= HTTP_URL_STRIP_QUERY;
			$flags |= HTTP_URL_STRIP_FRAGMENT;
		}
		// HTTP_URL_STRIP_AUTH becomes HTTP_URL_STRIP_USER and HTTP_URL_STRIP_PASS
		else if ( $flags & HTTP_URL_STRIP_AUTH ) {
			$flags |= HTTP_URL_STRIP_USER;
			$flags |= HTTP_URL_STRIP_PASS;
		}

		// Parse the original URL
		// - Suggestion by Sayed Ahad Abbas
		//   In case you send a parse_url array as input
		$parse_url = !is_array($url) ? parse_url($url) : $url;

		// Scheme and Host are always replaced
		if ( isset($parts['scheme']) ) {
			$parse_url['scheme'] = $parts['scheme'];
		}
		if ( isset($parts['host']) ) {
			$parse_url['host'] = $parts['host'];
		}

		// (If applicable) Replace the original URL with it's new parts
		if ( $flags & HTTP_URL_REPLACE ) {
			foreach ($keys as $key) {
				if ( isset($parts[$key]) ) {
					$parse_url[$key] = $parts[$key];
				}
			}
		}
		else {
			// Join the original URL path with the new path
			if (isset($parts['path']) && ($flags & HTTP_URL_JOIN_PATH)) {
				if ( isset($parse_url['path']) ) {
					$parse_url['path'] = rtrim(str_replace(basename($parse_url['path']), '', $parse_url['path']), '/') . '/' . ltrim($parts['path'], '/');
				}
				else {
					$parse_url['path'] = $parts['path'];
				}
			}

			// Join the original query string with the new query string
			if ( isset($parts['query']) && ($flags & HTTP_URL_JOIN_QUERY) ) {
				if ( isset($parse_url['query']) ) {
					$parse_url['query'] .= '&' . $parts['query'];
				}
				else {
					$parse_url['query'] = $parts['query'];
				}
			}
		}

		// Strips all the applicable sections of the URL
		// Note: Scheme and Host are never stripped
		foreach ($keys as $key) {
			if ( $flags & (int)constant('HTTP_URL_STRIP_' . strtoupper($key)) ) {
				unset($parse_url[$key]);
			}
		}

		$new_url = $parse_url;

		return
			 (isset($parse_url['scheme']) ? $parse_url['scheme'] . '://' : '')
			.(isset($parse_url['user']) ? $parse_url['user'] . (isset($parse_url['pass']) ? ':' . $parse_url['pass'] : '') .'@' : '')
			.(isset($parse_url['host']) ? $parse_url['host'] : '')
			.(isset($parse_url['port']) ? ':' . $parse_url['port'] : '')
			.(isset($parse_url['path']) ? $parse_url['path'] : '')
			.(isset($parse_url['query']) ? '?' . $parse_url['query'] : '')
			.(isset($parse_url['fragment']) ? '#' . $parse_url['fragment'] : '')
		;
	}
}
