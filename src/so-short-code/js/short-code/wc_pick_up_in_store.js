(function ($) {
/**
 * --------------------------
 * WelCart用
 * 店頭受け取り機能
 * --------------------------
 */
$(function () {
    let deliveryInfoEle = $('#delivery-info');  // 配送・支払い設定
    let infoConfirmEle = $('#info-confirm');    // 内容確認
    if (deliveryInfoEle.length > 0) {
        // ============================
        // 配送・支払い設定画面設定
        // ============================
        // 使用要素
        let deliveryMethodSelectClass = '#delivery_method_select';      // 配送方法クラス
        let targetText = '店頭受け取り';
        // ============================
        // 配送方法に「店頭受け取り」が存在するか確認
        // ============================
        let hasPickUpInShop = $(deliveryMethodSelectClass + ' option').filter(function () {
            return $.trim($(this).text()) === targetText;
        }).length > 0;
        // 「店頭受け取り」が無い場合は、店頭受け取り機能を使わない
        if (!hasPickUpInShop) {
            return;
        } else {
            deliveryInfoEle.addClass('sosc-pickup-layout');
            // ============================
            // 店頭受け取り機能実行
            // ============================
            // 使用要素
            let deliveryDateSelectIdName = 'delivery_date_select'           // 到着希望日ID名
            let deliveryMethodSelectEle = $(deliveryMethodSelectClass);     // 配送方法要素
            let deliveryDateSelectEle = $('#' + deliveryDateSelectIdName);  // 到着希望日要素
            let deliveryTimeSelectEle = $('#delivery_time_select');         // 到着希望時間要素
            let detachedNoPreferenceOption = null;
            deliveryMethodSelectEle.closest('tr').children('th').first().text('受け取り方法');
            // 配送先の選択欄と住所欄を、支払い方法の表の後へまとめて移動する。
            const deliveryTable = $('#delivery_table');
            const destinationTable = deliveryTable.prev('table.customer_form');
            const paymentInput = $('input[name="offer[payment_name]"]').first();
            const paymentBlock = paymentInput.closest('table').length ? paymentInput.closest('table') : paymentInput.closest('dl');
            if (destinationTable.length && deliveryTable.length && paymentBlock.length &&
                paymentBlock[0] !== destinationTable[0] && paymentBlock[0] !== deliveryTable[0]) {
                paymentBlock.after(destinationTable, deliveryTable);
            }
            // =================================================
            // 配送方法変更イベント(到着希望日がjsで変更が走ったら)
            // =================================================
            $(document).on('change', deliveryMethodSelectClass, function () {
                syncDeliveryDateOptions();
            });
            // 到着希望日の変化を監視
            const target = document.getElementById(deliveryDateSelectIdName);
            if (target) {
                const observer = new MutationObserver(function () {
                    syncDeliveryDateOptions();
                });
                observer.observe(target, {
                    childList: true
                });
            }
            // =================================================
            // 配送方法変更処理
            // =================================================
            function syncDeliveryDateOptions() {
                let selectedText = $.trim($(deliveryMethodSelectClass + ' option:selected').text());
                if (selectedText === targetText) {
                    // 店頭受け取りを選択
                    deliveryDateSelectEle.closest('tr').children('th').text('お受け取り希望日');
                    deliveryTimeSelectEle.closest('tr').children('th').text('お受け取り時間');
                    // 配送先テーブルを非表示にする
                    $('#delivery_table').hide();
                    $('#delivery_table').prev('table.customer_form').hide();
                    // 到着希望日から「指定しない」を非表示にする
                    let $first = $('#' + deliveryDateSelectIdName + ' option:first');
                    if ($first.length && $.trim($first.text()) === '指定しない') {
                        detachedNoPreferenceOption = $first.detach();
                    }
                    // 支払方法の代引きを非表示にする
                    let paymentDt = '';
                    $('input[name="offer[payment_name]"]').each(function () {
                        if ($.trim($(this).val()) === '代引き') {
                            const wasChecked = this.checked;
                            $(this).prop('checked', false);
                            paymentDt = $(this).closest('dt');
                            paymentDt.hide();
                            paymentDt.next('dd').hide();
                            // 非表示以外の先頭を選択
                            if (wasChecked) {
                                $('input[name="offer[payment_name]"]').filter(function () {
                                    return this.value !== '代引き' && !this.disabled && $(this).closest('dt').is(':visible');
                                }).first().prop('checked', true).trigger('change');
                            }
                        }
                    });
                } else {
                    // それ以外を選択
                    deliveryDateSelectEle.closest('tr').children('th').text('到着希望日');
                    deliveryTimeSelectEle.closest('tr').children('th').text('到着希望時間');
                    // 配送先を表示
                    $('#delivery_table').prev('table.customer_form').show();
                    // 配送先の別の配送先を指定するの状況に合わせて設定
                    if ($('#delivery_flag2').is(':checked')) {
                        $('#delivery_table').show();
                    } else {
                        $('#delivery_table').hide();
                    }
                    // 到着希望日から「指定しない」を表示する
                    let $first = $('#' + deliveryDateSelectIdName + ' option:first');
                    if (!$first.length || $.trim($first.text()) !== '指定しない') {
                        if (detachedNoPreferenceOption) {
                            deliveryDateSelectEle.prepend(detachedNoPreferenceOption);
                            detachedNoPreferenceOption = null;
                        }
                    }
                    // 支払方法の代引きを表示にする
                    let paymentDt = '';
                    $('input[name="offer[payment_name]"]').each(function () {
                        if ($.trim($(this).val()) === '代引き') {
                            paymentDt = $(this).closest('dt');
                            paymentDt.show();
                            paymentDt.next('dd').show();
                        }
                    });
                }
            }
            syncDeliveryDateOptions();
        }
    } else if (infoConfirmEle.length > 0) {
        // ============================
        // 内容確認画面設定
        // ============================
        $('#confirm_table th').filter(function () {
            return $(this).text().trim() === '配送方法';
        }).text('受け取り方法');
        // 配送方法を確認
        let deliveryMethod = $('#confirm_table th').filter(function () {
            return $(this).text().trim() === '受け取り方法';
        }).next('td').text().trim();

        if (deliveryMethod === '店頭受け取り') {
            // 到着希望日カラムの文言変更
            $('#confirm_table th').filter(function () {
                return $(this).text().trim() === '到着希望日';
            }).text('お受け取り希望日');
            // 到着希望時間の文言変更
            $('#confirm_table th').filter(function () {
                return $(this).text().trim() === '到着希望時間';
            }).text('お受け取り時間');
            // 配送先情報の文言変更
            $('#confirm_table h3').filter(function () {
                return $(this).text().trim() === '配送先情報';
            }).closest('tr').nextUntil('tr:has(h3)').hide().end().hide();
        }
    }
});
})(jQuery);

