---
name: wp-plugin-db-snapshot
description: 廃止済み。PR作成時のDBダンプ保存とmain更新後の自動DB復元を行わない。DBの保全・復元は個別の明示依頼として扱う。
---

# wp-plugin DBスナップショット（廃止）

- 状態: `retired`
- 廃止日: 2026-11-10
- 対象: `C:\osaruAi\project\wp-plugin`
- 理由: Git管理のDBスナップショットにローカル管理者以外のアカウント、パスワードハッシュ、会社ドメインのメールアドレス、保存先識別子が含まれていた。公開可能な履歴へ整理するため、PR・main同期とDBの自動連動を廃止した。

このSkillをPR作成、main同期、通常の表示検証で使用しない。`server/db_dump/` はGit除外とし、残るローカルファイルを自動でステージしない。DBの保全または復元が明示された場合は、対象のローカル環境、保存先、復旧方法、データ区分を確認し、`database-change`と`backup-restore-validation`の適用を判断する。既存の[WordPressDBスナップショットShell](../../../shell/WordPressDBスナップショット_20260930.ps1)はローカル専用の旧運用資産であり、Gitへ出力を含める目的では使わない。

登録先: [wp-plugin Agent・Skill一覧](../../../doc/Agent・Skill一覧_20260930.md)
