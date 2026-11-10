(function ($) {
/**
 * --------------------------
 * H1非表示
 * メニューにnone_h1作成し、その対象ページのh1を非表示
 * --------------------------
 */
$(function () {
    if ($('.sosc_none_h1').text() == "true") {
        $('body').addClass('none_h1');
    }
})

/**
 * --------------------------
 * コンテンツ上マージン非表示
 * メニューにnone_contents_top_margin作成し、その対象ページのコンテンツ下マージンを非表示
 * --------------------------
 */
$(function () {
    if ($('.sosc_none_contents_tm').text() == "true") {
        $('body').addClass('none_contents_tm');
    }
})

/**
 * --------------------------
 * コンテンツ下マージン非表示
 * メニューにnone_contents_bottom_margin作成し、その対象ページのコンテンツ下マージンを非表示
 * --------------------------
 */
$(function () {
    if ($('.sosc_none_contents_bm').text() == "true") {
        $('body').addClass('none_contents_bm');
    }
})

/**
 * --------------------------
 * Font変更
 * --------------------------
 */
$(function () {
    if ($('.sosc_change_font').length) {
        let className = 'change_font_' + $('.sosc_change_font').text();
        $('body').addClass(className);
    }
})

/**
 * --------------------------
 * コンタクトフォーム基本追加設定
 * --------------------------
 */
$(function () {
    if ($('.sosc_contact_form_add').text() == "true") {
        $('body').addClass('sosc-contact-form');
    }
})
})(jQuery);
