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

    /**
     * 指定ユーザー ID 群に紐づく m_staff を user_id キーで返す。
     * （統合ユーザー管理画面の一覧生成用: is_active は問わない）
     *
     * @param array<int, int> $userIds
     * @return array<int, array<string, mixed>>  key: user_id, value: staff row
     */
    public function findByUserIds(array $userIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $userIds), static fn ($v) => $v > 0)));
        if ($ids === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare(
            'SELECT id, staff_name, is_sales, is_office, user_id, sjnet_code, is_active, sort_order
             FROM m_staff
             WHERE user_id IN (' . $placeholders . ')'
        );
        foreach ($ids as $i => $uid) {
            $stmt->bindValue($i + 1, $uid, PDO::PARAM_INT);
        }
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $map = [];
        foreach ($rows as $row) {
            $uid = (int) ($row['user_id'] ?? 0);
            if ($uid > 0) {
                $map[$uid] = $row;
            }
        }
        return $map;
    }

    /**
     * ユーザー単位で担当者行を UPSERT する。
     * - 既存行があれば sjnet_code / is_sales / is_office のみ更新（他項目は維持）
     * - 無ければ新規作成。staff_name は $fallbackStaffName、is_active=1, sort_order=0 で作成
     */
    public function upsertForUser(
        int $userId,
        ?string $sjnetCode,
        int $isSales,
        int $isOffice,
        string $fallbackStaffName,
        int $actorUserId
    ): int {
        $stmt = $this->pdo->prepare('SELECT id, staff_name, is_active, sort_order FROM m_staff WHERE user_id = :user_id LIMIT 1');
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if (is_array($existing)) {
            $id = (int) $existing['id'];
            $upd = $this->pdo->prepare(
                'UPDATE m_staff
                   SET is_sales   = :is_sales,
                       is_office  = :is_office,
                       sjnet_code = :sjnet_code,
                       updated_by = :updated_by
                 WHERE id = :id'
            );
            $upd->bindValue(':is_sales', $isSales, PDO::PARAM_INT);
            $upd->bindValue(':is_office', $isOffice, PDO::PARAM_INT);
            $code = ($sjnetCode !== null && $sjnetCode !== '') ? $sjnetCode : null;
            $upd->bindValue(':sjnet_code', $code, $code !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $upd->bindValue(':updated_by', $actorUserId, PDO::PARAM_INT);
            $upd->bindValue(':id', $id, PDO::PARAM_INT);
            $upd->execute();
            return $id;
        }

        return $this->create(
            $fallbackStaffName !== '' ? $fallbackStaffName : 'ユーザー#' . $userId,
            $isSales,
            $isOffice,
            $userId,
            ($sjnetCode !== null && $sjnetCode !== '') ? $sjnetCode : null,
            1,
            0,
            $actorUserId
        );
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
