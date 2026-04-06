<?php
declare(strict_types=1);

namespace App\Domain\Tenant;

use PDO;

final class StaffRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /** @return array<int, array<string, mixed>> */
    public function findAll(): array
    {
        $rows = $this->pdo->query(
            'SELECT id, staff_name, is_sales, is_office, user_id, sjnet_code, is_active, sort_order, created_at, updated_at
             FROM m_staff
             ORDER BY sort_order ASC, id ASC'
        )->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    /** @return array<int, array<string, mixed>>  sales プルダウン用（is_sales=1 かつ is_active=1） */
    public function findForSales(): array
    {
        $rows = $this->pdo->query(
            'SELECT id, staff_name FROM m_staff WHERE is_sales = 1 AND is_active = 1 ORDER BY sort_order ASC, id ASC'
        )->fetchAll(PDO::FETCH_ASSOC);
        return is_array($rows) ? $rows : [];
    }

    /** @return array<int, array<string, mixed>>  office プルダウン用（is_office=1 かつ is_active=1） */
    public function findForOffice(): array
    {
        $rows = $this->pdo->query(
            'SELECT id, staff_name FROM m_staff WHERE is_office = 1 AND is_active = 1 ORDER BY sort_order ASC, id ASC'
        )->fetchAll(PDO::FETCH_ASSOC);
        return is_array($rows) ? $rows : [];
    }

    /** @return array<int, array<string, mixed>>  全プルダウン用（is_active=1） */
    public function findActive(): array
    {
        $rows = $this->pdo->query(
            'SELECT id, staff_name FROM m_staff WHERE is_active = 1 ORDER BY sort_order ASC, id ASC'
        )->fetchAll(PDO::FETCH_ASSOC);
        return is_array($rows) ? $rows : [];
    }

    /** ログインユーザー（common.users.id）に紐づく m_staff.id を返す。紐づきがなければ null */
    public function findIdByUserId(int $userId): ?int
    {
        $stmt = $this->pdo->prepare(
            'SELECT id FROM m_staff WHERE user_id = :user_id AND is_active = 1 LIMIT 1'
        );
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) && isset($row['id']) ? (int) $row['id'] : null;
    }

    /** @return array<string, mixed>|null */
    public function findBySjnetCode(string $sjnetCode): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, staff_name, user_id, is_active FROM m_staff WHERE sjnet_code = :code LIMIT 1'
        );
        $stmt->bindValue(':code', $sjnetCode);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM m_staff WHERE id = :id LIMIT 1');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function create(
        string $staffName,
        int $isSales,
        int $isOffice,
        ?int $userId,
        ?string $sjnetCode,
        int $isActive,
        int $sortOrder,
        int $actorUserId
    ): int {
        $stmt = $this->pdo->prepare(
            'INSERT INTO m_staff
               (staff_name, is_sales, is_office, user_id, sjnet_code, is_active, sort_order, created_by, updated_by)
             VALUES
               (:staff_name, :is_sales, :is_office, :user_id, :sjnet_code, :is_active, :sort_order, :created_by, :updated_by)'
        );
        $stmt->bindValue(':staff_name', $staffName);
        $stmt->bindValue(':is_sales', $isSales, PDO::PARAM_INT);
        $stmt->bindValue(':is_office', $isOffice, PDO::PARAM_INT);
        $stmt->bindValue(':user_id', $userId, $userId !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':sjnet_code', $sjnetCode !== '' ? $sjnetCode : null, $sjnetCode !== null && $sjnetCode !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':is_active', $isActive, PDO::PARAM_INT);
        $stmt->bindValue(':sort_order', $sortOrder, PDO::PARAM_INT);
        $stmt->bindValue(':created_by', $actorUserId, PDO::PARAM_INT);
        $stmt->bindValue(':updated_by', $actorUserId, PDO::PARAM_INT);
        $stmt->execute();
        return (int) $this->pdo->lastInsertId();
    }

    public function update(
        int $id,
        string $staffName,
        int $isSales,
        int $isOffice,
        ?int $userId,
        ?string $sjnetCode,
        int $isActive,
        int $sortOrder,
        int $actorUserId
    ): int {
        $stmt = $this->pdo->prepare(
            'UPDATE m_staff
             SET staff_name  = :staff_name,
                 is_sales    = :is_sales,
                 is_office   = :is_office,
                 user_id     = :user_id,
                 sjnet_code  = :sjnet_code,
                 is_active   = :is_active,
                 sort_order  = :sort_order,
                 updated_by  = :updated_by
             WHERE id = :id'
        );
        $stmt->bindValue(':staff_name', $staffName);
        $stmt->bindValue(':is_sales', $isSales, PDO::PARAM_INT);
        $stmt->bindValue(':is_office', $isOffice, PDO::PARAM_INT);
        $stmt->bindValue(':user_id', $userId, $userId !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':sjnet_code', $sjnetCode !== '' ? $sjnetCode : null, $sjnetCode !== null && $sjnetCode !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':is_active', $isActive, PDO::PARAM_INT);
        $stmt->bindValue(':sort_order', $sortOrder, PDO::PARAM_INT);
        $stmt->bindValue(':updated_by', $actorUserId, PDO::PARAM_INT);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount();
    }

    public function delete(int $id): int
    {
        $stmt = $this->pdo->prepare('DELETE FROM m_staff WHERE id = :id');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount();
    }
}
