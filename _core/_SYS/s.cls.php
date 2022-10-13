<?php
/**
 *	string handler
 *
 * @since  Dec/26/2020 `html()` default param changed to false
 * @since  Dec/26/2020
 *
 */
defined( 'IN' ) || exit;

class s {
	/**
	 * Auto link a text
	 */
	public static function auto_link( $text ) {
		$pattern = '/(((http[s]?:\/\/(.+(:.+)?@)?)|(www\.))[a-z0-9](([-a-z0-9]+\.)*\.[a-z]{2,})?\/?[a-z0-9.,_\/~#&=:;%+!?-]+)/is';
		$text = preg_replace($pattern, ' <a href="$1" target="_blank">$1</a>', $text);
		// fix URLs without protocols
		$text = preg_replace('/href="www/', 'href="https://www', $text);
		return $text;
	}

	/**
	 * Filter JS
	 */
	public static function strip_js( $content ) {
		return preg_replace( '/<script([^>]*)>(.*)<\/script>/isU', '', $content );
	}

	/**
	 * Drop Iframe
	 */
	public static function strip_iframe( $content ) {
		return preg_replace( '/<iframe([^>]*)>(.*)<\/iframe>/isU', '', $content );
	}

	//xml load sanitize
	public static function sanitizeXml($content) {
	  if (!$content) return '';
	  $invalid_characters = '/[^\x9\xa\x20-\xD7FF\xE000-\xFFFD]/';
	  return preg_replace($invalid_characters, '', $content);
	}

	//id2shortURL
	public static function num2code($id){
		global $_charArr, $_len;
		if(!$_charArr) {
			$_charArr = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
			$_len = strlen($_charArr);
		}
		$url = $id >= $_len ? self::num2code(floor($id/$_len)) : '';
		$url .= $_charArr[$id%$_len];
		return $url;
	}
	public static function code2num($url){
		global $_charArr, $_len;
		if(!$_charArr) {
			$_charArr = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
			$_len = strlen($_charArr);
		}
		$id = pow($_len, strlen($url)-1)*strpos($_charArr, substr($url, 0, 1));
		if(strlen($url) > 1) $id += self::code2num(substr($url, 1));
		return $id;
	}

	//parse bbcode
	public static function bbcode($str){
		$search = array (
			"~\[ul\]~",
			"~\[ol\]~",
			"~\n*\[li\]~",
			"~\[/li\]\n*~",
			"~\n*\[/ul\]\s{0,2}~",
			"~\n*\[/ol\]\s{0,2}~",

			"~\[/td\][^\[]*~",
			"~\[/tr\][^\[]*~",
			"~\[table\]~",
			"~\[tr\]~",
			"~\[td\]~",
			"~\[/table\]~",
			'~\[hr\]~',
			'~\[b\]~', '~\[/b\]~',
			'~\[i\]~', '~\[/i\]~',
			'~\[u\]~', '~\[/u\]~',
			'~\[left\]~', "~\[/left\]\s{0,2}~",
			'~\[center\]~', "~\[/center\]\s{0,2}~",
			'~\[right\]~', "~\[/right\]\s{0,2}~",
			'~\[size=(\d+)\]~isU', '~\[/size\]~',
			'~\[color=([^\]]+)\]~isU', '~\[/color\]~',
			'~\[font=([^\]]+)\]~isU', '~\[/font\]~',
			'~\[url=([^\]]+)\]~isU', '~\[/url\]~',
			'~\[youtube\]([^\[]+)\[/youtube\]~',
			'~\[iframe\]([^\[]+)\[/iframe\]~',
			'~\[mp4\]([^\[]+)\[/mp4\]~',
			"~\n~",
		);

		$replace = array (
			'<ul>',
			'<ol>',
			'<li>',
			'</li>',
			'</ul>',
			'</ol>',

			'</td>',
			'</tr>',
			'<table class="table table-bordered table-striped">',
			'<tr>',
			'<td>',
			'</table>',
			'<hr />',
			'<strong>', '</strong>',
			'<em>', '</em>',
			'<u>', '</u>',
			'<div>', '</div>',
			'<div style="display:inline-block;width:100%;text-align:center;">', "</div>",
			'<div style="display:inline-block;width:100%;text-align:right;">', '</div>',
			'<font size="$1">', '</font>',
			'<font color="$1">', '</font>',
			'<font face="$1">', '</font>',
			'<a href="$1" target="_blank">', '</a>',
			'<iframe id="ytplayer" type="text/html" width="640" height="360" src="https://www.youtube.com/embed/$1" frameborder="0" allowfullscreen></iframe>',
			'<iframe width="640" height="360" src="$1" frameborder="0" allowfullscreen></iframe>',
			'<video style="max-width:700px;"><source type="video/mp4" src="$1"></video>',
			'<br />',
		);
		return preg_replace($search, $replace, $str);
	}

	/**
	 *	XML parser
	 *
	 */
	public static function xml2arr($xml){
		$values = array();
		$index  = array();
		$array  = array();
		$parser = xml_parser_create();
		xml_parser_set_option($parser, XML_OPTION_SKIP_WHITE, 1);
		xml_parser_set_option($parser, XML_OPTION_CASE_FOLDING, 0);
		xml_parse_into_struct($parser, $xml, $values, $index);
		xml_parser_free($parser);
		$i = 0;
		$name = $values[$i]['tag'];
		$array[$name] = isset($values[$i]['attributes']) ? $values[$i]['attributes'] : '';
		$array[$name] = self::_xml2arr($values, $i);
		return $array;
	}

	private static function _xml2arr($values, &$i){
		$child = array();
		if(isset($values[$i]['value'])) array_push($child, $values[$i]['value']);
		while ($i++ < count($values)){
			switch ($values[$i]['type']){
				case 'cdata':
					array_push($child, $values[$i]['value']);
					break;
				case 'complete':
					$name = $values[$i]['tag'];
					if(!empty($name)){
						if(isset($child[$name])){
							if(!isset($child[$name][0])) $child[$name] = array($child[$name]);
							if(isset($values[$i]['attributes'])) $child[$name][] = $values[$i]['attributes'];
						}else{
							$child[$name]= !empty($values[$i]['value']) ? $values[$i]['value'] : '';
							if(isset($values[$i]['attributes'])) $child[$name] = $values[$i]['attributes'];
						}
					}
					break;
				case 'open':
					$name = $values[$i]['tag'];
					$size = isset($child[$name]) ? sizeof($child[$name]) : 0;
					$child[$name][$size] =  self::_xml2arr($values, $i);
					break;
				case 'close':
					return $child;
					break;
			}
		}
		return $child;
	}

	/**
	 *	code convert
	 *
	 */
	public static function conv($text, $from = 'utf-8', $to = 'gbk'){
		if(is_array($text)) foreach($text as $key => $val) $text[$key] = self::conv($val, $from, $to);
		else $text = mb_convert_encoding($text, $to, $from);
		return $text;
	}

	/**
	 *	sub str
	 *
	 */
	public static function rsubstr($string, $len, $add = 0){
		$str2 = mb_substr($string, 0, $len, 'utf-8');
		if($add != 0) $add ++;
		if(strlen($str2) < strlen($string) && $add != 0) {
			$add ++;
			$leftchars = floor(($len * 2 - self::len($str2)) / 2);
			if($leftchars > 0) $str2 .= "...";
		}
		Return $str2;
	}

	/**
	 *	strlen
	 *
	 */
	public static function len($string, $charNum = 0){
		if(!$charNum) return strlen(self::conv($string));
		return mb_strlen($string, 'utf-8');
	}

	/**
	 *	br
	 *
	 */
	public static function htmlBr($string){
		if(is_array($string)) foreach($string as $k => $v) $string[$k] = self::htmlBr($v);
		else $string = str_replace(array('  ', "\r"."\n", "\n", "\t"), array('&nbsp;&nbsp;', '<br />', '<br />', '&nbsp;&nbsp;&nbsp;&nbsp;'), $string);
		return $string;
	}

	/**
	 *	HTML
	 *
	 */
	public static function html( $string, $no_br = false, $noConv = [] ){
		if ( ! $string ) return $string;

		if ( ! is_array( $noConv ) ) $noConv = [ $noConv ];

		if ( is_array( $string ) ) {
			foreach( $string as $k => $v ) {
				if ( isInt( $k ) || ! in_array( $k, $noConv ) ) $string[ $k ] = self::html( $v, $no_br, $noConv );
			}
		}
		else {
			$string = str_replace( [ '<', '>', '"', "'", '\\' ], [ '&lt;', '&gt;', '&quot;', '&#039;', '&#092;' ], $string ); // No more replace '&', to '&amp;',
		}

		if ( ! is_array( $string ) && ! $no_br ) return self::htmlBr( $string );

		return $string;
	}

	/**
	 *	convert str to color
	 *
	 * CSS prepare 20 colors
	 *
	 */
	public static function color( $str, $style = false ) {
		if ( ! $str ) {
			return $str;
		}

		$num = hexdec( substr( md5( $str ), -3 ) ) % 60;

		if ( $style === true ) {
			return "<span class='badge badge_color$num'>$str</span>";
		}

		if ( $style === 'num' ) {
			return $num;
		}

		return "<font class='_color$num'>$str</font>";
	}

	/**
	 *	random
	 *
	 * @since  1.0 Oct/15/2017
	 * @param  int  $len  	 Length of string
	 * @param  int  $type    1-Number 2-LowerChar 4-UpperChar
	 *
	 */
	public static function rrand( $len, $type = 7 ) {
		mt_srand( ( double ) microtime() * 1000000 );

		switch( $type ) {
			case 0 :
				$charlist = '012';
				break;

			case 1 :
				$charlist = '0123456789';
				break;

			case 2 :
				$charlist = 'abcdefghijklmnopqrstuvwxyz';
				break;

			case 3 :
				$charlist = '0123456789abcdefghijklmnopqrstuvwxyz';
				break;

			case 4 :
				$charlist = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
				break;

			case 5 :
				$charlist = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
				break;

			case 6 :
				$charlist = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
				break;

			case 7 :
				$charlist = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
				break;

		}

		$str = '';

		$max = strlen( $charlist ) - 1;
		for( $i = 0; $i < $len; $i++ ) {
			$str .= $charlist[ mt_rand( 0, $max ) ];
		}

		return $str;
	}

	/**
	 *	encrypt
	 *
	 * NOTE: if use the result in url, need to add urlencode() for the returning data ( no urldecode needed for decrypt )
	 *
	 */
	const METHOD = 'aes-256-cbc';
	public static function encrypt($str) {
		$key = substr(hash('sha256', _SALT), 0, 32);
		$ivsize = openssl_cipher_iv_length(self::METHOD);
		$iv = openssl_random_pseudo_bytes($ivsize);
		$str_encrypt = openssl_encrypt(
			$str,
			self::METHOD,
			$key,
			OPENSSL_RAW_DATA,
			$iv
		);
		return base64_encode($iv.$str_encrypt);
	}

	public static function decrypt($str) {
		$str = base64_decode($str);
		$key = substr(hash('sha256', _SALT), 0, 32);
		$ivsize = openssl_cipher_iv_length(self::METHOD);
		$iv = substr($str, 0, $ivsize);
		$ciphertext = substr($str, $ivsize);

		try{
			$str_decrypt = openssl_decrypt(
				$ciphertext,
				self::METHOD,
				$key,
				OPENSSL_RAW_DATA,
				$iv
			);
		}catch (Exception $e){
			$str_decrypt = false;
		}
		return $str_decrypt;
	}

	/**
	 * Convert array to safe url
	 *
	 * @since  1.0
	 */
	public static function arr2url_encrypt( $arr ) {
		return urlencode( self::encrypt( arr2str( $arr ) ) );
	}

}
