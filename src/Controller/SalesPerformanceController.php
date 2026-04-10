<?php
declare(strict_types=1);

namespace App\Controller;

use App\AppConfig;
use App\Domain\Sales\SalesCsvImportService;
use App\Domain\Sales\SalesPerformanceRepository;
use App\Domain\Tenant\StaffRepository;
use App\Http\Responses;
use App\Infra\CommonConnectionFactory;
use App\Infra\TenantConnectionFactory;
use App\Presentation\SalesPerformanceDetailView;
use App\Presentation\SalesPerformanceListView;
use App\Presentation\View\ListViewHelper;
use App\Security\AuthGuard;
use DateTimeImmutable;
use PDO;
use Throwable;

final class SalesPerformanceController
{
    private const ALLOWED_TYPES = ['new', 'renewal', 'addition', 'change', 'cancel_deduction'];
    private const ALLOWED_SOURCE_TYPES = ['non_life', 'life'];
    private const ALLOWED_FORM_TYPES = ['non_life', 'life'];

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
        $listUrl = $this->listUrl($criteria, $listState);

        $rows = [];
        $total = 0;
        $customers = [];
        $staffUsers = [];
        $contracts = [];
        $renewalCases = [];
        $performanceMonths = [];
        $createDraft = $this->consumeCreateDraft();
        $importBatch = null;
        $importRows = [];
        $error = null;

        $openModal = trim((string) ($_GET['open_modal'] ?? ''));
        if (!in_array($openModal, ['create_nonlife', 'create_life', 'import'], true)) {
            $openModal = '';
        }
        if ($createDraft !== null && $openModal === '') {
            $openModal = ($createDraft['form_type'] ?? '') === 'life' ? 'create_life' : 'create_nonlife';
        }

        try {
            $pdo = $this->tenantConnectionFactory->createForAuthenticatedUser($this->guard->requireAuthenticated());
            $repository = new SalesPerformanceRepository($pdo);
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
            $customers = $repository->fetchCustomers(5000);
            $staffUsers = (new StaffRepository($pdo))->findActive();
            $contracts = $repository->fetchContracts(500);
            $renewalCases = $repository->fetchRenewalCases(500);
            $performanceMonths = $repository->fetchPerformanceMonths();
            $staffUserNames = $this->buildUserNameMap($staffUsers);
            $rows = $this->attachStaffNamesToRows($rows, $staffUserNames);

            $importBatchId = (int) ($_GET['import_batch_id'] ?? 0);
            if ($importBatchId > 0) {
                $importBatch = $repository->findImportBatchById($importBatchId);
                if ($importBatch !== null) {
                    $importRows = $repository->findImportRowsByBatchId($importBatchId, 500);
                    if ($openModal === '') {
                        $openModal = 'import';
                    }
                }
            }
        } catch (Throwable) {
            $error = '成績一覧の取得に失敗しました。接続設定を確認してください。';
        }

        $flashError = $this->guard->session()->consumeFlash('error');
        $flashSuccess = $this->guard->session()->consumeFlash('success');

        Responses::html(SalesPerformanceListView::render(
            $rows,
            $total,
            $criteria,
            $listState,
            $customers,
            $staffUsers,
            $contracts,
            $renewalCases,
            $performanceMonths,
            $createDraft,
            $openModal,
            $listUrl,
            $this->config->routeUrl('sales/detail'),
            $this->config->routeUrl('sales/create'),
            $this->config->routeUrl('sales/import'),
            $this->config->routeUrl('sales/delete'),
            $this->guard->session()->issueCsrfToken('sales_create'),
            $this->guard->session()->issueCsrfToken('sales_import'),
            $this->guard->session()->issueCsrfToken('sales_delete'),
            $flashError,
            $flashSuccess,
            $error,
            self::ALLOWED_TYPES,
            $importBatch,
            $importRows,
            (string) ($_GET['filter_open'] ?? '') === '1',
            ControllerLayoutHelper::build($this->guard, $this->config, 'sales')
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
            $this->guard->session()->setFlash('error', '成績IDが不正です。');
            Responses::redirect($listUrl);
        }

        $record = null;
        $customers = [];
        $staffUsers = [];
        $contracts = [];
        $renewalCases = [];
        $audits = [];
        $error = null;

        try {
            $pdo = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            $repository = new SalesPerformanceRepository($pdo);
            $record = $repository->findById($id);
            if ($record === null) {
                $this->guard->session()->setFlash('error', '対象成績が見つかりません。');
                Responses::redirect($listUrl);
            }
            $customers = $repository->fetchCustomers(5000);
            $staffUsers = (new StaffRepository($pdo))->findActive();
            $contracts = $repository->fetchContracts(500);
            $renewalCases = $repository->fetchRenewalCases(500);
            $staffUserNames = $this->buildUserNameMap($staffUsers);
            $record = $this->attachStaffNameToRecord($record, $staffUserNames);

            $audits = $repository->findAuditEvents($id);
            $auditUserNames = $this->fetchUserNamesByRows($audits, 'changed_by');
            $audits = $this->attachAuditUserNames($audits, $auditUserNames);

            $editDraft = $this->consumeEditDraft();
            if ($editDraft !== null && (int) ($editDraft['id'] ?? 0) === $id && is_array($editDraft['input'] ?? null)) {
                $record = array_merge($record, (array) $editDraft['input']);
            }
        } catch (Throwable) {
            $error = '成績詳細の取得に失敗しました。接続設定を確認してください。';
        }

        $flashError = $this->guard->session()->consumeFlash('error');
        $flashSuccess = $this->guard->session()->consumeFlash('success');
        $detailUrl = $this->detailUrl($id, $criteria, $listState);

        Responses::html(SalesPerformanceDetailView::render(
            $record,
            $customers,
            $staffUsers,
            $contracts,
            $renewalCases,
            $audits,
            self::ALLOWED_TYPES,
            $listUrl,
            $detailUrl,
            $this->config->routeUrl('sales/update'),
            $this->config->routeUrl('sales/delete'),
            $this->guard->session()->issueCsrfToken('sales_update'),
            $this->guard->session()->issueCsrfToken('sales_delete'),
            $flashError,
            $flashSuccess,
            $error,
            $this->config->routeUrl('customer/detail'),
            $this->config->routeUrl('renewal/detail'),
            ControllerLayoutHelper::build(
                $this->guard,
                $this->config,
                'sales',
                [
                    ['label' => 'ホーム', 'url' => $this->config->routeUrl('dashboard')],
                    ['label' => '成績一覧', 'url' => $listUrl],
                    ['label' => '成績詳細'],
                ]
            )
        ));
    }

    public function import(): void
    {
        $auth = $this->guard->requireAuthenticated();
        $returnTo = $this->validateReturnTo($_POST['return_to'] ?? null);
        $token = (string) ($_POST['_csrf_token'] ?? '');
        if (!$this->guard->session()->validateAndConsumeCsrfToken('sales_import', $token)) {
            $this->guard->session()->setFlash('error', '不正な操作です。再度お試しください。');
            Responses::redirect($returnTo);
        }

        $file = $_FILES['csv_file'] ?? null;
        if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $this->guard->session()->setFlash('error', 'CSVファイルを指定してください。');
            Responses::redirect($returnTo);
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');
        $originalName = (string) ($file['name'] ?? 'upload.csv');
        if ($tmpName === '' || !is_uploaded_file($tmpName) && !is_file($tmpName)) {
            $this->guard->session()->setFlash('error', 'CSVファイルの読込に失敗しました。');
            Responses::redirect($returnTo);
        }

        try {
            $pdo = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            $repository = new SalesPerformanceRepository($pdo);
            $service = new SalesCsvImportService($repository);
            $result = $service->importFile($tmpName, $originalName, (int) ($auth['user_id'] ?? 0));

            if (($result['status'] ?? '') === 'failed') {
                $this->guard->session()->setFlash('error', 'CSV取込に失敗しました。内容を確認してください。');
            } else {
                $this->guard->session()->setFlash('success', 'CSV取込が完了しました。');
            }

            Responses::redirect(ListViewHelper::buildUrl($returnTo, [
                'import_batch_id' => (string) ((int) ($result['batch_id'] ?? 0)),
                'open_modal' => 'import',
            ]));
        } catch (Throwable) {
            $this->guard->session()->setFlash('error', 'CSV取込に失敗しました。内容を確認してください。');
            Responses::redirect(ListViewHelper::buildUrl($returnTo, ['open_modal' => 'import']));
        }
    }

    public function create(): void
    {
        $auth = $this->guard->requireAuthenticated();
        $returnTo = $this->validateReturnTo($_POST['return_to'] ?? null);
        $token = (string) ($_POST['_csrf_token'] ?? '');
        if (!$this->guard->session()->validateAndConsumeCsrfToken('sales_create', $token)) {
            $this->guard->session()->setFlash('error', '不正な操作です。再度お試しください。');
            Responses::redirect($returnTo);
        }

        $input = $this->collectInput();
        $errors = $this->validateInput($input);
        $createOpenModal = ($input['form_type'] ?? '') === 'life' ? 'create_life' : 'create_nonlife';
        if ($errors !== []) {
            $this->guard->session()->setFlash('error', implode(' ', $errors));
            $this->storeCreateDraft($input);
            Responses::redirect(ListViewHelper::buildUrl($returnTo, ['open_modal' => $createOpenModal]));
        }

        try {
            $pdo = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            $repository = new SalesPerformanceRepository($pdo);
            $repository->create($input, (int) ($auth['user_id'] ?? 0));
            $this->guard->session()->setFlash('success', '成績を登録しました。');
        } catch (Throwable) {
            $this->guard->session()->setFlash('error', '成績の登録に失敗しました。');
            $this->storeCreateDraft($input);
            Responses::redirect(ListViewHelper::buildUrl($returnTo, ['open_modal' => $createOpenModal]));
        }

        Responses::redirect($returnTo);
    }

    public function update(): void
    {
        $auth = $this->guard->requireAuthenticated();
        $returnTo = $this->validateReturnTo($_POST['return_to'] ?? null);
        $token = (string) ($_POST['_csrf_token'] ?? '');
        if (!$this->guard->session()->validateAndConsumeCsrfToken('sales_update', $token)) {
            $this->guard->session()->setFlash('error', '不正な操作です。再度お試しください。');
            Responses::redirect($returnTo);
        }

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            $this->guard->session()->setFlash('error', '成績IDが不正です。');
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
            $repository = new SalesPerformanceRepository($pdo);
            $updated = $repository->update($id, $input, (int) ($auth['user_id'] ?? 0));
            if ($updated > 0) {
                $this->guard->session()->setFlash('success', '成績を更新しました。');
            } else {
                $this->guard->session()->setFlash('error', '更新対象が見つかりません。');
                $this->storeEditDraft($id, $input);
            }
        } catch (Throwable) {
            $this->guard->session()->setFlash('error', '成績の更新に失敗しました。');
            $this->storeEditDraft($id, $input);
        }

        Responses::redirect($returnTo);
    }

    public function delete(): void
    {
        $auth = $this->guard->requireAuthenticated();
        $returnTo = $this->validateReturnTo($_POST['return_to'] ?? null);
        $token = (string) ($_POST['_csrf_token'] ?? '');
        if (!$this->guard->session()->validateAndConsumeCsrfToken('sales_delete', $token)) {
            $this->guard->session()->setFlash('error', '不正な操作です。再度お試しください。');
            Responses::redirect($returnTo);
        }

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            $this->guard->session()->setFlash('error', '成績IDが不正です。');
            Responses::redirect($returnTo);
        }

        try {
            $pdo = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            $repository = new SalesPerformanceRepository($pdo);
            $deleted = $repository->softDelete($id, (int) ($auth['user_id'] ?? 0));
            if ($deleted > 0) {
                $this->guard->session()->setFlash('success', '成績を削除しました。');
            } else {
                $this->guard->session()->setFlash('error', '削除対象が見つかりません。');
            }
        } catch (Throwable) {
            $this->guard->session()->setFlash('error', '成績の削除に失敗しました。');
        }

        Responses::redirect($this->config->routeUrl('sales/list'));
    }

    /**
     * @param array<string, mixed> $source
     * @return array<string, string>
     */
    private function extractCriteria(array $source): array
    {
        // 年度（4月始まり）のデフォルト計算はダッシュボードと同一ロジック
        $performanceFiscalYear = trim((string) ($source['performance_fiscal_year'] ?? ''));
        $performanceMonthNum   = trim((string) ($source['performance_month_num'] ?? ''));

        return [
            'performance_fiscal_year' => $performanceFiscalYear,
            'performance_month_num'   => $performanceMonthNum,
            'customer_name' => trim((string) ($source['customer_name'] ?? '')),
            'staff_id' => trim((string) ($source['staff_id'] ?? '')),
            'source_type' => trim((string) ($source['source_type'] ?? '')),
            'performance_type' => trim((string) ($source['performance_type'] ?? '')),
            'policy_no' => trim((string) ($source['policy_no'] ?? '')),
            'product_type' => trim((string) ($source['product_type'] ?? '')),
            'settlement_month' => trim((string) ($source['settlement_month'] ?? '')),
        ];
    }

    /**
     * @param array<string, mixed> $source
     * @return array<string, string>
     */
    private function extractListState(array $source): array
    {
        $sort = ListViewHelper::normalizeSort($source['sort'] ?? '', SalesPerformanceRepository::SORTABLE_FIELDS);

        return [
            'page' => (string) ListViewHelper::normalizePage($source['page'] ?? 1),
            'per_page' => (string) ListViewHelper::normalizePerPage($source['per_page'] ?? ListViewHelper::DEFAULT_PER_PAGE),
            'sort' => $sort,
            'direction' => $sort === '' ? 'desc' : ListViewHelper::normalizeDirection($source['direction'] ?? 'asc'),
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
        return ListViewHelper::buildUrl($this->config->routeUrl('sales/list'), $this->buildListQuery($criteria, $listState));
    }

    /**
     * @param array<string, string> $criteria
     * @param array<string, string> $listState
     */
    private function detailUrl(int $id, array $criteria, array $listState): string
    {
        return ListViewHelper::buildUrl(
            $this->config->routeUrl('sales/detail'),
            array_merge(['id' => (string) $id], $this->buildListQuery($criteria, $listState))
        );
    }

    private function validateReturnTo(mixed $returnTo): string
    {
        $default = $this->config->routeUrl('sales/list');
        $candidate = trim((string) $returnTo);
        if ($candidate === '' || str_contains($candidate, "\n") || str_contains($candidate, "\r")) {
            return $default;
        }

        if (str_contains($candidate, 'route=sales/list') || str_contains($candidate, 'route=sales/detail')) {
            return $candidate;
        }

        return $default;
    }

    /**
     * @return array<string, mixed>
     */
    private function collectInput(): array
    {
        $customerId = (int) ($_POST['customer_id'] ?? 0);
        $contractId = (int) ($_POST['contract_id'] ?? 0);
        $renewalCaseId = (int) ($_POST['renewal_case_id'] ?? 0);
        $staffUserId = (int) ($_POST['staff_id'] ?? 0);
        $formType = trim((string) ($_POST['form_type'] ?? ''));
        $performanceTypeDetail = trim((string) ($_POST['performance_type_detail'] ?? ''));

        // form_type + renewal_case_id → source_type + performance_type に変換
        $mapped = $this->determineBusinessCategory($formType, $renewalCaseId, $performanceTypeDetail);

        return [
            'form_type' => $formType,
            'customer_id' => $customerId,
            'contract_id' => $contractId > 0 ? $contractId : null,
            'renewal_case_id' => $renewalCaseId > 0 ? $renewalCaseId : null,
            'performance_date' => trim((string) ($_POST['performance_date'] ?? '')),
            'performance_type' => $mapped['performance_type'],
            'source_type' => $mapped['source_type'],
            'policy_no' => $this->nullableText($_POST['policy_no'] ?? null),
            'policy_start_date' => $this->nullableDate($_POST['policy_start_date'] ?? null),
            'application_date' => $this->nullableDate($_POST['application_date'] ?? null),
            'insurance_category' => $this->nullableText($_POST['insurance_category'] ?? null),
            'product_type' => $this->nullableText($_POST['product_type'] ?? null),
            'premium_amount' => trim((string) ($_POST['premium_amount'] ?? '')),
            'installment_count' => $this->nullableInt($_POST['installment_count'] ?? null),
            'receipt_no' => $this->nullableText($_POST['receipt_no'] ?? null),
            'settlement_month' => $this->nullableText($_POST['settlement_month'] ?? null),
            'staff_id' => $staffUserId > 0 ? $staffUserId : null,
            'remark' => $this->nullableText($_POST['remark'] ?? null),
        ];
    }

    /**
     * @return array{source_type: string, performance_type: string}
     */
    private function determineBusinessCategory(string $formType, int $renewalCaseId, string $performanceTypeDetail): array
    {
        if ($formType === 'life') {
            return ['source_type' => 'life', 'performance_type' => 'new'];
        }
        if ($formType === 'non_life' && $renewalCaseId > 0) {
            return ['source_type' => 'non_life', 'performance_type' => 'renewal'];
        }
        // non_life 新規（または form_type 不明時のフォールバック）
        $allowed = ['new', 'addition', 'change', 'cancel_deduction'];
        $pt = in_array($performanceTypeDetail, $allowed, true) ? $performanceTypeDetail : 'new';
        return ['source_type' => 'non_life', 'performance_type' => $pt];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<int, string>
     */
    private function validateInput(array &$input): array
    {
        $errors = [];

        $formType = (string) ($input['form_type'] ?? '');
        if (!in_array($formType, self::ALLOWED_FORM_TYPES, true)) {
            $errors[] = '業務区分は必須です。';
        }

        $type = (string) ($input['performance_type'] ?? '');
        if (!in_array($type, self::ALLOWED_TYPES, true)) {
            $errors[] = '成績区分が不正です。';
        }

        $sourceType = (string) ($input['source_type'] ?? '');
        if (!in_array($sourceType, self::ALLOWED_SOURCE_TYPES, true)) {
            $errors[] = '業務区分が不正です。';
        }

        if ((int) ($input['customer_id'] ?? 0) <= 0) {
            $errors[] = '顧客は必須です。';
        }

        $date = (string) ($input['performance_date'] ?? '');
        if (!$this->isValidDate($date)) {
            $errors[] = '成績計上日は YYYY-MM-DD 形式で指定してください。';
        }

        $installmentCount = $input['installment_count'] ?? null;
        if ($installmentCount !== null) {
            if (!is_int($installmentCount) || $installmentCount < 1 || $installmentCount > 255) {
                $errors[] = '分割回数は1-255の範囲で入力してください。';
            }
        }

        $policyStartDate = (string) ($input['policy_start_date'] ?? '');
        if ($policyStartDate !== '' && !$this->isValidDate($policyStartDate)) {
            $errors[] = '始期日は YYYY-MM-DD 形式で入力してください。';
        }

        $applicationDate = (string) ($input['application_date'] ?? '');
        if ($applicationDate !== '' && !$this->isValidDate($applicationDate)) {
            $errors[] = '申込日は YYYY-MM-DD 形式で入力してください。';
        }

        $premiumRaw = (string) ($input['premium_amount'] ?? '');
        if ($premiumRaw === '' || !is_numeric($premiumRaw)) {
            $errors[] = '保険料は数値で入力してください。';
        } else {
            $premium = (float) $premiumRaw;
            if ($premium < 0) {
                $errors[] = '保険料は0以上で入力してください。';
            }
            $input['premium_amount'] = (int) round($premium);
        }

        $settlementMonth = (string) ($input['settlement_month'] ?? '');
        if ($settlementMonth !== '' && !preg_match('/^\d{4}-\d{2}$/', $settlementMonth)) {
            $errors[] = '精算月は YYYY-MM 形式で入力してください。';
        }

        $staffUserId = $input['staff_id'] ?? null;
        if ($staffUserId !== null) {
            if (!is_int($staffUserId) || $staffUserId <= 0) {
                $errors[] = '担当者が不正です。';
            }
        }

        // 生保は精算月・分割回数・領収証番号を強制的に NULL
        if ($formType === 'life') {
            $input['settlement_month']  = null;
            $input['installment_count'] = null;
            $input['receipt_no']        = null;
        }

        return $errors;
    }

    private function isValidDate(string $value): bool
    {
        if ($value === '') {
            return false;
        }

        $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);
        return $date !== false && $date->format('Y-m-d') === $value;
    }

    private function nullableText(mixed $value): ?string
    {
        $text = trim((string) $value);
        return $text === '' ? null : $text;
    }

    private function nullableDate(mixed $value): ?string
    {
        $text = trim((string) $value);
        return $text === '' ? null : $text;
    }

    private function nullableInt(mixed $value): ?int
    {
        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }
        return is_numeric($text) ? (int) $text : null;
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

        if ($userIds === []) {
            return [];
        }

        $ids = array_keys($userIds);
        $placeholders = implode(', ', array_fill(0, count($ids), '?'));

        try {
            $pdo = $this->commonConnectionFactory->create();
            $stmt = $pdo->prepare(
                'SELECT id, COALESCE(NULLIF(display_name, ""), name) AS name FROM users WHERE id IN (' . $placeholders . ') AND status = 1 AND is_deleted = 0'
            );
            foreach ($ids as $index => $id) {
                $stmt->bindValue($index + 1, $id, PDO::PARAM_INT);
            }
            $stmt->execute();
            $names = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $names[(int) $row['id']] = (string) $row['name'];
            }

            return $names;
        } catch (Throwable) {
            return [];
        }
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

    /**
     * @param array<int, array{id:int, staff_name:string}> $users
     * @return array<int, string>
     */
    private function buildUserNameMap(array $users): array
    {
        $map = [];
        foreach ($users as $user) {
            $id = (int) ($user['id'] ?? 0);
            $name = trim((string) ($user['staff_name'] ?? $user['name'] ?? ''));
            if ($id > 0 && $name !== '') {
                $map[$id] = $name;
            }
        }

        return $map;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @param array<int, string> $staffUserNames
     * @return array<int, array<string, mixed>>
     */
    private function attachStaffNamesToRows(array $rows, array $staffUserNames): array
    {
        foreach ($rows as $index => $row) {
            if (!is_array($row)) {
                continue;
            }

            $staffUserId = (int) ($row['staff_id'] ?? 0);
            $rows[$index]['staff_user_name'] = $staffUserId > 0 ? ($staffUserNames[$staffUserId] ?? '') : '';
        }

        return $rows;
    }

    /**
     * @param array<string, mixed>|null $record
     * @param array<int, string> $staffUserNames
     * @return array<string, mixed>|null
     */
    private function attachStaffNameToRecord(?array $record, array $staffUserNames): ?array
    {
        if (!is_array($record)) {
            return null;
        }

        $staffUserId = (int) ($record['staff_id'] ?? 0);
        $record['staff_user_name'] = $staffUserId > 0 ? ($staffUserNames[$staffUserId] ?? '') : '';

        return $record;
    }

    /**
     * @param array<string, mixed> $input
     */
    private function storeCreateDraft(array $input): void
    {
        $this->guard->session()->setFlash('sales_create_form_input', json_encode($input, JSON_UNESCAPED_UNICODE) ?: '{}');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function consumeCreateDraft(): ?array
    {
        $raw = (string) $this->guard->session()->consumeFlash('sales_create_form_input');
        if ($raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param array<string, mixed> $input
     */
    private function storeEditDraft(int $id, array $input): void
    {
        $payload = ['id' => $id, 'input' => $input];
        $this->guard->session()->setFlash('sales_edit_form_payload', json_encode($payload, JSON_UNESCAPED_UNICODE) ?: '{}');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function consumeEditDraft(): ?array
    {
        $raw = (string) $this->guard->session()->consumeFlash('sales_edit_form_payload');
        if ($raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }
}
