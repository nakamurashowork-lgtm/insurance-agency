<?php
declare(strict_types=1);

namespace App\Domain\Dashboard;

use PDO;
use Throwable;

final class DashboardRepository
{
    /** @var array<int, string> */
    private const RENEWAL_DONE_STATUSES = ['completed'];

    /** @var array<int, string> */
    private const ACCIDENT_DONE_STATUSES = ['resolved', 'closed'];

    /** @var array<int, string> */
    private const ACCIDENT_HIGH_PRIORITIES = ['high', 'urgent'];

    public function __construct(private PDO $pdo)
    {
    }

    /**
     * 満期業務サマリを返す。
     *
     * @return array{due_today: int, upcoming_30: int, overdue: int, this_month_not_completed: int, early_deadline_overdue: int}|null
     */
    public function getRenewalSummary(): ?array
    {
        try {
            $renewalDoneSql = self::quotedList(self::RENEWAL_DONE_STATUSES);

            $stmt = $this->pdo->prepare(
                'SELECT
                    COALESCE(SUM(CASE
                        WHEN rc.next_action_date = CURDATE()
                         AND rc.case_status NOT IN (' . $renewalDoneSql . ')
                        THEN 1 ELSE 0 END), 0) AS due_today,
                    COALESCE(SUM(CASE
                        WHEN rc.maturity_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                         AND rc.case_status NOT IN (' . $renewalDoneSql . ')
                        THEN 1 ELSE 0 END), 0) AS upcoming_30,
                    COALESCE(SUM(CASE
                        WHEN rc.next_action_date < CURDATE()
                         AND rc.case_status NOT IN (' . $renewalDoneSql . ')
                        THEN 1 ELSE 0 END), 0) AS overdue,
                    COALESCE(SUM(CASE
                        WHEN rc.maturity_date BETWEEN DATE_FORMAT(CURDATE(), "%Y-%m-01")
                                              AND LAST_DAY(CURDATE())
                         AND rc.case_status NOT IN (' . $renewalDoneSql . ')
                        THEN 1 ELSE 0 END), 0) AS this_month_not_completed,
                    COALESCE(SUM(CASE
                        WHEN rc.early_renewal_deadline IS NOT NULL
                         AND rc.early_renewal_deadline < CURDATE()
                         AND rc.case_status NOT IN (' . $renewalDoneSql . ')
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
     * 事故業務サマリを返す。
     *
     * @return array{open_count: int, high_priority_open_count: int, resolved_this_month: int, new_accepted_count: int}|null
     */
    public function getAccidentSummary(): ?array
    {
        try {
            $accidentDoneSql      = self::quotedList(self::ACCIDENT_DONE_STATUSES);
            $accidentHighPrioritySql = self::quotedList(self::ACCIDENT_HIGH_PRIORITIES);

            $stmt = $this->pdo->prepare(
                'SELECT
                    COALESCE(SUM(CASE
                        WHEN ac.status NOT IN (' . $accidentDoneSql . ')
                        THEN 1 ELSE 0 END), 0) AS open_count,
                    COALESCE(SUM(CASE
                        WHEN ac.priority IN (' . $accidentHighPrioritySql . ')
                         AND ac.status NOT IN (' . $accidentDoneSql . ')
                        THEN 1 ELSE 0 END), 0) AS high_priority_open_count,
                    COALESCE(SUM(CASE
                        WHEN ac.resolved_date BETWEEN DATE_FORMAT(CURDATE(), "%Y-%m-01") AND CURDATE()
                        THEN 1 ELSE 0 END), 0) AS resolved_this_month,
                    COALESCE(SUM(CASE
                        WHEN ac.status = "accepted"
                        THEN 1 ELSE 0 END), 0) AS new_accepted_count
                 FROM t_accident_case ac
                 WHERE ac.is_deleted = 0'
            );
            $stmt->execute();

            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row)) {
                return null;
            }

            return [
                'open_count'              => (int) ($row['open_count'] ?? 0),
                'high_priority_open_count' => (int) ($row['high_priority_open_count'] ?? 0),
                'resolved_this_month'     => (int) ($row['resolved_this_month'] ?? 0),
                'new_accepted_count'      => (int) ($row['new_accepted_count'] ?? 0),
            ];
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * 実績の今月入力件数を返す。
     * settlement_month が NULL のレコードは除外する。
     */
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
     * 今後30日以内に満期を迎える未完了案件（最大 $limit 件）を返す。
     *
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
                        rc.case_status
                 FROM t_renewal_case rc
                 INNER JOIN t_contract c ON c.id = rc.contract_id AND c.is_deleted = 0
                 INNER JOIN m_customer mc ON mc.id = c.customer_id AND mc.is_deleted = 0
                 WHERE rc.is_deleted = 0
                   AND rc.case_status != "completed"
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
     * 対応中の事故案件（最大 $limit 件）を返す。
     *
     * @return array<int, array<string, mixed>>
     */
    public function getAccidentOpenRows(int $limit = 5): array
    {
        try {
            $accidentDoneSql = self::quotedList(self::ACCIDENT_DONE_STATUSES);
            $stmt = $this->pdo->prepare(
                'SELECT ac.id AS accident_case_id,
                        mc.customer_name,
                        ac.accident_date,
                        ac.accepted_date,
                        ac.status
                 FROM t_accident_case ac
                 INNER JOIN m_customer mc ON mc.id = ac.customer_id AND mc.is_deleted = 0
                 WHERE ac.is_deleted = 0
                   AND ac.status NOT IN (' . $accidentDoneSql . ')
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
     * 当日の活動記録（最大 $limit 件）を返す。t_activity が存在しない場合は空配列。
     *
     * @return array<int, array<string, mixed>>
     */
    public function getTodayActivityRows(int $userId, string $today, int $limit = 10): array
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT a.id,
                        a.start_time,
                        mc.customer_name,
                        a.activity_type,
                        a.content_summary,
                        a.result_type
                 FROM t_activity a
                 INNER JOIN m_customer mc ON mc.id = a.customer_id AND mc.is_deleted = 0
                 WHERE a.is_deleted = 0
                   AND a.activity_date = :today
                   AND a.staff_user_id = :user_id
                 ORDER BY a.start_time ASC, a.id ASC
                 LIMIT :limit'
            );
            $stmt->bindValue(':today', $today);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
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
