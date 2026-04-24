# config/dml/master/ — マスターデータ（必須マスタ）

## 概要

業務運用に**必須**のマスタデータ。
**検証環境・本番環境の両方**に投入する。

DDL 適用直後（テーブル作成後）に一度だけ実行し、以降はマスタ追加時に再実行する。
すべてのファイルは **冪等** （重複実行しても安全）な構造になっている。

**方針**: DDL はスキーマ定義のみ、シードデータは本フォルダに一本化する。

---

## フォルダ構成

```
config/dml/master/
├── README.md              ← 本ファイル
├── 01_m_case_status.sql
├── 02_m_sales_case_status.sql
├── 03_m_activity_type.sql
├── 04_m_activity_purpose_type.sql
├── 05_m_renewal_method.sql
├── 06_m_procedure_method.sql
├── 07_m_product_category.sql
├── 08_seed_internal_customer.sql
└── 09_m_renewal_reminder_phase.sql
```

投入先はテナント DB（xs000001_teXXX 等）。

common DB（xs000001_admin 等）には現状必須マスタなし。
テナントや users は環境ごとに手動登録する運用のため、本フォルダでは扱わない。

---

## ファイル一覧と投入順序

| 順序 | ファイル | テーブル | 件数 | 用途 |
|---|---|---|---|---|
| 1 | `01_m_case_status.sql` | `m_case_status` | 13 | 対応状況マスタ（renewal / accident） |
| 2 | `02_m_sales_case_status.sql` | `m_sales_case_status` | 5 | 見込案件ステータス |
| 3 | `03_m_activity_type.sql` | `m_activity_type` | 5 | 活動種別（訪問/電話/メール...） |
| 4 | `04_m_activity_purpose_type.sql` | `m_activity_purpose_type` | 9 | 活動用件区分（満期/新規/事故...） |
| 5 | `05_m_renewal_method.sql` | `m_renewal_method` | 3 | 更改方法（対面/郵送/電話募集） |
| 6 | `06_m_procedure_method.sql` | `m_procedure_method` | 7 | 手続方法（対面/対面ナビ...） |
| 7 | `07_m_product_category.sql` | `m_product_category` | 670+ | SJ-NET 種目マスタ |
| 8 | `08_seed_internal_customer.sql` | `m_customer` | 1 | 「社内・顧客なし」ダミー顧客 |
| 9 | `09_m_renewal_reminder_phase.sql` | `m_renewal_reminder_phase` | 2 | 満期通知フェーズ（早期/直前） |

### 投入順序の根拠

- 01 ～ 07 は相互に FK 依存がないため順序は任意だが、番号順で統一
- 08 の `m_customer` INSERT は他より後（`m_customer` DDL 適用後であればいつでも可）
- 09 は FK 依存なし。投入後にテナント設定画面で日数を保存するまでバッチは発火しない（初期値は範囲値のため）

---

## 投入手順

### phpMyAdmin

1. 対象テナント DB（例: `xs000001_te001`）を選択
2. 「SQL」タブを開く
3. 上記順序で各ファイルの内容を実行

### コマンドライン（MySQL CLI）

```bash
DB=xs000001_te001
for f in $(ls config/dml/master/*.sql | sort); do
  echo "-- applying $f"
  mysql --default-character-set=utf8mb4 -u root "$DB" < "$f"
done
```

---

## 冪等性（Idempotency）

すべてのファイルは重複実行しても安全:

| ファイル | 冪等化の仕組み |
|---|---|
| `01_m_case_status.sql` | `ON DUPLICATE KEY UPDATE` / (case_type, code) UNIQUE |
| `02_m_sales_case_status.sql` | `ON DUPLICATE KEY UPDATE` / code UNIQUE |
| `03_m_activity_type.sql` | `ON DUPLICATE KEY UPDATE` / code PRIMARY KEY |
| `04_m_activity_purpose_type.sql` | `ON DUPLICATE KEY UPDATE` / code PRIMARY KEY |
| `05_m_renewal_method.sql` | `ON DUPLICATE KEY UPDATE` / label UNIQUE |
| `06_m_procedure_method.sql` | `ON DUPLICATE KEY UPDATE` / label UNIQUE |
| `07_m_product_category.sql` | `ON DUPLICATE KEY UPDATE` / csv_value UNIQUE |
| `08_seed_internal_customer.sql` | `WHERE NOT EXISTS` で同名レコードがなければ挿入 |
| `09_m_renewal_reminder_phase.sql` | `ON DUPLICATE KEY UPDATE` / phase_code UNIQUE。from/to/is_enabled はテナント設定値を保持するため更新しない |

---

## 本番投入時の注意

- `TRUNCATE` や `DELETE` は含まれない。既存データを破壊しない設計
- マスタ追加が必要な場合は、該当ファイルを更新して再投入する（既存行は ON DUPLICATE KEY UPDATE で更新、新規行のみ追加）
- カスタム項目（例: `m_case_status` の `is_system=0` 行、`m_product_category` の後から追加した行、`m_sales_case_status` の `custom_NNN` 行など）は本ファイルが壊さない

---

## DDL との責務分離

従来、一部の DDL にはシード `INSERT` が組み込まれていたが、
DDL はスキーマ定義のみ・DML はデータ投入のみという責務分離のため、
以下のシードは DDL から本フォルダに分離済み:

- `m_sales_case_status` → `02_m_sales_case_status.sql`
- `m_activity_type` → `03_m_activity_type.sql`
- `m_activity_purpose_type` → `04_m_activity_purpose_type.sql`
- `m_renewal_method` → `05_m_renewal_method.sql`
- `m_procedure_method` → `06_m_procedure_method.sql`
- 旧 `config/ddl/seed_internal_customer.sql` → `08_seed_internal_customer.sql`

DDL 側（`config/ddl/`）には `CREATE TABLE` のみが残る。
