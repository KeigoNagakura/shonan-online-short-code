<?php
if (!defined('ABSPATH')) exit;
/*
 * お知らせ01
 */
function sosc_blog_card01($atts)
{
	$atts = shortcode_atts(['slug' => '', 'link' => '', 'addClass' => ''], sosc_normalize_shortcode_atts($atts), 'sosc_blog_card01');
	if ('' === sanitize_title($atts['slug'])) {
		return '';
	}
    // 追加クラス
    $addClass = '';
    if (isset($atts['addClass'])) {
        $addClass = sosc_sanitize_css_classes($atts['addClass']);
    }
    // リンク設定
    $link = $atts['link'];
    if (!isset($link)) {
        $link = '';
    }
    // 投稿を取得
    $posts = get_posts([
        'post_type'      => 'post',
        'name'           => $atts['slug'],
        'posts_per_page' => 1,
        'post_status'    => 'publish',
    ]);
    $post = $posts ? $posts[0] : null;
    if (is_null($post) || post_password_required($post)) {
        // データがない場合、空を返す
        return '';
    } else {
        // 説明文
        $excerpoTxt = $post->post_excerpt;
        if (empty($excerpoTxt)) {
            // HTMLタグ除去して、文字数制限（例：1000文字）
            $content = wp_strip_all_tags($post->post_content);
            $excerpoTxt = mb_substr($content, 0, 1000) . '…';
        }
        // アイキャッチを取得
        $thumb_url = get_the_post_thumbnail_url($post->ID, 'full');
        if ($thumb_url == false) {
            $thumb_url = SOSC_NO_IMG;
        }
        // HTML作成
        $html = '';
        $html .= '<div class="sosc_blog_card01 sosc-bs-scope ' . $addClass . '">';
		$html .= '  <a href="' . esc_url($link) . '" class="text-decoration-none text-body opacity-hover6">';
        $html .= '    <div class="img_frame16_9 rounded-4">';
		$html .= '      <img src="' . esc_url($thumb_url) . '" alt="" class="frame_over frame_max_hover20">';
        $html .= '    </div>';
        $html .= '    <div>';
		$html .= '      <div class="multi_line_truncate_of_1 fw-bold fs-5">' . esc_html($post->post_title) . '</div>';
		$html .= '      <div class="multi_line_truncate_of_5">' . esc_html(wp_strip_all_tags($excerpoTxt)) . '</div>';
        $html .= '      <div class="fs-6 text-end text-black-50">' . esc_html(wp_date('Y/m/d', get_post_modified_time('U', true, $post))) . '</div>';
        $html .= '    </div>';
        $html .= '  </a>';
        $html .= '</div>';
        return $html;
    }
}
add_shortcode('sosc_blog_card01', 'sosc_blog_card01');
