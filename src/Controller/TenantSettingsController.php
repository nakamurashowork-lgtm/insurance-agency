<?php
declare(strict_types=1);

namespace App\Controller;

use App\AppConfig;
use App\Domain\Tenant\TenantSettingsRepository;
use App\Http\Responses;
use App\Infra\CommonConnectionFactory;
use App\Infra\TenantConnectionFactory;
use App\Presentation\TenantSettingsView;
use App\Security\AuthGuard;
use Throwable;

final class TenantSettingsController
{
    private const ALLOWED_PROVIDER = ['lineworks', 'slack', 'teams', 'google_chat'];

    public function __construct(
        private AuthGuard $guard,
        private TenantConnectionFactory $tenantConnectionFactory,
        private CommonConnectionFactory $commonConnectionFactory,
        private AppConfig $config
    ) {
    }

    public function show(): void
    {
        $auth = $this->guard->requireAuthenticated();
        $this->assertAdmin($auth);

        $notifySettings = [
            'renewal' => [
                'is_enabled' => 0,
                'provider_type' => 'lineworks',
                'destination_name' => 'renewal_default',
                'webhook_url' => '',
            ],
            'accident' => [
                'is_enabled' => 0,
                'provider_type' => 'lineworks',
                'destination_name' => 'accident_default',
                'webhook_url' => '',
            ],
        ];
        $phases = [];
        $error = null;

        try {
            $commonPdo = $this->commonConnectionFactory->create();
            $tenantPdo = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            $repository = new TenantSettingsRepository($commonPdo, $tenantPdo);
            $notifySettings = $repository->findNotificationSettings((string) ($auth['tenant_code'] ?? ''));
            $phases = $repository->findReminderPhases();
        } catch (Throwable) {
            $error = '管理・設定の取得に失敗しました。';
        }

        $flashError = $this->guard->session()->consumeFlash('error');
        $flashSuccess = $this->guard->session()->consumeFlash('success');

        Responses::html(TenantSettingsView::render(
            $auth,
            $notifySettings,
            $phases,
            $this->config->routeUrl('tenant/settings/all'),
            $this->guard->session()->issueCsrfToken('tenant_settings_all'),
            $error,
            $flashError,
            $flashSuccess,
            ControllerLayoutHelper::build(
                $this->guard,
                $this->config,
                'settings'
            )
        ));
    }

    public function saveNotify(): void
    {
        $auth = $this->guard->requireAuthenticated();
        $this->assertAdmin($auth);

        $token = (string) ($_POST['_csrf_token'] ?? '');
        if (!$this->guard->session()->validateAndConsumeCsrfToken('tenant_settings_notify', $token)) {
            $this->guard->session()->setFlash('error', '不正な更新要求を検出しました。');
            Responses::redirect($this->config->routeUrl('tenant/settings'));
        }

        $tenantCode = (string) ($auth['tenant_code'] ?? '');
        $userId = (int) ($auth['user_id'] ?? 0);

        $renewal = $this->normalizeNotifyInput('renewal');
        $accident = $this->normalizeNotifyInput('accident');

        if ($renewal === null || $accident === null) {
            $this->guard->session()->setFlash('error', '通知設定の入力値が不正です。');
            Responses::redirect($this->config->routeUrl('tenant/settings'));
        }

        try {
            $commonPdo = $this->commonConnectionFactory->create();
            $tenantPdo = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            $repository = new TenantSettingsRepository($commonPdo, $tenantPdo);
            $repository->saveNotificationSetting(
                $tenantCode,
                'renewal',
                $renewal['provider_type'],
                $renewal['destination_name'],
                $renewal['webhook_url'],
                $renewal['is_enabled'],
                $userId
            );
            $repository->saveNotificationSetting(
                $tenantCode,
                'accident',
                $accident['provider_type'],
                $accident['destination_name'],
                $accident['webhook_url'],
                $accident['is_enabled'],
                $userId
            );

            $this->guard->session()->setFlash('success', '通知設定を保存しました。');
        } catch (Throwable) {
            $this->guard->session()->setFlash('error', '通知設定の保存に失敗しました。');
        }

        Responses::redirect($this->config->routeUrl('tenant/settings'));
    }

    public function savePhase(): void
    {
        $auth = $this->guard->requireAuthenticated();
        $this->assertAdmin($auth);

        $token = (string) ($_POST['_csrf_token'] ?? '');
        if (!$this->guard->session()->validateAndConsumeCsrfToken('tenant_settings_phase', $token)) {
            $this->guard->session()->setFlash('error', '不正な更新要求を検出しました。');
            Responses::redirect($this->config->routeUrl('tenant/settings'));
        }

        $id = (int) ($_POST['id'] ?? 0);
        $fromDaysBefore = $this->toInt($_POST['from_days_before'] ?? null, -1);
        $toDaysBefore = $this->toInt($_POST['to_days_before'] ?? null, -1);
        $displayOrder = $this->toInt($_POST['display_order'] ?? null, -1);
        $isEnabled = isset($_POST['is_enabled']) ? 1 : 0;

        if ($id <= 0 || $fromDaysBefore < 0 || $toDaysBefore < 0 || $displayOrder < 0 || $fromDaysBefore < $toDaysBefore) {
            $this->guard->session()->setFlash('error', '満期通知フェーズの入力値が不正です。');
            Responses::redirect($this->config->routeUrl('tenant/settings'));
        }

        try {
            $commonPdo = $this->commonConnectionFactory->create();
            $tenantPdo = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            $repository = new TenantSettingsRepository($commonPdo, $tenantPdo);
            $updated = $repository->updateReminderPhase(
                $id,
                $fromDaysBefore,
                $toDaysBefore,
                $isEnabled,
                $displayOrder,
                (int) ($auth['user_id'] ?? 0)
            );

            if ($updated > 0) {
                $this->guard->session()->setFlash('success', '通知タイミングを更新しました。');
            } else {
                $this->guard->session()->setFlash('error', '更新対象が見つからないか、変更がありません。');
            }
        } catch (Throwable) {
            $this->guard->session()->setFlash('error', '通知タイミングの更新に失敗しました。');
        }

        Responses::redirect($this->config->routeUrl('tenant/settings'));
    }

    public function saveAll(): void
    {
        $auth = $this->guard->requireAuthenticated();
        $this->assertAdmin($auth);

        $token = (string) ($_POST['_csrf_token'] ?? '');
        if (!$this->guard->session()->validateAndConsumeCsrfToken('tenant_settings_all', $token)) {
            $this->guard->session()->setFlash('error', '不正な更新要求を検出しました。');
            Responses::redirect($this->config->routeUrl('tenant/settings'));
        }

        $tenantCode = (string) ($auth['tenant_code'] ?? '');
        $userId = (int) ($auth['user_id'] ?? 0);

        $renewal = $this->normalizeNotifyInput('renewal');
        $accident = $this->normalizeNotifyInput('accident');

        if ($renewal === null || $accident === null) {
            $this->guard->session()->setFlash('error', '通知設定の入力値が不正です。');
            Responses::redirect($this->config->routeUrl('tenant/settings'));
        }

        $phaseInputs = is_array($_POST['phases'] ?? null) ? $_POST['phases'] : [];
        $parsedPhases = [];
        foreach ($phaseInputs as $idStr => $vals) {
            if (!is_array($vals)) {
                continue;
            }
            $id = (int) $idStr;
            $from = $this->toInt($vals['from_days_before'] ?? null, -1);
            $to = $this->toInt($vals['to_days_before'] ?? null, -1);
            $displayOrder = $this->toInt($vals['display_order'] ?? null, -1);
            if ($id <= 0 || $from < 0 || $to < 0 || $displayOrder < 0 || $from < $to) {
                $this->guard->session()->setFlash('error', '通知タイミングの入力値が不正です。');
                Responses::redirect($this->config->routeUrl('tenant/settings'));
            }
            $parsedPhases[] = ['id' => $id, 'from' => $from, 'to' => $to, 'display_order' => $displayOrder];
        }

        try {
            $commonPdo = $this->commonConnectionFactory->create();
            $tenantPdo = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            $repository = new TenantSettingsRepository($commonPdo, $tenantPdo);
            $repository->saveNotificationSetting(
                $tenantCode, 'renewal',
                $renewal['provider_type'], $renewal['destination_name'],
                $renewal['webhook_url'], $renewal['is_enabled'], $userId
            );
            $repository->saveNotificationSetting(
                $tenantCode, 'accident',
                $accident['provider_type'], $accident['destination_name'],
                $accident['webhook_url'], $accident['is_enabled'], $userId
            );
            foreach ($parsedPhases as $p) {
                $repository->updateReminderPhase(
                    $p['id'], $p['from'], $p['to'], 1, $p['display_order'], $userId
                );
            }
            $this->guard->session()->setFlash('success', '設定を保存しました。');
        } catch (Throwable) {
            $this->guard->session()->setFlash('error', '設定の保存に失敗しました。');
        }

        Responses::redirect($this->config->routeUrl('tenant/settings'));
    }

    /**
     * @param array<string, mixed> $auth
     */
    private function assertAdmin(array $auth): void
    {
        $permissions = $auth['permissions'] ?? [];
        $isSystemAdmin = is_array($permissions) && !empty($permissions['is_system_admin']);
        $isTenantAdmin = is_array($permissions) && (($permissions['tenant_role'] ?? '') === 'admin');
        if (!$isSystemAdmin && !$isTenantAdmin) {
            $this->guard->session()->setFlash('error', '管理者向け機能のため利用できません。');
            Responses::redirect($this->config->routeUrl('dashboard'));
        }
    }

    /**
     * @return array<string, int|string>|null
     */
    private function normalizeNotifyInput(string $prefix): ?array
    {
        $providerType = trim((string) ($_POST[$prefix . '_provider_type'] ?? ''));
        $destinationName = trim((string) ($_POST[$prefix . '_destination_name'] ?? ''));
        $webhookUrl = trim((string) ($_POST[$prefix . '_webhook_url'] ?? ''));
        $isEnabled = isset($_POST[$prefix . '_is_enabled']) ? 1 : 0;

        if (!in_array($providerType, self::ALLOWED_PROVIDER, true)) {
            return null;
        }
        if ($destinationName === '' || strlen($destinationName) > 100) {
            return null;
        }
        if ($isEnabled === 1 && $webhookUrl === '') {
            return null;
        }
        if (strlen($webhookUrl) > 2000) {
            return null;
        }

        return [
            'provider_type' => $providerType,
            'destination_name' => $destinationName,
            'webhook_url' => $webhookUrl,
            'is_enabled' => $isEnabled,
        ];
    }

    private function toInt(mixed $value, int $default): int
    {
        $text = trim((string) $value);
        if ($text === '' || !preg_match('/^-?\d+$/', $text)) {
            return $default;
        }

        return (int) $text;
    }
}
