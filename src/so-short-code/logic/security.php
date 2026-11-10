<?php
/**
 * Security compatibility helpers.
 *
 * @package ShonanOnlineShortCode
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sanitize a space-separated list of CSS class names.
 *
 * @param mixed $classes Class names supplied by a shortcode.
 * @return string
 */
function sosc_sanitize_css_classes( $classes ) {

	if ( ! is_scalar( $classes ) ) {
		return '';
	}
	$sanitized = array();
	foreach ( preg_split( '/\s+/', trim( (string) $classes ) ) as $class_name ) {
		$class_name = sanitize_html_class( $class_name );
		if ( '' !== $class_name ) {
			$sanitized[] = $class_name;
		}
	}
	return implode( ' ', array_unique( $sanitized ) );
}

/**
 * Normalize a shortcode list size to a safe, bounded value.
 *
 * @param mixed $value   Requested item count.
 * @param int   $default Default item count.
 * @return int
 */
function sosc_sanitize_list_count( $value, $default = 5 ) {

	$default = min( 100, max( 1, absint( $default ) ) );
	$count   = is_scalar( $value ) ? absint( $value ) : 0;

	return min( 100, max( 1, $count ? $count : $default ) );
}
/**
 * Restore documented camelCase shortcode keys after WordPress lowercases them.
 *
 * @param mixed $atts Raw shortcode attributes.
 * @return array
 */
function sosc_normalize_shortcode_atts( $atts ) {
	$atts = is_array( $atts ) ? $atts : array();
	foreach ( array( 'addClass', 'yearSlug', 'orderSlug', 'areaSlug' ) as $canonical ) {
		$lower = strtolower( $canonical );
		if ( isset( $atts[ $lower ] ) && ! isset( $atts[ $canonical ] ) ) {
			$atts[ $canonical ] = $atts[ $lower ];
		}
	}
	return $atts;
}
