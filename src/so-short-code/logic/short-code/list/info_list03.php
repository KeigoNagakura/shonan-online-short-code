<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/*
 * お知らせ03
 */
function sosc_info_list03( $atts ) {
	$atts = shortcode_atts(
		array(
			'slug'      => '',
			'list'      => 5,
			'addClass'  => '',
			'yearSlug'  => 'year',
			'orderSlug' => 'order',
			'areaSlug'  => 'area',
		),
		sosc_normalize_shortcode_atts( $atts ),
		'sosc_info_list03'
	);
	if ( '' === sanitize_title( $atts['slug'] ) ) {
		return '';
	}
	// 追加クラス
	$add_class = '';
	if ( isset( $atts['addClass'] ) ) {
		$add_class = sosc_sanitize_css_classes( $atts['addClass'] );
	}
	// 件数
	$list_count = sosc_sanitize_list_count( $atts['list'] );
	// 年度カテゴリを取得
	$year_slug = 'year';
	if ( isset( $atts['yearSlug'] ) ) {
		$year_slug = $atts['yearSlug'];
	}
	$year_base_category = get_category_by_slug( $year_slug );
	$year_categories    = array();
	if ( $year_base_category ) {
		$year_categories = get_categories(
			array(
				'parent' => $year_base_category->term_id,
			)
		);
	}
	// 発注元カテゴリを取得
	$order_slug = 'order';
	if ( isset( $atts['orderSlug'] ) ) {
		$order_slug = $atts['orderSlug'];
	}
	$order_base_category = get_category_by_slug( $order_slug );
	$order_categories    = array();
	if ( $order_base_category ) {
		$order_categories = get_categories(
			array(
				'parent' => $order_base_category->term_id,
			)
		);
	}
	// エリアカテゴリを取得
	$area_slug = 'area';
	if ( isset( $atts['areaSlug'] ) ) {
		$area_slug = $atts['areaSlug'];
	}
	$area_base_category = get_category_by_slug( $area_slug );
	$area_categories    = array();
	if ( $area_base_category ) {
		$area_categories = get_categories(
			array(
				'parent' => $area_base_category->term_id,
			)
		);
	}
	// データを取得
	$cat_posts = get_posts(
		array(
			'category_name'  => $atts['slug'],
			'posts_per_page' => $list_count,     // 表示件数
			'orderby'        => 'date',                // 表示順の基準
			'order'          => 'DESC',                  // 昇順・降順
		)
	);
	// タイトル分解
	$tmp_list = array();
	foreach ( $cat_posts as $post ) {
		// 投稿名を入れる
		$tmp_data['title'] = $post->post_title;
		// 所属カテゴリを取得
		$categories = get_the_category( $post->ID );
		// 年度を取得
		$tmp_data['year'] = '';
		foreach ( $year_categories as $year ) {
			foreach ( $categories as $cat ) {
				if ( $year->slug === $cat->slug ) {
					$tmp_data['year'] = $year->name;
					break;
				}
			}
		}
		// 発注元カテゴリを取得
		$tmp_data['order'] = '';
		foreach ( $order_categories as $order ) {
			foreach ( $categories as $cat ) {
				if ( $order->slug === $cat->slug ) {
					$tmp_data['order'] = $order->name;
					break;
				}
			}
		}
		// エリアカテゴリを取得
		$tmp_data['area'] = '';
		foreach ( $area_categories as $area ) {
			foreach ( $categories as $cat ) {
				if ( $area->slug === $cat->slug ) {
					$tmp_data['area'] = $area->name;
					break;
				}
			}
		}
		$tmp_list[] = $tmp_data;
	}
	// HTML作成
	$html  = '';
	$html .= '<div class="sosc_info_list03 sosc-bs-scope ' . $add_class . '">';
	$html .= '<div class="row w-100">';
	$html .= '<div class="col-12 p-3">';
	$html .= '<div class="row w-100 py-3 border-bottom fw-bold sosc-info-head">';
	$html .= '  <div class="col-2">発注元</div>';   // 発注元
	$html .= '  <div class="col-5">内容</div>';     // タイトル
	$html .= '  <div class="col-3">エリア</div>';   // エリア
	$html .= '  <div class="col-2">年度</div>';     // 年度
	$html .= '</div>';  // row
	foreach ( $cat_posts as $key => $val ) {
		$html .= '<a href="' . esc_url( get_permalink( $val->ID ) ) . '" class="text-decoration-none text-dark w-100">';
		$html .= '<div class="row w-100 py-3 border-bottom infolist_mouse_over">';
		$html .= '<div class="col-2" data-label="' . esc_attr__( '発注元', 'so-short-code' ) . '">' . esc_html( $tmp_list[ $key ]['order'] ) . '</div>';    // 発注元
		$html .= '<div class="col-5">' . esc_html( $tmp_list[ $key ]['title'] ) . '</div>';    // タイトル
		$html .= '<div class="col-3" data-label="' . esc_attr__( 'エリア', 'so-short-code' ) . '">' . esc_html( $tmp_list[ $key ]['area'] ) . '</div>';     // エリア
		$html .= '<div class="col-2" data-label="' . esc_attr__( '年度', 'so-short-code' ) . '">' . esc_html( $tmp_list[ $key ]['year'] ) . '</div>';     // 年度
		$html .= '</div>';  // row
		$html .= '</a>';
	}
	$html .= '</div>';  // col-12
	$html .= '</div>';  // row
	$html .= '</div>';

	return $html;
}
add_shortcode( 'sosc_info_list03', 'sosc_info_list03' );
