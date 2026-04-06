<?php
declare(strict_types=1);

namespace App\Domain\Activity;

use PDO;

final class ActivityRepository
{
    public const SORTABLE_FIELDS = [
        'activity_date',
        'activity_type',
        'customer_name',
        'staff_id',
        'next_action_date',
    ];

    public function __construct(private PDO $pdo)
    {
    }

    /**
     * @param array<string, string> $criteria
     * @return array{rows: array<int, array<string, mixed>>, total: int, page: int, per_page: int}
     */
    public function searchPage(
        array $criteria,
        int $page,
        int $perPage,
        string $sort,
        string $direction
    ): array {
        $page    = max(1, $page);
        $perPage = max(1, min($perPage, 500));

        $params   = [];
        $whereSql = $this->buildWhereClause($criteria, $params);
        $total    = $this->countSearch($whereSql, $params);
        $maxPage  = max(1, (int) ceil($total / $perPage));
        $page     = min($page, $maxPage);
        $offset   = ($page - 1) * $perPage;

        $sql =
            'SELECT a.id,
                    a.customer_id,
                    a.renewal_case_id,
                    a.accident_case_id,
                    a.sales_case_id,
                    a.activity_date,
                    a.start_time,
                    a.end_time,
                    a.activity_type,
                    a.purpose_type,
                    a.visit_place,
                    a.interviewee_name,
                    a.subject,
                    a.content_summary,
                    a.detail_text,
                    a.next_action_date,
                    a.next_action_note,
                    a.result_type,
                    a.staff_id,
                    a.created_at,
                    a.updated_at,
                    mc.customer_name,
                    COALESCE(dr.is_submitted, 0) AS daily_is_submitted
             FROM t_activity a
             LEFT JOIN m_customer mc
                     ON mc.id = a.customer_id
                    AND mc.is_deleted = 0
             LEFT JOIN t_daily_report dr
                     ON dr.report_date = a.activity_date
                    AND dr.staff_user_id = a.staff_id
                    AND dr.is_deleted = 0'
            . $whereSql
            . $this->buildOrderBy($sort, $direction)
            . ' LIMIT :limit OFFSET :offset';

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();

        return [
            'rows'     => is_array($rows) ? $rows : [],
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT a.id,
                    a.customer_id,
                    a.contract_id,
                    a.renewal_case_id,
                    a.accident_case_id,
                    a.sales_case_id,
                    a.activity_date,
                    a.start_time,
                    a.end_time,
                    a.activity_type,
                    a.purpose_type,
                    a.visit_place,
                    a.interviewee_name,
                    a.subject,
                    a.content_summary,
                    a.detail_text,
                    a.next_action_date,
                    a.next_action_note,
                    a.result_type,
                    a.staff_id,
                    a.created_at,
                    a.updated_at,
                    mc.customer_name
             FROM t_activity a
             LEFT JOIN m_customer mc
                     ON mc.id = a.customer_id
                    AND mc.is_deleted = 0
             WHERE a.id = :id
               AND a.is_deleted = 0'
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @param array<string, mixed> $input
     */
    public function create(array $input, int $actorUserId): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO t_activity
                (customer_id, contract_id, renewal_case_id, accident_case_id, sales_case_id,
                 activity_date, start_time, end_time, activity_type, purpose_type,
                 visit_place, interviewee_name, subject, content_summary, detail_text,
                 next_action_date, next_action_note, result_type, staff_id)
             VALUES
                (:customer_id, :contract_id, :renewal_case_id, :accident_case_id, :sales_case_id,
                 :activity_date, :start_time, :end_time, :activity_type, :purpose_type,
                 :visit_place, :interviewee_name, :subject, :content_summary, :detail_text,
                 :next_action_date, :next_action_note, :result_type, :staff_id)'
        );
        $this->bindActivityParams($stmt, $input, $actorUserId);
        $stmt->execute();

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @param array<string, mixed> $input
     */
    public function update(int $id, array $input, int $actorUserId): int
    {
        $stmt = $this->pdo->prepare(
            'UPDATE t_activity SET
                customer_id       = :customer_id,
                contract_id       = :contract_id,
                renewal_case_id   = :renewal_case_id,
                accident_case_id  = :accident_case_id,
                sales_case_id     = :sales_case_id,
                activity_date     = :activity_date,
                start_time        = :start_time,
                end_time          = :end_time,
                activity_type     = :activity_type,
                purpose_type      = :purpose_type,
                visit_place       = :visit_place,
                interviewee_name  = :interviewee_name,
                subject           = :subject,
                content_summary   = :content_summary,
                detail_text       = :detail_text,
                next_action_date  = :next_action_date,
                next_action_note  = :next_action_note,
                result_type       = :result_type,
                staff_id     = :staff_id
             WHERE id = :id
               AND is_deleted = 0'
        );
        $this->bindActivityParams($stmt, $input, $actorUserId);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount();
    }

    public function softDelete(int $id): int
    {
        $stmt = $this->pdo->prepare(
            'UPDATE t_activity SET is_deleted = 1 WHERE id = :id AND is_deleted = 0'
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchCustomers(int $limit = 1000): array
    {
        // 「（社内・顧客なし）」を先頭に固定し、残りを customer_name 昇順で返す
        $stmt = $this->pdo->prepare(
            'SELECT id, customer_name FROM m_customer
             WHERE is_deleted = 0
             ORDER BY (customer_name = \'（社内・顧客なし）\') DESC, customer_name ASC
             LIMIT :limit'
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param array<string, string> $criteria
     * @param array<string, mixed> $params
     */
    private function buildWhereClause(array $criteria, array &$params): string
    {
        $sql = ' WHERE a.is_deleted = 0';

        $dateFrom = trim((string) ($criteria['activity_date_from'] ?? ''));
        if ($dateFrom !== '') {
            $sql .= ' AND a.activity_date >= :activity_date_from';
            $params['activity_date_from'] = $dateFrom;
        }

        $dateTo = trim((string) ($criteria['activity_date_to'] ?? ''));
        if ($dateTo !== '') {
            $sql .= ' AND a.activity_date <= :activity_date_to';
            $params['activity_date_to'] = $dateTo;
        }

        $customerName = trim((string) ($criteria['customer_name'] ?? ''));
        if ($customerName !== '') {
            $sql .= ' AND mc.customer_name LIKE :customer_name';
            $params['customer_name'] = '%' . $customerName . '%';
        }

        $activityType = trim((string) ($criteria['activity_type'] ?? ''));
        if ($activityType !== '') {
            $sql .= ' AND a.activity_type = :activity_type';
            $params['activity_type'] = $activityType;
        }

        $staffUserId = trim((string) ($criteria['staff_id'] ?? ''));
        if ($staffUserId !== '' && ctype_digit($staffUserId)) {
            $sql .= ' AND a.staff_id = :staff_id';
            $params['staff_id'] = (int) $staffUserId;
        }

        $dailyReportStatus = trim((string) ($criteria['daily_report_status'] ?? ''));
        if ($dailyReportStatus === 'submitted') {
            $sql .= ' AND COALESCE(dr.is_submitted, 0) = 1';
        } elseif ($dailyReportStatus === 'not_submitted') {
            $sql .= ' AND COALESCE(dr.is_submitted, 0) = 0';
        }

        return $sql;
    }

    /**
     * @param array<string, mixed> $params
     */
    private function countSearch(string $whereSql, array $params): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*)
             FROM t_activity a
             LEFT JOIN m_customer mc
                     ON mc.id = a.customer_id
                    AND mc.is_deleted = 0
             LEFT JOIN t_daily_report dr
                     ON dr.report_date = a.activity_date
                    AND dr.staff_user_id = a.staff_id
                    AND dr.is_deleted = 0'
            . $whereSql
        );
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    private function buildOrderBy(string $sort, string $direction): string
    {
        $dir = strtolower($direction) === 'desc' ? 'DESC' : 'ASC';

        if ($sort === 'customer_name') {
            return ' ORDER BY mc.customer_name ' . $dir . ', a.activity_date DESC, a.id DESC';
        }

        $allowed = array_flip(self::SORTABLE_FIELDS);
        if (isset($allowed[$sort])) {
            return ' ORDER BY a.' . $sort . ' ' . $dir . ', a.id DESC';
        }

        return ' ORDER BY a.activity_date DESC, a.id DESC';
    }

    /**
     * @param array<string, mixed> $input
     */
    private function bindActivityParams(\PDOStatement $stmt, array $input, int $actorUserId): void
    {
        $nullableInt = static function (mixed $v): ?int {
            $s = trim((string) $v);
            return ($s !== '' && ctype_digit($s)) ? (int) $s : null;
        };
        $nullableStr = static function (mixed $v): ?string {
            $s = trim((string) $v);
            return $s !== '' ? $s : null;
        };

        $customerId = $nullableInt($input['customer_id'] ?? null);
        $staffUserId = $nullableInt($input['staff_id'] ?? null) ?? $actorUserId;

        $stmt->bindValue(':customer_id', $customerId, $customerId !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':contract_id', $nullableInt($input['contract_id'] ?? null), PDO::PARAM_INT);
        $stmt->bindValue(':renewal_case_id', $nullableInt($input['renewal_case_id'] ?? null), PDO::PARAM_INT);
        $stmt->bindValue(':accident_case_id', $nullableInt($input['accident_case_id'] ?? null), PDO::PARAM_INT);
        $stmt->bindValue(':sales_case_id', null, PDO::PARAM_NULL);
        $stmt->bindValue(':activity_date', trim((string) ($input['activity_date'] ?? '')));
        $stmt->bindValue(':start_time', $nullableStr($input['start_time'] ?? null));
        $stmt->bindValue(':end_time', $nullableStr($input['end_time'] ?? null));
        $stmt->bindValue(':activity_type', trim((string) ($input['activity_type'] ?? '')));
        $stmt->bindValue(':purpose_type', $nullableStr($input['purpose_type'] ?? null));
        $stmt->bindValue(':visit_place', $nullableStr($input['visit_place'] ?? null));
        $stmt->bindValue(':interviewee_name', $nullableStr($input['interviewee_name'] ?? null));
        $stmt->bindValue(':subject', $nullableStr($input['subject'] ?? null));
        $stmt->bindValue(':content_summary', trim((string) ($input['content_summary'] ?? '')));
        $stmt->bindValue(':detail_text', $nullableStr($input['detail_text'] ?? null));
        $stmt->bindValue(':next_action_date', $nullableStr($input['next_action_date'] ?? null));
        $stmt->bindValue(':next_action_note', $nullableStr($input['next_action_note'] ?? null));
        $stmt->bindValue(':result_type', $nullableStr($input['result_type'] ?? null));
        $stmt->bindValue(':staff_id', $staffUserId, PDO::PARAM_INT);
    }
}
