<?php
declare(strict_types=1);

namespace App\Domain\Tenant;

use PDO;

final class TenantSettingsRepository
{
    public function __construct(
        private PDO $commonPdo,
        private PDO $tenantPdo
    ) {
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function findNotificationSettings(string $tenantCode): array
    {
        $stmt = $this->commonPdo->prepare(
            'SELECT r.notification_type,
                    r.is_enabled AS route_enabled,
                    t.id AS target_id,
                    t.provider_type,
                    t.destination_name,
                    t.webhook_url,
                    t.is_enabled AS target_enabled
             FROM tenant_notify_routes r
             LEFT JOIN tenant_notify_targets t
                    ON t.id = r.destination_id
                   AND t.is_deleted = 0
             WHERE r.tenant_code = :tenant_code
               AND r.is_deleted = 0'
        );
        $stmt->execute(['tenant_code' => $tenantCode]);
        $rows = $stmt->fetchAll();

        $settings = [
            'renewal' => [
                'notification_type' => 'renewal',
                'is_enabled' => 0,
                'provider_type' => 'lineworks',
                'destination_name' => 'renewal_default',
                'webhook_url' => '',
            ],
            'accident' => [
                'notification_type' => 'accident',
                'is_enabled' => 0,
                'provider_type' => 'lineworks',
                'destination_name' => 'accident_default',
                'webhook_url' => '',
            ],
        ];

        if (is_array($rows)) {
            foreach ($rows as $row) {
                $type = (string) ($row['notification_type'] ?? '');
                if (!isset($settings[$type])) {
                    continue;
                }

                $enabled = ((int) ($row['route_enabled'] ?? 0) === 1) && ((int) ($row['target_enabled'] ?? 0) === 1);

                $settings[$type] = [
                    'notification_type' => $type,
                    'is_enabled' => $enabled ? 1 : 0,
                    'provider_type' => (string) ($row['provider_type'] ?? 'lineworks'),
                    'destination_name' => (string) ($row['destination_name'] ?? ($type . '_default')),
                    'webhook_url' => (string) ($row['webhook_url'] ?? ''),
                ];
            }
        }

        return $settings;
    }

    public function saveNotificationSetting(
        string $tenantCode,
        string $notificationType,
        string $providerType,
        string $destinationName,
        string $webhookUrl,
        int $isEnabled,
        int $userId
    ): void {
        $this->commonPdo->beginTransaction();

        try {
            $upsertTarget = $this->commonPdo->prepare(
                'INSERT INTO tenant_notify_targets (
                    tenant_code,
                    provider_type,
                    destination_name,
                    webhook_url,
                    is_enabled,
                    is_deleted,
                    created_by,
                    updated_by
                 ) VALUES (
                    :tenant_code,
                    :provider_type,
                    :destination_name,
                    :webhook_url,
                    :is_enabled,
                    0,
                    :created_by,
                    :updated_by
                 )
                 ON DUPLICATE KEY UPDATE
                    provider_type = VALUES(provider_type),
                    webhook_url = VALUES(webhook_url),
                    is_enabled = VALUES(is_enabled),
                    is_deleted = 0,
                    updated_by = VALUES(updated_by)'
            );
            $upsertTarget->execute([
                'tenant_code' => $tenantCode,
                'provider_type' => $providerType,
                'destination_name' => $destinationName,
                'webhook_url' => $webhookUrl,
                'is_enabled' => $isEnabled,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            $targetId = (int) $this->commonPdo->lastInsertId();
            if ($targetId <= 0) {
                $idStmt = $this->commonPdo->prepare(
                    'SELECT id
                     FROM tenant_notify_targets
                     WHERE tenant_code = :tenant_code
                       AND destination_name = :destination_name
                     LIMIT 1'
                );
                $idStmt->execute([
                    'tenant_code' => $tenantCode,
                    'destination_name' => $destinationName,
                ]);
                $row = $idStmt->fetch();
                $targetId = is_array($row) ? (int) ($row['id'] ?? 0) : 0;
            }

            if ($targetId <= 0) {
                throw new \RuntimeException('通知先IDの取得に失敗しました。');
            }

            $upsertRoute = $this->commonPdo->prepare(
                'INSERT INTO tenant_notify_routes (
                    tenant_code,
                    notification_type,
                    destination_id,
                    is_enabled,
                    is_deleted,
                    created_by,
                    updated_by
                 ) VALUES (
                    :tenant_code,
                    :notification_type,
                    :destination_id,
                    :is_enabled,
                    0,
                    :created_by,
                    :updated_by
                 )
                 ON DUPLICATE KEY UPDATE
                    destination_id = VALUES(destination_id),
                    is_enabled = VALUES(is_enabled),
                    is_deleted = 0,
                    updated_by = VALUES(updated_by)'
            );
            $upsertRoute->execute([
                'tenant_code' => $tenantCode,
                'notification_type' => $notificationType,
                'destination_id' => $targetId,
                'is_enabled' => $isEnabled,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            $this->commonPdo->commit();
        } catch (\Throwable $e) {
            if ($this->commonPdo->inTransaction()) {
                $this->commonPdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findReminderPhases(): array
    {
        $stmt = $this->tenantPdo->query(
            'SELECT id,
                    phase_code,
                    phase_name,
                    from_days_before,
                    to_days_before,
                    is_enabled,
                    display_order,
                    updated_at
             FROM m_renewal_reminder_phase
             WHERE is_deleted = 0
             ORDER BY display_order ASC, id ASC'
        );
        $rows = $stmt->fetchAll();

        return is_array($rows) ? $rows : [];
    }

    public function updateReminderPhase(
        int $id,
        int $fromDaysBefore,
        int $toDaysBefore,
        int $isEnabled,
        int $displayOrder,
        int $updatedBy
    ): int {
        $stmt = $this->tenantPdo->prepare(
            'UPDATE m_renewal_reminder_phase
             SET from_days_before = :from_days_before,
                 to_days_before = :to_days_before,
                 is_enabled = :is_enabled,
                 display_order = :display_order,
                 updated_by = :updated_by
             WHERE id = :id
               AND is_deleted = 0'
        );
        $stmt->execute([
            'id' => $id,
            'from_days_before' => $fromDaysBefore,
            'to_days_before' => $toDaysBefore,
            'is_enabled' => $isEnabled,
            'display_order' => $displayOrder,
            'updated_by' => $updatedBy,
        ]);

        return $stmt->rowCount();
    }
}
