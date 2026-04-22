<?php
declare(strict_types=1);

namespace App\Controller;

use App\AppConfig;
use App\Domain\Renewal\ContractRepository;
use App\Domain\Renewal\RenewalCaseRepository;
use App\Domain\Renewal\SjnetCsvImportService;
use App\Domain\Renewal\SjnetImportRepository;
use App\Domain\Tenant\CaseStatusRepository;
use App\Domain\Tenant\ProcedureMethodRepository;
use App\Domain\Tenant\RenewalMethodRepository;
use App\Domain\Tenant\StaffRepository;
use App\Http\Responses;
use App\Infra\CommonConnectionFactory;
use App\Infra\TenantConnectionFactory;
use App\Presentation\RenewalCaseDetailView;
use App\Presentation\RenewalCaseListView;
use App\Presentation\View\ListViewHelper;
use App\Security\AuthGuard;
use DateTimeImmutable;
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
        $allUsers = [];
        $renewalStatuses = [];

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
            // フィルタ・表示用スタッフ一覧
            $staffList = (new StaffRepository($pdo))->findActive();
            $staffMap = [];
            foreach ($staffList as $s) {
                $staffMap[(int) $s['id']] = (string) $s['staff_name'];
            }
            foreach ($rows as $index => $row) {
                $sid = (int) ($row['assigned_staff_id'] ?? 0);
                $rows[$index]['assigned_user_name'] = $sid > 0 ? ($staffMap[$sid] ?? '') : '';
            }

            $allUsers = $staffList;
            $renewalStatuses = (new CaseStatusRepository($pdo))->findByType('renewal');

            $importBatchId = (int) ($_GET['import_batch_id'] ?? 0);
            if ($importBatchId > 0) {
                $sjnetRepo = new SjnetImportRepository($pdo);
                $importBatch = $sjnetRepo->findBatchById($importBatchId);
                if ($importBatch !== null) {
                    $importRows = $sjnetRepo->findRowsByBatchId($importBatchId, 200);
                }
            }
        } catch (Throwable) {
            $error = '満期一覧の取得に失敗しました。時間をおいて再度お試しください。解消しない場合は管理者へご連絡ください。';
        }

        $flashError   = $this->guard->session()->consumeFlash('error');
        $flashSuccess = $this->guard->session()->consumeFlash('success');
        $importFlashError   = $this->guard->session()->consumeFlash('import_error');
        $importFlashSuccess = $this->guard->session()->consumeFlash('import_success');
        $openImportDialog = (string) ($_GET['import_dialog'] ?? '') === '1'
            || $importBatch !== null
            || (is_string($importFlashError)   && $importFlashError   !== '')
            || (is_string($importFlashSuccess) && $importFlashSuccess !== '');

        Responses::html(RenewalCaseListView::render(
            $rows,
            $total,
            $criteria,
            $listState,
            $this->config->routeUrl('renewal/list'),
            $this->config->routeUrl('renewal/detail'),
            $this->config->routeUrl('renewal/import'),
            $this->guard->session()->issueCsrfToken('renewal_import'),
            $importFlashError,
            $importFlashSuccess,
            $importBatch,
            $importRows,
            $openImportDialog,
            $error ?? $flashError,
            (string) ($_GET['filter_open'] ?? '') === '1',
            $allUsers,
            ControllerLayoutHelper::build($this->guard, $this->config, 'renewal'),
            $flashSuccess,
            $renewalStatuses,
            $this->config->routeUrl('customer/detail')
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

        $renewalMethods = [];

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
            $assignedStaffId = (int) ($detail['assigned_staff_id'] ?? 0);
            $officeStaffId   = (int) ($detail['office_staff_id'] ?? 0);
            $staffRepo = new StaffRepository($pdo);
            $staffMap = [];
            foreach ([$assignedStaffId, $officeStaffId] as $sid) {
                if ($sid > 0 && !isset($staffMap[$sid])) {
                    $s = $staffRepo->findById($sid);
                    if ($s !== null) {
                        $staffMap[$sid] = (string) $s['staff_name'];
                    }
                }
            }
            $detail['assigned_user_name'] = $assignedStaffId > 0 ? ($staffMap[$assignedStaffId] ?? '') : '';
            $detail['office_user_name']   = $officeStaffId   > 0 ? ($staffMap[$officeStaffId]   ?? '') : '';
            $officeStaffList = $staffRepo->findForOffice();
            $salesStaffList  = $staffRepo->findForSales();
            $staffNameMap = [];
            foreach ($staffRepo->findActive() as $s) {
                $sid = (string) ($s['id'] ?? '');
                $sname = (string) ($s['staff_name'] ?? '');
                if ($sid !== '' && $sname !== '') {
                    $staffNameMap[$sid] = $sname;
                }
            }
            $renewalStatuses = (new CaseStatusRepository($pdo))->findByType('renewal');
            $procedureMethods = (new ProcedureMethodRepository($pdo))->findAll();
            $renewalMethods = (new RenewalMethodRepository($pdo))->findAll();

            $updateInput = $this->consumeUpdateInput($renewalCaseId);
            $fieldErrors = $this->consumeUpdateErrors($renewalCaseId);
            if (is_array($updateInput)) {
                foreach (['case_status', 'next_action_date', 'renewal_method', 'procedure_method', 'completed_date', 'office_staff_id'] as $key) {
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

            $contractId = (int) ($detail['contract_id'] ?? 0);
            Responses::html(RenewalCaseDetailView::render(
                $detail,
                $comments,
                $audits,
                $this->config->routeUrl('renewal/update'),
                $this->config->routeUrl('renewal/comment'),
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
                ),
                $officeStaffList,
                $renewalStatuses,
                $procedureMethods,
                $renewalMethods,
                $this->config->routeUrl('renewal/link-customer'),
                $contractId > 0
                    ? $this->guard->session()->issueCsrfToken('renewal_link_customer_' . $contractId)
                    : '',
                $this->config->routeUrl('renewal/update-assigned-staff'),
                $this->guard->session()->issueCsrfToken('renewal_update_assigned_staff_' . $renewalCaseId),
                $salesStaffList,
                $staffNameMap
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
        $allowedStatuses = [];
        $allowedProcedureMethods = [];
        $allowedRenewalMethods = [];
        try {
            $pdoForValidation = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            $allowedStatuses = (new CaseStatusRepository($pdoForValidation))->activeNames('renewal');
            $allowedProcedureMethods = (new ProcedureMethodRepository($pdoForValidation))->findActiveNames();
            $allowedRenewalMethods = (new RenewalMethodRepository($pdoForValidation))->findActiveNames();
        } catch (\Throwable) {
            // fallback: no restriction
        }
        $fieldErrors = $this->validateUpdateInput($input, $allowedStatuses, $allowedProcedureMethods, $allowedRenewalMethods);
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
        if (mb_strlen($commentBody) > 500) {
            $this->guard->session()->setFlash('error', 'コメントは500文字以内で入力してください。');
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

    public function import(): void
    {
        $auth = $this->guard->requireAuthenticated();

        $token = (string) ($_POST['_csrf_token'] ?? '');
        if (!$this->guard->session()->validateAndConsumeCsrfToken('renewal_import', $token)) {
            $this->guard->session()->setFlash('import_error', '不正なリクエストを検出しました。');
            Responses::redirect($this->config->routeUrl('renewal/list') . '&import_dialog=1');
        }

        $returnTo = trim((string) ($_POST['return_to'] ?? ''));
        if ($returnTo === '' || !str_contains($returnTo, 'import_dialog')) {
            $returnTo = $this->config->routeUrl('renewal/list') . '&import_dialog=1';
        }

        $uploadedFile = $_FILES['csv_file'] ?? null;
        if (!is_array($uploadedFile) || ($uploadedFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $this->guard->session()->setFlash('import_error', 'CSVファイルのアップロードに失敗しました。');
            Responses::redirect($returnTo);
        }

        $tmpPath  = (string) ($uploadedFile['tmp_name'] ?? '');
        $origName = (string) ($uploadedFile['name'] ?? 'upload.csv');

        if ($tmpPath === '' || !is_file($tmpPath)) {
            $this->guard->session()->setFlash('import_error', 'アップロードファイルが見つかりません。');
            Responses::redirect($returnTo);
        }

        $result = [];
        try {
            $pdo     = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            $userId  = (int) ($auth['user_id'] ?? 0);
            $service = new SjnetCsvImportService($pdo, $userId, new DateTimeImmutable('today'));
            $result  = $service->import($tmpPath, $origName);
        } catch (Throwable $e) {
            $this->guard->session()->setFlash('import_error', 'CSV取込に失敗しました: ' . $e->getMessage());
            Responses::redirect($returnTo);
        }

        $batchId    = (int) ($result['batch_id'] ?? 0);
        $errorCount = (int) ($result['error'] ?? 0);

        if ($errorCount > 0) {
            // エラー行の証券番号を取得してメッセージに含める
            $importRepo   = new SjnetImportRepository($pdo);
            $errorRows    = array_filter(
                $importRepo->findRowsByBatchId($batchId, 200),
                static fn(array $r): bool => ($r['row_status'] ?? '') === 'error'
            );
            $policyLabels = array_map(
                static fn(array $r): string => (string) ($r['policy_no'] ?? '（証券番号なし）'),
                array_values($errorRows)
            );
            $displayMax = 20;
            $overCount  = max(0, count($policyLabels) - $displayMax);
            $policyStr  = implode('、', array_slice($policyLabels, 0, $displayMax));
            if ($overCount > 0) {
                $policyStr .= '…他' . $overCount . '件';
            }

            $successCount = (int) ($result['insert'] ?? 0) + (int) ($result['update'] ?? 0);
            if ($successCount === 0) {
                // 全件失敗
                $flashMsg = "CSV取込に失敗しました。全{$errorCount}件でエラーが発生しました。\n失敗した証券番号: {$policyStr}";
            } else {
                // 一部失敗
                $flashMsg = "CSV取込は完了しましたが、{$errorCount}件のエラーがありました。\n失敗した証券番号: {$policyStr}";
            }
            $this->guard->session()->setFlash('import_error', $flashMsg);
        } else {
            $this->guard->session()->setFlash('import_success', 'CSV取込が完了しました。');
        }

        $listUrl = $this->config->routeUrl('renewal/list');
        Responses::redirect($listUrl . '&import_batch_id=' . $batchId . '&import_dialog=1');
    }

    /**
     * 顧客紐づけ操作（設定・変更）
     * POST renewal/link-customer
     * fields: contract_id, customer_id（必須・正の整数）, renewal_case_id（戻り先用）, _csrf_token
     */
    public function linkCustomer(): void
    {
        $auth           = $this->guard->requireAuthenticated();
        $criteria       = $this->extractCriteria($_POST);
        $listState      = $this->extractListState($_POST);
        $contractId     = (int) ($_POST['contract_id'] ?? 0);
        $renewalCaseId  = (int) ($_POST['renewal_case_id'] ?? 0);

        if ($contractId <= 0 || $renewalCaseId <= 0) {
            $this->guard->session()->setFlash('error', '不正なリクエストです。');
            Responses::redirect($this->listUrl($criteria, $listState));
        }

        $token = (string) ($_POST['_csrf_token'] ?? '');
        if (!$this->guard->session()->validateAndConsumeCsrfToken('renewal_link_customer_' . $contractId, $token)) {
            $this->guard->session()->setFlash('error', '不正な更新要求を検出しました。');
            Responses::redirect($this->detailUrl($renewalCaseId, $criteria, $listState));
        }

        $customerIdRaw = trim((string) ($_POST['customer_id'] ?? ''));
        if ($customerIdRaw === '' || !ctype_digit($customerIdRaw) || (int) $customerIdRaw <= 0) {
            $this->guard->session()->setFlash('error', '顧客が選択されていません。');
            Responses::redirect($this->detailUrl($renewalCaseId, $criteria, $listState));
        }
        $newCustomerId = (int) $customerIdRaw;

        try {
            $pdo    = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            $repo   = new ContractRepository($pdo);
            $userId = (int) ($auth['user_id'] ?? 0);
            $repo->linkCustomer($contractId, $newCustomerId, $userId, $renewalCaseId);

            $this->guard->session()->setFlash('success', '顧客を紐づけました。');
        } catch (Throwable) {
            $this->guard->session()->setFlash('error', '顧客の紐づけ操作に失敗しました。');
        }

        Responses::redirect($this->detailUrl($renewalCaseId, $criteria, $listState));
    }

    /**
     * 営業担当の変更
     * POST renewal/update-assigned-staff
     * fields: renewal_case_id, assigned_staff_id（0 or 空 で未設定）, _csrf_token
     */
    public function updateAssignedStaff(): void
    {
        $auth          = $this->guard->requireAuthenticated();
        $criteria      = $this->extractCriteria($_POST);
        $listState     = $this->extractListState($_POST);
        $renewalCaseId = (int) ($_POST['renewal_case_id'] ?? 0);

        if ($renewalCaseId <= 0) {
            $this->guard->session()->setFlash('error', '不正なリクエストです。');
            Responses::redirect($this->listUrl($criteria, $listState));
        }

        $token = (string) ($_POST['_csrf_token'] ?? '');
        if (!$this->guard->session()->validateAndConsumeCsrfToken('renewal_update_assigned_staff_' . $renewalCaseId, $token)) {
            $this->guard->session()->setFlash('error', '不正な更新要求を検出しました。');
            Responses::redirect($this->detailUrl($renewalCaseId, $criteria, $listState));
        }

        $raw = trim((string) ($_POST['assigned_staff_id'] ?? ''));
        $assignedStaffId = null;
        if ($raw !== '') {
            if (!ctype_digit($raw)) {
                $this->guard->session()->setFlash('error', '営業担当の指定が不正です。');
                Responses::redirect($this->detailUrl($renewalCaseId, $criteria, $listState));
            }
            $assignedStaffId = (int) $raw;
            if ($assignedStaffId < 0) {
                $assignedStaffId = null;
            }
        }

        try {
            $pdo        = $this->tenantConnectionFactory->createForAuthenticatedUser($auth);
            $repository = new RenewalCaseRepository($pdo);
            $updated    = $repository->updateAssignedStaff($renewalCaseId, $assignedStaffId, (int) ($auth['user_id'] ?? 0));
            if ($updated) {
                $this->guard->session()->setFlash('success', '営業担当を更新しました。');
            } else {
                $this->guard->session()->setFlash('error', '更新対象が見つかりません。');
            }
        } catch (Throwable) {
            $this->guard->session()->setFlash('error', '営業担当の更新に失敗しました。');
        }

        Responses::redirect($this->detailUrl($renewalCaseId, $criteria, $listState));
    }

    /**
     * @param array<string, mixed> $source
     * @return array<string, string>
     */
    private function collectUpdateInput(array $source): array
    {
        return [
            'case_status'      => trim((string) ($source['case_status'] ?? '')),
            'next_action_date' => trim((string) ($source['next_action_date'] ?? '')),
            'renewal_method'   => trim((string) ($source['renewal_method'] ?? '')),
            'procedure_method' => trim((string) ($source['procedure_method'] ?? '')),
            'completed_date'   => trim((string) ($source['completed_date'] ?? '')),
            'office_staff_id'  => trim((string) ($source['office_staff_id'] ?? '')),
        ];
    }

    /**
     * @param array<string, string> $input
     * @param list<string> $allowedStatuses
     * @param list<string> $allowedProcedureMethods
     * @return array<string, string>
     */
    private function validateUpdateInput(array $input, array $allowedStatuses = [], array $allowedProcedureMethods = [], array $allowedRenewalMethods = []): array
    {
        // マスタ取得失敗時は検証をスキップ（データベース異常）
        $allowedResults = ['', 'pending', 'renewed', 'cancelled', 'lost'];
        // allowedRenewalMethods が空のとき（DB 取得失敗）は検証をスキップする
        if ($allowedRenewalMethods !== []) {
            $allowedRenewalMethods = array_merge([''], $allowedRenewalMethods);
        }
        if ($allowedProcedureMethods === []) {
            $allowedProcedureMethods = ['対面', '対面ナビ', '電話ナビ', '電話募集', '署名・捺印', 'ケータイOR', 'マイページ'];
        }
        $allowedProcedureMethods = array_merge([''], $allowedProcedureMethods);

        $caseStatus = $input['case_status'] ?? '';
        $nextActionDate = $input['next_action_date'] ?? '';
        $renewalResult = $input['renewal_result'] ?? '';
        $renewalMethod = $input['renewal_method'] ?? '';
        $procedureMethod = $input['procedure_method'] ?? '';

        $errors = [];

        if ($allowedStatuses !== [] && !in_array($caseStatus, $allowedStatuses, true)) {
            $errors['case_status'] = '対応ステータスを選択してください。';
        }

        if (!in_array($renewalResult, $allowedResults, true)) {
            $errors['renewal_result'] = '更改結果が不正です。';
        }

        if ($allowedRenewalMethods !== [] && !in_array($renewalMethod, $allowedRenewalMethods, true)) {
            $errors['renewal_method'] = '更改方法が不正です。';
        }

        if (!in_array($procedureMethod, $allowedProcedureMethods, true)) {
            $errors['procedure_method'] = '手続方法が不正です。';
        }

        $completedDate = $input['completed_date'] ?? '';
        if ($completedDate !== '' && !$this->isValidDate($completedDate)) {
            $errors['completed_date'] = '完了日は YYYY-MM-DD 形式で入力してください。';
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
        $window = trim((string) ($source['maturity_window'] ?? 'all'));
        if (!in_array($window, ['30', '60', '90', 'all'], true)) {
            $window = 'all';
        }

        return [
            'customer_name'    => trim((string) ($source['customer_name'] ?? '')),
            'policy_no'        => trim((string) ($source['policy_no'] ?? '')),
            'case_status'      => trim((string) ($source['case_status'] ?? '')),
            'maturity_window'  => $window,
            'assigned_staff_id' => trim((string) ($source['assigned_staff_id'] ?? '')),
            'product_type'     => trim((string) ($source['product_type'] ?? '')),
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
     * @return array<int, string>  [user_id => name]
     */
    private function fetchAllActiveUsers(): array
    {
        try {
            $pdo = $this->commonConnectionFactory->create();
            $stmt = $pdo->prepare(
                'SELECT id, COALESCE(NULLIF(display_name, ""), name) AS name
                 FROM users
                 WHERE status = 1
                   AND is_deleted = 0
                 ORDER BY name ASC'
            );
            $stmt->execute();
            $names = [];
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
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