<?php
/**
 *	DBI class
 *
 * @since  2018Jun25 charset changed to utf8mb4
 * @since  2018Jun27 cond changed `is null` to `NULL`
 * @since  2018Jul29 Support `_raw`
 * @since  2018Aug01 Add file log to halt
 * @since  2018Aug04 Added debugDumpParams to return full query of error
 * @since  2019Feb25 Throw exceptions when getting errors in `_halt()`
 * @since  2019Feb25 Drop function `is_error()`
 * @since  2019Mar25 Cache key changed to json_encode encoding
 * @since  04/24/2020 Used Redis instead of memcached
 * @since  04/28/2020 OC key prefix
 * @since  04/30/2020 OC key prefix
 * @since  Dec/19/2020 Cond `and`/`_or` supported
 * @since  Dec/19/2020 Latest version
 *
 */
defined( 'IN' ) || exit( 'Access Denied' );

class db extends instance {
	protected $_pdo = null;
	protected $_query_num;
	protected static $_db = 'test';
	protected static $_dbcfg;

	protected function __construct() {
		// connect pdo
		try {
			$dbcfg = self::$_dbcfg ?: _cfg( 'mysql' );
			if ( count( $dbcfg ) != 3 ) {
				error_log( 'PDO err: db cfg err' );
				exit( 'db cfg err' );
			}
			$this->_pdo = new \PDO( 'mysql:host=' . $dbcfg[ 'host' ] . ';dbname=' . self::$_db . ';charset=utf8mb4', $dbcfg[ 'user' ], $dbcfg[ 'pswd' ], [ \PDO::MYSQL_ATTR_FOUND_ROWS => true ] );
		}
		catch ( \PDOException $e ) {
			return $this->_halt( $e->getMessage() );
		}

		if ( ! $this->_pdo ) {
			$this->_halt( 'Can not establish pdo connection' );
		}
	}

	/**
	 * Set default database
	 */
	public static function init_db( $db ) {
		self::$_db = $db;
	}

	/**
	 * Set host info to connect db
	 */
	public static function init( $host, $user, $pswd ) {
		self::$_dbcfg = array(
			'host'	=> $host,
			'user'	=> $user,
			'pswd'	=> $pswd,
		);
	}

	/**
	 * Release db connection
	 */
	public static function close() {
		self::cls()->_pdo = null;
		self::unset_cls();
	}

	/**
	 * Run store procedure
	 */
	public static function p() {
		return call_user_func_array( array( self::cls(), '_p' ), func_get_args() );
	}

	/**
	 *
	 * @param type $procName
	 * @param type $params
	 * @param type $resultRow: 0: none, 1: one row, 2: multi-row
	 * @param type $isMulti: multiple datasets
	 * @return type
	 * @throws DBException
	 */
	public static function callsp($procName, $params, $resultRow=2, $isMulti=false) {
		$db = self::cls();
		$db->_query_num++;
		$pdo = $db->_pdo;

		$args = array_values($params);
		$param_num = count($params);
		$sql = 'CALL ' . $procName . '(';

		if ($param_num > 0) {
			$sql .= implode(',', array_fill(0, $param_num, '?'));
		}
		$sql .= ')';
		$data = $isMulti ? [] : null;
		try {
			$stmt = $pdo->prepare($sql);
			$stmt->execute($args);
			if ($db->_check_halt( $stmt, $sql, true)) {
				// try again after 0.2 sec
				error_log('OOPS retry for ' . $procName);
				usleep(200000);
				$stmt = $pdo->prepare($sql);
				$stmt->execute($args);
				$db->_check_halt($stmt, $sql );
			}
			if ($resultRow > 0) {
				do {
					$d = $stmt->fetchAll(\PDO::FETCH_ASSOC);
					if (count($d) == 1 && !empty($d[0]['USER_ERROR'])) {
						$msg = $d[0]['USER_ERROR'];
						throw new \Exception('DB err: ' . $msg);
					}
					if (!$isMulti) {
						$data = ($resultRow == 1 && !empty($d)) ? $d[0] : $d;
						break;
					}
					if (!empty($d)) {
						$data[] = $d;
					}
				} while ($stmt->nextRowset());
			}

			$stmt = null;
		} catch (\PDOException $e) {
			return $db->_halt( $e->getMessage(), $sql );
		}
		return $data;

	}

	protected function _p() {
		$this->_query_num++;

		$args = func_get_args();
		$procedure_name = $args[ 0 ];
		unset( $args[ 0 ] );
		$args = array_values( $args );
		$param_num = count( $args );

		if ( $param_num > 0 ) {
			$sql = 'CALL ' . $procedure_name . '( ' . implode( ',', array_fill( 0, $param_num, '?' ) ) . ' )';
		}
		else {
			$sql = 'CALL ' . $procedure_name . '()';
		}

		try {
			$stmt = $this->_pdo->prepare( $sql );
			$stmt->execute( $args );
		}
		catch ( \PDOException $e ) {
			return $this->_halt( $e->getMessage(), $sql );
		}

		$this->_check_halt( $stmt, $sql );

		$data = [];
		do {
			$d = $stmt->fetchAll( \PDO::FETCH_ASSOC );
			if ( $d ) {
				$data[] = $d;
			}
		} while ( $stmt->nextRowset() );

		$stmt = null;

		return $data;

	}

	/**
	 * Insert into db
	 */
	public static function i( $tb, $cond ) {
		return self::cls()->_i( $tb, $cond );
	}

	protected function _i( $tb, $cond ) {
		$this->_query_num++;

		$prepareSQL = array();
		$exeSQL = array();
		foreach ( $cond as $key => $value ) {
			if ( isint( $key ) ) {
				$prepareSQL[] = " $value ";
			}
			else {
				$prepareSQL[] = " `$key`=? ";
				$exeSQL[] = $value;
			}
		}

		$tb = $this->_prefix_tb( $tb );
		$sql = "INSERT INTO $tb SET ". implode( ',', $prepareSQL );

		try {
			$stmt = $this->_pdo->prepare( $sql );
			$stmt->execute( $exeSQL );
		}
		catch ( \PDOException $e ) {
			return $this->_halt( $e->getMessage(), $sql );
		}

		$this->_check_halt( $stmt, $sql );

		$stmt = null;

		return $this->_pdo->lastInsertId();
	}

	/**
	 * Add prefix to table names
	 * Note: `raw()` must manually add this prefix
	 */
	public static function prefix_tb( $tb ) {
		return self::cls()->_prefix_tb( $tb );
	}

	private function _prefix_tb( $tb ) {
		$tb = DB_PREFIX . '_' . $tb;

		if ( strpos( $tb, 'JOIN ' ) ) {
			$tb = str_replace( 'JOIN ', 'JOIN ' . DB_PREFIX . '_', $tb );
		}

		if ( strpos( $tb, ',' ) ) {
			$tb = preg_replace( '/,([a-z]+)/', ',' . DB_PREFIX . '_$1', $tb );
		}

		return $tb;
	}

	/**
	 * Insert multiple lines
	 */
	public static function i_multi( $tb, $data ) {
		return self::cls()->_i_multi( $tb, $data );
	}

	protected function _i_multi( $tb, $data ) {
		$this->_query_num++;

		$exeSQL = array();
		foreach ( $data as $v ) {
			foreach ( $v as $v2 ) {
				$exeSQL[] = $v2;
			}
		}

		$tb = $this->_prefix_tb( $tb );
		$sql = "INSERT INTO $tb ( `" . implode( '`,`', array_keys( $data[ 0 ] ) ) . "` ) VALUES ";
		$sql .= implode( ',', array_map(
			function( $el ) { return '(' . implode( ',', $el ) . ')'; },
			array_chunk( array_fill( 0, count( $data ) * count( $data[ 0 ] ), '?' ), count( $data[ 0 ] ) )
		) );

		try {
			$stmt = $this->_pdo->prepare( $sql );
			$stmt->execute( $exeSQL );
		}
		catch ( \PDOException $e ) {
			return $this->_halt( $e->getMessage(), $sql );
		}

		$this->_check_halt( $stmt, $sql );

		$stmt = null;

		return $this->_pdo->lastInsertId();
	}

	/**
	 * Replace
	 */
	public static function r( $tb, $cond ) {
		return self::cls()->_r( $tb, $cond );
	}

	protected function _r( $tb, $cond ) {
		$this->_query_num++;

		$prepareSQL = array();
		$exeSQL = array();
		foreach ( $cond as $key => $value ) {
			if ( isint( $key ) ) {
				$prepareSQL[] = " $value ";
			}
			else {
				$prepareSQL[] = " `$key`=? ";
				$exeSQL[] = $value;
			}
		}
		$tb = $this->_prefix_tb( $tb );
		$sql = "REPLACE INTO $tb SET " . implode( ',', $prepareSQL );

		try {
			$stmt = $this->_pdo->prepare( $sql );
			$stmt->execute( $exeSQL );
		}
		catch ( \PDOException $e ) {
			return $this->_halt( $e->getMessage(), $sql );
		}

		$this->_check_halt( $stmt, $sql );

		$stmt = null;

		return $this->_pdo->lastInsertId();
	}

	/**
	 * Update
	 */
	public static function u( $tb, $to, $cond ) {
		return self::cls()->_u( $tb, $to, $cond );
	}

	protected function _u( $tb, $to, $cond ) {
		$this->_query_num++;

		$prepareSQL = array();
		$exeSQL = array();
		foreach ( $to as $key => $value ) {
			if ( isint( $key ) ) {
				$prepareSQL[] = " $value ";
			}
			else {
				$prepareSQL[] = " `$key`=? ";
				$exeSQL[] = $value;
			}
		}

		$tb = $this->_prefix_tb( $tb );
		$sql = "UPDATE $tb SET " . implode( ',', $prepareSQL );
		$tmp = self::build_cond( $cond );
		if ( $tmp ) {
			$sql .= " WHERE " . $tmp;
		}

		try {
			$stmt = $this->_pdo->prepare( $sql );
			$stmt->execute( array_merge( $exeSQL, self::build_cond( $cond, 1 ) ) );
		}
		catch ( \PDOException $e ) {
			return $this->_halt( $e->getMessage(), $sql );
		}

		$this->_check_halt( $stmt, $sql );

		$num = $stmt->rowCount();
		$stmt = null;

		return $num;
	}

	/**
	 * Delete
	 */
	public static function d( $tb, $cond ) {
		return self::cls()->_d( $tb, $cond );
	}

	protected function _d( $tb, $cond ) {
		$this->_query_num++;

		$tb = $this->_prefix_tb( $tb );
		$sql = "DELETE FROM $tb ";
		$tmp = self::build_cond( $cond );
		if ( $tmp ) {
			$sql .= " WHERE " . $tmp;
		}
		try {
			$stmt = $this->_pdo->prepare( $sql );
			$stmt->execute( self::build_cond( $cond, 1 ) );
		}
		catch ( \PDOException $e ) {
			return $this->_halt( $e->getMessage(), $sql );
		}

		$this->_check_halt( $stmt, $sql );

		$num = $stmt->rowCount();
		$stmt = null;

		return $num;
	}

	/**
	 * Select one (memcached)
	 */
	public static function ms( $tb, $cond = null, $orderBy = false, $expired = 3600 ) {
		return self::cls()->_oc_get( '_s', $expired, $tb, $cond, $orderBy );
	}

	/**
	 * Select one
	 */
	public static function s( $tb, $cond = null, $orderBy = false ) {
		return self::cls()->_s( $tb, $cond, $orderBy );
	}

	protected function _s( $tb, $cond = null, $orderBy = false ) {
		$this->_query_num++;

		$f = '*';
		if ( is_array( $tb ) ) {
			$f = $tb[ 0 ];
			$tb = $tb[ 1 ];
		}

		$tb = $this->_prefix_tb( $tb );
		$sql = "SELECT $f FROM $tb ";
		if ( $cond !== null ) {
			$tmp = self::build_cond( $cond );
			if ( $tmp ) {
				$sql .= " WHERE " . $tmp;
			}
		}
		if ( $orderBy ) {
			$sql .= " ORDER BY $orderBy";
		}

		$sql .= " LIMIT 1";

		try {
			$stmt = $this->_pdo->prepare( $sql );
			$cond_built = self::build_cond( $cond, true );

			$tmp = ! empty( $_SERVER[ 'REQUEST_URI' ] ) ? $_SERVER[ 'REQUEST_URI' ] : '';
			$if_clear = true;// filesize( '/tmp/debug.sql' ) > 30000;
			// f::write( '/tmp/debug.sql', IP . " " . $tmp . "\n\n" . var_export( $sql, true ) . "\n\n" . var_export( $cond_built, true ) . "\n--------------------\n", ! $if_clear );

			$stmt->execute( $cond_built );
		}
		catch ( \PDOException $e ) {
			return $this->_halt( $e->getMessage(), $sql );
		}

		$this->_check_halt( $stmt, $sql );

		$data = $stmt->fetch( PDO::FETCH_ASSOC );
		$stmt = null;

		return $data;
	}

	/**
	 * Select many/all (memcached)
	 */
	public static function msa( $tb, $cond = null, $orderBy = false, $limit = false, $groupBy = false, $expired = 3600 ) {
		return self::cls()->_oc_get( '_sa', $expired, $tb, $cond, $orderBy, $limit, $groupBy );
	}

	/**
	 * Select many/all
	 */
	public static function sa( $tb, $cond = null, $orderBy = false, $limit = false, $groupBy = false ) {
		return self::cls()->_sa( $tb, $cond, $orderBy, $limit, $groupBy );
	}

	protected function _sa( $tb, $cond = null, $orderBy = false, $limit = false, $groupBy = false ) {
		$this->_query_num++;

		$f = '*';
		if ( is_array( $tb ) ) {
			$f = $tb[ 0 ];
			$tb = $tb[ 1 ];
		}

		$tb = $this->_prefix_tb( $tb );
		$sql = "SELECT $f FROM $tb ";
		if ( $cond !== null ) {
			$tmp = self::build_cond( $cond );
			if ( $tmp ) {
				$sql .= " WHERE " . $tmp;
			}
		}
		if ( $groupBy ) {
			$sql .= " GROUP BY $groupBy";
		}
		if ( $orderBy ) {
			$sql .= " ORDER BY $orderBy";
		}
		if ( $limit ) {
			$sql .= " LIMIT $limit";
		}

		try {
			$stmt = $this->_pdo->prepare( $sql );

			$cond_built = self::build_cond( $cond, true );

			$tmp = ! empty( $_SERVER[ 'REQUEST_URI' ] ) ? $_SERVER[ 'REQUEST_URI' ] : '';
			$if_clear = true;
			// $if_clear = filesize( '/tmp/debug.sql' ) > 30000;
			// f::write( '/tmp/debug.sql', IP . " " . $tmp . "\n\n" . var_export( $sql, true ) . "\n\n" . var_export( $cond_built, true ) . "\n--------------------\n", ! $if_clear );

			$stmt->execute( $cond_built );
		}
		catch ( \PDOException $e ) {
			return $this->_halt( $e->getMessage(), $sql );
		}

		$this->_check_halt( $stmt, $sql );

		$data = $stmt->fetchAll( PDO::FETCH_ASSOC );
		$stmt = null;

		return $data;
	}

	/**
	 * Count (memcached)
	 */
	public static function mc( $tb, $cond = null, $groupBy = false, $expired = 3600 ) {
		return self::cls()->_oc_get( '_c', $expired, $tb, $cond, $groupBy );

	}

	/**
	 * Count
	 */
	public static function c( $tb, $cond = null, $groupBy = false ) {
		return self::cls()->_c( $tb, $cond, $groupBy );
	}

	protected function _c( $tb, $cond = null, $groupBy = false ) {
		$this->_query_num++;

		$f = '*';
		if ( is_array( $tb ) ) {
			// $f = $tb[ 0 ];
			$tb = $tb[ 1 ];
		}

		$tb = $this->_prefix_tb( $tb );
		$sql = "SELECT COUNT($f) AS num FROM $tb ";
		if ( $cond !== null ) {
			$tmp = self::build_cond( $cond );
			if ( $tmp ) {
				$sql .= " WHERE " . $tmp;
			}
		}
		if ( $groupBy ) {
			$sql .= " GROUP BY $groupBy";
		}

		try {
			$stmt = $this->_pdo->prepare( $sql );
			$stmt->execute( self::build_cond( $cond, 1 ) );
		}
		catch ( \PDOException $e ) {
			return $this->_halt( $e->getMessage(), $sql );
		}

		$this->_check_halt( $stmt, $sql );

		$data = $stmt->fetch( PDO::FETCH_ASSOC );
		$stmt = null;

		return $data[ 'num' ];
	}

	/**
	 * Raw Select many/all (memcached)
	 */
	public static function mraw_sa( $raw, $cond = null, $orderBy = false, $limit = false, $groupBy = false, $expired = 3600 ) {
		return self::cls()->_oc_get( '_raw_sa', $expired, $raw, $cond, $orderBy, $limit, $groupBy );

	}

	/**
	 * Raw select many
	 */
	public static function raw_sa( $raw, $cond = null, $orderBy = false, $limit = false, $groupBy = false ) {
		return self::cls()->_raw_sa( $raw, $cond, $orderBy, $limit, $groupBy );
	}

	protected function _raw_sa( $raw, $cond = null, $orderBy = false, $limit = false, $groupBy = false ) {
		$this->_query_num++;

		$sql = $raw;
		if ( $groupBy ) {
			$sql .= " GROUP BY $groupBy";
		}
		if ( $orderBy ) {
			$sql .= " ORDER BY $orderBy";
		}
		if ( $limit && strpos( strtolower( $limit ), 'limit' ) === false ) {
			$sql .= " LIMIT $limit";
		}

		try {
			$stmt = $this->_pdo->prepare( $sql );
			$stmt->execute( self::build_cond( $cond, 1 ) );
		}
		catch ( \PDOException $e ) {
			return $this->_halt( $e->getMessage(), $sql );
		}

		$this->_check_halt( $stmt, $sql );

		$data = $stmt->fetchAll( PDO::FETCH_ASSOC );
		$stmt = null;

		return $data;
	}

	/**
	 * Raw count (memcached)
	 */
	public static function mraw_c( $sql, $cond = null, $expired = 3600 ) {
		return self::cls()->_oc_get( '_raw_c', $expired, $sql, $cond );

	}

	/**
	 * Raw count
	 */
	public static function raw_c( $sql, $cond = null ) {
		return self::cls()->_raw_c( $sql, $cond );
	}

	protected function _raw_c( $sql, $cond = null ) {
		$this->_query_num++;

		try {
			if ( $cond ) {
				$stmt = $this->_pdo->prepare( $sql );// $sql is supposed to have build_cond included already
				$stmt->execute( self::build_cond( $cond, 1 ) );
			}
			else {
				$stmt = $this->_pdo->query( $sql );
			}

		}
		catch ( \PDOException $e ) {
			return $this->_halt( $e->getMessage(), $sql );
		}

		$this->_check_halt( $stmt, $sql );

		$data = $stmt->rowCount();
		$stmt = null;

		return $data;
	}

	/**
	 * Raw sql (memcached)
	 */
	public static function mraw( $sql, $expired = 3600 ) {
		return self::cls()->_oc_get( '_raw', $expired, $sql );

	}

	/**
	 * Raw sql
	 * Warning: Must manually append table prefix by `db::prefix_tb()`
	 */
	public static function raw( $sql ) {
		return self::cls()->_raw( $sql );
	}

	protected function _raw( $sql ) {
		$this->_query_num++;

		try {
			$stmt = $this->_pdo->query( $sql );
		}
		catch ( \PDOException $e ) {
			return $this->_halt( $e->getMessage(), $sql );
		}

		$this->_check_halt( $stmt, $sql );

		try {
			$data = $stmt->fetchAll( PDO::FETCH_ASSOC );
		}
		catch ( \PDOException $e ) {
			$data = false;
		}

		$stmt = null;

		return $data;
	}

	/**
	 * SQL num count
	 */
	public static function num() {
		return self::cls()->_query_num;
	}

	/**
	 * Build conditions
	 */
	public static function build_cond( $cond = null, $returnExe = false, $combineOr = false ) {
		if ( $cond === null || ( ! $cond && is_array( $cond ) ) ) {
			return null;
		}
		if ( ! is_array( $cond ) ) {
			$cond = [ 'id' => $cond ];
		}

		$prepareSQL = $exeSQL = [];
		foreach ( $cond as $key => $value ) {
			if ( ! isint( $key ) ) {
				$key = trim( $key );
			}

			if ( in_array( $key, [ '_or', 'or_' ], true ) ) {
				$key = 'or';
			}

			if ( in_array( $key, [ '_and', 'and_' ], true ) ) { // If not sure if the key existed or not, can use `and` to avoid conflicts
				$key = 'and';
			}

			// `or` condition recursive
			if ( $key === 'or' ) {
				$prepareSQL[] = self::build_cond( $value, false, true );
				$exeSQL = array_merge( $exeSQL, self::build_cond( $value, true, true ) );
				continue;
			}

			// `and` condition recursive
			if ( $key === 'and' ) {
				$prepareSQL[] = self::build_cond( $value );
				$exeSQL = array_merge( $exeSQL, self::build_cond( $value, true ) );
				continue;
			}

			if ( in_array( $key, [ 'raw', '_raw' ], true ) && ! is_array( $value ) ) {
				$prepareSQL[] = $value;
				continue;
			}

			$key = self::_keyQuote( $key );
			if ( ! is_array( $value ) ) {
				if ( $value === 'NULL' ) {
					$prepareSQL[] = " $key IS NULL ";
				}
				else {
					$prepareSQL[] = " $key=? ";
					$exeSQL[] = $value;
				}
			}
			else { // $value is array
				// comparison
				if ( ! empty( $value[ 0 ] ) && in_array( $value[ 0 ], array( '!=', '>', '<', '>=', '<=' ) ) && count( $value ) == 2 ) {
					$prepareSQL[] = "$key $value[0] ?";
					$exeSQL[] = $value[ 1 ];
				}
				// between
				elseif ( ! empty( $value[ 0 ] ) && $value[ 0 ] === 'between' && count( $value ) == 3 ) {
					$prepareSQL[] = "$key BETWEEN ? AND ?";
					$exeSQL[] = $value[ 1 ];
					$exeSQL[] = $value[ 2 ];
				}
				// like
				elseif ( ! empty( $value[ 0 ] ) && $value[ 0 ] === 'like' && count( $value ) == 2 ) {
					$prepareSQL[] = "$key LIKE ?";
					$exeSQL[] = $value[ 1 ];
				}
				// not like
				elseif ( ! empty( $value[ 0 ] ) && $value[ 0 ] === 'not like' && count( $value ) == 2 ) {
					$prepareSQL[] = "$key NOT LIKE ?";
					$exeSQL[] = $value[ 1 ];
				}
				// NOT IN
				elseif ( ! empty( $value[ 0 ] ) && $value[ 0 ] === 'not in' && count( $value ) == 2 && is_array( $value[ 1 ] ) ) {
					if ( empty( $value[ 1 ] ) ) {
						$value[ 1 ] = [ -999 ];
					}
					$prepareSQL[] = "$key NOT IN (" . implode( ',', array_fill( 0, count( $value[ 1 ] ), '?' ) ) . ")";
					foreach ( $value[ 1 ] as $tmp ) {
						$exeSQL[] = $tmp;
					}
				}
				// IN
				elseif ( ! empty( $value[ 0 ] ) && $value[ 0 ] === 'in' && count( $value ) == 2 && is_array( $value[ 1 ] ) ) {
					if ( empty( $value[ 1 ] ) ) {
						$value[ 1 ] = [ -999 ];
					}
					$prepareSQL[] = "$key IN (" . implode( ',', array_fill( 0, count( $value[ 1 ] ), '?' ) ) . ")";
					foreach ( $value[ 1 ] as $tmp ) {
						$exeSQL[] = $tmp;
					}
				}
				// Bitwise And
				elseif ( ! empty( $value[ 0 ] ) && $value[ 0 ] === 'bit' && count( $value ) == 2 && (int)$value[ 1 ] > 0 ) {
					$prepareSQL[] = "( $key & ? ) = ?";
					$exeSQL[] = (int)$value[ 1 ];
					$exeSQL[] = (int)$value[ 1 ];
				}
				// Bitwise Nor
				elseif ( ! empty( $value[ 0 ] ) && $value[ 0 ] === 'bit nor' && count( $value ) == 2 && (int)$value[ 1 ] > 0 ) {
					$prepareSQL[] = "( $key & ? ) != ?";
					$exeSQL[] = (int)$value[ 1 ];
					$exeSQL[] = (int)$value[ 1 ];
				}
				// recursive
				elseif ( count( $value ) > 1 ) {
					$prepareSQL[] = self::build_cond( $value, false );
					$exeSQL = array_merge( $exeSQL, self::build_cond( $value, true ) );
					continue;
				}
				else {
					error_log( 'Unknown SQL: ' . var_export( $cond, true ) );
					exit( 'Unknown SQL' );
				}
			}
		}

		if ( ! $returnExe ) {
			return '(' . implode( ( $combineOr ? ' OR ' : ' AND ' ), $prepareSQL ) . ')';
		}
		return $exeSQL;
	}

	/**
	 * Quote field name
	 */
	protected static function _keyQuote( $key ) {
		if ( isint( $key ) ) $key = 'id';

		// IF key has table name
		if ( strpos( $key, '.' ) ) {
			$key = explode( '.', $key );
			$key = "$key[0].`$key[1]`";
		}
		else {
			$key = "`$key`";
		}

		return $key;
	}

	/**
	 * Check and return error
	 */
	protected function _check_halt( $stmt = false, $sql = false, $softcheck = false ) {
		$err = ! $stmt ? $this->_pdo->errorInfo() : $stmt->errorInfo();
		if ( $err[ 2 ] ) {
			if ( $softcheck && strpos( $err[ 2 ], 'Deadlock found when trying to get lock; try restarting transaction' ) !== false ) {
				return true;
			}
			$this->_halt( $err[ 2 ], $sql );
		}

		return false;
		// $handle = $stmt ?: $this->_pdo;

		// $err = $handle->errorInfo();

		// if ( $err[ 2 ] ) {
		// 	$this->_halt( $err[ 2 ], $handle );
		// }
	}

	/**
	 * Generate error info
	 *
	 * @param  string $msg Error message
	 */
	protected function _halt( $msg, $sql = false ) {
		// Gnerate backtrace files
		$trace_log = ' -> ';
		$backtrace_limit = 5;
		$trace = version_compare( PHP_VERSION, '5.4.0', '<' ) ? debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS ) : debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, $backtrace_limit + 2 );
		for ( $i=1; $i <= $backtrace_limit + 1; $i++ ) {// the 0st item is push()
			if ( empty( $trace[ $i ][ 'class' ] ) ) {
				if ( empty( $trace[ $i ][ 'file' ] ) ) {
					break;
				}
				$log = $trace[ $i ][ 'file' ] . ' ';
			}
			else {
				if ( substr( $trace[ $i ][ 'file' ], -11 ) == 'db.cls.php' ) {
					continue;
				}

				$log = $trace[ $i ][ 'file' ] . ' ' . $trace[ $i ][ 'class' ] . $trace[ $i ][ 'type' ] . $trace[ $i ][ 'function' ] . '()';
			}

			if ( ! empty( $trace[ $i ][ 'line' ] ) ) {
				$log .= '@' . $trace[ $i ][ 'line' ];
			}
			$trace_log .= "\n\t\t --- $log";
		}

		error_log( 'DBI err: ' . $msg . "\n===SQL=== " . $sql . " ===\n" . $trace_log . "🌸\n\n" );
		// if ( $handle ) {
		// 	try {
		// 		ob_start();
		// 		$handle->debugDumpParams();
		// 		$r = ob_get_contents();
		// 		ob_end_clean();
		// 		error_log( $r );
		// 	}
		// 	catch ( \Exception $ex ) {

		// 	}
		// }

		throw new \Exception( 'DBI err: ' . $msg );
	}

	/**
	 * Get data from OC. Set if not exist
	 *
	 * @since  04/24/2020
	 */
	protected function _oc_get() {
		$params = func_get_args();

		// Validate callback func name
		$callback = $params[ 0 ];
		if ( ! method_exists( $this, $callback ) ) {
			error_log( 'Unknown Callback func ' . $callback );
			return false;
		}

		$expired = $params[ 1 ];

		// Get params that will send to callback func
		unset( $params[ 0 ] );
		unset( $params[ 1 ] );
		$params = array_values( $params );

		$key = 'db' . $callback . '_' . $this->_cache_key( $params );
		if ( ! $rows = oc::get( $key ) ) {
			$rows = call_user_func_array( [ $this, $callback ], $params );
			oc::set( $key, json_encode( $rows ), $expired );
		}
		else {
			$rows = json_decode( $rows, true );
		}

		return $rows;
	}

	/**
	 * Get cache key for memcached operation
	 */
	protected function _cache_key( $arr ) {
		$key = md5( base64_encode( json_encode( $arr ) ) );
		return $key;
	}

}