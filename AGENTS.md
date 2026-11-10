# wp-plugin プログラムワークスペースAgent

- 状態: `active`
- プログラムID: `wp-plugin`
- 対象: `C:\osaruAi\project\wp-plugin`以下
- 目的: 湘南オンラインプラグイン（so-short-code）のローカル開発
- 最終更新日: 2026-09-30
- 台帳: [プロジェクトAgent・Skill一覧](doc/Agent・Skill一覧_20260930.md)

## 上位規則と範囲

基盤ルートの[AGENTS.md](../../AGENTS.md)、会社・基盤共通ルール、タスクルーターを継承する。本定義で上位規則を緩和しない。別プログラム・顧客・本番は対象外。関連顧客、本番環境、公開・配布条件は未確認であり、関係する外部公開・共有データ変更・本番操作は確定するまで行わない。

## 正本と保存先

- 開発の入口: [README](README.md)
- 起動・停止・検証・データ保全: [Docker運用書](doc/運用/Docker開発環境_20260927.md)
- 実行構成: [compose.yml](compose.yml)、server/docker/
- 自作ソース: src/so-short-code/
- WordPress初期配置元: site/
- 仕様・運用・監査資料: doc/。分類は[文書案内](doc/README.md)を参照。
- 会話記録: log/。秘密情報を含めずGit対象外とする。
- ローカル認証: `.local/credentials.clixml`はWindows DPAPI暗号化。`.local/tls`は共通ローカルCAが署名したwp-plugin専用leaf証明書・秘密鍵。いずれも会話・文書・Gitへ転記しない。

## 開発環境とデータ保全

Compose名はosaru-wp。構成・バージョン・コマンドは運用書を正本とする。実行データは専用ボリュームへ保持する。siteは初回コピー元であり、buildだけで既存ボリュームを上書き更新しない。

初期SQLは新規DBに一度だけ投入する。稼働済みDBへの再投入、volume削除、本番変更、旧環境の破棄は対象・操作・復旧方法を確認してから扱う。旧環境の停止コンテナ内には現配置にないテスト・SQLが残っており、未回収のまま削除しない。旧データ名・バックアップ・未コミット変更は上位規則に従って保護する。

## Skillと検証

開発DBのスナップショット、manifest、SQL、ユーザー情報、パスワードハッシュ、保存先トークンはGitへ含めない。PR作成時は対象変更だけを扱い、DBのexport・importを自動実行しない。マージ後にmainへ戻る場合もDBを自動復元しない。ローカルDBの保全・復元が明示された場合だけ対象と復旧方法を確認する。[wp-plugin-db-snapshot](.agents/skills/wp-plugin-db-snapshot/SKILL.md)の旧Git連動手順は廃止した。その他の依頼では、主目的から上位タスクルーターの必須Skillを選ぶ。

アプリケーション変更は内容に応じて検証し、現在の共通起動ShellのVerifyを利用する。過去資料の合格記録を現環境での合格と扱わない。commit・push・PR・公開はそれぞれ依頼された範囲で行う。

CSS、HTML、テンプレート、ショートコード出力、管理画面などデザインに影響する変更を修正した後は、[wp-pluginデザイン回帰Skill](.agents/skills/wp-plugin-visual-regression/SKILL.md)で[正規デザインキャプチャ](tests/visual-baseline/README.md)と実ブラウザ画像を比較する。差分があれば目視で原因を確認し、未解決の崩れを合格扱いにしない。基準画像の更新は意図したデザイン変更を確認して別版として保存し、既存版を上書きしない。
