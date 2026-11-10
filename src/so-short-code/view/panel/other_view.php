<?php
if (!defined('ABSPATH')) exit;
// 権限チェック（必要に応じて変更）
if (!current_user_can('manage_options')) {
    wp_die('権限がありません');
}
$monthlyBackupEncryptionAvailable = \ShonanOnline\ShortCode\Backup\Manager::encryption_available();
$settingsNoticeKey = 'sosc_settings_notice_' . get_current_user_id();
$settingsNotice = get_transient($settingsNoticeKey);
if (is_array($settingsNotice) && isset($settingsNotice['message'])) {
    delete_transient($settingsNoticeKey);
    echo '<div class="notice ' . (!empty($settingsNotice['error']) ? 'notice-error' : 'notice-success') . ' is-dismissible"><p>' . esc_html($settingsNotice['message']) . '</p></div>';
}
// データ読み込み===========================
// h1タグ非表示
$h1CheckVal = sosc_get_value('h1_is_check', '0');
// コンテンツ上マージン非表示
$contentsTMCheckVal = sosc_get_value('contents_tm_is_check', '0');
// コンテンツ下マージン非表示
$contentsBMCheckVal = sosc_get_value('contents_bm_is_check', '0');
// フォント種類
$fontSelectVal = sosc_get_value('font_change_select', 'default');
// コンタクトフォーム変更
$contactFormIsCheckVal = sosc_get_value('contact_form_is_check', '0');
// WelCart店頭受取機能
$welcartPickUpInStoreIsCheckVal = sosc_get_value('welcart_pick_up_in_store_is_check', '0');
// WelCartサービス商品機能
$welcartServiceItemStoreIsCheckVal = sosc_get_value('welcart_service_item_store_is_check', '0');
// WelCart箱詰め設定機能
$packingFeatureIsCheckVal = sosc_get_value('packing_feature_is_check', '0');
// 月次バックアップ機能
$monthlyBackupIsCheckVal = sosc_get_value('monthly_backup_is_check', '0');
$monthlyBackupPasswordIsSet = \ShonanOnline\ShortCode\Backup\Manager::has_encryption_password();
?>

<div>
    <div class="table-wrap">
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="sosc_save_settings">
            <?php wp_nonce_field('sosc_save_settings', 'sosc_nonce'); ?>
            <table class="table table-striped fs-7 sosc-settings-table">
                <thead>
                    <tr>
                        <th class="bg-head" scope="col">機能名</th>
                        <th class="bg-head" scope="col">設定</th>
                        <th class="bg-head" scope="col">備考</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- ================================================================== -->
                    <tr>
                        <td>h1タグ非表示</td>
                        <td>
                            <div class="form-check">
                                <input
                                    type="checkbox"
                                    name="h1_is_check"
                                    id="h1_is_check"
                                    value="1"
                                    class="form-check-input"
                                    <?php checked($h1CheckVal, '1'); ?>>
                                <label for="h1_is_check" class="form-check-label">
                                    利用
                                </label>
                            </div>
                        </td>
                        <td>メニューにnone_h1を作成し、そこに入れたページはh1タグを消す</td>
                    </tr>
                    <!-- ================================================================== -->
                    <tr>
                        <td>コンテンツ上マージン非表示</td>
                        <td>
                            <div class="form-check">
                                <input
                                    type="checkbox"
                                    name="contents_tm_is_check"
                                    id="contents_tm_is_check"
                                    value="1"
                                    class="form-check-input"
                                    <?php checked($contentsTMCheckVal, '1'); ?>>
                                <label for="contents_tm_is_check" class="form-check-label">
                                    利用
                                </label>
                            </div>
                        </td>
                        <td>メニューにnone_contents_top_marginを作成し、そこに入れたページはコンテンツ上マージンを0にする</td>
                    </tr>
                    <!-- ================================================================== -->
                    <tr>
                        <td>コンテンツ下マージン非表示</td>
                        <td>
                            <div class="form-check">
                                <input
                                    type="checkbox"
                                    name="contents_bm_is_check"
                                    id="contents_bm_is_check"
                                    value="1"
                                    class="form-check-input"
                                    <?php checked($contentsBMCheckVal, '1'); ?>>
                                <label for="contents_bm_is_check" class="form-check-label">
                                    利用
                                </label>
                            </div>
                        </td>
                        <td>メニューにnone_contents_bottom_marginを作成し、そこに入れたページはコンテンツ下マージンを0にする</td>
                    </tr>
                    <!-- ================================================================== -->
                    <tr>
                        <td>フォント指定</td>
                        <td>
                            <label class="screen-reader-text" for="font_change_select">表示フォント</label>
                            <select name="font_change_select" id="font_change_select" class="form-select form-select-sm">
                                <option value="default" <?php selected($fontSelectVal, 'default');  ?>>変更しない</option>
								<option value="noto-serif-jp" <?php selected($fontSelectVal, 'noto-serif-jp'); ?>>端末の明朝体</option>
                            </select>
                        </td>
                        <td>特になし。プラグインによるフォント変更が不要な場合は「変更しない」を選択してください。</td>
                    </tr>
                    <!-- ================================================================== -->
                    <tr>
                        <td>コンタクトフォーム修正</td>
                        <td>
                            <div class="form-check">
                                <input
                                    type="checkbox"
                                    name="contact_form_is_check"
                                    id="contact_form_is_check"
                                    value="1"
                                    class="form-check-input"
                                    <?php checked($contactFormIsCheckVal, '1'); ?>>
                                <label for="contact_form_is_check" class="form-check-label">
                                    利用
                                </label>
                            </div>
                        </td>
                        <td>コンタクトフォームの幅を広くする</td>
                    </tr>
                    <!-- ================================================================== -->
                    <tr>
                        <td>Welcart店頭受取機能</td>
                        <td>
                            <div class="form-check">
                                <input
                                    type="checkbox"
                                    name="welcart_pick_up_in_store_is_check"
                                    id="welcart_pick_up_in_store_is_check"
                                    value="1"
                                    class="form-check-input"
                                    <?php checked($welcartPickUpInStoreIsCheckVal, '1'); ?>>
                                <label for="welcart_pick_up_in_store_is_check" class="form-check-label">
                                    利用
                                </label>
                            </div>
                        </td>
                        <td>
                            Welcart専用店頭受取を有効にする<br>
                            〇配送設定<br>
                            ・送料に「送料無料」を追加<br>
                            ・配送日数に「店頭受け取り」を追加(0でもOK)<br>
                            ・配送方法<br>
                            　→「店頭受け取り」を追加<br>
                            　→指定時間帯に営業時間を設定<br>
                            　→送料固定を「送料無料」に設定<br>
                            　→配送日数を「店頭受け取り」に設定<br>
                            　→代引き不可にチェックを入れる<br>
                            〇商品<br>
                            ・発送目安を設定すると、配送日数と合わせた日付から受取希望日を選べます。
                        </td>
                    </tr>
                    <!-- ================================================================== -->
                    <tr>
                        <td>Welcartサービス商品機能</td>
                        <td>
                            <div class="form-check">
                                <input
                                    type="checkbox"
                                    name="welcart_service_item_store_is_check"
                                    id="welcart_service_item_store_is_check"
                                    value="1"
                                    class="form-check-input"
                                    <?php checked($welcartServiceItemStoreIsCheckVal, '1'); ?>>
                                <label for="welcart_service_item_store_is_check" class="form-check-label">
                                    利用
                                </label>
                            </div>
                        </td>
                        <td>
                            Welcart専用サービス商品(配送なし商品)を有効にする<br>
                            〇配送設定<br>
                            ・配送方法<br>
                            　→「サービス商品」を追加<br>
                            　→指定時間帯に指定なしを設定<br>
                            　→送料固定を「送料無料」or「送料を固定しない」に設定<br>
                            　→代引き不可にチェックを入れる<br>
                            〇商品<br>
                            ・サービス商品にチェック<br>
                            〇カートページ設定<br>
                            ・カスタムオーダーフィールド<br>
                            　→フィールドを追加<br>
                            　　・フィールドキー：service_item_line<br>
                            　　・フィールド名：Line名(自由に変えてOK)<br>
                            　　・テキスト<br>
                            　　・必須は必要に応じて
                        </td>
                    </tr>
                    <!-- ================================================================== -->
                    <tr>
                        <td>Welcart箱詰め設定</td>
                        <td>
                            <div class="form-check">
                                <input
                                    type="checkbox"
                                    name="packing_feature_is_check"
                                    id="packing_feature_is_check"
                                    value="1"
                                    class="form-check-input"
                                    <?php checked($packingFeatureIsCheckVal, '1'); ?>>
                                <label for="packing_feature_is_check" class="form-check-label">
                                    利用
                                </label>
                            </div>
                        </td>
                        <td>有効にすると、サイドメニューに「箱詰め設定」が表示されます。必須設定を登録して基本設定の保存に成功すると、送料計算を開始します。</td>
                    </tr>
                    <!-- ================================================================== -->
                    <tr>
                        <td>月次バックアップ</td>
                        <td>
                            <div class="form-check">
                                <input
                                    type="checkbox"
                                    name="monthly_backup_is_check"
                                    id="monthly_backup_is_check"
                                    value="1"
                                    class="form-check-input"
                                    <?php checked($monthlyBackupIsCheckVal, '1'); ?>>
                                <label for="monthly_backup_is_check" class="form-check-label">
                                    利用
                                </label>
                            </div>
							<div id='monthly_backup_password_wrap' class='mt-2' <?php echo '1' === $monthlyBackupIsCheckVal && $monthlyBackupEncryptionAvailable ? '' : 'hidden'; ?>>
								<label for='monthly_backup_password' class='form-label'>暗号化パスワード</label>
								<div class='sosc-password-field'>
									<input type='password' name='monthly_backup_password' id='monthly_backup_password' class='form-control form-control-sm' minlength='12' maxlength='128' autocomplete='new-password' aria-describedby='monthly_backup_password_help'>
									<button type='button' id='monthly_backup_password_toggle' class='sosc-password-toggle' aria-controls='monthly_backup_password' aria-pressed='false' aria-label='パスワードを表示'>
										<span class='dashicons dashicons-visibility' aria-hidden='true'></span>
									</button>
								</div>
								<small id='monthly_backup_password_help' class='form-text text-muted'>
									<?php echo esc_html($monthlyBackupPasswordIsSet ? '設定済みです。空欄のまま保存しても現在のパスワードを維持します。変更する場合は12文字以上128文字以下で入力してください。' : '暗号化する場合は12文字以上128文字以下で入力してください。'); ?>
								</small>
								<?php if ($monthlyBackupPasswordIsSet) : ?>
									<label class='form-check mt-2'><input class='form-check-input' type='checkbox' name='monthly_backup_password_remove' value='1'> 保存済み暗号化パスワードを解除する</label>
								<?php endif; ?>
							</div>
							<p id='monthly_backup_encryption_unavailable' class='mt-2 mb-0 text-muted' <?php echo '1' === $monthlyBackupIsCheckVal && ! $monthlyBackupEncryptionAvailable ? '' : 'hidden'; ?>>このサーバーでは暗号化ZIPを利用できないため、暗号化なしでバックアップします。</p>
						</td>
						<td>
							毎月末日の午前3時以降に、WordPressファイルとDBをZIPで保存します。<br>
							パスワードを入力するとAES-256暗号化を必須にし、非対応サーバーでは平文ZIPを作らずバックアップを中止します。<br>
							保存済みパスワードがある場合、空欄のまま保存しても暗号化設定を維持します。<br>
							成功したバックアップを6世代保持します。
						</td>
                    </tr>
                    <!-- ================================================================== -->
                </tbody>
            </table>
            <input type="submit" class="btn btn-primary" value="保存" />
        </form>
    </div>
</div>
<style>
.sosc-password-field {
	position: relative;
}
.sosc-password-field input {
	padding-right: 40px;
}
.sosc-password-toggle {
	position: absolute;
	top: 50%;
	right: 4px;
	display: inline-flex;
	align-items: center;
	justify-content: center;
	width: 32px;
	height: 28px;
	padding: 0;
	color: #50575e;
	background: transparent;
	border: 0;
	border-radius: 3px;
	transform: translateY(-50%);
	cursor: pointer;
}
.sosc-password-toggle:hover,
.sosc-password-toggle:focus {
	color: #2271b1;
}
.sosc-password-toggle:focus-visible {
	outline: 2px solid #2271b1;
	outline-offset: -2px;
}
</style>
<script>
document.addEventListener('DOMContentLoaded', function () {
	const enabled = document.getElementById('monthly_backup_is_check');
	const wrap = document.getElementById('monthly_backup_password_wrap');
	const password = document.getElementById('monthly_backup_password');
	const toggle = document.getElementById('monthly_backup_password_toggle');
	const unavailable = document.getElementById('monthly_backup_encryption_unavailable');
	const backupTab = document.getElementById('sosc-backup-tab-item');
	const encryptionAvailable = <?php echo $monthlyBackupEncryptionAvailable ? 'true' : 'false'; ?>;
	if (!enabled || !wrap || !password || !toggle) {
		return;
	}
	const updateVisibility = function () {
		wrap.hidden = !enabled.checked || !encryptionAvailable;
		password.disabled = !enabled.checked || !encryptionAvailable;
		if (unavailable) {
			unavailable.hidden = !enabled.checked || encryptionAvailable;
		}
		if (backupTab) {
			backupTab.hidden = !enabled.checked;
		}
	};
	enabled.addEventListener('change', updateVisibility);
	toggle.addEventListener('click', function () {
		const show = password.type === 'password';
		password.type = show ? 'text' : 'password';
		toggle.setAttribute('aria-pressed', show ? 'true' : 'false');
		toggle.setAttribute('aria-label', show ? 'パスワードを隠す' : 'パスワードを表示');
		const icon = toggle.querySelector('.dashicons');
		if (icon) {
			icon.classList.toggle('dashicons-visibility', !show);
			icon.classList.toggle('dashicons-hidden', show);
		}
	});
	updateVisibility();
});
</script>

<!--
    // 先頭テキスト
    $beforText = '';
    if (isset($atts['befor_text'])) {
        $beforText = $atts['befor_text'];
    }
    // 後部テキスト
    $afterText = '';
    if (isset($atts['after_text'])) {
        $afterText = $atts['after_text'];
    }
    // リダイレクト先
    $redirectUrl = home_url('/');
    if (isset($atts['redirect_url'])) {
        $redirectUrl = $atts['redirect_url'];
    }
    // 有効期限単位
    $unitBase = 'd';    //Mwdhmsのどれか
    if (isset($atts['unit_base'])) {
        $unitBase = $atts['unit_base'];
    }
    // 期限切れ再アクセス禁止フラグ
    $isAccessBlocked = true;
    if (isset($atts['is_access_blocked'])) {
        $isAccessBlocked = $atts['is_access_blocked'];
    }
    // ミリ秒表示
    $isMilliseconds = true;
    if (isset($atts['is_milliseconds'])) {
        $isMilliseconds = $atts['is_milliseconds'];
    }
    // 有効期限
    $expiry = $atts['expiry'];
---!>
