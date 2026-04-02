<?php
declare(strict_types=1);

namespace App\Controller;

use App\AppConfig;
use App\Domain\Tenant\ActivityPurposeTypeRepository;
use App\Domain\Tenant\SjnetStaffMappingRepository;
use App\Domain\Tenant\SalesTargetRepository;
use App\Domain\Tenant\TenantSettingsRepository;
use App\Http\Responses;
use App\Infra\CommonConnectionFactory;
use App\Infra\TenantConnectionFactory;
use App\Presentation\TenantSettingsView;
use App\Security\AuthGuard;
use DateTimeImmutable;
use PDO;
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
        $phases         = [];
        $purposeTypes   = [];
        $sjnetMappings  = [];
        $salesTargets   = [];
        $staffUsers     = [];
        $error          = null;

        $currentFiscalYear = $this->currentFiscalYear();

        try {
            $commonPdo  = $this->commonConnectionFactory->create();
            $tenantPdo  = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);

            $settingsRepo = new TenantSettingsRepository($commonPdo, $tenantPdo);
            $notifySettings = $settingsRepo->findNotificationSettings((string) ($auth['tenant_code'] ?? ''));
            $phases         = $settingsRepo->findReminderPhases();

            $purposeTypes  = (new ActivityPurposeTypeRepository($tenantPdo))->findAll();
            $sjnetMappings = (new SjnetStaffMappingRepository($tenantPdo))->findAll();
            $salesTargets  = (new SalesTargetRepository($tenantPdo))->findByFiscalYear($currentFiscalYear);
            $staffUsers    = $this->fetchTenantUsers((string) ($auth['tenant_code'] ?? ''));
        } catch (Throwable) {
            $error = '管理・設定の取得に失敗しました。';
        }

        $flashError   = $this->guard->session()->consumeFlash('error');
        $flashSuccess = $this->guard->session()->consumeFlash('success');

        $masterCsrfs = [
            'purpose_type_create'     => $this->guard->session()->issueCsrfToken('tenant_purpose_type_create'),
            'purpose_type_update'     => $this->guard->session()->issueCsrfToken('tenant_purpose_type_update'),
            'purpose_type_deactivate' => $this->guard->session()->issueCsrfToken('tenant_purpose_type_deactivate'),
            'purpose_type_activate'   => $this->guard->session()->issueCsrfToken('tenant_purpose_type_activate'),
            'sjnet_create'            => $this->guard->session()->issueCsrfToken('tenant_sjnet_create'),
            'sjnet_update'            => $this->guard->session()->issueCsrfToken('tenant_sjnet_update'),
            'sjnet_deactivate'        => $this->guard->session()->issueCsrfToken('tenant_sjnet_deactivate'),
            'sales_target_save'       => $this->guard->session()->issueCsrfToken('tenant_sales_target_save'),
        ];

        $masterUrls = [
            'purpose_type_create'     => $this->config->routeUrl('tenant/settings/purpose-type/create'),
            'purpose_type_update'     => $this->config->routeUrl('tenant/settings/purpose-type/update'),
            'purpose_type_deactivate' => $this->config->routeUrl('tenant/settings/purpose-type/deactivate'),
            'purpose_type_activate'   => $this->config->routeUrl('tenant/settings/purpose-type/activate'),
            'sjnet_create'            => $this->config->routeUrl('tenant/settings/sjnet/create'),
            'sjnet_update'            => $this->config->routeUrl('tenant/settings/sjnet/update'),
            'sjnet_deactivate'        => $this->config->routeUrl('tenant/settings/sjnet/deactivate'),
            'sales_target_save'       => $this->config->routeUrl('tenant/settings/sales-target/save'),
        ];

        Responses::html(TenantSettingsView::render(
            $auth,
            $notifySettings,
            $phases,
            $this->config->routeUrl('tenant/settings/all'),
            $this->guard->session()->issueCsrfToken('tenant_settings_all'),
            $error,
            $flashError,
            $flashSuccess,
            ControllerLayoutHelper::build($this->guard, $this->config, 'settings'),
            $purposeTypes,
            $sjnetMappings,
            $staffUsers,
            $salesTargets,
            $currentFiscalYear,
            $masterCsrfs,
            $masterUrls
        ));
    }

    // ---- 用件区分マスタ ----

    public function purposeTypeCreate(): void
    {
        $auth = $this->guard->requireAuthenticated();
        $this->assertAdmin($auth);

        $token = (string) ($_POST['_csrf_token'] ?? '');
        if (!$this->guard->session()->validateAndConsumeCsrfToken('tenant_purpose_type_create', $token)) {
            $this->guard->session()->setFlash('error', '不正な更新要求を検出しました。');
            Responses::redirect($this->config->routeUrl('tenant/settings'));
        }

        $code         = trim((string) ($_POST['code'] ?? ''));
        $label        = trim((string) ($_POST['label'] ?? ''));
        $displayOrder = (int) ($_POST['display_order'] ?? 0);

        if ($code === '' || $label === '') {
            $this->guard->session()->setFlash('error', 'コードと表示名は必須です。');
            Responses::redirect($this->config->routeUrl('tenant/settings'));
        }
        if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $code)) {
            $this->guard->session()->setFlash('error', 'コードは半角英数・アンダースコア・ハイフンのみ使用できます。');
            Responses::redirect($this->config->routeUrl('tenant/settings'));
        }

        try {
            $tenantPdo = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            $repo = new ActivityPurposeTypeRepository($tenantPdo);
            if ($repo->findByCode($code) !== null) {
                $this->guard->session()->setFlash('error', 'コード "' . $code . '" は既に登録されています。');
                Responses::redirect($this->config->routeUrl('tenant/settings'));
            }
            $repo->create($code, $label, $displayOrder);
            $this->guard->session()->setFlash('success', '用件区分を追加しました。');
        } catch (Throwable) {
            $this->guard->session()->setFlash('error', '用件区分の追加に失敗しました。');
        }

        Responses::redirect($this->config->routeUrl('tenant/settings'));
    }

    public function purposeTypeUpdate(): void
    {
        $auth = $this->guard->requireAuthenticated();
        $this->assertAdmin($auth);

        $token = (string) ($_POST['_csrf_token'] ?? '');
        if (!$this->guard->session()->validateAndConsumeCsrfToken('tenant_purpose_type_update', $token)) {
            $this->guard->session()->setFlash('error', '不正な更新要求を検出しました。');
            Responses::redirect($this->config->routeUrl('tenant/settings'));
        }

        $code         = trim((string) ($_POST['code'] ?? ''));
        $label        = trim((string) ($_POST['label'] ?? ''));
        $displayOrder = (int) ($_POST['display_order'] ?? 0);

        if ($code === '' || $label === '') {
            $this->guard->session()->setFlash('error', 'コードと表示名は必須です。');
            Responses::redirect($this->config->routeUrl('tenant/settings'));
        }

        try {
            $tenantPdo = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            $repo = new ActivityPurposeTypeRepository($tenantPdo);
            $updated = $repo->update($code, $label, $displayOrder);
            if ($updated > 0) {
                $this->guard->session()->setFlash('success', '用件区分を更新しました。');
            } else {
                $this->guard->session()->setFlash('error', '更新対象が見つかりません。');
            }
        } catch (Throwable) {
            $this->guard->session()->setFlash('error', '用件区分の更新に失敗しました。');
        }

        Responses::redirect($this->config->routeUrl('tenant/settings'));
    }

    public function purposeTypeDeactivate(): void
    {
        $auth = $this->guard->requireAuthenticated();
        $this->assertAdmin($auth);

        $token = (string) ($_POST['_csrf_token'] ?? '');
        if (!$this->guard->session()->validateAndConsumeCsrfToken('tenant_purpose_type_deactivate', $token)) {
            $this->guard->session()->setFlash('error', '不正な更新要求を検出しました。');
            Responses::redirect($this->config->routeUrl('tenant/settings'));
        }

        $code = trim((string) ($_POST['code'] ?? ''));
        if ($code === '') {
            $this->guard->session()->setFlash('error', 'コードが指定されていません。');
            Responses::redirect($this->config->routeUrl('tenant/settings'));
        }

        try {
            $tenantPdo = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            (new ActivityPurposeTypeRepository($tenantPdo))->deactivate($code);
            $this->guard->session()->setFlash('success', '用件区分を無効化しました。');
        } catch (Throwable) {
            $this->guard->session()->setFlash('error', '用件区分の無効化に失敗しました。');
        }

        Responses::redirect($this->config->routeUrl('tenant/settings'));
    }

    public function purposeTypeActivate(): void
    {
        $auth = $this->guard->requireAuthenticated();
        $this->assertAdmin($auth);

        $token = (string) ($_POST['_csrf_token'] ?? '');
        if (!$this->guard->session()->validateAndConsumeCsrfToken('tenant_purpose_type_activate', $token)) {
            $this->guard->session()->setFlash('error', '不正な更新要求を検出しました。');
            Responses::redirect($this->config->routeUrl('tenant/settings'));
        }

        $code = trim((string) ($_POST['code'] ?? ''));
        if ($code === '') {
            $this->guard->session()->setFlash('error', 'コードが指定されていません。');
            Responses::redirect($this->config->routeUrl('tenant/settings'));
        }

        try {
            $tenantPdo = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            (new ActivityPurposeTypeRepository($tenantPdo))->activate($code);
            $this->guard->session()->setFlash('success', '用件区分を有効化しました。');
        } catch (Throwable) {
            $this->guard->session()->setFlash('error', '用件区分の有効化に失敗しました。');
        }

        Responses::redirect($this->config->routeUrl('tenant/settings'));
    }

    // ---- SJNETコード設定 ----

    public function sjnetCreate(): void
    {
        $auth = $this->guard->requireAuthenticated();
        $this->assertAdmin($auth);

        $token = (string) ($_POST['_csrf_token'] ?? '');
        if (!$this->guard->session()->validateAndConsumeCsrfToken('tenant_sjnet_create', $token)) {
            $this->guard->session()->setFlash('error', '不正な更新要求を検出しました。');
            Responses::redirect($this->config->routeUrl('tenant/settings'));
        }

        $agencyCode    = trim((string) ($_POST['sjnet_agency_code'] ?? ''));
        $staffName     = trim((string) ($_POST['sjnet_staff_name'] ?? ''));
        $userId        = (int) ($_POST['user_id'] ?? 0);
        $note          = trim((string) ($_POST['note'] ?? ''));
        $actorUserId   = (int) ($auth['user_id'] ?? 0);

        if ($agencyCode === '' || $userId <= 0) {
            $this->guard->session()->setFlash('error', '代理店コードと対応ユーザーは必須です。');
            Responses::redirect($this->config->routeUrl('tenant/settings'));
        }

        try {
            $tenantPdo = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            (new SjnetStaffMappingRepository($tenantPdo))->create(
                $agencyCode,
                $staffName !== '' ? $staffName : null,
                $userId,
                $note !== '' ? $note : null,
                $actorUserId
            );
            $this->guard->session()->setFlash('success', 'SJNETコードを登録しました。');
        } catch (Throwable $e) {
            $msg = $e->getMessage();
            if (str_contains($msg, 'Duplicate') || str_contains($msg, 'duplicate')) {
                $this->guard->session()->setFlash('error', '代理店コード "' . $agencyCode . '" は既に登録されています。');
            } else {
                $this->guard->session()->setFlash('error', 'SJNETコードの登録に失敗しました。');
            }
        }

        Responses::redirect($this->config->routeUrl('tenant/settings'));
    }

    public function sjnetUpdate(): void
    {
        $auth = $this->guard->requireAuthenticated();
        $this->assertAdmin($auth);

        $token = (string) ($_POST['_csrf_token'] ?? '');
        if (!$this->guard->session()->validateAndConsumeCsrfToken('tenant_sjnet_update', $token)) {
            $this->guard->session()->setFlash('error', '不正な更新要求を検出しました。');
            Responses::redirect($this->config->routeUrl('tenant/settings'));
        }

        $id          = (int) ($_POST['id'] ?? 0);
        $agencyCode  = trim((string) ($_POST['sjnet_agency_code'] ?? ''));
        $staffName   = trim((string) ($_POST['sjnet_staff_name'] ?? ''));
        $userId      = (int) ($_POST['user_id'] ?? 0);
        $note        = trim((string) ($_POST['note'] ?? ''));
        $actorUserId = (int) ($auth['user_id'] ?? 0);

        if ($id <= 0 || $agencyCode === '' || $userId <= 0) {
            $this->guard->session()->setFlash('error', '入力値が不正です。');
            Responses::redirect($this->config->routeUrl('tenant/settings'));
        }

        try {
            $tenantPdo = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            $updated = (new SjnetStaffMappingRepository($tenantPdo))->update(
                $id,
                $agencyCode,
                $staffName !== '' ? $staffName : null,
                $userId,
                $note !== '' ? $note : null,
                $actorUserId
            );
            if ($updated > 0) {
                $this->guard->session()->setFlash('success', 'SJNETコードを更新しました。');
            } else {
                $this->guard->session()->setFlash('error', '更新対象が見つかりません。');
            }
        } catch (Throwable $e) {
            $msg = $e->getMessage();
            if (str_contains($msg, 'Duplicate') || str_contains($msg, 'duplicate')) {
                $this->guard->session()->setFlash('error', '代理店コード "' . $agencyCode . '" は既に使用されています。');
            } else {
                $this->guard->session()->setFlash('error', 'SJNETコードの更新に失敗しました。');
            }
        }

        Responses::redirect($this->config->routeUrl('tenant/settings'));
    }

    public function sjnetDeactivate(): void
    {
        $auth = $this->guard->requireAuthenticated();
        $this->assertAdmin($auth);

        $token = (string) ($_POST['_csrf_token'] ?? '');
        if (!$this->guard->session()->validateAndConsumeCsrfToken('tenant_sjnet_deactivate', $token)) {
            $this->guard->session()->setFlash('error', '不正な更新要求を検出しました。');
            Responses::redirect($this->config->routeUrl('tenant/settings'));
        }

        $id          = (int) ($_POST['id'] ?? 0);
        $actorUserId = (int) ($auth['user_id'] ?? 0);

        if ($id <= 0) {
            $this->guard->session()->setFlash('error', 'IDが指定されていません。');
            Responses::redirect($this->config->routeUrl('tenant/settings'));
        }

        try {
            $tenantPdo = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            (new SjnetStaffMappingRepository($tenantPdo))->deactivate($id, $actorUserId);
            $this->guard->session()->setFlash('success', 'SJNETコードを無効化しました。');
        } catch (Throwable) {
            $this->guard->session()->setFlash('error', 'SJNETコードの無効化に失敗しました。');
        }

        Responses::redirect($this->config->routeUrl('tenant/settings'));
    }

    // ---- 目標管理 ----

    public function salesTargetSave(): void
    {
        $auth = $this->guard->requireAuthenticated();
        $this->assertAdmin($auth);

        $token = (string) ($_POST['_csrf_token'] ?? '');
        if (!$this->guard->session()->validateAndConsumeCsrfToken('tenant_sales_target_save', $token)) {
            $this->guard->session()->setFlash('error', '不正な更新要求を検出しました。');
            Responses::redirect($this->config->routeUrl('tenant/settings'));
        }

        $fiscalYear  = (int) ($_POST['fiscal_year'] ?? 0);
        $monthStr    = trim((string) ($_POST['target_month'] ?? ''));
        $staffStr    = trim((string) ($_POST['staff_user_id'] ?? ''));
        $targetType  = trim((string) ($_POST['target_type'] ?? ''));
        $amountStr   = trim((string) ($_POST['target_amount'] ?? ''));
        $actorUserId = (int) ($auth['user_id'] ?? 0);

        if ($fiscalYear <= 2000 || $fiscalYear > 2100) {
            $this->guard->session()->setFlash('error', '年度が不正です。');
            Responses::redirect($this->config->routeUrl('tenant/settings'));
        }

        $targetMonth  = ($monthStr !== '' && ctype_digit($monthStr)) ? (int) $monthStr : null;
        $staffUserId  = ($staffStr  !== '' && ctype_digit($staffStr))  ? (int) $staffStr  : null;

        if ($targetMonth !== null && ($targetMonth < 1 || $targetMonth > 12)) {
            $this->guard->session()->setFlash('error', '月は1〜12で指定してください。');
            Responses::redirect($this->config->routeUrl('tenant/settings'));
        }

        if (!in_array($targetType, SalesTargetRepository::ALLOWED_TARGET_TYPES, true)) {
            $this->guard->session()->setFlash('error', '目標種別が不正です。');
            Responses::redirect($this->config->routeUrl('tenant/settings'));
        }

        if (!ctype_digit($amountStr) && !($amountStr !== '' && preg_match('/^\d+$/', $amountStr))) {
            $this->guard->session()->setFlash('error', '目標値は0以上の整数で入力してください。');
            Responses::redirect($this->config->routeUrl('tenant/settings'));
        }
        $targetAmount = (int) $amountStr;

        try {
            $tenantPdo = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            (new SalesTargetRepository($tenantPdo))->upsert(
                $fiscalYear,
                $targetMonth,
                $staffUserId,
                $targetType,
                $targetAmount,
                $actorUserId
            );
            $this->guard->session()->setFlash('success', '目標を保存しました。');
        } catch (Throwable) {
            $this->guard->session()->setFlash('error', '目標の保存に失敗しました。');
        }

        Responses::redirect($this->config->routeUrl('tenant/settings'));
    }

    // ---- 既存メソッド（変更なし） ----

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
                $tenantCode, 'renewal',
                $renewal['provider_type'], $renewal['destination_name'],
                $renewal['webhook_url'], $renewal['is_enabled'], $userId
            );
            $repository->saveNotificationSetting(
                $tenantCode, 'accident',
                $accident['provider_type'], $accident['destination_name'],
                $accident['webhook_url'], $accident['is_enabled'], $userId
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
                $id, $fromDaysBefore, $toDaysBefore, $isEnabled, $displayOrder,
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

    // ---- private helpers ----

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
        $providerType    = trim((string) ($_POST[$prefix . '_provider_type'] ?? ''));
        $destinationName = trim((string) ($_POST[$prefix . '_destination_name'] ?? ''));
        $webhookUrl      = trim((string) ($_POST[$prefix . '_webhook_url'] ?? ''));
        $isEnabled       = isset($_POST[$prefix . '_is_enabled']) ? 1 : 0;

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
            'provider_type'    => $providerType,
            'destination_name' => $destinationName,
            'webhook_url'      => $webhookUrl,
            'is_enabled'       => $isEnabled,
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

    /**
     * テナントに所属するアクティブなユーザー一覧を取得する。
     *
     * @return array<int, array{id:int, name:string}>
     */
    private function fetchTenantUsers(string $tenantCode): array
    {
        if ($tenantCode === '') {
            return [];
        }

        $pdo = $this->commonConnectionFactory->create();
        $stmt = $pdo->prepare(
            'SELECT u.id, u.name
             FROM user_tenants ut
             INNER JOIN users u ON u.id = ut.user_id
             WHERE ut.tenant_code = :tenant_code
               AND ut.status = 1
               AND ut.is_deleted = 0
               AND u.status = 1
               AND u.is_deleted = 0
             ORDER BY u.name ASC, u.id ASC'
        );
        $stmt->bindValue(':tenant_code', $tenantCode);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!is_array($rows)) {
            return [];
        }

        $result = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id   = (int) ($row['id'] ?? 0);
            $name = trim((string) ($row['name'] ?? ''));
            if ($id > 0 && $name !== '') {
                $result[] = ['id' => $id, 'name' => $name];
            }
        }

        return $result;
    }

    private function currentFiscalYear(): int
    {
        $now   = new DateTimeImmutable();
        $year  = (int) $now->format('Y');
        $month = (int) $now->format('n');

        return $month >= 4 ? $year : $year - 1;
    }
}
