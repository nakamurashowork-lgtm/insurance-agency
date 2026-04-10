<?php
declare(strict_types=1);

namespace App\Domain\Tenant;

use PDO;

final class CaseStatusRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    // ── 取得 ──────────────────────────────────────────────────────────────

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findByType(string $caseType): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, case_type, code, display_name, display_order, is_system, is_active
             FROM m_case_status
             WHERE case_type = :case_type
             ORDER BY is_active DESC, display_order ASC, id ASC'
        );
        $stmt->bindValue(':case_type', $caseType);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return is_array($rows) ? $rows : [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findAll(): array
    {
        $rows = $this->pdo->query(
            'SELECT id, case_type, code, display_name, display_order, is_system, is_active
             FROM m_case_status
             ORDER BY case_type ASC, is_active DESC, display_order ASC, id ASC'
        )->fetchAll(PDO::FETCH_ASSOC);
        return is_array($rows) ? $rows : [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, case_type, code, display_name, display_order, is_system, is_active
             FROM m_case_status WHERE id = :id LIMIT 1'
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    /**
     * code => display_name map for a given case_type
     * @return array<string, string>
     */
    public function codeNameMap(string $caseType): array
    {
        $rows = $this->findByType($caseType);
        $map = [];
        foreach ($rows as $row) {
            $map[(string) ($row['code'] ?? '')] = (string) ($row['display_name'] ?? '');
        }
        return $map;
    }

    /**
     * @return list<string>
     */
    public function validCodes(string $caseType): array
    {
        $rows = $this->findByType($caseType);
        return array_values(array_map(
            fn($r) => (string) ($r['code'] ?? ''),
            array_filter($rows, fn($r) => (int) ($r['is_active'] ?? 1) === 1)
        ));
    }

    // ── 操作可否判定 ──────────────────────────────────────────────────────

    /**
     * @param array<string, mixed> $row
     */
    public function canRename(array $row): bool
    {
        return !$this->isProtectedCode((string) ($row['code'] ?? ''));
    }

    /**
     * @param array<string, mixed> $row
     */
    public function canDisable(array $row): bool
    {
        return !$this->isProtectedCode((string) ($row['code'] ?? ''));
    }

    /**
     * @param array<string, mixed> $row
     */
    public function canDelete(array $row): bool
    {
        return !$this->isProtectedCode((string) ($row['code'] ?? ''));
    }

    // ── 書き込み ──────────────────────────────────────────────────────────

    /**
     * カスタムステータスを新規追加する。
     * コードは case_type 内で custom_001, custom_002... と自動採番する。
     */
    public function create(string $caseType, string $displayName, int $actorUserId): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COALESCE(MAX(display_order), 0) FROM m_case_status WHERE case_type = :ct'
        );
        $stmt->bindValue(':ct', $caseType);
        $stmt->execute();
        $maxOrder = (int) $stmt->fetchColumn();

        $code = $this->generateCustomCode($caseType);

        $stmt = $this->pdo->prepare(
            'INSERT INTO m_case_status (case_type, code, display_name, display_order, is_system, created_by, updated_by)
             VALUES (:case_type, :code, :display_name, :display_order, 0, :created_by, :updated_by)'
        );
        $stmt->bindValue(':case_type', $caseType);
        $stmt->bindValue(':code', $code);
        $stmt->bindValue(':display_name', $displayName);
        $stmt->bindValue(':display_order', $maxOrder + 10, PDO::PARAM_INT);
        $stmt->bindValue(':created_by', $actorUserId, PDO::PARAM_INT);
        $stmt->bindValue(':updated_by', $actorUserId, PDO::PARAM_INT);
        $stmt->execute();
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * 表示名を変更する。保護コード（closed / completed）は拒否。
     */
    public function updateDisplayName(int $id, string $displayName, int $actorUserId): int
    {
        $stmt = $this->pdo->prepare(
            'UPDATE m_case_status
             SET display_name = :display_name, updated_by = :updated_by
             WHERE id = :id AND code NOT IN (\'closed\', \'completed\')'
        );
        $stmt->bindValue(':display_name', $displayName);
        $stmt->bindValue(':updated_by', $actorUserId, PDO::PARAM_INT);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount();
    }

    /**
     * 隣接行と display_order を入れ替える（up: 前の行と交換 / down: 次の行と交換）。
     * @return bool 交換が実行された場合 true
     */
    public function swapDisplayOrder(int $id, string $direction, int $actorUserId): bool
    {
        $self = $this->findById($id);
        if ($self === null) {
            return false;
        }

        $caseType    = (string) ($self['case_type'] ?? '');
        $selfOrder   = (int) ($self['display_order'] ?? 0);

        // 隣接行を取得（direction に応じて前後を選ぶ）
        if ($direction === 'up') {
            $stmt = $this->pdo->prepare(
                'SELECT id, display_order FROM m_case_status
                 WHERE case_type = :ct AND display_order < :order
                 ORDER BY display_order DESC LIMIT 1'
            );
        } else {
            $stmt = $this->pdo->prepare(
                'SELECT id, display_order FROM m_case_status
                 WHERE case_type = :ct AND display_order > :order
                 ORDER BY display_order ASC LIMIT 1'
            );
        }
        $stmt->bindValue(':ct', $caseType);
        $stmt->bindValue(':order', $selfOrder, PDO::PARAM_INT);
        $stmt->execute();
        $neighbor = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($neighbor)) {
            return false;
        }

        $neighborId    = (int) ($neighbor['id'] ?? 0);
        $neighborOrder = (int) ($neighbor['display_order'] ?? 0);

        $upd = $this->pdo->prepare(
            'UPDATE m_case_status SET display_order = :order, updated_by = :actor WHERE id = :id'
        );
        $upd->bindValue(':actor', $actorUserId, PDO::PARAM_INT);

        $upd->bindValue(':order', $neighborOrder, PDO::PARAM_INT);
        $upd->bindValue(':id', $id, PDO::PARAM_INT);
        $upd->execute();

        $upd->bindValue(':order', $selfOrder, PDO::PARAM_INT);
        $upd->bindValue(':id', $neighborId, PDO::PARAM_INT);
        $upd->execute();

        return true;
    }

    /**
     * 有効・無効を切り替える。保護コード（closed / completed）は拒否（rowCount=0 を返す）。
     */
    public function setActive(int $id, int $active): int
    {
        $stmt = $this->pdo->prepare(
            'UPDATE m_case_status SET is_active = :active WHERE id = :id AND code NOT IN (\'closed\', \'completed\')'
        );
        $stmt->bindValue(':active', $active, PDO::PARAM_INT);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount();
    }

    /**
     * カスタムステータス（is_system=0）を物理削除する。
     * 既存案件で使用中の場合は例外をスローする。
     *
     * @throws \DomainException
     */
    public function delete(int $id, int $actorUserId): void
    {
        $row = $this->findById($id);
        if ($row === null) {
            throw new \DomainException('対象が見つかりません。');
        }
        if (!$this->canDelete($row)) {
            throw new \DomainException('このステータスは削除できません。');
        }

        $stmt = $this->pdo->prepare(
            'DELETE FROM m_case_status WHERE id = :id AND code NOT IN (\'closed\', \'completed\')'
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    // ── プライベート ──────────────────────────────────────────────────────

    /**
     * 編集・無効化・削除が禁止されている保護コードか判定する。
     */
    private function isProtectedCode(string $code): bool
    {
        return in_array($code, ['closed', 'completed'], true);
    }

    /**
     * case_type 内のカスタムコード最大番号を返す。
     */
    private function generateCustomCode(string $caseType): string
    {
        $stmt = $this->pdo->prepare(
            "SELECT code FROM m_case_status
             WHERE case_type = :ct AND code REGEXP '^custom_[0-9]+$'
             ORDER BY CAST(SUBSTRING(code, 8) AS UNSIGNED) DESC LIMIT 1"
        );
        $stmt->bindValue(':ct', $caseType);
        $stmt->execute();
        $lastCode = (string) ($stmt->fetchColumn() ?: '');
        $num = 0;
        if (preg_match('/^custom_(\d+)$/', $lastCode, $m)) {
            $num = (int) $m[1];
        }
        return sprintf('custom_%03d', $num + 1);
    }

}
