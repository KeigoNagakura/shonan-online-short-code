<?php
if (!defined('ABSPATH')) exit;
/*
 * お知らせ05
 */
function sosc_info_list05($atts)
{
	$atts = shortcode_atts(['slug' => '', 'list' => 5, 'addClass' => ''], sosc_normalize_shortcode_atts($atts), 'sosc_info_list05');
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
    $html .= '<div class="sosc_info_list05 sosc-bs-scope ' . $addClass . '">';
    $html .= '<div class="w-100">';
    $html .= '<dl>';
    $html .= '<div class="w-100 border-bottom"></div>';
    foreach ($cat_posts as $val) {
        $url = get_permalink($val);
        $html .= '<div class="w-100 border-bottom p-2 infolist_mouse_over">';
        $html .= '<a href="' . esc_url($url) . '" class="d-block text-body text-decoration-none cursor-pointer w-100">';
        $html .= '<dt class="d-inline fw-normal text-secondary">' . esc_html(wp_date('Y/m/d', get_post_time('U', true, $val))) . '</dt>';
		$html .= '<dd class="ps-4 d-inline">' . esc_html($val->post_title) . '</dd>';
        $html .= '</a>';
        $html .= '</div>';
    }
    $html .= '</dl>';
    $html .= '</div>';
    $html .= '</div>';
    return $html;
}
add_shortcode('sosc_info_list05', 'sosc_info_list05');
