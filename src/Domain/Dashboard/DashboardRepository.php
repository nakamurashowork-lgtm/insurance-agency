<?php
declare(strict_types=1);

namespace App\Domain\Dashboard;

use PDO;
use Throwable;

final class DashboardRepository
{
    /** @var array<int, string> */
    private const ACCIDENT_HIGH_PRIORITIES = ['high'];

    /**
     * case_type='renewal' の完了扱い name サブクエリ。
     */
    private const RENEWAL_DONE_SUBQUERY =
        '(SELECT name FROM m_case_status WHERE case_type = \'renewal\' AND is_completed = 1)';

    /**
     * case_type='accident' の完了扱い name サブクエリ。
     */
    private const ACCIDENT_DONE_SUBQUERY =
        '(SELECT name FROM m_case_status WHERE case_type = \'accident\' AND is_completed = 1)';

    public function __construct(
        private PDO $pdo,
        private ?PDO $commonPdo = null,
        private string $tenantCode = ''
    ) {
    }

    // ─── Tenant user list ────────────────────────────────────────────────

    /**
     * テナント所属ユーザー一覧を返す（担当者選択ドロップダウン用）。
     * display_name が NULL の場合は name にフォールバックする。
     *
     * @return array<int, array{id: int, display_name: string}>
     */
    public function fetchTenantUsers(): array
    {
        if ($this->commonPdo === null || $this->tenantCode === '') {
            return [];
        }

        $stmt = $this->commonPdo->prepare(
            'SELECT u.id, COALESCE(NULLIF(u.display_name, ""), u.name) AS display_name
             FROM users u
             JOIN user_tenants ut ON ut.user_id = u.id AND ut.tenant_code = :tenant_code
             WHERE u.is_deleted = 0 AND ut.is_deleted = 0 AND ut.status = 1
             ORDER BY display_name ASC'
        );
        $stmt->bindValue(':tenant_code', $this->tenantCode);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!is_array($rows)) {
            return [];
        }

        return array_map(
            static fn (array $row): array => [
                'id'           => (int) $row['id'],
                'display_name' => (string) $row['display_name'],
            ],
            $rows
        );
    }

    // ─── New summary methods ──────────────────────────────────────────────

    /**
     * 満期アラートカウント（対応遅れ・7日以内・14日以内・28日以内・60日以内）を返す。
     *
     * @return array{overdue: int, within_7d: int, within_14d: int, within_28d: int, within_60d: int}
     */
    public function getRenewalAlertCounts(?int $staffUserId): array
    {
        $userJoin = $staffUserId !== null
            ? 'INNER JOIN m_staff ms ON ms.id = rc.assigned_staff_id AND ms.user_id = :staff_user_id'
            : '';

        $sql =
            'SELECT
                COALESCE(SUM(CASE WHEN rc.maturity_date < CURDATE() THEN 1 ELSE 0 END), 0) AS overdue,
                COALESCE(SUM(CASE WHEN rc.maturity_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END), 0) AS within_7d,
                COALESCE(SUM(CASE WHEN rc.maturity_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 14 DAY) THEN 1 ELSE 0 END), 0) AS within_14d,
                COALESCE(SUM(CASE WHEN rc.maturity_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 28 DAY) THEN 1 ELSE 0 END), 0) AS within_28d,
                COALESCE(SUM(CASE WHEN rc.maturity_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 60 DAY) THEN 1 ELSE 0 END), 0) AS within_60d
             FROM t_renewal_case rc
             ' . $userJoin . '
             WHERE rc.is_deleted = 0
               AND rc.case_status NOT IN ' . self::RENEWAL_DONE_SUBQUERY;

        $stmt = $this->pdo->prepare($sql);
        if ($staffUserId !== null) {
            $stmt->bindValue(':staff_user_id', $staffUserId, PDO::PARAM_INT);
        }
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'overdue'    => (int) (is_array($row) ? ($row['overdue']    ?? 0) : 0),
            'within_7d'  => (int) (is_array($row) ? ($row['within_7d']  ?? 0) : 0),
            'within_14d' => (int) (is_array($row) ? ($row['within_14d'] ?? 0) : 0),
            'within_28d' => (int) (is_array($row) ? ($row['within_28d'] ?? 0) : 0),
            'within_60d' => (int) (is_array($row) ? ($row['within_60d'] ?? 0) : 0),
        ];
    }

    /**
     * 事故アラートカウント（高・中・低優先度未完了）を返す。
     *
     * @return array{high_priority: int, mid_priority: int, low_priority: int, open: int}
     */
    public function getAccidentAlertCounts(?int $staffUserId): array
    {
        $userJoin   = $staffUserId !== null
            ? 'LEFT JOIN m_staff ms ON ms.id = ac.assigned_staff_id AND ms.user_id = :staff_user_id'
            : '';
        $userWhere  = $staffUserId !== null ? 'AND ms.id IS NOT NULL' : '';

        $sql =
            'SELECT
                COALESCE(SUM(CASE WHEN ac.priority = "high" THEN 1 ELSE 0 END), 0) AS `high_priority`,
                COALESCE(SUM(CASE WHEN ac.priority = "normal"            THEN 1 ELSE 0 END), 0) AS `mid_priority`,
                COALESCE(SUM(CASE WHEN ac.priority = "low"               THEN 1 ELSE 0 END), 0) AS `low_priority`,
                COALESCE(COUNT(*), 0) AS `open`
             FROM t_accident_case ac
             ' . $userJoin . '
             WHERE ac.is_deleted = 0
               AND ac.status NOT IN ' . self::ACCIDENT_DONE_SUBQUERY . '
               ' . $userWhere;

        $stmt = $this->pdo->prepare($sql);
        if ($staffUserId !== null) {
            $stmt->bindValue(':staff_user_id', $staffUserId, PDO::PARAM_INT);
        }
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'high_priority' => (int) (is_array($row) ? ($row['high_priority'] ?? 0) : 0),
            'mid_priority'  => (int) (is_array($row) ? ($row['mid_priority']  ?? 0) : 0),
            'low_priority'  => (int) (is_array($row) ? ($row['low_priority']  ?? 0) : 0),
            'open'          => (int) (is_array($row) ? ($row['open']          ?? 0) : 0),
        ];
    }

    /**
     * 見込管理サマリ（見込度別件数・今月成約予定件数）を返す。
     * m_sales_case_status.is_completed=1 の name に該当するステータスを除外する。
     *
     * @return array{rank_a: int, rank_b: int, rank_c: int, closing_this_month: int}
     */
    public function getSalesCaseAlertCounts(?int $staffUserId): array
    {
        $userJoin = $staffUserId !== null
            ? 'LEFT JOIN m_staff ms ON ms.id = sc.assigned_staff_id AND ms.user_id = :staff_user_id'
            : '';
        $userWhere = $staffUserId !== null ? 'AND ms.id IS NOT NULL' : '';

        $sql =
            'SELECT
                COALESCE(SUM(CASE WHEN sc.prospect_rank = \'A\' THEN 1 ELSE 0 END), 0) AS rank_a,
                COALESCE(SUM(CASE WHEN sc.prospect_rank = \'B\' THEN 1 ELSE 0 END), 0) AS rank_b,
                COALESCE(SUM(CASE WHEN sc.prospect_rank = \'C\' THEN 1 ELSE 0 END), 0) AS rank_c,
                COALESCE(SUM(CASE WHEN sc.expected_contract_month = DATE_FORMAT(CURDATE(), \'%Y-%m\') THEN 1 ELSE 0 END), 0) AS closing_this_month
             FROM t_sales_case sc
             ' . $userJoin . '
             WHERE sc.is_deleted = 0
               AND sc.status NOT IN (SELECT name FROM m_sales_case_status WHERE is_completed = 1)
               ' . $userWhere;

        $stmt = $this->pdo->prepare($sql);
        if ($staffUserId !== null) {
            $stmt->bindValue(':staff_user_id', $staffUserId, PDO::PARAM_INT);
        }
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'rank_a'             => (int) (is_array($row) ? ($row['rank_a']             ?? 0) : 0),
            'rank_b'             => (int) (is_array($row) ? ($row['rank_b']             ?? 0) : 0),
            'rank_c'             => (int) (is_array($row) ? ($row['rank_c']             ?? 0) : 0),
            'closing_this_month' => (int) (is_array($row) ? ($row['closing_this_month'] ?? 0) : 0),
        ];
    }

    /**
     * 月別成績サマリ（12ヶ月分、4月始まり）を返す。
     *
     * @return array<int, array{premium: int, count: int}>
     */
    public function getPerformanceMonthlySummary(int $fiscalYear, ?int $staffUserId): array
    {
        // 集計基準: settlement_month（精算月）
        // fiscal_year の年度 = {fiscalYear}-04 〜 {fiscalYear+1}-03
        $fyStart  = $fiscalYear      . '-04';
        $fyEnd12  = $fiscalYear      . '-12';
        $fyStart1 = ($fiscalYear + 1) . '-01';
        $fyEnd    = ($fiscalYear + 1) . '-03';

        $userJoin  = $staffUserId !== null
            ? 'LEFT JOIN m_staff ms ON ms.id = sp.staff_id AND ms.user_id = :staff_user_id'
            : '';
        $userWhere = $staffUserId !== null ? 'AND ms.id IS NOT NULL' : '';

        $sql =
            'SELECT CAST(SUBSTRING(sp.settlement_month, 6, 2) AS UNSIGNED) AS perf_month,
                    COALESCE(SUM(sp.premium_amount), 0) AS premium,
                    COUNT(*) AS cnt
             FROM t_sales_performance sp
             ' . $userJoin . '
             WHERE sp.is_deleted = 0
               AND sp.settlement_month IS NOT NULL
               AND (
                   sp.settlement_month BETWEEN :fy_start AND :fy_end12
                   OR sp.settlement_month BETWEEN :fy_start1 AND :fy_end
               )
               ' . $userWhere . '
             GROUP BY CAST(SUBSTRING(sp.settlement_month, 6, 2) AS UNSIGNED)';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':fy_start',  $fyStart);
        $stmt->bindValue(':fy_end12',  $fyEnd12);
        $stmt->bindValue(':fy_start1', $fyStart1);
        $stmt->bindValue(':fy_end',    $fyEnd);
        if ($staffUserId !== null) {
            $stmt->bindValue(':staff_user_id', $staffUserId, PDO::PARAM_INT);
        }
        $stmt->execute();
        $dbRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 12ヶ月分を初期化（データなし月は 0）
        $result = [];
        foreach ([4,5,6,7,8,9,10,11,12,1,2,3] as $m) {
            $result[$m] = ['premium' => 0, 'count' => 0];
        }

        if (is_array($dbRows)) {
            foreach ($dbRows as $row) {
                $m = (int) ($row['perf_month'] ?? 0);
                if (isset($result[$m])) {
                    $result[$m]['premium'] = (int) ($row['premium'] ?? 0);
                    $result[$m]['count']   = (int) ($row['cnt']     ?? 0);
                }
            }
        }

        return $result;
    }

    /**
     * 業務区分別（損保/生保）の月別成績サマリを返す。
     * source_type IN ('non_life','life') で絞り込み、12ヶ月分を 4月始まりで返す。
     *
     * @return array{
     *     non_life: array<int, array{premium: int, count: int}>,
     *     life:     array<int, array{premium: int, count: int}>,
     * }
     */
    public function getPerformanceMonthlySummaryBySourceType(int $fiscalYear, ?int $staffUserId): array
    {
        $fyStart  = $fiscalYear      . '-04';
        $fyEnd12  = $fiscalYear      . '-12';
        $fyStart1 = ($fiscalYear + 1) . '-01';
        $fyEnd    = ($fiscalYear + 1) . '-03';

        $userJoin  = $staffUserId !== null
            ? 'LEFT JOIN m_staff ms ON ms.id = sp.staff_id AND ms.user_id = :staff_user_id'
            : '';
        $userWhere = $staffUserId !== null ? 'AND ms.id IS NOT NULL' : '';

        $sql =
            'SELECT sp.source_type AS src,
                    CAST(SUBSTRING(sp.settlement_month, 6, 2) AS UNSIGNED) AS perf_month,
                    COALESCE(SUM(sp.premium_amount), 0) AS premium,
                    COUNT(*) AS cnt
             FROM t_sales_performance sp
             ' . $userJoin . '
             WHERE sp.is_deleted = 0
               AND sp.settlement_month IS NOT NULL
               AND sp.source_type IN (\'non_life\', \'life\')
               AND (
                   sp.settlement_month BETWEEN :fy_start AND :fy_end12
                   OR sp.settlement_month BETWEEN :fy_start1 AND :fy_end
               )
               ' . $userWhere . '
             GROUP BY sp.source_type, CAST(SUBSTRING(sp.settlement_month, 6, 2) AS UNSIGNED)';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':fy_start',  $fyStart);
        $stmt->bindValue(':fy_end12',  $fyEnd12);
        $stmt->bindValue(':fy_start1', $fyStart1);
        $stmt->bindValue(':fy_end',    $fyEnd);
        if ($staffUserId !== null) {
            $stmt->bindValue(':staff_user_id', $staffUserId, PDO::PARAM_INT);
        }
        $stmt->execute();
        $dbRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $initMonths = static function (): array {
            $out = [];
            foreach ([4,5,6,7,8,9,10,11,12,1,2,3] as $m) {
                $out[$m] = ['premium' => 0, 'count' => 0];
            }
            return $out;
        };
        $result = [
            'non_life' => $initMonths(),
            'life'     => $initMonths(),
        ];

        if (is_array($dbRows)) {
            foreach ($dbRows as $row) {
                $src = (string) ($row['src'] ?? '');
                $m   = (int) ($row['perf_month'] ?? 0);
                if (($src === 'non_life' || $src === 'life') && isset($result[$src][$m])) {
                    $result[$src][$m]['premium'] = (int) ($row['premium'] ?? 0);
                    $result[$src][$m]['count']   = (int) ($row['cnt']     ?? 0);
                }
            }
        }

        return $result;
    }

    /**
     * 月別・年度目標サマリを返す。
     *
     * @return array{annual: int|null, monthly: array<int, int>}
     */
    public function getTargetMonthlySummary(int $fiscalYear, ?int $staffUserId): array
    {
        if ($staffUserId === null) {
            $staffWhere = 'AND st.staff_user_id IS NULL';
        } else {
            $staffWhere = 'AND st.staff_user_id = :staff_user_id';
        }

        $sql =
            'SELECT st.target_month, st.target_amount
             FROM t_sales_target st
             WHERE st.is_deleted = 0
               AND st.fiscal_year = :fiscal_year
               AND st.target_type = \'premium_total\'
               ' . $staffWhere;

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':fiscal_year', $fiscalYear, PDO::PARAM_INT);
        if ($staffUserId !== null) {
            $stmt->bindValue(':staff_user_id', $staffUserId, PDO::PARAM_INT);
        }
        $stmt->execute();
        $dbRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $annual  = null;
        $monthly = [];

        if (is_array($dbRows)) {
            foreach ($dbRows as $row) {
                $targetMonth  = $row['target_month'] ?? null;
                $targetAmount = (int) ($row['target_amount'] ?? 0);

                if ($targetMonth === null) {
                    $annual = $targetAmount;
                } else {
                    $monthly[(int) $targetMonth] = $targetAmount;
                }
            }
        }

        return ['annual' => $annual, 'monthly' => $monthly];
    }

    /**
     * 今日の活動件数を返す。
     *
     * @return array{today_count: int}
     */
    public function getActivitySummary(int $staffUserId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM t_activity
             WHERE activity_date = CURDATE()
               AND staff_id = :staff_id
               AND is_deleted = 0'
        );
        $stmt->bindValue(':staff_id', $staffUserId, PDO::PARAM_INT);
        $stmt->execute();

        return ['today_count' => (int) $stmt->fetchColumn()];
    }

    /**
     * テナント全体の日報提出状況（管理者専用）を返す。
     *
     * @return array{total: int, submitted: int, unsubmitted: int}
     */
    public function getDailyReportStatus(): array
    {
        // テナントのアクティブユーザー数（common DB）
        $total = 0;
        if ($this->commonPdo !== null && $this->tenantCode !== '') {
            $stmt = $this->commonPdo->prepare(
                'SELECT COUNT(*)
                 FROM user_tenants ut
                 WHERE ut.tenant_code = :tenant_code
                   AND ut.status = 1
                   AND ut.is_deleted = 0'
            );
            $stmt->bindValue(':tenant_code', $this->tenantCode);
            $stmt->execute();
            $total = (int) $stmt->fetchColumn();
        }

        // 今日の提出済み日報数（tenant DB）
        $stmt2 = $this->pdo->prepare(
            'SELECT COUNT(*)
             FROM t_daily_report
             WHERE report_date = CURDATE()
               AND is_submitted = 1
               AND is_deleted = 0'
        );
        $stmt2->execute();
        $submitted = (int) $stmt2->fetchColumn();
        $submitted = min($submitted, $total); // total を超えない

        return [
            'total'       => $total,
            'submitted'   => $submitted,
            'unsubmitted' => max(0, $total - $submitted),
        ];
    }

    // ─── Existing methods (kept for backward compatibility) ──────────────

    /**
     * @return array{due_today: int, upcoming_30: int, overdue: int, this_month_not_completed: int, early_deadline_overdue: int}|null
     */
    public function getRenewalSummary(): ?array
    {
        try {
            $doneSub = self::RENEWAL_DONE_SUBQUERY;

            $stmt = $this->pdo->prepare(
                'SELECT
                    COALESCE(SUM(CASE
                        WHEN rc.next_action_date = CURDATE()
                         AND rc.case_status NOT IN ' . $doneSub . '
                        THEN 1 ELSE 0 END), 0) AS due_today,
                    COALESCE(SUM(CASE
                        WHEN rc.maturity_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                         AND rc.case_status NOT IN ' . $doneSub . '
                        THEN 1 ELSE 0 END), 0) AS upcoming_30,
                    COALESCE(SUM(CASE
                        WHEN rc.next_action_date < CURDATE()
                         AND rc.case_status NOT IN ' . $doneSub . '
                        THEN 1 ELSE 0 END), 0) AS overdue,
                    COALESCE(SUM(CASE
                        WHEN rc.maturity_date BETWEEN DATE_FORMAT(CURDATE(), "%Y-%m-01")
                                              AND LAST_DAY(CURDATE())
                         AND rc.case_status NOT IN ' . $doneSub . '
                        THEN 1 ELSE 0 END), 0) AS this_month_not_completed,
                    COALESCE(SUM(CASE
                        WHEN rc.early_renewal_deadline IS NOT NULL
                         AND rc.early_renewal_deadline < CURDATE()
                         AND rc.case_status NOT IN ' . $doneSub . '
                        THEN 1 ELSE 0 END), 0) AS early_deadline_overdue
                 FROM t_renewal_case rc
                 WHERE rc.is_deleted = 0'
            );
            $stmt->execute();

            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row)) {
                return null;
            }

            return [
                'due_today'                => (int) ($row['due_today'] ?? 0),
                'upcoming_30'              => (int) ($row['upcoming_30'] ?? 0),
                'overdue'                  => (int) ($row['overdue'] ?? 0),
                'this_month_not_completed' => (int) ($row['this_month_not_completed'] ?? 0),
                'early_deadline_overdue'   => (int) ($row['early_deadline_overdue'] ?? 0),
            ];
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array{open_count: int, high_priority_open_count: int, resolved_this_month: int, new_accepted_count: int}|null
     */
    public function getAccidentSummary(): ?array
    {
        try {
            $accidentDoneSub         = self::ACCIDENT_DONE_SUBQUERY;
            $accidentHighPrioritySql = self::quotedList(self::ACCIDENT_HIGH_PRIORITIES);

            // 初期対応前の件数は「一番最初の有効ステータス（= display_order 最小）」とする。
            $firstStatusRow = $this->pdo->query(
                "SELECT name FROM m_case_status
                 WHERE case_type = 'accident' AND is_active = 1
                 ORDER BY display_order ASC, id ASC LIMIT 1"
            )->fetch(PDO::FETCH_ASSOC);
            $firstStatusName = is_array($firstStatusRow) ? (string) ($firstStatusRow['name'] ?? '') : '';

            $stmt = $this->pdo->prepare(
                'SELECT
                    COALESCE(SUM(CASE
                        WHEN ac.status NOT IN ' . $accidentDoneSub . '
                        THEN 1 ELSE 0 END), 0) AS open_count,
                    COALESCE(SUM(CASE
                        WHEN ac.priority IN (' . $accidentHighPrioritySql . ')
                         AND ac.status NOT IN ' . $accidentDoneSub . '
                        THEN 1 ELSE 0 END), 0) AS high_priority_open_count,
                    COALESCE(SUM(CASE
                        WHEN ac.resolved_date BETWEEN DATE_FORMAT(CURDATE(), "%Y-%m-01") AND CURDATE()
                        THEN 1 ELSE 0 END), 0) AS resolved_this_month,
                    COALESCE(SUM(CASE
                        WHEN ac.status = :first_status
                        THEN 1 ELSE 0 END), 0) AS new_accepted_count
                 FROM t_accident_case ac
                 WHERE ac.is_deleted = 0'
            );
            $stmt->bindValue(':first_status', $firstStatusName);
            $stmt->execute();

            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row)) {
                return null;
            }

            return [
                'open_count'               => (int) ($row['open_count'] ?? 0),
                'high_priority_open_count' => (int) ($row['high_priority_open_count'] ?? 0),
                'resolved_this_month'      => (int) ($row['resolved_this_month'] ?? 0),
                'new_accepted_count'       => (int) ($row['new_accepted_count'] ?? 0),
            ];
        } catch (Throwable) {
            return null;
        }
    }

    public function getSalesMonthlyInputCount(string $yearMonth): ?int
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT COUNT(*)
                 FROM t_sales_performance sp
                 WHERE sp.is_deleted = 0
                   AND sp.settlement_month = :year_month'
            );
            $stmt->execute(['year_month' => $yearMonth]);

            $count = $stmt->fetchColumn();
            if (!is_numeric($count)) {
                return null;
            }

            return (int) $count;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getRenewalUpcomingRows(int $limit = 5): array
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT rc.id AS renewal_case_id,
                        mc.customer_name,
                        c.product_type,
                        rc.maturity_date,
                        rc.early_renewal_deadline,
                        rc.case_status,
                        rc.next_action_date,
                        ms.staff_name AS assigned_staff_name
                 FROM t_renewal_case rc
                 INNER JOIN t_contract c ON c.id = rc.contract_id AND c.is_deleted = 0
                 INNER JOIN m_customer mc ON mc.id = c.customer_id AND mc.is_deleted = 0
                 LEFT  JOIN m_staff ms ON ms.id = rc.assigned_staff_id AND ms.is_active = 1
                 WHERE rc.is_deleted = 0
                   AND rc.case_status NOT IN ' . self::RENEWAL_DONE_SUBQUERY . '
                   AND rc.maturity_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                 ORDER BY rc.maturity_date ASC, rc.id ASC
                 LIMIT :limit'
            );
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return is_array($rows) ? $rows : [];
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getAccidentOpenRows(int $limit = 5): array
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT ac.id AS accident_case_id,
                        COALESCE(mc.customer_name, ac.prospect_name, \'\') AS customer_name,
                        ac.accident_date,
                        ac.accepted_date,
                        ac.product_type,
                        ac.priority,
                        ac.status,
                        ms.staff_name AS assigned_staff_name
                 FROM t_accident_case ac
                 LEFT  JOIN m_customer mc ON mc.id = ac.customer_id AND mc.is_deleted = 0
                 LEFT  JOIN m_staff ms ON ms.id = ac.assigned_staff_id AND ms.is_active = 1
                 WHERE ac.is_deleted = 0
                   AND ac.status NOT IN ' . self::ACCIDENT_DONE_SUBQUERY . '
                 ORDER BY ac.accepted_date DESC, ac.id DESC
                 LIMIT :limit'
            );
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return is_array($rows) ? $rows : [];
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getRecentActivityRows(int $limit = 10): array
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT a.id,
                        a.activity_date,
                        mc.customer_name,
                        a.activity_type,
                        a.content_summary,
                        ms.staff_name
                 FROM t_activity a
                 INNER JOIN m_customer mc ON mc.id = a.customer_id AND mc.is_deleted = 0
                 LEFT  JOIN m_staff ms ON ms.id = a.staff_id AND ms.is_active = 1
                 WHERE a.is_deleted = 0
                 ORDER BY a.updated_at DESC, a.id DESC
                 LIMIT :limit'
            );
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return is_array($rows) ? $rows : [];
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @param array<int, string> $values
     */
    private static function quotedList(array $values): string
    {
        return implode(', ', array_map(
            static fn (string $value): string => '"' . str_replace('"', '""', $value) . '"',
            $values
        ));
    }
}
