<?php
declare(strict_types=1);

namespace App\Domain\Customer;

use PDO;

final class CustomerRepository
{
    /**
     * @var array<int, string>
     */
    public const SORTABLE_FIELDS = ['customer_name', 'contract_count', 'updated_at'];

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
                    mc.customer_name_kana,
                    mc.phone,
                    mc.email,
                    mc.address1,
                    mc.address2,
                    mc.status,
                    mc.updated_at,
                    COALESCE(cnt.contract_count, 0) AS contract_count'
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
                SELECT customer_id, COUNT(*) AS contract_count
                FROM t_contract
                WHERE is_deleted = 0
                GROUP BY customer_id
             ) cnt ON cnt.customer_id = mc.id
             WHERE mc.is_deleted = 0';

        $params = [];

        $customerName = trim((string) ($criteria['customer_name'] ?? ''));
        if ($customerName !== '') {
            $sql .= ' AND (mc.customer_name LIKE :customer_name OR mc.customer_name_kana LIKE :customer_name_kana)';
            $params['customer_name'] = '%' . $customerName . '%';
            $params['customer_name_kana'] = '%' . $customerName . '%';
        }

        return ['sql' => $sql, 'params' => $params];
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
            'customer_name'  => 'mc.customer_name ' . $directionSql . ', mc.id ASC',
            'contract_count' => 'COALESCE(cnt.contract_count, 0) ' . $directionSql . ', mc.id ASC',
            'updated_at'     => 'mc.updated_at ' . $directionSql . ', mc.id DESC',
            default          => 'mc.updated_at DESC, mc.id DESC',
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
                    customer_name_kana,
                    phone,
                    email,
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
     * @return array<int, array<string, mixed>>
     */
    public function findContacts(int $customerId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT contact_name, department, position_name, phone, email, is_primary, sort_order
             FROM m_customer_contact
             WHERE customer_id = :customer_id
               AND is_deleted = 0
             ORDER BY is_primary DESC, sort_order ASC, id ASC'
        );
        $stmt->execute(['customer_id' => $customerId]);
        $rows = $stmt->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findContracts(int $customerId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT c.id,
                    c.policy_no,
                    c.insurer_name,
                    c.product_type,
                    c.policy_start_date,
                    c.policy_end_date,
                    c.status,
                    rc.id AS renewal_case_id,
                    rc.case_status,
                    rc.maturity_date
             FROM t_contract c
             LEFT JOIN t_renewal_case rc
               ON rc.id = (
                    SELECT rc2.id
                    FROM t_renewal_case rc2
                    WHERE rc2.contract_id = c.id
                      AND rc2.is_deleted = 0
                    ORDER BY rc2.maturity_date DESC, rc2.id DESC
                    LIMIT 1
               )
             WHERE c.customer_id = :customer_id
               AND c.is_deleted = 0
             ORDER BY c.policy_end_date DESC, c.id DESC'
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
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO m_customer
                    (customer_type, customer_name, customer_name_kana, phone, email,
                     postal_code, address1, address2, status, note,
                     created_by, updated_by)
                 VALUES
                    (:customer_type, :customer_name, :customer_name_kana, :phone, :email,
                     :postal_code, :address1, :address2, :status, :note,
                     :created_by, :updated_by)'
            );
            $stmt->execute([
                'customer_type'      => (string) ($input['customer_type'] ?? 'individual'),
                'customer_name'      => (string) ($input['customer_name'] ?? ''),
                'customer_name_kana' => $input['customer_name_kana'] ?? null,
                'phone'              => $input['phone'] ?? null,
                'email'              => $input['email'] ?? null,
                'postal_code'        => $input['postal_code'] ?? null,
                'address1'           => $input['address1'] ?? null,
                'address2'           => $input['address2'] ?? null,
                'status'             => 'active',
                'note'               => $input['note'] ?? null,
                'created_by'         => $userId,
                'updated_by'         => $userId,
            ]);

            $customerId = (int) $this->pdo->lastInsertId();

            // 初期代表連絡先を同一トランザクション内で作成
            $contactName = (string) ($input['customer_name'] ?? '');
            $stmt2 = $this->pdo->prepare(
                'INSERT INTO m_customer_contact
                    (customer_id, contact_name, phone, email, is_primary, sort_order,
                     created_by, updated_by)
                 VALUES
                    (:customer_id, :contact_name, :phone, :email, 1, 1,
                     :created_by, :updated_by)'
            );
            $stmt2->execute([
                'customer_id'  => $customerId,
                'contact_name' => $contactName,
                'phone'        => $input['phone'] ?? null,
                'email'        => $input['email'] ?? null,
                'created_by'   => $userId,
                'updated_by'   => $userId,
            ]);

            $this->pdo->commit();
            return $customerId;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * @param array<string, mixed> $input
     */
    public function update(int $customerId, array $input, int $userId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE m_customer
             SET note       = :note,
                 updated_by = :updated_by
             WHERE id = :id
               AND is_deleted = 0'
        );
        $stmt->execute([
            'note'       => $input['note'] ?? null,
            'updated_by' => $userId,
            'id'         => $customerId,
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findAccidentCases(int $customerId, int $limit = 50): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, accident_no, accepted_date, insurance_category, status, priority, accident_summary
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
            'SELECT id, activity_date, activity_type, subject, content_summary, detail_text, result_type,
                    next_action_date, next_action_note, staff_id
             FROM t_activity
             WHERE customer_id = :customer_id
               AND is_deleted = 0
             ORDER BY activity_date DESC, id DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':customer_id', $customerId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        return is_array($rows) ? $rows : [];
    }
}
