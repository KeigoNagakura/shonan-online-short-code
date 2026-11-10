---
name: wp-plugin-visual-regression
description: wp-pluginのCSS、HTML、ショートコード、管理画面など表示に影響する変更を修正した後、固定サンプルの実ブラウザ画像を正規デザインキャプチャと比較し、デザイン崩れを判定するときに使用する。表示に影響しない変更には使用しない。
---

# wp-plugin デザイン回帰確認

`project/wp-plugin` の表示変更後、[デザイン基準画像](../../../tests/visual-baseline/README.md)と同じ条件で再撮影し、画像差分と実寸を確認する。基準との差が出た場合は、意図した変更か崩れかを画面で判断する。自動比較の成功だけでデザインの正しさを断定しない。

## 対象と開始条件

- CSS、テンプレート、HTML、画面に出るJavaScript、ショートコード出力、管理画面、画像やフォントに影響する修正の後に使う。表示に影響しないPHP処理やPDF本文だけの変更には使わない。
- `osaru-wp` のローカルComposeとWordPressが対象であることを確認する。Dockerエンジンが停止しPHP等の検証に必要なら、共通 `AGENTS.md` に従って起動許可を確認する。別Composeや既存コンテナを無断で停止しない。
- 作業ツリー、基準画像、[環境条件](../../../tests/visual-baseline/20261020-v3/environment.json)を確認する。WordPress、テーマ、so-short-code、Welcartの版とWelcartの有効状態を `wp core version`、`wp theme get`、`wp plugin get` などで確認する。基準画像とmanifestは比較中に変更しない。

## 公開画面

1. `docker cp tests/visual-fixture.php osaru-wp-web-1:/tmp/sosc-visual-fixture.php` でfixtureを配置し、`docker exec --user www-data osaru-wp-web-1 wp eval-file /tmp/sosc-visual-fixture.php status` を実行する。既存fixtureがあれば所有と内容を確認し、無断削除しない。`posts=0 terms=0 skus=0` なら同じ `wp eval-file` の末尾を `create` にして固定サンプルを作る。`create` は同名投稿・分類・SKUが残る場合に拒否する。
2. プロジェクトルートで `node --experimental-websocket tests/visual/capture.cjs compare` を実行する。出力は `.local/visual-current/`、現行基準は `tests/visual-baseline/20261020-v3/`。320・390・768・1280pxのページ全体と各部品を撮影し、ページ幅、描画、画像の読込、ファイルhashを照合する。撮影器は画像読込とページ先頭への復帰後に2秒待ち、撮影中だけSWELLの `#pagetop` を非表示にする。
3. `comparison.json` を読み、画像差分は画素数にかかわらず新旧画像を開いて原因を調べる。比較器は差分が小さくても終了コード2で止まる。テーマ由来の差なら範囲と原因を記録し、プラグイン由来の差は意図した変更か崩れか判断する。部品の欠落、文字切れ、画像欠落、横はみ出し、ボタン・入力欄の重なりを目視する。
   終了コード1は撮影失敗で、比較レポートは生成されない。失敗時に古い画像ファイルが出力先へ残る場合があるため、レポート不在の画像を今回の合格証拠に使わない。
4. 自分で作成したfixtureだけを同じ `wp eval-file` の `cleanup` で削除し、再度 `status` で `posts=0 terms=0 skus=0` を確認する。`cleanup` は所有印のある投稿・分類と該当商品の専用SKUだけを対象にする。途中失敗でも同じ確認を行う。最後にコンテナ内の一時コピーを削除する。

## 管理画面

- `.local/credentials.clixml` の `WP_ADMIN_PASSWORD` をPowerShellで復号し、`SOSC_ADMIN_PASSWORD` として子プロセスへ一時的に渡す。例えば `Import-Clixml` と `[Net.NetworkCredential]::new('', $secrets['WP_ADMIN_PASSWORD']).Password` を使い、`try/finally` で環境変数を消す。値はコマンド文字列、画面、文書、ログへ出さない。
- `node --experimental-websocket tests/visual/admin-capture.cjs compare` を実行し、`.local/visual-admin/admin-comparison.json` と新旧画像を確認する。ショートコード、Welcart、その他の3タブを320・390・1280pxで比較する。
- 管理画面も終了コード1では比較失敗として扱い、レポート不在の画像を今回の合格証拠に使わない。撮影後に `.local/visual-chrome-*` と `.local/visual-admin-chrome-*` の残存を確認し、残っていれば利用中プロセスと対象パスを確認する。
- PowerShellの `try/finally` で認証情報を消す場合、Node実行直後の `$LASTEXITCODE` を変数へ保持し、最後にその値を終了コードへ反映する。差分ありの終了コードをPowerShellの正常終了で隠さない。
- WordPressやテーマの通知、管理バーの変化とプラグイン領域の変化を分ける。表の横スクロールは意図した表示だが、種別・名前が一文字ずつ縦に折れる状態は不合格とする。
- 月次バックアップタブは保存先の識別子を表示するため、Git管理する基準画像には含めない。変更対象ならローカルで実表示を確認し、画像を保存・共有する前に機密表示を点検する。箱詰めの注文画面も注文情報を含むため、この固定サンプル基準の対象外とする。

## 判定と基準の更新

- 未判定の画像差分、欠落した画像・部品、横はみ出し、未読込画像、見た目で確認した崩れが残る場合は合格としない。差分を目視で許容した場合は画像名、座標、原因、判断を記録する。ブラウザやテーマの版が異なる場合は比較条件の違いを先に特定する。
- 基準画像は現行表示を正解にするためだけに更新しない。意図したデザイン変更を差分画像で確認し、変更理由、対象幅、影響を記録したうえで、`SOSC_BASELINE_DATE` に新しい `YYYYMMDD` または `YYYYMMDD-vN` を指定して `baseline` モードで別ディレクトリへ作る。両方のmanifestと環境条件を保存し、既存版を上書きしない。新しい版を既定の比較対象に切り替えるときは、差分を確認して両captureスクリプトの既定版と基準READMEを更新する。
- 結果には比較対象、修正点、画像差分、未確認条件、fixtureの後片付けを記録する。基準との一致はサンプル条件での回帰なしを示し、実データや別ブラウザの品質保証へ拡大しない。

登録先: [wp-plugin Agent・Skill一覧](../../../doc/Agent・Skill一覧_20260930.md)
