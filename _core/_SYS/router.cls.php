<?php
/**
 * @since  Jun/24/2019 et.oa navbar changed to dropdown menu
 */
defined( 'IN' ) || exit;

global $_last;
if ( empty( $_last ) ) {
	$_last = [];
}

/**

CREATE TABLE `log_activity` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `acp_id` int(11) NOT NULL,
  `acp_truename` varchar(255) NOT NULL,
  `p0` varchar(255) NOT NULL,
  `p1` varchar(255) NOT NULL,
  `action` varchar(255) NOT NULL,
  `submit` tinyint(4) DEFAULT NULL,
  `isget` tinyint(4) DEFAULT NULL,
  `info` text NOT NULL,
  `dateline` int(11) NOT NULL,
  `ip` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `action` (`action`),
  KEY `acp_id` (`acp_id`),
  KEY `acp_name` (`acp_truename`),
  KEY `submit` (`submit`),
  KEY `p0` (`p0`),
  KEY `p1` (`p1`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='control panel operation logs';

 */

class router extends instance {
	private $_ignoreLog = [];

	const TB_LOG = 'log.log_activity';

	/**
	 * 菜單和權限初始化
	 */
	public function init() {
		ini_set( 'display_errors', true );
		ini_set( 'display_startup_errors', true );

		require ROOT . 'inc/menu.cfg.php';

		// Filter category
		$_menuDetail = ADMIN_MENU_ORI;
		foreach( $_menuDetail as $p0 => $p1Arr ) {
			if ( ! self::_acpPriv( $p0 ) ) { // No category access
				unset( $_menuDetail[ $p0 ] );
				continue;
			}
			foreach( $p1Arr as $p1 => $title ) {
				if ( ! self::_acpPriv( "$p0.$p1" ) ) unset( $_menuDetail[ $p0 ][ $p1 ] );
			}
		}
		define( 'ADMIN_MENUS', $_menuDetail );

		$_p = explode( '.', _get_post( 'p' ) );
		$need_redirect = false;
		if ( empty( $_p[ 0 ] ) ) {
			$_p[ 0 ] = array_keys( ADMIN_MENUS )[ 0 ];
			$need_redirect = true;
		}
		if ( empty( ADMIN_MENUS[ $_p[ 0 ] ] ) ) exit( 'Empty menu:' . $_p[ 0 ] );
		if ( empty( $_p[ 1 ] ) ) {
			$_p[ 1 ] = array_keys( ADMIN_MENUS[ $_p[ 0 ] ] )[ 0 ];
			$need_redirect = true;
		}
		if ( empty( $_p[ 1 ] ) || empty( ADMIN_MENUS[ $_p[ 0 ] ][ $_p[ 1 ] ] ) ) exit( 'Wrong menu' );

		! defined( 'P_ARR' ) && define( 'P_ARR', $_p );
		! defined( 'P' ) && define( 'P', implode( '.', P_ARR ) );

		if ( $need_redirect ) {
			j( '/' . P );
		}

		// 获得包含的子文件
		$pFile = P_ARR[ 0 ] . '\\' . P_ARR[ 1 ];
		// $pFile = ROOT . "$pFile.php";
		if ( ! method_exists( $pFile, 'handler' ) ) {
			exit( 'In dev: ' . $pFile );
		}

		global $_assign;
		$_assign[ 'P_ARR' ] = P_ARR;
		$_assign[ 'p' ] = P;
		$_assign[ '_url' ] = '/' . P;
		$is_easysubmit = _get_post( 'modal' ) ? '' : 'data-easysubmit';
		$_assign[ '_form' ] = '<form action="/' . P . '" method="post" class="form-horizontal" enctype="multipart/form-data" ' . $is_easysubmit . '>';
		// No action
		$_assign[ '_form_native' ] = '<form action="/' . P . '" method="post" class="form-horizontal" enctype="multipart/form-data">';
		// No page
		$_assign[ '_form_search' ] = '<form action="/' . P . '" method="get"><input name="s00" value="1" type="hidden" />';
		if ( $_page = _get( '_page' ) ) {
			$_assign[ '_url' ] .= '?_page=' . $_page;
			if ( ACTION != 'add' ) {
				$_assign[ '_form' ] .= '<input type="hidden" name="_page" value="' . $_page . '" />';
				$_assign[ '_form_native' ] .= '<input type="hidden" name="_page" value="' . $_page . '" />';
			}
		}
		if ( ACTION ) {
			switch ( ACTION ) {
				case 'add':
					$form_action = 'insert';
					break;

				case 'edit':
					$form_action = 'update';
					break;

				default:
					$form_action = ACTION;
					break;
			}
			$_assign[ '_form' ] .= '<input type="hidden" name="action" value="' . $form_action . '" />';
		}
		if ( ID ) {
			$_assign[ '_form' ] .= '<input type="hidden" name="id" value="' . ID . '" />';
			$_assign[ '_form_native' ] .= '<input type="hidden" name="id" value="' . ID . '" />';
		}

		if ( _get_post( 'modal' ) == 1 || ( ! empty( $_SERVER[ 'HTTP_REFERER' ] ) && strpos( $_SERVER[ 'HTTP_REFERER' ], 'modal=1' ) && _get_post( 'modal' ) != -1 ) ) {
			$_assign[ '_url' ] .= ( strpos( $_assign[ '_url' ], '?' ) === false ? '?' : '&' ) . 'modal=1';
			$_assign['_form'] .= '<input type="hidden" name="modal" value="1" />';
			$_assign['_form_native'] .= '<input type="hidden" name="modal" value="1" />';
		}
		$_assign[ '_form' ] .= '<input type="hidden" name="submit2" value="true" />';

		$_assign[ '_cols_diy' ] = '<a href="/' . P . '/cols_diy" target="modal" accesskey="W"><i class="fa fa-table"></i></a>';

		if ( ( ACTION == 'add' || ACTION == 'insert' ) && _get_post( 'goon' ) ) {
			define( 'GOON', true );
			$_assign[ 'goon' ] = true;
		}

		// Log always
		$tmp = $_GET ?: $_POST;
		unset( $tmp[ 'p' ], $tmp[ '_page' ] );
		$logit = true;
		if ( ! ACTION ) {
			$logit = false;
		}
		elseif ( in_array( [ P_ARR[ 0 ], P_ARR[ 1 ], ACTION ], $this->_ignoreLog ) ) {
			$logit = false;
		}
		elseif ( in_array( ACTION, [ 'view', 'detail' ] ) ) {
			$logit = false;
		}
		elseif ( ACTION == 'cols_diy' && ! SUBMIT && ACTION_STEP != 'del' ) {
			$logit = false;
		}
		if ( $tmp && $logit ) {
			$s = [
				'acp_id' => S[ 'acp_id' ],
				'acp_truename' => S[ 'acp_truename' ],
				'p0' => P_ARR[ 0 ],
				'p1' => P_ARR[ 1 ],
				'action' => ACTION,
				'submit' => $GLOBALS[ 'submit' ] ? 1 : 0,
				'isget' => !empty( $_GET ) ? 1 : 0,
				'info' => arr2str( $tmp ),
				'dateline' => TS,
				'ip'	=> IP,
			];
			db::i( self::TB_LOG, $s );
		}

		//定义使用的模板
		$tpl_p1 = P_ARR[ 1 ];
		if ( strpos( $tpl_p1 , '__') ) {
			$tpl_p1 = substr( $tpl_p1, 0, strpos( $tpl_p1, '__' ) );
		}
		if ( ACTION ) {
			$tpl_p1 .= '.' . ACTION;

			if ( ACTION_STEP ) {
				$tpl_p1 .= '.' . ACTION_STEP;
			}
		}
		define( "TPL", P_ARR[ 0 ] . '/' . $tpl_p1 );

		$this->cls( $pFile )->handler();
	}

	/**
	 *	后台权限
	 *
	 */
	private static function _acpPriv( $priv ) {
		if ( ! S ) return false;
		if ( S[ 'root' ] ) return true;
		if ( ! in_array( $priv, S[ 'privilege' ] ) ) return false;
		return true;
	}

	/**
	 *	页头输出
	 *
	 */
	public function tpl_header( $title = '', $menuStyle = 1, $appendArr = [] ) {
		global $_outURLMenu;
		$noNav = 0;
		if ( _get( 'modal' ) == 1 || ( ! empty( $_SERVER[ 'HTTP_REFERER' ] ) && strpos( $_SERVER[ 'HTTP_REFERER' ], 'modal=1' ) && _get( 'modal' ) != -1 ) ) $title = 1;//modal里调用header时不需要用_header(1)了
		if ( $title == 1 || $menuStyle == 0 ) $noNav = 1;//兼容全局b()函数
		//二次输出检查
		if ( defined( 'HEADER_SENDED' ) ) return;
		else define( 'HEADER_SENDED', 1 );

		$_p = defined( 'P_ARR' ) ? P_ARR : [];
		! defined( 'ADMIN_MENU_P0' ) && define( 'ADMIN_MENU_P0', [] );
		! defined( 'ADMIN_MENUS' ) && define( 'ADMIN_MENUS', [] );

		if ( $title ) {
			$title .= ' ';
		}
		$cat_title = ! empty( $_p[ 0 ] ) && ! empty( ADMIN_MENU_P0[ $_p[ 0 ] ] ) ? ADMIN_MENU_P0[ $_p[ 0 ] ] : '';
		$title .= $cat_title;

		$cat_list = [];
		$menu_list = [];
		if ( ! $noNav ) {
			if ( ADMIN_MENU_P0 ) foreach( ADMIN_MENU_P0 as $k => $v ) {
				if ( ! self::_acpPriv( $k ) ) continue;

				$this_menu_list = [];
				$dropdown_mapping_key = [];

				if ( ! empty( ADMIN_MENUS[ $k ] ) ) foreach( ADMIN_MENUS[ $k ] as $k2 => $v2 ) {
					if ( ! self::_acpPriv( "$k.$k2" ) ) continue;

					$curr = $k == $_p[ 0 ] && $k2 == $_p[ 1 ];
					if ( $curr ) {
						$title = $v2 . ' - ' . $title;
					}

					$this_menu_item = [
						'p0'		=> $k,
						'p1'		=> $k2,
						'title'		=> $v2,
						'curr'	=> $curr,
					];

					if ( empty( ADMIN_MENU_DROPDOWN[ "$k.$k2" ] ) ) {
						$this_menu_list[] = $this_menu_item;
					}
					else {
						$dropdown_title = ADMIN_MENU_DROPDOWN[ "$k.$k2" ];
						$is_first_item = false;
						if ( empty( $dropdown_mapping_key[ $dropdown_title ] ) ) {
							$dropdown_mapping_key[ $dropdown_title ] = count( $this_menu_list ); // The dropdown index key in list

							// Add parent menu
							$this_menu_list[ $dropdown_mapping_key[ $dropdown_title ] ] = [
								'p0'		=> $k,
								'p1'		=> $k2,
								// 'title'		=> $v2,
								'has_dropdown'	=> true,
								'title'		=> $dropdown_title,
								'dropdown_list' => [],
								'curr'	=> false,
							];

							$is_first_item = true;
						}

						// Set the parent menu curr highlight
						if ( $curr ) {
							$this_menu_list[ $dropdown_mapping_key[ $dropdown_title ] ][ $is_first_item ? 'curr' : 'curr2' ] = true;
						}

						// Add child menu
						$this_menu_list[ $dropdown_mapping_key[ $dropdown_title ] ][ 'dropdown_list' ][] = $this_menu_item;
					}
				}

				if ( $k == $_p[ 0 ] ) {
					$menu_list = $this_menu_list;
				}

				$cat_list[] = [
					'p0'		=> $k,
					'title'		=> $v,
					'menu_list'	=> $this_menu_list,
					'outURL'	=> ! empty( $_outURLMenu[$k] ) ? $_outURLMenu[$k] : '',
					'curr'	=> $k == $_p[ 0 ],
				];
			}
		}

		$_js_vars = 'const p = "' . ( defined( 'P' ) ? P : '' ) . '";' . PHP_EOL;
		$_js_vars .= 'var latest_ts = "' . TS . '";' . PHP_EOL;
		if ( _get( 'modal_parent' ) ) {
			$_js_vars .= 'window.parent.modal_parent = true;' . PHP_EOL;
		}
		if ( $_anchor = _get( '_anchor' ) ) {
			$_js_vars .= 'window.parent._anchor = "' . $_anchor . '";' . PHP_EOL;
		}
		$_js_vars .= lang::build_js_lang_func() . PHP_EOL;

		$tpl = new t( 'header' );
		$tpl->assign( [
			'_js_vars' => $_js_vars,
			'nav'	=> ! $noNav,
			'title'	=> strip_tags( $title ),
			'cat_title'	=> $cat_title,
			'menu_list'	=> $menu_list,
			'cat_list'	=> $cat_list,
			// '_sid'	=> SID,
			'p'		=> ! empty( $_p ) ? implode( '.', $_p ) : '',
			'env' => _ENV,
			'avatar' => defined( 'S' ) && file_exists( ROOT . 'upload/avatars/' . S[ 'acp_id' ] . '.png' ) ? S[ 'acp_id' ] . '.png?ver=' . filemtime( ROOT . 'upload/avatars/' . S[ 'acp_id' ] . '.png' ) : false,
		] );
		if ( $appendArr ) $tpl->assign( $appendArr );
		$tpl->output();
	}

	public function tpl_footer($noNav = 0){
		global $starttime;
		if(defined('NOSTYLE')) $noNav = 1;
		if( _get('modal') == 1 || ( ! empty( $_SERVER[ 'HTTP_REFERER' ] ) && strpos( $_SERVER[ 'HTTP_REFERER' ], 'modal=1' ) && _get( 'modal' ) != -1 ) ) $noNav == 1;//modal里调用时不需要用_footer(1)了

		$www = false;
		if ( ! file_exists( ROOT . 'tpl/footer.html' ) ) {
			$www = _SYS;
		}

		$tpl = new t( 'footer', $www );
		$totaltime = number_format(microtime(1)-$starttime, 3);

		$tpl->assign([
			'nav'	=> !$noNav,
			'C_totaltime'  => $totaltime,
			'C_dbcount'    => db::num(true),
			'PER_PAGE' => defined( 'PER_PAGE' ) ? PER_PAGE : false,
			'LANG' => LANG,
		]);
		$tpl->output();

		exit();
	}

	//獲取圖標
	public static function icon($ext){
		if(in_array($ext, array('pdf', 'indd'))) return $ext;
		if(in_array($ext, array('jpg', 'jpeg', 'png'))) return 'image';
		if(in_array($ext, array('doc', 'docx'))) return 'word';
		return 'generic';
	}

	public static function usStates(){
		return array('AL'=>"Alabama",
			'AK'=>"Alaska",
			'AZ'=>"Arizona",
			'AR'=>"Arkansas",
			'CA'=>"California",
			'CO'=>"Colorado",
			'CT'=>"Connecticut",
			'DE'=>"Delaware",
			'DC'=>"District Of Columbia",
			'FL'=>"Florida",
			'GA'=>"Georgia",
			'HI'=>"Hawaii",
			'ID'=>"Idaho",
			'IL'=>"Illinois",
			'IN'=>"Indiana",
			'IA'=>"Iowa",
			'KS'=>"Kansas",
			'KY'=>"Kentucky",
			'LA'=>"Louisiana",
			'ME'=>"Maine",
			'MD'=>"Maryland",
			'MA'=>"Massachusetts",
			'MI'=>"Michigan",
			'MN'=>"Minnesota",
			'MS'=>"Mississippi",
			'MO'=>"Missouri",
			'MT'=>"Montana",
			'NE'=>"Nebraska",
			'NV'=>"Nevada",
			'NH'=>"New Hampshire",
			'NJ'=>"New Jersey",
			'NM'=>"New Mexico",
			'NY'=>"New York",
			'NC'=>"North Carolina",
			'ND'=>"North Dakota",
			'OH'=>"Ohio",
			'OK'=>"Oklahoma",
			'OR'=>"Oregon",
			'PA'=>"Pennsylvania",
			'RI'=>"Rhode Island",
			'SC'=>"South Carolina",
			'SD'=>"South Dakota",
			'TN'=>"Tennessee",
			'TX'=>"Texas",
			'UT'=>"Utah",
			'VT'=>"Vermont",
			'VA'=>"Virginia",
			'WA'=>"Washington",
			'WV'=>"West Virginia",
			'WI'=>"Wisconsin",
			'WY'=>"Wyoming"
		);
	}

	// 索引提交solr
	public static function solr($id){
		$news = db::s(['a.*,b.content', 'kny.art a left join kny.artCon b on b.art_id=a.id'], ['a.active'=>5, $id]);
		if(!$news){// 删除
			$json = ['delete' => ['id' => $id]];
		}else{
			$con = $news['content'];
			$con = self::imgParse(s::bbcode(s::html($con, 1)));
			$con = trim(strip_tags($con));
			$con = str_replace(["\r", "\n"], '', $con);
			$data = [
				'id'	=> $news['id'],
				'title'	=> strip_tags($news['title']),
				'content'	=> $con,
			];
			$json = ['add' => ['doc' => $data]];
		}
		$res = f::post('http://'._cfg('solr', 'host').':8983/solr/kny/update', $json, true );//?commit=true
		return $res;
	}

	// CMS 内容图片解析
	public static function imgParse($con, $folder = 'news'){
		preg_match_all('~\[(image|gif|png)\]((\d+)(\|(.*))?)\[/(image|gif|png)\]~isU', $con, $match);
		$_domain = '/file';
		if(defined('IMG_S3')) $_domain = IMG_S3;// 对于admin/admin2之类的特殊路径处理
		$_domain .= '/'.$folder;
		if(!empty($match[3])) foreach($match[3] as $key=>$val){
			$tmp = $key == 0 ? 'data-fragment="lead-image"' : '';
			$from = !empty($match[5][$key]) ? '来源:'.$match[5][$key] : '';
			if(defined('IN_ACP')) $from .= " ID:$val";
			$postfix = $match[1][$key] == 'image' ? 'jpg' : $match[1][$key];
			$con = str_replace($match[0][$key], "<img src='$_domain/".self::imgPath($val).".$postfix' title='$from' $tmp rel='nofollow' />", $con);
		}
		return $con;
	}

	// 图片路径末尾带上ID
	public static function imgPath($id){
		$path = '';
		for($i=0; $i<strlen($id); $i+=2){
			$currP = substr($id, 0, $i+2);
			if(strlen($currP) != $i+2) $currP .= '0';
			$path .= $currP."/";
		}
		return $path.$id;
	}

	//titleSEO
	public static function titleSEO($title){
		$title = strip_tags($title);
		$title = stripslashes($title);
		$title = str_replace(array(
				'《','》','，','。','！','？','——','(',')','（','）','：','\'','"', '〝', '〞', '%', '、', '“', '”', '…',
				'&','?','@','!','#','-','/',':','~',',',';', ' ', '　', '|', '^', "\n", "\t", '／', '；', '*', '+', '='
			),' ', $title);
		$title = preg_replace('~ +~', ' ', $title);
		$title = trim($title);
		$title = str_replace(' ', '-', $title);
		$title = strtolower($title);
		$title = s::rsubstr($title, 30);
		if(!$title) $title = '-';
		return $title;
	}

	//繁体转简体
	/*
		s2t.json 简体到繁体
		t2s.json 繁体到简体
		s2tw.json 简体到台湾正体
		tw2s.json 台湾正体到简体
		s2hk.json 简体到香港繁体（香港小学学习字词表标准）
		hk2s.json 香港繁体（香港小学学习字词表标准）到简体
		s2twp.json 简体到繁体（台湾正体标准）并转换为台湾常用词汇
		tw2sp.json 繁体（台湾正体标准）到简体并转换为中国大陆常用词汇
	*/
	public static function zt2jt($txt){
		$od = opencc_open('t2s.json');
		$txt = opencc_convert($txt, $od);
		//opencc_error();
		opencc_close($od);

		return $txt;
	}
	public static function jt2zt($txt){
		$od = opencc_open('s2t.json');
		$txt = opencc_convert($txt, $od);
		//opencc_error();
		opencc_close($od);

		return $txt;
	}
}
