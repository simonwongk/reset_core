<?php
/**
 *	Translation management
 */
/**

CREATE TABLE `lang` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL DEFAULT '',
  `lang_zh` varchar(255) NOT NULL DEFAULT '',
  `dateline` int(11) DEFAULT NULL,
  `lastacp_id` int(11) DEFAULT NULL,
  `lastacp_truename` varchar(255) DEFAULT NULL,
  `lastdateline` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `title` (`title`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

 */
defined( 'IN' ) || exit;

class lang extends instance {
	const TB = 'acp.lang';
	const MODAL_TPL = true;
	const NO_COL_ACTIVE = true;
	const EXTRA_FIELDS = [ 'lang_zh' ];
	const SEARCH_COLS = [ 'title', 'lang_zh' ];

	/**
	 * Generate JS func
	 */
	public static function build_js_lang_func() {
		require_once _SYS . 'lang.lib.php';
		$_lang_set = array_merge( LANG_SET, __build_lang_set() );

		$_js_vars = 'const LANG = "' . LANG . '";' . PHP_EOL;
		$_js_vars .= 'function __(title){
			var lang_set = ' . json_encode( $_lang_set ) . ';
			if( lang_set.hasOwnProperty( title.toLowerCase() ) ) return LANG == "en" || !lang_set[title.toLowerCase()] ? title : lang_set[title.toLowerCase()];
			console.error("Translation missing [string] " + title);
			$("footer").before("<div class=\'w-100 text-warning pl-3\'>Missing translation:<code>"+title+"</code></div>");
			return title;
		}';
		return $_js_vars;
	}

	protected function Delete() {
		$row = $this->_row();
		db::d( static::TB, ID );
		jx();
	}
}