<?php
/**
 * The abstract instance

---------- Database: acp -------------
CREATE TABLE `menu_col` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `p` varchar(255) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `type` varchar(50) NOT NULL DEFAULT 'dropdown',
  `values` varchar(800) DEFAULT NULL COMMENT 'array of value and color for dropdown',
  `priority` smallint(6) DEFAULT NULL,
  `active` tinyint(4) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `p` (`p`),
  KEY `priority` (`priority`),
  KEY `active` (`active`),
  KEY `p_2` (`p`,`active`),
  KEY `p_3` (`p`,`active`,`title`),
  KEY `active_2` (`active`,`p`,`type`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4;

CREATE TABLE `menu_data` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `menu_col_id` int(11) DEFAULT NULL,
  `ref_id` int(11) DEFAULT NULL COMMENT 'Related record ID',
  `val` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `data_id` (`ref_id`,`menu_col_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `ps` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `p` varchar(255) NOT NULL DEFAULT '',
  `ref_id` int(11) DEFAULT NULL,
  `acp_id` int(11) DEFAULT NULL,
  `acp_truename` varchar(255) DEFAULT NULL,
  `info` text DEFAULT NULL,
  `dateline` int(11) DEFAULT NULL,
  `active` tinyint(4) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `dateline` (`dateline`),
  KEY `p` (`p`,`active`,`ref_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `files` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `p` varchar(255) NOT NULL,
  `ref_id` int(11) NOT NULL,
  `filename` varchar(255) NOT NULL DEFAULT '',
  `size` varchar(255) NOT NULL,
  `path` varchar(255) NOT NULL DEFAULT '' COMMENT '带ID&后缀的完整path',
  `postfix` varchar(20) NOT NULL COMMENT '后缀',
  `acp_id` int(11) NOT NULL,
  `acp_truename` varchar(255) NOT NULL DEFAULT '',
  `dateline` int(11) NOT NULL,
  `active` tinyint(4) NOT NULL COMMENT '-1 1',
  `icon` varchar(255) NOT NULL DEFAULT '',
  `preview` varchar(255) NOT NULL DEFAULT '' COMMENT 'PDF對應png路徑，即path.png',
  `type` tinyint(4) NOT NULL DEFAULT 0,
  `description` varchar(500) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `p,ref_id,active` (`p`,`ref_id`,`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `meta_data` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `p` varchar(255) NOT NULL DEFAULT '',
  `ref_id` int(11) NOT NULL,
  `meta_key` varchar(255) NOT NULL DEFAULT '',
  `val` varchar(500) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `p,ref_id,meta_key` (`p`,`ref_id`,`meta_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

---------- Database: log -------------
CREATE TABLE `actlog` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `p` varchar(255) NOT NULL DEFAULT '',
  `row_id` int(11) DEFAULT NULL,
  `acp_id` int(11) DEFAULT NULL,
  `acp_truename` varchar(255) DEFAULT NULL,
  `info` text DEFAULT NULL,
  `dateline` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `p` (`p`,`row_id`),
  KEY `dateline` (`dateline`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `cols_diy` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `acp_id` int(11) NOT NULL,
  `p` varchar(255) NOT NULL DEFAULT '',
  `cols_hide` varchar(1000) NOT NULL DEFAULT '',
  `cols_priority` varchar(1000) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `acp_id` (`acp_id`,`p`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

 */
defined( 'IN' ) || exit;

use \sys\admin;

abstract class instance extends root_cols_diy {
	const TB_ACTLOG = 'log.actlog';
	const TB_PS = 'acp.ps';
	const TB_FILES = 'acp.files';
	const TB_META_BASE = 'acp.meta_data';

	// For List() usage
	protected $cond = [];
	protected $filter_list = [];
	protected $curr_filters = [];
	protected $tpl_data = [];
	protected $o01link = '';
	protected $total = 0;
	protected $pagelink;
	private $i = 0;

	// Instance set
	private static $_instances;

	/**
	 * Call this if required office
	 */
	protected function __construct() {
		// Koffice validate
		if ( ! defined( 'CLI' ) && defined( 'static::NEED_K' ) ) {
			if ( ! defined( 'K' ) || ! K ) {
				b( 'Please set your office by admin first. 請先設置分社' );
			}

			$koffice = self::cls( 'sys\koffice' )->row_by_id( K );
			! defined( 'CONTRACT_PREFIX' ) && define( 'CONTRACT_PREFIX', $koffice[ 'contract' ] );

			if ( $koffice[ 'timezone' ] ) {
				ini_set( 'date.timezone', $koffice[ 'timezone' ] );
				date_default_timezone_set( $koffice[ 'timezone' ] );
			}
		}
	}

	/**
	 * Generic meta data Set
	 */
	public function set_meta( $ref_id, $meta_key, $val, $p = false ) {
		$data = [
			'p' => $p ?: $this->_get_p(),
			'ref_id' => $ref_id,
			'meta_key' => $meta_key,
		];

		// Delete empty data
		if ( ! $val ) {
			return db::d( static::TB_META_BASE, $data );
		}

		if ( $count = db::u( static::TB_META_BASE, [ 'val' => $val ], $data ) ) {
			return $count;
		}

		$data[ 'val' ] = $val;
		return db::i( static::TB_META_BASE, $data );
	}

	/**
	 * Generic meta data
	 */
	public function get_meta( $ref_ids, $meta_keys = false, $p = false ) {
		$cond = [
			'p' => $p ?: $this->_get_p(),
			'ref_id' => ! is_array( $ref_ids ) ? $ref_ids : [ 'in', $ref_ids ],
		];

		// Get single row
		if ( $meta_keys ) {
			$cond[ 'meta_key' ] = ! is_array( $meta_keys ) ? $meta_keys : [ 'in', $meta_keys ];

			// Single result only
			if ( ! is_array( $ref_ids ) && ! is_array( $meta_keys ) ) {
				$row = db::s( static::TB_META_BASE, $cond );
				return $row[ 'val' ];
			}
		}

		$list = db::sa( static::TB_META_BASE, $cond );

		$metas = [];

		// Multi ref_ids and single metakey case
		if ( is_array( $ref_ids ) && $meta_keys && ! is_array( $meta_keys ) ) {
			foreach ( $list as $v ) {
				$metas[ $v[ 'ref_id' ] ] = $v[ 'val' ];
			}
			// Append non-existing records
			foreach ( $ref_ids as $v ) {
				if ( empty( $metas[ $v ] ) ) {
					$metas[ $v ] = false;
				}
			}
			return $metas;
		}

		// single ref_ids and multi keys case
		if ( ! is_array( $ref_ids ) ) {
			foreach ( $list as $v ) {
				$metas[ $v[ 'meta_key' ] ] = $v[ 'val' ];
			}
			// Append non-existing records
			if ( $meta_keys ) {
				foreach ( $meta_keys as $v ) {
					if ( empty( $metas[ $v ] ) ) {
						$metas[ $v ] = false;
					}
				}
			}
			return $metas;
		}

		// Multi ref_ids and multi meta_keys case
		foreach ( $list as $v ) {
			$metas[ $v[ 'ref_id' ] ][ $v[ 'meta_key' ] ] = $v[ 'val' ];
			// Patch empty ids
			foreach ( $ref_ids as $v2 ) {
				if ( $meta_keys ) {
					foreach ( $meta_keys as $v3 ) {
						if ( empty( $metas[ $v2 ][ $v3 ] ) ) {
							$metas[ $v2 ][ $v3 ] = false;
						}
					}
				}
			}
		}

		return $metas;
	}

	/**
	 * Convert an object to array
	 */
	protected function obj2arr( $obj ) {
		return json_decode( json_encode( $obj ), true );
	}

	/**
	 * Load an instance or create it if not existed
	 *
	 * Supported format:
	 * 		`$this->cls('crm\xx')->xxx()`
	 * 		`$this->cls('xx')->xxx()` 		will auto append caller namespace e.g. `crm`
	 * 		`xx::cls()->xxx()`
	 */
	public static function cls( $cls = false ) {
		if ( ! $cls ) {
			$cls = static::class;
		}

		if ( substr( $cls, 0, 1 ) == '\\' ) {
			throw new \Exception( 'wrong cls used: ' . $cls );
		}

		// Append namespace if not set (sibling class caller under same namespace)
		if ( strpos( $cls, '\\' ) === false && strpos( static::class, '\\' ) != false ) {
			$namespace = explode( '\\', static::class );
			$cls = $namespace[ 0 ] . '\\' . $cls;
		}

		if ( ! isset( self::$_instances[ $cls ] ) ) {
			$clsname = '\\' . $cls;
			self::$_instances[ $cls ] = new $clsname();
		}

		return self::$_instances[ $cls ];
	}

	/**
	 * Unset one inited cls
	 */
	protected static function unset_cls() {
		unset( self::$_instances[ static::class ] );
	}

	/**
	 * Only get the list
	 */
	public function get_list() {
		return array_values( $this->_id2list );
	}

	/**
	 * Only get the ids
	 */
	public function get_ids() {
		return array_keys( $this->_id2list );
	}

	/**
	 * Get row by ID
	 */
	public function row_by_id( $id, $col = false ) {
		if ( empty( $this->_id2list[ $id ] ) ) {
			return false;
		}

		if ( $col ) {
			return $this->_id2list[ $id ][ $col ];
		}

		return $this->_id2list[ $id ];
	}

	public function row( $id = '_null', $ignore_office = false, $col = false, $dry_run = false ) {
		if ( ! empty( $this->_id2list ) ) {
			b( 'Do you mean row_by_id()?' );
		}
		$row = $this->_row( $id, $ignore_office, $dry_run );
		if ( $col ) {
			if ( ! empty( $row[ $col ] ) ) {
				return $row[ $col ];
			}

			return null;
		}
		return $row;
	}

	/**
	 * List rows by certain column id
	 */
	public function row_by_colid( $col, $id ) {
		return db::s( static::TB, [ $col => $id ], 'id' );
	}

	/**
	 * List rows by id list
	 */
	public function list_by_ids( $ids ) {
		if ( ! $ids ) {
			return [];
		}

		$list = [];
		if ( ! empty( $this->_id2list ) ) {
			foreach ( $this->_id2list as $v ) {
				if ( in_array( $v[ 'id' ], $ids ) ) {
					$list[ $v[ 'id' ] ] = $v;
				}
			}
		}
		else {
			$rows = db::sa( static::TB, [ 'id' => [ 'in', $ids ] ] );
			foreach ( $rows as $v ) {
				$list[ $v[ 'id' ] ] = $v;
			}
		}

		return $list;
	}

	/**
	 * Delete rows by id list
	 */
	public function del_by_ids( $ids ) {
		if ( ! $ids ) {
			return;
		}

		db::d( static::TB, [ 'id' => [ 'in', $ids ] ] );
	}

	/**
	 * List rows by certain column id
	 */
	public function list_by_colid( $col, $id, $orderby = false ) {
		$list = [];
		if ( ! empty( $this->_id2list ) ) {
			foreach ( $this->_id2list as $v ) {
				if ( $v[ $col ] == $id ) {
					$list[] = $v;
				}
			}
		}
		else {
			if ( $orderby ) {
				$orderby = $orderby . ',id desc';
			}
			else {
				$orderby = 'id desc';
			}
			$list = db::sa( static::TB, [ $col => $id ], $orderby );
		}

		return $list;
	}

	/**
	 * List rows by certain column id list
	 */
	public function list_by_colids( $col, $ids, $single_only = false ) {
		if ( ! $ids ) {
			return [];
		}

		$list = [];
		$rows = db::sa( static::TB, [ $col => [ 'in', $ids ] ], 'id desc' );

		if ( ! $single_only ) {
			return $rows;
		}

		foreach ( $rows as $v ) {
			$list[ $v[ $col ] ] = $v;
		}

		return $list;
	}

	/**
	 * Get the title of one id
	 */
	public function title( $id, $global_k = false ) {
		if ( $global_k ) {
			if ( ! isset( $this->_id2titles_global ) ) {
				$list = db::msa( static::TB, [ 'active' => 1 ], null, false, false, false, 60 );
				$this->_id2titles_global = [];
				foreach ( $list as $v ) {
					$this->_id2titles_global[ $v[ 'id' ] ] = $v[ 'title' ];
				}
			}

			if ( empty( $this->_id2titles_global[ $id ] ) ) {
				return $id;
			}
			return $this->_id2titles_global[ $id ];
		}

		if ( empty( $this->_id2titles[ $id ] ) ) {
			return $id;
		}
		return $this->_id2titles[ $id ];
	}

	/**
	 * Add
	 */
	protected function Insert() {
		$s = [
			'dateline'	=> TS,
		];
		if ( ! defined( 'static::NO_COL_ACTIVE' ) ) {
			$s[ 'active' ] = 1;
		}
		if ( defined( 'static::NEED_K' ) ) {
			$s[ 'k' ] = K;
		}

		$extra_fields_from_args = [];
		if ( func_num_args() > 0 ) {
			$extra_fields_from_args = func_get_arg( 0 );
		}

		if ( ! defined( 'static::NO_TITLE_COL' ) ) {
			$title = ! empty( $extra_fields_from_args[ 'title' ] ) ? $extra_fields_from_args[ 'title' ] : _post( 'title' );
			$title = trim( $title );
			if ( ! $title ) b( 'No title' );

			$check_cond = [];
			if ( ! defined( 'static::NO_COL_ACTIVE' ) ) {
				$check_cond[ 'active' ] = [ '>', -1 ];
			}
			if ( defined( 'static::TITLE_UNIQUE' ) ) {
				foreach ( static::TITLE_UNIQUE as $v ) {
					$check_cond[ $v ] = trim( _post( $v ) );
				}
			}
			else {
				$check_cond[ 'title' ] = $title;
			}
			if ( defined( 'static::NEED_K' ) ) {
				$check_cond[ 'k' ] = K;
			}
			if ( db::s( static::TB, $check_cond ) ) b( 'Title existed' );

			$s[ 'title' ] = $title;
		}

		// Append predefined data from param
		if ( $extra_fields_from_args ) {
			$s = array_merge( $s, $extra_fields_from_args );
		}

		if ( defined( 'static::EXTRA_FIELDS' ) ) {
			foreach ( static::EXTRA_FIELDS as $v ) {
				if ( ! array_key_exists( $v, $extra_fields_from_args ) ) {
					$s[ $v ] = trim( _post( $v ) );
				}

				if ( defined( 'static::FIELDS_REQUIRED' ) && in_array( $v, static::FIELDS_REQUIRED ) && ! $s[ $v ] ) {
					b( 'Empty field: ' . $v );
				}
			}
		}

		$id = db::i( static::TB, self::ll( $s ) );
		define( 'ID2', $id );

		// actlog
		$this->actlog( ID2, 'Created' );

		$this->_save_cols_val( ID2 );

		if ( defined( 'NO_JUMP' ) ) {
			return $s;
		}

		if ( _get_post( 'goon' ) ) j( 1 );

		if ( defined( 'static::MODAL_TPL' ) ) {
			jx();
		}

		j( 1 );
	}

	/**
	 * Save action log
	 */
	public function actlog( $id, $info, $p = false ) {
		$data = [
			'p' => $p ?: $this->_get_p(),
			'row_id' => $id,
			'info' => $info,
			'dateline' => TS,
		];

		if ( defined( 'S' ) ) {
			$data[ 'acp_id' ] = S[ 'acp_id' ];
			$data[ 'acp_truename' ] = S[ 'acp_truename' ];
		}
		elseif( defined( 'CLI' ) ) {
			$data[ 'acp_truename' ] = 'cron';
		}
		elseif ( defined( 'SESSION' ) ) {
			$data[ 'acp_truename' ] = SESSION[ 'user_nickname' ];
		}
		elseif ( defined( 'API' ) ) {
			$data[ 'acp_truename' ] = API;
		}
		db::i( self::TB_ACTLOG, $data );
	}

	/**
	 * Try to get backend current located p
	 */
	protected function _get_p( $ignore_linked_p = false ) {
		if ( defined( 'static::LINKED_P' ) && ! $ignore_linked_p ) {
			$p = static::LINKED_P;
		}
		else {
			if ( defined( 'P' ) ) {
				$p = P;
			}
			else {
				$p = str_replace( '\\', '.', get_class( $this ) );
			}
		}

		return $p;
	}

	/**
	 * Load actlog
	 */
	public function actlog_list( $id, $p = false ) {
		$p = $p ?: defined( 'static::LINKED_P' ) ? static::LINKED_P : P;
		$list = db::sa( [ 'acp_truename as truename, info, dateline', self::TB_ACTLOG ], [ 'p' => $p, 'row_id' => $id ], 'dateline desc, id desc' );
		return $list;
	}

	/**
	 * Notes adding action
	 */
	protected function Ps() {
		$row = $this->_row();

		if ( ACTION_STEP == 'delete' ) {
			$ps_row = db::s( self::TB_PS, _get( 'fid' ) );
			if ( $ps_row[ 'acp_id' ] != S[ 'acp_id' ] ) {
				b( 'No access' );
			}

			db::u( self::TB_PS, [ 'active' => 0 ], $ps_row[ 'id' ] );

			j( 1 );
		}

		if ( SUBMIT ) {
			$ps = _post( 'ps' );

			if ( ! $ps ) {
				b( 'no content' );
			}

			$this->ps_add( ID, $ps );

			if ( _post( 'modal' ) ) {
				jx();
			}

			j( 1 );
		}

		_header( 'Add a note to ID: ' . ID );
		$tpl = new t( 'ps', _SYS );
		$tpl->assign( [
			'row' => s::html( $row ),
			'ps_list' => $this->ps_list( ID ),
		] );
		$tpl->output();
		_footer();
	}

	/**
	 * Save Note
	 */
	public function ps_add( $id, $info ) {
		$data = [
			'p' => $this->_get_p(),
			'acp_id' => S[ 'acp_id' ],
			'acp_truename' => S[ 'acp_truename' ],
			'ref_id' => $id,
			'info' => $info,
			'dateline' => TS,
			'active' => 1,
		];
		db::i( self::TB_PS, $data );

		// Maybe send email
		$this->_maybe_send_assigner_email( $id, $info );
	}

	/**
	 * Load ps
	 */
	public function ps_list( $id, $p = false ) {
		$p = $p ?: $this->_get_p();
		$list = db::sa( [ 'acp_truename as truename, acp_id, ref_id, id, info, dateline', self::TB_PS ], [ 'p' => $p, 'active' => 1, 'ref_id' => $id ], 'dateline desc, id desc' );
		$list = s::html( $list );
		foreach ( $list as $k => $v ) {
			$list[ $k ][ '_can_del' ] = S[ 'acp_id' ] == $v[ 'acp_id' ];
			$list[ $k ][ 'info' ] = s::auto_link( $v[ 'info' ] );
		}
		return $list;
	}

	/**
	 * Edit
	 */
	protected function Update() {
		$id = false;

		$extra_fields_from_args = [];
		if ( func_num_args() > 0 ) {
			$extra_fields_from_args = func_get_arg( 0 );
			if ( func_num_args() > 1 ) {
				$id = func_get_arg( 1 );
			}
		}

		$row = $this->_row( $id ?: '_null' );

		$s = [];
		if ( ! defined( 'static::NO_TITLE_COL' ) ) {
			$title = ! empty( $extra_fields_from_args[ 'title' ] ) ? $extra_fields_from_args[ 'title' ] : _post( 'title' );
			$title = trim( $title );
			if ( ! $title ) b( 'No title' );

			$check_cond = [ [ '!=', $row[ 'id' ] ] ];
			if ( ! defined( 'static::NO_COL_ACTIVE' ) ) {
				$check_cond[ 'active' ] = [ '>', -1 ];
			}
			if ( defined( 'static::TITLE_UNIQUE' ) ) {
				foreach ( static::TITLE_UNIQUE as $v ) {
					$check_cond[ $v ] = trim( _post( $v ) );
				}
			}
			else {
				$check_cond[ 'title' ] = $title;
			}
			if ( defined( 'static::NEED_K' ) ) {
				$check_cond[ 'k' ] = K;
			}
			if ( db::s( static::TB, $check_cond ) ) b( 'Title existed' );

			$s[ 'title' ] = $title;
		}

		// Append predefined data from param
		if ( $extra_fields_from_args ) {
			$s = array_merge( $s, $extra_fields_from_args );
		}

		if ( defined( 'static::EXTRA_FIELDS' ) ) {
			foreach ( static::EXTRA_FIELDS as $v ) {
				if ( ! array_key_exists( $v, $extra_fields_from_args ) ) {
					$s[ $v ] = trim( _post( $v ) );
				}
			}
		}
		db::u( static::TB, self::ll( $s ), $row[ 'id' ] );

		// actlog
		$this->actlog( $row[ 'id' ], 'Modified' );

		$this->_save_cols_val( $row[ 'id' ] );

		if ( defined( 'NO_JUMP' ) ) {
			return array_merge( $row, $s );
		}

		if ( defined( 'static::MODAL_TPL' ) ) {
			jx();
		}

		j( 1 );
	}

	/**
	 * Forbid global access
	 */
	protected function forbid_global() {
		if ( GLOBAL_K ) {
			b( 'no access in global office mode' );
		}
	}

	/**
	 * Append global office filter
	 */
	protected function global_k( $alias = false ) {
		if ( ! GLOBAL_K ) {
			return;
		}

		$list = [];
		foreach ( self::cls( 'sys\koffice' )->get_list() as $v ) {
			$list[ $v[ 'title' ] ] = $v[ 'id' ];
		}

		$this->_list_filter( 93, $alias, 'k', $list, 'Office' );
	}

	/**
	 * Filter for list
	 *
	 * @param  $haystack Special value `_zero` for value=0 case, `_date_range` for data range
	 */
	protected function _list_filter( $o, $tb_alias = false, $col = false, $haystack = false, $tag_title = false, $cond_format = false ) {
		// Locked or not
		if ( $o == '95' ) {
			! defined( 'static::NO_COL_ACTIVE' ) || b( 'no trash filter enabled' );

			$o95 = _get( 's95' ) ? _get( 'o95' ) : _cookie( 'o95' );
			$o95 %= 3;

			cookie( 'o95', $o95, 8640000 );
			if( $o95 == 0 ) {
				$this->cond[ $tb_alias . 'active' ] = 1;
			}
			if( $o95 == 1 ) {
				$this->cond[ $tb_alias . 'active' ] = 0;
			}
			if( $o95 == 2 ) {
				$this->cond[ $tb_alias . 'active' ] = [ '>=', 0 ];
			}
			if ( $o95 != 0 ) {
				$this->curr_filters[] = [
					'o'	=> '95',
					'title_prefix'	=> 'Status',
					'title'	=> $o95 == 1 ? 'Trash' : 'All',
					'v'		=> $o95,
				];
			}
			$this->filter_list[ 'o95' ] = $o95;
			return;
		}

		// Search
		if ( $o === '00' ) {
			$o00 = _get( 's00' ) ? _get( 'o00' ) : _cookie( 'o00' );
			$o00 = trim( $o00 );
			if ( _get( 's00' ) ) {
				cookie( 'o00', $o00, 8640000 );
			}
			$this->filter_list[ 'o00' ] = $o00;
			if ( $o00 ) {
				$tmp = [];
				if ( strpos( $o00, 'id:' ) === 0 ) {
					$tmp[ $tb_alias . 'id' ] = substr( $o00, 3 );
				}
				else {
					if ( defined( 'static::SEARCH_COLS' ) ) {
						foreach ( static::SEARCH_COLS as $v ) {
							$this_col = strpos( $v, '.' ) === false ? $tb_alias . $v : $v;
							$tmp[ $this_col ] = [ 'like', '%' . $o00 . '%' ];
						}
					}
					else {
						$tmp = [
							$tb_alias . 'title' => [ 'like', '%' . $o00 . '%' ],
						];
					}
					if ( is_numeric( $o00 ) ) $tmp[ $tb_alias . 'id' ] = $o00;
				}

				$cond_k = $this->_pad( '_or' );
				$this->cond[ $cond_k ] = $tmp;
				$this->curr_filters[] = [
					'o'	=> '00',
					'title_prefix'	=> 'Search',
					'title'	=> $o00,
					'v'	=> urlencode( $o00 ),
				];
				return $cond_k;
			}
			return false;
		}

		// Dateline Start to End filter
		if ( $o == 94 ) {
			$haystack = '_date_range';
			$tag_title = 'Date Created';
			$col = 'dateline';
		}
		if ( $o == '96' ) {
			$haystack = '_date_range';
			$tag_title = 'Date Updated';
			$col = 'lastdateline';
		}

		$cond_k = $this->_pad( $tb_alias . $col );
		// Start to End range
		if ( $haystack == '_date_range' ) {
			$start = _get( "s$o" ) ? _get( 'start' ) : _cookie( "o{$o}start" );
			$end = _get( "s$o" ) ? _get( 'end' ) : _cookie( "o{$o}end" );
			$this->filter_list[ "o{$o}start" ] = $start;
			$this->filter_list[ "o{$o}end" ] = $end;
			cookie( "o{$o}start", $start, 8640000 );
			cookie( "o{$o}end", $end, 8640000 );
			if ( $start && $end ) {
				$this->cond[ $cond_k ] = [
					'between',
					in_array( $o, [ 94, 96 ] ) ? strtotime( $start ) : $start,
					in_array( $o, [ 94, 96 ] ) ? strtotime( $end . ' 23:59:59' ) : $end,
				];
				$this->curr_filters[] = [
					'o'	=> $o,
					'title_prefix'	=> $tag_title,
					'title'	=> $start . ' to ' . $end,
					'v'		=> $start,
				];
			}
			return $cond_k;
		}

		// 93 is for global K filter

		// Other filters
		$o_v = _cookie( "o$o" );
		if ( ! is_array( $o_v ) ) {
			$o_v = $o_v ? [ $o_v ] : [];
		}
		$ori_v = $o_v;

		if ( _get( "s$o" ) ) {
			if ( $del = _get( 'del' ) ) {
				unset( $o_v[ array_search( $del, $o_v ) ] );
			}

			$set = _get( "o$o" );
			if ( $set && ! in_array( $set, $o_v ) ) {
				$o_v[] = $set;
			}
		}

		if ( $o_v ) {
			$o_v = array_unique( $o_v );
			$o_v = array_filter( $o_v );
			$cond_v = [];
			foreach ( $o_v as $k => $v ) {
				if ( $haystack && ! in_array( $v, $haystack ) ) {
					unset( $o_v[ $k ] );
				}
				else {
					$cond_v[] = $v !== '_zero' ? $v : 0; // Special value `_zero` for 0 case
				}
			}
			if ( $cond_v ) {
				if ( ! $cond_format ) {
					$this->cond[ $cond_k ] = [ 'in', $cond_v ];
				}
				else if ( $cond_format == 'not in' ) {
					$this->cond[ $cond_k ] = [ 'not in', $cond_v ];
				}
				else if ( $cond_format == '>=' ) {
					$this->cond[ $cond_k ] = [ '>=', $cond_v[0] ];
				}
				else if ( $cond_format == '<=' ) {
					$this->cond[ $cond_k ] = [ '<=', $cond_v[0] ];
				}

				else if ( $cond_format == 'bit' || $cond_format == 'bit nor' ) {
					$cond_k = $this->_pad( 'and' );
					$this->cond[ $cond_k ] = [];
					foreach ( $cond_v as $v ) {
						$this->cond[ $cond_k ][ $this->_pad( $tb_alias . $col ) ] = [ $cond_format, $v ];
					}
				}
				else {
					$cond_k = $this->_pad( 'or' );
					$this->cond[ $cond_k ] = [];
					foreach ( $cond_v as $v ) {
						$this->cond[ $cond_k ][ $this->_pad( $tb_alias . $col ) ] = [ 'like', str_replace( '%d', $v, $cond_format ) ];
					}
				}
			}
		}
		if ( $ori_v != $o_v ) {
			cookie( "o$o", $o_v, 8640000 );
		}

		$this->filter_list[ "o$o" ] = [];
		if ( $haystack ) {
			foreach ( $haystack as $k => $v ) {
				$title = is_int( $k ) ? $v : $k;
				$this->filter_list[ "o$o" ][] = [
					'o' => $o,
					'title'	=> $title,
					'v'	=> urlencode( $v ),
					'curr'	=> in_array( $v, $o_v ),
				];
			}
		}
		else {
			$this->filter_list[ "o$o" ] = $o_v ? $o_v[ 0 ] : '';
		}

		// Current existing filters display
		foreach ( $o_v as $v ) {
			// Check if has non-int title in haystack or not
			if ( $haystack ) {
				$title = array_search( $v, $haystack );
				if ( is_int( $title ) ) {
					$title = $v;
				}
			}
			else {
				$title = $v;
			}
			$this->curr_filters[] = [
				'o'	=> $o,
				'title_prefix'	=> $tag_title,
				'title'	=> $title,
				'v'	=> urlencode( $v ),
			];
		}

		$this->o_v = $o_v;

		return $cond_k;
	}

	/**
	 * Order by for list
	 */
	protected function _list_orderby( $cols = false ) {
		if ( ! $cols ) {
			$cols = [ 'id', 'lastdateline', 'dateline' ];
		}

		if ( defined( 'static::LIST_ORDERBY_COLS' ) ) {
			$cols = array_merge( $cols, static::LIST_ORDERBY_COLS );
		}

		$o01 = _get( 's01' ) ? _get( 'o01' ) : _cookie( 'o01' );
		if ( ! in_array( $o01, $cols ) ) {
			$o01 = $cols[ 0 ];
		}
		$o01order = _get( 's01' ) ? _get( 'o01order' ) : _cookie( 'o01order' );
		if ( $o01order != 'asc' ) {
			$o01order = 'desc';
		}
		cookie( 'o01', $o01, 8640000 );
		cookie( 'o01order', $o01order, 8640000 );

		$orderby = "$o01 $o01order";
		if ( $o01 != $cols[ 0 ] && strpos( $cols[ 0 ], 'id' ) !== false ) { // Append default `id desc` orderby
			$orderby .= ",$cols[0] $o01order";
		}

		return [ $orderby, "$o01.$o01order", $o01 ];
	}

	/**
	 * List
	 *
	 * $this->col_filter_no_form_wrapper 	to return coldiy filters only
	 */
	protected function List() {
		if ( ! isset( $this->_tb ) ) {
			$this->_tb = static::TB . ' a';
		}
		if ( ! isset( $this->_cols_to_select ) ) {
			$this->_cols_to_select = 'a.*';
		}

		if ( defined( 'static::NEED_K' ) ) {
			if ( ! GLOBAL_K ) {
				$this->cond[ isset( $this->_tb ) ? 'a.k' : 'k' ] = K; // if assigned $this->_tb, means it has multi tables joined
			}
		}

		// filter locked
		defined( 'static::NO_COL_ACTIVE' ) || $this->_list_filter( 95, 'a.' );

		// Search
		$this->_list_filter( '00', 'a.' );

		// Start to End dateline
		$this->_list_filter( 94, 'a.' );

		if ( method_exists( $this, '_additional_filter' ) ) {
			$this->_additional_filter();
		}

		// Cols diy filters
		$this->_cols_filter();

		// Order by
		list( $orderby, $this->o01link ) = $this->_list_orderby();

		if ( defined( 'LIST_ALL' ) ) {
			return db::sa( [ $this->_cols_to_select, $this->_tb ], $this->cond, $orderby );
		}

		// List all
		$this->total = db::c( $this->_tb, $this->cond );
		$this->pagelink = page::gen( $this->total );

		$list = db::sa( [ $this->_cols_to_select, $this->_tb ], $this->cond, $orderby, $this->pagelink[ 'limit' ] );

		// Readable convert
		if ( method_exists( $this, '_readable' ) ) {
			$list = array_map( [ $this, '_readable' ], $list );
		}
		elseif ( method_exists( $this, 'readable' ) ) {
			$list = array_map( [ $this, 'readable' ], $list );
		}
		else {
			$list = s::html( $list );
		}

		// Group readable convert
		if ( method_exists( $this, '_readable_list' ) ) {
			$list = $this->_readable_list( $list );
		}

		$data = $this->_assemble_list_view( [ 'list' => $list ], isset( $this->col_filter_no_form_wrapper ) );
		$this->_tpl( $data );
	}

	/**
	 * Finalize list data
	 */
	protected function _assemble_list_view( $data, $filters_only = false ) {
		$this->_cols();
		$data[ 'list' ] = $this->append_cols( $data[ 'list' ] );
		$data[ '_cols_filters' ] = $this->_cols_filter_tpl( $filters_only );

		$data = array_merge( $data, $this->filter_list );
		$data[ 'o01link' ] = $this->o01link;
		$data[ 'total' ] = $this->total;
		$data[ 'curr_filters' ] = $this->_curr_filters();
		$data[ 'cols_list' ] = $this->cols_list;
		$data[ 'pagelink' ] = $this->pagelink[ 'output' ];
		return $data;
	}

	/**
	 * Give a unique space pad to a sql key
	 */
	protected function _pad( $k ) {
		return $k . str_repeat( ' ', $this->i++ );
	}

	/**
	 * Generate existing filter list
	 */
	protected function _curr_filters() {
		$t = new t( 'filter_list', _SYS );
		$t->assign( 'list', $this->curr_filters );
		return $t->output( true );
	}

	/**
	 * Convert bits to titles
	 */
	protected function _bit2title( $bits, $vals ) {
		$titles = [];
		foreach ( $vals as $k => $v ) {
			if ( $k & $bits ) {
				$titles[] = [
					'val' 	=> $k,
					'title' => $v,
					'title_colored' => s::color( $v ),
				];
			}
		}
		return $titles;
	}

	/**
	 * Add
	 */
	protected function Add() {
		$data = [
			'goon'	=> _get_post( 'goon' ),
			'cols_list' => $this->gen_cols_tpl(),
		];

		$this->_tpl( $data );
	}

	/**
	 * Edit
	 */
	protected function Edit() {
		$row = $this->_row();

		$row[ 'actlog' ] = $this->actlog_list( ID );

		$data = [
			'row'	=> s::html( $row, true ),
			'cols_list' => $this->gen_cols_tpl( ID ),
		];

		$this->_tpl( $data );
	}

	/**
	 * View detail
	 */
	protected function Detail() {
		$row = $this->_row();
		$row = s::html( $row );
		$row = $this->append_cols_detail( $row );

		$data = [
			'row'	=> $row,
			'actlog' => $this->actlog_list( ID ),
		];

		$this->_tpl( $data );
	}

	/**
	 * Lock/unlock
	 */
	protected function Lock() {
		! defined( 'static::NO_COL_ACTIVE' ) || b( 'No trash enabled' );

		$row = $this->_row();

		$active = ( $row[ 'active' ] + 1 ) % 2;

		$s = [ 'active' => $active ];

		db::u( static::TB, self::ll( $s ), ID );

		$this->actlog( ID, $active ? 'Restored' : 'Deleted' );

		if ( defined( 'NO_JUMP' ) ) {
			return array_merge( $row, $s );
		}

		j( 1 );
	}

	/**
	 * Read one row
	 *
	 * @param 2nd $ignore_office=false
	 */
	protected function _row( $id = '_null', $ignore_office = false, $dry_run = false, $col = false ) {
		if ( $id !== '_null' ) {
			if ( ! $id ) {
				b( 'No ID: ' . static::TB );
			}
		}
		else {
			$id = ID;
		}

		$cond = [
			'id' => $id,
		];

		if ( defined( 'static::NEED_K' ) && ! $ignore_office && ! GLOBAL_K ) {
			$cond[ 'k' ] = K;
		}

		$row = db::s( static::TB, $cond );
		if ( ! $dry_run ) {
			if ( ! $row ) b( 'No such data under this office. [ID] ' . $id . ' [code] ' . static::class );
		}

		if ( $col ) {
			return $row[ $col ];
		}

		return $row;
	}

	/**
	 * Show tpl
	 */
	protected function _tpl( $data, $title = false, $tpl = false, $sys_tpl = false ) {
		$www = false;
		if ( ! $tpl && defined( 'TPL' ) && ! file_exists( ROOT . 'tpl/' . TPL . '.html' ) && file_exists( _SYS . 'tpl/' . TPL . '.html' ) ) {
			$www = _SYS;
		}
		if ( $sys_tpl ) {
			$www = _SYS;
		}
		_header( $title );
		$tpl = new t( $tpl, $www );
		$tpl->assign( $data );

		if ( ! empty( $this->tpl_data ) ) {
			$tpl->assign( $this->tpl_data );
		}

		$tpl->output();
		_footer();
	}

	/**
	 * Log last operation
	 */
	public static function ll( $arr = false ) {
		if ( ! defined( 'S' ) || empty( S[ 'acp_id' ] ) ) {
			$_last = [ 'lastdateline' => TS ];
		}
		else {
			$_last = [
				'lastacp_id'	=> S['acp_id'],
				'lastacp_truename'	=> S['acp_truename'],
				'lastdateline'	=> TS,
			];
		}

		if ( ! $arr ) return $_last;
		return array_merge( $arr, $_last );
	}

	/**
	 * Default handler
	 */
	public function handler() {
		$action = func_num_args() > 0 ? func_get_arg( 0 ) : ACTION;
		if ( ! $action ) {
			$action = 'list';
		}

		if ( preg_match( '/\W/', $action ) ) {
			b( 'Action error: ' . $action );
		}

		// Check if allow this action
		if ( defined( 'static::ACTIONS' ) && ! in_array( $action, static::ACTIONS ) ) {
			// debug( 'Forbidden action ' . $action . ' [p] ' . _get_post( 'p' ), static::ACTIONS );
			b( 'Forbidden action: ' . $action );
		}

		// Capitalized 1st char can be used public directly
		$pub_action = ucfirst( $action );
		$cls_funcs = get_class_methods( $this );
		if ( in_array( $pub_action, $cls_funcs ) ) {
			$this->$pub_action();
			return;
		}

		b( 'No action: ' . $action );
	}

	/**
	 * Load files
	 */
	protected function _file_list( $id, $type = false ) {
		$cond = [
			'p' => $this->_get_p(),
			'ref_id' => $id,
			'active' => 1,
			'type' => $type ?: 0,
		];
		$file_list = db::sa( static::TB_FILES, $cond, 'id desc' );
		$file_list = s::html( $file_list );
		foreach ( $file_list as $k => $v ) {
			$file_list[ $k ][ 'size' ] = f::realSize( $v[ 'size' ] );
			$file_list[ $k ][ 'can_del' ] = $v[ 'acp_id' ] == S[ 'acp_id' ] || admin::priv( 's3del' );
			$file_list[ $k ][ '_s3_path' ] = s3::url( static::S3_FILE . '/' . $v[ 'path' ] );
		}

		return $file_list;
	}

	/**
	 * s3upload
	 */
	protected function File_upload() {
		if ( ! defined( 'static::S3_FILE' ) ) {
			b( 'No file s3 path' );
		}

		// File delete
		if ( ACTION_STEP == 's3del' ) {
			$file = db::s( static::TB_FILES, [ ID, 'p' => $this->_get_p(), 'active' => 1 ] );
			if ( ! $file ) {
				b( 'No file' );
			}

			if ( $file[ 'acp_id' ] != S[ 'acp_id' ] && ! admin::priv( 's3del' ) ) {
				b( 'Access denied' );
			}

			db::u( static::TB_FILES, [ 'active' => -99 ], ID );
			// s3::del( static::S3_FILE . '/' . $file[ 'path' ] );

			define( 'ID2', $file[ 'ref_id' ] );

			// log
			$log = 'Deleted [' . s::html( $file[ 'filename' ] ) . ']';
			$this->actlog( $file[ 'ref_id' ], $log );
			db::u( static::TB, $this->ll(), ID );

			j( 1 );
		}

		// File upload
		$row = $this->_row();

		if ( SUBMIT ) {
			if ( empty( $_FILES[ 'attach' ][ 'name' ] ) ) b( 'No File' );
			$file = $_FILES[ 'attach' ];
			$size = filesize( $file[ 'tmp_name' ] );
			if ( $size > 50000000 ) b( 'Filesize max 50M' );
			$ext = strtolower( pathinfo( $file[ 'name' ], PATHINFO_EXTENSION ) );

			$s = [
				'p'			=> $this->_get_p(),
				'ref_id'	=> ID,
				'filename'	=> $file[ 'name' ],
				'size'		=> $size,
				'postfix'	=> $ext,
				'icon'		=> router::icon( $ext ),
				'acp_id'	=> S[ 'acp_id' ],
				'acp_truename'	=> S[ 'acp_truename' ],
				'dateline'	=> TS,
				'active'	=> 1,
				'type'		=> _post( 'type' ),
				'description'	=> _post( 'description' ),
			];
			$fid = db::i( static::TB_FILES, $s );

			$safe_filename = $fid . '-' . preg_replace( '/[^\w\d\-\(\)_.]/isU', '_', pathinfo( $file[ 'name' ], PATHINFO_FILENAME ) );
			$path = date( "Ym/d", TS ) . "/$safe_filename.$ext";

			$tmp_file = "/tmp/$fid.$ext";
			move_uploaded_file( $file[ 'tmp_name' ], $tmp_file );
			s3::upload( static::S3_FILE . '/' . $path, $tmp_file );
			unlink( $tmp_file );

			$s = [
				'path'	=> $path,
			];
			db::u( static::TB_FILES, $s, $fid );

			// log
			$log = 'Uploaded [' . s::html( $file[ 'name' ] ) . ']';
			$this->actlog( ID, $log );
			db::u( static::TB, $this->ll(), ID );

			jx();
		}

		$type = _get( 'type' );
		$this->_tpl( [ 'type' => $type, 'type_title' => false ], false, 'file_upload', true );
	}

}