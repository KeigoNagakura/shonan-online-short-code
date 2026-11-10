<?php
if (!defined('ABSPATH')) exit;
/**
 * タグ表示01
 */
function sosc_tag_view01($atts)
{
	$atts = shortcode_atts(['tag' => '', 'addClass' => ''], sosc_normalize_shortcode_atts($atts), 'sosc_tag_view01');
	if ('' === trim($atts['tag'])) {
		return '';
	}
    // 追加クラス
    $addClass = '';
    if (isset($atts['addClass'])) {
        $addClass = sosc_sanitize_css_classes($atts['addClass']);
    }
    // 対象タグ
    $targetTag = explode(',', $atts['tag']);
    // タグを取得
    $tagParams = [];
    foreach (get_tags() as $val) {
        $tmp = [];
        foreach ($targetTag as $tVal) {
            if ($val->name === $tVal) {
                $tmp['name'] = $tVal;
                $tag_link = get_tag_link($val->term_id);
                if (is_wp_error($tag_link)) {
                    continue;
                }
                $tmp['url'] = $tag_link;
                $tagParams[] = $tmp;
                break;
            }
        }
    }
    // HTML作成
    $html = '';
    $html .= '<div class="sosc_tag_view01 sosc-bs-scope ' . $addClass . '">';
    $html .= '<div class="row w-100">';
    $html .= '<div class="col-12">';
    foreach ($tagParams as $val) {
		$html .= '<a href="' . esc_url($val['url']) . '" class="m-0 p-0 text-decoration-none">';
        $html .= '<span class="badg badge-with-hole badge-hover bg-info text-light m-1 my-2 py-0">';
		$html .= esc_html($val['name']);
        $html .= '</span>';
        $html .= '</a>';
    }
    $html .= '</div>';
    $html .= '</div>';
    $html .= '</div>';
    return $html;
}
add_shortcode('sosc_tag_view01', 'sosc_tag_view01');
