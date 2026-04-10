<?php
declare(strict_types=1);

namespace App\Domain\Sales;

use PDO;

final class SalesPerformanceRepository
{
    public const SORTABLE_FIELDS = [
        'performance_date',
        'performance_type',
        'source_type',
        'customer_name',
        'staff_id',
        'policy_no',
        'product_type',
        'premium_amount',
        'settlement_month',
    ];

    public function __construct(private PDO $pdo)
    {
    }

    /**
     * @param array<string, string> $criteria
     * @return array<int, array<string, mixed>>
     */
    public function search(array $criteria, int $limit = 200): array
    {
        $result = $this->searchPage($criteria, 1, $limit, '', 'asc');
        return $result['rows'];
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
        $page = max(1, $page);
        $perPage = max(1, min($perPage, 500));

        $params = [];
        $whereSql = $this->buildWhereClause($criteria, $params);
        $total = $this->countSearch($whereSql, $params);
        $maxPage = max(1, (int) ceil($total / $perPage));
        $page = min($page, $maxPage);
        $offset = ($page - 1) * $perPage;

        $sql =
            'SELECT sp.id,
                    sp.customer_id,
                    sp.contract_id,
                    sp.renewal_case_id,
                    sp.performance_date,
                    sp.performance_type,
                    sp.source_type,
                    sp.policy_no,
                    sp.policy_start_date,
                    sp.application_date,
                    sp.insurance_category,
                    sp.product_type,
                    sp.premium_amount,
                    sp.installment_count,
                    sp.receipt_no,
                    sp.settlement_month,
                    sp.staff_id,
                    sp.remark,
                    sp.updated_at,
                    mc.customer_name,
                    c.policy_no AS contract_policy_no,
                    c.policy_start_date AS contract_policy_start_date,
                    c.insurance_category AS contract_insurance_category,
                    c.product_type AS contract_product_type,
                    COALESCE(NULLIF(sp.policy_no, ""), c.policy_no) AS policy_no_display
             FROM t_sales_performance sp
             INNER JOIN m_customer mc
                     ON mc.id = sp.customer_id
                    AND mc.is_deleted = 0
             LEFT JOIN t_contract c
                    ON c.id = sp.contract_id
                   AND c.is_deleted = 0'
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
            'rows' => is_array($rows) ? $rows : [],
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    /**
     * @param array<string, string> $criteria
     * @param array<string, mixed> $params
     */
    private function buildWhereClause(array $criteria, array &$params): string
    {
        $sql = ' WHERE sp.is_deleted = 0';

        // 年度（4月始まり）+ 月の組み合わせで日付範囲を構築する
        // 年度のみ   → fiscal_year-04-01 〜 (fiscal_year+1)-04-01
        // 年度 + 月  → 月>=4: fiscal_year年のその月 / 月<=3: (fiscal_year+1)年のその月
        // 月のみ     → MONTH() で全年度横断絞り込み
        $fiscalYear  = trim((string) ($criteria['performance_fiscal_year'] ?? ''));
        $monthNum    = trim((string) ($criteria['performance_month_num'] ?? ''));
        $hasFY       = $fiscalYear !== '' && ctype_digit($fiscalYear);
        $hasMonth    = $monthNum !== '' && ctype_digit($monthNum)
                       && (int) $monthNum >= 1 && (int) $monthNum <= 12;

        if ($hasFY && $hasMonth) {
            $fy       = (int) $fiscalYear;
            $mn       = (int) $monthNum;
            $calYear  = $mn >= 4 ? $fy : $fy + 1;
            $dateFrom = sprintf('%04d-%02d-01', $calYear, $mn);
            $dateTo   = date('Y-m-d', (int) strtotime($dateFrom . ' +1 month'));
            $sql .= ' AND sp.performance_date >= :performance_date_from AND sp.performance_date < :performance_date_to';
            $params['performance_date_from'] = $dateFrom;
            $params['performance_date_to']   = $dateTo;
        } elseif ($hasFY) {
            $fy = (int) $fiscalYear;
            $sql .= ' AND sp.performance_date >= :performance_date_from AND sp.performance_date < :performance_date_to';
            $params['performance_date_from'] = $fy . '-04-01';
            $params['performance_date_to']   = ($fy + 1) . '-04-01';
        } elseif ($hasMonth) {
            $sql .= ' AND MONTH(sp.performance_date) = :performance_month_num';
            $params['performance_month_num'] = (int) $monthNum;
        }

        $customerName = trim((string) ($criteria['customer_name'] ?? ''));
        if ($customerName !== '') {
            $sql .= ' AND mc.customer_name LIKE :customer_name';
            $params['customer_name'] = '%' . $customerName . '%';
        }

        $staffUserId = trim((string) ($criteria['staff_id'] ?? ''));
        if ($staffUserId !== '' && ctype_digit($staffUserId)) {
            $sql .= ' AND sp.staff_id = :staff_id';
            $params['staff_id'] = (int) $staffUserId;
        }

        $sourceType = trim((string) ($criteria['source_type'] ?? ''));
        if ($sourceType !== '') {
            $sql .= ' AND sp.source_type = :source_type';
            $params['source_type'] = $sourceType;
        }

        $performanceType = trim((string) ($criteria['performance_type'] ?? ''));
        if ($performanceType !== '') {
            $sql .= ' AND sp.performance_type = :performance_type';
            $params['performance_type'] = $performanceType;
        }

        $policyNo = trim((string) ($criteria['policy_no'] ?? ''));
        if ($policyNo !== '') {
            $sql .= ' AND COALESCE(NULLIF(sp.policy_no, ""), c.policy_no) LIKE :policy_no';
            $params['policy_no'] = '%' . $policyNo . '%';
        }

        $productType = trim((string) ($criteria['product_type'] ?? ''));
        if ($productType !== '') {
            $sql .= ' AND sp.product_type LIKE :product_type';
            $params['product_type'] = '%' . $productType . '%';
        }

        $settlementMonth = trim((string) ($criteria['settlement_month'] ?? ''));
        if ($settlementMonth !== '') {
            $sql .= ' AND sp.settlement_month = :settlement_month';
            $params['settlement_month'] = $settlementMonth;
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
             FROM t_sales_performance sp
             INNER JOIN m_customer mc
                     ON mc.id = sp.customer_id
                    AND mc.is_deleted = 0
             LEFT JOIN t_contract c
                    ON c.id = sp.contract_id
                   AND c.is_deleted = 0'
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
        $column = match ($sort) {
            'performance_type' => 'sp.performance_type',
            'source_type' => 'sp.source_type',
            'customer_name' => 'mc.customer_name',
            'staff_id' => 'sp.staff_id',
            'policy_no' => 'COALESCE(NULLIF(sp.policy_no, ""), c.policy_no)',
            'product_type' => 'sp.product_type',
            'premium_amount' => 'sp.premium_amount',
            'settlement_month' => 'sp.settlement_month',
            'performance_date' => 'sp.performance_date',
            default => 'sp.performance_date',
        };

        $dir = strtolower($direction) === 'desc' ? 'DESC' : 'ASC';
        $fallbackDir = $dir === 'DESC' ? 'DESC' : 'ASC';

        return ' ORDER BY ' . $column . ' ' . $dir . ', sp.id ' . $fallbackDir;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT sp.id,
                  sp.customer_id,
                  sp.contract_id,
                  sp.renewal_case_id,
                  sp.performance_date,
                  sp.performance_type,
                sp.source_type,
                sp.policy_no,
                sp.policy_start_date,
                sp.application_date,
                  sp.insurance_category,
                  sp.product_type,
                  sp.premium_amount,
                sp.installment_count,
                  sp.receipt_no,
                  sp.settlement_month,
                  sp.staff_id,
                  sp.remark,
                  mc.customer_name,
                c.policy_no AS contract_policy_no,
                c.policy_start_date AS contract_policy_start_date,
                c.insurance_category AS contract_insurance_category,
                c.product_type AS contract_product_type,
                COALESCE(NULLIF(sp.policy_no, ""), c.policy_no) AS policy_no_display
              FROM t_sales_performance sp
              INNER JOIN m_customer mc
                ON mc.id = sp.customer_id
                  AND mc.is_deleted = 0
              LEFT JOIN t_contract c
                  ON c.id = sp.contract_id
                 AND c.is_deleted = 0
              WHERE sp.id = :id
             AND sp.is_deleted = 0
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * 指定年月の集計指標を返す。
     *
     * @return array{non_life_month: int, non_life_ytd: int, general_month: int, total_count_month: int}
     */
    public function fetchMonthlyMetrics(int $year, int $month): array
    {
        // 会計年度開始（4月始まり）
        $fyStartYear = $month >= 4 ? $year : $year - 1;
        $fyStart     = sprintf('%04d-04-01', $fyStartYear);
        $monthStart  = sprintf('%04d-%02d-01', $year, $month);
        $monthEnd    = date('Y-m-t', (int) mktime(0, 0, 0, $month, 1, $year));

        $stmt = $this->pdo->prepare(
            "SELECT
                SUM(CASE WHEN source_type = 'non_life'
                         AND performance_date BETWEEN :ms1 AND :me1
                    THEN premium_amount ELSE 0 END) AS non_life_month,
                SUM(CASE WHEN source_type = 'non_life'
                         AND performance_date >= :fy AND performance_date <= :me2
                    THEN premium_amount ELSE 0 END) AS non_life_ytd,
                SUM(CASE WHEN source_type = 'non_life'
                         AND performance_date BETWEEN :ms2 AND :me3
                         AND (insurance_category IS NULL OR insurance_category <> '自動車')
                    THEN premium_amount ELSE 0 END) AS general_month,
                COUNT(CASE WHEN performance_date BETWEEN :ms3 AND :me4 THEN 1 END) AS total_count_month
             FROM t_sales_performance
             WHERE is_deleted = 0"
        );
        $stmt->execute([
            'ms1' => $monthStart,
            'me1' => $monthEnd,
            'fy'  => $fyStart,
            'me2' => $monthEnd,
            'ms2' => $monthStart,
            'me3' => $monthEnd,
            'ms3' => $monthStart,
            'me4' => $monthEnd,
        ]);
        $row = $stmt->fetch();
        if (!is_array($row)) {
            return ['non_life_month' => 0, 'non_life_ytd' => 0, 'general_month' => 0, 'total_count_month' => 0];
        }

        return [
            'non_life_month'    => (int) ($row['non_life_month'] ?? 0),
            'non_life_ytd'      => (int) ($row['non_life_ytd'] ?? 0),
            'general_month'     => (int) ($row['general_month'] ?? 0),
            'total_count_month' => (int) ($row['total_count_month'] ?? 0),
        ];
    }

    /**
     * t_sales_performance に成績が存在する年月を降順で返す
     *
     * @return array<int, string>  例: ['2026-04', '2026-03', ...]
     */
    public function fetchPerformanceMonths(): array
    {
        $stmt = $this->pdo->query(
            'SELECT DATE_FORMAT(performance_date, "%Y-%m") AS ym
             FROM t_sales_performance
             WHERE is_deleted = 0
             GROUP BY ym
             ORDER BY ym DESC'
        );
        if ($stmt === false) {
            return [];
        }
        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
        return is_array($rows) ? array_values($rows) : [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchCustomers(int $limit = 5000): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, customer_name
             FROM m_customer
             WHERE is_deleted = 0
             ORDER BY customer_name ASC, id ASC
             LIMIT :limit'
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchContracts(int $limit = 500): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT c.id,
                    c.customer_id,
                    c.policy_no,
                    c.insurance_category,
                    c.product_type,
                    c.policy_start_date,
                    c.policy_end_date,
                    mc.customer_name
             FROM t_contract c
             INNER JOIN m_customer mc
                     ON mc.id = c.customer_id
                    AND mc.is_deleted = 0
             WHERE c.is_deleted = 0
             ORDER BY c.policy_end_date DESC, c.id DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchRenewalCases(int $limit = 500): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT rc.id,
                    rc.contract_id,
                    rc.assigned_staff_id,
                    rc.maturity_date,
                    rc.case_status,
                    c.customer_id,
                    c.policy_no,
                    c.product_type,
                    c.insurance_category,
                    c.policy_start_date,
                    c.premium_amount AS prev_premium_amount,
                    cust.customer_name
             FROM t_renewal_case rc
             LEFT JOIN t_contract c
                    ON c.id = rc.contract_id
                   AND c.is_deleted = 0
             LEFT JOIN m_customer cust
                    ON cust.id = c.customer_id
                   AND cust.is_deleted = 0
             WHERE rc.is_deleted = 0
               AND rc.case_status NOT IN (\'renewed\', \'lost\', \'closed\')
             ORDER BY rc.maturity_date DESC, rc.id DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param array<string, mixed> $input
     */
    public function create(array $input, int $userId): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO t_sales_performance (
                customer_id,
                contract_id,
                renewal_case_id,
                performance_date,
                performance_type,
                source_type,
                policy_no,
                policy_start_date,
                application_date,
                insurance_category,
                product_type,
                premium_amount,
                installment_count,
                receipt_no,
                settlement_month,
                staff_id,
                remark,
                created_by,
                updated_by
             ) VALUES (
                :customer_id,
                :contract_id,
                :renewal_case_id,
                :performance_date,
                :performance_type,
                :source_type,
                :policy_no,
                :policy_start_date,
                :application_date,
                :insurance_category,
                :product_type,
                :premium_amount,
                :installment_count,
                :receipt_no,
                :settlement_month,
                :staff_id,
                :remark,
                :created_by,
                :updated_by
             )'
        );

        $stmt->execute([
            'customer_id' => $input['customer_id'] ?? 0,
            'contract_id' => $input['contract_id'] ?? null,
            'renewal_case_id' => $input['renewal_case_id'] ?? null,
            'performance_date' => $input['performance_date'] ?? null,
            'performance_type' => $input['performance_type'] ?? null,
            'source_type' => $input['source_type'] ?? null,
            'policy_no' => $input['policy_no'] ?? null,
            'policy_start_date' => $input['policy_start_date'] ?? null,
            'application_date' => $input['application_date'] ?? null,
            'insurance_category' => $input['insurance_category'] ?? null,
            'product_type' => $input['product_type'] ?? null,
            'premium_amount' => $input['premium_amount'] ?? 0,
            'installment_count' => $input['installment_count'] ?? null,
            'receipt_no' => $input['receipt_no'] ?? null,
            'settlement_month' => $input['settlement_month'] ?? null,
            'staff_id' => $input['staff_id'] ?? null,
            'remark' => $input['remark'] ?? null,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);
    }

    /**
     * @param array<string, mixed> $input
     */
    public function update(int $id, array $input, int $userId): int
    {
        $before = $this->findForAudit($id);

        $stmt = $this->pdo->prepare(
            'UPDATE t_sales_performance
             SET customer_id = :customer_id,
                 contract_id = :contract_id,
                 renewal_case_id = :renewal_case_id,
                 performance_date = :performance_date,
                 performance_type = :performance_type,
                 source_type = :source_type,
                 policy_no = :policy_no,
                 policy_start_date = :policy_start_date,
                 application_date = :application_date,
                 insurance_category = :insurance_category,
                 product_type = :product_type,
                 premium_amount = :premium_amount,
                 installment_count = :installment_count,
                 receipt_no = :receipt_no,
                 settlement_month = :settlement_month,
                 staff_id = :staff_id,
                 remark = :remark,
                 updated_by = :updated_by
             WHERE id = :id
               AND is_deleted = 0'
        );

        $stmt->execute([
            'id' => $id,
            'customer_id' => $input['customer_id'] ?? 0,
            'contract_id' => $input['contract_id'] ?? null,
            'renewal_case_id' => $input['renewal_case_id'] ?? null,
            'performance_date' => $input['performance_date'] ?? null,
            'performance_type' => $input['performance_type'] ?? null,
            'source_type' => $input['source_type'] ?? null,
            'policy_no' => $input['policy_no'] ?? null,
            'policy_start_date' => $input['policy_start_date'] ?? null,
            'application_date' => $input['application_date'] ?? null,
            'insurance_category' => $input['insurance_category'] ?? null,
            'product_type' => $input['product_type'] ?? null,
            'premium_amount' => $input['premium_amount'] ?? 0,
            'installment_count' => $input['installment_count'] ?? null,
            'receipt_no' => $input['receipt_no'] ?? null,
            'settlement_month' => $input['settlement_month'] ?? null,
            'staff_id' => $input['staff_id'] ?? null,
            'remark' => $input['remark'] ?? null,
            'updated_by' => $userId,
        ]);

        $affected = $stmt->rowCount();

        if ($affected > 0 && $before !== null) {
            $after = $this->findForAudit($id);
            if ($after !== null) {
                $details = $this->buildAuditDetails($before, $after);
                $eventId = $this->insertAuditEvent($id, $userId, '成績情報を更新');
                if ($details !== []) {
                    $this->insertAuditEventDetails($eventId, $details);
                }
            }
        }

        return $affected;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findAuditEvents(int $salesPerformanceId, int $limit = 50): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, changed_at, changed_by, action_type, change_source, note
             FROM t_audit_event
             WHERE entity_type = "sales_performance"
               AND entity_id = :entity_id
             ORDER BY changed_at DESC, id DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':entity_id', $salesPerformanceId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $events = $stmt->fetchAll();
        if (!is_array($events) || $events === []) {
            return [];
        }

        $eventIds = [];
        foreach ($events as $row) {
            if (!is_array($row)) {
                continue;
            }
            $eventId = (int) ($row['id'] ?? 0);
            if ($eventId > 0) {
                $eventIds[] = $eventId;
            }
        }

        $detailsByEventId = $this->findAuditEventDetails($eventIds);
        foreach ($events as $index => $row) {
            if (!is_array($row)) {
                continue;
            }
            $eventId = (int) ($row['id'] ?? 0);
            $events[$index]['details'] = $detailsByEventId[$eventId] ?? [];
        }

        return $events;
    }

    /**
     * @return array<string, string|null>|null
     */
    private function findForAudit(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT performance_date, performance_type, source_type,
                    customer_id, staff_id, insurance_category,
                    product_type, premium_amount, policy_no, policy_start_date,
                    application_date, receipt_no, settlement_month, installment_count,
                    contract_id, renewal_case_id, remark
             FROM t_sales_performance
             WHERE id = :id AND is_deleted = 0
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        if (!is_array($row)) {
            return null;
        }

        $normalize = static function (mixed $v): ?string {
            if ($v === null) {
                return null;
            }
            $s = trim((string) $v);
            return $s === '' ? null : $s;
        };

        return [
            'performance_date'   => $normalize($row['performance_date'] ?? null),
            'performance_type'   => $normalize($row['performance_type'] ?? null),
            'source_type'        => $normalize($row['source_type'] ?? null),
            'customer_id'        => $normalize($row['customer_id'] ?? null),
            'staff_id'           => $normalize($row['staff_id'] ?? null),
            'insurance_category' => $normalize($row['insurance_category'] ?? null),
            'product_type'       => $normalize($row['product_type'] ?? null),
            'premium_amount'     => $normalize($row['premium_amount'] ?? null),
            'policy_no'          => $normalize($row['policy_no'] ?? null),
            'policy_start_date'  => $normalize($row['policy_start_date'] ?? null),
            'application_date'   => $normalize($row['application_date'] ?? null),
            'receipt_no'         => $normalize($row['receipt_no'] ?? null),
            'settlement_month'   => $normalize($row['settlement_month'] ?? null),
            'installment_count'  => $normalize($row['installment_count'] ?? null),
            'contract_id'        => $normalize($row['contract_id'] ?? null),
            'renewal_case_id'    => $normalize($row['renewal_case_id'] ?? null),
            'remark'             => $normalize($row['remark'] ?? null),
        ];
    }

    /**
     * @param array<string, string|null> $before
     * @param array<string, string|null> $after
     * @return array<int, array<string, string|null>>
     */
    private function buildAuditDetails(array $before, array $after): array
    {
        $fields = [
            'performance_date'   => ['label' => '成績計上日',  'value_type' => 'DATE'],
            'performance_type'   => ['label' => '成績区分',    'value_type' => 'STRING'],
            'source_type'        => ['label' => '業務区分',    'value_type' => 'STRING'],
            'customer_id'        => ['label' => '契約者ID',    'value_type' => 'NUMBER'],
            'staff_id'           => ['label' => '担当者ID',    'value_type' => 'NUMBER'],
            'insurance_category' => ['label' => '保険種類',    'value_type' => 'STRING'],
            'product_type'       => ['label' => '種目',        'value_type' => 'STRING'],
            'premium_amount'     => ['label' => '保険料',      'value_type' => 'NUMBER'],
            'policy_no'          => ['label' => '証券番号',    'value_type' => 'STRING'],
            'policy_start_date'  => ['label' => '始期日',      'value_type' => 'DATE'],
            'application_date'   => ['label' => '申込日',      'value_type' => 'DATE'],
            'receipt_no'         => ['label' => '領収証番号',  'value_type' => 'STRING'],
            'settlement_month'   => ['label' => '精算月',      'value_type' => 'STRING'],
            'installment_count'  => ['label' => '分割回数',    'value_type' => 'NUMBER'],
            'contract_id'        => ['label' => '関連契約ID',  'value_type' => 'NUMBER'],
            'renewal_case_id'    => ['label' => '関連満期案件ID', 'value_type' => 'NUMBER'],
            'remark'             => ['label' => '備考',        'value_type' => 'STRING'],
        ];

        $details = [];
        foreach ($fields as $fieldKey => $meta) {
            $beforeValue = $before[$fieldKey] ?? null;
            $afterValue  = $after[$fieldKey]  ?? null;
            if ($beforeValue === $afterValue) {
                continue;
            }
            $details[] = [
                'field_key'         => $fieldKey,
                'field_label'       => (string) $meta['label'],
                'value_type'        => (string) $meta['value_type'],
                'before_value_text' => $beforeValue,
                'after_value_text'  => $afterValue,
            ];
        }

        return $details;
    }

    private function insertAuditEvent(int $salesPerformanceId, int $changedBy, string $note): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO t_audit_event (
                entity_type, entity_id, action_type, change_source, changed_by, note
             ) VALUES (
                "sales_performance", :entity_id, "UPDATE", "SCREEN", :changed_by, :note
             )'
        );
        $stmt->execute([
            'entity_id'  => $salesPerformanceId,
            'changed_by' => $changedBy,
            'note'       => $note,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @param array<int, array<string, string|null>> $details
     */
    private function insertAuditEventDetails(int $auditEventId, array $details): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO t_audit_event_detail (
                audit_event_id, field_key, field_label, value_type,
                before_value_text, after_value_text
             ) VALUES (
                :audit_event_id, :field_key, :field_label, :value_type,
                :before_value_text, :after_value_text
             )'
        );

        foreach ($details as $detail) {
            $stmt->execute([
                'audit_event_id'    => $auditEventId,
                'field_key'         => (string) ($detail['field_key'] ?? ''),
                'field_label'       => (string) ($detail['field_label'] ?? ''),
                'value_type'        => (string) ($detail['value_type'] ?? 'STRING'),
                'before_value_text' => $detail['before_value_text'] ?? null,
                'after_value_text'  => $detail['after_value_text'] ?? null,
            ]);
        }
    }

    /**
     * @param array<int, int> $eventIds
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function findAuditEventDetails(array $eventIds): array
    {
        if ($eventIds === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($eventIds), '?'));
        $stmt = $this->pdo->prepare(
            'SELECT audit_event_id, field_key, field_label, value_type,
                    before_value_text, after_value_text
             FROM t_audit_event_detail
             WHERE audit_event_id IN (' . $placeholders . ')
             ORDER BY id ASC'
        );

        foreach ($eventIds as $index => $eventId) {
            $stmt->bindValue($index + 1, $eventId, PDO::PARAM_INT);
        }
        $stmt->execute();

        $rows = $stmt->fetchAll();
        if (!is_array($rows)) {
            return [];
        }

        $grouped = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $auditEventId = (int) ($row['audit_event_id'] ?? 0);
            if ($auditEventId > 0) {
                $grouped[$auditEventId][] = $row;
            }
        }

        return $grouped;
    }

    public function softDelete(int $id, int $userId): int
    {
        $stmt = $this->pdo->prepare(
            'UPDATE t_sales_performance
             SET is_deleted = 1,
                 updated_by = :updated_by
             WHERE id = :id
               AND is_deleted = 0'
        );
        $stmt->execute([
            'id' => $id,
            'updated_by' => $userId,
        ]);

        return $stmt->rowCount();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findContractByPolicyNo(string $policyNo): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, customer_id, policy_no
             FROM t_contract
             WHERE policy_no = :policy_no
               AND is_deleted = 0
             LIMIT 1'
        );
        $stmt->execute(['policy_no' => $policyNo]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findRenewalCaseByContractAndMaturityDate(int $contractId, ?string $maturityDate): ?array
    {
        if ($maturityDate === null || $maturityDate === '') {
            return null;
        }

        $stmt = $this->pdo->prepare(
            'SELECT id, contract_id, maturity_date
             FROM t_renewal_case
             WHERE contract_id = :contract_id
               AND maturity_date = :maturity_date
               AND is_deleted = 0
             ORDER BY id DESC
             LIMIT 1'
        );
        $stmt->execute([
            'contract_id' => $contractId,
            'maturity_date' => $maturityDate,
        ]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findActiveByReceiptNo(string $receiptNo): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id,
                    customer_id,
                    contract_id,
                    renewal_case_id,
                    performance_date,
                    performance_type,
                    insurance_category,
                    product_type,
                    premium_amount,
                    receipt_no,
                    settlement_month,
                    staff_id,
                    remark
             FROM t_sales_performance
             WHERE receipt_no = :receipt_no
               AND is_deleted = 0
             ORDER BY id DESC
             LIMIT 1'
        );
        $stmt->execute(['receipt_no' => $receiptNo]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    public function createImportBatch(string $fileName, string $sourceEncoding, int $executedBy): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO t_sjnet_import_batch (
                file_name,
                source_encoding,
                import_status,
                executed_by
             ) VALUES (
                :file_name,
                :source_encoding,
                :import_status,
                :executed_by
             )'
        );
        $stmt->execute([
            'file_name' => $fileName,
            'source_encoding' => $sourceEncoding,
            'import_status' => 'running',
            'executed_by' => $executedBy,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @param array<string, mixed> $summary
     */
    public function finalizeImportBatch(int $batchId, array $summary, string $status): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE t_sjnet_import_batch
             SET import_status = :import_status,
                 total_row_count = :total_row_count,
                 valid_row_count = :valid_row_count,
                 duplicate_skip_count = :duplicate_skip_count,
                 insert_count = :insert_count,
                 update_count = :update_count,
                 error_count = :error_count,
                 finished_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute([
            'id' => $batchId,
            'import_status' => $status,
            'total_row_count' => (int) ($summary['total_row_count'] ?? 0),
            'valid_row_count' => (int) ($summary['valid_row_count'] ?? 0),
            'duplicate_skip_count' => (int) ($summary['duplicate_skip_count'] ?? 0),
            'insert_count' => (int) ($summary['insert_count'] ?? 0),
            'update_count' => (int) ($summary['update_count'] ?? 0),
            'error_count' => (int) ($summary['error_count'] ?? 0),
        ]);
    }

    /**
     * @param array<string, mixed> $row
     */
    public function createImportRow(int $batchId, array $row): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO t_sjnet_import_row (
                sjnet_import_batch_id,
                row_no,
                raw_payload_json,
                policy_no,
                customer_name,
                maturity_date,
                matched_contract_id,
                matched_renewal_case_id,
                row_status,
                error_message
             ) VALUES (
                :batch_id,
                :row_no,
                :raw_payload_json,
                :policy_no,
                :customer_name,
                :maturity_date,
                :matched_contract_id,
                :matched_renewal_case_id,
                :row_status,
                :error_message
             )'
        );
        $stmt->execute([
            'batch_id' => $batchId,
            'row_no' => (int) ($row['row_no'] ?? 0),
            'raw_payload_json' => (string) ($row['raw_payload_json'] ?? '{}'),
            'policy_no' => $row['policy_no'] ?? null,
            'customer_name' => $row['customer_name'] ?? null,
            'maturity_date' => $row['maturity_date'] ?? null,
            'matched_contract_id' => $row['matched_contract_id'] ?? null,
            'matched_renewal_case_id' => $row['matched_renewal_case_id'] ?? null,
            'row_status' => (string) ($row['row_status'] ?? 'error'),
            'error_message' => $row['error_message'] ?? null,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findImportBatchById(int $batchId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT *
             FROM t_sjnet_import_batch
             WHERE id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $batchId]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findImportRowsByBatchId(int $batchId, int $limit = 200): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id,
                    row_no,
                    policy_no,
                    customer_name,
                    maturity_date,
                    matched_contract_id,
                    matched_renewal_case_id,
                    row_status,
                    error_message
             FROM t_sjnet_import_row
             WHERE sjnet_import_batch_id = :batch_id
             ORDER BY row_no ASC
             LIMIT :limit'
        );
        $stmt->bindValue(':batch_id', $batchId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        return is_array($rows) ? $rows : [];
    }
}