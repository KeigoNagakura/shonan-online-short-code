<?php
if (!defined('ABSPATH')) exit;
/*
 * お知らせ04
 */
function sosc_info_list04($atts)
{
	$atts = shortcode_atts(['slug' => '', 'list' => 5, 'addClass' => ''], sosc_normalize_shortcode_atts($atts), 'sosc_info_list04');
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
        'category_name'  => $atts['slug'],  // カテゴリをスラッグで指定する場合
        'posts_per_page' => $listCount,     // 表示件数
        'orderby'        => 'date',         // 表示順の基準
        'order'          => 'DESC'          // 昇順・降順
    ]);
    // HTML作成
    $html = '';
    $html .= '<div class="sosc_info_list04 sosc-bs-scope ' . $addClass . '">';
    $html .= '<div class="row">';
    foreach ($cat_posts as $val) {
        if (post_password_required($val)) {
            continue;
        }
        $html .= '<div class="col-12 col-sm-6 col-lg-4 mb-3">';
        // ブログカード呼び出し
        $atts = [];
        $atts['slug'] = $val->post_name;
        $atts['link'] = get_permalink($val);
        $html .= sosc_blog_card01($atts);
        $html .= '</div>';
    }
    $html .= '</div>';
    $html .= '</div>';
    return $html;
}
add_shortcode('sosc_info_list04', 'sosc_info_list04');
