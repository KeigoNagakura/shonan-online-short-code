<?php
/**
 * Shared LP content shortcode.
 *
 * @package ShonanOnlineShortCode
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function sosc_target_content( $atts ) {
	static $rendering = array();
	$atts = shortcode_atts( array( 'slug' => '' ), sosc_normalize_shortcode_atts( $atts ), 'sosc_target_content' );
	if ( ! is_string( $atts['slug'] ) || '' === sanitize_title( $atts['slug'] ) ) {
		return '';
	}
	$pages = get_posts(
		array(
			'post_type'      => 'lp',
			'name'           => $atts['slug'],
			'posts_per_page' => 1,
			'post_status'    => 'publish',
		)
	);
	$page = $pages ? $pages[0] : null;
	if ( ! $page instanceof WP_Post || post_password_required( $page ) || isset( $rendering[ $page->ID ] ) || count( $rendering ) >= 20 ) {
		return '';
	}
	$rendering[ $page->ID ] = true;
	try {
		return '<div class="sosc_info_list01 sosc-bs-scope"><div>' . apply_filters( 'the_content', $page->post_content ) . '</div></div>';
	} finally {
		unset( $rendering[ $page->ID ] );
	}
}
add_shortcode( 'sosc_target_content', 'sosc_target_content' );
