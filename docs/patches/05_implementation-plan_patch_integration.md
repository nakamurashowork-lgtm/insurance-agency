# 05_implementation-plan_patch.md 統合手順書

## 位置づけ

本ファイルは `docs/patches/05_implementation-plan_patch.md` を `docs/plans/05_implementation-plan.md` 本体へ統合するための手順を定義する。

統合完了後、`docs/patches/05_implementation-plan_patch.md` は削除する。
削除前に本手順書に「統合完了日時」を記録すること。

---

## 統合対象と反映先

| パッチ内容 | 反映先セクション | 反映種別 |
|---|---|---|
| SCR-DASHBOARD の依存テーブルに `t_sales_target` 追加 | セクション4-2 依存リスト | 追記 |
| SCR-SALES-DETAIL の責務に `sales_channel/referral_source`・赤字表示・source_type 切替を追加 | セクション4-8 責務 | 追記 |
| SCR-TENANT-SETTINGS の依存テーブルに `m_sjnet_staff_mapping`, `m_activity_purpose_type`, `t_sales_target` 追加 | セクション4-10 依存リスト | 追記 |
| 画面/API/DB対応表の SCR-DASHBOARD・SCR-ACTIVITY-NEW/DETAIL・SCR-TENANT-SETTINGS 行を更新 | セクション5 対応表 | 更新 |
| Phase 4A 受入判定に DDL変更追加確認項目の注記を追記 | セクション16 | 追記 |
| Phase 4B 冒頭に CSV取込仕様改訂の重要注記を追記 | セクション17 | 追記 |
| Phase 4C（CSV取込 成績管理簿対応）を新規フェーズとして追加 | セクション17末尾 | 追加 |
| Phase 設定A（テナント設定拡張）を新規フェーズとして追加 | セクション末尾 | 追加 |

---

## 統合手順

1. `docs/plans/05_implementation-plan.md` を開く
2. 上記表の各「反映先セクション」を対象に、`docs/patches/05_implementation-plan_patch.md` の該当内容を反映する
3. 反映後、本ファイル下部の「統合完了記録」に日時と担当者を記録する
4. `docs/patches/05_implementation-plan_patch.md` を削除する
5. 本ファイル（`docs/patches/05_implementation-plan_patch_integration.md`）も削除する

---

## 統合完了記録

| 項目 | 内容 |
|---|---|
| 統合完了日時 | （記入欄） |
| 担当者 | （記入欄） |
| 確認者 | （記入欄） |
| パッチファイル削除 | □ 完了 |
| 本ファイル削除 | □ 完了 |

