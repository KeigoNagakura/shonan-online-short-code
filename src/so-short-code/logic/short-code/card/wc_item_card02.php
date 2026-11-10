<?php
if (!defined('ABSPATH')) exit;
/*
 * WellCart商品ブログカード02
 */
function sosc_wc_item_card02($atts)
{
	$atts = shortcode_atts(['post_name' => '', 'addClass' => ''], sosc_normalize_shortcode_atts($atts), 'sosc_wc_item_card02');
	if ('' === sanitize_title($atts['post_name'])) {
		return '';
	}
    // 追加クラス
    $addClass = '';
    if (isset($atts['addClass'])) {
        $addClass = sosc_sanitize_css_classes($atts['addClass']);
    }
    // 商品を取得
    $item = SoScGetItemPost($atts['post_name']);
    if (is_null($item) || post_password_required((int) $item['ID'])) {
        // データがない場合、空を返す
        return '';
    } else {
        // アイキャッチを取得
        $thumb_url = get_the_post_thumbnail_url($item['ID'], 'full');
        if ($thumb_url == false) {
            $thumb_url = SOSC_NO_IMG;
        }
        // 商品最大金額と最低金額を取得
        $skuPaymentMinMax = SoScGetItemMinAndMaxPayment($item['ID']);
        // 表示金額を整備
        $viewPayTxt = '';
        if (null === $skuPaymentMinMax['min']) {
            $viewPayTxt = '価格未設定';
        } elseif ($skuPaymentMinMax['min'] == $skuPaymentMinMax['max']) {
            $viewPayTxt = number_format($skuPaymentMinMax['min']) . ' 円';
        } else {
            $viewPayTxt = number_format($skuPaymentMinMax['min']) . '円～' .number_format($skuPaymentMinMax['max']) . '円';
        }
        // HTML作成
        $html = '';
        $html .= '<div class="sosc_wc_item_card02 sosc-bs-scope ' . esc_attr($addClass) . '">';
        $html .= '  <a href="' . esc_url(get_permalink($item['ID'])) . '" class="text-decoration-none text-body opacity-hover6">';
        $html .= '    <div class="row">';
        // 商品画像
        $html .= '      <div class="col-12 px-1">';
        $html .= '        <div class="img_frame1_1 rounded-2">';
        $html .= '          <img src="' . esc_url($thumb_url) . '" alt="" class="frame_over frame_max_hover20 img_vcenter">';
        $html .= '        </div>';
        $html .= '      </div>';
        // 商品文章・金額
        $html .= '      <div class="col-12">';
        $html .= '        <div class="row">';
        $html .= '          <div class="col-12 multi_line_truncate_of_2">' . esc_html($item['post_title']) . '</div>';
        $html .= '          <div class="col-12 text-end fs-6">価格:' . esc_html($viewPayTxt) . '</div>';
        $html .= '        </div>';
        $html .= '      </div>';

        $html .= '    </div>';
        $html .= '  </a>';
        $html .= '</div>';
        return $html;   
    }
}
add_shortcode('sosc_wc_item_card02', 'sosc_wc_item_card02');
