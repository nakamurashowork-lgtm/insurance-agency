<?php
declare(strict_types=1);

namespace App\Domain\Customer;

use PDO;

final class CustomerRepository
{
    /**
     * @var array<int, string>
     */
    public const SORTABLE_FIELDS = ['customer_name', 'updated_at'];

    public function __construct(private PDO $pdo)
    {
    }

    /**
     * @param array<string, string> $criteria
     * @return array<int, array<string, mixed>>
     */
    public function search(array $criteria, int $limit = 200): array
    {
        $result = $this->searchPage($criteria, 1, $limit);
        return $result['rows'];
    }

    /**
     * @param array<string, string> $criteria
     * @return array{rows: array<int, array<string, mixed>>, total: int, page: int}
     */
    public function searchPage(array $criteria, int $page = 1, int $perPage = 10, string $sort = '', string $direction = 'asc'): array
    {
        $total = $this->countSearch($criteria);
        if ($perPage <= 0) {
            $perPage = 10;
        }

        $currentPage = 1;
        if ($total > 0) {
            $totalPages = (int) ceil($total / $perPage);
            $currentPage = max(1, min($page, $totalPages));
        }

        $offset = $total > 0 ? ($currentPage - 1) * $perPage : 0;
        $query = $this->buildSearchQuery($criteria);

        $sql =
            'SELECT mc.id,
                    mc.customer_name,
                    mc.customer_type,
                    mc.birth_date,
                    mc.phone,
                    mc.address1,
                    mc.address2,
                    mc.status,
                    mc.updated_at,
                    COALESCE(rcnt.renewal_case_count, 0) AS renewal_case_count,
                    COALESCE(acnt.accident_case_count, 0) AS accident_case_count,
                    COALESCE(avcnt.activity_count, 0) AS activity_count'
            . $query['sql']
            . ' ORDER BY ' . $this->buildOrderBy($sort, $direction)
            . ' LIMIT :limit OFFSET :offset';

        $stmt = $this->pdo->prepare($sql);
        foreach ($query['params'] as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        return [
            'rows' => is_array($rows) ? $rows : [],
            'total' => $total,
            'page' => $currentPage,
        ];
    }

    /**
     * @param array<string, string> $criteria
     * @return array{sql: string, params: array<string, string|int>}
     */
    private function buildSearchQuery(array $criteria): array
    {
        $sql =
            ' FROM m_customer mc
             LEFT JOIN (
                SELECT t_contract.customer_id, COUNT(*) AS renewal_case_count
                FROM t_renewal_case
                JOIN t_contract ON t_contract.id = t_renewal_case.contract_id AND t_contract.is_deleted = 0
                WHERE t_renewal_case.is_deleted = 0
                GROUP BY t_contract.customer_id
             ) rcnt ON rcnt.customer_id = mc.id
             LEFT JOIN (
                SELECT customer_id, COUNT(*) AS accident_case_count
                FROM t_accident_case
                WHERE is_deleted = 0
                GROUP BY customer_id
             ) acnt ON acnt.customer_id = mc.id
             LEFT JOIN (
                SELECT customer_id, COUNT(*) AS activity_count
                FROM t_activity
                WHERE is_deleted = 0 AND customer_id IS NOT NULL
                GROUP BY customer_id
             ) avcnt ON avcnt.customer_id = mc.id
             WHERE mc.is_deleted = 0';

        $params = [];

        $customerName = trim((string) ($criteria['customer_name'] ?? ''));
        if ($customerName !== '') {
            $sql .= ' AND mc.customer_name LIKE :customer_name';
            $params['customer_name'] = '%' . $customerName . '%';
        }

        $customerType = trim((string) ($criteria['customer_type'] ?? ''));
        if (in_array($customerType, ['individual', 'corporate'], true)) {
            $sql .= ' AND mc.customer_type = :customer_type';
            $params['customer_type'] = $customerType;
        }

        // クイックフィルタ
        $quickFilter = trim((string) ($criteria['quick_filter'] ?? ''));
        if ($quickFilter === 'individual') {
            $sql .= ' AND mc.customer_type = :qf_type_ind';
            $params['qf_type_ind'] = 'individual';
        } elseif ($quickFilter === 'corporate') {
            $sql .= ' AND mc.customer_type = :qf_type_corp';
            $params['qf_type_corp'] = 'corporate';
        }

        return ['sql' => $sql, 'params' => $params];
    }

    /**
     * クイックフィルタ別の件数を返す。
     *
     * @param array<string, string> $criteria quick_filter 以外の現行検索条件
     * @return array<string, int>
     */
    public function countByQuickFilters(array $criteria): array
    {
        $out = [];
        foreach (['all', 'individual', 'corporate'] as $key) {
            $c = $criteria;
            $c['quick_filter'] = $key === 'all' ? '' : $key;
            $out[$key] = $this->countSearch($c);
        }
        return $out;
    }

    /**
     * @param array<string, string> $criteria
     */
    private function countSearch(array $criteria): int
    {
        $query = $this->buildSearchQuery($criteria);
        $stmt = $this->pdo->prepare('SELECT COUNT(*)' . $query['sql']);
        foreach ($query['params'] as $key => $value) {
            $stmt->bindValue(':' . $key, (string) $value);
        }
        $stmt->execute();

        $count = $stmt->fetchColumn();
        return is_numeric($count) ? (int) $count : 0;
    }

    private function buildOrderBy(string $sort, string $direction): string
    {
        $directionSql = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';

        return match ($sort) {
            'customer_name' => 'mc.customer_name ' . $directionSql . ', mc.id ASC',
            'updated_at'    => 'mc.updated_at ' . $directionSql . ', mc.id DESC',
            default         => 'mc.updated_at DESC, mc.id DESC',
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findDetailById(int $customerId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id,
                    customer_type,
                    customer_name,
                    birth_date,
                    phone,
                    postal_code,
                    address1,
                    address2,
                    status,
                    note,
                    updated_at
             FROM m_customer
             WHERE id = :customer_id
               AND is_deleted = 0
             LIMIT 1'
        );
        $stmt->execute(['customer_id' => $customerId]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    /**
     * 顧客名 + 生年月日 の組み合わせが他の顧客と衝突するか判定する。
     * 生年月日が NULL の場合は customer_name のみで一致判定する
     * （SJNET CSV STEP2-B と同等の挙動）。
     *
     * @param int      $excludeId  対象自身のID（新規登録時は 0 を渡す）
     */
    public function findDuplicateByNameAndBirth(int $excludeId, string $customerName, ?string $birthDate): bool
    {
        $name = trim($customerName);
        if ($name === '') {
            return false;
        }

        if ($birthDate !== null && $birthDate !== '') {
            $stmt = $this->pdo->prepare(
                'SELECT 1 FROM m_customer
                 WHERE customer_name = :name
                   AND birth_date = :birth_date
                   AND is_deleted = 0
                   AND id <> :exclude_id
                 LIMIT 1'
            );
            $stmt->bindValue(':name', $name);
            $stmt->bindValue(':birth_date', $birthDate);
        } else {
            $stmt = $this->pdo->prepare(
                'SELECT 1 FROM m_customer
                 WHERE customer_name = :name
                   AND birth_date IS NULL
                   AND is_deleted = 0
                   AND id <> :exclude_id
                 LIMIT 1'
            );
            $stmt->bindValue(':name', $name);
        }
        $stmt->bindValue(':exclude_id', $excludeId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchColumn() !== false;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findContracts(int $customerId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT c.id,
                    c.policy_no,
                    c.product_type,
                    c.policy_start_date,
                    c.policy_end_date,
                    c.status,
                    rc.id AS renewal_case_id,
                    rc.case_status,
                    rc.maturity_date
             FROM t_contract c
             LEFT JOIN t_renewal_case rc
                    ON rc.contract_id = c.id
                   AND rc.is_deleted = 0
             WHERE c.customer_id = :customer_id
               AND c.is_deleted = 0
             ORDER BY COALESCE(rc.maturity_date, c.policy_end_date) DESC, c.id DESC, rc.id DESC'
        );
        $stmt->execute(['customer_id' => $customerId]);
        $rows = $stmt->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param array<string, mixed> $input
     */
    public function create(array $input, int $userId): int
    {
        $birthDate = $this->normalizeNullable($input['birth_date'] ?? null);

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO m_customer
                    (customer_type, customer_name, birth_date, phone,
                     postal_code, address1, address2, status, note,
                     created_by, updated_by)
                 VALUES
                    (:customer_type, :customer_name, :birth_date, :phone,
                     :postal_code, :address1, :address2, :status, :note,
                     :created_by, :updated_by)'
            );
            $stmt->bindValue(':customer_type', (string) ($input['customer_type'] ?? 'individual'));
            $stmt->bindValue(':customer_name', (string) ($input['customer_name'] ?? ''));
            $stmt->bindValue(':birth_date', $birthDate, $birthDate === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':phone', $this->normalizeNullable($input['phone'] ?? null), $this->normalizeNullable($input['phone'] ?? null) === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':postal_code', $this->normalizeNullable($input['postal_code'] ?? null), $this->normalizeNullable($input['postal_code'] ?? null) === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':address1', $this->normalizeNullable($input['address1'] ?? null), $this->normalizeNullable($input['address1'] ?? null) === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':address2', $this->normalizeNullable($input['address2'] ?? null), $this->normalizeNullable($input['address2'] ?? null) === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':status', 'active');
            $stmt->bindValue(':note', $this->normalizeNullable($input['note'] ?? null), $this->normalizeNullable($input['note'] ?? null) === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':created_by', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':updated_by', $userId, PDO::PARAM_INT);
            $stmt->execute();

            $newId = (int) $this->pdo->lastInsertId();

            $after = [
                'customer_type' => $this->normalizeAuditValue($input['customer_type'] ?? null),
                'customer_name' => $this->normalizeAuditValue($input['customer_name'] ?? null),
                'birth_date'    => $birthDate,
                'phone'         => $this->normalizeAuditValue($input['phone'] ?? null),
                'postal_code'   => $this->normalizeAuditValue($input['postal_code'] ?? null),
                'address1'      => $this->normalizeAuditValue($input['address1'] ?? null),
                'address2'      => $this->normalizeAuditValue($input['address2'] ?? null),
                'note'          => $this->normalizeAuditValue($input['note'] ?? null),
            ];
            $details = $this->buildAuditDetails(self::emptyAuditState(), $after);
            $eventId = $this->insertAuditEvent($newId, $userId, 'INSERT', '顧客を登録');
            if ($details !== []) {
                $this->insertAuditEventDetails($eventId, $details);
            }

            $this->pdo->commit();
            return $newId;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * @param array<string, mixed> $input
     */
    public function update(int $customerId, array $input, int $userId): void
    {
        $before = $this->findCustomerForAudit($customerId);
        if ($before === null) {
            throw new \RuntimeException('顧客が見つかりません: customer_id=' . $customerId);
        }

        $birthDate = $this->normalizeNullable($input['birth_date'] ?? null);

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'UPDATE m_customer
                 SET customer_type      = :customer_type,
                     customer_name      = :customer_name,
                     birth_date         = :birth_date,
                     phone              = :phone,
                     postal_code        = :postal_code,
                     address1           = :address1,
                     address2           = :address2,
                     note               = :note,
                     updated_by         = :updated_by
                 WHERE id = :id
                   AND is_deleted = 0'
            );
            $stmt->bindValue(':customer_type', (string) ($input['customer_type'] ?? ''));
            $stmt->bindValue(':customer_name', (string) ($input['customer_name'] ?? ''));
            $stmt->bindValue(':birth_date', $birthDate, $birthDate === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':phone', $this->normalizeNullable($input['phone'] ?? null), $this->normalizeNullable($input['phone'] ?? null) === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':postal_code', $this->normalizeNullable($input['postal_code'] ?? null), $this->normalizeNullable($input['postal_code'] ?? null) === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':address1', $this->normalizeNullable($input['address1'] ?? null), $this->normalizeNullable($input['address1'] ?? null) === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':address2', $this->normalizeNullable($input['address2'] ?? null), $this->normalizeNullable($input['address2'] ?? null) === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':note', $this->normalizeNullable($input['note'] ?? null), $this->normalizeNullable($input['note'] ?? null) === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':updated_by', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':id', $customerId, PDO::PARAM_INT);
            $stmt->execute();

            $after = $this->findCustomerForAudit($customerId);
            if ($after === null) {
                $this->pdo->rollBack();
                throw new \RuntimeException('顧客の再読み込みに失敗しました: customer_id=' . $customerId);
            }

            $details = $this->buildAuditDetails($before, $after);
            if ($details !== []) {
                $eventId = $this->insertAuditEvent($customerId, $userId, 'UPDATE', '基本情報を更新');
                $this->insertAuditEventDetails($eventId, $details);
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * 顧客の基本情報変更履歴を新しい順に返す。
     *
     * @return array<int, array<string, mixed>>
     */
    public function findAuditEvents(int $customerId, int $limit = 30): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, changed_at, changed_by, action_type, change_source, note
             FROM t_audit_event
             WHERE entity_type = "customer"
               AND entity_id = :customer_id
             ORDER BY changed_at DESC, id DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':customer_id', $customerId, PDO::PARAM_INT);
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
            $eid = (int) ($row['audit_event_id'] ?? 0);
            if ($eid > 0) {
                $grouped[$eid][] = $row;
            }
        }
        return $grouped;
    }

    /**
     * @return array<string, string|null>|null
     */
    private function findCustomerForAudit(int $customerId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT customer_type, customer_name, birth_date, phone,
                    postal_code, address1, address2, note
             FROM m_customer
             WHERE id = :customer_id
               AND is_deleted = 0
             LIMIT 1'
        );
        $stmt->execute(['customer_id' => $customerId]);
        $row = $stmt->fetch();
        if (!is_array($row)) {
            return null;
        }

        return [
            'customer_type' => $this->normalizeAuditValue($row['customer_type'] ?? null),
            'customer_name' => $this->normalizeAuditValue($row['customer_name'] ?? null),
            'birth_date'    => $this->normalizeAuditValue($row['birth_date'] ?? null),
            'phone'         => $this->normalizeAuditValue($row['phone'] ?? null),
            'postal_code'   => $this->normalizeAuditValue($row['postal_code'] ?? null),
            'address1'      => $this->normalizeAuditValue($row['address1'] ?? null),
            'address2'      => $this->normalizeAuditValue($row['address2'] ?? null),
            'note'          => $this->normalizeAuditValue($row['note'] ?? null),
        ];
    }

    /**
     * @return array<string, null>
     */
    private static function emptyAuditState(): array
    {
        return [
            'customer_type' => null,
            'customer_name' => null,
            'birth_date'    => null,
            'phone'         => null,
            'postal_code'   => null,
            'address1'      => null,
            'address2'      => null,
            'note'          => null,
        ];
    }

    private function normalizeAuditValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $normalized = trim((string) $value);
        return $normalized === '' ? null : $normalized;
    }

    private function normalizeNullable(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $text = trim((string) $value);
        return $text === '' ? null : $text;
    }

    /**
     * @param array<string, string|null> $before
     * @param array<string, string|null> $after
     * @return array<int, array<string, string|null>>
     */
    private function buildAuditDetails(array $before, array $after): array
    {
        $fields = [
            'customer_name' => ['label' => '顧客名',     'value_type' => 'STRING'],
            'birth_date'    => ['label' => '生年月日',   'value_type' => 'DATE'],
            'customer_type' => ['label' => '顧客種別',   'value_type' => 'STRING'],
            'phone'         => ['label' => '電話番号',   'value_type' => 'STRING'],
            'postal_code'   => ['label' => '郵便番号',   'value_type' => 'STRING'],
            'address1'      => ['label' => '住所1',      'value_type' => 'STRING'],
            'address2'      => ['label' => '住所2',      'value_type' => 'STRING'],
            'note'          => ['label' => '備考',       'value_type' => 'STRING'],
        ];

        $details = [];
        foreach ($fields as $fieldKey => $meta) {
            $beforeValue = $before[$fieldKey] ?? null;
            $afterValue  = $after[$fieldKey] ?? null;
            if ($beforeValue === $afterValue) {
                continue;
            }
            $details[] = [
                'field_key'        => $fieldKey,
                'field_label'      => (string) $meta['label'],
                'value_type'       => (string) $meta['value_type'],
                'before_value_text' => $beforeValue,
                'after_value_text'  => $afterValue,
            ];
        }
        return $details;
    }

    private function insertAuditEvent(int $customerId, int $userId, string $actionType, string $note): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO t_audit_event
                (entity_type, entity_id, action_type, change_source, changed_by, note)
             VALUES
                ("customer", :entity_id, :action_type, "SCREEN", :changed_by, :note)'
        );
        $stmt->bindValue(':entity_id', $customerId, PDO::PARAM_INT);
        $stmt->bindValue(':action_type', $actionType);
        $stmt->bindValue(':changed_by', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':note', $note);
        $stmt->execute();
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @param array<int, array<string, string|null>> $details
     */
    private function insertAuditEventDetails(int $auditEventId, array $details): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO t_audit_event_detail
                (audit_event_id, field_key, field_label, value_type, before_value_text, after_value_text)
             VALUES
                (:audit_event_id, :field_key, :field_label, :value_type, :before_value_text, :after_value_text)'
        );
        foreach ($details as $detail) {
            $stmt->bindValue(':audit_event_id', $auditEventId, PDO::PARAM_INT);
            $stmt->bindValue(':field_key', (string) ($detail['field_key'] ?? ''));
            $stmt->bindValue(':field_label', (string) ($detail['field_label'] ?? ''));
            $stmt->bindValue(':value_type', (string) ($detail['value_type'] ?? 'STRING'));
            $stmt->bindValue(':before_value_text', $detail['before_value_text'] ?? null, $detail['before_value_text'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->bindValue(':after_value_text', $detail['after_value_text'] ?? null, $detail['after_value_text'] === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $stmt->execute();
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findAccidentCases(int $customerId, int $limit = 50): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, accepted_date, accident_date, insurance_category, sc_staff_name, status
             FROM t_accident_case
             WHERE customer_id = :customer_id
               AND is_deleted = 0
             ORDER BY accepted_date DESC, id DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':customer_id', $customerId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findActivities(int $customerId, int $limit = 30): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT a.id, a.activity_date, a.activity_type, a.subject,
                    a.content_summary, a.staff_id,
                    COALESCE(s.staff_name, \'\') AS staff_name
             FROM t_activity a
             LEFT JOIN m_staff s ON s.id = a.staff_id AND s.is_active = 1
             WHERE a.customer_id = :customer_id
               AND a.is_deleted = 0
             ORDER BY a.activity_date DESC, a.id DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':customer_id', $customerId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    /**
     * 顧客紐づけモーダル用の軽量検索
     * 顧客名の部分一致で最大20件を返す
     *
     * @return array<int, array<string, mixed>>
     */
    public function searchForLink(string $name, int $limit = 20): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, customer_name, birth_date, phone
             FROM m_customer
             WHERE customer_name LIKE :name
               AND is_deleted = 0
             ORDER BY customer_name ASC, id ASC
             LIMIT :limit'
        );
        $stmt->bindValue(':name', '%' . $name . '%');
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return is_array($rows) ? $rows : [];
    }
}
