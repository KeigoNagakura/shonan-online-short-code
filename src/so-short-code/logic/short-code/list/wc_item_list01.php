<?php
if (!defined('ABSPATH')) exit;
/*
 * 商品リスト
 */
function sosc_wc_item_list01($atts)
{
	$atts = shortcode_atts(['slug' => '', 'list' => 5, 'addClass' => ''], sosc_normalize_shortcode_atts($atts), 'sosc_wc_item_list01');
	if ('' === sanitize_title($atts['slug'])) return '';
    // 追加クラス
    $addClass = '';
    if (isset($atts['addClass'])) {
        $addClass = sosc_sanitize_css_classes($atts['addClass']);
    }
    // 件数
    $listCount = sosc_sanitize_list_count($atts['list']);
    // データを取得
    $posts = SoScGetItemsPostOfCategory($atts['slug'], $listCount);
    if (COUNT($posts) == 0) {
        return '';
    } else {
        // HTML作成
        $html = '';
        $html .= '<div class="sosc_wc_item_list01 sosc-bs-scope ' . $addClass . '">';
        foreach ($posts as $val) {
            if (post_password_required((int) $val['ID'])) {
                continue;
            }
            $html .= '<div class="row my-3">';
            $html .= '<div class="col-12">';
            // ブログカード呼び出し
            $atts = [];
            $atts['post_name'] = $val['post_name'];
            $html .= sosc_wc_item_card01($atts);
            $html .= '</div>';
            $html .= '</div>';
        }
        $html .= '</div>';
        return $html;
    }
}
add_shortcode('sosc_wc_item_list01', 'sosc_wc_item_list01');
