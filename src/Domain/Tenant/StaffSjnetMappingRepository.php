<?php
declare(strict_types=1);

namespace App\Domain\Tenant;

use PDO;

final class StaffSjnetMappingRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * 全件取得（id ASC）
     *
     * @return array<int, array<string, mixed>>
     */
    public function findAll(): array
    {
        $rows = $this->pdo->query(
            'SELECT id, sjnet_code, staff_name, user_id, is_active, note, created_at, updated_at
             FROM m_staff_sjnet_mapping
             ORDER BY id ASC'
        )->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    /**
     * 代理店コードで1件検索（is_active は問わず返す）
     *
     * @return array<string, mixed>|null
     */
    public function findByAgencyCode(string $agencyCode): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, sjnet_code, staff_name, user_id, is_active
             FROM m_staff_sjnet_mapping
             WHERE sjnet_code = :code
             LIMIT 1'
        );
        $stmt->bindValue(':code', $agencyCode);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    public function create(string $sjnetCode, string $staffName, int $userId, int $isActive, ?string $note, int $actorUserId): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO m_staff_sjnet_mapping
               (sjnet_code, staff_name, user_id, is_active, note, created_by, updated_by)
             VALUES
               (:sjnet_code, :staff_name, :user_id, :is_active, :note, :created_by, :updated_by)'
        );
        $stmt->bindValue(':sjnet_code', $sjnetCode);
        $stmt->bindValue(':staff_name', $staffName);
        $stmt->bindValue(':user_id', $userId > 0 ? $userId : null, $userId > 0 ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':is_active', $isActive, PDO::PARAM_INT);
        $stmt->bindValue(':note', $note !== '' ? $note : null, $note !== null && $note !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':created_by', $actorUserId, PDO::PARAM_INT);
        $stmt->bindValue(':updated_by', $actorUserId, PDO::PARAM_INT);
        $stmt->execute();

        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, string $sjnetCode, string $staffName, int $userId, int $isActive, ?string $note, int $actorUserId): int
    {
        $stmt = $this->pdo->prepare(
            'UPDATE m_staff_sjnet_mapping
             SET sjnet_code = :sjnet_code,
                 staff_name = :staff_name,
                 user_id    = :user_id,
                 is_active  = :is_active,
                 note       = :note,
                 updated_by = :updated_by
             WHERE id = :id'
        );
        $stmt->bindValue(':sjnet_code', $sjnetCode);
        $stmt->bindValue(':staff_name', $staffName);
        $stmt->bindValue(':user_id', $userId > 0 ? $userId : null, $userId > 0 ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':is_active', $isActive, PDO::PARAM_INT);
        $stmt->bindValue(':note', $note !== '' ? $note : null, $note !== null && $note !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':updated_by', $actorUserId, PDO::PARAM_INT);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount();
    }

    public function delete(int $id): int
    {
        $stmt = $this->pdo->prepare('DELETE FROM m_staff_sjnet_mapping WHERE id = :id');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount();
    }
}
