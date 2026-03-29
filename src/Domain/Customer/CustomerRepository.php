<?php
declare(strict_types=1);

namespace App\Domain\Customer;

use PDO;

final class CustomerRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * @param array<string, string> $criteria
     * @return array<int, array<string, mixed>>
     */
    public function search(array $criteria, int $limit = 200): array
    {
        $sql =
            'SELECT mc.id,
                    mc.customer_name,
                    mc.phone,
                    mc.email,
                    mc.address1,
                    mc.address2,
                    mc.status,
                    mc.updated_at,
                    COALESCE(cnt.contract_count, 0) AS contract_count
             FROM m_customer mc
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
            $sql .= ' AND mc.customer_name LIKE :customer_name';
            $params['customer_name'] = '%' . $customerName . '%';
        }

        $phone = trim((string) ($criteria['phone'] ?? ''));
        if ($phone !== '') {
            $sql .= ' AND mc.phone LIKE :phone';
            $params['phone'] = '%' . $phone . '%';
        }

        $email = trim((string) ($criteria['email'] ?? ''));
        if ($email !== '') {
            $sql .= ' AND mc.email LIKE :email';
            $params['email'] = '%' . $email . '%';
        }

        $status = trim((string) ($criteria['status'] ?? ''));
        if ($status !== '') {
            $sql .= ' AND mc.status = :status';
            $params['status'] = $status;
        }

        $sql .= ' ORDER BY mc.updated_at DESC, mc.id DESC LIMIT :limit';

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
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
     * @return array<int, array<string, mixed>>
     */
    public function findActivities(int $customerId, int $limit = 30): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT activity_at, activity_type, subject, detail, outcome
             FROM t_activity
             WHERE customer_id = :customer_id
               AND is_deleted = 0
             ORDER BY activity_at DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':customer_id', $customerId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        return is_array($rows) ? $rows : [];
    }
}
