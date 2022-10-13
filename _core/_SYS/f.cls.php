<?php
/**
 *	File management
 *
 * @since  1.6
 * @since  2.1 [20180315]
 * @since  2.2.1 [20180410]
 * @since  2.2.3 [20180501]
 * @since  2.4.1 [20180711] Added exception 404 for f::read()
 * @since  Aug/14/2018 Ignore certificate check in all curl funcs.
 * @since  Dec/03/2018 Added ping()
 * @since  Mar/04/2018 is_404()
 * @since  Mar/22/2019 Added curl1.1 for curl
 * @since  Apr/01/2019 Allow set ip in post()
 * @since  Apr/10/2019 New func `ll()`.
 * @since  May/01/2019 Encode space in URL for CURL
 * @since  Jun/13/2019 `get()` will honor 301
 * @since  Aug/05/2019 Allow for permissions in mmkdir()
 * @since  Oct/15/2019 `read()` allow IP resolve
 * @since  Dec/25/2020 `write()` auto create folder
 * @since  Dec/26/2020 `post()` json format
 * @since  Sep/25/2021 `post()` added 4th param `headers`
 * @since  Sep/25/2021 `get()` added 3rd param `headers`
 * @since  Sep/25/2021 latest
 *
 */
defined( 'IN' ) || exit( 'Access Denied' );

class f {
	const UA = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/93.0.4577.82 Safari/537.36';

	/**
	 * Generate a safename
	 */
	public static function safename( $name ) {
		return preg_replace( '/[^\w\d\-\(\)_.]/isU', '_', $name );
	}

	/**
	 * Check if an URL is 404 or not
	 */
	public static function is_404( $url ) {
		$ch = curl_init();
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch, CURLOPT_NOBODY, true );
		curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, true );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, false );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
		curl_setopt( $ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1 );

		curl_setopt( $ch, CURLOPT_URL, $url );

		$response = curl_exec( $ch );
		$http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		curl_close( $ch );

		if ( $http_code == 404 ) {
			return true;
		}

		return false;
	}

	/**
	 * Get ping speed
	 */
	public static function ping( $domain ) {
		if ( strpos( $domain, ':' ) ) {
			$domain = parse_url( $domain, PHP_URL_HOST );
		}
		$starttime	= microtime( true );
		$file		= fsockopen( $domain, 80, $errno, $errstr, 10 );
		$stoptime	= microtime( true );
		$status		= 0;

		if ( ! $file ) $status = 99999;// Site is down
		else {
			fclose( $file );
			$status = ( $stoptime - $starttime ) * 1000;
			$status = floor( $status );
		}

		return $status;
	}

	/**
	 * Find final destination for one url if it has 301/
	 *
	 * @since  1.6.6
	 */
	public static function find_final_url( $url, $maxRequests = 10 ) {
		$ch = curl_init();

		curl_setopt( $ch, CURLOPT_HEADER, true );
		curl_setopt( $ch, CURLOPT_NOBODY, true );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, true );
		curl_setopt( $ch, CURLOPT_MAXREDIRS, $maxRequests );
		curl_setopt( $ch, CURLOPT_TIMEOUT, 15 );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, false );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
		curl_setopt( $ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1 );

		curl_setopt( $ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Link Checker)' );

		curl_setopt( $ch, CURLOPT_URL, $url );
		curl_exec( $ch );

		$url = curl_getinfo( $ch, CURLINFO_EFFECTIVE_URL );

		curl_close( $ch );
		return $url;
	}

	/**
	 * rm empty folder recursively
	 *
	 * @since  1.6.1
	 */
	public static function rm_empty( $filename ) {
		$folder = dirname( $filename );

		// If is empty
		if ( is_dir( $folder ) && count( scandir( $folder ) ) == 2 ) {

			rmdir( $folder );

			self::rm_empty( $folder );
		}

	}

	/**
	 * Create folders recursively for file
	 *
	 * @since  1.6
	 */
	public static function mmkdir( $path, $permissions = 0777 ) {
		if ( is_dir( $path ) ) {
			return true;
		}

		$old = umask( 0 );
		mkdir( $path, $permissions, true );
		umask( $old );
	}

	/**
	 * Generate path based on id
	 *
	 * @since  1.6
	 */
	public static function path( $id, $step = 3, $path_only = false, $prefix = false ) {
		$path = '';
		for ( $i = 0; $i < strlen( $id ); $i += $step ) {
			$currP = substr( $id, 0, $i + $step );
			if ( strlen( $currP ) != $i + $step ) {
				$currP .= str_repeat( '0', $step - 1 );
			}
			$path .= $prefix . $currP . "/";
		}

		if ( $path_only ) {
			return $path;
		}

		return $path . $id;
	}

	/**
	 *	secode check
	 *
	 */
	public static function secode() {
		global $_ip;
		$ggKey = _cfg( 'google' );
		$res = self::get( "https://www.google.com/recaptcha/api/siteverify", [
			'secret'	=> $ggKey[ 'recaptcha' ],
			'response'	=> _get_post( 'g-recaptcha-response' ),
			'remoteip'	=> $_ip,
		] );
		$res = json_decode($res, true);
		if ( $res[ 'success' ] == true ) {
			return true;
		}
		$error = ! empty( $res[ 'error-codes' ] ) ? $res[ 'error-codes' ][0] : 'error';
		b( 'Wrong recaptcha Code:' . $error );
	}

	/**
	 *	email
	 *
	 */
	public static function sendmail( $target, $title, $content, $from = '' ) {
		ini_set( 'smtp_port', 587 );
		if ( defined( 'EMAIL_FROM' ) && ! $from ) $from = EMAIL_FROM;
		if(!$from) $from = 'Nill <test@test.com>';
		$headers =
			"From: $from\r\n" .
			"Reply-To: $from\r\n" .
			"Content-type: text/html; charset=utf-8";
		return @mail($target, $title, $content, $headers);
	}

	/**
	 *	file size
	 *
	 */
	public static function realSize( $filesize ) {
		if ( $filesize >= 1073741824 ) {
			$filesize = round( $filesize / 1073741824 * 100 ) / 100 . 'G';
		}
		elseif ( $filesize >= 1048576 ) {
			$filesize = round( $filesize / 1048576 * 100 ) / 100 . 'M';
		}
		elseif ( $filesize >= 1024 ) {
			$filesize = round( $filesize / 1024 * 100 ) / 100 . 'K';
		}
		else {
			$filesize = $filesize . 'B';
		}
		return $filesize;
	}

	/**
	 *	write file
	 *
	 */
	public static function write( $filename, $data, $add = false ) {
		$folder = dirname( $filename );
		if ( ! file_exists( $folder ) ) {
			try {
				mkdir( $folder, 0755, true );
			}
			catch ( \ErrorException $ex ) {
				exit( 'cant create ' . $folder . ':' . $ex->getMessage() );
			}
		}

		if ( ! $add ) {
			file_put_contents( $filename, $data );
		}
		else {
			file_put_contents( $filename, $data, FILE_APPEND );
		}

		if ( ! is_writable( $filename ) ) exit( 'can not write to ' . $filename );

		return true;
	}

	/**
	 *	read file or url
	 */
	public static function read( $url, $timeout = 300, $HTTP_REFERER = false ) {
		/**
		 * Enable hosted IP usage
		 * @since  Oct/15/2019
		 */
		$ip = false;
		if ( is_array( $url ) ) {
			$ip = $url[ 1 ];
			$url = $url[ 0 ];
		}

		if ( substr( $url, 0, 4 ) != "http" && ! file_exists( $url ) ) return false;
		if ( substr( $url, 0, 4 ) != "http" ) return file_get_contents( $url );

		$url = str_replace( ' ', '%20', $url );

		$options = [
			CURLOPT_URL				=> $url,
			CURLOPT_CONNECTTIMEOUT	=> 2,
			CURLOPT_RETURNTRANSFER	=> true,
			CURLOPT_FOLLOWLOCATION	=> true,
			CURLOPT_POSTREDIR		=> 3,
			CURLOPT_TIMEOUT			=> $timeout,
			CURLOPT_SSL_VERIFYHOST	=> false,
			CURLOPT_SSL_VERIFYPEER	=> false,
			CURLOPT_VERBOSE			=> 0,
			CURLOPT_HTTP_VERSION	=> CURL_HTTP_VERSION_1_1,
			CURLOPT_USERAGENT		=> self::UA . ( defined( 'API_V' ) ? '/' . API_V : '' ),
		];

		// curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; rv:1.7.3) Gecko/20041001 Firefox/0.10.1');
		if ( $HTTP_REFERER ) {
			$options[ CURLOPT_REFERER ] = $HTTP_REFERER;
		}

		if ( $ip ) {
			$parsed_url = parse_url( $url );

			if ( ! empty( $parsed_url[ 'host' ] ) ) {
				$dom = $parsed_url[ 'host' ];
				$port = $parsed_url[ 'scheme' ] == 'https' ? '443' : '80';

				$url = $dom . ':' . $port . ':' . $ip;

				$options[ CURLOPT_RESOLVE ] = [ $url ];
				$options[ CURLOPT_DNS_USE_GLOBAL_CACHE ] = false;
			}
		}

		$ch = curl_init();
		curl_setopt_array( $ch, $options );
		$return = curl_exec( $ch );

		$httpCode = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		$err = false;
		if ( $httpCode == 404 ) {
			$err = 404;
		}

		curl_close( $ch );

		if ( $err ) {
			throw new \Exception( $err );
		}

		return $return;
	}

	/**
	 * Get data
	 */
	public static function get( $url, $data = false, $append_headers = false, $timeout = false, $return_header = false, $auth = false, $ua = false ) {
		if (strpos($url, '?') === false) $url .= '?';

		if ( $data && is_array( $data ) ) {
			foreach ( $data as $k => $v ) {
				$url .= "$k=$v&";
			}
		}

		$options = [
			CURLOPT_URL => $url,
			CURLOPT_TIMEOUT => $timeout ?: 300,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_SSL_VERIFYHOST => false,
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_USERAGENT => self::UA . ( defined( 'API_V' ) ? '/' . API_V : '' ),
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		];
		if ( $auth !== false ) {
			$options[ CURLOPT_USERPWD ] = $auth;
		}
		if ( $return_header ) {
			$options[ CURLOPT_VERBOSE ] = 1;
			$options[ CURLOPT_HEADER ] = 1;
		}
		if ( $ua ) {
			$options[ CURLOPT_USERAGENT ] = $ua;
		}

		if ( $append_headers ) {
			$options[ CURLOPT_HTTPHEADER ] = $append_headers;
		}

		$ch = curl_init();
		curl_setopt_array( $ch, $options );
		$return = curl_exec( $ch );

		if ( $return_header ) {
			$header_size = curl_getinfo( $ch, CURLINFO_HEADER_SIZE );
		}

		curl_close( $ch );

		// Parse returned headers
		if ( $return_header ) {
			$header = substr( $return, 0, $header_size );
			$body = substr( $return, $header_size );

			$header = explode( "\n", $header );
			$header = array_map( 'trim', $header );

			return [ $header, $body ];
		}

		return $return;
	}

	/**
	 *	post data
	 *
	 */
	public static function post( $url, $data = false, $json = false, $append_headers = false, $timeout = false, $return_header = false, $auth = false ) {
		if ( $data === false ) {
			$data = [];
		}

		if ( ! $json ) {
			$header = [ 'Content-Type: application/x-www-form-urlencoded' ];
			if ( is_array( $data ) ) {
				$data = http_build_query( $data );
			}
		}
		else {
			$header = [ 'Content-Type: application/json; charset=UTF-8' ];
			$data = json_encode( $data );
		}

		if ( $append_headers ) {
			$header = array_merge( $header, $append_headers );
		}

		/**
		 * Enable hosted IP usage
		 * @since  Apr/1/2019
		 */
		$ip = false;
		if ( is_array( $url ) ) {
			$ip = $url[ 1 ];
			$url = $url[ 0 ];
		}

		$options = [
			CURLOPT_URL				=> $url,
			CURLOPT_HTTPHEADER		=> $header,
			CURLOPT_RETURNTRANSFER	=> true,
			CURLOPT_FOLLOWLOCATION	=> true,
			CURLOPT_POSTREDIR		=> 3,
			CURLOPT_TIMEOUT			=> $timeout ?: 300,
			CURLOPT_POST			=> true,
			CURLOPT_POSTFIELDS		=> $data,
			CURLOPT_SSL_VERIFYHOST	=> false,
			CURLOPT_SSL_VERIFYPEER	=> false,
			CURLOPT_VERBOSE			=> 0,
			CURLOPT_HTTP_VERSION	=> CURL_HTTP_VERSION_1_1,
			CURLOPT_USERAGENT		=> self::UA . ( defined( 'API_V' ) ? '/' . API_V : '' ),
		];

		if ( $auth !== false ) {
			$options[ CURLOPT_USERPWD ] = $auth;
		}

		if ( $ip ) {
			$parsed_url = parse_url( $url );

			if ( ! empty( $parsed_url[ 'host' ] ) ) {
				$dom = $parsed_url[ 'host' ];
				$port = $parsed_url[ 'scheme' ] == 'https' ? '443' : '80';

				$url = $dom . ':' . $port . ':' . $ip;

				$options[ CURLOPT_RESOLVE ] = [ $url ];
				$options[ CURLOPT_DNS_USE_GLOBAL_CACHE ] = false;
			}
		}

		if ( $return_header ) {
			$options[ CURLOPT_VERBOSE ] = 1;
			$options[ CURLOPT_HEADER ] = 1;
		}

		$ch = curl_init();
		curl_setopt_array( $ch, $options );
		$return = curl_exec( $ch );

		$return = self::_remove_zero_space( $return );

		if ( $return_header ) {
			$header_size = curl_getinfo( $ch, CURLINFO_HEADER_SIZE );
		}

		curl_close( $ch );

		// Parse returned headers
		if ( $return_header ) {
			$header = substr( $return, 0, $header_size );
			$body = substr( $return, $header_size );

			$header = explode( "\n", $header );
			$header = array_map( 'trim', $header );

			return [ $header, $body ];
		}

		return $return;
	}


	/**
	 * Remove Unicode zero-width space <200b><200c>
	 *
	 * @since 2.2.3
	 */
	private static function _remove_zero_space( $content ) {
		if ( is_array( $content ) ) {
			$content = array_map( 'self::_remove_zero_space', $content );
			return $content;
		}

		// Remove UTF-8 BOM if present
		// $bom = pack( 'H*','EFBBBF' );
		$content = str_replace( "\xef\xbb\xbf", '', $content );

		$content = str_replace( "\xe2\x80\x8b", '', $content );
		$content = str_replace( "\xe2\x80\x8c", '', $content );
		$content = str_replace( "\xe2\x80\x8d", '', $content );

		return $content;
	}

	/**
	 * List files under a path
	 *
	 * @access public
	 */
	public static function lll( $path ) {
		return array_diff( scandir( $path ), [ '..', '.' ] );
	}

	/**
	 *	del folder
	 *
	 */
	public static function rrmdir($path, $leavedir = false, $igfile = array(), $igdir = array()){
		if(!is_dir($path)) Return false;
		foreach(self::listfile($path, 0, $igfile) as $filename) {
			unlink($path.'/'.$filename);
		}
		foreach(self::listdir($path, 0, $igdir) as $subpath) {
			self::rrmdir($path.'/'.$subpath, $leavedir, $igfile, $igdir);
		}
		if(!$leavedir) rmdir($path);
	}

	/**
	 *	list all dir
	 *
	 */
	public static function listdir($pathname, $subfolder = 0, $ignore = array()){
		$dirlist = self::rreaddir($pathname, 2, $ignore);
		if($subfolder <= 0) return $dirlist;
		foreach($dirlist as $dir) {
			$subfolders = self::listdir($pathname.'/'.$dir, $subfolder - 1, $ignore);
			foreach($subfolders as $subdir) {
				$dirlist[] = $dir.'/'.$subdir;
			}
		}
		return $dirlist;
	}

	/**
	 *	list all files
	 *
	 */
	public static function listfile($pathname, $subfolder = 0, $ignore = array()){
		$filelist = self::rreaddir($pathname, 1, $ignore);

		if($subfolder <= 0) return $filelist;

		$dirlist = self::listdir($pathname);
		foreach($dirlist as $dir) {
			$subfolderfiles = self::listfile("$pathname/$dir", $subfolder - 1, $ignore);
			foreach($subfolderfiles as $subfile) {
				$filelist[] = "$dir/$subfile";
			}
		}
		return $filelist;
	}

	/**
	 *	self::list all
	 *
	 */
	private static function rreaddir($pathname, $mode = 1, $ignore = array()){//$mode : 1 - file 2 - folder
		$ignore = array_merge(array('.', '..'), $ignore);
		$dh = opendir($pathname);
		if($dh === false) return false;
		$dirlist = array();
		while(($filename = readdir($dh)) !== false) {
			if(in_array($filename, $ignore)) continue;
			if($mode == 1 && !is_file("$pathname/$filename")) continue;
			if($mode == 2 && !is_dir("$pathname/$filename")) continue;
			$dirlist[] = $filename;
		}
		closedir($dh);
		return $dirlist;
	}

	/**
	 * Creates a handle with lock. If already locked, return null.
	 *
	 * Assumes directory exists.
	 */
	public static function lock($filename) {

		$handle = fopen( $filename, "c+" );

		if ( ! flock ( $handle, LOCK_EX ) ) {
			fclose( $handle );
			$handle = null;
		}

		return $handle;
	}

	/**
	 * Unlocks the handle locked by lock()
	 */
	public static function unlock($handle) {
		flock( $handle, LOCK_UN );
		fclose( $handle );
	}
}
