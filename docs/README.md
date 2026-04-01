# docs ディレクトリ運用

`docs/` は仕様の正本です。

## 1. 直下に置くファイル

`docs/` 直下に置く Markdown は次の2つのみとする。

- `docs/README.md`（本ファイル）
- `docs/00_overview.md`（全体概要）

設計書・方針書・計画書・パッチ文書は、必ず下位フォルダへ配置する。

## 2. フォルダ分類

- `docs/foundations/`: 基本設計・上位方針
- `docs/plans/`: 実装計画
- `docs/policies/`: 運用方針・UI規約
- `docs/migrations/`: 移行方針
- `docs/reconciliation/`: 差分照合・整合確認
- `docs/patches/`: パッチ指示書・統合手順
- `docs/screens/`: 画面別仕様

## 3. 参照の書き方

参照パスは `docs/...` の絶対相対表記で統一する。

例:

- `docs/foundations/03_screen-map.md`
- `docs/policies/06_common-ui-rules.md`
- `docs/plans/05_implementation-plan.md`
