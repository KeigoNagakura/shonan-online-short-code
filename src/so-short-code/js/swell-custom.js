(function ($) {
/** ===========================================
 * sosc-reveal-media
 * SWELLのバナーリンクの文字縦書き中央寄せ
 =========================================== **/
$(function () {
    var $targets = $('.wp-block-media-text.sosc-reveal-media');
    if (!$targets.length) return;
    if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        $targets.addClass('is-inview');
        return;
    }
    $targets.addClass('sosc-reveal-ready');
    // 最初から付いてたら外す（アニメのため）
    $targets.removeClass('is-inview');
    var offset = 120;
    function inView($el) {
        var st = $(window).scrollTop();
        var wh = $(window).height();
        var top = $el.offset().top;
        return top < (st + wh - offset);
    }
    function reveal($el) {
        if ($el.hasClass('is-inview')) return;
        $el[0].getBoundingClientRect();
        requestAnimationFrame(function () {
            $el.addClass('is-inview');
        });
    }
    function check() {
        $targets.each(function () {
            var $el = $(this);
            if (inView($el)) reveal($el);
        });
    }
    $(window).on('scroll resize', check);
    $(window).on('load', function () {
        setTimeout(check, 60);
    });
    // 初回（即時）
    setTimeout(check, 0);
});
})(jQuery);
