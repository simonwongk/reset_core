<?php
/**
 *	ip geo handler
 *	@since  Jan/12/2020 New `geo_md5()` func
 */
defined( 'IN' ) || exit;

class ip {
	const PREFIX_SET = array(
		'continent',
		'continent_code',
		'country',
		'country_code',
		'subdivision',
		'subdivision_code',
		'city',
		'postal',
	);

	/**
	 * Get visitor's IP
	 */
	public static function me() {
		$_ip = '';
		if ( function_exists( 'apache_request_headers' ) ) {
			$apache_headers = apache_request_headers();
			$_ip = ! empty( $apache_headers['True-Client-IP'] ) ? $apache_headers['True-Client-IP'] : false;
			if ( ! $_ip ) {
				$_ip = ! empty( $apache_headers['X-Forwarded-For'] ) ? $apache_headers['X-Forwarded-For'] : false;
			}
		}

		if ( ! $_ip && ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$_ip = $_SERVER['REMOTE_ADDR'];
		}

		if ( strpos( $_ip, ',' ) ) {
			$_ip = explode( ',', $_ip );
			$_ip = trim( $_ip[ 0 ] );
		}

		return preg_replace( '/^(\d+\.\d+\.\d+\.\d+):\d+$/', '\1', $_ip );
	}

	/**
	 * Get geo info of one IP
	 */
	public static function geo( $ip = false ) {
		if ( ! $ip ) {
			$ip = self::me();
		}

		$geo_list = oc::get( 'geoip.' . $ip );
		if ( $geo_list ) {
			return str2arr( $geo_list );
		}

		$data = f::get( "https://doapi.us/ip/$ip/json" );

		$data = json_decode( $data, true );

		// Build geo data
		$geo_list = array( 'ip' => $ip );
		foreach ( self::PREFIX_SET as $tag ) {
			$geo_list[ $tag ] = ! empty( $data[ $tag ] ) ? trim( $data[ $tag ] ) : false;
		}

		oc::set( 'geoip.' . $ip, arr2str( $geo_list ) );

		return $geo_list;
	}

	/**
	 * Get geo md5 w/o IP
	 */
	public static function geo_md5( $ip = false ) {
		$geo_list = self::geo( $ip );
		unset( $geo_list[ 'ip' ] );
		return md5( arr2str( $geo_list ) );
	}
}