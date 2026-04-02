<?php
declare(strict_types=1);

namespace App\Domain\Tenant;

use PDO;

final class SalesTargetRepository
{
    /**
     * target_type の許容値。
     */
    public const ALLOWED_TARGET_TYPES = [
        'premium_non_life',
        'premium_life',
        'premium_total',
        'case_count',
    ];

    public function __construct(private PDO $pdo)
    {
    }

    /**
     * 指定年度の目標一覧を取得する。
     * staff_user_id が NULL の行（チーム全体目標）も含む。
     *
     * @return array<int, array<string, mixed>>
     */
    public function findByFiscalYear(int $fiscalYear): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, fiscal_year, target_month, staff_user_id, target_type, target_amount, created_at, updated_at
             FROM t_sales_target
             WHERE fiscal_year = :fiscal_year
               AND is_deleted = 0
             ORDER BY staff_user_id ASC, target_month ASC, target_type ASC'
        );
        $stmt->bindValue(':fiscal_year', $fiscalYear, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    /**
     * 目標を登録または更新する（upsert）。
     * UNIQUE KEY (fiscal_year, target_month, staff_user_id, target_type) の NULL を含む場合、
     * MySQL の UNIQUE 制約では NULL は重複扱いにならないため、UPDATE/INSERT の2段階で処理する。
     */
    public function upsert(
        int $fiscalYear,
        ?int $targetMonth,
        ?int $staffUserId,
        string $targetType,
        int $targetAmount,
        int $actorUserId
    ): void {
        // 既存行の検索（NULL を WHERE IS NULL で処理）
        $whereSql = 'fiscal_year = :fiscal_year AND target_type = :target_type AND is_deleted = 0';
        $params = [
            ':fiscal_year' => $fiscalYear,
            ':target_type' => $targetType,
        ];

        if ($targetMonth === null) {
            $whereSql .= ' AND target_month IS NULL';
        } else {
            $whereSql .= ' AND target_month = :target_month';
            $params[':target_month'] = $targetMonth;
        }

        if ($staffUserId === null) {
            $whereSql .= ' AND staff_user_id IS NULL';
        } else {
            $whereSql .= ' AND staff_user_id = :staff_user_id';
            $params[':staff_user_id'] = $staffUserId;
        }

        $updateStmt = $this->pdo->prepare(
            "UPDATE t_sales_target
             SET target_amount = :target_amount,
                 updated_by    = :updated_by,
                 updated_at    = CURRENT_TIMESTAMP
             WHERE $whereSql"
        );
        $updateStmt->bindValue(':target_amount', $targetAmount, PDO::PARAM_INT);
        $updateStmt->bindValue(':updated_by', $actorUserId, PDO::PARAM_INT);
        foreach ($params as $key => $val) {
            $updateStmt->bindValue($key, $val, is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $updateStmt->execute();

        if ($updateStmt->rowCount() > 0) {
            return;
        }

        // INSERT 新規
        $insertStmt = $this->pdo->prepare(
            'INSERT INTO t_sales_target
                (fiscal_year, target_month, staff_user_id, target_type, target_amount, created_by, updated_by)
             VALUES
                (:fiscal_year, :target_month, :staff_user_id, :target_type, :target_amount, :created_by, :updated_by)'
        );
        $insertStmt->bindValue(':fiscal_year', $fiscalYear, PDO::PARAM_INT);
        $insertStmt->bindValue(':target_month', $targetMonth, $targetMonth !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $insertStmt->bindValue(':staff_user_id', $staffUserId, $staffUserId !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $insertStmt->bindValue(':target_type', $targetType);
        $insertStmt->bindValue(':target_amount', $targetAmount, PDO::PARAM_INT);
        $insertStmt->bindValue(':created_by', $actorUserId, PDO::PARAM_INT);
        $insertStmt->bindValue(':updated_by', $actorUserId, PDO::PARAM_INT);
        $insertStmt->execute();
    }
}
