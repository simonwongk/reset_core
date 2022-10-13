<?php
defined( 'IN' ) || exit( 'Access Denied' );

class oc extends instance {
	protected $_redis = null;

	protected function __construct() {
		// connect pdo
		$cfg = _cfg( 'redis' );
		if ( empty( $cfg[ 'host' ] ) || empty( $cfg[ 'port' ] ) ) {
			throw new \Exception( 'Redis cfg err' );
		}
		$this->_redis = new \Redis();
		$conn = $this->_redis->connect( $cfg[ 'host' ], $cfg[ 'port' ] );

		if ( ! $conn ) {
			throw new \Exception( 'Can not establish redis connection' );
		}
	}

	/**
	 * Counter++
	 */
	public static function incr( $key ) {
		$oc = self::cls()->_redis;
		$key = CFG_PREFIX . $key;
		return $oc->incr( $key );
	}

	/**
	 * Get a value
	 */
	public static function get( $key ) {
		$oc = self::cls()->_redis;
		$key = CFG_PREFIX . $key;
		return $oc->get( $key );
	}

	/**
	 * Set a value
	 */
	public static function set( $key, $val, $ttl = null ) {
		if ( is_array( $val ) ) {
			throw new \Exception( 'redis val cant be array in oc::set()' );
		}

		$oc = self::cls()->_redis;
		$key = CFG_PREFIX . $key;
		if ( $ttl === null ) {
			return $oc->set( $key, $val );
		}

		return $oc->setEx( $key, $ttl, $val );
	}

	/**
	 * Prepend a string to a list
	 * NOTE: this can't set TTL
	 */
	public static function prepend( $key, $val ) {
		$oc = self::cls()->_redis;
		$key = CFG_PREFIX . $key;
		return $oc->lPush( $key, $val );
	}

	/**
	 * Append a string to a list
	 */
	public static function push( $key, $val ) {
		$oc = self::cls()->_redis;
		$key = CFG_PREFIX . $key;
		return $oc->rPush( $key, $val );
	}

	/**
	 * Get a list
	 */
	public static function list2( $key ) {
		$oc = self::cls()->_redis;
		$key = CFG_PREFIX . $key;
		return $oc->lRange( $key, 0, -1 );
	}

	/**
	 * Delete a list
	 */
	public static function del( $key ) {
		$oc = self::cls()->_redis;
		$key = CFG_PREFIX . $key;
		return $oc->del( $key );
	}

	/**
	 * Trim a list to a certain length by dropping the last extra elements
	 */
	public static function trim( $key, $length ) {
		$oc = self::cls()->_redis;
		$key = CFG_PREFIX . $key;
		return $oc->lTrim( $key, 0, $length - 1 );
	}

	/**
	 * Trim a list
	 */
	public static function ltrim( $key, $start, $end ) {
		$oc = self::cls()->_redis;
		$key = CFG_PREFIX . $key;
		return $oc->lTrim( $key, $start, $end );
	}
}