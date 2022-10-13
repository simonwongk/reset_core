<?php
/**
 *	Template cls
 *
 *	@since Aug/22/2018 Auto mkdir for tpl folder
 */
if(!defined('IN')) exit('Access Denied');

class t{
	function __construct($tpl = '', $wwwTpl = false){//$wwwTpl 是否使用根目录模板文件夹
		if ( defined( 'TPL' ) && ! $tpl ) {
			$tpl = TPL;
		}
		$this->data = array();

		$this->tpl_file = ROOT."tpl/$tpl.html";
		if($wwwTpl === 1 && defined('WWW')) $this->tpl_file = WWW."tpl/$tpl.html";
		if($wwwTpl && $wwwTpl !== 1) $this->tpl_file = $wwwTpl."tpl/$tpl.html";

		if ( ! is_file( $this->tpl_file ) ) {
			exit( 'tpl file not exist: ' . substr( $this->tpl_file, strpos( $this->tpl_file, '/tpl/' ) + 4 ) );
		}

		$cpl_file_append = '' ;
		// if ( _cookie('lang') ) {
		// 	$cpl_file_append = '.' . _cookie('lang') ;
		// }
		if ( ! is_dir( '/tmp/tpl/' ) ) {
			mkdir( '/tmp/tpl/', 0755, true ) ;
		}

		$this->cpl_file = '/tmp/tpl/'.preg_replace('/[:\/.\\\\]/', '__', $this->tpl_file).$cpl_file_append.'.php';

		if( isset( $GLOBALS[ '_assign' ] ) ) {
			$this->assign( $GLOBALS[ '_assign' ] );
		}
	}

	function assign($name, $value = ''){
		if(is_array($name)) foreach($name as $k => $v) $this->data[$k] = $v;
		else $this->data[$name] = $value;
	}

	function output($return = false){
		$_top = $this->data;
		$_obj = &$_top;
		$_stack_cnt = 0;
		$_stack[$_stack_cnt++] = $_obj;

		//检查是否已编译
		if(!is_file($this->cpl_file) || filemtime($this->cpl_file) <= filemtime($this->tpl_file)){
			$this->parser = new TplParser($this->tpl_file);
			$this->parser->compile($this->cpl_file);
		}
		if($return) {
			ob_start();
			include $this->cpl_file;
			$info = @ob_get_clean();
			// $info = str_replace('	', '', $info);
			return $info;
		}else {
			include $this->cpl_file;
		}
	}
}

/**
 *	Parser
 */
class TplParser{
	function __construct($tpl){
		$this->template = file_get_contents($tpl);
	}
	function compile($compiled_template = ''){
		if(false === $this->template) {
			$this->error = "Read template file $this->template failed";
			return false;
		}

		//$this->template = preg_replace('/(<\?|<\?php)/ise',"htmlspecialchars('\\1');", $this->template);
		$this->template = preg_replace_callback('/(<\?|<\?php)/is', function ($m) {return htmlspecialchars($m[1]);}, $this->template);

		$page = "<?php defined('IN') || exit('Denied'); ?>\n" . $this->template;
		// END, ELSE
		$page = preg_replace("/<!-- ENDIF -->/", "<?php\n}\n?>", $page);
		$page = preg_replace("/<!-- END -->/", "<?php\n}\n\$_obj = \$_stack[--\$_stack_cnt];}\n?>", $page);
		$page = str_replace("<!-- ELSE -->", "<?php\n}else {\n?>", $page);
		// Language func
		$page = preg_replace( "/__\('([^']+)'\)/U", '<?=__(\'$1\')?>', $page );
		// 'BEGIN - END'
		if(preg_match_all('/<!-- BEGIN ([\w.,]+) -->/', $page, $var)) {
			foreach($var[1] as $tag) {
				list($parent, $block) = $this->var_name($tag);
				$code = "<?php\n"
						."\tif(!empty(\$$parent"."['$block'])) {\n"
						."\t\tif(!is_array(\$$parent"."['$block']))\n"
						."\t\t\t\$$parent"."['$block'] = array(array('$block' => \$$parent"."['$block']));\n"
						."\t\t\$_tmp_arr_keys = array_keys(\$$parent"."['$block']);\n"
						."\t\tif(\$_tmp_arr_keys[0] != '0')\n"
						."\t\t\t\$$parent"."['$block'] = array(0 => \$$parent"."['$block']);\n"
						."\t\t\$_stack[\$_stack_cnt++] = \$_obj;\n"
						."\t\tforeach(\$$parent"."['$block'] as \$rowcnt=>\$__tmp) {\n"
						."\t\t\t\$__tmp"."['I'] = \$rowcnt;\n"
						."\t\t\t\$__tmp"."['II'] = \$rowcnt+1;\n"
						."\t\t\t\$__tmp"."['III'] = \$rowcnt%7;\n";

				if ( isset( $GLOBALS[ '_assign' ] ) ) {
					foreach( $GLOBALS[ '_assign' ] as $k2 => $v2 ) {
						$code .= "\t\t\t\$__tmp"."[ '$k2' ] = @\$GLOBALS[ '_assign' ][ '$k2' ];\n";
					}
				}

				$code .= "\t\t\t\$__tmp"."['A'] = \$rowcnt % 2;\n"
						."\t\t\t\$__tmp"."['B'] = (\$rowcnt + 1) % 2;\n"
						."\t\t\t\$_obj = &\$__tmp;\n?>";
				$page = str_replace("<!-- BEGIN $tag -->", $code, $page);
			}
		}
		// 'IF nnn="mmm"'
		if(preg_match_all('/<!-- (ELSE)?IF ([\w.]+)([!=<>]+)"([^"]*)" -->/', $page, $var)) {
			foreach($var[2] as $cnt => $tag) {
				list($parent, $block) = $this->var_name($tag);
				$cmp  = $var[3][$cnt];
				$val  = $var[4][$cnt];
				$else = $var[1][$cnt] == 'ELSE' ? '}else' : '';
				if($cmp == '=') $cmp = '==';
				$code = "<?php\n$else"."if(\$$parent"."['$block'] $cmp \"$val\") {\n?>";
				$page = str_replace($var[0][$cnt], $code, $page);
			}
		}
		// 'IF nnn'
		if(preg_match_all('/<!-- (ELSE)?IF ([\w.]+) -->/', $page, $var)) {
			foreach($var[2] as $cnt => $tag) {
				$else = ($var[1][$cnt] == 'ELSE') ? '} else' : '';
				list($parent, $block) = $this->var_name($tag);
				$code = "<?php\n$else"."if(!empty(\$$parent"."['$block'])) {\n?>";
				$page = str_replace($var[0][$cnt], $code, $page);
			}
		}
		// 'IF !nnn'
		if(preg_match_all('/<!-- (ELSE)?IF !([\w.]+) -->/', $page, $var)) {
			foreach($var[2] as $cnt => $tag) {
				$else = ($var[1][$cnt] == 'ELSE') ? '} else' : '';
				list($parent, $block) = $this->var_name($tag);
				$code = "<?php\n$else"."if(empty(\$$parent"."['$block'])) {\n?>";
				$page = str_replace($var[0][$cnt], $code, $page);
			}
		}
		// Replace date var
		if(preg_match_all('/{(\w[\w.]*_DATE_[^\}]+)}/', $page, $var)) {
			foreach($var[1] as $tag) {
				list($block, $skalar) = $this->var_name($tag);
				preg_match('/([\w]+)_DATE_(.+)/', $skalar, $skalar2);
				$code = "<?php\necho \$$block"."['$skalar2[1]'] ? date('$skalar2[2]',\$$block"."['$skalar2[1]']):'';\n?>\n";
				$page = str_replace('{'.$tag.'}', $code, $page);
			}
		}
		// Replace var in language
		if(preg_match_all('/__\({(\w[\w.,]*)}\)/', $page, $var)) {
			foreach($var[1] as $tag){
				list($block, $skalar) = $this->var_name($tag);
				$code = "<?php\necho __(\$$block"."['$skalar']);\n?>\n";
				$page = str_replace('__({'.$tag.'})', $code, $page);
			}
		}
		// Replace var
		if(preg_match_all('/{(\w[\w.,]*)}/', $page, $var)) {
			foreach($var[1] as $tag){
				list($block, $skalar) = $this->var_name($tag);
				$code = "<?php\necho \$$block"."['$skalar'];\n?>\n";
				$page = str_replace('{'.$tag.'}', $code, $page);
			}
		}
		// Store Code to Temp Dir
		if($compiled_template){
			$folder = dirname( $compiled_template ) ;
			if ( ! is_dir( $folder ) ) {
				mkdir( $folder ) ;
			}
			f::write($compiled_template, $page);
		}else {
			return $page;
		}
	}
	function var_name($tag){
		$obj = '_obj';
		while(is_int(strpos($tag, '.'))) {
			list($parent, $tag) = explode('.', $tag, 2);
			$obj .= is_numeric($parent) ? "[$parent]" : "['$parent']";
		}
		return array($obj, $tag);
	}
}