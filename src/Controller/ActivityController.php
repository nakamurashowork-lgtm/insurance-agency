<?php
declare(strict_types=1);

namespace App\Controller;

use App\AppConfig;
use App\Domain\Activity\ActivityRepository;
use App\Domain\Activity\DailyReportRepository;
use App\Domain\SalesCase\SalesCaseRepository;
use App\Domain\Tenant\ActivityPurposeTypeRepository;
use App\Domain\Tenant\ActivityTypeRepository;
use App\Domain\Tenant\StaffRepository;
use App\Http\Responses;
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
    public function __construct(
        private AuthGuard $guard,
        private TenantConnectionFactory $tenantConnectionFactory,
        private AppConfig $config
    ) {
    }

    public function list(): void
    {
        $auth = $this->guard->requireAuthenticated();

        $today       = (new DateTimeImmutable())->format('Y-m-d');
        $isAdmin     = $this->isAdmin($auth);
        $loginUserId = (int) ($auth['user_id'] ?? 0);
        $criteria    = $this->extractCriteria($_GET, $loginUserId, $today, $isAdmin);
        $listState   = $this->extractListState($_GET);
        $listUrl     = $this->listUrl($criteria, $listState);

        $rows = [];
        $total = 0;
        $customers = [];
        $staffUsers = [];
        $activityTypes = [];
        $error = null;

        try {
            $pdo = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            $activityTypes = (new ActivityTypeRepository($pdo))->findActiveMap();

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
            $staffUsers = (new StaffRepository($pdo))->findActive();
            $staffUserNames = $this->buildUserNameMap($staffUsers);
            $rows = $this->attachStaffNamesToRows($rows, $staffUserNames);
        } catch (Throwable) {
            $error = '活動一覧の取得に失敗しました。時間をおいて再度お試しください。解消しない場合は管理者へご連絡ください。';
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
            $this->config->routeUrl('activity/detail'),
            $this->config->routeUrl('activity/daily'),
            $this->config->routeUrl('customer/detail'),
            $flashError,
            $flashSuccess,
            $error,
            $activityTypes,
            $isAdmin,
            (string) ($_GET['filter_open'] ?? '') === '1',
            ControllerLayoutHelper::build($this->guard, $this->config, 'activity')
        ));
    }

    public function detail(): void
    {
        $auth = $this->guard->requireAuthenticated();

        $id         = (int) ($_GET['id'] ?? 0);
        $from       = $this->sanitizeFrom((string) ($_GET['from'] ?? 'list'));
        $date       = $this->sanitizeDate((string) ($_GET['date'] ?? ''));
        $customerId = (int) ($_GET['customer_id'] ?? 0);
        $backInfo   = $this->resolveBackInfo($from, $date, $customerId);
        $backUrl    = $backInfo['url'];

        if ($id <= 0) {
            $this->guard->session()->setFlash('error', '活動IDが不正です。');
            Responses::redirect($backUrl);
        }

        $record        = null;
        $customers     = [];
        $staffUsers    = [];
        $purposeTypes  = [];
        $activityTypes = [];
        $salesCases    = [];
        $error         = null;
        $canEdit       = false;

        try {
            $pdo = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            $repository = new ActivityRepository($pdo);
            $record = $repository->findById($id);
            if ($record === null) {
                $this->guard->session()->setFlash('error', '対象の活動が見つかりません。');
                Responses::redirect($backUrl);
            }
            $customers     = $repository->fetchCustomers(500);
            $staffRepo     = new StaffRepository($pdo);
            $staffUsers    = $staffRepo->findActive();
            $staffUserNames = $this->buildUserNameMap($staffUsers);
            $record['staff_name'] = $staffUserNames[(int) ($record['staff_id'] ?? 0)] ?? '';
            $loginStaffMstId = $staffRepo->findIdByUserId((int) ($auth['user_id'] ?? 0));
            $canEdit = $this->canEditActivity($record, $auth, $loginStaffMstId);
            $purposeTypes  = (new ActivityPurposeTypeRepository($pdo))->findAll();
            $activityTypes = (new ActivityTypeRepository($pdo))->findActiveMap();
            $salesCases    = (new SalesCaseRepository($pdo))->fetchForDropdown(500);

            $editDraft = $this->consumeEditDraft();
            if ($editDraft !== null && (int) ($editDraft['id'] ?? 0) === $id && is_array($editDraft['input'] ?? null)) {
                $record = array_merge($record, (array) $editDraft['input']);
            }
        } catch (Throwable) {
            $error = '活動詳細の取得に失敗しました。時間をおいて再度お試しください。解消しない場合は管理者へご連絡ください。';
        }

        $flashError   = $this->guard->session()->consumeFlash('error');
        $flashSuccess = $this->guard->session()->consumeFlash('success');

        // from パラメータを維持した詳細 URL（更新後の return_to に使用）
        $detailParams = array_filter([
            'id'          => (string) $id,
            'from'        => $from,
            'date'        => $date,
            'customer_id' => $customerId > 0 ? (string) $customerId : '',
        ], fn($v) => $v !== '');
        $detailUrl = ListViewHelper::buildUrl($this->config->routeUrl('activity/detail'), $detailParams);

        Responses::html(ActivityDetailView::renderDetail(
            $record,
            $customers,
            $staffUsers,
            $salesCases ?? [],
            $backUrl,
            $detailUrl,
            $this->config->routeUrl('activity/update'),
            $this->config->routeUrl('customer/detail'),
            $this->guard->session()->issueCsrfToken('activity_update'),
            $flashError,
            $flashSuccess,
            $error,
            $activityTypes,
            ControllerLayoutHelper::build(
                $this->guard,
                $this->config,
                'activity',
                $backInfo['breadcrumbs']
            ),
            $purposeTypes,
            $canEdit
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
            $this->storeStoreDraft($input);
            Responses::redirect(ListViewHelper::buildUrl($returnTo, ['open_modal' => 'store']));
        }

        try {
            $pdo = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            $repository = new ActivityRepository($pdo);
            $repository->create($input, (int) ($auth['user_id'] ?? 0));
            $this->guard->session()->setFlash('success', '活動を登録しました。');
        } catch (Throwable) {
            $this->guard->session()->setFlash('error', '活動の登録に失敗しました。');
            $this->storeStoreDraft($input);
            Responses::redirect(ListViewHelper::buildUrl($returnTo, ['open_modal' => 'store']));
        }

        Responses::redirect($returnTo);
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
            $current = $repository->findById($id);
            if ($current === null) {
                $this->guard->session()->setFlash('error', '更新対象が見つかりません。');
                Responses::redirect($returnTo);
            }
            $loginStaffMstId = (new StaffRepository($pdo))->findIdByUserId((int) ($auth['user_id'] ?? 0));
            if (!$this->canEditActivity($current, $auth, $loginStaffMstId)) {
                $this->guard->session()->setFlash('error', 'この活動を編集する権限がありません。');
                Responses::redirect($returnTo);
            }
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
            $current = $repository->findById($id);
            if ($current === null) {
                $this->guard->session()->setFlash('error', '削除対象が見つかりません。');
                Responses::redirect($returnTo);
            }
            $loginStaffMstId = (new StaffRepository($pdo))->findIdByUserId((int) ($auth['user_id'] ?? 0));
            if (!$this->canEditActivity($current, $auth, $loginStaffMstId)) {
                $this->guard->session()->setFlash('error', 'この活動を削除する権限がありません。');
                Responses::redirect($returnTo);
            }
            $deleted = $repository->softDelete($id);
            if ($deleted > 0) {
                $this->guard->session()->setFlash('success', '活動を削除しました。');
            } else {
                $this->guard->session()->setFlash('error', '削除対象が見つかりません。');
            }
        } catch (Throwable) {
            $this->guard->session()->setFlash('error', '活動の削除に失敗しました。');
        }

        Responses::redirect($returnTo);
    }

    public function daily(): void
    {
        $auth = $this->guard->requireAuthenticated();

        $loginUserId = (int) ($auth['user_id'] ?? 0);
        $displayName = (string) ($auth['display_name'] ?? '');
        $today = (new DateTimeImmutable())->format('Y-m-d');

        $date        = trim((string) ($_GET['date'] ?? $today));
        $staffUserId = $loginUserId;

        // date検証
        $parsedDate = DateTimeImmutable::createFromFormat('Y-m-d', $date);
        if ($parsedDate === false || $parsedDate->format('Y-m-d') !== $date) {
            $date = $today;
        }

        $prevDate = (new DateTimeImmutable($date))->modify('-1 day')->format('Y-m-d');
        $nextDate = (new DateTimeImmutable($date))->modify('+1 day')->format('Y-m-d');

        $activities      = [];
        $dailyReport     = null;
        $staffUsers      = [];
        $customers       = [];
        $salesCases      = [];
        $purposeTypes    = [];
        $activityTypes   = [];
        $error           = null;
        $loginStaffMstId = null;

        try {
            $pdo = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            $staffRepo    = new StaffRepository($pdo);
            $dailyRepo    = new DailyReportRepository($pdo);
            $actRepo      = new ActivityRepository($pdo);

            // t_activity.staff_id = m_staff.id のため、loginUserId ではなく m_staff.id でフィルタする
            $loginStaffMstId = $staffRepo->findIdByUserId($loginUserId);
            $filterStaffId   = $loginStaffMstId ?? $loginUserId; // m_staff なければ後方互換で loginUserId

            $activities    = $dailyRepo->findActivitiesForDay($date, $filterStaffId);
            $dailyReport   = $dailyRepo->findByDateAndStaff($date, $staffUserId);
            $staffUsers    = $staffRepo->findActive();
            $customers     = $actRepo->fetchCustomers(500);
            $salesCases    = (new SalesCaseRepository($pdo))->fetchForDropdown(500);
            $purposeTypes  = (new ActivityPurposeTypeRepository($pdo))->findAll();
            $activityTypes = (new ActivityTypeRepository($pdo))->findActiveMap();

            // フォームのプレフィル用に m_staff.id を保持
            $staffUserId = $loginStaffMstId ?? 0;
        } catch (Throwable) {
            $error = '日報データの取得に失敗しました。';
        }


        $flashError   = $this->guard->session()->consumeFlash('error');
        $flashSuccess = $this->guard->session()->consumeFlash('success');
        $listUrl      = $this->config->routeUrl('activity/list');
        $storeDraft   = $this->consumeStoreDraft();
        $openStoreModal = (string) ($_GET['open_modal'] ?? '') === 'store' || $storeDraft !== null;

        $dailyUrl = ListViewHelper::buildUrl(
            $this->config->routeUrl('activity/daily'),
            ['date' => $date]
        );

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
            $flashError,
            $flashSuccess,
            $error,
            $activityTypes,
            $this->config->routeUrl('activity/store'),
            $this->guard->session()->issueCsrfToken('activity_store'),
            $dailyUrl,
            $customers,
            $salesCases,
            $purposeTypes,
            ControllerLayoutHelper::build(
                $this->guard,
                $this->config,
                'activity',
                []
            ),
            $loginUserId,
            $this->config->routeUrl('activity/delete'),
            $this->guard->session()->issueCsrfToken('activity_delete'),
            $loginStaffMstId,
            $this->isAdmin($auth),
            $storeDraft,
            $openStoreModal
        ));
    }

    /**
     * 日報提出処理
     *
     * 注記: 本メソッドは UI からの呼び出しは無効化されている（2026-04-08）。
     * 将来、承認フロー実装時に UI を復活させる予定。
     * docs/screens/activity-daily.md Section 4-4 を参照。
     */
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
        $staffUserId = (int) ($_POST['staff_id'] ?? $loginUserId);

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
        $staffUserId = (int) ($_POST['staff_id'] ?? $loginUserId);
        $comment     = trim((string) ($_POST['comment'] ?? ''));

        if (mb_strlen($comment) > 500) {
            $this->guard->session()->setFlash('error', '日報コメントは500文字以内で入力してください。');
            Responses::redirect($this->config->routeUrl('activity/list'));
        }

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

    // ---- back-navigation helpers ----

    /**
     * from パラメータをホワイトリスト検証する。未知の値は 'list' にフォールバック。
     */
    private function sanitizeFrom(string $from): string
    {
        return in_array($from, ['daily', 'list', 'customer'], true) ? $from : 'list';
    }

    /**
     * date パラメータを検証する。不正な値は空文字にフォールバック。
     */
    private function sanitizeDate(string $date): string
    {
        if ($date === '') {
            return '';
        }
        $parsed = DateTimeImmutable::createFromFormat('Y-m-d', $date);
        return ($parsed !== false && $parsed->format('Y-m-d') === $date) ? $date : '';
    }

    /**
     * from の値に応じて戻り先情報（url / label / breadcrumbs）を返す。
     *
     * @return array{url: string, label: string, breadcrumbs: array<int, array<string, string|null>>}
     */
    private function resolveBackInfo(string $from, string $date, int $customerId): array
    {
        $effectiveDate = $date !== '' ? $date : (new DateTimeImmutable())->format('Y-m-d');

        return match ($from) {
            'daily' => [
                'url'   => ListViewHelper::buildUrl($this->config->routeUrl('activity/daily'), ['date' => $effectiveDate]),
                'label' => '日報に戻る',
                'breadcrumbs' => [
                    ['label' => 'ホーム',                            'url' => $this->config->routeUrl('dashboard')],
                    ['label' => '営業日報（' . $effectiveDate . '）', 'url' => ListViewHelper::buildUrl($this->config->routeUrl('activity/daily'), ['date' => $effectiveDate])],
                    ['label' => '活動詳細'],
                ],
            ],
            'customer' => [
                'url'   => $customerId > 0
                    ? ListViewHelper::buildUrl($this->config->routeUrl('customer/detail'), ['id' => (string) $customerId])
                    : $this->config->routeUrl('activity/list'),
                'label' => '顧客に戻る',
                'breadcrumbs' => [
                    ['label' => 'ホーム',   'url' => $this->config->routeUrl('dashboard')],
                    ['label' => '顧客詳細', 'url' => $customerId > 0
                        ? ListViewHelper::buildUrl($this->config->routeUrl('customer/detail'), ['id' => (string) $customerId])
                        : null],
                    ['label' => '活動詳細'],
                ],
            ],
            default => [
                'url'   => $this->config->routeUrl('activity/list'),
                'label' => '活動一覧に戻る',
                'breadcrumbs' => [
                    ['label' => 'ホーム',     'url' => $this->config->routeUrl('dashboard')],
                    ['label' => '活動一覧', 'url' => $this->config->routeUrl('activity/list')],
                    ['label' => '活動詳細'],
                ],
            ],
        };
    }

    // ---- private helpers ----

    /**
     * @param array<string, mixed> $source
     * @return array<string, string>
     */
    private function extractCriteria(array $source, int $loginUserId, string $today, bool $isAdmin = false): array
    {
        $dateFrom = trim((string) ($source['activity_date_from'] ?? ''));
        $dateTo   = trim((string) ($source['activity_date_to'] ?? ''));

        return [
            'activity_date_from'  => $dateFrom,
            'activity_date_to'    => $dateTo,
            'customer_name'       => trim((string) ($source['customer_name'] ?? '')),
            'activity_type'       => trim((string) ($source['activity_type'] ?? '')),
            'staff_id'            => trim((string) ($source['staff_id'] ?? '')),
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
        if (!is_array($parsed)) {
            return $default;
        }
        // 外部ホストへのオープンリダイレクトを防ぐ。
        // appUrl 設定時は routeUrl() が絶対 URL を返すため、同一ホストは許可する。
        if (isset($parsed['host'])) {
            $serverHost = $_SERVER['HTTP_HOST'] ?? '';
            if ($parsed['host'] !== $serverHost) {
                return $default;
            }
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
            'sales_case_id'     => trim((string) ($_POST['sales_case_id'] ?? '')),
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
            'staff_id'     => trim((string) ($_POST['staff_id'] ?? '')),
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
        // 空文字は「顧客なし」（NULL保存）として許容。値がある場合は正整数であること
        if ($customerId !== '' && (!ctype_digit($customerId) || (int) $customerId <= 0)) {
            $errors[] = '顧客の選択が不正です。';
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

        $subject = trim((string) ($input['subject'] ?? ''));
        if ($subject === '') {
            $errors[] = '活動概要は必須です。';
        }

        return $errors;
    }

    private function isValidDate(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value;
    }

    private function storeStoreDraft(array $input): void
    {
        $this->guard->session()->setFlash('activity_store_draft', serialize($input));
    }

    private function consumeStoreDraft(): ?array
    {
        $raw = $this->guard->session()->consumeFlash('activity_store_draft');
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
     * @param array<int, array<string, mixed>> $users
     * @return array<int, string>
     */
    private function buildUserNameMap(array $users): array
    {
        $map = [];
        foreach ($users as $user) {
            $id   = (int) ($user['id'] ?? 0);
            $name = trim((string) ($user['staff_name'] ?? $user['name'] ?? ''));
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
     * 活動の編集・削除権限を判定する。
     * - 管理者: すべての活動を操作可能
     * - 本人 (staff_id が m_staff.id と一致): 操作可能
     * - staff_id が NULL の場合: created_by が一致すれば操作可能
     *
     * @param array<string, mixed> $activity
     * @param array<string, mixed> $auth
     */
    private function canEditActivity(array $activity, array $auth, ?int $loginStaffMstId): bool
    {
        if ($this->isAdmin($auth)) {
            return true;
        }
        $loginUserId = (int) ($auth['user_id'] ?? 0);
        $staffId     = $activity['staff_id'] ?? null;
        if ($staffId !== null && $staffId !== '') {
            return (int) $staffId === ($loginStaffMstId ?? $loginUserId);
        }
        $createdBy = (int) ($activity['created_by'] ?? 0);
        return $createdBy > 0 && $createdBy === $loginUserId;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array<int, string> $nameMap
     * @return array<int, array<string, mixed>>
     */
    private function attachStaffNamesToRows(array $rows, array $nameMap): array
    {
        foreach ($rows as &$row) {
            $row['staff_name'] = $nameMap[(int) ($row['staff_id'] ?? 0)] ?? '';
        }
        unset($row);

        return $rows;
    }

    /** GETパラメータの staff_id 生値（未指定判定用） */
    private function rawGetStaffId(): string
    {
        return trim((string) ($_GET['staff_id'] ?? ''));
    }
}
