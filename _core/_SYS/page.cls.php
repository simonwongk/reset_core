<?php
/**
 *	Pagination
 */
defined( 'IN' ) || exit;

class page {
	public static function gen( $total, $pagesize = false ) {
		$_cookie_per_page = _cookie( 'per_page' );
		$_per_page = _get( 'per_page' ) ?: ( $_cookie_per_page ?: 40 );
		if ( $_per_page > 1000 ) {
			$_per_page = 40;
		}
		if ( $_cookie_per_page != $_per_page ) {
			cookie( 'per_page', $_per_page, 864000 );
		}
		defined( 'PER_PAGE' ) || define( 'PER_PAGE', $_per_page );

		if ( ! $pagesize ) {
			$pagesize = (int) PER_PAGE;
		}

		$page_max = ceil( $total / $pagesize ) ?: 1;
		$curr = ! empty( $_GET[ '_page' ] ) ? intval( $_GET[ '_page' ] ) : 1;
		if ( $curr < 1 ) {
			$curr = 1;
		}
		if ( $curr > $page_max ) {
			$curr = $page_max;
		}

		$link = parse_url( $_SERVER[ 'REQUEST_URI' ] );
		if ( ! empty( $link[ 'query' ] ) ) {
			parse_str( $link[ 'query' ], $link[ 'query' ] );
		}

		$form_link = $_SERVER[ 'REQUEST_URI' ];
		if ( ! empty( $link[ 'query' ][ '_page' ] ) ) {
			$thislink = $link;
			unset( $thislink[ 'query' ][ '_page' ] );
			$thislink[ 'query' ] = http_build_query( $thislink[ 'query' ] );
			$form_link = http_build_url( $thislink );
		}

		$last_arrow = '<i class="fa fa-chevron-left"></i>';
		if ( $curr > 1 ) {
			$thislink = $link;
			$thislink[ 'query' ][ '_page' ] = $curr - 1;
			$thislink[ 'query' ] = http_build_query( $thislink[ 'query' ] );
			$thislink = http_build_url( $thislink );
			$last_arrow = '<a href="' . $thislink . '" data-page="last" title="Shortcut: Left arrow">' . $last_arrow . '</a>';
		}

		$next_arrow = '<i class="fa fa-chevron-right"></i>';
		if ( $curr < $page_max ) {
			$thislink = $link;
			$thislink[ 'query' ][ '_page' ] = $curr + 1;
			$thislink[ 'query' ] = http_build_query( $thislink[ 'query' ] );
			$thislink = http_build_url( $thislink );
			$next_arrow = '<a href="' . $thislink . '" data-page="next" title="Shortcut: Right arrow">' . $next_arrow . '</a>';
		}

		$content = '<div class="d-flex pagelink">
						<div class="arrows">' . $last_arrow . '</div>
						<form action="' . $form_link . '" method="get">
						<input type="text" name="_page" value="' . $curr . '" class="mx-3" noStyles />
						</form>
						<div class="arrows">' . $next_arrow . '</div>
						<span class="mx-3">Total of ' . $page_max . '</span>
					</div>';

		$limit = ( ( $curr - 1 ) * $pagesize ) . ', ' . $pagesize;

		return [
			'limit' => $limit,
			'output' => $content,
		];
	}

	private static $total = 0;
	private static $pvar = '';
	private static $psize = 0;
	private static $pageMax = 0;
	private static $rewrite = 0;
	private static $varstr = '';
	private static $output = '';
	private static $curr = 0;
	private static $display = array();
	private static $OriginalUrl = '';

	private static function show( $total, $pagesize = 20, $rewrite = 0, $current = false, $param = false, $pvar = '_page' ) {
		self::$total = $total;
		self::$pvar = $pvar;
		self::$psize = $pagesize;
		self::$pageMax = ceil($total / $pagesize);//总页数
		self::$rewrite = $rewrite;
		self::$varstr = '';
		self::setVar($param);
		self::$output = '';
		self::$curr = $current;
		if(!self::$curr) self::$curr = empty($_GET[self::$pvar]) ? 1 : intval($_GET[self::$pvar]);
		if(self::$curr > self::$pageMax) self::$curr = self::$pageMax;
		if(self::$curr < 1) self::$curr = 1;
		self::$display = array(
			'start' => '|&lt;',
			'prev10' => '&lt;&lt;',
			'prev' => '&lt;',
			'next' => '&gt;',
			'next10' => '&gt;&gt;',
			'end' => self::$pageMax.'&gt;|',
		);
		self::$OriginalUrl = self::GetUrl();

		if($current) return self::output(1);
		$output = array();
		$output['output'] = self::output(1);
		$output['limit'] = self::limit();
		return $output;
	}

	/**
	 *	除$_GET外追加变量的设置
	 *
	 */
	private static function setVar($data){
		if(empty($data) || !is_array($data)) Return;
		foreach($data as $k => $v) {
			if(!empty($k)) self::$varstr .= $k.'='.urlencode($v).'&';
			else self::$varstr .= $v.'&';
		}
	}

	/**
	 *	输出
	 *
	 */
	private static function output($return = false){
		if(self::$pageMax > 1) {
			$start = floor(self::$curr / 10) * 10;
			$end = $start + 9;
			if($start < 1) $start = 1;
			if($end > self::$pageMax) $end = self::$pageMax;

			self::$output .= '';
			if(self::$curr >1) self::$output .= self::HTMLTagA(self::FormatUrl(1), self::$display['start']);
			if(self::$curr >=10) self::$output .= self::HTMLTagA(self::FormatUrl(self::$curr - 10), self::$display['prev10']);
			if(self::$curr >1) self::$output .= self::HTMLTagA(self::FormatUrl(self::$curr - 1), self::$display['prev'], false, 'last');

			for($i = $start; $i <= $end; $i++) {
				self::$output .= self::HTMLTagA(self::FormatUrl($i), $i, self::$curr == $i);
			}

			if(self::$curr < self::$pageMax) self::$output .= self::HTMLTagA(self::FormatUrl(self::$curr+1), self::$display['next'], false, 'next');
			if(self::$pageMax >=10 && self::$pageMax - $start >= 9) {
				$next10 = self::$pageMax-self::$curr>=10 ? self::$curr+10 : self::$pageMax;
				self::$output .= self::HTMLTagA(self::FormatUrl($next10), self::$display['next10']);
			}

			if(self::$curr<self::$pageMax) self::$output .= self::HTMLTagA(self::FormatUrl(self::$pageMax), self::$display['end']);

			self::$output .= '';
		}

		if($return) return self::$output;
		else echo self::$output;
	}

	private static function limit(){
		return ( ( self::$curr - 1 ) * self::$psize ) . ', ' . self::$psize ;
	}

	private static function GetUrl(){
		if(!empty($_SERVER['REDIRECT_URL']) && substr($_SERVER['REDIRECT_URL'], -4) == '.php') self::$rewrite = 0;
		$BrowserUrl= !empty($_SERVER['HTTP_X_REWRITE_URL']) ? $_SERVER['HTTP_X_REWRITE_URL'] : $_SERVER['REQUEST_URI'];
		//含ZPageChar的自定义页码
		if(self::$rewrite) Return str_replace('ZPageChar', '_ZPageChar', self::$rewrite);//追加_以便第一页不带_
		//未重写的用PHP_SELF+QUERY_STRING判断
		parse_str($_SERVER['QUERY_STRING'], $QUERY_STRING);
		self::setVar($QUERY_STRING);

		$WholeUrl = '//'.$_SERVER['SERVER_NAME'].$_SERVER['PHP_SELF'].'?'.self::$varstr;

		$pos = strpos($WholeUrl, self::$pvar.'='.self::$curr.'&');//检查是否已含分页

		if(false === $pos) $OriginalUrl = $WholeUrl.self::$pvar.'=ZPageChar&';
		else $OriginalUrl = str_replace(self::$pvar.'='.self::$curr.'&', self::$pvar.'=ZPageChar&', $WholeUrl);

		return substr($OriginalUrl, 0 ,-1);//去掉末尾&
	}

	private static function FormatUrl($num){
		if($num == 1) return str_replace(['_ZPageChar', 'ZPageChar'], '', self::$OriginalUrl);
		return str_replace('ZPageChar', $num, self::$OriginalUrl);
	}

	private static function HTMLTagA( $href, $text, $style = false, $lastOrNext = false ) {
		$lastOrNextAttr = $lastOrNext &&0 ? "data-page='$lastOrNext'" : '' ;
		return "<li class='page-item" . ( $style ? ' active' : '' ) . "'><a href='$href' class='page-link' $lastOrNextAttr>$text</a></li>" ;
	}
}

