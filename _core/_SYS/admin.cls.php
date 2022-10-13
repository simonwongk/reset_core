<?php
/**
 *	Admin management
 */
/**

CREATE TABLE `admin` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL DEFAULT '',
  `truename` varchar(255) NOT NULL,
  `idname` varchar(255) NOT NULL DEFAULT '',
  `pswd` varchar(255) NOT NULL,
  `privilege` mediumtext NOT NULL,
  `priv_tags` mediumtext NOT NULL,
  `last_act_dateline` int(11) NOT NULL,
  `lastip` varchar(255) NOT NULL,
  `acp_id` int(11) NOT NULL,
  `acp_truename` varchar(255) NOT NULL,
  `accessip` varchar(255) NOT NULL,
  `error` tinyint(4) NOT NULL COMMENT 'Login error count',
  `root` tinyint(4) NOT NULL,
  `history` varchar(255) NOT NULL COMMENT 'pswd history',
  `active` tinyint(4) NOT NULL COMMENT '-99=del  -1=deactivated 0=paused 1=normal',
  `dateline` int(11) NOT NULL,
  `mobile` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `admin_tags` varchar(255) NOT NULL DEFAULT '' COMMENT '群組tag ,分割',
  `ps` varchar(255) NOT NULL,
  `lastacp_id` int(11) NOT NULL,
  `lastacp_truename` varchar(255) NOT NULL,
  `lastdateline` int(11) NOT NULL,
  `shortcuts` text NOT NULL,
  `cols_defines` text NOT NULL COMMENT 'The menus that can define cols as admin',
  `cols_disabled` text NOT NULL,
  `avatar` text DEFAULT NULL,
  `secret_2fa` varchar(100) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `active` (`active`),
  KEY `dateline` (`dateline`),
  KEY `name` (`name`),
  KEY `acpTag_id` (`admin_tags`),
  KEY `mobile` (`mobile`),
  KEY `email` (`email`),
  KEY `truename` (`truename`),
  KEY `pubname` (`idname`),
  KEY `active_2` (`active`,`priv_tags`(300))
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8 COMMENT='admin panel accounts';

 */
defined( 'IN' ) || exit;

class admin extends instance {
	const TB = 'acp.admin';
	const TB_SESSION = 'acp.admin_session';
	const SEARCH_COLS = [ 'name', 'truename', 'idname' ];

	// Special permission
	const PRIV_TAGS = [
		'sys.acp_edit'		=> 'Admin Edit',
	];

	const COLS = [
		'i'			=> 1,
		'id'		=> 1,
		'truename'	=> 1,
		'menu'		=> 1,
		'privilege'	=> 1,
		'group'		=> 1,
		'activity'	=> 1,
		'note'		=> 1,
		'created'	=> 1,
		'updated'	=> 1,
	];

	protected $_id2list = [];
	protected $_id2titles = [];
	protected $_tag2admin = [];

	protected static $i = 0;

	protected function __construct() {
		$list = db::msa( static::TB, [ 'active' => 1 ], 'truename', false, false, false, 60 );
		foreach ( $list as $k => $v ) {
			$this->_id2titles[ $v[ 'id' ] ] = $v[ 'truename' ];
			$this->_id2list[ $v[ 'id' ] ] = $v;
		}
	}

	/**
	 * Batch operation
	 */
	protected function Batch() {
		if ( SUBMIT ) {
			$ids = _post( 'ids' );
			$privilege = _post( 'privilege' );

			// Validate
			$list = $this->list_by_ids( $ids );
			$ids = [];
			foreach ( $list as $v ) {
				// $this->check_pass( $v );
				$ids[] = $v[ 'id' ];
			}

			$data = $actlog = [];
			// Privilege handler
			$privArr = [];
			$log = [];
			foreach( ADMIN_MENU_P0 as $p0 => $p0title ) {
				$p1arr = ! empty( ADMIN_MENUS[ $p0 ] ) ? ADMIN_MENUS[ $p0 ] : [];
				if ( empty( $privilege[ $p0 ] ) ) continue;
				$privArr[] = $p0;
				foreach( $p1arr as $p1 => $title ) {
					if ( empty( $privilege[ $p0 ][ $p1 ] ) ) continue;
					$privArr[] = "$p0.$p1";
					$log[] = $p0title . ' - ' . $title;
				}
			}
			$actlog[] = 'Batch added priv: ' . implode( ', ', $log ); // not in use

			if ( ! $data ) {
				// b( 'No operation chosen' );
			}

			// Batch update
			foreach ( $list as $row ) {
				$data = [];
				$data[ 'privilege' ] = arr2str( array_unique( array_merge( str2arr( $row[ 'privilege' ] ), $privArr ) ) );
				db::u( static::TB, self::ll( $data ), $row[ 'id' ] );
				$this->actlog( $row[ 'id' ], implode( ', ', $actlog ) );
			}

			jx();
		}

		// Show batch GUI
		$ids = _get( 'ids' );
		$id_list = [];
		foreach ( explode( ',', $ids) as $v ) {
			if ( ! $v ) {
				continue;
			}
			$id_list[] = [ 'v' => $v ];
		}

		if ( ! $id_list ) {
			b( 'No ID selected' );
		}

		$priv_lists = $this->_build_priv_list( false );

		$this->_tpl( [
			'ids' => $ids,
			'id_list' => $id_list,
			'priv_lists' => $priv_lists,
		] );
	}

	/**
	 * Disable 2fa login
	 */
	protected function Disable_2fa() {
		$row = $this->_row();

		db::u( static::TB, [ 'secret_2fa' => '' ], ID );

		$this->actlog( ID, 'Disabled 2FA' );

		j( 1 );
	}

	/**
	 * Load admins choose dropdown input select field
	 */
	public function load_select_tpl( $field, $tag_id = false, $curr = false, $single_only = false, $is_global = false, $whitelist_ids = false ) {
		$list = [];
		if ( $tag_id ) {
			$list = $this->rows_by_tag( $tag_id, $is_global );
		}
		else {
			if ( $is_global ) {
				$list = $this->_id2list;
			}
			else {
				foreach ( $this->_id2list as $v ) {
					$list[] = $v;
				}
			}
		}

		$list2 = [];
		foreach ( $list as $v ) {
			if ( $whitelist_ids && ! in_array( $v[ 'id' ], $whitelist_ids ) ) {
				continue;
			}
			$list2[] = $v;
		}

		$admins = array_map( function( $v ) { return [ 'id' => $v[ 'id' ], 'truename' => $v[ 'truename' ] ]; }, $list2 );
		if ( ! $curr ) {
			$curr = [];
		}

		$tpl = new t( 'crm/component.admin' );
		$tpl->assign( [
			'admins' => json_encode( $admins ),
			'curr'	=> json_encode( $curr ),
			'field' => $field,
			'div_i' => self::$i++,
			'multi' => $single_only ? 'false' : 'true' ,
		] );

		! defined( 'REACT_NEEDED' ) && define( 'REACT_NEEDED', true );
		return $tpl->output( true );
	}

	/**
	 *	Admin special privilege check
	 *
	 */
	public static function priv( $tag, $thisp0 = false ) {
		if ( empty( P_ARR[ 0 ] ) && ! $thisp0 ) return false;
		if ( ! $thisp0 ) $thisp0 = P_ARR[ 0 ];

		if ( ! in_array( "$thisp0.$tag", S[ 'priv_tags' ] ) ) return false;
		return true;
	}

	/**
	 * Return all admins per tag(s)
	 */
	public function rows_by_tag( $tag_ids ) {
		if ( ! $this->_tag2admin ) {
			$this->_build_tag2admin();
		}

		if ( ! is_array( $tag_ids ) ) {
			return $this->_tag2admin[ $tag_ids ];
		}

		$list = [];
		foreach ( $tag_ids as $tag_id ) {
			if ( ! empty( $this->_tag2admin[ $tag_id ] ) ) foreach ( $this->_tag2admin[ $tag_id ] as $k2 => $v2 ) {
				$list[ $k2 ] = $v2;
			}
		}

		return $list;
	}

	/**
	 * Build the tag groups
	 */
	private function _build_tag2admin() {
		foreach ( $this->_id2list as $v ) {
			foreach ( str2arr( $v[ 'admin_tags' ] ) as $v2 ) {
				$this->_tag2admin[ $v2 ][ $v[ 'id' ] ] = $v;
			}
		}
	}

	/**
	 * Add one admin
	 */
	protected function Add() {
		$priv_lists = $this->_build_priv_list( _cookie( 'add.privilege' ) );

		$priv_tags = [];
		foreach( static::PRIV_TAGS as $key => $val ) {
			$priv_tags[] = [
				'tag'	=> $key,
				'tagshort'	=> str_replace( '.', '', $key ),
				'title'	=> ADMIN_MENU_P0[ substr( $key, 0, strpos( $key, '.' ) ) ] . "-$val",
			];
		}

		$admin_tags = $this->build_admin_tags( _cookie( 'add.admin_tags' ) );

		// Build disabled cols for certain menus
		$cols_defines = $this->_build_cols_diy_list( _cookie( 'add.cols_disabled' ) );

		$this->_tpl( [
			'priv_lists'  => $priv_lists,
			'priv_tags'  => $priv_tags,
			'admin_tags'  => $admin_tags,
			'cols_defines'  => $cols_defines,
			'goon'	=> _get_post( 'goon' ),
		] );
	}

	/**
	 * Build disabled cols for certain menus
	 */
	protected function _build_cols_diy_list( $curr_disabled, $curr_can_define = false ) {
		$curr_disabled = str2arr( $curr_disabled );
		$curr_can_define = str2arr( $curr_can_define );

		$list = [];
		$all_cols_defined = $this->_load_all_cols_defined();
		foreach( ADMIN_MENU_P0 as $p0 => $p1arr ) {
			if( empty( ADMIN_MENUS[ $p0 ] ) ) continue;

			foreach( ADMIN_MENUS[ $p0 ] as $p1 => $title ) {
				if ( ! defined( "\\{$p0}\\{$p1}::COLS" ) ) {
					continue;
				}

				$p = $p0 . '.' . $p1;

				$list_col = [];
				foreach( constant( "\\{$p0}\\{$p1}::COLS" ) as $col => $tmp ) {
					$list_col[] = [
						'title' => ucwords( $col ),
						'menu' => $p,
						'col' => $col,
						'curr' => ! empty( $curr_disabled[ $p ] ) && in_array( $col, $curr_disabled[ $p ] ),
					];
				}
				// Append customized col list
				// Also parent linked_p if existed
				$p_for_cols_defined = [ $p ];
				if ( defined( "\\{$p0}\\{$p1}::LINKED_P" ) ) {
					$p_for_cols_defined[] = constant( "\\{$p0}\\{$p1}::LINKED_P" );
				}
				foreach ( $p_for_cols_defined as $curr_p ) {
					if ( ! empty( $all_cols_defined[ $curr_p ] ) ) {
						foreach ( $all_cols_defined[ $curr_p ] as $col => $col_info ) {
							$list_col[] = [
								'title' => $col_info[ 'title' ],
								'menu' => $p,
								'col' => $col,
								'curr' => ! empty( $curr_disabled[ $p ] ) && in_array( $col, $curr_disabled[ $p ] ),
							];
						}
					}
				}

				$list[] = [
					'title' => ADMIN_MENU_P0[ $p0 ] . ' - ' . $title,
					'menu'	=> $p,
					'curr_can_define' => in_array( $p, $curr_can_define ),
					'list_col' => $list_col,
				];
			}
		}

		return $list;
	}

	/**
	 * Load all defined cols group by menu
	 */
	private function _load_all_cols_defined() {
		$list = db::sa( self::TB_MENU_COL, [ 'active' => 1 ], 'priority, id' );
		foreach ( $list as $v ) {
			$list[ $v[ 'p' ] ][ $v[ 'id' ] ] = $v;
		}
		return $list;
	}

	/**
	 * Build the privilege list
	 */
	protected function _build_priv_list( $curr ) {
		$curr = str2arr( $curr );

		$priv_lists = [];
		foreach( ADMIN_MENU_P0 as $p0 => $p1arr ) {
			$p1arr = ! empty( ADMIN_MENUS[ $p0 ] ) ? ADMIN_MENUS[ $p0 ] : [];
			$p1privArr = false;
			$p0checked = in_array( $p0, $curr );
			foreach( $p1arr as $p1 => $title ) {
				$p1checked = in_array( "$p0.$p1", $curr );
				$p0checked = $p0checked || $p1checked;
				$p1privArr[] = [
					'title'	=> s::color( $title ),
					'p0'	=> $p0,
					'p1'	=> $p1,
					'curr'	=> $p1checked,
				];
			}
			$priv_lists[] = [
				'title'	=> s::color( ADMIN_MENU_P0[ $p0 ] ),
				'p0'	=> $p0,
				'priv_lists'	=> $p1privArr,
				'curr'	=> $p0checked,
			];
		}

		return $priv_lists;
	}

	/**
	 * Build admin groups/tags
	 */
	public function build_admin_tags( $curr = false ) {
		$curr = str2arr( $curr );

		$admin_tags = $this->cls( 'admin_tag' )->get_list();
		foreach( $admin_tags as $k => $v ) {
			$admin_tags[ $k ][ 'title' ] = s::color( $v[ 'title' ] );
			$admin_tags[ $k ][ 'curr' ] = in_array( $v[ 'id' ], $curr );
		}

		return $admin_tags;
	}

	/**
	 * Edit one admin
	 */
	protected function Edit() {
		$row = $this->_row();
		if ( $row[ 'root' ] && ! S[ 'root' ] ) {
			b( 'only root can edit root' );
		}

		$priv_lists = $this->_build_priv_list( $row[ 'privilege' ] );

		$priv_tags = [];
		$row[ 'priv_tags' ] = str2arr( $row[ 'priv_tags' ] );
		foreach( static::PRIV_TAGS as $key => $val ) {
			if ( empty( ADMIN_MENU_P0[ substr( $key, 0, strpos( $key, '.' ) ) ] ) ) {
				continue;
			}

			$priv_tags[] = [
				'tag'	=> $key,
				'tagshort'	=> str_replace( ".", "", $key ),
				'title'	=> ADMIN_MENU_P0[ substr( $key, 0, strpos( $key, '.' ) ) ] . "-$val",
				'curr'	=> in_array( $key, $row[ 'priv_tags' ] ),
			];
		}

		// $row[ 'admin_tags' ] = explode( ',', $row[ 'admin_tags' ] );
		$admin_tags = $this->build_admin_tags( $row[ 'admin_tags' ] );

		// Build disabled cols for certain menus
		$cols_defines = $this->_build_cols_diy_list( $row[ 'cols_disabled' ], $row[ 'cols_defines' ] );

		$this->_tpl( [
			'row'  => s::html( $row, true ),
			'priv_lists'  => $priv_lists,
			'priv_tags'  => $priv_tags,
			'admin_tags'  => $admin_tags,
			'cols_defines'  => $cols_defines,
		] );
	}

	/**
	 * Add
	 */
	protected function Insert() {
		$data = $this->_sanitize_input();

		//用户存在与否
		if ( db::s( static::TB, [ 'name' => $data[ 'name' ], 'active' => [ '>=', -1 ] ] ) ) b( 'username exist' );
		// if ( ID && db::s( static::TB, ID ) ) b( '已有此id' );
		if ( empty( $data[ 'pswd' ] ) ) {
			b( 'Need password' );
		}

		$data[ 'acp_id' ] = S[ 'acp_id' ];
		$data[ 'acp_truename' ] = S[ 'acp_truename' ];
		$data[ 'active' ] = 1;
		$data[ 'dateline' ] = TS;

		// if ( ID ) {
		// 	$data[ 'id' ] = ID;
		// }

		$id = db::i( static::TB, self::ll( $data ) );
		$this->actlog( $id, 'Added' );

		// Store for next add
		cookie( 'add.privilege', $data[ 'privilege' ], 864000 );
		cookie( 'add.admin_tags', $data[ 'admin_tags' ], 864000 );

		j();
	}

	/**
	 * Edit submit
	 */
	protected function Update() {
		$row = $this->_row();
		if ( $row[ 'root' ] && ! S[ 'root' ] ) {
			b( 'only root can edit root' );
		}

		$data = $this->_sanitize_input();
		//用户存在与否
		if ( db::s(  static::TB, [ 'name' => $data[ 'name' ], [ '!=', ID ], 'active' => [ '>=', -1 ] ] ) ) b( 'username exist' );

		db::u( static::TB, self::ll( $data ), ID );
		$this->actlog( ID, 'Updated' );
		//编辑之后若有登录则重载其权限
		db::d( static::TB_SESSION, [ 'acp_id' => ID ] );

		j();
	}

	/**
	 * Sanitize input
	 */
	private function _sanitize_input() {
		$farr = [ 'name', 'truename', 'idname', 'root', 'privilege', 'priv_tags', 'mobile', 'email', 'pswd', 'admin_tags', 'ps', 'cols_defines', 'cols_disabled' ];
		$data = [];
		foreach( $farr as $v ) $data[ $v ] = _post( $v );

		if ( ! $data[ 'truename' ] || ! $data[ 'name' ] ) b( 'lack of idname/truename/username' );
		if ( ! $data[ 'mobile' ] ) b( 'lack of mobile' );
		if ( strlen( $data[ 'mobile' ] ) != 10 && substr( $data[ 'mobile' ], 0, 1 ) !== '+' ) {
			b( 'Mobile needs to start w/ +' );
		}
		if ( preg_match( '/[^\w\.]/', $data[ 'name' ] ) ) b( 'Illegal login name' );
		if ( preg_match( '/[^\w ]/', $data[ 'truename' ] ) ) b( 'Illegal truename' );
		// if ( preg_match( '/[^\w ]/', $data[ 'idname' ] ) ) b( 'Illegal idname' );
		if ( is_numeric( substr( $data[ 'name' ], 0, 1 ) ) ) b( '用户名不允许采用数字开头' );
		$len = s::len( $data[ 'name' ] );
		if ( $len < 2 || $len > 18 ) b( '用户名长度应介于 2 - 18 字符之间' );
		$data[ 'name' ] = strtolower( $data[ 'name' ] );
		// if ( ! isint( $data[ 'mobile' ] ) || s::len( $data[ 'mobile' ] ) < 10 ) b( 'Mobile error' );

		$data[ 'priv_tags' ] = arr2str( $data[ 'priv_tags' ] );
		$data[ 'admin_tags' ] = arr2str( $data[ 'admin_tags' ] );
		$data[ 'cols_defines' ] = arr2str( $data[ 'cols_defines' ] );
		$data[ 'cols_disabled' ] = arr2str( $data[ 'cols_disabled' ] );

		// Privilege handler
		$privArr = [];
		if ( empty( $data[ 'root' ] ) ) {
			foreach( ADMIN_MENU_P0 as $p0 => $p1arr ) {
				$p1arr = ! empty( ADMIN_MENUS[ $p0 ] ) ? ADMIN_MENUS[ $p0 ] : [];
				if ( empty( $data[ 'privilege' ][ $p0 ] ) ) continue;
				$privArr[] = $p0;
				foreach( $p1arr as $p1 => $title ) {
					if ( empty( $data[ 'privilege' ][ $p0 ][ $p1 ] ) ) continue;
					$privArr[] = "$p0.$p1";
				}
			}
		}
		$data[ 'privilege' ] = arr2str( $privArr );

		if ( $data[ 'root' ] && ! S[ 'root' ] ) {
			b( 'Only root can assign root' );
		}

		// If has password
		if ( $data[ 'pswd' ] ) {
			$data[ 'pswd' ] = $this->hash_pswd( $data[ 'pswd' ] );
		}
		else {
			unset( $data[ 'pswd' ] );
		}

		return $data;
	}

	/**
	 * Update pswd
	 */
	public function update_pswd( $id, $pswd ) {
		db::u( static::TB, [ 'pswd' => $this->hash_pswd( $pswd ) ], $id );
	}

	/**
	 * Get hashed pswd
	 */
	public function hash_pswd( $pswd ) {
		return password_hash( $pswd, PASSWORD_ARGON2ID );
	}

	/**
	 * Verify a password
	 */
	public function verify_pswd( $pswd, $record ) {
		return password_verify( $pswd, $record );
	}

	/**
	 *  Load or Generate user logo if not existed
	 */
	public function logo( $id ) {
		$admin = $this->row_by_id( $id );
		if ( $admin[ 'avatar' ] && file_exists( ROOT . 'upload/avatars/' . parse_url( $admin[ 'avatar' ], PHP_URL_PATH ) ) ) {
			return '/upload/avatars/' . $admin[ 'avatar' ];
		}

		return false;
		return '/assets/img/_img/avatar.png';

		// Generate a tmp logo (25x25) for this user
		$color = '';
	}

	/**
	 * Generate random logo
	 */
	protected function Logo_random() {
		$row = $this->_row();

		auth::cls()->avatar_random( ID, $row[ 'truename' ] );

		j( 1 );
	}

	/**
	 * Upload logo
	 */
	protected function Logo_upload() {
		$row = $this->_row();

		auth::cls()->avatar_upload( ID );

		j( 1 );
	}

	/**
	 * Show detail
	 */
	protected function Detail() {
		$row = $this->_row();
		$row = $this->_readable( $row );

		$code = db::s( \auth::TB_DYNAMIC_PSWD, [ 'acp_id' => ID ] );
		if ( $code ) {
			$code = $code[ 'code' ];
		}

		$data = [
			'row'	=> $row,
			'actlog'=> $this->actlog_list( ID ),
			'code'	=> $code,
		];

		$this->_tpl( $data );
	}

	/**
	 * Prepare readable info
	 */
	protected function _readable( $row ) {
		$row[ 'privilege' ] = str2arr( $row[ 'privilege' ] );
		$row[ 'priv_tags' ] = str2arr( $row[ 'priv_tags' ] );
		$row[ 'admin_tags' ] = str2arr( $row[ 'admin_tags' ] );
		$row = s::html( $row );

		$tmp = [];
		foreach( $row[ 'privilege' ] as $v ) {
			$br = 0;
			if ( ! empty( ADMIN_MENU_P0[ $v ] ) ) {
				$title = ADMIN_MENU_P0[ $v ];
				$br = 1;
			} else {
				$v = explode( '.', $v );
				if ( empty( ADMIN_MENUS[ $v[ 0 ] ] ) ) continue;
				if ( empty( ADMIN_MENUS[ $v[ 0 ] ][ $v[ 1 ] ] ) ) continue;
				$title = ADMIN_MENUS[ $v[ 0 ] ][ $v[ 1 ] ];
			}
			$tmp[] = [
				'title'	=> $title,
				'br'	=> $br,
			];
		}
		$row[ 'privilege' ] = $tmp;

		$tmp = [];
		foreach( $row[ 'priv_tags' ] as $v ) {
			$tmp[] = [ 'title' => ! empty( static::PRIV_TAGS[ $v ] ) ? s::color( static::PRIV_TAGS[ $v ] ) : $v ];
		}
		$row[ 'priv_tags' ] = $tmp;

		$tmp = [];
		foreach( array_filter( $row[ 'admin_tags' ] ) as $v ) {
			$title = $this->cls( 'admin_tag' )->title( $v );
			if ( $title ) {
				$tmp[] = [ 'title' => s::color( $title ) ];
			}
		}
		$row[ 'admin_tags' ] = $tmp;

		$geoip = \ip::geo( $row[ 'lastip' ] );
		$row[ 'ip' ] = '';
		if ( $geoip[ 'country_code' ] != 'US' ) {
			$row[ 'ip' ] = "<font color='red'>$geoip[country]</font>";
		}
		$row[ 'ip' ] .= $geoip[ 'subdivision' ] . ' ';
		$row[ 'ip' ] .= $geoip[ 'city' ];

		$row[ '_diffname' ] = $row[ 'idname' ] && $row[ 'truename' ] != $row[ 'idname' ];

		return $row;
	}

	/**
	 * Lock one row
	 */
	protected function Lock() {
		db::d( static::TB_SESSION, [ 'acp_id' => ID ] );
		parent::Lock();
	}

	/**
	 * Default handler
	 */
	public function handler() {
		if ( ! static::priv( 'acp_edit' ) ) {
			b( 'No edit admin access' );
		}

		parent::handler();
	}

}
