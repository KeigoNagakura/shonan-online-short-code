# wp-plugin Agent・Skill一覧

- 初版: 2026-09-30、最終更新日: 2026-10-20
- 対象: `project/wp-plugin`

この文書は、wp-plugin固有のAgent・Skillを管理するプロジェクト内台帳である。会社共通のAgent・Skill台帳へプログラム固有処理を混在させない。

## Agent

| 名前 | 状態 | 対象 | 定義 |
| --- | --- | --- | --- |
| wp-plugin プログラムワークスペースAgent | active | wp-pluginの開発・運用 | [AGENTS.md](../AGENTS.md) |

## Skill

| 名前 | 表示名 | 用途 | 使用条件 | 使用しない条件 | 適用範囲 | 必須・追加Skill | 状態 | 定義 | 最終更新日 |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| wp-plugin-db-snapshot | テストDBスナップショット（廃止） | PR・main同期とDBの自動連動を停止した記録 | 使用しない | PR、main同期、本番DB、通常作業 | wp-pluginの旧ローカルDB運用 | なし。DB保全は明示依頼で別途判断 | retired | [SKILL.md](../.agents/skills/wp-plugin-db-snapshot/SKILL.md) | 2026-11-10 |
| wp-plugin-visual-regression | デザイン回帰確認 | 公開画面と管理画面を実ブラウザで撮影し、正規デザインキャプチャと比較する | CSS・HTML・ショートコード・管理画面など表示に影響する修正後 | 表示に影響しない変更、基準画像の無審査更新 | wp-pluginのローカル開発画面 | 必要時に`verification-review` | active | [SKILL.md](../.agents/skills/wp-plugin-visual-regression/SKILL.md) | 2026-10-20 |

## 関連実装

- [WordPress DBスナップショットShell](../shell/WordPressDBスナップショット_20260930.ps1) — ローカル専用の旧運用資産。出力先の`server/db_dump`はGit除外。PR・main同期では自動実行しない。
- [正規デザインキャプチャ](../tests/visual-baseline/README.md) — 公開画面15ショートコードと管理画面3タブの基準画像、環境条件、比較manifest。

<details>
<summary>参考資料</summary>

### 一次情報

- [プログラムAgent](../AGENTS.md) — Skillの強制適用条件。
- [Compose定義](../compose.yml) — 対象Compose、サービス、DBの定義。

### 二次情報

- なし

</details>

<details>
<summary>不明・怪しい・推測事項</summary>

- 既存のローカルスナップショットには会社ドメインのメールアドレス等が含まれるため、公開リポジトリへ含めない。履歴の整理は別途確認を要する。

</details>
