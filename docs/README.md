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
- `docs/deployment/`: 本番環境へのデプロイ手順
- `docs/patches/`: パッチ指示書・統合手順（一時置き場。統合後は削除する）
- `docs/screens/`: 画面別仕様

## 3. 参照の書き方

参照パスは `docs/...` の絶対相対表記で統一する。

例:

- `docs/foundations/03_screen-map.md`
- `docs/policies/06_common-ui-rules.md`
- `docs/plans/05_implementation-plan.md`

## 4. patches フォルダの運用ルール

`docs/patches/` はパッチの**一時置き場**であり、本体への統合完了後は速やかに削除する。

- パッチは本体（`docs/plans/`, `docs/screens/` 等）へ統合した時点で役割を終える
- 統合完了後にパッチファイルを残すと、仕様の正本が分裂した状態になる
- `docs/patches/` 配下にファイルが存在する状態は「未統合パッチあり」を意味する
- パッチを統合したら、そのパッチファイルと対応する統合手順書をともに削除する

統合完了の判定基準:

1. 反映先の本体ファイルに内容が反映されている
2. 反映後の本体ファイルをレビューして内容が正しいことを確認している
3. パッチファイルおよび統合手順書（`_integration.md`）を削除している
