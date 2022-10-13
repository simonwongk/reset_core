<?php
defined( 'IN' ) || exit;
/**

CREATE TABLE `menu_col` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `p` varchar(255) DEFAULT NULL,
  `title` varchar(255) DEFAULT NULL,
  `type` varchar(50) NOT NULL DEFAULT 'dropdown',
  `values` varchar(800) DEFAULT NULL COMMENT 'array of value and color for dropdown',
  `priority` smallint(6) DEFAULT NULL,
  `active` tinyint(4) NOT NULL DEFAULT 0,
  `readonly` tinyint(4) NOT NULL DEFAULT 0,
  `disabled_admin_tag_ids` varchar(500) NOT NULL DEFAULT '',
  `reverse_search` tinyint(4) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `p` (`p`),
  KEY `priority` (`priority`),
  KEY `active` (`active`),
  KEY `p_2` (`p`,`active`),
  KEY `p_3` (`p`,`active`,`title`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4;

CREATE TABLE `menu_data` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `menu_col_id` int(11) DEFAULT NULL,
  `ref_id` int(11) DEFAULT NULL COMMENT 'Related record ID',
  `val` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `data_id` (`ref_id`,`menu_col_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

# 2022/01/19
ALTER TABLE menu_col ADD `readonly` tinyint(4) NOT NULL DEFAULT 0, ADD `disabled_admin_tag_ids` varchar(500) NOT NULL DEFAULT '';

 */

abstract class root_cols_diy {
	const TB_MENU_COL = 'acp.menu_col';
	const TB_MENU_DATA = 'acp.menu_data';
	const TB_COLS_DIY = 'log.cols_diy';

	const COLS_DEFINED_TYPES = [ 'dropdown', 'link', 'text', 'email', 'number', 'date', 'geo', 'member', 'mirror' ];

	protected $cols = [];
	protected $cols_valid = [];
	protected $cols_list = [];
	protected $cols_defined;
	protected $cols_defined_mirror;
	protected $cols_defined_valid;

	/**
	 * Col diy filters template
	 */
	protected function _cols_filter_tpl( $filters_only = false ) {
		if ( ! $this->cols_defined_valid ) {
			return false;
		}

		$cols_list = [];
		$member_groups = [];
		foreach ( $this->cols_defined_valid as $col ) {
			$curr_col_defined = $this->cols_defined[ $col ];
			$ori_fid = $curr_col_defined[ 'id' ];
			$col_title = $curr_col_defined[ 'title' ];
			$reverse_search = $curr_col_defined[ 'reverse_search' ];
			// Mirror will use mirrored column's attributes
			if  ( $curr_col_defined[ 'type' ] == 'mirror' ) {
				$col = $curr_col_defined[ 'values' ][ 0 ];
				$curr_col_defined = $this->cols_defined_mirror[ $col ];
				$reverse_search |= $curr_col_defined[ 'reverse_search' ];
			}

			if ( $curr_col_defined[ 'type' ] == 'member' ) {
				$member_groups = array_unique( array_merge( $member_groups, str2arr( $curr_col_defined[ 'values' ] ) ) );
			}

			list( $o_v, $o_v2start, $o_v2end ) = $this->_init_o_v( $col, $curr_col_defined[ 'type' ] );

			$cols_list[] = [
				'ori_fid' => $ori_fid,
				'col' => $curr_col_defined,
				'col_title' => $col_title,
				'val' => $this->_final_filter_looks( $curr_col_defined, $o_v ),
				'existing_data' => $o_v,
				'o_v2start' => $o_v2start,
				'o_v2end' => $o_v2end,
			];

			if ( $reverse_search ) {
				list( $o_v, $o_v2start, $o_v2end ) = $this->_init_o_v( 'R' . $col, $curr_col_defined[ 'type' ] );

				$cols_list[] = [
					'ori_fid' => 'R' . $ori_fid,
					'col' => $curr_col_defined,
					'col_title' => $col_title,
					'val' => $this->_final_filter_looks( $curr_col_defined, $o_v, true ),
					'existing_data' => $o_v,
					'o_v2start' => $o_v2start,
					'o_v2end' => $o_v2end,
					'reverse_search' => true,
				];
			}
		}

		$members = $member_groups ? array_values( instance::cls( 'sys\admin' )->rows_by_tag( $member_groups ) ) : false;
		$members_myself = $members && in_array( S[ 'acp_id' ], array_column( $members, 'id' ) );

		$tpl = new t( 'cols_filter', _SYS );
		$tpl->assign( 'cols_list', $cols_list );
		$tpl->assign( 'filters_only', $filters_only );
		$tpl->assign( 'members', $members );
		$tpl->assign( 'members_myself', $members_myself );
		$tpl->assign( 'o95', ! defined( 'static::NO_COL_ACTIVE' ) ? $this->filter_list[ 'o95' ] : false );
		$tpl->assign( 'has_trash', ! defined( 'static::NO_COL_ACTIVE' ) );
		return $tpl->output( true );
	}

	/**
	 * o_v init
	 */
	private function _init_o_v( $col, $type = false ) {
		$o_v = _cookie( "o_col$col" );
		if ( ! is_array( $o_v ) ) {
			$o_v = $o_v ? [ $o_v ] : [];
		}

		$o_v2start = false;
		$o_v2end = false;
		if  ( $type == 'date' ) {
			$o_v2start = _get( "s{$col}m" ) ? _get( 'start' ) : _cookie( "o_col{$col}start" );
			$o_v2end = _get( "s{$col}m" ) ? _get( 'end' ) : _cookie( "o_col{$col}end" );
		}

		return [ $o_v, $o_v2start, $o_v2end ];
	}

	/**
	 * Generate single col filter looks
	 */
	private function _final_filter_looks( $curr_col_defined, $existing_vals ) {
		// Dropdown special handler
		if  ( $curr_col_defined[ 'type' ] == 'dropdown' ) {
			$val = [];
			foreach ( str2arr( $curr_col_defined[ 'values' ] ) as $v ) {
				$color = ! empty( $v[ 1 ] ) ? $v[ 1 ] : false;
				$val[] = [
					'title' => $v[ 0 ],
					'color' => $color,
					'curr' => in_array( $v[ 0 ], $existing_vals ),
					// 'link' => '&val=' . $v[ 0 ], // non use yet
				];
			}
		}
		else if ( $curr_col_defined[ 'type' ] == 'member' ) {
			$val = [];
			foreach ( instance::cls( 'sys\admin' )->rows_by_tag( str2arr( $curr_col_defined[ 'values' ] ) ) as $v ) {
				$val[] = [
					'id' => $v[ 'id' ],
					'truename' => $v[ 'truename' ],
					'curr' => in_array( $v[ 'id' ], $existing_vals ),
					// 'link' => '&val=' . $v[ 'id' ],
				];
			}
		}
		else { // Other filters don't allow multi values
			$val = $existing_vals ? $existing_vals[ 0 ] : '';
		}

		return $val;
	}

	/**
	 * Maybe update o_v values
	 */
	private function _maybe_alter_o_v( $o_v, $o ) {
		if ( _get( "s$o" ) ) {
			if ( $del = _get( 'del' ) ) {
				unset( $o_v[ array_search( $del, $o_v ) ] );
			}

			$set = _get( "o$o" );
			if ( $set && ! in_array( $set, $o_v ) ) {
				$o_v[] = $set;
			}
		}

		return $o_v;
	}

	/**
	 * Col diy filters
	 */
	protected function _cols_filter() {
		$this->_load_cols_defined();
		if ( ! $this->cols_defined_valid ) {
			return;
		}

		// Filter Any Member
		$o = '_col00';
		list( $o_v ) = $this->_init_o_v( '00' );
		$ori_v = $o_v;

		$o_v = $this->_maybe_alter_o_v( $o_v, $o );

		$alias = 'col00';

		if ( $o_v ) {
			$o_v = array_unique( $o_v );
			$o_v = array_filter( $o_v );
			$o_v = array_values( $o_v );
			$cond_v = [];
			foreach ( $o_v as $k => $v ) {
				$cond_v[] = $v !== '_zero' ? $v : 0; // Special value `_zero` for 0 case
			}
			if ( $cond_v ) {
				// Make table alias
				$member_cols = [];
				$mirror_id = 'id';
				foreach ( $this->cols_defined_valid as $col ) {
					$curr_col_defined = $this->cols_defined[ $col ];
					if  ( $curr_col_defined[ 'type' ] == 'mirror' ) { // Mirror will use mirrored column's attributes
						$col = $curr_col_defined[ 'values' ][ 0 ];
						$mirror_id = $curr_col_defined[ 'values' ][ 1 ];
						$curr_col_defined = $this->cols_defined_mirror[ $col ];
					}

					if ( $curr_col_defined[ 'type' ] == 'member' ) {
						$member_cols[] = $col;
					}

				}
				$this->_alias_tb( $alias, $member_cols, $mirror_id );

				$this->cond[ $this->_pad( $alias . '.val' ) ] = [ 'in', $cond_v ];
				defined( 'COLDIY_ANY_MEMBER_SEARCH' ) || define( 'COLDIY_ANY_MEMBER_SEARCH', true );
			}
		}
		if ( $ori_v != $o_v ) {
			cookie( "o$o", $o_v, 8640000 );
		}

		// Current existing filters display
		foreach ( $o_v as $v ) {
			$this->curr_filters[] = [
				'o'	=> $o,
				'title_prefix'	=> 'Any Person',
				'title'	=> instance::cls( 'sys\admin' )->title( $v ),
				'v'	=> urlencode( $v ),
			];
		}

		// Loop all cols to filter
		foreach ( $this->cols_defined_valid as $col ) {
			$has_reverse_search = $this->_cols_filter_cb( $col );
			if ( $has_reverse_search ) {
				$this->_cols_filter_cb( $col, true );
			}
		}
	}

	/**
	 * Col filter - apply each filter
	 */
	private function _cols_filter_cb( $col, $reverse_search = false ) {
		$col_defined = $curr_col_defined = $this->cols_defined[ $col ];
		$ori_fid = $curr_col_defined[ 'id' ];
		$has_reverse_search = $curr_col_defined[ 'reverse_search' ];
		// Mirror will use mirrored column's attributes
		$mirror_id = 'id';
		if  ( $curr_col_defined[ 'type' ] == 'mirror' ) {
			$col = $curr_col_defined[ 'values' ][ 0 ];
			$mirror_id = $curr_col_defined[ 'values' ][ 1 ];
			$curr_col_defined = $this->cols_defined_mirror[ $col ];
			$has_reverse_search |= $curr_col_defined[ 'reverse_search' ];
		}

		$haystack = false;
		if ( $curr_col_defined[ 'type' ] == 'dropdown' ) {
			$haystack = array_column( str2arr( $curr_col_defined[ 'values' ] ), 0 );
		}
		if ( $curr_col_defined[ 'type' ] == 'member' ) {
			$haystack = array_column( instance::cls( 'sys\admin' )->rows_by_tag( str2arr( $curr_col_defined[ 'values' ] ) ), 'id' );
		}

		$reverse_search = $reverse_search ? 'R' : '';
		$ori_fid = $reverse_search . $ori_fid;

		$o = '_col' . $ori_fid;
		list( $o_v ) = $this->_init_o_v( $ori_fid );
		$ori_v = $o_v;

		$o_v = $this->_maybe_alter_o_v( $o_v, $o );

		$alias = 'col' . $ori_fid;
		$alias_made = false;

		if ( $o_v ) {
			$o_v = array_unique( $o_v );
			$o_v = array_filter( $o_v );
			$o_v = array_values( $o_v );
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
				// Make table alias
				$this->_alias_tb( $alias, $col, $mirror_id );
				$alias_made = true;

				if ( in_array( $curr_col_defined[ 'type' ], [ 'dropdown', 'member' ] ) ) {
					if ( $reverse_search ) {
						$this->cond[ $this->_pad( '_or' ) ] = [
							$alias . '.val' => [ 'not in', $cond_v ],
							$alias . '.val ' => 'NULL',
						];
					}
					else {
						$this->cond[ $this->_pad( $alias . '.val' ) ] = [ 'in', $cond_v ];
					}
				}
				else {
					$cond_k = $this->_pad( '_or' );
					$this->cond[ $cond_k ] = [];
					if ( $reverse_search ) {
						$this->cond[ $cond_k ] = [
							$alias . '.val' => 'NULL',
							'_and' => [],
						];
					}
					foreach ( $cond_v as $v ) {
						if ( $reverse_search ) {
							$this->cond[ $cond_k ][ '_and' ][ $this->_pad( $alias . '.val' ) ] = [ 'not like', '%' . $v . '%' ];
						}
						else {
							$this->cond[ $cond_k ][ $this->_pad( $alias . '.val' ) ] = [ 'like', '%' . $v . '%' ];
						}
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
				if ( $curr_col_defined[ 'type' ] == 'member' ) {
					$title = instance::cls( 'sys\admin' )->title( $title );
				}
			}
			else {
				$title = $v;
			}
			$this->curr_filters[] = [
				'o'	=> $o,
				'title_prefix'	=> $col_defined[ 'title' ],
				'title'	=> $title,
				'v'	=> urlencode( $v ),
				'reverse_search' => $reverse_search,
			];
		}

		// Extra start - end filter handler
		if ( $curr_col_defined[ 'type' ] == 'date' ) {
			$ori_start = $start = _cookie( "o{$o}start" );
			$ori_end = $end = _cookie( "o{$o}end" );
			if ( _get( "s{$o}m" ) ) {
				if ( $del = _get( 'del' ) ) {
					$start = false;
					$end = false;
				}
				else {
					if ( _get( 'start' ) ) {
						$start = _get( 'start' );
					}
					if ( _get( 'end' ) ) {
						$end = _get( 'end' );
					}
				}
			}
			if ( $ori_start != $start ) {
				cookie( "o{$o}start", $start, 8640000 );
			}
			if ( $ori_end != $end ) {
				cookie( "o{$o}end", $end, 8640000 );
			}
			if ( $start && $end ) {
				$alias_made || $this->_alias_tb( $alias, $col, $mirror_id );
				$this->cond[ $this->_pad( $alias . '.val' ) ] = [ 'between', $start, $end ];
				$this->curr_filters[] = [
					'o'	=> $o . 'm',
					'title_prefix'	=> $col_defined[ 'title' ],
					'title'	=> $start . ' to ' . $end,
					'v'		=> $start,
				];
			}
		}

		return $has_reverse_search;
	}

	/**
	 * Add table alias
	 */
	private function _alias_tb( $alias, $col, $mirror_id ) {
		if ( strpos( $this->_tb, ',' ) ) {
			$this->_tb .= ',' . self::TB_MENU_DATA . ' ' . $alias;
			$this->cond[ $this->_pad( '_raw' ) ] = "$alias.ref_id=a.$mirror_id";
			$this->cond[ $this->_pad( $alias . '.menu_col_id' ) ] = is_array( $col ) ? [ 'in', $col ] : $col;
		}
		else {
			$cond = is_array( $col ) ?  'menu_col_id in (' . implode( ',', $col ) . ')' : "menu_col_id='$col'";
			$this->_tb .= ' LEFT JOIN ' . self::TB_MENU_DATA . " $alias ON $alias.$cond AND $alias.ref_id=a.$mirror_id "; // Assume main table is always `a.`
		}
	}

	/**
	 * Get customized columns in list if there is any
	 */
	protected function _cols() {
		if ( ! defined( 'static::COLS' ) ) {
			return;
		}

		if ( $this->cols ) {
			return;
		}

		// Load hidden cols and cols priority
		$cols_diy = db::s( self::TB_COLS_DIY, [ 'acp_id' => S[ 'acp_id' ], 'p' => P ] );
		$cols_hide = [];
		if ( $cols_diy ) {
			$cols_hide = str2arr( $cols_diy[ 'cols_hide' ] );
		}
		else {
			$cols_hide = [];
			foreach ( static::COLS as $k => $v ) {
				if ( ! $v ) {
					$cols_hide[] = $k;
				}
			}
		}

		// Load default cols
		$cols_default = static::COLS;

		// Append defined cols
		$this->_load_cols_defined();
		foreach ( $this->cols_defined_valid as $col ) {
			$cols_default[ $col ] = 1;
		}

		// Build cols priority
		$cols_visible = [];
		foreach ( $cols_default as $col => $tmp ) {
			$this->cols_valid[ $col ] = 1;

			if ( in_array( $col, $cols_hide ) ) {
				continue;
			}
			$cols_visible[] = $col;
		}
		$final_cols = [];
		if ( $cols_diy[ 'cols_priority' ] ) {
			$cols_priority = str2arr( $cols_diy[ 'cols_priority' ] );
			foreach ( $cols_priority as $col ) {
				if ( in_array( $col, $cols_visible ) ) {
					$final_cols[] = $col;
				}
			}
			$final_cols = array_merge( $final_cols, array_diff( $cols_visible, $cols_priority ) );
		}
		else {
			$final_cols = $cols_visible;
		}

		$this->cols = [];
		$this->cols_list = [];
		foreach ( $final_cols as $col ) {
			$this->cols[] = $col;

			$col_custom = array_key_exists( $col, $this->cols_defined );

			// Build the `thead -> th` col title loop on List page
			$col_title = $col;
			$cls = 'custom_col';
			if ( $col_custom ) {
				$curr_col_defined = $this->cols_defined[ $col ];
				$col_title = __( $curr_col_defined[ 'title' ] );
				if ( $curr_col_defined[ 'type' ] == 'mirror' ) {
					$curr_col_defined = $this->cols_defined_mirror[ $curr_col_defined[ 'values' ][ 0 ] ];
				}
				if ( $curr_col_defined[ 'type' ] == 'member' ) {
					$cls .= ' member';
				}
			}

			$readonly = $col_custom && $this->cols_defined[ $col ][ 'readonly' ] ? '<sup title="Read only" class="text-secondary">*</sup>' : '';

			$this->cols_list[] = [
				'_col' => $col,
				'col_title' => $col_title,
				'_col_custom' => $col_custom,
				'_col_th' => '<th class="py-3 border-left border-light ' . $cls . '">' . $col_title . $readonly . '</th>',
				'o01link' => $this->o01link,
			];
		}
	}

	/**
	 * Append all valid defined cols data
	 */
	protected function append_cols_detail( $row ) {
		$this->_load_cols_defined();
		$menu_data = $this->get_col( $row[ 'id' ], $this->cols_defined_valid, $row );

		$cols_list = [];
		foreach ( $this->cols_defined_valid as $col ) {
			$col_ori = $curr_col_defined = $this->cols_defined[ $col ];
			$readonly = $curr_col_defined[ 'readonly' ];
			if ( $curr_col_defined[ 'type' ] == 'mirror' ) {
				$curr_col_defined = $this->cols_defined_mirror[ $curr_col_defined[ 'values' ][ 0 ] ];
				$readonly |= $curr_col_defined[ 'readonly' ];
			}
			$cols_list[] = [
				'is_modal' => from_modal(),
				'col' => $col_ori,
				'readonly' => $readonly,
				'val' => $this->_final_col_looks( $col, $menu_data[ $col ], $row, 'div' ),
			];
		}

		$tpl = new t( 'cols_list', _SYS );
		$tpl->assign( 'cols_list', $cols_list );
		$row[ '_cols_list' ] = $tpl->output( true );

		return $row;
	}

	/**
	 * Show diy cols inputs on Add/Edit page
	 */
	protected function gen_cols_tpl( $ref_id = false ) {
		$this->_load_cols_defined();
		if ( ! $this->cols_defined ) {
			return;
		}

		// Load existing data
		$menu_data = [];
		if ( $ref_id ) {
			$menu_data = $this->get_col( $ref_id, $this->cols_defined_valid );
		}

		// build menu
		$cols_list = [];
		foreach ( $this->cols_defined_valid as $col ) {
			$col_ori = $curr_col_defined = $this->cols_defined[ $col ];
			$readonly = $curr_col_defined[ 'readonly' ];
			if ( $curr_col_defined[ 'type' ] == 'mirror' ) {
				if ( ! $ref_id && $curr_col_defined[ 'values' ][ 1 ] != 'id' ) { // Add page will bypass mirror col when the col ID is different
					continue;
				}
				$curr_col_defined = $this->cols_defined_mirror[ $curr_col_defined[ 'values' ][ 0 ] ];
				$readonly |= $curr_col_defined[ 'readonly' ];
			}

			if ( $readonly ) {
				continue;
			}

			$existing_data = ! empty( $menu_data[ $col ] ) ? $menu_data[ $col ] : false;

			// Show col value page
			$options_list = [];
			if ( $curr_col_defined[ 'type' ] == 'dropdown' ) {
				foreach ( str2arr( $curr_col_defined[ 'values' ] ) as $v ) {
					$color = ! empty( $v[ 1 ] ) ? $v[ 1 ] : false;
					$options_list[] = [
						'col_ori' => $col_ori,
						'col_info' => $curr_col_defined,
						'title' => $v[ 0 ],
						'color' => $color,
						'curr' => $v[ 0 ] == $existing_data,
					];
				}
			}
			$assign_to_me = false;
			if ( $curr_col_defined[ 'type' ] == 'member' ) {
				foreach ( instance::cls( 'sys\admin' )->rows_by_tag( str2arr( $curr_col_defined[ 'values' ] ) ) as $v ) {
					$options_list[] = [
						'col_ori' => $col_ori,
						'col_info' => $curr_col_defined,
						'id' => $v[ 'id' ],
						'truename' => $v[ 'truename' ],
						'curr' => $v[ 'id' ] == $existing_data,
					];

					if ( $v[ 'id' ] == S[ 'acp_id' ] ) {
						$assign_to_me = S[ 'acp_id' ];
					}
				}
			}

			$cols_list[] = [
				'is_modal' => from_modal(),
				'existing_data' => s::html( $existing_data ),
				'col_ori' => $col_ori,
				'col_info' => $curr_col_defined,
				'options_list' => $options_list,
				'assign_to_me' => $assign_to_me,
				'google_geo_key' => _cfg( 'google', 'apikey' ),
			];
		}

		$tpl = new t( 'cols_full_set', _SYS );
		$tpl->assign( [
			'cols_list'	=> $cols_list,
		] );
		return $tpl->output( true );
	}

	/**
	 * Load col set data by cols list
	 * @since 2021-12-17 Mirror column will keep the original column ID while not mirror src column ID as key
	 */
	public function get_col( $ref_ids, $cols = false, $ref_ids2rows = false ) {
		$this->_load_cols_defined();

		// Prepare ref_ids
		$ref_ids_arr = $ref_ids;
		if ( ! is_array( $ref_ids ) ) {
			$ref_ids_arr = [ $ref_ids ];
			if ( $ref_ids2rows !== false ) {
				$ref_ids2rows = [ $ref_ids2rows ];
			}
		}

		// Prepare cols
		$cols_arr = $cols;
		if ( ! $cols ) {
			$cols_arr = array_keys( $this->cols_defined );
		}
		elseif ( ! is_array( $cols ) ) {
		 	$cols_arr = [ $cols ];
		}

		// Mirror col checking
		$customized_col_ids = [];
		$mirror_mapping = [];
		$root_ref_ids = [];
		foreach ( $cols_arr as $col ) {
			if ( ! array_key_exists( $col, $this->cols_defined ) ) {
				continue;
			}
			if ( $this->cols_defined[ $col ][ 'type' ] == 'mirror' ) {
				$root_col = $this->cols_defined[ $col ][ 'values' ][ 0 ];
				$mapping_field = $this->cols_defined[ $col ][ 'values' ][ 1 ];
				if ( $mapping_field != 'id' ) { // Special table col mapping
					if ( $ref_ids2rows === false ) {
						$ref_ids2rows = $this->list_by_ids( $ref_ids_arr );
					}
					// Build ID -> mirror col -> root mirrored ref_id Mapping
					foreach ( $ref_ids2rows as $v2 ) {
						$mirror_mapping[ $v2[ 'id' ] ][ $root_col ] = $v2[ $mapping_field ];
						$root_ref_ids[ $v2[ $mapping_field ] ] = 1;
					}
				}
				$customized_col_ids[] = $root_col;
			}
			else {
				$customized_col_ids[] = $col;
			}
		}

		// Data in db
		$menu_src_data = [];
		if ( $customized_col_ids ) {
			$cond = [
				'ref_id' => [ 'in', array_merge( $ref_ids_arr, array_keys( $root_ref_ids ) ) ],
				'menu_col_id' => [ 'in', $customized_col_ids ],
			];
			$tmp = db::sa( [ 'menu_col_id, ref_id, val', self::TB_MENU_DATA ], $cond );
			foreach ( $tmp as $v ) {
				$val = $v[ 'val' ];
				$curr_col_defined = ! empty( $this->cols_defined[ $v[ 'menu_col_id' ] ] ) ? $this->cols_defined[ $v[ 'menu_col_id' ] ] : $this->cols_defined_mirror[ $v[ 'menu_col_id' ] ];

				if ( $curr_col_defined[ 'type' ] == 'link' ) {
					$val = $this->_try_revert_link( $val );
				}

				if ( ! is_array( $ref_ids ) && ! is_array( $cols ) ) {
					return s::html( $val );
				}

				if ( ! is_array( $cols ) ) {
					$menu_src_data[ $v[ 'ref_id' ] ] = s::html( $val );
				}
				else {
					$menu_src_data[ $v[ 'ref_id' ] ][ $v[ 'menu_col_id' ] ] = s::html( $val );
				}
			}
		}

		// Convert to original col mapping data array
		$menu_data = [];
		foreach ( $ref_ids_arr as $ori_ref_id ) {
			foreach ( $cols_arr as $ori_col ) {
				if ( ! array_key_exists( $ori_col, $this->cols_defined ) ) {
					continue;
				}

				$col = $ori_col;
				$ref_id = $ori_ref_id;
				if ( $this->cols_defined[ $ori_col ][ 'type' ] == 'mirror' ) {
					$col = $this->cols_defined[ $ori_col ][ 'values' ][ 0 ];
					// Revert root id to original id
					if ( $this->cols_defined[ $ori_col ][ 'values' ][ 1 ] != 'id' ) {
						$ref_id = $mirror_mapping[ $ori_ref_id ][ $col ];
					}
				}

				if ( ! is_array( $cols ) ) {
					$menu_data[ $ori_ref_id ] = isset( $menu_src_data[ $ref_id ] ) ? $menu_src_data[ $ref_id ] : false;
				}
				else {
					$menu_data[ $ori_ref_id ][ $ori_col ] = isset( $menu_src_data[ $ref_id ][ $col ] ) ? $menu_src_data[ $ref_id ][ $col ] : false;
				}
			}
		}

		if ( ! is_array( $ref_ids ) ) {
			return isset( $menu_data[ $ref_ids_arr[ 0 ] ] ) ? $menu_data[ $ref_ids_arr[ 0 ] ] : [];
		}

		return $menu_data;
	}

	/**
	 * Convert link to array
	 */
	private function _try_revert_link( $v ) {
		if ( strpos( $v, '{' ) === 0 ) {
			$v = str2arr( $v );
			$text = ! empty( $v[ 'text' ] ) ? $v[ 'text' ] : (! empty( $v[ 'url' ] ) ? $v[ 'url' ] : false);
			$link = ! empty( $v[ 'url' ] ) ? $v[ 'url' ] : false;
			$link_data = [
				'text' => $text,
				'url' => $link,
			];
		}
		else { // compatible to previous data
			$link_data = [
				'text' => parse_url( $v, PHP_URL_HOST ) . parse_url( $v, PHP_URL_PATH ),
				'url' => $v,
			];
		}

		return $link_data;
	}

	/**
	 * Generate single col value w/ editable link
	 */
	private function _final_col_looks( $col, $cell_con, $row, $el, $ignore_modal_check = false ) { // todo: need to return pure content for data-mirror_root override
		$cell_css = '';
		$extra_attr = '';

		$curr_col_defined = $this->cols_defined[ $col ];
		$ori_fid = $curr_col_defined[ 'id' ];
		$readonly = $curr_col_defined[ 'readonly' ];
		// Mirror will use mirrored column's attributes
		if  ( $curr_col_defined[ 'type' ] == 'mirror' ) {
			if ( $curr_col_defined[ 'values' ][ 1 ] != 'id' ) {
				$extra_attr .= ' data-mirror_root="' . $row[ $curr_col_defined[ 'values' ][ 1 ] ] . '"';
			}
			$col = $curr_col_defined[ 'values' ][ 0 ];
			$curr_col_defined = $this->cols_defined_mirror[ $col ];
			$readonly |= $curr_col_defined[ 'readonly' ];
		}

		$link = '/' . $this->_get_p( true ) . '/' . $row[ 'id' ] . '/cols_diy?step=set&fid=' . $ori_fid . '&el=' . $el;
		$modal = 'modal';
		if ( from_modal() && ! $ignore_modal_check ) { // For the details already in modal, open in current page
			$modal = '_self';
		}

		// Dropdown special handler
		if  ( $curr_col_defined[ 'type' ] == 'dropdown' ) {
			! $readonly && $cell_css = ' custom_col_dropdown';
			if ( ! empty( $curr_col_defined[ '_title2color' ][ $cell_con ] ) ) {
				$extra_attr .= ' style="color:#fff;background-color: ' . $curr_col_defined[ '_title2color' ][ $cell_con ] . '"';
			}
		}
		// Maybe transform cell content to different format
		$alt_title = $cell_con;
		if ( $cell_con ) {
			if  ( $curr_col_defined[ 'type' ] == 'link' ) {
				$cell_css .= ' custom_col_link';
				$alt_title = $cell_con[ 'url' ];
				$cell_con = '<a href="' . $cell_con[ 'url' ] . '" class="e_cover_front" target="_blank" data-cell_con>' . $cell_con[ 'text' ] . '</a>';
			}
			elseif  ( $curr_col_defined[ 'type' ] == 'member' ) {
				$cell_css .= ' custom_col_memeber';
				$title = instance::cls( 'sys\admin' )->title( $cell_con );
				$alt_title = $title;
				$logo = instance::cls( 'sys\admin' )->logo( $cell_con );
				if ( $logo ) {
					$cell_con = '<img class="member" src="' . $logo . '" />';
					if ( $el != 'td' ) { // For detail page display, add username
						$cell_con .= '<span class="pl-1">' . $title . '</span>';
					}
				}
				else {
					// $cell_con = '<i class="fa fa-user-o"></i> ' . $title;
					$cell_con = $title;
				}
			}
			elseif  ( in_array( $curr_col_defined[ 'type' ], [ 'text', 'number', 'date' ] ) ) {
				$cell_con = $curr_col_defined[ 'type' ] == 'number' ? number_format( (int)$cell_con ) : $cell_con;
			}

			if ( ! $readonly ) $cell_con .= '<a href="' . $link . '&submit=1" target="' . $modal . '" class="coldiy_clear" title="Clear the value"><i class="fa fa-times"></i></a>';
		}
		else {
			if ( $curr_col_defined[ 'type' ] == 'member' ) {
				$cell_css .= ' custom_col_memeber';
				$cell_con = '<i class="fa fa-user-o text-secondary"></i>';
			}
			if  ( $curr_col_defined[ 'type' ] == 'link' ) {
				$cell_con = '';
				$alt_title = '';
			}
		}

		if ( ! $readonly ) {
			$cell_con .= '<a href="' . $link . '" target="' . $modal . '" class="e_cover">&nbsp;</a><i class="fa fa-pencil-square-o edit_icon"></i>';
			$cell_css .= ' custom_col_editable';
		}

		return sprintf(
			'<%1$s class="custom_col %2$s" %3$s title="%4$s" id="coldiy%5$s_%6$s">%7$s</%1$s>',
			$el,
			$cell_css,
			$extra_attr,
			strip_tags( $alt_title ),
			$row[ 'id' ],
			$ori_fid,
			$cell_con
		);
	}

	/**
	 * Append customized cols & latest note
	 */
	protected function append_cols( $list ) {
		if ( ! $list || ! $this->cols ) {
			return $list;
		}

		// Maybe append latest note column
		if ( in_array( 'latest_note', $this->cols ) ) {
			$latest_notes = [];
			$p = defined( 'static::LINKED_P' ) ? static::LINKED_P : P;
			$tmp = db::raw( 'SELECT t1.ref_id, t1.info, t1.acp_truename, t1.dateline FROM ' . db::prefix_tb( instance::TB_PS ) . ' t1 INNER JOIN (
					SELECT MAX(id) AS max_id FROM ' . db::prefix_tb( instance::TB_PS ) . ' WHERE p="' . $p . '" AND active=1 AND ref_id IN (' . implode( ',', array_column( $list, 'id' ) ) . ') GROUP BY ref_id
				) t2 ON t1.id=t2.max_id' );
			foreach ( $tmp as $v ) {
				$latest_notes[ $v[ 'ref_id' ] ] = $v;
			}
		}

		// Maybe append customized column
		$menu_data = $this->get_col( array_column( $list, 'id' ), $this->cols, $list );

		foreach ( $list as $k => $v ) {
			$v[ 'i' ] = $k + 1;
			// Build the `tbody -> td` col loop on List page
			$cols_list = [];
			foreach ( $this->cols as $col ) {
				$cols_row = $v;
				$cols_row[ '_col' ] = $col;
				$cols_row[ '_col_custom' ] = array_key_exists( $col, $this->cols_defined );
				if ( $col == 'latest_note' ) {
					$cols_row[ '_latest_note' ] = ! empty( $latest_notes[ $cols_row[ 'id' ] ] ) ? s::html( $latest_notes[ $cols_row[ 'id' ] ] ) : false;
				}
				// List custom cols
				elseif ( $cols_row[ '_col_custom' ] ) {
					$cols_row[ '_col_td' ] = $this->_final_col_looks( $col, $menu_data[ $v[ 'id' ] ][ $col ], $v, 'td' );
				}

				$cols_list[] = $cols_row;
			}
			$list[ $k ][ 'cols_list' ] = $cols_list;
		}

		return $list;
	}

	/**
	 * Load default defined cols/mirror root cols/valid cols
	 */
	private function _load_cols_defined() {
		if ( isset( $this->cols_defined ) ) {
			return;
		}

		$admin_groups = false;
		if ( defined( 'S' ) && S[ 'acp_id' ] ) {
			$admin_info = instance::cls( 'sys\admin' )->row_by_id( S[ 'acp_id' ] );
			$admin_groups = str2arr( $admin_info[ 'admin_tags' ] );
		}

		$this->cols_defined = [];
		$mirror_ids = [];
		// $p = defined( 'static::LINKED_P' ) ? [ 'in', [ static::LINKED_P, P ] ] : P;
		$p = str_replace( '\\', '.', static::class );
		if ( ! $p || ! strpos( $p, '.' ) ) {
			b( 'no P' );
		}

		$this->cols_defined_valid = [];

		$list = db::sa( self::TB_MENU_COL, [ 'p' => $p, 'active' => 1 ], 'priority, id' );
		foreach ( $list as $v ) {
			if ( $v[ 'type' ] == 'dropdown' ) {
				$v[ '_title2color' ] = [];
				foreach ( str2arr( $v[ 'values' ] ) as $k2 => $v2 ) {
					$color = ! empty( $v2[ 1 ] ) ? $v2[ 1 ] : false;
					$v[ '_title2color' ][ $v2[ 0 ] ] = $color;
				}
			}
			if ( $v[ 'type' ] == 'mirror' ) {
				$tmp = str2arr( $v[ 'values' ] );
				if ( ! is_array( $tmp ) ) {
					$v[ 'values' ] = [ $v[ 'values' ], 'id' ];
				}
				else {
					$v[ 'values' ] = $tmp;
				}
				$mirror_ids[] = $v[ 'values' ][ 0 ];
			}
			$v = s::html( $v, false, [ 'values', 'disabled_admin_tag_ids' ] );
			$this->cols_defined[ $v[ 'id' ] ] = $v;

			// Check if is disallowed or not
			if ( $v[ 'disabled_admin_tag_ids' ] && $admin_groups && array_intersect( str2arr( $v[ 'disabled_admin_tag_ids' ] ), $admin_groups ) ) {
				continue;
			}

			$this->cols_defined_valid[] = $v[ 'id' ];
		}

		$this->cols_defined_mirror = [];
		if ( $mirror_ids ) {
			$list = db::sa( self::TB_MENU_COL, [ 'id' => [ 'in', $mirror_ids ] ], 'priority, id' );
			foreach ( $list as $v ) {
				if ( $v[ 'type' ] == 'dropdown' ) {
					$v[ '_title2color' ] = [];
					foreach ( str2arr( $v[ 'values' ] ) as $k2 => $v2 ) {
						$color = ! empty( $v2[ 1 ] ) ? $v2[ 1 ] : false;
						$v[ '_title2color' ][ $v2[ 0 ] ] = $color;
					}
				}
				$v = s::html( $v, false, [ 'values', 'disabled_admin_tag_ids' ] );
				$this->cols_defined_mirror[ $v[ 'id' ] ] = $v;
			}
		}

		if ( defined( 'S' ) && ! empty( S[ 'cols_disabled' ][ $p ] ) ) {
			$this->cols_defined_valid = array_diff( $this->cols_defined_valid, S[ 'cols_disabled' ][ $p ] );
		}
	}

	/**
	 * Full cols form Submit handler
	 */
	protected function _save_cols_val( $ref_id ) {
		$col_val = _post( 'col_val' );
		if ( ! $col_val ) {
			return;
		}

		$this->_load_cols_defined();

		$existing_data = $this->get_col( $ref_id );
		foreach ( $col_val as $col => $val ) {
			if ( ! array_key_exists( $col, $this->cols_defined ) ) {
				debug( 'No col id in _set_col_val ' . $col );
				b( 'No such column ID: ' . $col );
			}

			// Check readonly
			$curr_col_defined = $this->cols_defined[ $col ];
			$readonly = $curr_col_defined[ 'readonly' ];
			if ( $curr_col_defined[ 'type' ] == 'mirror' ) {
				$curr_col_defined = $this->cols_defined_mirror[ $curr_col_defined[ 'values' ][ 0 ] ];
				$readonly |= $curr_col_defined[ 'readonly' ];
			}
			if ( $readonly ) {
				debug( 'Readonly column ' . $col );
				b( 'Readonly column ' . $col );
			}

			if ( ! empty( $existing_data[ $col ] ) && $val == $existing_data[ $col ] ) {
				continue;
			}

			$this->set_col( $ref_id, $col, $val );
		}
	}

	/**
	 * Update a custom column value for a certain record
	 */
	public function set_col( $ref_id, $col_id, $val, $bypass_log = false, $bypass_email = false ) {
		$this->_load_cols_defined();

		if ( ! array_key_exists( $col_id, $this->cols_defined ) ) {
			debug( 'No col id in _set_col_val ' . $col_id );
			b( 'No such column ID: ' . $col_id );
		}

		$ori_curr_col_defined = $curr_col_defined = $this->cols_defined[ $col_id ];
		$ori_ref_id = $ref_id;
		$root_id = false;
		$row = [ 'id' => $ori_ref_id ];
		if ( $curr_col_defined[ 'type' ] == 'mirror' ) {
			if ( $curr_col_defined[ 'values' ][ 1 ] != 'id' ) {
				$row = $this->_row( $ref_id, true );
				$root_id = $ref_id = $row[ $curr_col_defined[ 'values' ][ 1 ] ];
			}
			$curr_col_defined = $this->cols_defined_mirror[ $curr_col_defined[ 'values' ][ 0 ] ];
		}

		// Sanitize data
		if ( $val ) {
			if ( $curr_col_defined[ 'type' ] == 'dropdown' ) {
				if ( ! array_key_exists( $val, $curr_col_defined[ '_title2color' ] ) ) {
					$val = false;
				}
			}

			if ( $curr_col_defined[ 'type' ] == 'member' ) {
				$acp_list = instance::cls( 'sys\admin' )->rows_by_tag( str2arr( $curr_col_defined[ 'values' ] ) );
				if ( ! array_key_exists( $val, $acp_list ) ) {
					$val = false;
				}
			}

			if ( $curr_col_defined[ 'type' ] == 'number' ) {
				$val = preg_replace( '/[^\d.]/', '', $val );
			}

			if ( $curr_col_defined[ 'type' ] == 'link' ) {
				if ( ! is_array( $val ) || ! isset( $val[ 'url' ] ) ) {
					b( 'Link needs to specify `url`' );
				}
				if ( ! empty( $val[ 'url' ] ) ) {
					// $val[ 'url' ] = htmlspecialchars_decode( $val[ 'url' ] );
					if ( empty( $val[ 'text' ] ) ) {
						$val[ 'text' ] = parse_url( $val[ 'url' ], PHP_URL_HOST ) . parse_url( $val[ 'url' ], PHP_URL_PATH );
					}
					$val = arr2str( $val );
				}
				else {
					$val = false;
				}
			}
		}

		// Save data
		$log = '';
		$admin = false;
		if ( $val ) {
			$count = db::u( self::TB_MENU_DATA, [ 'val' => $val ], [ 'ref_id' => $ref_id, 'menu_col_id' => $curr_col_defined[ 'id' ] ] );
			// $log = ' --Update [ref_id] ' . $ref_id . ' [menu_col_id] ' . $curr_col_defined[ 'id' ] . ' [val] ' . $val . ' [count] ' . $count;
			if ( ! $count ) {
				$data = [
					'menu_col_id' => $curr_col_defined[ 'id' ],
					'ref_id' => $ref_id,
					'val' => $val,
				];
				$tmp = db::i( self::TB_MENU_DATA, $data );
				// $log = ' --Insert [ref_id] ' . $ref_id . ' [menu_col_id] ' . $curr_col_defined[ 'id' ] . ' [val] ' . $val . ' [id] ' . $tmp;
				$count = 1;
			}

			// Convert val to title for log
			if ( $curr_col_defined[ 'type' ] == 'member' ) {
				$admin = instance::cls( 'sys\admin' )->row_by_id( $val );
				$val = $admin[ 'truename' ];
			}
		}
		else {
			$count = db::d( self::TB_MENU_DATA, [ 'ref_id' => $ref_id, 'menu_col_id' => $curr_col_defined[ 'id' ] ] );
		}

		$val2log = s::html( $val );
		if ( $curr_col_defined[ 'type' ] == 'link' ) {
			$val = str2arr( $val );
			if ( $val && $val[ 'url' ] ) {
				$val2log = '<a href="' . s::html( $val[ 'url' ] ) . '" target="_blank">' . s::html( $val[ 'text' ] ) . '</a>';
			}
		}

		if ( ! $bypass_log && $count ) {
			$this->actlog( $ori_ref_id, 'Set [' . $ori_curr_col_defined[ 'title' ] . '] to ' . $val2log . $log );
		}

		// Store changes for ajax update
		oc::push( 'coldiy' . TS, arr2str( [
			'coldiy' . $ori_ref_id . '_' . $col_id,
			$this->_final_col_looks( $col_id, $val, $row, 'td', true ),
			$root_id
		] ) );
		oc::push( 'coldiy_entries', TS );
		// Clear changes storage older than 10 mins
		$this->cleanup_oc_changes();

		// Send email if member has an email
		if ( ! $bypass_email && ! empty( $admin[ 'email' ] ) && $admin[ 'id' ] != S[ 'acp_id' ] && _cfg( 'mailer', 'email', true ) ) {
			$ref_id = defined( 'ID2' ) ? ID2 : ID;
			$row = $this->_row( $ref_id, true );
			if ( ! empty( $row[ 'title' ] ) ) {
				$title = '[CMS Notification] You have been assigned as [' . $ori_curr_col_defined[ 'title' ] . '] to ' . $row[ 'title' ];
				$link = 'https://' . $_SERVER[ 'HTTP_HOST' ] . '/' . P . '/' . $ref_id;
				$content = 'You have been assigned as [' . $ori_curr_col_defined[ 'title' ] . '] to <a href="' . $link . '" target="_blank">' . $row[ 'title' ] . '</a>.';
				mailer::send( [ [ $admin[ 'email' ], $admin[ 'truename' ] ] ], $title, $content, false, [ 'noreply@epochtimes.com', 'noreply'], false, [ 'name' => S[ 'acp_truename' ] ] );
				if ( ! $bypass_log ) {
					$this->actlog( $ref_id, 'Auto emailed [' . $admin[ 'truename' ] . ']' );
				}
			}
		}

		return $val;
	}

	/**
	 * Maybe send email to members
	 */
	protected function _maybe_send_assigner_email( $ref_id, $info ) {
		if ( ! _cfg( 'mailer', 'email', true ) ) {
			return;
		}

		$this->_load_cols_defined();
		$member_cols = [];
		foreach ( $this->cols_defined as $col_info ) {
			$curr_col_defined = $col_info;
			if  ( $curr_col_defined[ 'type' ] == 'mirror' ) {
				$curr_col_defined = $this->cols_defined_mirror[ $curr_col_defined[ 'values' ][ 0 ] ];
			}

			if ( $curr_col_defined[ 'type' ] == 'member' ) {
				$member_cols[] = $curr_col_defined[ 'id' ];
			}
		}

		$menu_data = $this->get_col( $ref_id, $member_cols );
		foreach ( array_unique( $menu_data ) as $uid ) {
			if ( ! $uid || $uid == S[ 'acp_id' ] ) {
				continue;
			}

			$admin = instance::cls( 'sys\admin' )->row_by_id( $uid );
			if ( empty( $admin[ 'email' ] ) || $admin[ 'id' ] == S[ 'acp_id' ] ) {
				continue;
			}

			// Send email to this member
			$row = $this->_row( $ref_id, true );
			if ( ! empty( $row[ 'title' ] ) ) {
				$title = '[CMS Notification] A new note was added to: ' . $row[ 'title' ];
				$link = 'https://' . $_SERVER[ 'HTTP_HOST' ] . '/' . P . '/' . $ref_id;
				$content = '<p>A new note was added to: <a href="' . $link . '" target="_blank">' . $row[ 'title' ] . '</a></p><strong>' . S[ 'acp_truename' ] . ':</strong> ' . $info;
				mailer::send( [ [ $admin[ 'email' ], $admin[ 'truename' ] ] ], $title, $content, false, [ 'noreply@epochtimes.com', 'noreply'], false, [ 'name' => S[ 'acp_truename' ] ] );
				$this->actlog( $ref_id, 'Auto emailed [' . $admin[ 'truename' ] . ']' );
			}
		}
	}

	/**
	 * Need refresh tag in OC for AJAX update
	 */
	protected function _oc_need_refresh() {
		oc::push( 'coldiy' . TS, arr2str( [ '_need_refresh' ] ) );
		oc::push( 'coldiy_entries', TS );
	}

	/**
	 * Clean up expired OC chanegs
	 */
	private function cleanup_oc_changes() {
		$ts_list = oc::list2( 'coldiy_entries' );
		if ( ! $ts_list ) {
			return;
		}

		$start = 0;
		foreach ( $ts_list as $ts ) {
			if ( $ts > TS - 20 ) {

				break;
			}

			$start++;

			// Clear stored data
			oc::del( 'coldiy' . $ts );
		}

		// Clear entry
		$start > 0 && oc::ltrim( 'coldiy_entries', $start, -1 );
	}

	/**
	 * Load col changes via AJAX
	 */
	private function _coldiy_changes() {
		$ts = _get( 'ts' );
		if ( ! $ts ) {
			err( 'No timestamp' );
		}
		$changes = [];
		for ( $i=$ts; $i < TS; $i++ ) {
			if ( $this_list = oc::list2( 'coldiy' . $i ) ) {
				$changes = array_merge( $changes, $this_list );
			}
		}

		ok( [
			'ts' => TS,
			'list' => $changes,
		] );
	}

	/**
	 * Customize cols
	 */
	protected function Cols_diy() {
		if ( ! defined( 'static::COLS' ) ) {
			b( 'no cols can be set' );
		}

		$this->_load_cols_defined();

		// Defined cols
		$cols_defined_types = [];
		$admin_info = instance::cls( 'sys\admin' )->row_by_id( S[ 'acp_id' ] );
		$can_define_cols = in_array( P, str2arr( $admin_info[ 'cols_defines' ] ) );
		if ( $can_define_cols ) {
			foreach ( self::COLS_DEFINED_TYPES as $v ) {
				$cols_defined_types[] = [ 'v' => $v ];
			}
		}

		// Ajax load changes for existing cols
		if ( ACTION_STEP == 'changes' ) {
			$this->_coldiy_changes();
		}

		// Set col value
		if ( ACTION_STEP == 'set' ) {
			$fid = _get_post( 'fid' );
			if ( empty( $this->cols_defined[ $fid ] ) ) {
				b( 'No this column' );
			}

			if ( $this->cols_defined[ $fid ][ 'readonly' ] ) {
				b( 'Readonly column' );
			}

			$allowed_p = defined( 'static::LINKED_P' ) ? [ static::LINKED_P, P ] : [ P ]; // Allow child P to set parent P's col value
			if ( ! in_array( $this->cols_defined[ $fid ][ 'p' ], $allowed_p ) ) {
				b( 'menu err' );
			}

			$data_row = $this->_row();

			$curr_col_defined = $this->cols_defined[ $fid ];
			$ref_id = ID;
			$root_id = false;
			if ( $curr_col_defined[ 'type' ] == 'mirror' ) {
				if ( $curr_col_defined[ 'values' ][ 1 ] != 'id' ) {
					$root_id = $ref_id = $data_row[ $curr_col_defined[ 'values' ][ 1 ] ];
				}
				$curr_col_defined = $this->cols_defined_mirror[ $curr_col_defined[ 'values' ][ 0 ] ];
			}

			// Save menu data
			if ( SUBMIT ) {
				debug( S[ 'acp_id' ] . ' start submitting' );
				$val = _get_post( 'val' );

				$val = $this->set_col( ID, $fid, $val );
				debug( 'set_col done' );

				// Update main record's lastdateline
				db::u( static::TB, $this->ll(), ID );

				debug( 'json response' );

				// Update w/o refresh
				$el = _get_post( 'el' ) ?: 'td';
				json( [
					'id' => 'coldiy' . ID . '_' . $fid,
					'td' => $this->_final_col_looks( $fid, $val, $data_row, $el, true ),
					'root_id' => $root_id,
				] );
			}

			$existing_data = db::s( self::TB_MENU_DATA, [ 'ref_id' => $ref_id, 'menu_col_id' => $curr_col_defined[ 'id' ] ] );
			$existing_data = $existing_data[ 'val' ];

			// Show col value page
			$link = '/' . P . '/' . ID . '/' . ACTION . '?step=set&fid=' . $fid . '&submit=1&el=' . _get( 'el' );

			$col_list = [];
			if ( $curr_col_defined[ 'type' ] == 'dropdown' ) {
				$col_list[] = [
					'title' => false,
					'color' => false,
					'curr' => !$existing_data,
					'link' => $link . '&val=',
				];

				foreach ( str2arr( $curr_col_defined[ 'values' ] ) as $v ) {
					$color = ! empty( $v[ 1 ] ) ? $v[ 1 ] : false;
					$col_list[] = [
						'title' => $v[ 0 ],
						'color' => $color,
						'curr' => $v[ 0 ] == $existing_data,
						'link' => $link . '&val=' . urlencode( $v[ 0 ] ),
					];
				}
			}
			$assign_to_me = false;
			if ( $curr_col_defined[ 'type' ] == 'member' ) {
				foreach ( instance::cls( 'sys\admin' )->rows_by_tag( str2arr( $curr_col_defined[ 'values' ] ) ) as $v ) {
					$col_list[] = [
						'id' => $v[ 'id' ],
						'truename' => $v[ 'truename' ],
						'curr' => $v[ 'id' ] == $existing_data,
						'link' => $link . '&val=' . urlencode( $v[ 'id' ] ),
					];

					if ( $v[ 'id' ] == S[ 'acp_id' ] ) {
						$assign_to_me = $link . '&val=' . S[ 'acp_id' ];
					}
				}

				array_multisort( array_column( $col_list, 'truename' ), SORT_ASC, $col_list ); // Asc order
			}
			if ( $curr_col_defined[ 'type' ] == 'link' ) {
				$existing_data = $this->_try_revert_link( $existing_data );
			}

			if ( _get( 'json' ) || defined( 'AJAX' ) ) {
				json( [
					'col_list' => $col_list,
				] );
			}

			_header();
			$tpl = new t( 'cols_diy_set', _SYS );
			$tpl->assign( [
				'fid' => $fid,
				'data_row' => s::html( $data_row ),
				'existing_data' => s::html( $existing_data ),
				'col_list' => $col_list,
				'col_info' => $curr_col_defined,
				'assign_to_me' => $assign_to_me,
				'link' => $link,
				'el' => _get( 'el' ),
				'google_geo_key' => _cfg( 'google', 'apikey' ),
			] );
			$tpl->output();
			_footer();
		}

		// Show col add page
		if ( ACTION_STEP == 'add' ) {
			if ( ! $can_define_cols ) {
				exit( 'No access' );
			}

			if ( SUBMIT ) {
				$main_data = $this->_cols_diy_define_sanitize_post();

				if ( db::s( self::TB_MENU_COL, [ 'p' => P, 'active' => 1, 'title' => $main_data[ 'title' ] ] ) ) {
					b( 'Title existed' );
				}

				// Store menu_col
				$main_data[ 'p' ] = P;
				$main_data[ 'active' ] = 1;
				db::i( self::TB_MENU_COL, $main_data );

				j( '/' . P . '/' . ACTION . '?modal_parent=1&modal=1' );
			}

			$priority = db::s( [ 'MAX(priority) as priority', self::TB_MENU_COL ], [ 'p' => P ] );

			_header();
			$tpl = new t( 'cols_diy_add', _SYS );
			$tpl->assign( [
				'cols_defined_types' => $cols_defined_types,
				'priority' => $priority[ 'priority' ] + 1,
				'ts' => TS,
				'admin_tags' => instance::cls( 'sys\admin' )->build_admin_tags(),
				'mirror_list' => array_values( $this->_mirror_list() ),
				'disabled_admin_tags' => instance::cls( 'sys\admin' )->build_admin_tags(),
			] );
			$tpl->output();
			_footer();
		}

		// Show col edit page
		if ( ACTION_STEP == 'edit' ) {
			if ( ! $can_define_cols ) {
				exit( 'No access' );
			}

			if ( empty( $this->cols_defined[ ID ] ) ) {
				b( 'No record' );
			}

			$row = $this->cols_defined[ ID ];
			if ( $row[ 'p' ] != P ) {
				b( 'menu error' );
			}

			if ( SUBMIT ) {
				$main_data = $this->_cols_diy_define_sanitize_post();

				if ( db::s( self::TB_MENU_COL, [ 'p' => P, 'id' => [ '!=', ID ], 'active' => 1, 'title' => $main_data[ 'title' ] ] ) ) {
					b( 'Title existed' );
				}

				db::u( self::TB_MENU_COL, $main_data, ID );

				j( '/' . P . '/' . ACTION . '?modal_parent=1&modal=1' );
			}

			foreach ( $cols_defined_types as $k => $v ) {
				$cols_defined_types[ $k ][ 'curr' ] = $v[ 'v' ] == $row[ 'type' ];
			}

			$dropdown_list = [];
			if ( $row[ 'type' ] == 'dropdown' ) {
				$dropdown_list = s::html( str2arr( $row[ 'values' ] ) );
			}
			$row[ '_dropdown_list' ] = json_encode( is_array( $dropdown_list ) ? $dropdown_list : [] );

			$admin_tags = false;
			if ( $row[ 'type' ] == 'member' ) {
				$admin_tags = $row[ 'values' ];
			}

			$mirror_curr = false;
			$mirror_id = false;
			if ( $row[ 'type' ] == 'mirror' ) {
				$mirror_curr = $row[ 'values' ][ 0 ];
				$mirror_id = $row[ 'values' ][ 1 ];
			}

			_header();
			$tpl = new t( 'cols_diy_edit', _SYS );
			$tpl->assign( [
				'ts' => TS,
				'row' => $row,
				'cols_defined_types' => $cols_defined_types,
				'admin_tags' => instance::cls( 'sys\admin' )->build_admin_tags( $admin_tags ),
				'mirror_list' => array_values( $this->_mirror_list( $mirror_curr ) ),
				'mirror_id' => $mirror_id,
				'disabled_admin_tags' => instance::cls( 'sys\admin' )->build_admin_tags( $row[ 'disabled_admin_tag_ids' ] ),
			] );
			$tpl->output();
			_footer();
		}

		// Delete one customized col
		if ( ACTION_STEP == 'del' ) {
			if ( ! $can_define_cols ) {
				exit( 'No access' );
			}

			if ( empty( $this->cols_defined[ ID ] ) ) {
				b( 'No record' );
			}

			$row = $this->cols_defined[ ID ];
			if ( $row[ 'p' ] != P ) {
				b( 'menu error' );
			}

			db::u( self::TB_MENU_COL, [ 'active' => 0 ], ID );

			j( '/' . P . '/' . ACTION . '?modal_parent=1&modal=1' );
		}

		/**
		 * Customize personal cols to show on List page
		 */
		if ( ! ACTION_STEP ) {
			$default_cols = static::COLS;
			foreach ( $this->cols_defined_valid as $col ) {
				$default_cols[ $col ] = 1;
			}

			if ( SUBMIT ) {
				$cols = _post( 'cols' );
				$cols_hide = array_diff( array_keys( $default_cols ), $cols );
				$cols_hide = array_values( $cols_hide );

				db::r( self::TB_COLS_DIY, [ 'acp_id' => S[ 'acp_id' ], 'p' => P, 'cols_hide' => arr2str( $cols_hide ), 'cols_priority' => arr2str( $cols ) ] );

				jx();
			}

			// Default cols
			$cols_diy = db::s( self::TB_COLS_DIY, [ 'acp_id' => S[ 'acp_id' ], 'p' => P ] );
			$existing_cols_hide = str2arr( $cols_diy[ 'cols_hide' ] );
			$existing_cols_priority = str2arr( $cols_diy[ 'cols_priority' ] );

			$list_tmp = [];
			foreach ( $default_cols as $k => $v ) {
				$curr = $v;
				if ( $existing_cols_hide ) {
					$curr = ! in_array( $k, $existing_cols_hide );
				}

				$title = ucwords( $k );
				if ( ! empty( $this->cols_defined[ $k ] ) ) {
					$title = $this->cols_defined[ $k ][ 'title' ];
				}

				$list_tmp[ $k ] = [
					'title' => $title,
					'v' => $k,
					'curr' => $curr,
				];
			}
			// Prioritize col list
			$list = [];
			foreach ( $existing_cols_priority as $col ) {
				if ( empty( $list_tmp[ $col ] ) ) {
					continue;
				}
				$list[] = $list_tmp[ $col ];
				unset( $list_tmp[ $col ] );
			}
			$list = array_merge( $list, array_values( $list_tmp ) );

			$cols_defined = [];
			if ( $can_define_cols ) {
				foreach ( $this->cols_defined as $v ) {
					if ( $v[ 'p' ] != P ) { // bypass linked_p
						continue;
					}
					if ( $v[ 'type' ] == 'member' ) {
						$v[ '_member_groups' ] = array_values( instance::cls( 'sys\admin_tag' )->list_by_ids( str2arr( $v[ 'values' ] ) ) );
					}
					elseif ( $v[ 'type' ] == 'dropdown' ) {
						$v[ '_dropdown_list' ] = [];
						foreach ( str2arr( $v[ 'values' ] ) as $v2 ) {
							$color = ! empty( $v2[ 1 ] ) ? $v2[ 1 ] : false;
							$v[ '_dropdown_list' ][] = [
								'title' => $v2[ 0 ],
								'color' => $color,
							];
						}
					}
					elseif ( $v[ 'type' ] == 'mirror' ) {
						$mirror_list = $this->_mirror_list();
						$mirror = $mirror_list[ $v[ 'values' ][ 0 ] ];
						$v[ '_mirror' ] = ! empty( $mirror_list[ $v[ 'values' ][ 0 ] ] ) ? $mirror[ '_p' ] . ' => ' . $mirror[ 'title' ] : 'Invalid Record';
					}

					$v[ '_disabled_admin_tags' ] = [];
					foreach ( str2arr( $v[ 'disabled_admin_tag_ids' ] ) as $v2 ) {
						$v[ '_disabled_admin_tags' ][] = [
							'title' => instance::cls( 'sys\admin_tag' )->title( $v2 ),
						];
					}

					$cols_defined[] = $v;
				}
			}

			$data = [
				'list' => $list,
				'can_define_cols' => $can_define_cols,
				'cols_defined' => $cols_defined,
				'cols_defined_types' => $cols_defined_types,
			];

			_header();
			$tpl = new t( 'cols_diy', _SYS );
			$tpl->assign( $data );
			$tpl->output();
			_footer();
		}
	}

	/**
	 * Generate mirror-able list for column DIY
	 */
	private function _mirror_list( $curr = false ) {
		$mirror_list = [];
		$existing_mirror_candidates = db::msa( self::TB_MENU_COL, [ 'active' => 1, 'p' => [ '!=', P ], 'type' => [ '!=', 'mirror' ] ], 'id desc', false, false, 60 );
		foreach ( $existing_mirror_candidates as $v ) {
			list( $p0, $p1 ) = explode( '.', $v[ 'p' ] );
			if ( $p0 != P_ARR[ 0 ] ) {
				continue;
			}
			$v[ '_p' ] = ADMIN_MENU_P0[ $p0 ] . ' - ' . ADMIN_MENU_ORI[ $p0 ][ $p1 ];
			$v[ 'curr' ] = $v[ 'id' ] == $curr;
			$mirror_list[ $v[ 'id' ] ] = $v;
		}
		return s::html( $mirror_list );
	}

	/**
	 * Define cols submission data sanitize
	 */
	private function _cols_diy_define_sanitize_post() {
		$title = trim( _post( 'title' ) );
		$readonly = _post( 'readonly' ) % 2;
		$reverse_search = _post( 'reverse_search' ) % 2;
		$disabled_admin_tag_ids = _post( 'disabled_admin_tag_ids' );
		$priority = (int)_post( 'priority' );
		$type = _post( 'type' );
		$dropdown_vals = _post( 'dropdown_vals' );
		$dropdown_colors = _post( 'dropdown_colors' );
		$admin_tags = _post( 'admin_tags' );
		$mirror = _post( 'mirror' );

		if ( ! $title ) {
			b( 'no title' );
		}

		if ( ! in_array( $type, self::COLS_DEFINED_TYPES ) ) {
			b( 'wrong type' );
		}

		$value_list = false;

		if ( $type == 'dropdown' ) {
			$value_list = [];
			if ( $dropdown_vals ) foreach ( $dropdown_vals as $k => $v ) {
				if ( ! $v ) {
					continue;
				}
				$value_list[] = [ $v, ! empty( $dropdown_colors[ $k ] ) ? $dropdown_colors[ $k ] : false ];
			}
			if ( ! $value_list ) {
				b( 'No dropdown values' );
			}

			$value_list = arr2str( $value_list );
		}
		elseif ( $type == 'member' ) {
			if ( ! $admin_tags ) {
				b( 'No member group values' );
			}

			$value_list = arr2str( $admin_tags );
		}
		elseif ( $type == 'mirror' ) {
			if ( ! $mirror || ! array_key_exists( $mirror, $this->_mirror_list() ) ) {
				b( 'No such mirror-able column' );
			}

			$mirror_id = _post( 'mirror_id' );

			if ( ! $mirror_id || preg_match( '/\W/', $mirror_id ) ) {
				$mirror_id = 'id';
			}

			$value_list = arr2str( [ $mirror, $mirror_id ] );
		}

		return [
			'title' => $title,
			'type' => $type,
			'priority' => $priority,
			'values' => $value_list,
			'readonly' => $readonly,
			'reverse_search' => $reverse_search,
			'disabled_admin_tag_ids' => arr2str( $disabled_admin_tag_ids ),
		];
	}

}