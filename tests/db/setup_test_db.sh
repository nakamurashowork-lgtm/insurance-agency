#!/usr/bin/env bash
# =============================================================================
# setup_test_db.sh — テスト DB セットアップスクリプト
#
# 警告: このスクリプトはデータベースを DROP して再作成します。
#       テスト DB 以外には絶対に実行しないでください。
#
# 使用方法:
#   bash tests/db/setup_test_db.sh
#   DB_NAME=xs000001_test bash tests/db/setup_test_db.sh
#   DB_HOST=127.0.0.1 DB_USER=root DB_PASS="" bash tests/db/setup_test_db.sh
# =============================================================================
set -euo pipefail

# --- 接続設定（環境変数で上書き可能）---
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
DB_USER="${DB_USER:-root}"
DB_PASS="${DB_PASS:-}"
DB_NAME="${DB_NAME:-xs000001_test}"

# --- 安全装置: DB 名が _test で終わらなければ即終了 ---
if [[ "${DB_NAME}" != *_test ]]; then
  echo "ERROR: DB_NAME '${DB_NAME}' は '_test' で終わっていません。" >&2
  echo "       本番 DB を誤って破壊しないよう、このスクリプトは終了します。" >&2
  exit 1
fi

# --- MySQL コマンドの組み立て ---
MYSQL_CMD=(/c/xampp/mysql/bin/mysql.exe -h "${DB_HOST}" -P "${DB_PORT}" -u "${DB_USER}")
if [[ -n "${DB_PASS}" ]]; then
  MYSQL_CMD+=(-p"${DB_PASS}")
fi

echo "============================================="
echo "  警告: '${DB_NAME}' を DROP & 再作成します"
echo "  ホスト: ${DB_HOST}:${DB_PORT}  ユーザー: ${DB_USER}"
echo "============================================="
echo ""

# --- スキーマ再作成 ---
echo "[1/3] DROP & CREATE DATABASE ..."
"${MYSQL_CMD[@]}" <<SQL
DROP DATABASE IF EXISTS \`${DB_NAME}\`;
CREATE DATABASE \`${DB_NAME}\`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
SQL

echo "[2/3] DDL ファイルを適用 ..."

# 適用順序: マスタ → トランザクション（論理的な参照先から順に適用）
DDL_DIR="$(cd "$(dirname "$0")/../../config/ddl/tenant" && pwd)"

DDL_ORDER=(
  # マスタ系（他テーブルから参照される側）
  "m_customer.sql"
  "m_staff.sql"
  "m_case_status.sql"
  "m_product_category.sql"
  "m_procedure_method.sql"
  "m_activity_purpose_type.sql"
  "m_renewal_reminder_phase.sql"
  # トランザクション系（m_customer / m_staff に依存）
  "t_contract.sql"
  "t_renewal_case.sql"
  "t_accident_case.sql"
  "t_sales_case.sql"
  "t_sales_performance.sql"
  "t_activity.sql"
  "t_case_comment.sql"
  # SJNET 取込系
  "t_sjnet_import_batch.sql"
  "t_sjnet_import_row.sql"
  # 通知・監査系
  "t_accident_reminder_rule.sql"
  "t_accident_reminder_rule_weekday.sql"
  "t_notification_run.sql"
  "t_notification_delivery.sql"
  "t_audit_event.sql"
  "t_audit_event_detail.sql"
  # その他
  "t_sales_target.sql"
  "t_daily_report.sql"
)

for ddl_file in "${DDL_ORDER[@]}"; do
  full_path="${DDL_DIR}/${ddl_file}"
  if [[ -f "${full_path}" ]]; then
    echo "  -> ${ddl_file}"
    "${MYSQL_CMD[@]}" "${DB_NAME}" < "${full_path}"
  else
    echo "  [SKIP] ${ddl_file} (ファイルなし)"
  fi
done

# seed ファイルは任意（テスト DB では不要なためスキップ）
echo "  [SKIP] seed_internal_customer.sql (テスト DB では適用しない)"

echo "[3/3] 適用済みテーブル一覧:"
"${MYSQL_CMD[@]}" -e "SHOW TABLES;" "${DB_NAME}"

echo ""
echo "完了: '${DB_NAME}' のセットアップが完了しました。"
