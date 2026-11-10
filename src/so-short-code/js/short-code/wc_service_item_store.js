(function ($) {
/**
 * --------------------------
 * WelCart用
 * サービス商品機能
 * --------------------------
 */
$(function () {
    const deliveryInfoEle = $('#delivery-info');  // 配送・支払い設定
    const infoConfirmEle = $('#info-confirm');    // 内容確認

    if (deliveryInfoEle.length > 0) {
        // ============================
        // 配送・支払い設定画面設定
        // ============================
        const deliveryMethodSelect = $('#delivery_method_select');
        // 配送方法に「サービス商品」が存在するか確認
        const serviceOption = deliveryMethodSelect.find('option').filter(function () {
            return $.trim($(this).text()) === 'サービス商品';
        });
        const hasServiceItemShop = serviceOption.length > 0;
        // 配送方法に「店頭受け取り」が存在するか確認
        const hasPickUpInShop = deliveryMethodSelect.find('option').filter(function () {
            return $.trim($(this).text()) === '店頭受け取り';
        }).length > 0;
        // 「サービス商品」が無い、または「店頭受け取り」がある場合は処理しない
        if (!hasServiceItemShop || hasPickUpInShop) {
            return;
        }
        // ============================
        // サービス商品機能実行
        // ============================
        // 配送方法をサービス商品に変更
        deliveryMethodSelect
            .val(serviceOption.val())
            .trigger('change');
        // 配送先非表示
        const deliverySend = $('#delivery_flag1').closest('tr');
        deliverySend.hide();
        deliverySend.next('tr').hide();
        // 配送方法非表示
        deliveryMethodSelect.closest('tr').hide();
        // 到着希望日非表示
        $('#delivery_date_select').closest('tr').hide();
        // 到着希望時間非表示
        $('#delivery_time_select').closest('tr').hide();
        // 支払方法の代引きを非表示にする
        $('input[name="offer[payment_name]"]').each(function () {
            const paymentInput = $(this);
            const paymentName = $.trim(paymentInput.val());
            if (paymentName === '代引き') {
                const paymentDt = paymentInput.closest('dt');
                paymentDt.hide();
                paymentDt.next('dd').hide();
                // 代引きが選択済みなら解除
                paymentInput.prop('checked', false);
            }
        });

        // 選択済みの有効な支払方法を保持し、必要な場合だけ代替を選ぶ。
        const availablePayments = $('input[name="offer[payment_name]"]').filter(function () {
            return !this.disabled && $.trim(this.value) !== '代引き' && $(this).closest('dt').is(':visible');
        });
        if (!availablePayments.filter(':checked').length) {
            availablePayments.first().prop('checked', true).trigger('change');
        }
        // オプション項目を一旦すべて非表示
        const customOrderFields = $('input[name^="custom_order["], select[name^="custom_order["], textarea[name^="custom_order["]');
        customOrderFields
            .prop('disabled', true)
            .closest('tr')
            .attr('aria-hidden', 'true')
            .hide();
        // ライン名だけ表示
        $('input[name="custom_order[service_item_line]"], select[name="custom_order[service_item_line]"], textarea[name="custom_order[service_item_line]"]')
            .prop('disabled', false)
            .closest('tr')
            .removeAttr('aria-hidden')
            .show();
    } else if (infoConfirmEle.length > 0) {
        // ============================
        // 内容確認画面設定
        // ============================
        const confirmTable = $('#confirm_table');
        // 配送方法を確認
        const deliveryMethod = confirmTable.find('th').filter(function () {
            return ['配送方法', '受け取り方法'].includes($(this).text().trim());
        }).next('td').text().trim();
        if (deliveryMethod === 'サービス商品') {
            // 到着希望日を非表示
            confirmTable.find('th').filter(function () {
                return $(this).text().trim() === '到着希望日';
            }).closest('tr').hide();
            // 到着希望時間を非表示
            confirmTable.find('th').filter(function () {
                return $(this).text().trim() === '到着希望時間';
            }).closest('tr').hide();
            // 配送先情報を非表示
            confirmTable.find('h3').filter(function () {
                return $(this).text().trim() === '配送先情報';
            }).closest('tr').nextUntil('tr:has(h3)').hide().end().hide();
        }
    }
});
})(jQuery);
