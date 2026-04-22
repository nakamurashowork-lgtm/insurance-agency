<?php
declare(strict_types=1);

namespace App\Domain\Tenant;

use PDO;

final class SalesTargetRepository
{
    /** 許容する target_type（損保/生保の年度目標のみ） */
    private const ALLOWED_TARGET_TYPES = ['premium_non_life', 'premium_life'];

    public function __construct(
        private PDO $tenantPdo,
        private PDO $commonPdo,
        private string $tenantCode
    ) {
    }

    /**
     * 指定年度の年度目標一覧を取得する。
     * target_month IS NULL, target_type IN ('premium_non_life','premium_life') を対象とし、
     * staff_user_id ごとに損保・生保の金額を 1 行にまとめて返す。
     *
     * @return array<int, array{
     *     staff_user_id: int|null,
     *     display_name: string,
     *     non_life_amount: int|null,
     *     life_amount: int|null,
     * }>
     */
    public function findYearlyTargets(int $fiscalYear): array
    {
        $stmt = $this->tenantPdo->prepare(
            'SELECT staff_user_id, target_type, target_amount
             FROM t_sales_target
             WHERE fiscal_year  = :fiscal_year
               AND target_month IS NULL
               AND target_type  IN (\'premium_non_life\', \'premium_life\')
               AND is_deleted   = 0'
        );
        $stmt->bindValue(':fiscal_year', $fiscalYear, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!is_array($rows)) {
            $rows = [];
        }

        // staff_user_id をキーに non_life / life を pivot
        /** @var array<string, array{staff_user_id: int|null, non_life_amount: int|null, life_amount: int|null}> $byStaff */
        $byStaff = [];
        foreach ($rows as $row) {
            $uid      = $row['staff_user_id'] !== null ? (int) $row['staff_user_id'] : null;
            $key      = $uid === null ? 'team' : (string) $uid;
            $amount   = (int) $row['target_amount'];
            $type     = (string) $row['target_type'];
            if (!isset($byStaff[$key])) {
                $byStaff[$key] = [
                    'staff_user_id'   => $uid,
                    'non_life_amount' => null,
                    'life_amount'     => null,
                ];
            }
            if ($type === 'premium_non_life') {
                $byStaff[$key]['non_life_amount'] = $amount;
            } elseif ($type === 'premium_life') {
                $byStaff[$key]['life_amount'] = $amount;
            }
        }

        // 担当者名の一括解決
        $userIds = [];
        foreach ($byStaff as $entry) {
            if ($entry['staff_user_id'] !== null) {
                $userIds[] = $entry['staff_user_id'];
            }
        }
        $userIds = array_values(array_unique($userIds));

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
        foreach ($byStaff as $entry) {
            $uid      = $entry['staff_user_id'];
            $result[] = [
                'staff_user_id'   => $uid,
                'display_name'    => $uid !== null ? ($nameMap[$uid] ?? '（不明）') : 'チーム全体',
                'non_life_amount' => $entry['non_life_amount'],
                'life_amount'     => $entry['life_amount'],
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
     * 年度目標 1 件を指定 target_type で取得する。
     *
     * @return array{id: int, staff_user_id: int|null, target_amount: int}|null
     */
    public function findYearlyTarget(int $fiscalYear, ?int $staffUserId, string $targetType): ?array
    {
        $this->assertAllowedTargetType($targetType);

        $staffWhere = $staffUserId === null
            ? 'AND staff_user_id IS NULL'
            : 'AND staff_user_id = :staff_user_id';

        $stmt = $this->tenantPdo->prepare(
            'SELECT id, staff_user_id, target_amount
             FROM t_sales_target
             WHERE fiscal_year  = :fiscal_year
               AND target_month IS NULL
               AND target_type  = :target_type
               AND is_deleted   = 0
               ' . $staffWhere . '
             LIMIT 1'
        );
        $stmt->bindValue(':fiscal_year', $fiscalYear, PDO::PARAM_INT);
        $stmt->bindValue(':target_type', $targetType);
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
     * 指定担当者（または チーム全体）の 年度目標合計を取得する。
     * Dashboard の達成率算出で使用。
     *
     * @return array{non_life: int, life: int, total: int}
     */
    public function findYearlyTargetTotals(int $fiscalYear, ?int $staffUserId): array
    {
        $nonLife = $this->findYearlyTarget($fiscalYear, $staffUserId, 'premium_non_life');
        $life    = $this->findYearlyTarget($fiscalYear, $staffUserId, 'premium_life');
        $n       = $nonLife !== null ? (int) $nonLife['target_amount'] : 0;
        $l       = $life    !== null ? (int) $life['target_amount']    : 0;
        return [
            'non_life' => $n,
            'life'     => $l,
            'total'    => $n + $l,
        ];
    }

    /**
     * 年度目標を登録または更新（UPSERT）。
     * target_type は 'premium_non_life' / 'premium_life' のみ許容。target_month IS NULL 固定。
     */
    public function upsertYearlyTarget(
        int $fiscalYear,
        ?int $staffUserId,
        string $targetType,
        int $targetAmount,
        int $userId
    ): void {
        $this->assertAllowedTargetType($targetType);

        $existing   = $this->findYearlyTarget($fiscalYear, $staffUserId, $targetType);
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
                   AND target_type  = :target_type
                   AND is_deleted   = 0
                   ' . $staffWhere
            );
            $stmt->bindValue(':target_amount', $targetAmount, PDO::PARAM_INT);
            $stmt->bindValue(':updated_by',    $userId,       PDO::PARAM_INT);
            $stmt->bindValue(':fiscal_year',   $fiscalYear,   PDO::PARAM_INT);
            $stmt->bindValue(':target_type',   $targetType);
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
                   (:fiscal_year, NULL, ' . $staffPlaceholder . ', :target_type, :target_amount,
                    0, :created_by, :updated_by)'
            );
            $stmt->bindValue(':fiscal_year',   $fiscalYear,   PDO::PARAM_INT);
            $stmt->bindValue(':target_type',   $targetType);
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
     * 指定担当者・指定 target_type の年度目標のみを論理削除する。
     * 「値を空にして保存 = その種別の目標を明示的に未設定にする」ユースケース向け。
     */
    public function deleteYearlyTargetByType(
        int $fiscalYear,
        ?int $staffUserId,
        string $targetType,
        int $userId
    ): void {
        $this->assertAllowedTargetType($targetType);

        $staffWhere = $staffUserId === null
            ? 'AND staff_user_id IS NULL'
            : 'AND staff_user_id = :staff_user_id';

        $stmt = $this->tenantPdo->prepare(
            'UPDATE t_sales_target
             SET is_deleted = 1,
                 updated_by = :updated_by
             WHERE fiscal_year  = :fiscal_year
               AND target_month IS NULL
               AND target_type  = :target_type
               AND is_deleted   = 0
               ' . $staffWhere
        );
        $stmt->bindValue(':fiscal_year', $fiscalYear, PDO::PARAM_INT);
        $stmt->bindValue(':target_type', $targetType);
        $stmt->bindValue(':updated_by',  $userId,     PDO::PARAM_INT);
        if ($staffUserId !== null) {
            $stmt->bindValue(':staff_user_id', $staffUserId, PDO::PARAM_INT);
        }
        $stmt->execute();
    }

    /**
     * 指定担当者の年度目標を 損保・生保 両方まとめて論理削除する。
     */
    public function deleteYearlyTargetsAllTypes(
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
               AND target_type  IN (\'premium_non_life\', \'premium_life\')
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

    private function assertAllowedTargetType(string $targetType): void
    {
        if (!in_array($targetType, self::ALLOWED_TARGET_TYPES, true)) {
            throw new \InvalidArgumentException(
                '許可されていない target_type です: ' . $targetType
            );
        }
    }
}
