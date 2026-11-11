# wp-plugin — WordPressプラグイン開発環境

湘南オンラインプラグイン（so-short-code）のローカル開発用ワークスペース。最終更新日: 2026-11-11。プラグインの対応PHPは8.2以上。現在のローカル開発環境はPHP 8.3を使用する。

管理者向けの機能別マニュアルは、WordPress管理画面の「湘南オンラインプラグイン」→「使い方マニュアル」から開く。目次と機能別ページはプラグインに同梱され、画像も同じパッケージから表示する。

月次バックアップはZIPの作成と管理者向けダウンロードまでを提供する。**プラグイン内の復元機能は設けない。** 復旧が必要なときは、サーバー管理者がZIP同梱の手順を参照してプラグイン外で実施する。

新しい公開リポジトリの`main`へPRをマージすると、GitHub Actionsが更新ZIPをGitHub Releaseに自動公開する。手順と初回手動適用の条件は[Git連動プラグイン自動更新](doc/運用/Git連動プラグイン更新配布_20261110.md)を参照する。

## 接続先

| 用途 | 接続先 |
| --- | --- |
| サイト | https://localhost/ |
| WordPress管理画面 | https://localhost/wp-admin/ |
| phpMyAdmin | http://localhost:8080/ — wordpress DBへ自動接続 |
| 管理者 | `wp_local_admin` |

## 起動・停止・検証

Docker Desktopを起動してPowerShellから実行する。

```powershell
& C:/osaruAi/shell/WordPress開発環境_20260927.ps1 -ProjectRoot C:/osaruAi/project/wp-plugin -Mode Start
```

同じコマンドの`-Mode`で操作する。`Start`は共通ローカルCAを初回だけ信頼し、wp-plugin専用localhost証明書を準備してから、他のローカルComposeプロジェクトをボリューム保持のまま停止してポートを切り替える。初期SQLがある場合は復元し、ない新規環境では空の専用DBへWordPressを初期構築する。`Stop`はデータを保持して停止、`Status`は状態表示、`Verify`はWordPressとphpMyAdminを検証、`Password`は管理者パスワードをクリップボードへコピーする。`Trust`は共通CAの信頼状態だけを確認・登録する。

詳細は[Docker開発環境の運用](doc/運用/Docker開発環境_20260927.md)を参照する。

正規URLは https://localhost/ と https://localhost/wp-admin/。HTTPは互換確認用で、WordPressのURLはHTTPSへ統一する。

## 構成

| パス | 用途 |
| --- | --- |
| `compose.yml` | Web・MySQL・phpMyAdminの構成。Compose名はosaru-wp |
| `server/docker/` | Dockerfile、Web/PHP設定、初期化・検証コード |
| `server/` のその他の領域 | 実サーバーのデータや差分（diff）など。Dockerのビルド対象外 |
| `src/so-short-code/` | 自作プラグイン。編集を実行環境へ即反映 |
| `site/` | WordPress本体。初回ボリューム作成時のコピー元 |
| `db/init/` | ローカル専用の初期SQL。Git対象外 |
| `db/backups/` | 保全したバックアップ。Git対象外 |
| `doc/` | 仕様・運用・過去の監査記録。[文書案内](doc/README.md)を参照 |
| `log/` | 会話記録。Git対象外 |
| `.local/` | 暗号化認証情報とwp-plugin専用の証明書・秘密鍵。Git対象外 |
| `.agents/skills/wp-plugin-db-snapshot/` | 廃止したDBのGit連動手順の記録 |
| `server/db_dump/` | ローカル専用のDBスナップショット。Git対象外 |
| `shell/` | プロジェクト固有のローカルDB保全用Shell |

本体・初期SQL・Composer依存は再構築に必要なので保持する。旧配置の停止コンテナには未回収テストやSQLが残るため保全する。DBボリュームを削除するdown -vは通常運用では使わない。

DBスナップショット、SQL、ユーザー情報、パスワードハッシュ、保存先トークンはGitへ含めない。PR作成時とmain同期時にDBの保存・復元は自動実行しない。ローカルDBの保全・復元は明示依頼がある場合に、対象と復旧方法を確認して行う。

<details>
<summary>参考資料</summary>

- [プログラムAgent](AGENTS.md)
- [Compose](compose.yml)
- [Docker運用書](doc/運用/Docker開発環境_20260927.md)

</details>

<details>
<summary>不明・怪しい・推測事項</summary>

- 本番環境・配布条件・関連顧客は未確認。このワークスペースはローカル開発用。
- 過去資料が参照するtests・docker資材は現配置にない。現在の検証は運用書を参照する。

</details>
