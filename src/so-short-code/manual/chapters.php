<?php
/** 管理画面マニュアルの本文。表示例は同梱の固定サンプル画像を使用する。 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$chapters = array();
$shortcodes = array(
	array( 'info-list01', 'お知らせリスト01', 'お知らせ・投稿', '新着情報を日付とタイトルの一覧で表示します。', '[sosc_info_list01 slug="news" list="6"]', 'info-1280-sosc_info_list01.png', 'お知らせ用カテゴリを作り、投稿を登録します。', 'slugにはカテゴリのスラッグを指定します。listを省くと5件です。' ),
	array( 'info-list02', 'お知らせリスト02', 'お知らせ・投稿', '投稿を和暦年度とタイトルで一覧表示します。', '[sosc_info_list02 slug="works" list="6"]', 'info-1280-sosc_info_list02.png', '年度別に見せたい投稿をカテゴリへ登録します。', '和暦年度は投稿日の年から計算します。' ),
	array( 'info-list03', 'お知らせリスト03', 'お知らせ・投稿', '実績を発注元・内容・エリア・年度の4列で見せます。', '[sosc_info_list03 slug="works" list="10" orderSlug="order" yearSlug="year" areaSlug="area"]', 'info-1280-sosc_info_list03.png', '実績投稿と、発注元・年度・エリアの子カテゴリを登録します。', '親カテゴリのスラッグを変更した場合はorderSlug・yearSlug・areaSlugも指定します。' ),
	array( 'info-list04', 'お知らせリスト04', 'お知らせ・投稿', '指定カテゴリの投稿を画像付きカードで並べます。', '[sosc_info_list04 slug="column" list="6"]', 'info-1280-sosc_info_list04.png', '表示する投稿をカテゴリへ登録します。アイキャッチ画像と抜粋は必要に応じて設定します。', '画像がない場合は代替画像、抜粋がない場合は本文の一部を表示します。公開前に見え方を確認してください。' ),
	array( 'info-list05', 'お知らせリスト05', 'お知らせ・投稿', '投稿の日付とタイトルを罫線付きの簡潔な一覧で表示します。', '[sosc_info_list05 slug="news" list="10"]', 'info-1280-sosc_info_list05.png', '表示したい投稿をカテゴリに登録します。', 'listを省くと5件です。' ),
	array( 'tag-view01', 'タグ表示01', 'お知らせ・投稿', '指定したタグをリンク付きで見せます。', '[sosc_tag_view01 tag="重要,採用,イベント"]', 'cards-1280-sosc_tag_view01.png', '表示するタグを作成します。', 'tagには登録済みのタグ名を半角カンマ区切りで指定します。タグ名の前後に空白を入れないでください。' ),
	array( 'blog-card01', 'ブログカード01', 'お知らせ・投稿', '公開投稿を画像・タイトル・抜粋付きの1枚のカードで紹介します。', '[sosc_blog_card01 slug="sample-post" link="/sample-post/"]', 'cards-1280-sosc_blog_card01.png', '紹介する投稿を公開します。アイキャッチ画像と抜粋は必要に応じて設定します。', 'linkは自動補完されません。正しいリンク先を指定してください。画像がない場合は代替画像を表示します。' ),
	array( 'target-content', 'LP内容の共通表示', 'お知らせ・投稿', '共通の案内文を1か所で管理し、複数ページへ表示します。', '[sosc_target_content slug="common-contact"]', 'cards-1280-sosc_target_content.png', '共通本文をカスタム投稿タイプ「LP」で公開します。', '存在しないLPのスラッグを指定すると表示されません。' ),
	array( 'item-card01', '商品カード01', 'Welcartの商品表示', '商品画像・説明・価格を横長カードで紹介します。', '[sosc_wc_item_card01 post_name="item-slug"]', 'welcart-1280-sosc_wc_item_card01.png', 'Welcartで公開商品・SKU・商品画像を登録します。', 'post_nameには商品のスラッグを指定します。価格は最低～最高価格を表示します。' ),
	array( 'item-card02', '商品カード02', 'Welcartの商品表示', '商品を正方形画像と価格のコンパクトなカードで表示します。', '[sosc_wc_item_card02 post_name="item-slug"]', 'welcart-1280-sosc_wc_item_card02.png', 'Welcartで公開商品・SKU・商品画像を登録します。', '複数SKUでは最低～最高価格を表示します。' ),
	array( 'item-card03', '商品カード03', 'Welcartの商品表示', '商品を正方形画像と最低価格で簡潔に紹介します。', '[sosc_wc_item_card03 post_name="item-slug"]', 'welcart-1280-sosc_wc_item_card03.png', 'Welcartで公開商品・SKU・商品画像を登録します。', '複数SKUでも最低価格だけを表示します。' ),
	array( 'item-list01', '商品リスト01', 'Welcartの商品表示', 'カテゴリ内の商品を横長カードの縦1列で表示します。', '[sosc_wc_item_list01 slug="item-category" list="6"]', 'welcart-1280-sosc_wc_item_list01.png', 'Welcartの商品カテゴリに公開商品を登録します。', 'listを省くと5件です。' ),
	array( 'item-list02', '商品リスト02', 'Welcartの商品表示', '商品を画像付きカードのグリッドで一覧表示します。', '[sosc_wc_item_list02 slug="item-category" list="6"]', 'welcart-1280-sosc_wc_item_list02.png', 'Welcartの商品カテゴリに公開商品を登録します。', '小画面では2列、中画面以上では3列で表示します。' ),
	array( 'item-list03', '商品リスト03', 'Welcartの商品表示', '最低価格を示す商品カードをグリッドで一覧表示します。', '[sosc_wc_item_list03 slug="item-category" list="6"]', 'welcart-1280-sosc_wc_item_list03.png', 'Welcartの商品カテゴリに公開商品を登録します。', '小画面では2列、中画面以上では3列で表示します。' ),
	array( 'documents', '請求書・領収書のダウンロード', 'Welcartの注文・配送', '購入者が注文番号とメールアドレスを入力し、対象の帳票PDFを取得できます。', '[sosc_wc_documents]', 'welcart-1280-sosc_wc_documents.png', 'Welcartを有効にし、固定ページへショートコードを設置します。', '領収書は入金済み注文だけです。ページとダウンロード先をキャッシュ対象から除外し、HTTPSで公開してください。' ),
);

foreach ( $shortcodes as $item ) {
	$chapters[ $item[0] ] = array(
		'title' => $item[1], 'group' => $item[2], 'summary' => $item[3],
		'code' => $item[4], 'image' => $item[5], 'preparation' => $item[6], 'note' => $item[7],
		'steps' => array( '表示したい固定ページまたは投稿を編集する', '「ショートコード」ブロックに記述例を貼り、必要な値を置き換える', 'プレビューで表示内容とリンク先を確認してから公開する' ),
	);
}
$chapters['documents']['steps'] = array( 'Welcartを有効にし、帳票案内用の固定ページを編集する', '「ショートコード」ブロックへ [sosc_wc_documents] を配置する', 'HTTPSでフォームを確認し、テスト注文でダウンロード条件を確認する' );

$chapters['hide-h1'] = array( 'title' => 'H1タグ非表示', 'group' => '表示・フォーム調整', 'summary' => '指定ページの見出しH1を非表示にします。', 'preparation' => '外観のメニューで「none_h1」を作成し、対象ページを追加します。', 'steps' => array( '外観 → メニューで none_h1 を作る', '対象ページをメニューへ追加する', '湘南オンラインプラグイン → その他で「h1タグ非表示」を有効にして保存する' ), 'note' => '登録URLと表示中ページのURLが完全一致した場合に適用します。' );
$chapters['top-margin'] = array( 'title' => 'コンテンツ上余白の調整', 'group' => '表示・フォーム調整', 'summary' => '指定ページの本文上側の余白をなくします。', 'preparation' => '外観のメニューで「none_contents_top_margin」を作成します。', 'steps' => array( '対象ページを none_contents_top_margin に追加する', '湘南オンラインプラグイン → その他で機能を有効にする', '保存後、対象ページの上余白を確認する' ), 'note' => '対象外ページの余白は変更しません。' );
$chapters['bottom-margin'] = array( 'title' => 'コンテンツ下余白の調整', 'group' => '表示・フォーム調整', 'summary' => '指定ページの本文下側の余白をなくします。', 'preparation' => '外観のメニューで「none_contents_bottom_margin」を作成します。', 'steps' => array( '対象ページを none_contents_bottom_margin に追加する', '湘南オンラインプラグイン → その他で機能を有効にする', '保存後、フッターとの間隔を確認する' ), 'note' => 'SWELLとTwenty Twenty-Fiveで調整する場所が異なります。' );
$chapters['font'] = array( 'title' => 'サイトのフォント指定', 'group' => '表示・フォーム調整', 'summary' => 'サイト全体の文字を端末の明朝体へ変更できます。', 'preparation' => 'テーマ側のフォント設定を確認します。', 'steps' => array( '湘南オンラインプラグイン → その他を開く', 'フォント指定で「変更しない」または「端末の明朝体」を選ぶ', '保存後、代表的な公開ページを確認する' ), 'note' => '外部フォントへの通信は行いません。サイト全体に影響するため、テーマとの組み合わせを確認してください。' );
$chapters['contact-form'] = array( 'title' => 'お問い合わせフォームの表示調整', 'group' => '表示・フォーム調整', 'summary' => 'Contact Form 7のフォーム幅と入力欄・送信ボタンの配置を整えます。', 'preparation' => 'Contact Form 7でフォームを作成し、ページへ配置します。', 'steps' => array( '湘南オンラインプラグイン → その他で「コンタクトフォーム修正」を有効にする', '設定を保存する', 'スマートフォンとパソコンで問い合わせページを確認する' ), 'note' => '調整はContact Form 7のフォームに限定します。' );
$chapters['pickup'] = array( 'title' => '店頭受け取り', 'group' => 'Welcartの注文・配送', 'summary' => '購入者が「受け取り方法」から店頭受け取りを選べます。', 'preparation' => 'Welcartで名称が「店頭受け取り」の配送方法、送料無料、受取用の日数と時間帯を設定します。商品には必要に応じて発送目安を設定します。', 'steps' => array( 'Welcartで店頭受け取り用の配送方法と希望日時を設定する', '湘南オンラインプラグイン → その他で「Welcart店頭受取機能」を有効にして保存する', '購入画面で「店頭受け取り」を選び、配送先が隠れ、代引きが選べないことを確認する' ), 'note' => '通常配送では配送先が支払い方法の下に表示されます。店頭受け取りでは配送先を隠し、希望日時の見出しを「お受け取り希望日」「お受け取り時間」に変えます。' );
$chapters['service-item'] = array( 'title' => 'サービス商品', 'group' => 'Welcartの注文・配送', 'summary' => '配送しないサービス商品の購入画面を簡潔にします。', 'preparation' => 'Welcartに「サービス商品」配送方法を作成し、必要ならカスタムオーダーフィールド service_item_line を登録します。', 'steps' => array( 'Welcartでサービス商品用の配送方法と対象商品を準備する', '湘南オンラインプラグイン → その他で「Welcartサービス商品機能」を有効にする', '購入画面で配送先・希望日時が隠れ、必要な入力欄だけ残ることを確認する' ), 'note' => '同じ画面に「店頭受け取り」配送方法がある場合、この自動表示制御は行いません。' );
$chapters['animation'] = array( 'title' => 'メディアとテキストの表示演出', 'group' => '表示・フォーム調整', 'summary' => 'SWELLの「メディアとテキスト」ブロックを、画面に入ったときに表示する演出です。', 'preparation' => 'SWELLで対象ブロックを配置します。', 'steps' => array( '対象の「メディアとテキスト」ブロックを選ぶ', '追加CSSクラスへ sosc-reveal-media を入力する', '公開ページでスクロールして演出を確認する' ), 'note' => '演出は対象ブロックにクラスを付けた場合だけです。' );
$chapters['vertical-banner'] = array( 'title' => 'バナーの縦書き文字', 'group' => '表示・フォーム調整', 'summary' => 'SWELLのバナーリンク内の文字を中央揃えの縦書きにします。', 'preparation' => 'SWELLのバナーリンクブロックを配置し、画像と文字を設定します。', 'steps' => array( '対象のバナーリンクブロックを選ぶ', '高度な設定の追加CSSクラスへ virtical-text を入力する', '公開ページで文字の位置とリンク先を確認する' ), 'note' => 'クラス名は実装どおり virtical-text と入力します。文字数が多い場合は画像との重なりを確認してください。', 'preview' => 'vertical' );
$chapters['h2-style'] = array( 'title' => '見出しH2の装飾', 'group' => '表示・フォーム調整', 'summary' => 'SWELLのH2見出しを青い左線と下線のデザインに変えます。', 'preparation' => 'SWELLでH2見出しブロックを配置します。', 'steps' => array( '対象のH2見出しブロックを選ぶ', '高度な設定の追加CSSクラスへ sosc_h2_sw_type1 を入力する', '公開ページで見出しの色と余白を確認する' ), 'note' => 'H2見出しにだけ適用します。テーマの装飾を置き換えるため、公開ページで確認してください。', 'preview' => 'h2' );
$chapters['updates'] = array( 'title' => 'プラグイン更新', 'group' => '管理・運用', 'summary' => 'WordPressの通常のプラグイン画面で更新通知と詳細を確認できます。', 'preparation' => '更新前にサイトのバックアップと動作確認手順を用意します。', 'steps' => array( 'プラグイン画面で更新通知と詳細を確認する', '更新内容とバックアップを確認する', '通常のWordPress更新操作後に主要ページを確認する' ), 'note' => 'GitHub Release対応版は公開リリースから更新情報を取得します。旧更新サーバーを参照する版からの初回切替には手動でZIPを適用します。下の「このサイトの動作状態」でWordPressのCronと自動更新設定を確認できます。' );
$chapters['packing-setup'] = array( 'title' => '箱詰め送料の初期設定', 'group' => '管理・運用', 'summary' => '商品寸法と外箱を使い、注文に合う箱数と送料を計算します。', 'preparation' => 'Welcartの配送方法・送料と、商品のSKU寸法を確認します。', 'steps' => array( '湘南オンラインプラグイン → その他で箱詰め設定を有効にする', '箱詰め設定で外箱・商品SKU寸法・必要な個別対応配送を登録する', '基本設定を保存し、準備検査と参考計算を確認する' ), 'note' => '有効化しただけでは送料計算を開始しません。準備検査に合格した保存後から動作します。' );
$chapters['packing-operation'] = array( 'title' => '箱詰め送料の日常運用', 'group' => '管理・運用', 'summary' => '送料計算の状況と個別対応が必要な注文を管理します。', 'preparation' => '箱詰め送料の初期設定と準備検査を完了します。', 'steps' => array( '箱詰め設定の運用ダッシュボードと商品設定状況を見る', '個別対応配送の注文を確認し、担当者・実発送方法・結果を記録する', 'システム状態と送料差額状況を確認する' ), 'note' => '数量上限や設定不足では購入が停止される場合があります。送料差額方式では購入者の明示同意が必要です。' );
$chapters['backup'] = array( 'title' => '月次バックアップ', 'group' => '管理・運用', 'summary' => 'WordPressのファイルとデータベースを月末にまとめて保存します。', 'preparation' => '保存先の容量・権限と、必要な暗号化パスワードの管理方法を確認します。', 'steps' => array( '湘南オンラインプラグイン → その他で月次バックアップを有効にする', '保存後に表示される「月次バックアップ」タブで設定と結果を確認する', '必要に応じて「今すぐバックアップ」を実行し、保存結果を確認する' ), 'note' => '正常に完成したバックアップを6世代保持します。このプラグインに復元機能はありません。復旧が必要な場合はサーバー管理者がZIP同梱の手順を使って手動で行います。下の「このサイトの動作状態」でCron設定と次回予約を確認できます。' );

// 実画面の画像がない機能には、機能ごとに内容を変えた操作図を示す。
$chapters['animation']['visual_steps'] = array( 'クラスを設定', '画面に入る', '表示演出' );
$chapters['updates']['visual_steps'] = array( '更新通知', 'バックアップ', '更新・確認' );
$chapters['packing-operation']['visual_steps'] = array( '状況を確認', '個別対応', '結果を記録' );
$chapters['backup']['visual_steps'] = array( '保存条件を設定', '月次実行', '結果を確認' );

foreach ( array( 'hide-h1', 'top-margin', 'bottom-margin', 'font', 'contact-form', 'pickup', 'service-item', 'packing-setup' ) as $setting_topic ) {
	$chapters[ $setting_topic ]['image']      = 'settings-' . $setting_topic . '.png';
	$chapters[ $setting_topic ]['image_kind'] = 'settings';
}

return $chapters;
