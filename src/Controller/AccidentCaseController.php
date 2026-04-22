<?php
declare(strict_types=1);

namespace App\Controller;

use App\AppConfig;
use App\Domain\Accident\AccidentCaseRepository;
use App\Domain\Tenant\CaseStatusRepository;
use App\Domain\Tenant\StaffRepository;
use App\Http\Responses;
use App\Infra\CommonConnectionFactory;
use App\Infra\TenantConnectionFactory;
use App\Presentation\AccidentCaseDetailView;
use App\Presentation\AccidentCaseListView;
use App\Presentation\View\ListViewHelper;
use App\Security\AuthGuard;
use PDO;
use Throwable;

final class AccidentCaseController
{
    private const ALLOWED_PRIORITY = ['low', 'normal', 'high'];

    public function __construct(
        private AuthGuard $guard,
        private TenantConnectionFactory $tenantConnectionFactory,
        private CommonConnectionFactory $commonConnectionFactory,
        private AppConfig $config
    ) {
    }

    public function list(): void
    {
        $auth = $this->guard->requireAuthenticated();

        $criteria = $this->extractCriteria($_GET);
        $listState = $this->extractListState($_GET);

        $rows = [];
        $total = 0;
        $customerOptions = [];
        $accidentStatuses = [];
        $error = null;

        try {
            $pdo = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            $repository = new AccidentCaseRepository($pdo);
            $result = $repository->searchPage(
                $criteria,
                (int) $listState['page'],
                (int) $listState['per_page'],
                (string) $listState['sort'],
                (string) $listState['direction']
            );
            $rows = $result['rows'];
            $total = (int) ($result['total'] ?? 0);
            $listState['page'] = (string) ($result['page'] ?? $listState['page']);
            $customerOptions = $repository->fetchCustomers(500);
            $accidentStatuses = (new CaseStatusRepository($pdo))->findByType('accident');

            // 次回リマインド日を付与
            $caseIds = array_map(fn($r) => (int) ($r['id'] ?? 0), $rows);
            $caseIds = array_filter($caseIds, fn($id) => $id > 0);
            if ($caseIds !== []) {
                $reminderDates = $repository->findNextReminderDates(array_values($caseIds));
                foreach ($rows as $idx => $row) {
                    $caseId = (int) ($row['id'] ?? 0);
                    $rows[$idx]['next_reminder_date'] = $reminderDates[$caseId] ?? null;
                }
            }
        } catch (Throwable) {
            $error = '事故案件一覧の取得に失敗しました。時間をおいて再度お試しください。解消しない場合は管理者へご連絡ください。';
        }

        try {
            $staffPdo = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            $staffUsers = (new StaffRepository($staffPdo))->findActive();
        } catch (\Throwable) {
            $staffUsers = [];
        }
        $createDraft = $this->consumeCreateDraft();
        $openModal = trim((string) ($_GET['open_modal'] ?? ''));
        if ($openModal !== 'create') {
            $openModal = '';
        }
        if ($createDraft !== null && $openModal === '') {
            $openModal = 'create';
        }

        $flashError = $this->guard->session()->consumeFlash('error');
        $flashSuccess = $this->guard->session()->consumeFlash('success');

        Responses::html(AccidentCaseListView::render(
            $rows,
            $total,
            $criteria,
            $listState,
            $this->config->routeUrl('accident/list'),
            $this->config->routeUrl('accident/detail'),
            $this->config->routeUrl('accident/store'),
            $this->guard->session()->issueCsrfToken('accident_create'),
            $this->config->routeUrl('accident/delete'),
            $this->guard->session()->issueCsrfToken('accident_delete'),
            $createDraft,
            $openModal,
            $customerOptions,
            $staffUsers,
            [
                'id' => (int) ($auth['user_id'] ?? 0),
                'name' => (string) ($auth['display_name'] ?? 'ログインユーザー'),
                'default_branch' => (string) ($auth['tenant_name'] ?? ''),
            ],
            $error,
            $flashError,
            $flashSuccess,
            (string) ($_GET['filter_open'] ?? '') === '1',
            ControllerLayoutHelper::build($this->guard, $this->config, 'accident'),
            $accidentStatuses
        ));
    }

    public function detail(): void
    {
        $auth = $this->guard->requireAuthenticated();
        $criteria = $this->extractCriteria($_GET);
        $listState = $this->extractListState($_GET);
        $listUrl = $this->listUrl($criteria, $listState);

        $id = (int) ($_GET['id'] ?? 0);
        if ($id <= 0) {
            $this->guard->session()->setFlash('error', '案件IDが不正です。');
            Responses::redirect($listUrl);
        }

        try {
            $pdo = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            $repository = new AccidentCaseRepository($pdo);
            $detail = $repository->findDetailById($id);
            if ($detail === null) {
                $this->guard->session()->setFlash('error', '対象案件が見つかりません。');
                Responses::redirect($listUrl);
            }

            $detailUrl = ListViewHelper::buildUrl(
                $this->config->routeUrl('accident/detail'),
                array_merge(['id' => (string) $id], $this->buildListQuery($criteria, $listState))
            );

            $comments = $repository->findComments($id, 30);
            $authorNames = $this->fetchUserNamesByRows($comments, 'created_by');
            $comments = $this->attachAuthorNames($comments, $authorNames);
            $audits = $repository->findAuditEvents($id, 30);
            $auditUserNames = $this->fetchUserNamesByRows($audits, 'changed_by');
            $audits = $this->attachAuditUserNames($audits, $auditUserNames);
            $assignedUsers = (new StaffRepository($pdo))->findActive();
            $accidentStatuses = (new CaseStatusRepository($pdo))->findByType('accident');
            $reminderRule = $repository->findReminderRuleByAccidentCaseId($id);
            $customers = $repository->fetchCustomers(500);
            $flashError = $this->guard->session()->consumeFlash('error');
            $flashSuccess = $this->guard->session()->consumeFlash('success');

            Responses::html(AccidentCaseDetailView::render(
                $detail,
                $comments,
                $audits,
                $assignedUsers,
                $this->config->routeUrl('accident/update'),
                $this->config->routeUrl('accident/comment'),
                $this->config->routeUrl('accident/reminder'),
                $this->guard->session()->issueCsrfToken('accident_update_' . $id),
                $this->guard->session()->issueCsrfToken('accident_comment_' . $id),
                $this->guard->session()->issueCsrfToken('accident_reminder_' . $id),
                $detailUrl,
                $this->config->routeUrl('customer/detail'),
                $this->config->routeUrl('renewal/detail'),
                $flashError,
                $flashSuccess,
                ControllerLayoutHelper::build(
                    $this->guard,
                    $this->config,
                    'accident',
                    [
                        ['label' => 'ホーム', 'url' => $this->config->routeUrl('dashboard')],
                        ['label' => '事故案件一覧', 'url' => $listUrl],
                        ['label' => '事故案件詳細'],
                    ]
                ),
                $accidentStatuses,
                $reminderRule,
                $customers,
                $this->config->routeUrl('accident/update_basic'),
                $this->guard->session()->issueCsrfToken('accident_update_basic_' . $id)
            ));
        } catch (Throwable) {
            $this->guard->session()->setFlash('error', '事故案件詳細の取得に失敗しました。');
            Responses::redirect($listUrl);
        }
    }

    public function update(): void
    {
        $auth = $this->guard->requireAuthenticated();

        $id = (int) ($_POST['id'] ?? 0);
        $returnTo = $this->validateReturnTo($_POST['return_to'] ?? null, $id);
        if ($id <= 0) {
            $this->guard->session()->setFlash('error', '案件IDが不正です。');
            Responses::redirect($this->config->routeUrl('accident/list'));
        }

        $token = (string) ($_POST['_csrf_token'] ?? '');
        if (!$this->guard->session()->validateAndConsumeCsrfToken('accident_update_' . $id, $token)) {
            $this->guard->session()->setFlash('error', '不正な更新要求を検出しました。');
            Responses::redirect($returnTo);
        }

        $status = trim((string) ($_POST['status'] ?? ''));
        $priority = trim((string) ($_POST['priority'] ?? ''));

        try {
            $pdo = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            $allowedStatuses = (new CaseStatusRepository($pdo))->activeNames('accident');
        } catch (\Throwable) {
            $allowedStatuses = [];
            $pdo = null;
        }

        if ($allowedStatuses === [] || !in_array($status, $allowedStatuses, true) || !in_array($priority, self::ALLOWED_PRIORITY, true)) {
            $this->guard->session()->setFlash('error', '状態または優先度が不正です。');
            Responses::redirect($returnTo);
        }

        $input = [
            'status' => $status,
            'priority' => $priority,
            'assigned_staff_id' => $this->nullableInt($_POST['assigned_staff_id'] ?? null),
            'resolved_date' => $this->nullableDate($_POST['resolved_date'] ?? null),
        ];

        try {
            if ($pdo === null) {
                $pdo = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            }
            $repository = new AccidentCaseRepository($pdo);
            $updated = $repository->updateAccidentCase($id, $input, (int) ($auth['user_id'] ?? 0));
            if ($updated > 0) {
                $this->guard->session()->setFlash('success', '事故案件を更新しました。');
            } else {
                $this->guard->session()->setFlash('error', '更新対象が見つからないか、変更がありません。');
            }
        } catch (Throwable) {
            $this->guard->session()->setFlash('error', '事故案件の更新に失敗しました。');
        }

        Responses::redirect($returnTo);
    }

    public function updateBasic(): void
    {
        $auth = $this->guard->requireAuthenticated();

        $id = (int) ($_POST['id'] ?? 0);
        $returnTo = $this->validateReturnTo($_POST['return_to'] ?? null, $id);
        if ($id <= 0) {
            $this->guard->session()->setFlash('error', '案件IDが不正です。');
            Responses::redirect($this->config->routeUrl('accident/list'));
        }

        $token = (string) ($_POST['_csrf_token'] ?? '');
        if (!$this->guard->session()->validateAndConsumeCsrfToken('accident_update_basic_' . $id, $token)) {
            $this->guard->session()->setFlash('error', '不正な更新要求を検出しました。');
            Responses::redirect($returnTo);
        }

        $acceptedDate = trim((string) ($_POST['accepted_date'] ?? ''));
        $customerId   = $this->nullableInt($_POST['customer_id'] ?? null);
        $prospectName = $this->nullableText($_POST['prospect_name'] ?? null);
        if ($acceptedDate === '') {
            $this->guard->session()->setFlash('error', '受付日は必須です。');
            Responses::redirect($returnTo);
        }
        if ($customerId === null && $prospectName === null) {
            $this->guard->session()->setFlash('error', 'お客さま（既存顧客または依頼者名）を入力してください。');
            Responses::redirect($returnTo);
        }
        // 既存顧客が選択されている場合は prospect_name を消す（customer_id 優先）
        if ($customerId !== null) {
            $prospectName = null;
        }

        $input = [
            'customer_id'        => $customerId,
            'prospect_name'      => $prospectName,
            'accepted_date'      => $acceptedDate,
            'accident_date'      => $this->nullableDate($_POST['accident_date'] ?? null),
            'insurance_category' => $this->nullableText($_POST['insurance_category'] ?? null),
            'accident_location'  => $this->nullableText($_POST['accident_location'] ?? null),
            'sc_staff_name'      => $this->nullableText($_POST['sc_staff_name'] ?? null),
        ];

        try {
            $pdo = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            $repository = new AccidentCaseRepository($pdo);
            $repository->updateBasicInfo($id, $input, (int) ($auth['user_id'] ?? 0));
            $this->guard->session()->setFlash('success', '基本情報を更新しました。');
        } catch (Throwable) {
            $this->guard->session()->setFlash('error', '基本情報の更新に失敗しました。');
        }

        Responses::redirect($returnTo);
    }

    public function comment(): void
    {
        $auth = $this->guard->requireAuthenticated();

        $id = (int) ($_POST['id'] ?? 0);
        $returnTo = $this->validateReturnTo($_POST['return_to'] ?? null, $id);
        if ($id <= 0) {
            $this->guard->session()->setFlash('error', '案件IDが不正です。');
            Responses::redirect($this->config->routeUrl('accident/list'));
        }

        $token = (string) ($_POST['_csrf_token'] ?? '');
        if (!$this->guard->session()->validateAndConsumeCsrfToken('accident_comment_' . $id, $token)) {
            $this->guard->session()->setFlash('error', '不正なコメント要求を検出しました。');
            Responses::redirect($returnTo);
        }

        $commentBody = trim((string) ($_POST['comment_body'] ?? ''));
        if ($commentBody === '') {
            $this->guard->session()->setFlash('error', 'コメント本文を入力してください。');
            Responses::redirect($returnTo);
        }
        if (mb_strlen($commentBody) > 500) {
            $this->guard->session()->setFlash('error', 'コメントは500文字以内で入力してください。');
            Responses::redirect($returnTo);
        }

        try {
            $pdo = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            $repository = new AccidentCaseRepository($pdo);
            $repository->createComment($id, $commentBody, (int) ($auth['user_id'] ?? 0));
            $this->guard->session()->setFlash('success', 'コメントを登録しました。');
        } catch (Throwable) {
            $this->guard->session()->setFlash('error', 'コメントの登録に失敗しました。');
        }

        Responses::redirect($returnTo);
    }

    public function reminder(): void
    {
        $auth = $this->guard->requireAuthenticated();

        $id = (int) ($_POST['id'] ?? 0);
        $returnTo = $this->validateReturnTo($_POST['return_to'] ?? null, $id);
        if ($id <= 0) {
            $this->guard->session()->setFlash('error', '案件IDが不正です。');
            Responses::redirect($this->config->routeUrl('accident/list'));
        }

        $token = (string) ($_POST['_csrf_token'] ?? '');
        if (!$this->guard->session()->validateAndConsumeCsrfToken('accident_reminder_' . $id, $token)) {
            $this->guard->session()->setFlash('error', '不正な更新要求を検出しました。');
            Responses::redirect($returnTo);
        }

        $isEnabled     = (int) ($_POST['is_enabled'] ?? 0);
        $intervalWeeks = (int) ($_POST['interval_weeks'] ?? 1);
        $weekdays      = array_map('intval', (array) ($_POST['weekdays'] ?? []));
        $startDate     = $this->nullableDate($_POST['start_date'] ?? null);
        $endDate       = $this->nullableDate($_POST['end_date'] ?? null);

        if ($intervalWeeks < 1) {
            $this->guard->session()->setFlash('error', '通知間隔は1以上の整数で入力してください。');
            Responses::redirect($returnTo);
        }
        if ($isEnabled === 1 && $weekdays === []) {
            $this->guard->session()->setFlash('error', '通知曜日を1つ以上選択してください。');
            Responses::redirect($returnTo);
        }
        if ($startDate !== null && $endDate !== null && $endDate < $startDate) {
            $this->guard->session()->setFlash('error', '通知終了日は通知開始日以降の日付を入力してください。');
            Responses::redirect($returnTo);
        }

        try {
            $pdo = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            $repository = new AccidentCaseRepository($pdo);
            $repository->saveReminderRule($id, [
                'is_enabled'     => $isEnabled,
                'interval_weeks' => $intervalWeeks,
                'weekdays'       => $weekdays,
                'start_date'     => $startDate,
                'end_date'       => $endDate,
            ], (int) ($auth['user_id'] ?? 0));
            $this->guard->session()->setFlash('success', 'リマインド設定を保存しました。');
        } catch (Throwable) {
            $this->guard->session()->setFlash('error', 'リマインド設定の保存に失敗しました。');
        }

        Responses::redirect($returnTo);
    }

    public function delete(): void
    {
        $auth = $this->guard->requireAuthenticated();

        $criteria  = $this->extractCriteria($_POST);
        $listState = $this->extractListState($_POST);

        $accidentCaseId = (int) ($_POST['id'] ?? 0);
        $token = (string) ($_POST['_csrf_token'] ?? '');
        if (!$this->guard->session()->validateAndConsumeCsrfToken('accident_delete', $token)) {
            $this->guard->session()->setFlash('error', '不正な削除要求を検出しました。');
            Responses::redirect($this->listUrl($criteria, $listState));
        }

        if ($accidentCaseId <= 0) {
            $this->guard->session()->setFlash('error', '案件IDが不正です。');
            Responses::redirect($this->listUrl($criteria, $listState));
        }

        try {
            $pdo = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            $deleted = (new AccidentCaseRepository($pdo))->softDelete($accidentCaseId, (int) ($auth['user_id'] ?? 0));
            if ($deleted) {
                $this->guard->session()->setFlash('success', '事故案件を削除しました。');
            } else {
                $this->guard->session()->setFlash('error', '対象案件が見つからないか既に削除されています。');
            }
        } catch (Throwable) {
            $this->guard->session()->setFlash('error', '削除に失敗しました。');
        }

        Responses::redirect($this->listUrl($criteria, $listState));
    }

    public function store(): void
    {
        $auth = $this->guard->requireAuthenticated();
        $returnTo = $this->validateStoreReturnTo($_POST['return_to'] ?? null);

        $token = (string) ($_POST['_csrf_token'] ?? '');
        if (!$this->guard->session()->validateAndConsumeCsrfToken('accident_create', $token)) {
            $this->guard->session()->setFlash('error', '不正な登録要求を検出しました。');
            Responses::redirect(ListViewHelper::buildUrl($returnTo, ['open_modal' => 'create']));
        }

        $accidentDate = $this->requiredDate($_POST['accident_date'] ?? null);
        $insuranceCategory = $this->requiredText($_POST['insurance_category'] ?? null);
        $customerId = $this->nullableInt($_POST['customer_id'] ?? null);
        $prospectName = $this->nullableText($_POST['prospect_name'] ?? null);
        $intakeBranch = $this->requiredText($_POST['intake_branch'] ?? null);
        $assignedUserId = $this->requiredInt($_POST['assigned_staff_id'] ?? null);
        $scStaffName = $this->nullableText($_POST['sc_staff_name'] ?? null);
        $status = trim((string) ($_POST['status'] ?? ''));

        try {
            $pdoForStore = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            $allowedStatuses = (new CaseStatusRepository($pdoForStore))->activeNames('accident');
        } catch (\Throwable) {
            $allowedStatuses = [];
            $pdoForStore = null;
        }

        // 既存顧客が選択されている場合は prospect_name を消す（customer_id 優先）
        if ($customerId !== null) {
            $prospectName = null;
        }

        $input = [
            'customer_id' => $customerId,
            'prospect_name' => $prospectName,
            'accepted_date' => $accidentDate,
            'accident_date' => $accidentDate,
            'insurance_category' => $insuranceCategory,
            'accident_location' => $intakeBranch,
            'status' => $status,
            'priority' => 'normal',
            'assigned_staff_id' => $assignedUserId,
            'sc_staff_name' => $scStaffName,
            'remark' => $this->nullableText($_POST['remark'] ?? null),
        ];

        if (
            $accidentDate === null
            || $insuranceCategory === null
            || ($customerId === null && $prospectName === null)
            || $intakeBranch === null
            || $assignedUserId === null
            || !in_array($status, $allowedStatuses, true)
        ) {
            $this->guard->session()->setFlash('error', '事故日・保険種類・お客さま（既存顧客または依頼者名）・担当拠点・担当者・ステータスを入力してください。');
            $this->storeCreateDraft($input);
            Responses::redirect(ListViewHelper::buildUrl($returnTo, ['open_modal' => 'create']));
        }

        try {
            $pdo = $pdoForStore ?? $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            $repository = new AccidentCaseRepository($pdo);
            $newId = $repository->createAccidentCase($input, (int) ($auth['user_id'] ?? 0));
            if ($newId > 0) {
                $this->guard->session()->setFlash('success', '事故案件を登録しました。');
                Responses::redirect($returnTo);
            } else {
                $this->guard->session()->setFlash('error', '事故案件の登録に失敗しました。');
                $this->storeCreateDraft($input);
                Responses::redirect(ListViewHelper::buildUrl($returnTo, ['open_modal' => 'create']));
            }
        } catch (Throwable) {
            $this->guard->session()->setFlash('error', '事故案件の登録に失敗しました。');
            $this->storeCreateDraft($input);
            Responses::redirect(ListViewHelper::buildUrl($returnTo, ['open_modal' => 'create']));
        }
    }

    /**
     * @param array<string, mixed> $source
     * @return array<string, string>
     */
    private function extractCriteria(array $source): array
    {
        $assignedUserId = (int) ($source['assigned_staff_id'] ?? 0);
        return [
            'accepted_date_from' => trim((string) ($source['accepted_date_from'] ?? '')),
            'accepted_date_to'   => trim((string) ($source['accepted_date_to'] ?? '')),
            'customer_name'      => trim((string) ($source['customer_name'] ?? '')),
            'policy_no'          => trim((string) ($source['policy_no'] ?? '')),
            'product_type'       => trim((string) ($source['product_type'] ?? '')),
            'status'             => trim((string) ($source['status'] ?? '')),
            'priority'           => trim((string) ($source['priority'] ?? '')),
            'assigned_staff_id'  => $assignedUserId > 0 ? (string) $assignedUserId : '',
        ];
    }

    /**
     * @param array<string, mixed> $source
     * @return array<string, string>
     */
    private function extractListState(array $source): array
    {
        $sort = ListViewHelper::normalizeSort($source['sort'] ?? '', AccidentCaseRepository::SORTABLE_FIELDS);

        return [
            'page' => (string) ListViewHelper::normalizePage($source['page'] ?? 1),
            'per_page' => (string) ListViewHelper::normalizePerPage($source['per_page'] ?? ListViewHelper::DEFAULT_PER_PAGE),
            'sort' => $sort,
            'direction' => $sort === '' ? 'asc' : ListViewHelper::normalizeDirection($source['direction'] ?? 'asc'),
        ];
    }

    /**
     * @param array<string, string> $criteria
     * @param array<string, string> $listState
     * @return array<string, string>
     */
    private function buildListQuery(array $criteria, array $listState): array
    {
        $params = $criteria;

        if ((int) ($listState['page'] ?? '1') > 1) {
            $params['page'] = (string) $listState['page'];
        }

        if ((int) ($listState['per_page'] ?? (string) ListViewHelper::DEFAULT_PER_PAGE) !== ListViewHelper::DEFAULT_PER_PAGE) {
            $params['per_page'] = (string) $listState['per_page'];
        }

        if (($listState['sort'] ?? '') !== '') {
            $params['sort'] = (string) $listState['sort'];
            $params['direction'] = (string) ($listState['direction'] ?? 'asc');
        }

        return $params;
    }

    /**
     * @param array<string, string> $criteria
     * @param array<string, string> $listState
     */
    private function listUrl(array $criteria, array $listState): string
    {
        return ListViewHelper::buildUrl($this->config->routeUrl('accident/list'), $this->buildListQuery($criteria, $listState));
    }

    private function validateReturnTo(mixed $returnTo, int $id): string
    {
        $default = $this->config->routeUrl('accident/detail') . '&id=' . $id;
        $candidate = trim((string) $returnTo);
        if ($candidate === '' || str_contains($candidate, "\n") || str_contains($candidate, "\r")) {
            return $default;
        }

        return str_contains($candidate, 'route=accident/detail') ? $candidate : $default;
    }

    private function validateStoreReturnTo(mixed $returnTo): string
    {
        $default = $this->config->routeUrl('accident/list');
        $candidate = trim((string) $returnTo);
        if ($candidate === '' || str_contains($candidate, "\n") || str_contains($candidate, "\r")) {
            return $default;
        }

        return str_contains($candidate, 'route=accident/list') ? $candidate : $default;
    }

    /**
     * @param array<string, mixed> $input
     */
    private function storeCreateDraft(array $input): void
    {
        $this->guard->session()->setFlash('accident_create_form_input', json_encode($input, JSON_UNESCAPED_UNICODE) ?: '{}');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function consumeCreateDraft(): ?array
    {
        $raw = (string) $this->guard->session()->consumeFlash('accident_create_form_input');
        if ($raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }

    private function nullableInt(mixed $value): ?int
    {
        $text = trim((string) $value);
        if ($text === '' || !ctype_digit($text)) {
            return null;
        }

        return (int) $text;
    }

    private function nullableDate(mixed $value): ?string
    {
        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $text)) {
            return null;
        }

        return $text;
    }

    private function requiredDate(mixed $value): ?string
    {
        $text = trim((string) $value);
        if ($text === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $text)) {
            return null;
        }

        return $text;
    }

    private function requiredText(mixed $value): ?string
    {
        $text = trim((string) $value);
        return $text === '' ? null : $text;
    }

    private function requiredInt(mixed $value): ?int
    {
        $text = trim((string) $value);
        if ($text === '' || !ctype_digit($text)) {
            return null;
        }

        $int = (int) $text;
        return $int > 0 ? $int : null;
    }

    private function nullableText(mixed $value): ?string
    {
        $text = trim((string) $value);
        return $text === '' ? null : $text;
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
            $this->guard->session()->setFlash('error', 'この操作を実行する権限がありません。');
            Responses::redirect($this->config->routeUrl('dashboard'));
        }
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, string>
     */
    private function fetchUserNamesByRows(array $rows, string $key): array
    {
        $userIds = [];
        foreach ($rows as $row) {
            $userId = (int) ($row[$key] ?? 0);
            if ($userId > 0) {
                $userIds[$userId] = true;
            }
        }

        return $this->fetchUserNames(array_keys($userIds));
    }

    /**
     * @param array<int, int> $ids
     * @return array<int, string>
     */
    private function fetchUserNames(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($ids), '?'));
        $pdo = $this->commonConnectionFactory->create();
        $stmt = $pdo->prepare(
            'SELECT id, COALESCE(NULLIF(display_name, ""), name) AS name
             FROM users
             WHERE id IN (' . $placeholders . ')
               AND status = 1
               AND is_deleted = 0'
        );
        foreach ($ids as $index => $id) {
            $stmt->bindValue($index + 1, $id, PDO::PARAM_INT);
        }
        $stmt->execute();

        $names = [];
        $rows = $stmt->fetchAll();
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
    }

    /**
     * @param array<int, array<string, mixed>> $comments
     * @param array<int, string> $authorNames
     * @return array<int, array<string, mixed>>
     */
    private function attachAuthorNames(array $comments, array $authorNames): array
    {
        foreach ($comments as $index => $row) {
            $authorId = (int) ($row['created_by'] ?? 0);
            $comments[$index]['author_name'] = $authorNames[$authorId] ?? '不明なユーザー';
        }

        return $comments;
    }

    /**
     * @param array<int, array<string, mixed>> $audits
     * @param array<int, string> $userNames
     * @return array<int, array<string, mixed>>
     */
    private function attachAuditUserNames(array $audits, array $userNames): array
    {
        foreach ($audits as $index => $row) {
            $changedBy = (int) ($row['changed_by'] ?? 0);
            $audits[$index]['changed_by_name'] = $userNames[$changedBy] ?? '不明なユーザー';
        }

        return $audits;
    }
}
