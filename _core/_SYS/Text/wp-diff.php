<?php
/**
 * WordPress Diff bastard child of old MediaWiki Diff Formatter.
 *
 * Basically all that remains is the table structure and some method names.
 *
 * @package WordPress
 * @subpackage Diff
 */

if ( ! class_exists( 'Text_Diff', false ) ) {
	/** Text_Diff class */
	require dirname(__FILE__) . '/Diff.php';
	/** Text_Diff_Renderer class */
	require dirname(__FILE__) . '/Diff/Renderer.php';
	/** Text_Diff_Renderer_inline class */
	require dirname(__FILE__) . '/Diff/Renderer/inline.php';
}

require dirname(__FILE__) . '/class-wp-text-diff-renderer-table.php';
require dirname(__FILE__) . '/class-wp-text-diff-renderer-inline.php';

function wp_text_diff( $left_string, $right_string, $is_split_view = false ) {
	$left_string  = normalize_whitespace( $left_string );
	$right_string = normalize_whitespace( $right_string );

	$left_lines  = explode( "\n", $left_string );
	$right_lines = explode( "\n", $right_string );
	$text_diff   = new Text_Diff( $left_lines, $right_lines );
	$args = [
		'title'           => 'Difference',
		'title_left'      => 'Before',
		'title_right'     => 'After',
		'show_split_view' => true,
	];
	$renderer    = new WP_Text_Diff_Renderer_Table( $args );
	$diff        = $renderer->render( $text_diff );

	if ( ! $diff ) {
		return '';
	}

	$is_split_view_class = $is_split_view ? ' is-split-view' : '';

	$r = "<table class='diff$is_split_view_class'>\n";

	if ( $args['title'] ) {
		$r .= "<caption class='diff-title'>$args[title]</caption>\n";
	}

	if ( $args['title_left'] || $args['title_right'] ) {
		$r .= '<thead>';
	}

	if ( $args['title_left'] || $args['title_right'] ) {
		$th_or_td_left  = empty( $args['title_left'] ) ? 'td' : 'th';
		$th_or_td_right = empty( $args['title_right'] ) ? 'td' : 'th';

		$r .= "<tr class='diff-sub-title'>\n";
		$r .= "\t<$th_or_td_left>$args[title_left]</$th_or_td_left>\n";
		if ( $is_split_view ) {
			$r .= "\t<$th_or_td_right>$args[title_right]</$th_or_td_right>\n";
		}
		$r .= "</tr>\n";
	}

	if ( $args['title_left'] || $args['title_right'] ) {
		$r .= "</thead>\n";
	}

	$r .= "<tbody>\n$diff\n</tbody>\n";
	$r .= '</table>';

	return $r;
}

function normalize_whitespace( $str ) {
    $str = trim( $str );
    $str = str_replace( "\r", "\n", $str );
    $str = preg_replace( array( '/\n+/', '/[ \t]+/' ), array( "\n", ' ' ), $str );
    return $str;
}