<?php
declare(strict_types=1);

namespace App\Domain\Tenant;

use PDO;

final class SalesTargetRepository
{
    public function __construct(
        private PDO $tenantPdo,
        private PDO $commonPdo,
        private string $tenantCode
    ) {
    }

    /**
     * 指定年度の年度目標一覧を取得する（target_type = premium_total, target_month IS NULL）。
     * 担当者表示名は common.users から解決する。
     *
     * @return array<int, array{staff_user_id: int|null, display_name: string, target_amount: int}>
     */
    public function findYearlyTargets(int $fiscalYear): array
    {
        $stmt = $this->tenantPdo->prepare(
            'SELECT staff_user_id, target_amount
             FROM t_sales_target
             WHERE fiscal_year  = :fiscal_year
               AND target_month IS NULL
               AND target_type  = \'premium_total\'
               AND is_deleted   = 0'
        );
        $stmt->bindValue(':fiscal_year', $fiscalYear, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!is_array($rows)) {
            return [];
        }

        // 担当者IDを収集して一括で名前解決する
        $userIds = array_values(array_unique(array_filter(
            array_column($rows, 'staff_user_id'),
            static fn ($id) => $id !== null
        )));

        $nameMap = [];
        if ($userIds !== []) {
            $placeholders = implode(',', array_fill(0, count($userIds), '?'));
            $nameStmt = $this->commonPdo->prepare(
                'SELECT id, COALESCE(NULLIF(display_name, \'\'), name) AS display_name
                 FROM users
                 WHERE id IN (' . $placeholders . ') AND is_deleted = 0'
            );
            $nameStmt->execute($userIds);
            foreach ($nameStmt->fetchAll(PDO::FETCH_ASSOC) as $u) {
                $nameMap[(int) $u['id']] = (string) $u['display_name'];
            }
        }

        $result = [];
        foreach ($rows as $row) {
            $uid      = $row['staff_user_id'] !== null ? (int) $row['staff_user_id'] : null;
            $result[] = [
                'staff_user_id' => $uid,
                'display_name'  => $uid !== null ? ($nameMap[$uid] ?? '（不明）') : 'チーム全体',
                'target_amount' => (int) $row['target_amount'],
            ];
        }

        // チーム全体（null）を先頭、担当者別は名前順
        usort($result, static function (array $a, array $b): int {
            if ($a['staff_user_id'] === null) {
                return -1;
            }
            if ($b['staff_user_id'] === null) {
                return 1;
            }
            return strcmp($a['display_name'], $b['display_name']);
        });

        return $result;
    }

    /**
     * 年度目標1件を取得する。
     *
     * @return array{id: int, staff_user_id: int|null, target_amount: int}|null
     */
    public function findYearlyTarget(int $fiscalYear, ?int $staffUserId): ?array
    {
        $staffWhere = $staffUserId === null
            ? 'AND staff_user_id IS NULL'
            : 'AND staff_user_id = :staff_user_id';

        $stmt = $this->tenantPdo->prepare(
            'SELECT id, staff_user_id, target_amount
             FROM t_sales_target
             WHERE fiscal_year  = :fiscal_year
               AND target_month IS NULL
               AND target_type  = \'premium_total\'
               AND is_deleted   = 0
               ' . $staffWhere . '
             LIMIT 1'
        );
        $stmt->bindValue(':fiscal_year', $fiscalYear, PDO::PARAM_INT);
        if ($staffUserId !== null) {
            $stmt->bindValue(':staff_user_id', $staffUserId, PDO::PARAM_INT);
        }
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            return null;
        }
        return [
            'id'            => (int) $row['id'],
            'staff_user_id' => $row['staff_user_id'] !== null ? (int) $row['staff_user_id'] : null,
            'target_amount' => (int) $row['target_amount'],
        ];
    }

    /**
     * 年度目標を登録または更新（UPSERT）。
     * target_type = 'premium_total', target_month IS NULL 固定。
     */
    public function upsertYearlyTarget(
        int $fiscalYear,
        ?int $staffUserId,
        int $targetAmount,
        int $userId
    ): void {
        $existing   = $this->findYearlyTarget($fiscalYear, $staffUserId);
        $staffWhere = $staffUserId === null
            ? 'AND staff_user_id IS NULL'
            : 'AND staff_user_id = :staff_user_id';

        if ($existing !== null) {
            $stmt = $this->tenantPdo->prepare(
                'UPDATE t_sales_target
                 SET target_amount = :target_amount,
                     updated_by    = :updated_by
                 WHERE fiscal_year  = :fiscal_year
                   AND target_month IS NULL
                   AND target_type  = \'premium_total\'
                   AND is_deleted   = 0
                   ' . $staffWhere
            );
            $stmt->bindValue(':target_amount', $targetAmount, PDO::PARAM_INT);
            $stmt->bindValue(':updated_by',    $userId,       PDO::PARAM_INT);
            $stmt->bindValue(':fiscal_year',   $fiscalYear,   PDO::PARAM_INT);
            if ($staffUserId !== null) {
                $stmt->bindValue(':staff_user_id', $staffUserId, PDO::PARAM_INT);
            }
            $stmt->execute();
        } else {
            $staffPlaceholder = $staffUserId !== null ? ':staff_user_id' : 'NULL';
            $stmt = $this->tenantPdo->prepare(
                'INSERT INTO t_sales_target
                   (fiscal_year, target_month, staff_user_id, target_type, target_amount,
                    is_deleted, created_by, updated_by)
                 VALUES
                   (:fiscal_year, NULL, ' . $staffPlaceholder . ', \'premium_total\', :target_amount,
                    0, :created_by, :updated_by)'
            );
            $stmt->bindValue(':fiscal_year',   $fiscalYear,   PDO::PARAM_INT);
            $stmt->bindValue(':target_amount', $targetAmount, PDO::PARAM_INT);
            $stmt->bindValue(':created_by',    $userId,       PDO::PARAM_INT);
            $stmt->bindValue(':updated_by',    $userId,       PDO::PARAM_INT);
            if ($staffUserId !== null) {
                $stmt->bindValue(':staff_user_id', $staffUserId, PDO::PARAM_INT);
            }
            $stmt->execute();
        }
    }

    /**
     * 年度目標を論理削除する。
     */
    public function deleteYearlyTarget(
        int $fiscalYear,
        ?int $staffUserId,
        int $userId
    ): void {
        $staffWhere = $staffUserId === null
            ? 'AND staff_user_id IS NULL'
            : 'AND staff_user_id = :staff_user_id';

        $stmt = $this->tenantPdo->prepare(
            'UPDATE t_sales_target
             SET is_deleted = 1,
                 updated_by = :updated_by
             WHERE fiscal_year  = :fiscal_year
               AND target_month IS NULL
               AND target_type  = \'premium_total\'
               AND is_deleted   = 0
               ' . $staffWhere
        );
        $stmt->bindValue(':fiscal_year', $fiscalYear, PDO::PARAM_INT);
        $stmt->bindValue(':updated_by',  $userId,     PDO::PARAM_INT);
        if ($staffUserId !== null) {
            $stmt->bindValue(':staff_user_id', $staffUserId, PDO::PARAM_INT);
        }
        $stmt->execute();
    }

    /**
     * テナント所属ユーザーの一覧を取得する（目標設定対象の担当者リスト用）。
     *
     * @return array<int, array{user_id: int, display_name: string}>
     */
    public function fetchAssignableUsers(): array
    {
        $stmt = $this->commonPdo->prepare(
            'SELECT u.id AS user_id,
                    COALESCE(NULLIF(u.display_name, \'\'), u.name) AS display_name
             FROM users u
             INNER JOIN user_tenants ut ON ut.user_id = u.id
             WHERE ut.tenant_code = :tenant_code
               AND ut.status      = 1
               AND ut.is_deleted  = 0
               AND u.is_deleted   = 0
             ORDER BY COALESCE(NULLIF(u.display_name, \'\'), u.name) ASC, u.id ASC'
        );
        $stmt->bindValue(':tenant_code', $this->tenantCode);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!is_array($rows)) {
            return [];
        }
        return array_map(
            static fn (array $r): array => [
                'user_id'      => (int) $r['user_id'],
                'display_name' => (string) $r['display_name'],
            ],
            $rows
        );
    }
}
