<?php
if (!defined('ABSPATH')) exit;
/*
 * お知らせ02
 */
function sosc_info_list02($atts)
{
	$atts = shortcode_atts(['slug' => '', 'list' => 5, 'addClass' => ''], sosc_normalize_shortcode_atts($atts), 'sosc_info_list02');
	if ('' === sanitize_title($atts['slug'])) return '';
    // 追加クラス
    $addClass = '';
    if (isset($atts['addClass'])) {
        $addClass = sosc_sanitize_css_classes($atts['addClass']);
    }
    // 件数
    $listCount = sosc_sanitize_list_count($atts['list']);
    // データを取得
    $cat_posts = get_posts([
        'category_name' => $atts['slug'],   // カテゴリをスラッグで指定する場合
        'posts_per_page' => $listCount,     // 表示件数
        'orderby' => 'date',                // 表示順の基準
        'order' => 'DESC'                   // 昇順・降順
    ]);
    // HTML作成
    $html = '';
    $html .= '<div class="sosc_info_list02 sosc-bs-scope ' . $addClass . '">';
    $html .= '<div class="row w-100">';
    foreach ($cat_posts as $val) {
        $html .= '<div class="col-12 border-bottom infolist_mouse_over">';
		$html .= '<a href="' . esc_url(get_permalink($val->ID)) . '" class="text-decoration-none text-dark">';
        $html .= '<div>' . esc_html(wareki(wp_date('Y', get_post_time('U', true, $val)))) . '度</div>';
		$html .= '<div class="ms-3">' . esc_html($val->post_title) . '</div>';
        $html .= '</a>';
        $html .= '</div>';  // col-12
    }
    $html .= '</div>';  // row
    $html .= '</div>';  // row

    return $html;
}
add_shortcode('sosc_info_list02', 'sosc_info_list02');
