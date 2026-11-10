<?php
/**
 * Front-end content discovery and initial body classes.
 *
 * @package ShonanOnlineShortCode
 */

namespace ShonanOnline\ShortCode;

defined( 'ABSPATH' ) || exit;

final class FrontDisplay {

	const MARKERS = array( 'sosc-reveal-media', 'virtical-text', 'sosc_h2_sw_type1' );

	public static function boot() {
		add_filter( 'body_class', array( __CLASS__, 'body_classes' ) );
		add_filter( 'render_block', array( __CLASS__, 'rendered_content' ) );
		add_filter( 'the_content', array( __CLASS__, 'rendered_content' ), 9 );
	}

	public static function contains( $content, $needle, $seen = array(), $depth = 0 ) {
		if ( $depth >= 20 ) {
			return false;
		}
		if ( false !== strpos( (string) $content, $needle ) ) {
			return true;
		}
		$references = array();
		$blocks     = parse_blocks( $content );
		while ( $blocks ) {
			$block = array_pop( $blocks );
			if ( 'core/block' === $block['blockName'] && ! empty( $block['attrs']['ref'] ) ) {
				$references[] = get_post( absint( $block['attrs']['ref'] ) );
			}
			foreach ( $block['innerBlocks'] as $inner ) {
				$blocks[] = $inner;
			}
		}
		if ( preg_match_all( '/' . get_shortcode_regex( array( 'sosc_target_content' ) ) . '/s', $content, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$atts = shortcode_parse_atts( $match[3] );
				if ( '[' !== $match[1] && ! empty( $atts['slug'] ) ) {
					$posts = get_posts(
						array(
							'post_type'      => 'lp',
							'name'           => $atts['slug'],
							'post_status'    => 'publish',
							'posts_per_page' => 1,
						)
					);
					if ( $posts ) {
						$references[] = $posts[0];
					}
				}
			}
		}
		foreach ( $references as $post ) {
			if ( ! $post instanceof \WP_Post || 'publish' !== $post->post_status || post_password_required( $post ) || isset( $seen[ $post->ID ] ) ) {
				continue;
			}
			$seen[ $post->ID ] = true;
			if ( self::contains( $post->post_content, $needle, $seen, $depth + 1 ) ) {
				return true;
			}
		}
		return false;
	}

	public static function enqueue_swell() {
		wp_enqueue_style( 'sosc-cw-cus-css', SOSC_CSS_PATH . 'swell-custom.css', array(), SOSC_CSS_JS_TIME );
		wp_enqueue_script( 'sosc-cw-cus-js', SOSC_JS_PATH . 'swell-custom.js', array( 'jquery' ), SOSC_CSS_JS_TIME, true );
	}

	public static function rendered_content( $content ) {
		if ( is_admin() ) {
			return $content;
		}
		foreach ( self::MARKERS as $marker ) {
			if ( false !== strpos( $content, $marker ) ) {
				self::enqueue_swell();
				if ( did_action( 'wp_head' ) && ! did_action( 'wp_footer' ) ) {
					ob_start();
					wp_print_styles( array( 'sosc-cw-cus-css' ) );
					$content = ob_get_clean() . $content;
				}
				break;
			}
		}
		return $content;
	}

	public static function body_classes( $classes ) {
		if ( ! is_singular() ) {
			return $classes;
		}
		$url   = get_permalink( get_queried_object_id() );
		$menus = array(
			'h1_is_check'          => array( 'none_h1', 'none_h1' ),
			'contents_tm_is_check' => array( 'none_contents_top_margin', 'none_contents_tm' ),
			'contents_bm_is_check' => array( 'none_contents_bottom_margin', 'none_contents_bm' ),
		);
		foreach ( $menus as $setting => $menu ) {
			if ( '1' !== (string) sosc_get_value( $setting, '0' ) ) {
				continue;
			}
			foreach ( (array) wp_get_nav_menu_items( $menu[0] ) as $item ) {
				if ( $url === $item->url ) {
					$classes[] = $menu[1];
					break;
				}
			}
		}
		if ( 'twentytwentyfive' === get_template() ) {
			$classes[] = 'sosc-theme-twentytwentyfive';
		}
		return $classes;
	}
}
