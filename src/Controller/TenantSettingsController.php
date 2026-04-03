<?php
declare(strict_types=1);

namespace App\Controller;

use App\AppConfig;
use App\Domain\Tenant\ActivityPurposeTypeRepository;
use App\Domain\Tenant\ProductCategoryRepository;
use App\Domain\Tenant\RenewalCaseStatusRepository;
use App\Domain\Tenant\StaffSjnetMappingRepository;
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
        $phases              = [];
        $purposeTypes        = [];
        $staffMappings       = [];
        $renewalCaseStatuses = [];
        $productCategories   = [];
        $error               = null;

        try {
            $commonPdo  = $this->commonConnectionFactory->create();
            $tenantPdo  = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);

            $settingsRepo = new TenantSettingsRepository($commonPdo, $tenantPdo);
            $notifySettings      = $settingsRepo->findNotificationSettings((string) ($auth['tenant_code'] ?? ''));
            $phases              = $settingsRepo->findReminderPhases();

            $purposeTypes        = (new ActivityPurposeTypeRepository($tenantPdo))->findAll();
            $staffMappings       = (new StaffSjnetMappingRepository($tenantPdo))->findAll();
            $renewalCaseStatuses = (new RenewalCaseStatusRepository($tenantPdo))->findAll();
            $productCategories   = (new ProductCategoryRepository($tenantPdo))->findAll();
        } catch (Throwable) {
            $error = '管理・設定の取得に失敗しました。';
        }

        $flashError   = $this->guard->session()->consumeFlash('error');
        $flashSuccess = $this->guard->session()->consumeFlash('success');

        $masterCsrfs = [
            'purpose_type_create'  => $this->guard->session()->issueCsrfToken('tenant_purpose_type_create'),
            'purpose_type_update'  => $this->guard->session()->issueCsrfToken('tenant_purpose_type_update'),
            'purpose_type_delete'  => $this->guard->session()->issueCsrfToken('tenant_purpose_type_delete'),
            'staff_create'         => $this->guard->session()->issueCsrfToken('tenant_staff_create'),
            'staff_update'         => $this->guard->session()->issueCsrfToken('tenant_staff_update'),
            'staff_delete'         => $this->guard->session()->issueCsrfToken('tenant_staff_delete'),
            'status_create'        => $this->guard->session()->issueCsrfToken('tenant_status_create'),
            'status_update_name'   => $this->guard->session()->issueCsrfToken('tenant_status_update_name'),
            'status_delete'        => $this->guard->session()->issueCsrfToken('tenant_status_delete'),
            'category_create'      => $this->guard->session()->issueCsrfToken('tenant_category_create'),
            'category_update'      => $this->guard->session()->issueCsrfToken('tenant_category_update'),
            'category_delete'      => $this->guard->session()->issueCsrfToken('tenant_category_delete'),
            'notify_renewal'       => $this->guard->session()->issueCsrfToken('tenant_settings_renewal'),
            'notify_accident'      => $this->guard->session()->issueCsrfToken('tenant_settings_accident'),
        ];

        $masterUrls = [
            'purpose_type_create'  => $this->config->routeUrl('tenant/settings/purpose-type/create'),
            'purpose_type_update'  => $this->config->routeUrl('tenant/settings/purpose-type/update'),
            'purpose_type_delete'  => $this->config->routeUrl('tenant/settings/purpose-type/delete'),
            'staff_create'         => $this->config->routeUrl('tenant/settings/staff/create'),
            'staff_update'         => $this->config->routeUrl('tenant/settings/staff/update'),
            'staff_delete'         => $this->config->routeUrl('tenant/settings/staff/delete'),
            'status_create'        => $this->config->routeUrl('tenant/settings/status/create'),
            'status_update_name'   => $this->config->routeUrl('tenant/settings/status/update-name'),
            'status_delete'        => $this->config->routeUrl('tenant/settings/status/delete'),
            'category_create'      => $this->config->routeUrl('tenant/settings/category/create'),
            'category_update'      => $this->config->routeUrl('tenant/settings/category/update'),
            'category_delete'      => $this->config->routeUrl('tenant/settings/category/delete'),
            'notify_renewal'       => $this->config->routeUrl('tenant/settings/notify-renewal'),
            'notify_accident'      => $this->config->routeUrl('tenant/settings/notify-accident'),
        ];

        $allUsers = $this->fetchAllActiveUsers();

        Responses::html(TenantSettingsView::render(
            $auth,
            $notifySettings,
            $phases,
            $error,
            $flashError,
            $flashSuccess,
            ControllerLayoutHelper::build($this->guard, $this->config, 'settings'),
            $purposeTypes,
            $staffMappings,
            $renewalCaseStatuses,
            $productCategories,
            $masterCsrfs,
            $masterUrls,
            $allUsers
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
            Responses::redirect($this->settingsRedirectUrl());
        }

        $label = trim((string) ($_POST['label'] ?? ''));

        if ($label === '') {
            $this->guard->session()->setFlash('error', '表示名は必須です。');
            Responses::redirect($this->settingsRedirectUrl());
        }

        $code = 'pt_' . uniqid();

        try {
            $tenantPdo = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            (new ActivityPurposeTypeRepository($tenantPdo))->create($code, $label);
            $this->guard->session()->setFlash('success', '用件区分を追加しました。');
        } catch (Throwable) {
            $this->guard->session()->setFlash('error', '用件区分の追加に失敗しました。');
        }

        Responses::redirect($this->settingsRedirectUrl());
    }

    public function purposeTypeUpdate(): void
    {
        $auth = $this->guard->requireAuthenticated();
        $this->assertAdmin($auth);

        $token = (string) ($_POST['_csrf_token'] ?? '');
        if (!$this->guard->session()->validateAndConsumeCsrfToken('tenant_purpose_type_update', $token)) {
            $this->guard->session()->setFlash('error', '不正な更新要求を検出しました。');
            Responses::redirect($this->settingsRedirectUrl());
        }

        $code  = trim((string) ($_POST['code'] ?? ''));
        $label = trim((string) ($_POST['label'] ?? ''));

        if ($code === '' || $label === '') {
            $this->guard->session()->setFlash('error', 'コードと表示名は必須です。');
            Responses::redirect($this->settingsRedirectUrl());
        }

        try {
            $tenantPdo = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            $updated = (new ActivityPurposeTypeRepository($tenantPdo))->update($code, $label);
            if ($updated > 0) {
                $this->guard->session()->setFlash('success', '用件区分を更新しました。');
            } else {
                $this->guard->session()->setFlash('error', '更新対象が見つかりません。');
            }
        } catch (Throwable) {
            $this->guard->session()->setFlash('error', '用件区分の更新に失敗しました。');
        }

        Responses::redirect($this->settingsRedirectUrl());
    }

    public function purposeTypeDelete(): void
    {
        $auth = $this->guard->requireAuthenticated();
        $this->assertAdmin($auth);

        $token = (string) ($_POST['_csrf_token'] ?? '');
        if (!$this->guard->session()->validateAndConsumeCsrfToken('tenant_purpose_type_delete', $token)) {
            $this->guard->session()->setFlash('error', '不正な更新要求を検出しました。');
            Responses::redirect($this->settingsRedirectUrl());
        }

        $code = trim((string) ($_POST['code'] ?? ''));
        if ($code === '') {
            $this->guard->session()->setFlash('error', 'コードが指定されていません。');
            Responses::redirect($this->settingsRedirectUrl());
        }

        try {
            $tenantPdo = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            (new ActivityPurposeTypeRepository($tenantPdo))->delete($code);
            $this->guard->session()->setFlash('success', '用件区分を削除しました。');
        } catch (Throwable) {
            $this->guard->session()->setFlash('error', '用件区分の削除に失敗しました。');
        }

        Responses::redirect($this->settingsRedirectUrl());
    }

    // ---- 担当者マスタ ----

    public function staffCreate(): void
    {
        $auth = $this->guard->requireAuthenticated();
        $this->assertAdmin($auth);

        $token = (string) ($_POST['_csrf_token'] ?? '');
        if (!$this->guard->session()->validateAndConsumeCsrfToken('tenant_staff_create', $token)) {
            $this->guard->session()->setFlash('error', '不正な更新要求を検出しました。');
            Responses::redirect($this->settingsRedirectUrl());
        }

        $sjnetCode   = trim((string) ($_POST['sjnet_code'] ?? ''));
        $staffName   = trim((string) ($_POST['staff_name'] ?? ''));
        $isActive    = (int) isset($_POST['is_active']);
        $note        = trim((string) ($_POST['note'] ?? ''));
        $actorUserId = (int) ($auth['user_id'] ?? 0);

        if ($sjnetCode === '' || $staffName === '') {
            $this->guard->session()->setFlash('error', 'SJNETコードと担当者名は必須です。');
            Responses::redirect($this->settingsRedirectUrl());
        }

        try {
            $tenantPdo = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            (new StaffSjnetMappingRepository($tenantPdo))->create($sjnetCode, $staffName, 0, $isActive, $note !== '' ? $note : null, $actorUserId);
            $this->guard->session()->setFlash('success', '担当者を登録しました。');
        } catch (Throwable $e) {
            $msg = $e->getMessage();
            if (str_contains($msg, 'Duplicate') || str_contains($msg, 'duplicate')) {
                $this->guard->session()->setFlash('error', 'SJNETコード "' . $sjnetCode . '" は既に登録されています。');
            } else {
                $this->guard->session()->setFlash('error', '担当者の登録に失敗しました。');
            }
        }

        Responses::redirect($this->settingsRedirectUrl());
    }

    public function staffUpdate(): void
    {
        $auth = $this->guard->requireAuthenticated();
        $this->assertAdmin($auth);

        $token = (string) ($_POST['_csrf_token'] ?? '');
        if (!$this->guard->session()->validateAndConsumeCsrfToken('tenant_staff_update', $token)) {
            $this->guard->session()->setFlash('error', '不正な更新要求を検出しました。');
            Responses::redirect($this->settingsRedirectUrl());
        }

        $id          = (int) ($_POST['id'] ?? 0);
        $sjnetCode   = trim((string) ($_POST['sjnet_code'] ?? ''));
        $staffName   = trim((string) ($_POST['staff_name'] ?? ''));
        $isActive    = (int) isset($_POST['is_active']);
        $note        = trim((string) ($_POST['note'] ?? ''));
        $actorUserId = (int) ($auth['user_id'] ?? 0);

        if ($id <= 0 || $sjnetCode === '' || $staffName === '') {
            $this->guard->session()->setFlash('error', '入力値が不正です。');
            Responses::redirect($this->settingsRedirectUrl());
        }

        try {
            $tenantPdo = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            $updated = (new StaffSjnetMappingRepository($tenantPdo))->update($id, $sjnetCode, $staffName, 0, $isActive, $note !== '' ? $note : null, $actorUserId);
            if ($updated > 0) {
                $this->guard->session()->setFlash('success', '担当者を更新しました。');
            } else {
                $this->guard->session()->setFlash('error', '更新対象が見つかりません。');
            }
        } catch (Throwable $e) {
            $msg = $e->getMessage();
            if (str_contains($msg, 'Duplicate') || str_contains($msg, 'duplicate')) {
                $this->guard->session()->setFlash('error', 'SJNETコード "' . $sjnetCode . '" は既に使用されています。');
            } else {
                $this->guard->session()->setFlash('error', '担当者の更新に失敗しました。');
            }
        }

        Responses::redirect($this->settingsRedirectUrl());
    }

    public function staffDelete(): void
    {
        $auth = $this->guard->requireAuthenticated();
        $this->assertAdmin($auth);

        $token = (string) ($_POST['_csrf_token'] ?? '');
        if (!$this->guard->session()->validateAndConsumeCsrfToken('tenant_staff_delete', $token)) {
            $this->guard->session()->setFlash('error', '不正な更新要求を検出しました。');
            Responses::redirect($this->settingsRedirectUrl());
        }

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            $this->guard->session()->setFlash('error', 'IDが指定されていません。');
            Responses::redirect($this->settingsRedirectUrl());
        }

        try {
            $tenantPdo = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            (new StaffSjnetMappingRepository($tenantPdo))->delete($id);
            $this->guard->session()->setFlash('success', '担当者を削除しました。');
        } catch (Throwable) {
            $this->guard->session()->setFlash('error', '担当者の削除に失敗しました。');
        }

        Responses::redirect($this->settingsRedirectUrl());
    }

    // ---- 対応状況マスタ ----

    public function statusCreate(): void
    {
        $auth = $this->guard->requireAuthenticated();
        $this->assertAdmin($auth);

        $token = (string) ($_POST['_csrf_token'] ?? '');
        if (!$this->guard->session()->validateAndConsumeCsrfToken('tenant_status_create', $token)) {
            $this->guard->session()->setFlash('error', '不正な更新要求を検出しました。');
            Responses::redirect($this->settingsRedirectUrl());
        }

        $displayName = trim((string) ($_POST['display_name'] ?? ''));
        $actorUserId = (int) ($auth['user_id'] ?? 0);

        if ($displayName === '') {
            $this->guard->session()->setFlash('error', '表示名は必須です。');
            Responses::redirect($this->settingsRedirectUrl());
        }

        try {
            $tenantPdo = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            (new RenewalCaseStatusRepository($tenantPdo))->create($displayName, $actorUserId);
            $this->guard->session()->setFlash('success', '対応状況を追加しました。');
        } catch (Throwable) {
            $this->guard->session()->setFlash('error', '対応状況の追加に失敗しました。');
        }

        Responses::redirect($this->settingsRedirectUrl());
    }

    public function statusUpdateDisplayName(): void
    {
        $auth = $this->guard->requireAuthenticated();
        $this->assertAdmin($auth);

        $token = (string) ($_POST['_csrf_token'] ?? '');
        if (!$this->guard->session()->validateAndConsumeCsrfToken('tenant_status_update_name', $token)) {
            $this->guard->session()->setFlash('error', '不正な更新要求を検出しました。');
            Responses::redirect($this->settingsRedirectUrl());
        }

        $id          = (int) ($_POST['id'] ?? 0);
        $displayName = trim((string) ($_POST['display_name'] ?? ''));
        $actorUserId = (int) ($auth['user_id'] ?? 0);

        if ($id <= 0 || $displayName === '') {
            $this->guard->session()->setFlash('error', '入力値が不正です。');
            Responses::redirect($this->settingsRedirectUrl());
        }

        try {
            $tenantPdo = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            $updated = (new RenewalCaseStatusRepository($tenantPdo))->updateDisplayName($id, $displayName, $actorUserId);
            if ($updated > 0) {
                $this->guard->session()->setFlash('success', '対応状況を更新しました。');
            } else {
                $this->guard->session()->setFlash('error', '更新対象が見つからないか、固定ステータスです。');
            }
        } catch (Throwable) {
            $this->guard->session()->setFlash('error', '対応状況の更新に失敗しました。');
        }

        Responses::redirect($this->settingsRedirectUrl());
    }

    public function statusDelete(): void
    {
        $auth = $this->guard->requireAuthenticated();
        $this->assertAdmin($auth);

        $token = (string) ($_POST['_csrf_token'] ?? '');
        if (!$this->guard->session()->validateAndConsumeCsrfToken('tenant_status_delete', $token)) {
            $this->guard->session()->setFlash('error', '不正な更新要求を検出しました。');
            Responses::redirect($this->settingsRedirectUrl());
        }

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            $this->guard->session()->setFlash('error', 'IDが指定されていません。');
            Responses::redirect($this->settingsRedirectUrl());
        }

        try {
            $tenantPdo = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            $deleted = (new RenewalCaseStatusRepository($tenantPdo))->delete($id);
            if ($deleted > 0) {
                $this->guard->session()->setFlash('success', '対応状況を削除しました。');
            } else {
                $this->guard->session()->setFlash('error', '削除対象が見つからないか、固定ステータスのため削除できません。');
            }
        } catch (Throwable) {
            $this->guard->session()->setFlash('error', '対応状況の削除に失敗しました。');
        }

        Responses::redirect($this->settingsRedirectUrl());
    }

    // ---- 種目マスタ ----

    public function categoryCreate(): void
    {
        $auth = $this->guard->requireAuthenticated();
        $this->assertAdmin($auth);

        $token = (string) ($_POST['_csrf_token'] ?? '');
        if (!$this->guard->session()->validateAndConsumeCsrfToken('tenant_category_create', $token)) {
            $this->guard->session()->setFlash('error', '不正な更新要求を検出しました。');
            Responses::redirect($this->settingsRedirectUrl());
        }

        $csvValue    = trim((string) ($_POST['csv_value'] ?? ''));
        $displayName = trim((string) ($_POST['display_name'] ?? ''));
        $actorUserId = (int) ($auth['user_id'] ?? 0);

        if ($csvValue === '' || $displayName === '') {
            $this->guard->session()->setFlash('error', '種目種類値と表示名は必須です。');
            Responses::redirect($this->settingsRedirectUrl());
        }

        try {
            $tenantPdo = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            (new ProductCategoryRepository($tenantPdo))->create($csvValue, $displayName, $actorUserId);
            $this->guard->session()->setFlash('success', '種目を登録しました。');
        } catch (Throwable $e) {
            $msg = $e->getMessage();
            if (str_contains($msg, 'Duplicate') || str_contains($msg, 'duplicate')) {
                $this->guard->session()->setFlash('error', '種目種類値 "' . $csvValue . '" は既に登録されています。');
            } else {
                $this->guard->session()->setFlash('error', '種目の登録に失敗しました。');
            }
        }

        Responses::redirect($this->settingsRedirectUrl());
    }

    public function categoryUpdate(): void
    {
        $auth = $this->guard->requireAuthenticated();
        $this->assertAdmin($auth);

        $token = (string) ($_POST['_csrf_token'] ?? '');
        if (!$this->guard->session()->validateAndConsumeCsrfToken('tenant_category_update', $token)) {
            $this->guard->session()->setFlash('error', '不正な更新要求を検出しました。');
            Responses::redirect($this->settingsRedirectUrl());
        }

        $id          = (int) ($_POST['id'] ?? 0);
        $csvValue    = trim((string) ($_POST['csv_value'] ?? ''));
        $displayName = trim((string) ($_POST['display_name'] ?? ''));

        if ($id <= 0 || $csvValue === '' || $displayName === '') {
            $this->guard->session()->setFlash('error', '種目種類値と表示名は必須です。');
            Responses::redirect($this->settingsRedirectUrl());
        }

        try {
            $tenantPdo = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            (new ProductCategoryRepository($tenantPdo))->update($id, $csvValue, $displayName);
            $this->guard->session()->setFlash('success', '種目を更新しました。');
        } catch (Throwable $e) {
            $msg = $e->getMessage();
            if (str_contains($msg, 'Duplicate') || str_contains($msg, 'duplicate')) {
                $this->guard->session()->setFlash('error', '種目種類値 "' . $csvValue . '" は既に使用されています。');
            } else {
                $this->guard->session()->setFlash('error', '種目の更新に失敗しました。');
            }
        }

        Responses::redirect($this->settingsRedirectUrl());
    }

    public function categoryDelete(): void
    {
        $auth = $this->guard->requireAuthenticated();
        $this->assertAdmin($auth);

        $token = (string) ($_POST['_csrf_token'] ?? '');
        if (!$this->guard->session()->validateAndConsumeCsrfToken('tenant_category_delete', $token)) {
            $this->guard->session()->setFlash('error', '不正な更新要求を検出しました。');
            Responses::redirect($this->settingsRedirectUrl());
        }

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            $this->guard->session()->setFlash('error', 'IDが指定されていません。');
            Responses::redirect($this->settingsRedirectUrl());
        }

        try {
            $tenantPdo = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            (new ProductCategoryRepository($tenantPdo))->delete($id);
            $this->guard->session()->setFlash('success', '種目を削除しました。');
        } catch (Throwable) {
            $this->guard->session()->setFlash('error', '種目の削除に失敗しました。');
        }

        Responses::redirect($this->settingsRedirectUrl());
    }

    // ---- 通知設定 ----

    public function saveNotify(): void
    {
        $auth = $this->guard->requireAuthenticated();
        $this->assertAdmin($auth);

        $token = (string) ($_POST['_csrf_token'] ?? '');
        if (!$this->guard->session()->validateAndConsumeCsrfToken('tenant_settings_notify', $token)) {
            $this->guard->session()->setFlash('error', '不正な更新要求を検出しました。');
            Responses::redirect($this->settingsRedirectUrl());
        }

        $tenantCode = (string) ($auth['tenant_code'] ?? '');
        $userId = (int) ($auth['user_id'] ?? 0);

        $renewal = $this->normalizeNotifyInput('renewal');
        $accident = $this->normalizeNotifyInput('accident');

        if ($renewal === null || $accident === null) {
            $this->guard->session()->setFlash('error', '通知設定の入力値が不正です。');
            Responses::redirect($this->settingsRedirectUrl());
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

        Responses::redirect($this->settingsRedirectUrl());
    }

    public function savePhase(): void
    {
        $auth = $this->guard->requireAuthenticated();
        $this->assertAdmin($auth);

        $token = (string) ($_POST['_csrf_token'] ?? '');
        if (!$this->guard->session()->validateAndConsumeCsrfToken('tenant_settings_phase', $token)) {
            $this->guard->session()->setFlash('error', '不正な更新要求を検出しました。');
            Responses::redirect($this->settingsRedirectUrl());
        }

        $id = (int) ($_POST['id'] ?? 0);
        $fromDaysBefore = $this->toInt($_POST['from_days_before'] ?? null, -1);
        $toDaysBefore = $this->toInt($_POST['to_days_before'] ?? null, -1);
        $displayOrder = $this->toInt($_POST['display_order'] ?? null, -1);
        $isEnabled = isset($_POST['is_enabled']) ? 1 : 0;

        if ($id <= 0 || $fromDaysBefore < 0 || $toDaysBefore < 0 || $displayOrder < 0 || $fromDaysBefore < $toDaysBefore) {
            $this->guard->session()->setFlash('error', '満期通知フェーズの入力値が不正です。');
            Responses::redirect($this->settingsRedirectUrl());
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

        Responses::redirect($this->settingsRedirectUrl());
    }

    public function saveAll(): void
    {
        $auth = $this->guard->requireAuthenticated();
        $this->assertAdmin($auth);

        $token = (string) ($_POST['_csrf_token'] ?? '');
        if (!$this->guard->session()->validateAndConsumeCsrfToken('tenant_settings_all', $token)) {
            $this->guard->session()->setFlash('error', '不正な更新要求を検出しました。');
            Responses::redirect($this->settingsRedirectUrl());
        }

        $tenantCode = (string) ($auth['tenant_code'] ?? '');
        $userId = (int) ($auth['user_id'] ?? 0);

        $renewal = $this->normalizeNotifyInput('renewal');
        $accident = $this->normalizeNotifyInput('accident');

        if ($renewal === null || $accident === null) {
            $this->guard->session()->setFlash('error', '通知設定の入力値が不正です。');
            Responses::redirect($this->settingsRedirectUrl());
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
                Responses::redirect($this->settingsRedirectUrl());
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

        Responses::redirect($this->settingsRedirectUrl());
    }

    public function saveRenewalNotify(): void
    {
        $auth = $this->guard->requireAuthenticated();
        $this->assertAdmin($auth);

        $token = (string) ($_POST['_csrf_token'] ?? '');
        if (!$this->guard->session()->validateAndConsumeCsrfToken('tenant_settings_renewal', $token)) {
            $this->guard->session()->setFlash('error', '不正な更新要求を検出しました。');
            Responses::redirect($this->settingsRedirectUrl());
        }

        $tenantCode      = (string) ($auth['tenant_code'] ?? '');
        $userId          = (int) ($auth['user_id'] ?? 0);
        $providerType    = trim((string) ($_POST['renewal_provider_type'] ?? ''));
        $destinationName = trim((string) ($_POST['renewal_destination_name'] ?? ''));
        $webhookUrl      = trim((string) ($_POST['renewal_webhook_url'] ?? ''));

        if (!in_array($providerType, self::ALLOWED_PROVIDER, true)
            || $destinationName === ''
            || strlen($destinationName) > 100
            || strlen($webhookUrl) > 2000
        ) {
            $this->guard->session()->setFlash('error', '通知設定の入力値が不正です。');
            Responses::redirect($this->settingsRedirectUrl());
        }

        $isEnabled = isset($_POST['renewal_is_enabled']) ? 1 : 0;

        $earlyId      = (int) ($_POST['renewal_early_id'] ?? 0);
        $earlyDays    = max(0, $this->toInt($_POST['renewal_early_days'] ?? null, 28));
        $earlyEnabled = isset($_POST['renewal_early_enabled']) ? 1 : 0;
        $earlyOrder   = max(0, $this->toInt($_POST['renewal_early_order'] ?? null, 0));

        $nearId      = (int) ($_POST['renewal_near_id'] ?? 0);
        $nearDays    = max(0, $this->toInt($_POST['renewal_near_days'] ?? null, 14));
        $nearEnabled = isset($_POST['renewal_near_enabled']) ? 1 : 0;
        $nearOrder   = max(0, $this->toInt($_POST['renewal_near_order'] ?? null, 0));

        try {
            $commonPdo  = $this->commonConnectionFactory->create();
            $tenantPdo  = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            $repository = new TenantSettingsRepository($commonPdo, $tenantPdo);
            $repository->saveNotificationSetting(
                $tenantCode, 'renewal', $providerType, $destinationName, $webhookUrl, $isEnabled, $userId
            );
            if ($earlyId > 0) {
                $repository->updateReminderPhase($earlyId, $earlyDays, $earlyDays, $earlyEnabled, $earlyOrder, $userId);
            }
            if ($nearId > 0) {
                $repository->updateReminderPhase($nearId, $nearDays, $nearDays, $nearEnabled, $nearOrder, $userId);
            }
            $this->guard->session()->setFlash('success', '満期通知設定を保存しました。');
        } catch (Throwable) {
            $this->guard->session()->setFlash('error', '満期通知設定の保存に失敗しました。');
        }

        Responses::redirect($this->settingsRedirectUrl());
    }

    public function saveAccidentNotify(): void
    {
        $auth = $this->guard->requireAuthenticated();
        $this->assertAdmin($auth);

        $token = (string) ($_POST['_csrf_token'] ?? '');
        if (!$this->guard->session()->validateAndConsumeCsrfToken('tenant_settings_accident', $token)) {
            $this->guard->session()->setFlash('error', '不正な更新要求を検出しました。');
            Responses::redirect($this->settingsRedirectUrl());
        }

        $accident = $this->normalizeNotifyInput('accident');
        if ($accident === null) {
            $this->guard->session()->setFlash('error', '通知設定の入力値が不正です。');
            Responses::redirect($this->settingsRedirectUrl());
        }

        $tenantCode = (string) ($auth['tenant_code'] ?? '');
        $userId     = (int) ($auth['user_id'] ?? 0);

        try {
            $commonPdo  = $this->commonConnectionFactory->create();
            $tenantPdo  = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            $repository = new TenantSettingsRepository($commonPdo, $tenantPdo);
            $repository->saveNotificationSetting(
                $tenantCode, 'accident',
                $accident['provider_type'], $accident['destination_name'],
                $accident['webhook_url'], $accident['is_enabled'], $userId
            );
            $this->guard->session()->setFlash('success', '事故通知設定を保存しました。');
        } catch (Throwable) {
            $this->guard->session()->setFlash('error', '事故通知設定の保存に失敗しました。');
        }

        Responses::redirect($this->settingsRedirectUrl());
    }

    // ---- private helpers ----

    /**
     * @param array<string, mixed> $auth
     */
    private function settingsRedirectUrl(): string
    {
        $tab = trim((string) ($_POST['_tab'] ?? ''));
        $url = $this->config->routeUrl('tenant/settings');
        return $tab !== '' ? $url . '?tab=' . urlencode($tab) : $url;
    }

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
     * @return array<int, string>  [user_id => name]
     */
    private function fetchAllActiveUsers(): array
    {
        try {
            $pdo = $this->commonConnectionFactory->create();
            $stmt = $pdo->prepare(
                'SELECT id, name
                 FROM users
                 WHERE status = 1
                   AND is_deleted = 0
                 ORDER BY name ASC'
            );
            $stmt->execute();
            $names = [];
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            if (is_array($rows)) {
                foreach ($rows as $row) {
                    if (!is_array($row)) {
                        continue;
                    }
                    $id = (int) ($row['id'] ?? 0);
                    $name = trim((string) ($row['name'] ?? ''));
                    if ($id > 0 && $name !== '') {
                        $names[$id] = $name;
                    }
                }
            }
            return $names;
        } catch (\Throwable) {
            return [];
        }
    }
}
