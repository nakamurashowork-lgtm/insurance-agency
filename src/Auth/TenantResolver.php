<?php
declare(strict_types=1);

namespace App\Auth;

use App\Domain\Auth\AuthException;
use App\Infra\CommonConnectionFactory;

final class TenantResolver
{
    public function __construct(private CommonConnectionFactory $commonFactory)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function resolvePrimaryTenantForUser(int $userId): array
    {
        $memberships = $this->findActiveMemberships($userId);
        if ($memberships === []) {
            throw new AuthException('所属なしのためログインできません。管理者へ連絡してください。');
        }

        if (count($memberships) === 1) {
            return $memberships[0];
        }

        throw new AuthException('複数所属だが主所属未確定のためログインできません。管理者へ連絡してください。');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function findActiveMemberships(int $userId): array
    {
        $pdo = $this->commonFactory->create();
        $stmt = $pdo->prepare(
            'SELECT ut.id,
                    ut.user_id,
                    ut.tenant_code,
                    ut.role,
                    t.id AS tenant_id,
                    t.tenant_name,
                    t.db_name
             FROM user_tenants ut
             INNER JOIN tenants t ON t.tenant_code = ut.tenant_code
             WHERE ut.user_id = :user_id
               AND ut.status = 1
               AND ut.is_deleted = 0
               AND t.status = 1
               AND t.is_deleted = 0
             ORDER BY ut.id ASC'
        );
        $stmt->execute(['user_id' => $userId]);

        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }
}
