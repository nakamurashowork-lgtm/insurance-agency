<?php
declare(strict_types=1);

namespace App\Controller;

use App\AppConfig;
use App\Domain\Tenant\ActivityPurposeTypeRepository;
use App\Domain\Tenant\ActivityTypeRepository;
use App\Domain\Tenant\ProductCategoryRepository;
use App\Domain\Tenant\CaseStatusRepository;
use App\Domain\Tenant\ProcedureMethodRepository;
use App\Domain\Tenant\RenewalMethodRepository;
use App\Domain\Tenant\SalesCaseStatusRepository;
use App\Domain\Tenant\SalesTargetRepository;
use App\Domain\Tenant\StaffRepository;
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
        $phases               = [];
        $purposeTypes         = [];
        $activityTypes        = [];
        $renewalCaseStatuses  = [];
        $accidentCaseStatuses = [];
        $salesCaseStatuses    = [];
        $productCategories    = [];
        $tenantUsers          = [];
        $procedureMethods     = [];
        $renewalMethods       = [];
        $yearlyTargets        = [];
        $assignableUsers      = [];
        $error                = null;

        $currentFiscalYear = $this->getCurrentFiscalYear();
        $selectedTargetFy  = isset($_GET['target_fy'])
            ? max($currentFiscalYear - 2, min($currentFiscalYear + 2, (int) $_GET['target_fy']))
            : $currentFiscalYear;
        $fiscalYearOptions = [
            $currentFiscalYear - 2,
            $currentFiscalYear - 1,
            $currentFiscalYear,
            $currentFiscalYear + 1,
            $currentFiscalYear + 2,
        ];

        try {
            $commonPdo  = $this->commonConnectionFactory->create();
            $tenantPdo  = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);

            $settingsRepo = new TenantSettingsRepository($commonPdo, $tenantPdo);
            $notifySettings      = $settingsRepo->findNotificationSettings((string) ($auth['tenant_code'] ?? ''));
            $phases              = $settingsRepo->findReminderPhases();

            $purposeTypes         = (new ActivityPurposeTypeRepository($tenantPdo))->findAll();
            $activityTypes        = (new ActivityTypeRepository($tenantPdo))->findAll();
            $caseStatusRepo       = new CaseStatusRepository($tenantPdo);
            $renewalCaseStatuses  = $caseStatusRepo->findByType('renewal');
            $accidentCaseStatuses = $caseStatusRepo->findByType('accident');
            $salesCaseStatuses    = (new SalesCaseStatusRepository($tenantPdo))->findAll();
            $productCategories    = (new ProductCategoryRepository($tenantPdo))->findAll();
            $procedureMethods     = (new ProcedureMethodRepository($tenantPdo))->findAll();
            $renewalMethods       = (new RenewalMethodRepository($tenantPdo))->findAll();

            $tenantCode = (string) ($auth['tenant_code'] ?? '');
            if ($tenantCode !== '') {
                $stmt = $commonPdo->prepare(
                    'SELECT u.id, u.name, u.display_name, u.email, ut.role
                     FROM user_tenants ut
                     INNER JOIN users u ON u.id = ut.user_id
                     WHERE ut.tenant_code = :tenant_code
                       AND ut.status = 1 AND ut.is_deleted = 0
                       AND u.status = 1 AND u.is_deleted = 0
                     ORDER BY COALESCE(u.display_name, u.name) ASC, u.id ASC'
                );
                $stmt->bindValue(':tenant_code', $tenantCode);
                $stmt->execute();
                $tenantUsers = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

                // m_staff を user_id で結合（担当者マスタの代理店コード/業務ロールを一覧に合流）
                $userIds = array_map(static fn ($u) => (int) ($u['id'] ?? 0), $tenantUsers);
                $staffByUserId = (new StaffRepository($tenantPdo))->findByUserIds($userIds);
                foreach ($tenantUsers as &$u) {
                    $uid = (int) ($u['id'] ?? 0);
                    $s   = $staffByUserId[$uid] ?? null;
                    $u['staff_id']   = $s !== null ? (int) $s['id']          : null;
                    $u['sjnet_code'] = $s !== null ? (string) ($s['sjnet_code'] ?? '') : '';
                    $u['is_sales']   = $s !== null ? (int) ($s['is_sales']   ?? 0) : 0;
                    $u['is_office']  = $s !== null ? (int) ($s['is_office']  ?? 0) : 0;
                }
                unset($u);
            }

            $targetRepo      = new SalesTargetRepository($tenantPdo, $commonPdo, $tenantCode);
            $yearlyTargets   = $targetRepo->findYearlyTargets($selectedTargetFy);
            $assignableUsers = $targetRepo->fetchAssignableUsers();
        } catch (Throwable) {
            $error = '管理・設定の取得に失敗しました。';
        }

        $flashError   = $this->guard->session()->consumeFlash('error');
        $flashSuccess = $this->guard->session()->consumeFlash('success');

        $masterCsrfs = [
            'purpose_type_create'      => $this->guard->session()->issueCsrfToken('tenant_purpose_type_create'),
            'purpose_type_update'      => $this->guard->session()->issueCsrfToken('tenant_purpose_type_update'),
            'purpose_type_deactivate'  => $this->guard->session()->issueCsrfToken('tenant_purpose_type_deactivate'),
            'purpose_type_activate'    => $this->guard->session()->issueCsrfToken('tenant_purpose_type_activate'),
            'purpose_type_delete'      => $this->guard->session()->issueCsrfToken('tenant_purpose_type_delete'),
            'purpose_type_reorder'     => $this->guard->session()->issueCsrfToken('tenant_purpose_type_reorder'),
            'status_create'            => $this->guard->session()->issueCsrfToken('tenant_status_create'),
            'status_update_name'       => $this->guard->session()->issueCsrfToken('tenant_status_update_name'),
            'status_deactivate'        => $this->guard->session()->issueCsrfToken('tenant_status_deactivate'),
            'status_activate'          => $this->guard->session()->issueCsrfToken('tenant_status_activate'),
            'status_delete'            => $this->guard->session()->issueCsrfToken('tenant_status_delete'),
            'status_reorder'           => $this->guard->session()->issueCsrfToken('tenant_status_reorder'),
            'sales_case_status_create'     => $this->guard->session()->issueCsrfToken('tenant_sales_case_status_create'),
            'sales_case_status_update'     => $this->guard->session()->issueCsrfToken('tenant_sales_case_status_update'),
            'sales_case_status_deactivate' => $this->guard->session()->issueCsrfToken('tenant_sales_case_status_deactivate'),
            'sales_case_status_activate'   => $this->guard->session()->issueCsrfToken('tenant_sales_case_status_activate'),
            'sales_case_status_delete'     => $this->guard->session()->issueCsrfToken('tenant_sales_case_status_delete'),
            'sales_case_status_reorder'    => $this->guard->session()->issueCsrfToken('tenant_sales_case_status_reorder'),
            'category_create'          => $this->guard->session()->issueCsrfToken('tenant_category_create'),
            'category_update'          => $this->guard->session()->issueCsrfToken('tenant_category_update'),
            'category_deactivate'      => $this->guard->session()->issueCsrfToken('tenant_category_deactivate'),
            'category_activate'        => $this->guard->session()->issueCsrfToken('tenant_category_activate'),
            'category_delete'          => $this->guard->session()->issueCsrfToken('tenant_category_delete'),
            'procedure_method_create'      => $this->guard->session()->issueCsrfToken('tenant_procedure_method_create'),
            'procedure_method_update'      => $this->guard->session()->issueCsrfToken('tenant_procedure_method_update'),
            'procedure_method_deactivate'  => $this->guard->session()->issueCsrfToken('tenant_procedure_method_deactivate'),
            'procedure_method_activate'    => $this->guard->session()->issueCsrfToken('tenant_procedure_method_activate'),
            'procedure_method_delete'      => $this->guard->session()->issueCsrfToken('tenant_procedure_method_delete'),
            'procedure_method_reorder'     => $this->guard->session()->issueCsrfToken('tenant_procedure_method_reorder'),
            'activity_type_create'         => $this->guard->session()->issueCsrfToken('tenant_activity_type_create'),
            'activity_type_update'         => $this->guard->session()->issueCsrfToken('tenant_activity_type_update'),
            'activity_type_deactivate'     => $this->guard->session()->issueCsrfToken('tenant_activity_type_deactivate'),
            'activity_type_activate'       => $this->guard->session()->issueCsrfToken('tenant_activity_type_activate'),
            'activity_type_delete'         => $this->guard->session()->issueCsrfToken('tenant_activity_type_delete'),
            'activity_type_reorder'        => $this->guard->session()->issueCsrfToken('tenant_activity_type_reorder'),
            'renewal_method_create'        => $this->guard->session()->issueCsrfToken('tenant_renewal_method_create'),
            'renewal_method_update'        => $this->guard->session()->issueCsrfToken('tenant_renewal_method_update'),
            'renewal_method_deactivate'    => $this->guard->session()->issueCsrfToken('tenant_renewal_method_deactivate'),
            'renewal_method_activate'      => $this->guard->session()->issueCsrfToken('tenant_renewal_method_activate'),
            'renewal_method_delete'        => $this->guard->session()->issueCsrfToken('tenant_renewal_method_delete'),
            'renewal_method_reorder'       => $this->guard->session()->issueCsrfToken('tenant_renewal_method_reorder'),
            'notify_renewal'               => $this->guard->session()->issueCsrfToken('tenant_settings_renewal'),
            'notify_accident'              => $this->guard->session()->issueCsrfToken('tenant_settings_accident'),
            'user_update'                  => $this->guard->session()->issueCsrfToken('tenant_user_update'),
            'sales_target_save'            => $this->guard->session()->issueCsrfToken('tenant_sales_target_save'),
            'sales_target_bulk_save'       => $this->guard->session()->issueCsrfToken('tenant_sales_target_bulk_save'),
            'sales_target_delete'          => $this->guard->session()->issueCsrfToken('tenant_sales_target_delete'),
        ];

        $masterUrls = [
            'purpose_type_create'      => $this->config->routeUrl('tenant/settings/purpose-type/create'),
            'purpose_type_update'      => $this->config->routeUrl('tenant/settings/purpose-type/update'),
            'purpose_type_deactivate'  => $this->config->routeUrl('tenant/settings/purpose-type/deactivate'),
            'purpose_type_activate'    => $this->config->routeUrl('tenant/settings/purpose-type/activate'),
            'purpose_type_delete'      => $this->config->routeUrl('tenant/settings/purpose-type/delete'),
            'purpose_type_reorder'     => $this->config->routeUrl('tenant/settings/purpose-type/reorder'),
            'status_create'            => $this->config->routeUrl('tenant/settings/status/create'),
            'status_update_name'       => $this->config->routeUrl('tenant/settings/status/update-name'),
            'status_deactivate'        => $this->config->routeUrl('tenant/settings/status/deactivate'),
            'status_activate'          => $this->config->routeUrl('tenant/settings/status/activate'),
            'status_delete'            => $this->config->routeUrl('tenant/settings/status/delete'),
            'status_reorder'           => $this->config->routeUrl('tenant/settings/status/reorder'),
            'sales_case_status_create'     => $this->config->routeUrl('tenant/settings/sales-case-status/create'),
            'sales_case_status_update'     => $this->config->routeUrl('tenant/settings/sales-case-status/update'),
            'sales_case_status_deactivate' => $this->config->routeUrl('tenant/settings/sales-case-status/deactivate'),
            'sales_case_status_activate'   => $this->config->routeUrl('tenant/settings/sales-case-status/activate'),
            'sales_case_status_delete'     => $this->config->routeUrl('tenant/settings/sales-case-status/delete'),
            'sales_case_status_reorder'    => $this->config->routeUrl('tenant/settings/sales-case-status/reorder'),
            'category_create'          => $this->config->routeUrl('tenant/settings/category/create'),
            'category_update'          => $this->config->routeUrl('tenant/settings/category/update'),
            'category_deactivate'      => $this->config->routeUrl('tenant/settings/category/deactivate'),
            'category_activate'        => $this->config->routeUrl('tenant/settings/category/activate'),
            'category_delete'          => $this->config->routeUrl('tenant/settings/category/delete'),
            'procedure_method_create'      => $this->config->routeUrl('tenant/settings/procedure-method/create'),
            'procedure_method_update'      => $this->config->routeUrl('tenant/settings/procedure-method/update'),
            'procedure_method_deactivate'  => $this->config->routeUrl('tenant/settings/procedure-method/deactivate'),
            'procedure_method_activate'    => $this->config->routeUrl('tenant/settings/procedure-method/activate'),
            'procedure_method_delete'      => $this->config->routeUrl('tenant/settings/procedure-method/delete'),
            'procedure_method_reorder'     => $this->config->routeUrl('tenant/settings/procedure-method/reorder'),
            'activity_type_create'         => $this->config->routeUrl('tenant/settings/activity-type/create'),
            'activity_type_update'         => $this->config->routeUrl('tenant/settings/activity-type/update'),
            'activity_type_deactivate'     => $this->config->routeUrl('tenant/settings/activity-type/deactivate'),
            'activity_type_activate'       => $this->config->routeUrl('tenant/settings/activity-type/activate'),
            'activity_type_delete'         => $this->config->routeUrl('tenant/settings/activity-type/delete'),
            'activity_type_reorder'        => $this->config->routeUrl('tenant/settings/activity-type/reorder'),
            'renewal_method_create'        => $this->config->routeUrl('tenant/settings/renewal-method/create'),
            'renewal_method_update'        => $this->config->routeUrl('tenant/settings/renewal-method/update'),
            'renewal_method_deactivate'    => $this->config->routeUrl('tenant/settings/renewal-method/deactivate'),
            'renewal_method_activate'      => $this->config->routeUrl('tenant/settings/renewal-method/activate'),
            'renewal_method_delete'        => $this->config->routeUrl('tenant/settings/renewal-method/delete'),
            'renewal_method_reorder'       => $this->config->routeUrl('tenant/settings/renewal-method/reorder'),
            'notify_renewal'               => $this->config->routeUrl('tenant/settings/notify-renewal'),
            'notify_accident'              => $this->config->routeUrl('tenant/settings/notify-accident'),
            'user_update'                  => $this->config->routeUrl('tenant/settings/user/update'),
            'sales_target_save'            => $this->config->routeUrl('tenant/sales-target/save'),
            'sales_target_bulk_save'       => $this->config->routeUrl('tenant/sales-target/bulk-save'),
            'sales_target_delete'          => $this->config->routeUrl('tenant/sales-target/delete'),
            'settings_base'                => $this->config->routeUrl('tenant/settings'),
        ];

        Responses::html(TenantSettingsView::render(
            $auth,
            $notifySettings,
            $phases,
            $error,
            $flashError,
            $flashSuccess,
            ControllerLayoutHelper::build($this->guard, $this->config, 'settings'),
            $purposeTypes,
            [],
            $renewalCaseStatuses,
            $productCategories,
            $masterCsrfs,
            $masterUrls,
            $accidentCaseStatuses,
            $tenantUsers,
            $procedureMethods,
            $yearlyTargets,
            $selectedTargetFy,
            $fiscalYearOptions,
            $assignableUsers,
            $activityTypes,
            $renewalMethods,
            $salesCaseStatuses
        ));
    }

    // ---- 手続方法マスタ ----

    public function procedureMethodCreate(): void
    {
        $auth = $this->guard->requireAuthenticated();
        $this->assertAdmin($auth);

        $token = (string) ($_POST['_csrf_token'] ?? '');
        if (!$this->guard->session()->validateAndConsumeCsrfToken('tenant_procedure_method_create', $token)) {
            $this->guard->session()->setFlash('error', '不正な更新要求を検出しました。');
            Responses::redirect($this->settingsRedirectUrl('procedure'));
        }

        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name === '') {
            $this->guard->session()->setFlash('error', '表示名は必須です。');
            Responses::redirect($this->settingsRedirectUrl('procedure'));
        }

        try {
            $tenantPdo = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            (new ProcedureMethodRepository($tenantPdo))->create($name);
            $this->guard->session()->setFlash('success', '手続方法を追加しました。');
        } catch (\DomainException $e) {
            $this->guard->session()->setFlash('error', $e->getMessage());
        } catch (Throwable) {
            $this->guard->session()->setFlash('error', '手続方法の追加に失敗しました。');
        }

        Responses::redirect($this->settingsRedirectUrl('procedure'));
    }

    public function procedureMethodUpdate(): void
    {
        $auth = $this->guard->requireAuthenticated();
        $this->assertAdmin($auth);

        $token = (string) ($_POST['_csrf_token'] ?? '');
        if (!$this->guard->session()->validateAndConsumeCsrfToken('tenant_procedure_method_update', $token)) {
            $this->guard->session()->setFlash('error', '不正な更新要求を検出しました。');
            Responses::redirect($this->settingsRedirectUrl('procedure'));
        }

        $id   = (int) ($_POST['id'] ?? 0);
        $name = trim((string) ($_POST['name'] ?? ''));

        if ($id <= 0 || $name === '') {
            $this->guard->session()->setFlash('error', 'IDと表示名は必須です。');
            Responses::redirect($this->settingsRedirectUrl('procedure'));
        }

        try {
            $tenantPdo = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            $updated = (new ProcedureMethodRepository($tenantPdo))->update($id, $name);
            if ($updated > 0) {
                $this->guard->session()->setFlash('success', '手続方法を更新しました。');
            } else {
                $this->guard->session()->setFlash('error', '更新対象が見つかりません。');
            }
        } catch (\DomainException $e) {
            $this->guard->session()->setFlash('error', $e->getMessage());
        } catch (Throwable) {
            $this->guard->session()->setFlash('error', '手続方法の更新に失敗しました。');
        }

        Responses::redirect($this->settingsRedirectUrl('procedure'));
    }

    public function procedureMethodDeactivate(): void
    {
        $auth = $this->guard->requireAuthenticated();
        $this->assertAdmin($auth);

        $token = (string) ($_POST['_csrf_token'] ?? '');
        if (!$this->guard->session()->validateAndConsumeCsrfToken('tenant_procedure_method_deactivate', $token)) {
            $this->guard->session()->setFlash('error', '不正な更新要求を検出しました。');
            Responses::redirect($this->settingsRedirectUrl('procedure'));
        }

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            $this->guard->session()->setFlash('error', 'IDが指定されていません。');
            Responses::redirect($this->settingsRedirectUrl('procedure'));
        }

        try {
            $tenantPdo = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            (new ProcedureMethodRepository($tenantPdo))->setActive($id, 0);
            $this->guard->session()->setFlash('success', '手続方法を無効化しました。');
        } catch (Throwable) {
            $this->guard->session()->setFlash('error', '手続方法の無効化に失敗しました。');
        }

        Responses::redirect($this->settingsRedirectUrl('procedure'));
    }

    public function procedureMethodActivate(): void
    {
        $auth = $this->guard->requireAuthenticated();
        $this->assertAdmin($auth);

        $token = (string) ($_POST['_csrf_token'] ?? '');
        if (!$this->guard->session()->validateAndConsumeCsrfToken('tenant_procedure_method_activate', $token)) {
            $this->guard->session()->setFlash('error', '不正な更新要求を検出しました。');
            Responses::redirect($this->settingsRedirectUrl('procedure'));
        }

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            $this->guard->session()->setFlash('error', 'IDが指定されていません。');
            Responses::redirect($this->settingsRedirectUrl('procedure'));
        }

        try {
            $tenantPdo = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            (new ProcedureMethodRepository($tenantPdo))->setActive($id, 1);
            $this->guard->session()->setFlash('success', '手続方法を有効化しました。');
        } catch (Throwable) {
            $this->guard->session()->setFlash('error', '手続方法の有効化に失敗しました。');
        }

        Responses::redirect($this->settingsRedirectUrl('procedure'));
    }

    public function procedureMethodDelete(): void
    {
        $auth = $this->guard->requireAuthenticated();
        $this->assertAdmin($auth);

        $token = (string) ($_POST['_csrf_token'] ?? '');
        if (!$this->guard->session()->validateAndConsumeCsrfToken('tenant_procedure_method_delete', $token)) {
            $this->guard->session()->setFlash('error', '不正な更新要求を検出しました。');
            Responses::redirect($this->settingsRedirectUrl('procedure'));
        }

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            $this->guard->session()->setFlash('error', 'IDが指定されていません。');
            Responses::redirect($this->settingsRedirectUrl('procedure'));
        }

        try {
            $tenantPdo = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            (new ProcedureMethodRepository($tenantPdo))->delete($id);
            $this->guard->session()->setFlash('success', '手続方法を削除しました。');
        } catch (Throwable) {
            $this->guard->session()->setFlash('error', '手続方法の削除に失敗しました。');
        }

        Responses::redirect($this->settingsRedirectUrl('procedure'));
    }

    public function procedureMethodReorder(): void
    {
        $auth = $this->guard->requireAuthenticated();
        $this->assertAdmin($auth);

        $token = (string) ($_POST['_csrf_token'] ?? '');
        if (!$this->guard->session()->validateAndConsumeCsrfToken('tenant_procedure_method_reorder', $token)) {
            $this->guard->session()->setFlash('error', '不正な更新要求を検出しました。');
            Responses::redirect($this->settingsRedirectUrl('procedure'));
        }

        $id        = (int) ($_POST['id'] ?? 0);
        $direction = (string) ($_POST['direction'] ?? '');
        if ($id <= 0 || !in_array($direction, ['up', 'down'], true)) {
            $this->guard->session()->setFlash('error', '入力値が不正です。');
            Responses::redirect($this->settingsRedirectUrl('procedure'));
        }

        try {
            $tenantPdo = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            (new ProcedureMethodRepository($tenantPdo))->swapDisplayOrder($id, $direction);
        } catch (Throwable) {
            $this->guard->session()->setFlash('error', '表示順の変更に失敗しました。');
        }

        Responses::redirect($this->settingsRedirectUrl('procedure'));
    }

    // ---- 活動種別マスタ ----

    public function activityTypeCreate(): void
    {
        $auth = $this->guard->requireAuthenticated();
        $this->assertAdmin($auth);

        $token = (string) ($_POST['_csrf_token'] ?? '');
        if (!$this->guard->session()->validateAndConsumeCsrfToken('tenant_activity_type_create', $token)) {
            $this->guard->session()->setFlash('error', '不正な更新要求を検出しました。');
            Responses::redirect($this->settingsRedirectUrl('activity-type'));
        }

        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name === '') {
            $this->guard->session()->setFlash('error', '表示名は必須です。');
            Responses::redirect($this->settingsRedirectUrl('activity-type'));
        }

        try {
            $tenantPdo = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            (new ActivityTypeRepository($tenantPdo))->create($name);
            $this->guard->session()->setFlash('success', '活動種別を追加しました。');
        } catch (\DomainException $e) {
            $this->guard->session()->setFlash('error', $e->getMessage());
        } catch (Throwable) {
            $this->guard->session()->setFlash('error', '活動種別の追加に失敗しました。');
        }

        Responses::redirect($this->settingsRedirectUrl('activity-type'));
    }

    public function activityTypeUpdate(): void
    {
        $auth = $this->guard->requireAuthenticated();
        $this->assertAdmin($auth);

        $token = (string) ($_POST['_csrf_token'] ?? '');
        if (!$this->guard->session()->validateAndConsumeCsrfToken('tenant_activity_type_update', $token)) {
            $this->guard->session()->setFlash('error', '不正な更新要求を検出しました。');
            Responses::redirect($this->settingsRedirectUrl('activity-type'));
        }

        $id   = (int) ($_POST['id'] ?? 0);
        $name = trim((string) ($_POST['name'] ?? ''));
        if ($id <= 0 || $name === '') {
            $this->guard->session()->setFlash('error', 'IDと表示名は必須です。');
            Responses::redirect($this->settingsRedirectUrl('activity-type'));
        }

        try {
            $tenantPdo = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            $updated = (new ActivityTypeRepository($tenantPdo))->updateName($id, $name);
            if ($updated > 0) {
                $this->guard->session()->setFlash('success', '活動種別を更新しました。');
            } else {
                $this->guard->session()->setFlash('error', '更新対象が見つかりません。');
            }
        } catch (\DomainException $e) {
            $this->guard->session()->setFlash('error', $e->getMessage());
        } catch (Throwable) {
            $this->guard->session()->setFlash('error', '活動種別の更新に失敗しました。');
        }

        Responses::redirect($this->settingsRedirectUrl('activity-type'));
    }

    public function activityTypeDeactivate(): void
    {
        $auth = $this->guard->requireAuthenticated();
        $this->assertAdmin($auth);

        $token = (string) ($_POST['_csrf_token'] ?? '');
        if (!$this->guard->session()->validateAndConsumeCsrfToken('tenant_activity_type_deactivate', $token)) {
            $this->guard->session()->setFlash('error', '不正な更新要求を検出しました。');
            Responses::redirect($this->settingsRedirectUrl('activity-type'));
        }

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            $this->guard->session()->setFlash('error', 'IDが指定されていません。');
            Responses::redirect($this->settingsRedirectUrl('activity-type'));
        }

        try {
            $tenantPdo = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            (new ActivityTypeRepository($tenantPdo))->setActive($id, 0);
            $this->guard->session()->setFlash('success', '活動種別を無効化しました。');
        } catch (Throwable) {
            $this->guard->session()->setFlash('error', '活動種別の無効化に失敗しました。');
        }

        Responses::redirect($this->settingsRedirectUrl('activity-type'));
    }

    public function activityTypeActivate(): void
    {
        $auth = $this->guard->requireAuthenticated();
        $this->assertAdmin($auth);

        $token = (string) ($_POST['_csrf_token'] ?? '');
        if (!$this->guard->session()->validateAndConsumeCsrfToken('tenant_activity_type_activate', $token)) {
            $this->guard->session()->setFlash('error', '不正な更新要求を検出しました。');
            Responses::redirect($this->settingsRedirectUrl('activity-type'));
        }

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            $this->guard->session()->setFlash('error', 'IDが指定されていません。');
            Responses::redirect($this->settingsRedirectUrl('activity-type'));
        }

        try {
            $tenantPdo = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            (new ActivityTypeRepository($tenantPdo))->setActive($id, 1);
            $this->guard->session()->setFlash('success', '活動種別を有効化しました。');
        } catch (Throwable) {
            $this->guard->session()->setFlash('error', '活動種別の有効化に失敗しました。');
        }

        Responses::redirect($this->settingsRedirectUrl('activity-type'));
    }

    public function activityTypeDelete(): void
    {
        $auth = $this->guard->requireAuthenticated();
        $this->assertAdmin($auth);

        $token = (string) ($_POST['_csrf_token'] ?? '');
        if (!$this->guard->session()->validateAndConsumeCsrfToken('tenant_activity_type_delete', $token)) {
            $this->guard->session()->setFlash('error', '不正な更新要求を検出しました。');
            Responses::redirect($this->settingsRedirectUrl('activity-type'));
        }

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            $this->guard->session()->setFlash('error', 'IDが指定されていません。');
            Responses::redirect($this->settingsRedirectUrl('activity-type'));
        }

        try {
            $tenantPdo = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            (new ActivityTypeRepository($tenantPdo))->delete($id);
            $this->guard->session()->setFlash('success', '活動種別を削除しました。');
        } catch (Throwable) {
            $this->guard->session()->setFlash('error', '活動種別の削除に失敗しました。');
        }

        Responses::redirect($this->settingsRedirectUrl('activity-type'));
    }

    public function activityTypeReorder(): void
    {
        $auth = $this->guard->requireAuthenticated();
        $this->assertAdmin($auth);

        $token = (string) ($_POST['_csrf_token'] ?? '');
        if (!$this->guard->session()->validateAndConsumeCsrfToken('tenant_activity_type_reorder', $token)) {
            $this->guard->session()->setFlash('error', '不正な更新要求を検出しました。');
            Responses::redirect($this->settingsRedirectUrl('activity-type'));
        }

        $id        = (int) ($_POST['id'] ?? 0);
        $direction = (string) ($_POST['direction'] ?? '');
        if ($id <= 0 || !in_array($direction, ['up', 'down'], true)) {
            $this->guard->session()->setFlash('error', '入力値が不正です。');
            Responses::redirect($this->settingsRedirectUrl('activity-type'));
        }

        try {
            $tenantPdo = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            (new ActivityTypeRepository($tenantPdo))->swapDisplayOrder($id, $direction);
        } catch (Throwable) {
            $this->guard->session()->setFlash('error', '表示順の変更に失敗しました。');
        }

        Responses::redirect($this->settingsRedirectUrl('activity-type'));
    }

    // ---- 更改方法マスタ ----

    public function renewalMethodCreate(): void
    {
        $auth = $this->guard->requireAuthenticated();
        $this->assertAdmin($auth);

        $token = (string) ($_POST['_csrf_token'] ?? '');
        if (!$this->guard->session()->validateAndConsumeCsrfToken('tenant_renewal_method_create', $token)) {
            $this->guard->session()->setFlash('error', '不正な更新要求を検出しました。');
            Responses::redirect($this->settingsRedirectUrl('renewal-method'));
        }

        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name === '') {
            $this->guard->session()->setFlash('error', '表示名は必須です。');
            Responses::redirect($this->settingsRedirectUrl('renewal-method'));
        }

        try {
            $tenantPdo = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            (new RenewalMethodRepository($tenantPdo))->create($name);
            $this->guard->session()->setFlash('success', '更改方法を追加しました。');
        } catch (\DomainException $e) {
            $this->guard->session()->setFlash('error', $e->getMessage());
        } catch (Throwable) {
            $this->guard->session()->setFlash('error', '更改方法の追加に失敗しました。');
        }

        Responses::redirect($this->settingsRedirectUrl('renewal-method'));
    }

    public function renewalMethodUpdate(): void
    {
        $auth = $this->guard->requireAuthenticated();
        $this->assertAdmin($auth);

        $token = (string) ($_POST['_csrf_token'] ?? '');
        if (!$this->guard->session()->validateAndConsumeCsrfToken('tenant_renewal_method_update', $token)) {
            $this->guard->session()->setFlash('error', '不正な更新要求を検出しました。');
            Responses::redirect($this->settingsRedirectUrl('renewal-method'));
        }

        $id   = (int) ($_POST['id'] ?? 0);
        $name = trim((string) ($_POST['name'] ?? ''));
        if ($id <= 0 || $name === '') {
            $this->guard->session()->setFlash('error', 'IDと表示名は必須です。');
            Responses::redirect($this->settingsRedirectUrl('renewal-method'));
        }

        try {
            $tenantPdo = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            $updated = (new RenewalMethodRepository($tenantPdo))->update($id, $name);
            if ($updated > 0) {
                $this->guard->session()->setFlash('success', '更改方法を更新しました。');
            } else {
                $this->guard->session()->setFlash('error', '更新対象が見つかりません。');
            }
        } catch (\DomainException $e) {
            $this->guard->session()->setFlash('error', $e->getMessage());
        } catch (Throwable) {
            $this->guard->session()->setFlash('error', '更改方法の更新に失敗しました。');
        }

        Responses::redirect($this->settingsRedirectUrl('renewal-method'));
    }

    public function renewalMethodDeactivate(): void
    {
        $auth = $this->guard->requireAuthenticated();
        $this->assertAdmin($auth);

        $token = (string) ($_POST['_csrf_token'] ?? '');
        if (!$this->guard->session()->validateAndConsumeCsrfToken('tenant_renewal_method_deactivate', $token)) {
            $this->guard->session()->setFlash('error', '不正な更新要求を検出しました。');
            Responses::redirect($this->settingsRedirectUrl('renewal-method'));
        }

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            $this->guard->session()->setFlash('error', 'IDが指定されていません。');
            Responses::redirect($this->settingsRedirectUrl('renewal-method'));
        }

        try {
            $tenantPdo = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            (new RenewalMethodRepository($tenantPdo))->setActive($id, 0);
            $this->guard->session()->setFlash('success', '更改方法を無効化しました。');
        } catch (Throwable) {
            $this->guard->session()->setFlash('error', '更改方法の無効化に失敗しました。');
        }

        Responses::redirect($this->settingsRedirectUrl('renewal-method'));
    }

    public function renewalMethodActivate(): void
    {
        $auth = $this->guard->requireAuthenticated();
        $this->assertAdmin($auth);

        $token = (string) ($_POST['_csrf_token'] ?? '');
        if (!$this->guard->session()->validateAndConsumeCsrfToken('tenant_renewal_method_activate', $token)) {
            $this->guard->session()->setFlash('error', '不正な更新要求を検出しました。');
            Responses::redirect($this->settingsRedirectUrl('renewal-method'));
        }

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            $this->guard->session()->setFlash('error', 'IDが指定されていません。');
            Responses::redirect($this->settingsRedirectUrl('renewal-method'));
        }

        try {
            $tenantPdo = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            (new RenewalMethodRepository($tenantPdo))->setActive($id, 1);
            $this->guard->session()->setFlash('success', '更改方法を有効化しました。');
        } catch (Throwable) {
            $this->guard->session()->setFlash('error', '更改方法の有効化に失敗しました。');
        }

        Responses::redirect($this->settingsRedirectUrl('renewal-method'));
    }

    public function renewalMethodDelete(): void
    {
        $auth = $this->guard->requireAuthenticated();
        $this->assertAdmin($auth);

        $token = (string) ($_POST['_csrf_token'] ?? '');
        if (!$this->guard->session()->validateAndConsumeCsrfToken('tenant_renewal_method_delete', $token)) {
            $this->guard->session()->setFlash('error', '不正な更新要求を検出しました。');
            Responses::redirect($this->settingsRedirectUrl('renewal-method'));
        }

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            $this->guard->session()->setFlash('error', 'IDが指定されていません。');
            Responses::redirect($this->settingsRedirectUrl('renewal-method'));
        }

        try {
            $tenantPdo = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            (new RenewalMethodRepository($tenantPdo))->delete($id);
            $this->guard->session()->setFlash('success', '更改方法を削除しました。');
        } catch (Throwable) {
            $this->guard->session()->setFlash('error', '更改方法の削除に失敗しました。');
        }

        Responses::redirect($this->settingsRedirectUrl('renewal-method'));
    }

    public function renewalMethodReorder(): void
    {
        $auth = $this->guard->requireAuthenticated();
        $this->assertAdmin($auth);

        $token = (string) ($_POST['_csrf_token'] ?? '');
        if (!$this->guard->session()->validateAndConsumeCsrfToken('tenant_renewal_method_reorder', $token)) {
            $this->guard->session()->setFlash('error', '不正な更新要求を検出しました。');
            Responses::redirect($this->settingsRedirectUrl('renewal-method'));
        }

        $id        = (int) ($_POST['id'] ?? 0);
        $direction = (string) ($_POST['direction'] ?? '');
        if ($id <= 0 || !in_array($direction, ['up', 'down'], true)) {
            $this->guard->session()->setFlash('error', '入力値が不正です。');
            Responses::redirect($this->settingsRedirectUrl('renewal-method'));
        }

        try {
            $tenantPdo = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            (new RenewalMethodRepository($tenantPdo))->swapDisplayOrder($id, $direction);
        } catch (Throwable) {
            $this->guard->session()->setFlash('error', '表示順の変更に失敗しました。');
        }

        Responses::redirect($this->settingsRedirectUrl('renewal-method'));
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

        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name === '') {
            $this->guard->session()->setFlash('error', '表示名は必須です。');
            Responses::redirect($this->settingsRedirectUrl());
        }

        try {
            $tenantPdo = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            (new ActivityPurposeTypeRepository($tenantPdo))->create($name);
            $this->guard->session()->setFlash('success', '用件区分を追加しました。');
        } catch (\DomainException $e) {
            $this->guard->session()->setFlash('error', $e->getMessage());
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

        $id   = (int) ($_POST['id'] ?? 0);
        $name = trim((string) ($_POST['name'] ?? ''));
        if ($id <= 0 || $name === '') {
            $this->guard->session()->setFlash('error', 'IDと表示名は必須です。');
            Responses::redirect($this->settingsRedirectUrl());
        }

        try {
            $tenantPdo = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            $updated = (new ActivityPurposeTypeRepository($tenantPdo))->updateName($id, $name);
            if ($updated > 0) {
                $this->guard->session()->setFlash('success', '用件区分を更新しました。');
            } else {
                $this->guard->session()->setFlash('error', '更新対象が見つかりません。');
            }
        } catch (\DomainException $e) {
            $this->guard->session()->setFlash('error', $e->getMessage());
        } catch (Throwable) {
            $this->guard->session()->setFlash('error', '用件区分の更新に失敗しました。');
        }

        Responses::redirect($this->settingsRedirectUrl());
    }

    public function purposeTypeDeactivate(): void
    {
        $auth = $this->guard->requireAuthenticated();
        $this->assertAdmin($auth);

        $token = (string) ($_POST['_csrf_token'] ?? '');
        if (!$this->guard->session()->validateAndConsumeCsrfToken('tenant_purpose_type_deactivate', $token)) {
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
            (new ActivityPurposeTypeRepository($tenantPdo))->setActive($id, 0);
            $this->guard->session()->setFlash('success', '用件区分を無効化しました。');
        } catch (Throwable) {
            $this->guard->session()->setFlash('error', '用件区分の無効化に失敗しました。');
        }

        Responses::redirect($this->settingsRedirectUrl());
    }

    public function purposeTypeActivate(): void
    {
        $auth = $this->guard->requireAuthenticated();
        $this->assertAdmin($auth);

        $token = (string) ($_POST['_csrf_token'] ?? '');
        if (!$this->guard->session()->validateAndConsumeCsrfToken('tenant_purpose_type_activate', $token)) {
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
            (new ActivityPurposeTypeRepository($tenantPdo))->setActive($id, 1);
            $this->guard->session()->setFlash('success', '用件区分を有効化しました。');
        } catch (Throwable) {
            $this->guard->session()->setFlash('error', '用件区分の有効化に失敗しました。');
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

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            $this->guard->session()->setFlash('error', 'IDが指定されていません。');
            Responses::redirect($this->settingsRedirectUrl());
        }

        try {
            $tenantPdo = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            (new ActivityPurposeTypeRepository($tenantPdo))->delete($id);
            $this->guard->session()->setFlash('success', '用件区分を削除しました。');
        } catch (Throwable) {
            $this->guard->session()->setFlash('error', '用件区分の削除に失敗しました。');
        }

        Responses::redirect($this->settingsRedirectUrl());
    }

    public function purposeTypeReorder(): void
    {
        $auth = $this->guard->requireAuthenticated();
        $this->assertAdmin($auth);

        $token = (string) ($_POST['_csrf_token'] ?? '');
        if (!$this->guard->session()->validateAndConsumeCsrfToken('tenant_purpose_type_reorder', $token)) {
            $this->guard->session()->setFlash('error', '不正な更新要求を検出しました。');
            Responses::redirect($this->settingsRedirectUrl());
        }

        $id        = (int) ($_POST['id'] ?? 0);
        $direction = (string) ($_POST['direction'] ?? '');
        if ($id <= 0 || !in_array($direction, ['up', 'down'], true)) {
            $this->guard->session()->setFlash('error', '入力値が不正です。');
            Responses::redirect($this->settingsRedirectUrl());
        }

        try {
            $tenantPdo = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            (new ActivityPurposeTypeRepository($tenantPdo))->swapDisplayOrder($id, $direction);
        } catch (Throwable) {
            $this->guard->session()->setFlash('error', '表示順の変更に失敗しました。');
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

        $name        = trim((string) ($_POST['name'] ?? ''));
        $caseType    = trim((string) ($_POST['case_type'] ?? 'renewal'));
        $actorUserId = (int) ($auth['user_id'] ?? 0);
        $isCompleted = (($_POST['is_completed'] ?? '0') === '1' ? 1 : 0);

        if (!in_array($caseType, ['renewal', 'accident'], true)) {
            $caseType = 'renewal';
        }

        if ($name === '') {
            $this->guard->session()->setFlash('error', '表示名は必須です。');
            Responses::redirect($this->settingsRedirectUrl());
        }

        try {
            $tenantPdo = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            $repo = new CaseStatusRepository($tenantPdo);
            $newId = $repo->create($caseType, $name, $actorUserId);
            if ($isCompleted === 1) {
                $repo->setCompleted($newId, 1, $actorUserId);
            }
            $this->guard->session()->setFlash('success', '対応状況を追加しました。');
        } catch (\DomainException $e) {
            $this->guard->session()->setFlash('error', $e->getMessage());
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
        $name        = trim((string) ($_POST['name'] ?? ''));
        $actorUserId = (int) ($auth['user_id'] ?? 0);
        $isCompleted = (($_POST['is_completed'] ?? '0') === '1' ? 1 : 0);

        if ($id <= 0 || $name === '') {
            $this->guard->session()->setFlash('error', '入力値が不正です。');
            Responses::redirect($this->settingsRedirectUrl());
        }

        try {
            $tenantPdo = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            $repo = new CaseStatusRepository($tenantPdo);
            $row  = $repo->findById($id);
            if ($row === null) {
                $this->guard->session()->setFlash('error', '対象が見つかりません。');
                Responses::redirect($this->settingsRedirectUrl());
            }
            if ($repo->canRename($row)) {
                $repo->updateName($id, $name, $actorUserId);
            }
            // 完了フラグは保護レコードでも切替可能にする（ユーザー判断で変更可）
            $repo->setCompleted($id, $isCompleted, $actorUserId);
            $this->guard->session()->setFlash('success', '対応状況を更新しました。');
        } catch (\DomainException $e) {
            $this->guard->session()->setFlash('error', $e->getMessage());
        } catch (Throwable) {
            $this->guard->session()->setFlash('error', '対応状況の更新に失敗しました。');
        }

        Responses::redirect($this->settingsRedirectUrl());
    }

    public function statusDeactivate(): void
    {
        $auth = $this->guard->requireAuthenticated();
        $this->assertAdmin($auth);

        $token = (string) ($_POST['_csrf_token'] ?? '');
        if (!$this->guard->session()->validateAndConsumeCsrfToken('tenant_status_deactivate', $token)) {
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
            $repo = new CaseStatusRepository($tenantPdo);
            $row  = $repo->findById($id);
            if ($row === null || !$repo->canDisable($row)) {
                $this->guard->session()->setFlash('error', 'このステータスは無効化できません。');
                Responses::redirect($this->settingsRedirectUrl());
            }
            $updated = $repo->setActive($id, 0);
            if ($updated > 0) {
                $this->guard->session()->setFlash('success', '対応状況を無効化しました。');
            } else {
                $this->guard->session()->setFlash('error', '対象が見つかりません。');
            }
        } catch (Throwable) {
            $this->guard->session()->setFlash('error', '対応状況の無効化に失敗しました。');
        }

        Responses::redirect($this->settingsRedirectUrl());
    }

    public function statusActivate(): void
    {
        $auth = $this->guard->requireAuthenticated();
        $this->assertAdmin($auth);

        $token = (string) ($_POST['_csrf_token'] ?? '');
        if (!$this->guard->session()->validateAndConsumeCsrfToken('tenant_status_activate', $token)) {
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
            $updated = (new CaseStatusRepository($tenantPdo))->setActive($id, 1);
            if ($updated > 0) {
                $this->guard->session()->setFlash('success', '対応状況を有効化しました。');
            } else {
                $this->guard->session()->setFlash('error', '対象が見つかりません。');
            }
        } catch (Throwable) {
            $this->guard->session()->setFlash('error', '対応状況の有効化に失敗しました。');
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
            (new CaseStatusRepository($tenantPdo))->delete($id, (int) ($auth['user_id'] ?? 0));
            $this->guard->session()->setFlash('success', '対応状況を削除しました。');
        } catch (\DomainException $e) {
            $this->guard->session()->setFlash('error', $e->getMessage());
        } catch (Throwable) {
            $this->guard->session()->setFlash('error', '対応状況の削除に失敗しました。');
        }

        Responses::redirect($this->settingsRedirectUrl());
    }

    public function statusReorder(): void
    {
        $auth = $this->guard->requireAuthenticated();
        $this->assertAdmin($auth);

        $token = (string) ($_POST['_csrf_token'] ?? '');
        if (!$this->guard->session()->validateAndConsumeCsrfToken('tenant_status_reorder', $token)) {
            $this->guard->session()->setFlash('error', '不正な更新要求を検出しました。');
            Responses::redirect($this->settingsRedirectUrl());
        }

        $id        = (int) ($_POST['id'] ?? 0);
        $direction = trim((string) ($_POST['direction'] ?? ''));
        if ($id <= 0 || !in_array($direction, ['up', 'down'], true)) {
            $this->guard->session()->setFlash('error', '入力値が不正です。');
            Responses::redirect($this->settingsRedirectUrl());
        }

        try {
            $tenantPdo = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            (new CaseStatusRepository($tenantPdo))->swapDisplayOrder($id, $direction, (int) ($auth['user_id'] ?? 0));
        } catch (Throwable) {
            $this->guard->session()->setFlash('error', '並び順の変更に失敗しました。');
        }

        Responses::redirect($this->settingsRedirectUrl());
    }

    // ---- 見込案件ステータスマスタ ----

    public function salesCaseStatusCreate(): void
    {
        $auth = $this->guard->requireAuthenticated();
        $this->assertAdmin($auth);

        $token = (string) ($_POST['_csrf_token'] ?? '');
        if (!$this->guard->session()->validateAndConsumeCsrfToken('tenant_sales_case_status_create', $token)) {
            $this->guard->session()->setFlash('error', '不正な更新要求を検出しました。');
            Responses::redirect($this->settingsRedirectUrl('sales-case-status'));
        }

        $name        = trim((string) ($_POST['name'] ?? ''));
        $isCompleted = (int) (($_POST['is_completed'] ?? '0') === '1' ? 1 : 0);

        if ($name === '') {
            $this->guard->session()->setFlash('error', '表示名は必須です。');
            Responses::redirect($this->settingsRedirectUrl('sales-case-status'));
        }
        if (mb_strlen($name) > 50) {
            $this->guard->session()->setFlash('error', '表示名は50文字以内で入力してください。');
            Responses::redirect($this->settingsRedirectUrl('sales-case-status'));
        }

        try {
            $tenantPdo = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            $repo = new SalesCaseStatusRepository($tenantPdo);
            $newId = $repo->create($name);
            if ($isCompleted === 1) {
                $repo->setCompleted($newId, 1);
            }
            $this->guard->session()->setFlash('success', '対応状況を追加しました。');
        } catch (\DomainException $e) {
            $this->guard->session()->setFlash('error', $e->getMessage());
        } catch (Throwable) {
            $this->guard->session()->setFlash('error', '対応状況の追加に失敗しました。');
        }

        Responses::redirect($this->settingsRedirectUrl('sales-case-status'));
    }

    public function salesCaseStatusDelete(): void
    {
        $auth = $this->guard->requireAuthenticated();
        $this->assertAdmin($auth);

        $token = (string) ($_POST['_csrf_token'] ?? '');
        if (!$this->guard->session()->validateAndConsumeCsrfToken('tenant_sales_case_status_delete', $token)) {
            $this->guard->session()->setFlash('error', '不正な更新要求を検出しました。');
            Responses::redirect($this->settingsRedirectUrl('sales-case-status'));
        }

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            $this->guard->session()->setFlash('error', 'IDが指定されていません。');
            Responses::redirect($this->settingsRedirectUrl('sales-case-status'));
        }

        try {
            $tenantPdo = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            (new SalesCaseStatusRepository($tenantPdo))->delete($id);
            $this->guard->session()->setFlash('success', '対応状況を削除しました。');
        } catch (\DomainException $e) {
            $this->guard->session()->setFlash('error', $e->getMessage());
        } catch (Throwable) {
            $this->guard->session()->setFlash('error', '対応状況の削除に失敗しました。');
        }

        Responses::redirect($this->settingsRedirectUrl('sales-case-status'));
    }

    public function salesCaseStatusUpdate(): void
    {
        $auth = $this->guard->requireAuthenticated();
        $this->assertAdmin($auth);

        $token = (string) ($_POST['_csrf_token'] ?? '');
        if (!$this->guard->session()->validateAndConsumeCsrfToken('tenant_sales_case_status_update', $token)) {
            $this->guard->session()->setFlash('error', '不正な更新要求を検出しました。');
            Responses::redirect($this->settingsRedirectUrl('sales-case-status'));
        }

        $id          = (int) ($_POST['id'] ?? 0);
        $name        = trim((string) ($_POST['name'] ?? ''));
        $isCompleted = (($_POST['is_completed'] ?? '0') === '1' ? 1 : 0);

        if ($id <= 0 || $name === '') {
            $this->guard->session()->setFlash('error', '表示名は必須です。');
            Responses::redirect($this->settingsRedirectUrl('sales-case-status'));
        }
        if (mb_strlen($name) > 50) {
            $this->guard->session()->setFlash('error', '表示名は50文字以内で入力してください。');
            Responses::redirect($this->settingsRedirectUrl('sales-case-status'));
        }

        try {
            $tenantPdo = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            $repo = new SalesCaseStatusRepository($tenantPdo);
            $repo->updateName($id, $name);
            $repo->setCompleted($id, $isCompleted);
            $this->guard->session()->setFlash('success', '対応状況を更新しました。');
        } catch (\DomainException $e) {
            $this->guard->session()->setFlash('error', $e->getMessage());
        } catch (Throwable) {
            $this->guard->session()->setFlash('error', '対応状況の更新に失敗しました。');
        }

        Responses::redirect($this->settingsRedirectUrl('sales-case-status'));
    }

    public function salesCaseStatusDeactivate(): void
    {
        $auth = $this->guard->requireAuthenticated();
        $this->assertAdmin($auth);

        $token = (string) ($_POST['_csrf_token'] ?? '');
        if (!$this->guard->session()->validateAndConsumeCsrfToken('tenant_sales_case_status_deactivate', $token)) {
            $this->guard->session()->setFlash('error', '不正な更新要求を検出しました。');
            Responses::redirect($this->settingsRedirectUrl('sales-case-status'));
        }

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            $this->guard->session()->setFlash('error', 'IDが指定されていません。');
            Responses::redirect($this->settingsRedirectUrl('sales-case-status'));
        }

        try {
            $tenantPdo = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            $updated   = (new SalesCaseStatusRepository($tenantPdo))->setActive($id, 0);
            if ($updated > 0) {
                $this->guard->session()->setFlash('success', '対応状況を無効化しました。');
            } else {
                $this->guard->session()->setFlash('error', '対象が見つかりません。');
            }
        } catch (Throwable) {
            $this->guard->session()->setFlash('error', '対応状況の無効化に失敗しました。');
        }

        Responses::redirect($this->settingsRedirectUrl('sales-case-status'));
    }

    public function salesCaseStatusActivate(): void
    {
        $auth = $this->guard->requireAuthenticated();
        $this->assertAdmin($auth);

        $token = (string) ($_POST['_csrf_token'] ?? '');
        if (!$this->guard->session()->validateAndConsumeCsrfToken('tenant_sales_case_status_activate', $token)) {
            $this->guard->session()->setFlash('error', '不正な更新要求を検出しました。');
            Responses::redirect($this->settingsRedirectUrl('sales-case-status'));
        }

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            $this->guard->session()->setFlash('error', 'IDが指定されていません。');
            Responses::redirect($this->settingsRedirectUrl('sales-case-status'));
        }

        try {
            $tenantPdo = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            $updated   = (new SalesCaseStatusRepository($tenantPdo))->setActive($id, 1);
            if ($updated > 0) {
                $this->guard->session()->setFlash('success', '対応状況を有効化しました。');
            } else {
                $this->guard->session()->setFlash('error', '対象が見つかりません。');
            }
        } catch (Throwable) {
            $this->guard->session()->setFlash('error', '対応状況の有効化に失敗しました。');
        }

        Responses::redirect($this->settingsRedirectUrl('sales-case-status'));
    }

    public function salesCaseStatusReorder(): void
    {
        $auth = $this->guard->requireAuthenticated();
        $this->assertAdmin($auth);

        $token = (string) ($_POST['_csrf_token'] ?? '');
        if (!$this->guard->session()->validateAndConsumeCsrfToken('tenant_sales_case_status_reorder', $token)) {
            $this->guard->session()->setFlash('error', '不正な更新要求を検出しました。');
            Responses::redirect($this->settingsRedirectUrl('sales-case-status'));
        }

        $id        = (int) ($_POST['id'] ?? 0);
        $direction = trim((string) ($_POST['direction'] ?? ''));
        if ($id <= 0 || !in_array($direction, ['up', 'down'], true)) {
            $this->guard->session()->setFlash('error', '入力値が不正です。');
            Responses::redirect($this->settingsRedirectUrl('sales-case-status'));
        }

        try {
            $tenantPdo = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            (new SalesCaseStatusRepository($tenantPdo))->swapDisplayOrder($id, $direction);
        } catch (Throwable) {
            $this->guard->session()->setFlash('error', '並び順の変更に失敗しました。');
        }

        Responses::redirect($this->settingsRedirectUrl('sales-case-status'));
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
        $name        = trim((string) ($_POST['name'] ?? $_POST['display_name'] ?? ''));
        $actorUserId = (int) ($auth['user_id'] ?? 0);

        if ($csvValue === '' || $name === '') {
            $this->guard->session()->setFlash('error', '種目種類値と表示名は必須です。');
            Responses::redirect($this->settingsRedirectUrl());
        }

        try {
            $tenantPdo = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            (new ProductCategoryRepository($tenantPdo))->create($csvValue, $name, $actorUserId);
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

        $id       = (int) ($_POST['id'] ?? 0);
        $csvValue = trim((string) ($_POST['csv_value'] ?? ''));
        $name     = trim((string) ($_POST['name'] ?? $_POST['display_name'] ?? ''));

        if ($id <= 0 || $csvValue === '' || $name === '') {
            $this->guard->session()->setFlash('error', '種目種類値と表示名は必須です。');
            Responses::redirect($this->settingsRedirectUrl());
        }

        try {
            $tenantPdo = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            (new ProductCategoryRepository($tenantPdo))->update($id, $csvValue, $name);
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

    public function categoryDeactivate(): void
    {
        $auth = $this->guard->requireAuthenticated();
        $this->assertAdmin($auth);

        $token = (string) ($_POST['_csrf_token'] ?? '');
        if (!$this->guard->session()->validateAndConsumeCsrfToken('tenant_category_deactivate', $token)) {
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
            (new ProductCategoryRepository($tenantPdo))->setActive($id, 0);
            $this->guard->session()->setFlash('success', '種目を無効化しました。');
        } catch (Throwable) {
            $this->guard->session()->setFlash('error', '種目の無効化に失敗しました。');
        }

        Responses::redirect($this->settingsRedirectUrl());
    }

    public function categoryActivate(): void
    {
        $auth = $this->guard->requireAuthenticated();
        $this->assertAdmin($auth);

        $token = (string) ($_POST['_csrf_token'] ?? '');
        if (!$this->guard->session()->validateAndConsumeCsrfToken('tenant_category_activate', $token)) {
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
            (new ProductCategoryRepository($tenantPdo))->setActive($id, 1);
            $this->guard->session()->setFlash('success', '種目を有効化しました。');
        } catch (Throwable) {
            $this->guard->session()->setFlash('error', '種目の有効化に失敗しました。');
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
            Responses::redirect($this->settingsRedirectUrl('category'));
        }

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            $this->guard->session()->setFlash('error', 'IDが指定されていません。');
            Responses::redirect($this->settingsRedirectUrl('category'));
        }

        try {
            $tenantPdo = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            (new ProductCategoryRepository($tenantPdo))->delete($id);
            $this->guard->session()->setFlash('success', '種目を削除しました。');
        } catch (Throwable) {
            $this->guard->session()->setFlash('error', '種目の削除に失敗しました。');
        }

        Responses::redirect($this->settingsRedirectUrl('category'));
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
        $accident = $this->normalizeNotifyInput('accident', false);

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
        $accident = $this->normalizeNotifyInput('accident', false);

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

        $accident = $this->normalizeNotifyInput('accident', false);
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
    // ---- ユーザー管理 ----

    public function userUpdate(): void
    {
        $auth = $this->guard->requireAuthenticated();
        $this->assertAdmin($auth);

        $token = (string) ($_POST['_csrf_token'] ?? '');
        if (!$this->guard->session()->validateAndConsumeCsrfToken('tenant_user_update', $token)) {
            $this->guard->session()->setFlash('error', '不正な更新要求を検出しました。');
            Responses::redirect($this->settingsRedirectUrl());
        }

        $userId      = (int) ($_POST['user_id'] ?? 0);
        $displayName = trim((string) ($_POST['display_name'] ?? ''));
        $roleInput   = trim((string) ($_POST['role'] ?? ''));
        $sjnetCode   = trim((string) ($_POST['sjnet_code'] ?? ''));
        $isSales     = isset($_POST['is_sales'])  ? 1 : 0;
        $isOffice    = isset($_POST['is_office']) ? 1 : 0;
        $tenantCode  = (string) ($auth['tenant_code'] ?? '');
        $actorUserId = (int) ($auth['user_id'] ?? 0);

        if ($userId <= 0 || $tenantCode === '') {
            $this->guard->session()->setFlash('error', '入力値が不正です。');
            Responses::redirect($this->settingsRedirectUrl());
        }

        $newRole = in_array($roleInput, ['admin', 'member'], true) ? $roleInput : 'member';

        try {
            $commonPdo = $this->commonConnectionFactory->create();

            // テナント所属確認 & fallback 用 users.name 取得
            $chk = $commonPdo->prepare(
                'SELECT u.name, u.display_name
                 FROM user_tenants ut
                 INNER JOIN users u ON u.id = ut.user_id
                 WHERE ut.user_id = :user_id AND ut.tenant_code = :tenant_code
                   AND ut.status = 1 AND ut.is_deleted = 0 LIMIT 1'
            );
            $chk->bindValue(':user_id', $userId, \PDO::PARAM_INT);
            $chk->bindValue(':tenant_code', $tenantCode);
            $chk->execute();
            $row = $chk->fetch(\PDO::FETCH_ASSOC);
            if ($row === false) {
                $this->guard->session()->setFlash('error', '対象ユーザーが見つかりません。');
                Responses::redirect($this->settingsRedirectUrl());
            }

            $newValue = $displayName !== '' ? $displayName : null;
            $stmt = $commonPdo->prepare(
                'UPDATE users SET display_name = :display_name, updated_by = :updated_by WHERE id = :id'
            );
            $stmt->bindValue(':display_name', $newValue, $newValue !== null ? \PDO::PARAM_STR : \PDO::PARAM_NULL);
            $stmt->bindValue(':updated_by', $actorUserId, \PDO::PARAM_INT);
            $stmt->bindValue(':id', $userId, \PDO::PARAM_INT);
            $stmt->execute();

            $roleStmt = $commonPdo->prepare(
                'UPDATE user_tenants SET role = :role
                 WHERE user_id = :user_id AND tenant_code = :tenant_code
                   AND status = 1 AND is_deleted = 0'
            );
            $roleStmt->bindValue(':role', $newRole);
            $roleStmt->bindValue(':user_id', $userId, \PDO::PARAM_INT);
            $roleStmt->bindValue(':tenant_code', $tenantCode);
            $roleStmt->execute();

            // m_staff を UPSERT（担当者行が無ければ新規作成、あれば業務ロール/代理店コードを更新）
            $tenantPdo = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            $fallbackName = $displayName !== ''
                ? $displayName
                : (string) ($row['name'] ?? '');
            try {
                (new StaffRepository($tenantPdo))->upsertForUser(
                    $userId,
                    $sjnetCode !== '' ? $sjnetCode : null,
                    $isSales,
                    $isOffice,
                    $fallbackName,
                    $actorUserId
                );
            } catch (Throwable $e) {
                $msg = $e->getMessage();
                if (str_contains($msg, 'Duplicate') || str_contains($msg, 'duplicate')) {
                    $this->guard->session()->setFlash('error', '代理店コード "' . $sjnetCode . '" は既に使用されています。');
                    Responses::redirect($this->settingsRedirectUrl());
                }
                throw $e;
            }

            $this->guard->session()->setFlash('success', 'ユーザー設定を更新しました。');
        } catch (Throwable) {
            $this->guard->session()->setFlash('error', 'ユーザー設定の更新に失敗しました。');
        }

        Responses::redirect($this->settingsRedirectUrl());
    }

    // ---- 目標管理 ----

    public function salesTargetSave(): void
    {
        $auth   = $this->guard->requireAuthenticated();
        $this->assertAdmin($auth);
        $userId = (int) ($auth['user_id'] ?? 0);

        $token = (string) ($_POST['_csrf_token'] ?? '');
        if (!$this->guard->session()->validateAndConsumeCsrfToken('tenant_sales_target_save', $token)) {
            $this->guard->session()->setFlash('error', '不正な更新要求を検出しました。');
            Responses::redirect($this->targetRedirectUrl((int) ($_POST['fiscal_year'] ?? 0)));
        }

        $fiscalYear            = (int) ($_POST['fiscal_year'] ?? 0);
        $staffUserIdRaw        = trim((string) ($_POST['staff_user_id'] ?? ''));
        $targetNonLifeRaw      = trim((string) ($_POST['target_amount_nonlife'] ?? ''));
        $targetLifeRaw         = trim((string) ($_POST['target_amount_life'] ?? ''));

        // fiscal_year バリデーション
        if ($fiscalYear < 2020 || $fiscalYear > 2099) {
            $this->guard->session()->setFlash('error', '年度の値が不正です。');
            Responses::redirect($this->targetRedirectUrl($fiscalYear));
        }

        // target_amount バリデーション（損保・生保それぞれ）
        // 空欄は「目標未設定」を意味し、該当 target_type の既存レコードを論理削除する。
        // 値が入っている場合は0以上の整数のみ許容する。
        foreach (['損保目標' => $targetNonLifeRaw, '生保目標' => $targetLifeRaw] as $label => $raw) {
            if ($raw !== '' && !ctype_digit($raw)) {
                $this->guard->session()->setFlash('error', $label . 'には0以上の整数、または空欄（目標未設定）を入力してください。');
                Responses::redirect($this->targetRedirectUrl($fiscalYear));
            }
        }
        $nonLifeIsEmpty = $targetNonLifeRaw === '';
        $lifeIsEmpty    = $targetLifeRaw    === '';
        $targetNonLife  = $nonLifeIsEmpty ? 0 : (int) $targetNonLifeRaw;
        $targetLife     = $lifeIsEmpty    ? 0 : (int) $targetLifeRaw;

        // staff_user_id バリデーション
        $staffUserId = null;
        if ($staffUserIdRaw !== '') {
            $staffUserIdInt = filter_var($staffUserIdRaw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($staffUserIdInt === false) {
                $this->guard->session()->setFlash('error', '担当者の指定が不正です。');
                Responses::redirect($this->targetRedirectUrl($fiscalYear));
            }
            $staffUserId = (int) $staffUserIdInt;

            // テナント所属確認
            $tenantCode = (string) ($auth['tenant_code'] ?? '');
            $commonPdo  = $this->commonConnectionFactory->create();
            $chk = $commonPdo->prepare(
                'SELECT 1 FROM user_tenants ut
                 WHERE ut.user_id = :user_id AND ut.tenant_code = :tenant_code
                   AND ut.is_deleted = 0 AND ut.status = 1
                 LIMIT 1'
            );
            $chk->bindValue(':user_id',     $staffUserId, \PDO::PARAM_INT);
            $chk->bindValue(':tenant_code', $tenantCode);
            $chk->execute();
            if (!$chk->fetchColumn()) {
                $this->guard->session()->setFlash('error', '指定された担当者はこのテナントに所属していません。');
                Responses::redirect($this->targetRedirectUrl($fiscalYear));
            }
        }

        try {
            $tenantPdo  = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            $commonPdo  = $commonPdo ?? $this->commonConnectionFactory->create();
            $tenantCode = (string) ($auth['tenant_code'] ?? '');
            $repo       = new SalesTargetRepository($tenantPdo, $commonPdo, $tenantCode);

            $tenantPdo->beginTransaction();
            try {
                if ($nonLifeIsEmpty) {
                    $repo->deleteYearlyTargetByType($fiscalYear, $staffUserId, 'premium_non_life', $userId);
                } else {
                    $repo->upsertYearlyTarget($fiscalYear, $staffUserId, 'premium_non_life', $targetNonLife, $userId);
                }
                if ($lifeIsEmpty) {
                    $repo->deleteYearlyTargetByType($fiscalYear, $staffUserId, 'premium_life', $userId);
                } else {
                    $repo->upsertYearlyTarget($fiscalYear, $staffUserId, 'premium_life',     $targetLife,    $userId);
                }
                $tenantPdo->commit();
            } catch (Throwable $e) {
                if ($tenantPdo->inTransaction()) {
                    $tenantPdo->rollBack();
                }
                throw $e;
            }
            $this->guard->session()->setFlash('success', '目標を保存しました。');
        } catch (Throwable) {
            $this->guard->session()->setFlash('error', '目標の保存に失敗しました。');
        }

        Responses::redirect($this->targetRedirectUrl($fiscalYear));
    }

    /**
     * 担当者別目標を一括保存する。
     * 画面上の「担当者別目標」テーブル（損保・生保の入力欄を担当者ごとに持つ単一フォーム）からの POST を受け取る。
     * 入力欄は `staff_targets[{uid}][nonlife]` / `staff_targets[{uid}][life]` の形式。
     * 空欄は「目標未設定」を意味し、該当 target_type の既存レコードを論理削除する。
     */
    public function salesTargetBulkSave(): void
    {
        $auth   = $this->guard->requireAuthenticated();
        $this->assertAdmin($auth);
        $userId = (int) ($auth['user_id'] ?? 0);

        $token = (string) ($_POST['_csrf_token'] ?? '');
        if (!$this->guard->session()->validateAndConsumeCsrfToken('tenant_sales_target_bulk_save', $token)) {
            $this->guard->session()->setFlash('error', '不正な更新要求を検出しました。');
            Responses::redirect($this->targetRedirectUrl((int) ($_POST['fiscal_year'] ?? 0)));
        }

        $fiscalYear = (int) ($_POST['fiscal_year'] ?? 0);
        if ($fiscalYear < 2020 || $fiscalYear > 2099) {
            $this->guard->session()->setFlash('error', '年度の値が不正です。');
            Responses::redirect($this->targetRedirectUrl($fiscalYear));
        }

        $rawTargets = $_POST['staff_targets'] ?? [];
        if (!is_array($rawTargets)) {
            $this->guard->session()->setFlash('error', '入力形式が不正です。');
            Responses::redirect($this->targetRedirectUrl($fiscalYear));
        }

        // 事前バリデーション: staff_user_id の整合性 + 各値の数値性
        // まず対象テナント所属ユーザー一覧を取得し、送信された staff_user_id が全員テナント所属か確認する。
        $tenantCode = (string) ($auth['tenant_code'] ?? '');
        $commonPdo  = $this->commonConnectionFactory->create();
        $tenantPdo  = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
        $repo       = new SalesTargetRepository($tenantPdo, $commonPdo, $tenantCode);
        $assignableUsers = $repo->fetchAssignableUsers();
        $allowedUserIds  = [];
        foreach ($assignableUsers as $u) {
            $allowedUserIds[(int) $u['user_id']] = true;
        }

        /** @var array<int, array{nonlife: string, life: string, nonlife_empty: bool, life_empty: bool, nonlife_val: int, life_val: int}> $entries */
        $entries = [];
        foreach ($rawTargets as $uidKey => $pair) {
            $uid = filter_var((string) $uidKey, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($uid === false || !isset($allowedUserIds[$uid])) {
                $this->guard->session()->setFlash('error', '担当者の指定が不正です。');
                Responses::redirect($this->targetRedirectUrl($fiscalYear));
            }
            if (!is_array($pair)) {
                $this->guard->session()->setFlash('error', '入力形式が不正です。');
                Responses::redirect($this->targetRedirectUrl($fiscalYear));
            }
            $nlRaw = trim((string) ($pair['nonlife'] ?? ''));
            $lfRaw = trim((string) ($pair['life']    ?? ''));

            foreach (['損保目標' => $nlRaw, '生保目標' => $lfRaw] as $label => $raw) {
                if ($raw !== '' && !ctype_digit($raw)) {
                    $this->guard->session()->setFlash('error', $label . 'には0以上の整数、または空欄（目標未設定）を入力してください。');
                    Responses::redirect($this->targetRedirectUrl($fiscalYear));
                }
            }

            $entries[$uid] = [
                'nonlife'       => $nlRaw,
                'life'          => $lfRaw,
                'nonlife_empty' => $nlRaw === '',
                'life_empty'    => $lfRaw === '',
                'nonlife_val'   => $nlRaw === '' ? 0 : (int) $nlRaw,
                'life_val'      => $lfRaw === '' ? 0 : (int) $lfRaw,
            ];
        }

        try {
            $tenantPdo->beginTransaction();
            try {
                foreach ($entries as $uid => $e) {
                    if ($e['nonlife_empty']) {
                        $repo->deleteYearlyTargetByType($fiscalYear, $uid, 'premium_non_life', $userId);
                    } else {
                        $repo->upsertYearlyTarget($fiscalYear, $uid, 'premium_non_life', $e['nonlife_val'], $userId);
                    }
                    if ($e['life_empty']) {
                        $repo->deleteYearlyTargetByType($fiscalYear, $uid, 'premium_life', $userId);
                    } else {
                        $repo->upsertYearlyTarget($fiscalYear, $uid, 'premium_life', $e['life_val'], $userId);
                    }
                }
                $tenantPdo->commit();
            } catch (Throwable $e) {
                if ($tenantPdo->inTransaction()) {
                    $tenantPdo->rollBack();
                }
                throw $e;
            }
            $this->guard->session()->setFlash('success', '担当者別目標を保存しました。');
        } catch (Throwable) {
            $this->guard->session()->setFlash('error', '担当者別目標の保存に失敗しました。');
        }

        Responses::redirect($this->targetRedirectUrl($fiscalYear));
    }

    public function salesTargetDelete(): void
    {
        $auth   = $this->guard->requireAuthenticated();
        $this->assertAdmin($auth);
        $userId = (int) ($auth['user_id'] ?? 0);

        $token = (string) ($_POST['_csrf_token'] ?? '');
        if (!$this->guard->session()->validateAndConsumeCsrfToken('tenant_sales_target_delete', $token)) {
            $this->guard->session()->setFlash('error', '不正な更新要求を検出しました。');
            Responses::redirect($this->targetRedirectUrl((int) ($_POST['fiscal_year'] ?? 0)));
        }

        $fiscalYear     = (int) ($_POST['fiscal_year'] ?? 0);
        $staffUserIdRaw = trim((string) ($_POST['staff_user_id'] ?? ''));

        if ($fiscalYear < 2020 || $fiscalYear > 2099) {
            $this->guard->session()->setFlash('error', '年度の値が不正です。');
            Responses::redirect($this->targetRedirectUrl($fiscalYear));
        }

        $staffUserId = $staffUserIdRaw !== '' ? (int) $staffUserIdRaw : null;

        try {
            $tenantPdo  = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            $commonPdo  = $this->commonConnectionFactory->create();
            $tenantCode = (string) ($auth['tenant_code'] ?? '');
            (new SalesTargetRepository($tenantPdo, $commonPdo, $tenantCode))
                ->deleteYearlyTargetsAllTypes($fiscalYear, $staffUserId, $userId);
            $this->guard->session()->setFlash('success', '目標を削除しました。');
        } catch (Throwable) {
            $this->guard->session()->setFlash('error', '目標の削除に失敗しました。');
        }

        Responses::redirect($this->targetRedirectUrl($fiscalYear));
    }

    private function targetRedirectUrl(int $fiscalYear): string
    {
        $base = $this->config->routeUrl('tenant/settings');
        $fy   = ($fiscalYear >= 2020 && $fiscalYear <= 2099) ? $fiscalYear : $this->getCurrentFiscalYear();
        return $base . '&tab=target&target_fy=' . $fy;
    }

    private function getCurrentFiscalYear(): int
    {
        $now   = new \DateTimeImmutable();
        $year  = (int) $now->format('Y');
        $month = (int) $now->format('n');
        return $month >= 4 ? $year : $year - 1;
    }

    private function settingsRedirectUrl(string $tabFallback = ''): string
    {
        $tab = trim((string) ($_POST['_tab'] ?? ''));
        if ($tab === '') {
            $tab = $tabFallback;
        }
        $url = $this->config->routeUrl('tenant/settings');
        return $tab !== '' ? $url . '&tab=' . urlencode($tab) : $url;
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
    private function normalizeNotifyInput(string $prefix, bool $requireWebhookWhenEnabled = true): ?array
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
        if ($requireWebhookWhenEnabled && $isEnabled === 1 && $webhookUrl === '') {
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

}
