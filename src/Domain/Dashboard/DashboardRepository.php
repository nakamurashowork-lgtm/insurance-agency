<?php
declare(strict_types=1);

namespace App\Domain\Dashboard;

use PDO;
use Throwable;

final class DashboardRepository
{
    /** @var array<int, string> */
    private const RENEWAL_DONE_STATUSES = ['renewed', 'lost', 'closed'];

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
     * @return array{due_today: int, upcoming_30: int, overdue: int}|null
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
                        THEN 1 ELSE 0 END), 0) AS overdue
                 FROM t_renewal_case rc
                 WHERE rc.is_deleted = 0'
            );
            $stmt->execute();

            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row)) {
                return null;
            }

            return [
                'due_today' => (int) ($row['due_today'] ?? 0),
                'upcoming_30' => (int) ($row['upcoming_30'] ?? 0),
                'overdue' => (int) ($row['overdue'] ?? 0),
            ];
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * 事故業務サマリを返す。
     *
     * @return array{open_count: int, high_priority_open_count: int, resolved_this_month: int}|null
     */
    public function getAccidentSummary(): ?array
    {
        try {
            $accidentDoneSql = self::quotedList(self::ACCIDENT_DONE_STATUSES);
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
                        THEN 1 ELSE 0 END), 0) AS resolved_this_month
                 FROM t_accident_case ac
                 WHERE ac.is_deleted = 0'
            );
            $stmt->execute();

            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row)) {
                return null;
            }

            return [
                'open_count' => (int) ($row['open_count'] ?? 0),
                'high_priority_open_count' => (int) ($row['high_priority_open_count'] ?? 0),
                'resolved_this_month' => (int) ($row['resolved_this_month'] ?? 0),
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
