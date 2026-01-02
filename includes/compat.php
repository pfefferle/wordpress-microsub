<?php
/**
 * Compatibility shims.
 *
 * @package Microsub
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'str_starts_with' ) ) {
	/**
	 * Polyfill for str_starts_with for PHP < 8.0.
	 *
	 * @param string $haystack Full string.
	 * @param string $needle   Prefix to check.
	 * @return bool Whether the string starts with the prefix.
	 */
	function str_starts_with( $haystack, $needle ) {
		return 0 === strncmp( $haystack, $needle, strlen( $needle ) );
	}
}
