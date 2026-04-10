# config/dml/common/ — 動作確認用 DML（共通DB）

## 概要

common DB（xs000001_admin 等）に投入するテストデータ。  
2名のテストユーザーとテナント所属情報を定義する。

## ファイル一覧

| ファイル | テーブル | 件数 | 目的 |
|---|---|---|---|
| 01_users.sql | users | 2 | テスト管理者(id=1)・テスト担当者(id=2) |
| 02_user_tenants.sql | user_tenants | 2 | 両ユーザーを TE001 テナントに紐付け |

## 投入前提

- common DB が存在し、DDL が適用済みであること
- テナント TE001 が tenants テーブルに存在すること  
  （存在しない場合は先に tenants へ INSERT すること）

## 投入方法

phpMyAdmin の「SQL」タブで common DB を選択し、各ファイルの内容を順番に実行する。

```
1. 01_users.sql
2. 02_user_tenants.sql
```

## 既存データへの影響

- `users` は `INSERT IGNORE` のため、id=1/2 が既存の場合はスキップされる
- `user_tenants` も `INSERT IGNORE` のため、(user_id, tenant_code) 重複はスキップ
- 既存のユーザーデータには影響しない

## クリーンアップ

テストユーザーを削除する場合は以下を実行（既存データへの影響を確認してから）:

```sql
DELETE FROM user_tenants WHERE user_id IN (1, 2) AND tenant_code = 'TE001';
DELETE FROM users WHERE id IN (1, 2) AND email LIKE '%insurance-test.example.jp%';
```
