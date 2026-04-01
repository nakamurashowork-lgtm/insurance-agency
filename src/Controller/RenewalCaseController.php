<?php
declare(strict_types=1);

namespace App\Controller;

use App\AppConfig;
use App\Domain\Renewal\RenewalCaseRepository;
use App\Domain\Sales\SalesPerformanceRepository;
use App\Http\Responses;
use App\Infra\CommonConnectionFactory;
use App\Infra\TenantConnectionFactory;
use App\Presentation\RenewalCaseDetailView;
use App\Presentation\RenewalCaseListView;
use App\Presentation\View\ListViewHelper;
use App\Security\AuthGuard;
use PDO;
use Throwable;

final class RenewalCaseController
{
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
        $error = null;
        $importBatch = null;
        $importRows = [];

        try {
            $pdo = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            $repository = new RenewalCaseRepository($pdo);
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

            $importBatchId = (int) ($_GET['import_batch_id'] ?? 0);
            if ($importBatchId > 0) {
                $salesRepository = new SalesPerformanceRepository($pdo);
                $importBatch = $salesRepository->findImportBatchById($importBatchId);
                if ($importBatch !== null) {
                    $importRows = $salesRepository->findImportRowsByBatchId($importBatchId, 200);
                }
            }
        } catch (Throwable) {
            $error = '満期一覧の取得に失敗しました。接続設定を確認してください。';
        }

        $flashError = $this->guard->session()->consumeFlash('error');
        $flashSuccess = $this->guard->session()->consumeFlash('success');
        $openImportDialog = (string) ($_GET['import_dialog'] ?? '') === '1'
            || $importBatch !== null
            || (is_string($flashError) && $flashError !== '')
            || (is_string($flashSuccess) && $flashSuccess !== '');

        Responses::html(RenewalCaseListView::render(
            $rows,
            $total,
            $criteria,
            $listState,
            $this->config->routeUrl('renewal/list'),
            $this->config->routeUrl('renewal/detail'),
            $this->config->routeUrl('sales/import'),
            $this->guard->session()->issueCsrfToken('sales_import'),
            $flashError,
            $flashSuccess,
            $importBatch,
            $importRows,
            $openImportDialog,
            $error,
            (string) ($_GET['filter_open'] ?? '') === '1',
            ControllerLayoutHelper::build($this->guard, $this->config, 'renewal')
        ));
    }

    public function detail(): void
    {
        $auth = $this->guard->requireAuthenticated();
        $criteria = $this->extractCriteria($_GET);
        $listState = $this->extractListState($_GET);
        $listUrl = $this->listUrl($criteria, $listState);
        $renewalCaseId = (int) ($_GET['id'] ?? 0);
        if ($renewalCaseId <= 0) {
            $this->guard->session()->setFlash('error', '案件IDが不正です。');
            Responses::redirect($listUrl);
        }

        try {
            $pdo = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            $repository = new RenewalCaseRepository($pdo);
            $detail = $repository->findDetailById($renewalCaseId);
            if ($detail === null) {
                $this->guard->session()->setFlash('error', '対象案件が見つかりません。');
                Responses::redirect($listUrl);
            }

            $comments = $repository->findComments($renewalCaseId);
            $authorNames = $this->fetchUserNamesByRows($comments, 'created_by');
            $comments = $this->attachAuthorNames($comments, $authorNames);
            $audits = $repository->findAuditEvents($renewalCaseId);
            $auditUserNames = $this->fetchUserNamesByRows($audits, 'changed_by');
            $audits = $this->attachAuditUserNames($audits, $auditUserNames);
            $assignedUid = (int) ($detail['assigned_user_id'] ?? 0);
            if ($assignedUid > 0) {
                $assignedNames = $this->fetchUserNames([$assignedUid]);
                $detail['assigned_user_name'] = $assignedNames[$assignedUid] ?? '';
            } else {
                $detail['assigned_user_name'] = '';
            }
            $updateInput = $this->consumeUpdateInput($renewalCaseId);
            $fieldErrors = $this->consumeUpdateErrors($renewalCaseId);
            if (is_array($updateInput)) {
                foreach (['case_status', 'next_action_date', 'renewal_result', 'lost_reason', 'remark'] as $key) {
                    if (array_key_exists($key, $updateInput)) {
                        $detail[$key] = $updateInput[$key];
                    }
                }
            }
            $flashError = $this->guard->session()->consumeFlash('error');
            $flashSuccess = $this->guard->session()->consumeFlash('success');
            $csrfToken = $this->guard->session()->issueCsrfToken('renewal_update_' . $renewalCaseId);
            $commentCsrfToken = $this->guard->session()->issueCsrfToken('renewal_comment_' . $renewalCaseId);
            $detailUrl = $this->detailUrl($renewalCaseId, $criteria, $listState);

            Responses::html(RenewalCaseDetailView::render(
                $detail,
                $comments,
                $audits,
                $this->config->routeUrl('renewal/update'),
                $this->config->routeUrl('renewal/comment'),
                $listUrl,
                $detailUrl,
                $this->buildListQuery($criteria, $listState),
                $this->config->routeUrl('customer/detail'),
                $csrfToken,
                $commentCsrfToken,
                $flashError,
                $flashSuccess,
                $fieldErrors,
                ControllerLayoutHelper::build(
                    $this->guard,
                    $this->config,
                    'renewal',
                    [
                        ['label' => 'ホーム', 'url' => $this->config->routeUrl('dashboard')],
                        ['label' => '満期一覧', 'url' => $listUrl],
                        ['label' => '満期詳細'],
                    ]
                )
            ));
        } catch (Throwable) {
            $this->guard->session()->setFlash('error', '満期詳細の取得に失敗しました。');
            Responses::redirect($listUrl);
        }
    }

    public function update(): void
    {
        $auth = $this->guard->requireAuthenticated();
        $criteria = $this->extractCriteria($_POST);
        $listState = $this->extractListState($_POST);
        $renewalCaseId = (int) ($_POST['id'] ?? 0);
        if ($renewalCaseId <= 0) {
            $this->guard->session()->setFlash('error', '案件IDが不正です。');
            Responses::redirect($this->listUrl($criteria, $listState));
        }

        $token = (string) ($_POST['_csrf_token'] ?? '');
        if (!$this->guard->session()->validateAndConsumeCsrfToken('renewal_update_' . $renewalCaseId, $token)) {
            $this->guard->session()->setFlash('error', '不正な更新要求を検出しました。');
            Responses::redirect($this->detailUrl($renewalCaseId, $criteria, $listState));
        }

        $input = $this->collectUpdateInput($_POST);
        $fieldErrors = $this->validateUpdateInput($input);
        if ($fieldErrors !== []) {
            $this->guard->session()->setFlash('error', '入力内容を確認してください。');
            $this->storeUpdateInput($renewalCaseId, $input);
            $this->storeUpdateErrors($renewalCaseId, $fieldErrors);
            Responses::redirect($this->detailUrl($renewalCaseId, $criteria, $listState));
        }

        try {
            $pdo = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            $repository = new RenewalCaseRepository($pdo);
            $updated = $repository->updateRenewalCase($renewalCaseId, $input, (int) ($auth['user_id'] ?? 0));
            if ($updated) {
                $this->guard->session()->setFlash('success', '満期対応情報を更新しました。');
            } else {
                $this->guard->session()->setFlash('error', '更新対象が見つからないか、変更がありません。');
            }
        } catch (Throwable) {
            $this->guard->session()->setFlash('error', '満期対応情報の更新に失敗しました。');
        }

        Responses::redirect($this->detailUrl($renewalCaseId, $criteria, $listState));
    }

    public function comment(): void
    {
        $auth = $this->guard->requireAuthenticated();
        $criteria = $this->extractCriteria($_POST);
        $listState = $this->extractListState($_POST);
        $renewalCaseId = (int) ($_POST['id'] ?? 0);
        $returnTo = $this->validateDetailReturnTo($_POST['return_to'] ?? null, $renewalCaseId, $criteria, $listState);
        if ($renewalCaseId <= 0) {
            $this->guard->session()->setFlash('error', '案件IDが不正です。');
            Responses::redirect($this->listUrl($criteria, $listState));
        }

        $token = (string) ($_POST['_csrf_token'] ?? '');
        if (!$this->guard->session()->validateAndConsumeCsrfToken('renewal_comment_' . $renewalCaseId, $token)) {
            $this->guard->session()->setFlash('error', '不正なコメント要求を検出しました。');
            Responses::redirect($returnTo);
        }

        $commentBody = trim((string) ($_POST['comment_body'] ?? ''));
        if ($commentBody === '') {
            $this->guard->session()->setFlash('error', 'コメント本文を入力してください。');
            Responses::redirect($returnTo);
        }

        try {
            $pdo = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            $repository = new RenewalCaseRepository($pdo);
            $repository->createComment($renewalCaseId, $commentBody, (int) ($auth['user_id'] ?? 0));
            $this->guard->session()->setFlash('success', 'コメントを登録しました。');
        } catch (Throwable) {
            $this->guard->session()->setFlash('error', 'コメントの登録に失敗しました。');
        }

        Responses::redirect($returnTo);
    }

    /**
     * @param array<string, mixed> $source
     * @return array<string, string>
     */
    private function collectUpdateInput(array $source): array
    {
        return [
            'case_status' => trim((string) ($source['case_status'] ?? '')),
            'next_action_date' => trim((string) ($source['next_action_date'] ?? '')),
            'renewal_result' => trim((string) ($source['renewal_result'] ?? '')),
            'lost_reason' => trim((string) ($source['lost_reason'] ?? '')),
            'remark' => trim((string) ($source['remark'] ?? '')),
        ];
    }

    /**
     * @param array<string, string> $input
     * @return array<string, string>
     */
    private function validateUpdateInput(array $input): array
    {
        $errors = [];
        $allowedStatuses = ['open', 'contacted', 'quoted', 'waiting', 'renewed', 'lost', 'closed'];
        $allowedResults = ['', 'pending', 'renewed', 'cancelled', 'lost'];

        $caseStatus = $input['case_status'] ?? '';
        $nextActionDate = $input['next_action_date'] ?? '';
        $renewalResult = $input['renewal_result'] ?? '';

        if (!in_array($caseStatus, $allowedStatuses, true)) {
            $errors['case_status'] = '対応ステータスを選択してください。';
        }

        if (!in_array($renewalResult, $allowedResults, true)) {
            $errors['renewal_result'] = '更改結果が不正です。';
        }

        if ($nextActionDate !== '' && !$this->isValidDate($nextActionDate)) {
            $errors['next_action_date'] = '次回対応予定日は YYYY-MM-DD 形式で入力してください。';
        }

        return $errors;
    }

    private function isValidDate(string $value): bool
    {
        if ($value === '') {
            return false;
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value;
    }

    /**
     * @param array<string, mixed> $source
     * @return array<string, string>
     */
    private function extractCriteria(array $source): array
    {
        return [
            'customer_name' => trim((string) ($source['customer_name'] ?? '')),
            'policy_no' => trim((string) ($source['policy_no'] ?? '')),
            'case_status' => trim((string) ($source['case_status'] ?? '')),
            'maturity_date_from' => trim((string) ($source['maturity_date_from'] ?? '')),
            'maturity_date_to' => trim((string) ($source['maturity_date_to'] ?? '')),
        ];
    }

    /**
     * @param array<string, mixed> $source
     * @return array<string, string>
     */
    private function extractListState(array $source): array
    {
        $sort = ListViewHelper::normalizeSort($source['sort'] ?? '', RenewalCaseRepository::SORTABLE_FIELDS);

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
    private function buildListQuery(array $criteria, array $listState, bool $includePage = true): array
    {
        $params = $criteria;

        if ($includePage && (int) ($listState['page'] ?? '1') > 1) {
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
        return ListViewHelper::buildUrl($this->config->routeUrl('renewal/list'), $this->buildListQuery($criteria, $listState));
    }

    /**
     * @param array<string, string> $criteria
     * @param array<string, string> $listState
     */
    private function detailUrl(int $renewalCaseId, array $criteria, array $listState): string
    {
        return ListViewHelper::buildUrl(
            $this->config->routeUrl('renewal/detail'),
            array_merge(['id' => (string) $renewalCaseId], $this->buildListQuery($criteria, $listState))
        );
    }

    /**
     * @param array<string, string> $criteria
     * @param array<string, string> $listState
     */
    private function validateDetailReturnTo(mixed $returnTo, int $renewalCaseId, array $criteria, array $listState): string
    {
        $default = $this->detailUrl($renewalCaseId, $criteria, $listState);
        $candidate = trim((string) $returnTo);
        if ($candidate === '' || str_contains($candidate, "\n") || str_contains($candidate, "\r")) {
            return $default;
        }

        if (str_contains($candidate, 'route=renewal/detail') && str_contains($candidate, 'id=' . $renewalCaseId)) {
            return $candidate;
        }

        return $default;
    }

    /**
     * @param array<string, string> $input
     */
    private function storeUpdateInput(int $renewalCaseId, array $input): void
    {
        $key = 'renewal_update_input_' . $renewalCaseId;
        $this->guard->session()->setFlash($key, json_encode($input, JSON_UNESCAPED_UNICODE) ?: '{}');
    }

    /**
     * @return array<string, string>|null
     */
    private function consumeUpdateInput(int $renewalCaseId): ?array
    {
        $key = 'renewal_update_input_' . $renewalCaseId;
        $raw = (string) $this->guard->session()->consumeFlash($key);
        if ($raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param array<string, string> $errors
     */
    private function storeUpdateErrors(int $renewalCaseId, array $errors): void
    {
        $key = 'renewal_update_errors_' . $renewalCaseId;
        $this->guard->session()->setFlash($key, json_encode($errors, JSON_UNESCAPED_UNICODE) ?: '{}');
    }

    /**
     * @return array<string, string>
     */
    private function consumeUpdateErrors(int $renewalCaseId): array
    {
        $key = 'renewal_update_errors_' . $renewalCaseId;
        $raw = (string) $this->guard->session()->consumeFlash($key);
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
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
            'SELECT id, name
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