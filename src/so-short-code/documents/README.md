# Welcart 請求書・領収書ダウンロード

Welcartが有効な場合だけ `[sosc_wc_documents]` を登録します。固定ページのショートコードブロックへ記載すると、メールアドレス・注文番号と2つのダウンロードボタンを表示します。属性はありません。会員登録・ログインは不要です。Welcartが無効な場合、ショートコードは登録されません。

フォームの表示には、プラグインが読み込むスコープ付きBootstrap 5.2を使用します。入力欄は`form-control`、ボタンは`btn`を使い、狭い画面ではボタンを縦に並べます。テーマ全体へBootstrapのスタイルを適用しません。

## 設置と利用

1. WordPress管理画面でWelcartと湘南オンラインプラグインを有効化します。
2. 固定ページを作り、ショートコードブロックに `[sosc_wc_documents]` を単独で入力して公開します。
3. HTTPSの公開ページでフォームと2つのボタンが表示されることを確認します。ページキャッシュ・CDNキャッシュから掲載ページと `wp-admin/admin-post.php` を除外します。
4. 購入者は注文確認メールに記載されたメールアドレスと表示どおりの注文番号を入力し、請求書または領収書を選びます。

Welcartを停止するとショートコードは未登録になり、設置済みページには `[sosc_wc_documents]` の文字列がそのまま表示されます。公開ページで文字列を見せたくない場合は、Welcart停止前にページを非公開にしてください。

- 注文時のメールアドレスと、注文確認メールに記載された注文番号を入力します。接頭辞・ゼロ埋め・ランダム番号は表示どおり入力してください。内部の受注IDでは照合しません。
- 請求書はキャンセル・見積り以外の注文で取得できます。
- 領収書はさらに入金済み（`receipted`）で、未入金・決済保留を含まない注文に限定します。支払方法だけで入金済みとは判定しません。カード決済等でもWelcartの入金状態を確認してください。
- 不一致・取得対象外の応答は共通です。入力値はPOSTで送信し、URLに含めません。
- 15分単位で同一接続元IPは20回、同一メールアドレスは10回までです。成功した取得も数えます。IPの転送ヘッダーを信用せず、サーバーの `REMOTE_ADDR` を利用します。リバースプロキシ環境ではWebサーバー側で実IPを正しく設定してください。
- HTTPSで使用し、掲載ページと `wp-admin/admin-post.php` をページキャッシュ・CDNキャッシュの対象外にしてください。フォームnonceを古いキャッシュから配信すると取得できません。

## Welcartへの影響範囲

`so_short_code.php` が `Download.php` を明示的に読み込みます。名前空間は `ShonanOnline\ShortCode\Documents` です。新規テーブル、外部ライブラリ、追加CSS/JavaScriptは不要です。

Welcartの注文・注文メタは参照のみで、注文、入金状態、在庫、送料、会員権限、カートを更新する処理は追加しません。試行回数だけWordPress options内に期限付き・非autoloadで保存します。キーはサイトの秘密値を使ったハッシュで、メールアドレスやIPを平文保存しません。WordPressの通常のtransient清掃イベントで、この機能の期限切れカウンターを削除します。外部オブジェクトキャッシュを使用する場合もDB上のカウンターを清掃します。WP-Cronを停止している場合は定期実行を設定してください。

専用の `admin_post[_nopriv]_sosc_wc_documents` でnonceと注文照合・取得条件を確認してから、Welcart同梱の `includes/order_print.php` または軽減税率用 `order_print_ex.php` を実行します。管理者権限の付与、`order_action=pdfout` の送信、Welcart本体の改変は行いません。生成PDFはレスポンスで送信し、公開ディレクトリには保存しません。

Welcart 2.12.2の内部PDF処理に依存します。既存のPDF内容カスタマイズフックは実行されますが、任意の帳票ファイルへ置き換える `usces_filter_orderpdf_path[_ex]` は使用しません。独自のPDFフックがあるサイトでは、その処理の副作用と出力を事前に確認してください。Welcart更新時は通常税率・軽減税率のPDF取得と管理画面の帳票出力を再確認してください。

メールアドレスと注文番号を知っている人が取得できる方式です。メール所有確認やワンタイムパスワードは行いません。注文確認メールの共有先からも取得できる点を運用上考慮してください。

## 検証

現配置の `tests/documents-fixture.php` はローカル開発環境でテスト商品・商品明細付き注文・ショートコード設置ページを作成し、`paid`で入金済み、`reduced`で軽減税率8％へ切り替え、`status`で対象名の残数を確認し、`cleanup`で所有者マーカー付きのテストデータだけを削除する資材です。同じ識別名の注文・投稿が既にある場合、作成・削除を停止して残存物を報告します。HTTP検証はコンテナ内の `curl` で別途行います。取得したPDFは `tests/verify-pdf-text.py` でタイトル、商品、SKU、単価、税額、合計額の印字を確認できます。`cleanup` 実行後は、対象の商品・注文・注文明細・ページが0件であることを確認してください。2026-10-20に通常税率の請求書・領収書、軽減税率の請求書・領収書のPDF本文を抽出して照合しました。未入金領収書、誤メール、キャンセル、見積り、決済保留、nonce不正、Welcart OFF時のPOST拒否もHTTPで確認しました。試行回数制限は専用IP・メールを使い、IPの21回目とメールの11回目で429を確認しました。`tests/documents-pdf-probe.php` はWelcartのPDF描画処理へ渡る明細を追うための一時フックであり、検証後にコンテナから削除してください。

```powershell
docker cp tests/documents-fixture.php osaru-wp-web-1:/tmp/documents-fixture.php
docker exec --user www-data osaru-wp-web-1 wp eval-file /tmp/documents-fixture.php create
# 通常税率のHTTP検証後、入金済み・軽減税率の条件も確認
docker exec --user www-data osaru-wp-web-1 wp eval-file /tmp/documents-fixture.php paid
docker exec --user www-data osaru-wp-web-1 wp eval-file /tmp/documents-fixture.php reduced
# HTTP検証後、作成した商品・注文・明細・ページを削除
docker exec --user www-data osaru-wp-web-1 wp eval-file /tmp/documents-fixture.php cleanup
```

2026-09-11の追加監査では、WordPress 7.1 / PHP 8.3.33と、隔離したWordPress 6.0.3 / PHP 7.4.32の両方でWelcart 2.12.2.2608311の帳票実動作を確認しました。121注文の照合時のDB問い合わせを122回から2回へ削減し、タグ等を含む認証入力を補正せず拒否するよう修正しました。同時アクセス、権限、異常系、管理画面の既存PDF、複数ページ、帳票内容の比較まで含む結果・未確認範囲は [監査記録](../../../doc/点検記録/Welcart帳票ダウンロード監査_20260911.md) を参照してください。DB初期化・ボリューム再作成は不要です。
