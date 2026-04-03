<?php
declare(strict_types=1);

namespace App\Controller;

use App\AppConfig;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\DailyReportRepository;
use App\Domain\Tenant\ActivityPurposeTypeRepository;
use App\Http\Responses;
use App\Infra\CommonConnectionFactory;
use App\Infra\TenantConnectionFactory;
use App\Presentation\ActivityDailyView;
use App\Presentation\ActivityDetailView;
use App\Presentation\ActivityListView;
use App\Presentation\View\ListViewHelper;
use App\Security\AuthGuard;
use DateTimeImmutable;
use PDO;
use Throwable;

final class ActivityController
{
    private const ALLOWED_ACTIVITY_TYPES = [
        'visit'  => '訪問',
        'call'   => '電話',
        'email'  => 'メール',
        'online' => 'オンライン',
        'other'  => 'その他',
    ];

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

        $today   = (new DateTimeImmutable())->format('Y-m-d');
        $isAdmin = $this->isAdmin($auth);
        $criteria  = $this->extractCriteria($_GET, (int) ($auth['user_id'] ?? 0), $today, $isAdmin);
        $listState = $this->extractListState($_GET);
        $listUrl = $this->listUrl($criteria, $listState);

        $rows = [];
        $total = 0;
        $customers = [];
        $staffUsers = [];
        $error = null;

        try {
            $pdo = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            $repository = new ActivityRepository($pdo);
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
            $listUrl = $this->listUrl($criteria, $listState);
            $customers = $repository->fetchCustomers(500);
            $staffUsers = $this->fetchAssignableUsers((string) ($auth['tenant_code'] ?? ''));
            $staffUserNames = $this->buildUserNameMap($staffUsers);
            $rows = $this->attachStaffNamesToRows($rows, $staffUserNames);
        } catch (Throwable) {
            $error = '活動一覧の取得に失敗しました。接続設定を確認してください。';
        }

        $flashError = $this->guard->session()->consumeFlash('error');
        $flashSuccess = $this->guard->session()->consumeFlash('success');

        Responses::html(ActivityListView::render(
            $rows,
            $total,
            $criteria,
            $listState,
            $staffUsers,
            $listUrl,
            $this->config->routeUrl('activity/new'),
            $this->config->routeUrl('activity/detail'),
            $this->config->routeUrl('activity/daily'),
            $this->config->routeUrl('customer/detail'),
            $flashError,
            $flashSuccess,
            $error,
            self::ALLOWED_ACTIVITY_TYPES,
            $isAdmin,
            (string) ($_GET['filter_open'] ?? '') === '1',
            ControllerLayoutHelper::build($this->guard, $this->config, 'activity')
        ));
    }

    public function newForm(): void
    {
        $auth = $this->guard->requireAuthenticated();

        $listUrl = $this->listUrlFromGet();
        $customers    = [];
        $staffUsers   = [];
        $purposeTypes = [];
        $error        = null;

        $prefill = [
            'customer_id'    => trim((string) ($_GET['customer_id'] ?? '')),
            'activity_date'  => (new DateTimeImmutable())->format('Y-m-d'),
            'staff_user_id'  => (string) ($auth['user_id'] ?? ''),
        ];

        $draft = $this->consumeCreateDraft();
        if ($draft !== null) {
            $prefill = array_merge($prefill, (array) $draft);
        }

        try {
            $pdo = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            $repository = new ActivityRepository($pdo);
            $customers    = $repository->fetchCustomers(500);
            $staffUsers   = $this->fetchAssignableUsers((string) ($auth['tenant_code'] ?? ''));
            $purposeTypes = (new ActivityPurposeTypeRepository($pdo))->findAll();
        } catch (Throwable) {
            $error = '顧客・担当者情報の取得に失敗しました。';
        }

        $flashError = $this->guard->session()->consumeFlash('error');

        Responses::html(ActivityDetailView::renderNew(
            $prefill,
            $customers,
            $staffUsers,
            [],
            $listUrl,
            $this->config->routeUrl('activity/store'),
            $this->guard->session()->issueCsrfToken('activity_store'),
            $flashError,
            $error,
            self::ALLOWED_ACTIVITY_TYPES,
            ControllerLayoutHelper::build(
                $this->guard,
                $this->config,
                'activity',
                [
                    ['label' => 'ホーム', 'url' => $this->config->routeUrl('dashboard')],
                    ['label' => '活動一覧', 'url' => $listUrl],
                    ['label' => '活動登録'],
                ]
            ),
            $purposeTypes
        ));
    }

    public function detail(): void
    {
        $auth = $this->guard->requireAuthenticated();

        $id = (int) ($_GET['id'] ?? 0);
        $listUrl = $this->listUrlFromGet();

        if ($id <= 0) {
            $this->guard->session()->setFlash('error', '活動IDが不正です。');
            Responses::redirect($listUrl);
        }

        $record       = null;
        $customers    = [];
        $staffUsers   = [];
        $purposeTypes = [];
        $error        = null;

        try {
            $pdo = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            $repository = new ActivityRepository($pdo);
            $record = $repository->findById($id);
            if ($record === null) {
                $this->guard->session()->setFlash('error', '対象の活動が見つかりません。');
                Responses::redirect($listUrl);
            }
            $customers    = $repository->fetchCustomers(500);
            $staffUsers   = $this->fetchAssignableUsers((string) ($auth['tenant_code'] ?? ''));
            $staffUserNames = $this->buildUserNameMap($staffUsers);
            $record['staff_name'] = $staffUserNames[(int) ($record['staff_user_id'] ?? 0)] ?? '';
            $purposeTypes = (new ActivityPurposeTypeRepository($pdo))->findAll();

            $editDraft = $this->consumeEditDraft();
            if ($editDraft !== null && (int) ($editDraft['id'] ?? 0) === $id && is_array($editDraft['input'] ?? null)) {
                $record = array_merge($record, (array) $editDraft['input']);
            }
        } catch (Throwable) {
            $error = '活動詳細の取得に失敗しました。接続設定を確認してください。';
        }

        $flashError   = $this->guard->session()->consumeFlash('error');
        $flashSuccess = $this->guard->session()->consumeFlash('success');
        $detailUrl    = $this->detailUrl($id);

        Responses::html(ActivityDetailView::renderDetail(
            $record,
            $customers,
            $staffUsers,
            [],
            $listUrl,
            $detailUrl,
            $this->config->routeUrl('activity/update'),
            $this->config->routeUrl('activity/delete'),
            $this->config->routeUrl('customer/detail'),
            $this->guard->session()->issueCsrfToken('activity_update'),
            $this->guard->session()->issueCsrfToken('activity_delete'),
            $flashError,
            $flashSuccess,
            $error,
            self::ALLOWED_ACTIVITY_TYPES,
            ControllerLayoutHelper::build(
                $this->guard,
                $this->config,
                'activity',
                [
                    ['label' => 'ホーム', 'url' => $this->config->routeUrl('dashboard')],
                    ['label' => '活動一覧', 'url' => $listUrl],
                    ['label' => '活動詳細'],
                ]
            ),
            $purposeTypes
        ));
    }

    public function store(): void
    {
        $auth = $this->guard->requireAuthenticated();
        $returnTo = $this->validateReturnTo($_POST['return_to'] ?? null);
        $token = (string) ($_POST['_csrf_token'] ?? '');
        if (!$this->guard->session()->validateAndConsumeCsrfToken('activity_store', $token)) {
            $this->guard->session()->setFlash('error', '不正な操作です。再度お試しください。');
            Responses::redirect($returnTo);
        }

        $input = $this->collectInput();
        $errors = $this->validateInput($input);
        if ($errors !== []) {
            $this->guard->session()->setFlash('error', implode(' ', $errors));
            $this->storeCreateDraft($input);
            Responses::redirect($returnTo);
        }

        try {
            $pdo = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            $repository = new ActivityRepository($pdo);
            $repository->create($input, (int) ($auth['user_id'] ?? 0));
            $this->guard->session()->setFlash('success', '活動を登録しました。');
        } catch (Throwable) {
            $this->guard->session()->setFlash('error', '活動の登録に失敗しました。');
            $this->storeCreateDraft($input);
            Responses::redirect($returnTo);
        }

        Responses::redirect($this->config->routeUrl('activity/list'));
    }

    public function update(): void
    {
        $auth = $this->guard->requireAuthenticated();
        $returnTo = $this->validateReturnTo($_POST['return_to'] ?? null);
        $token = (string) ($_POST['_csrf_token'] ?? '');
        if (!$this->guard->session()->validateAndConsumeCsrfToken('activity_update', $token)) {
            $this->guard->session()->setFlash('error', '不正な操作です。再度お試しください。');
            Responses::redirect($returnTo);
        }

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            $this->guard->session()->setFlash('error', '活動IDが不正です。');
            Responses::redirect($returnTo);
        }

        $input = $this->collectInput();
        $errors = $this->validateInput($input);
        if ($errors !== []) {
            $this->guard->session()->setFlash('error', implode(' ', $errors));
            $this->storeEditDraft($id, $input);
            Responses::redirect($returnTo);
        }

        try {
            $pdo = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            $repository = new ActivityRepository($pdo);
            $updated = $repository->update($id, $input, (int) ($auth['user_id'] ?? 0));
            if ($updated > 0) {
                $this->guard->session()->setFlash('success', '活動を更新しました。');
            } else {
                $this->guard->session()->setFlash('error', '更新対象が見つかりません。');
                $this->storeEditDraft($id, $input);
            }
        } catch (Throwable) {
            $this->guard->session()->setFlash('error', '活動の更新に失敗しました。');
            $this->storeEditDraft($id, $input);
        }

        Responses::redirect($returnTo);
    }

    public function delete(): void
    {
        $auth = $this->guard->requireAuthenticated();
        $returnTo = $this->validateReturnTo($_POST['return_to'] ?? null);
        $token = (string) ($_POST['_csrf_token'] ?? '');
        if (!$this->guard->session()->validateAndConsumeCsrfToken('activity_delete', $token)) {
            $this->guard->session()->setFlash('error', '不正な操作です。再度お試しください。');
            Responses::redirect($returnTo);
        }

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            $this->guard->session()->setFlash('error', '活動IDが不正です。');
            Responses::redirect($returnTo);
        }

        try {
            $pdo = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            $repository = new ActivityRepository($pdo);
            $deleted = $repository->softDelete($id);
            if ($deleted > 0) {
                $this->guard->session()->setFlash('success', '活動を削除しました。');
            } else {
                $this->guard->session()->setFlash('error', '削除対象が見つかりません。');
            }
        } catch (Throwable) {
            $this->guard->session()->setFlash('error', '活動の削除に失敗しました。');
        }

        Responses::redirect($this->config->routeUrl('activity/list'));
    }

    public function daily(): void
    {
        $auth = $this->guard->requireAuthenticated();

        $loginUserId = (int) ($auth['user_id'] ?? 0);
        $today = (new DateTimeImmutable())->format('Y-m-d');

        $date        = trim((string) ($_GET['date'] ?? $today));
        $staffUserId = (int) ($_GET['staff'] ?? $loginUserId);
        if ($staffUserId <= 0) {
            $staffUserId = $loginUserId;
        }

        // date検証
        $parsedDate = DateTimeImmutable::createFromFormat('Y-m-d', $date);
        if ($parsedDate === false || $parsedDate->format('Y-m-d') !== $date) {
            $date = $today;
        }

        $prevDate = (new DateTimeImmutable($date))->modify('-1 day')->format('Y-m-d');
        $nextDate = (new DateTimeImmutable($date))->modify('+1 day')->format('Y-m-d');

        $activities  = [];
        $dailyReport = null;
        $staffUsers  = [];
        $error       = null;

        try {
            $pdo = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            $dailyRepo = new DailyReportRepository($pdo);
            $activities  = $dailyRepo->findActivitiesForDay($date, $staffUserId);
            $dailyReport = $dailyRepo->findByDateAndStaff($date, $staffUserId);
            $staffUsers  = $this->fetchAssignableUsers((string) ($auth['tenant_code'] ?? ''));
        } catch (Throwable) {
            $error = '日報データの取得に失敗しました。';
        }

        $staffUserNames = $this->buildUserNameMap($staffUsers);
        $displayName    = $staffUserNames[$staffUserId] ?? '';

        $flashError   = $this->guard->session()->consumeFlash('error');
        $flashSuccess = $this->guard->session()->consumeFlash('success');
        $listUrl      = $this->config->routeUrl('activity/list');

        $isOwnReport = ($staffUserId === $loginUserId);
        $submitCsrf  = $isOwnReport
            ? $this->guard->session()->issueCsrfToken('activity_submit')
            : '';

        Responses::html(ActivityDailyView::render(
            $date,
            $staffUserId,
            $displayName,
            $prevDate,
            $nextDate,
            $activities,
            $dailyReport,
            $staffUsers,
            $listUrl,
            $this->config->routeUrl('activity/daily'),
            $this->config->routeUrl('activity/comment'),
            $this->config->routeUrl('activity/detail'),
            $this->config->routeUrl('customer/detail'),
            $this->guard->session()->issueCsrfToken('activity_comment'),
            $loginUserId,
            $this->config->routeUrl('activity/submit'),
            $submitCsrf,
            $flashError,
            $flashSuccess,
            $error,
            self::ALLOWED_ACTIVITY_TYPES,
            ControllerLayoutHelper::build(
                $this->guard,
                $this->config,
                'activity',
                [
                    ['label' => 'ホーム', 'url' => $this->config->routeUrl('dashboard')],
                    ['label' => '活動一覧', 'url' => $listUrl],
                    ['label' => '日報ビュー（' . $date . '）'],
                ]
            )
        ));
    }

    public function submit(): void
    {
        $auth        = $this->guard->requireAuthenticated();
        $loginUserId = (int) ($auth['user_id'] ?? 0);

        $token = (string) ($_POST['_csrf_token'] ?? '');
        if (!$this->guard->session()->validateAndConsumeCsrfToken('activity_submit', $token)) {
            $this->guard->session()->setFlash('error', '不正な操作です。再度お試しください。');
            Responses::redirect($this->config->routeUrl('activity/list'));
        }

        $date        = trim((string) ($_POST['report_date'] ?? ''));
        $staffUserId = (int) ($_POST['staff_user_id'] ?? $loginUserId);

        $parsedDate = DateTimeImmutable::createFromFormat('Y-m-d', $date);
        if ($parsedDate === false || $parsedDate->format('Y-m-d') !== $date) {
            $this->guard->session()->setFlash('error', '日付が不正です。');
            Responses::redirect($this->config->routeUrl('activity/list'));
        }

        // 本人のみ提出可能
        if ($staffUserId !== $loginUserId) {
            $this->guard->session()->setFlash('error', '日報は本人のみ提出できます。');
            Responses::redirect(ListViewHelper::buildUrl(
                $this->config->routeUrl('activity/daily'),
                ['date' => $date, 'staff' => (string) $staffUserId]
            ));
        }

        $returnUrl = ListViewHelper::buildUrl(
            $this->config->routeUrl('activity/daily'),
            ['date' => $date, 'staff' => (string) $staffUserId]
        );

        try {
            $pdo       = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            $dailyRepo = new DailyReportRepository($pdo);
            $dailyRepo->submit($date, $staffUserId);
            $this->guard->session()->setFlash('success', '日報を提出しました。');
        } catch (Throwable) {
            $this->guard->session()->setFlash('error', '日報の提出に失敗しました。');
        }

        Responses::redirect($returnUrl);
    }

    public function saveComment(): void
    {
        $auth = $this->guard->requireAuthenticated();
        $loginUserId = (int) ($auth['user_id'] ?? 0);

        $token = (string) ($_POST['_csrf_token'] ?? '');
        if (!$this->guard->session()->validateAndConsumeCsrfToken('activity_comment', $token)) {
            $this->guard->session()->setFlash('error', '不正な操作です。再度お試しください。');
            Responses::redirect($this->config->routeUrl('activity/list'));
        }

        $date        = trim((string) ($_POST['report_date'] ?? ''));
        $staffUserId = (int) ($_POST['staff_user_id'] ?? $loginUserId);
        $comment     = trim((string) ($_POST['comment'] ?? ''));

        $parsedDate = DateTimeImmutable::createFromFormat('Y-m-d', $date);
        if ($parsedDate === false || $parsedDate->format('Y-m-d') !== $date) {
            $this->guard->session()->setFlash('error', '日付が不正です。');
            Responses::redirect($this->config->routeUrl('activity/list'));
        }

        $returnUrl = ListViewHelper::buildUrl(
            $this->config->routeUrl('activity/daily'),
            ['date' => $date, 'staff' => (string) $staffUserId]
        );

        try {
            $pdo = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            $dailyRepo = new DailyReportRepository($pdo);
            $dailyRepo->upsertComment($date, $staffUserId, $comment);
            $this->guard->session()->setFlash('success', '日報コメントを保存しました。');
        } catch (Throwable) {
            $this->guard->session()->setFlash('error', '日報コメントの保存に失敗しました。');
        }

        Responses::redirect($returnUrl);
    }

    // ---- private helpers ----

    /**
     * @param array<string, mixed> $source
     * @return array<string, string>
     */
    private function extractCriteria(array $source, int $loginUserId, string $today, bool $isAdmin = false): array
    {
        $staffUserId = trim((string) ($source['staff_user_id'] ?? ''));
        if ($staffUserId === '') {
            $staffUserId = (string) $loginUserId;
        }

        $dateFrom = trim((string) ($source['activity_date_from'] ?? ''));
        $dateTo   = trim((string) ($source['activity_date_to'] ?? ''));
        if ($dateFrom === '' && $dateTo === '') {
            $dateFrom = $today;
            $dateTo   = $today;
        }

        return [
            'activity_date_from'  => $dateFrom,
            'activity_date_to'    => $dateTo,
            'customer_name'       => trim((string) ($source['customer_name'] ?? '')),
            'activity_type'       => trim((string) ($source['activity_type'] ?? '')),
            'staff_user_id'       => $staffUserId,
            'daily_report_status' => $isAdmin ? trim((string) ($source['daily_report_status'] ?? '')) : '',
        ];
    }

    /**
     * @param array<string, mixed> $source
     * @return array<string, string>
     */
    private function extractListState(array $source): array
    {
        $sort = ListViewHelper::normalizeSort($source['sort'] ?? '', ActivityRepository::SORTABLE_FIELDS);

        return [
            'page'      => (string) ListViewHelper::normalizePage($source['page'] ?? 1),
            'per_page'  => (string) ListViewHelper::normalizePerPage($source['per_page'] ?? ListViewHelper::DEFAULT_PER_PAGE),
            'sort'      => $sort,
            'direction' => $sort === '' ? 'asc' : ListViewHelper::normalizeDirection($source['direction'] ?? 'asc'),
        ];
    }

    /**
     * @param array<string, string> $criteria
     * @param array<string, string> $listState
     */
    private function listUrl(array $criteria, array $listState): string
    {
        $params = $criteria;
        if ((int) ($listState['page'] ?? '1') > 1) {
            $params['page'] = (string) $listState['page'];
        }
        if ((int) ($listState['per_page'] ?? (string) ListViewHelper::DEFAULT_PER_PAGE) !== ListViewHelper::DEFAULT_PER_PAGE) {
            $params['per_page'] = (string) $listState['per_page'];
        }
        if (($listState['sort'] ?? '') !== '') {
            $params['sort']      = (string) $listState['sort'];
            $params['direction'] = (string) ($listState['direction'] ?? 'asc');
        }

        return ListViewHelper::buildUrl($this->config->routeUrl('activity/list'), $params);
    }

    private function listUrlFromGet(): string
    {
        $auth      = $this->guard->requireAuthenticated();
        $today     = (new DateTimeImmutable())->format('Y-m-d');
        $isAdmin   = $this->isAdmin($auth);
        $criteria  = $this->extractCriteria($_GET, (int) ($auth['user_id'] ?? 0), $today, $isAdmin);
        $listState = $this->extractListState($_GET);

        return $this->listUrl($criteria, $listState);
    }

    private function detailUrl(int $id): string
    {
        return ListViewHelper::buildUrl($this->config->routeUrl('activity/detail'), ['id' => (string) $id]);
    }

    private function validateReturnTo(mixed $returnTo): string
    {
        $default = $this->config->routeUrl('activity/list');
        if (!is_string($returnTo) || $returnTo === '') {
            return $default;
        }
        $parsed = parse_url($returnTo);
        if (!is_array($parsed) || isset($parsed['host'])) {
            return $default;
        }

        return $returnTo;
    }

    /**
     * @return array<string, mixed>
     */
    private function collectInput(): array
    {
        return [
            'customer_id'       => trim((string) ($_POST['customer_id'] ?? '')),
            'contract_id'       => trim((string) ($_POST['contract_id'] ?? '')),
            'renewal_case_id'   => trim((string) ($_POST['renewal_case_id'] ?? '')),
            'accident_case_id'  => trim((string) ($_POST['accident_case_id'] ?? '')),
            'activity_date'     => trim((string) ($_POST['activity_date'] ?? '')),
            'start_time'        => trim((string) ($_POST['start_time'] ?? '')),
            'end_time'          => trim((string) ($_POST['end_time'] ?? '')),
            'activity_type'     => trim((string) ($_POST['activity_type'] ?? '')),
            'purpose_type'      => trim((string) ($_POST['purpose_type'] ?? '')),
            'visit_place'       => trim((string) ($_POST['visit_place'] ?? '')),
            'interviewee_name'  => trim((string) ($_POST['interviewee_name'] ?? '')),
            'subject'           => trim((string) ($_POST['subject'] ?? '')),
            'content_summary'   => trim((string) ($_POST['content_summary'] ?? '')),
            'detail_text'       => trim((string) ($_POST['detail_text'] ?? '')),
            'next_action_date'  => trim((string) ($_POST['next_action_date'] ?? '')),
            'next_action_note'  => trim((string) ($_POST['next_action_note'] ?? '')),
            'result_type'       => trim((string) ($_POST['result_type'] ?? '')),
            'staff_user_id'     => trim((string) ($_POST['staff_user_id'] ?? '')),
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return string[]
     */
    private function validateInput(array $input): array
    {
        $errors = [];

        $customerId = trim((string) ($input['customer_id'] ?? ''));
        if ($customerId === '' || !ctype_digit($customerId) || (int) $customerId <= 0) {
            $errors[] = '顧客を選択してください。';
        }

        $activityDate = trim((string) ($input['activity_date'] ?? ''));
        if ($activityDate === '') {
            $errors[] = '活動日は必須です。';
        } elseif (!$this->isValidDate($activityDate)) {
            $errors[] = '活動日の形式が不正です。';
        }

        $activityType = trim((string) ($input['activity_type'] ?? ''));
        if ($activityType === '') {
            $errors[] = '活動種別は必須です。';
        }

        $contentSummary = trim((string) ($input['content_summary'] ?? ''));
        if ($contentSummary === '') {
            $errors[] = '内容要約は必須です。';
        }

        return $errors;
    }

    private function isValidDate(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value;
    }

    private function storeCreateDraft(mixed $input): void
    {
        $this->guard->session()->setFlash('activity_create_draft', serialize($input));
    }

    private function consumeCreateDraft(): ?array
    {
        $raw = $this->guard->session()->consumeFlash('activity_create_draft');
        if (!is_string($raw) || $raw === '') {
            return null;
        }
        $data = @unserialize($raw);
        return is_array($data) ? $data : null;
    }

    private function storeEditDraft(int $id, mixed $input): void
    {
        $this->guard->session()->setFlash('activity_edit_draft', serialize(['id' => $id, 'input' => $input]));
    }

    private function consumeEditDraft(): ?array
    {
        $raw = $this->guard->session()->consumeFlash('activity_edit_draft');
        if (!is_string($raw) || $raw === '') {
            return null;
        }
        $data = @unserialize($raw);
        return is_array($data) ? $data : null;
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function fetchAssignableUsers(string $tenantCode): array
    {
        $tenantCode = trim($tenantCode);
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

    /**
     * @param array<int, array{id:int, name:string}> $users
     * @return array<int, string>
     */
    private function buildUserNameMap(array $users): array
    {
        $map = [];
        foreach ($users as $user) {
            $id   = (int) ($user['id'] ?? 0);
            $name = trim((string) ($user['name'] ?? ''));
            if ($id > 0 && $name !== '') {
                $map[$id] = $name;
            }
        }

        return $map;
    }

    /**
     * @param array<string, mixed> $auth
     */
    private function isAdmin(array $auth): bool
    {
        $permissions = $auth['permissions'] ?? [];
        if (!is_array($permissions)) {
            return false;
        }

        return !empty($permissions['is_system_admin'])
            || (($permissions['tenant_role'] ?? '') === 'admin');
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array<int, string> $nameMap
     * @return array<int, array<string, mixed>>
     */
    private function attachStaffNamesToRows(array $rows, array $nameMap): array
    {
        foreach ($rows as &$row) {
            $row['staff_name'] = $nameMap[(int) ($row['staff_user_id'] ?? 0)] ?? '';
        }
        unset($row);

        return $rows;
    }
}
