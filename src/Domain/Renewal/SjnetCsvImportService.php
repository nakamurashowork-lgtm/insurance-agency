<?php
declare(strict_types=1);

namespace App\Domain\Renewal;

use App\Domain\Tenant\StaffRepository;
use DateTimeImmutable;
use PDO;
use Throwable;

final class SjnetCsvImportService
{
    private const EXPECTED_COLUMNS = 44;

    // 0-indexed column positions
    private const COL_MATURITY_MONTH  = 1;   // B: 満期日（月）
    private const COL_MATURITY_DAY    = 2;   // C: 満期日（日）
    private const COL_CUSTOMER_NAME   = 3;   // D: 顧客名
    private const COL_POSTAL_CODE     = 5;   // F: 郵便番号
    private const COL_ADDRESS1        = 6;   // G: 住所
    private const COL_PHONE           = 7;   // H: ＴＥＬ
    private const COL_INSURER_NAME    = 14;  // O: 保険会社
    private const COL_START_DATE      = 15;  // P: 保険始期
    private const COL_END_DATE        = 16;  // Q: 保険終期
    private const COL_PRODUCT_TYPE    = 17;  // R: 種目種類
    private const COL_POLICY_NO       = 18;  // S: 証券番号
    private const COL_PAYMENT_CYCLE   = 19;  // T: 払込方法
    private const COL_PREMIUM_AMOUNT  = 22;  // W: 合計保険料
    private const COL_SJNET_STAFF_NAME = 42; // AQ: 担当者
    private const COL_SJNET_AGENCY_CODE = 43; // AR: 代理店ｺｰﾄﾞ（実際のCSVヘッダは半角カタカナ）

    public function __construct(
        private PDO $pdo,
        private int $executedBy,
        private DateTimeImmutable $importDate
    ) {
    }

    /**
     * CSVファイルを取り込む
     *
     * @return array{batch_id: int, total: int, valid: int, skip: int, insert: int, update: int, customer_insert: int, error: int}
     */
    public function import(string $filePath, string $originalFileName): array
    {
        $importRepo = new SjnetImportRepository($this->pdo);

        // エンコーディング検出・変換
        $raw = (string) file_get_contents($filePath);
        [$encoding, $content] = $this->decodeContent($raw);

        // バッチ作成
        $batchId = $importRepo->createBatch($originalFileName, $encoding, $this->executedBy);

        $counters = [
            'total'           => 0,
            'valid'           => 0,
            'skip'            => 0,
            'insert'          => 0,
            'update'          => 0,
            'customer_insert' => 0,
            'error'           => 0,
        ];

        try {
            $lines = $this->parseCsvLines($content);
            $dataRows = array_slice($lines, 1); // ヘッダ行をスキップ

            foreach ($dataRows as $rowIndex => $cols) {
                $rowNo = $rowIndex + 2; // 2行目から（1行目がヘッダ）
                $counters['total']++;

                $rowData = $this->processRow($cols, $rowNo, $counters);
                $importRepo->insertRow($batchId, $rowNo, $rowData);
            }
        } catch (Throwable) {
            $importRepo->failBatch($batchId);
            throw new \RuntimeException('CSV取込中に予期しないエラーが発生しました。');
        }

        $importRepo->finishBatch($batchId, $counters);

        return array_merge(['batch_id' => $batchId], $counters);
    }

    /**
     * @return array{0: string, 1: string}  [encoding, utf8_content]
     */
    private function decodeContent(string $raw): array
    {
        // BOMチェック（UTF-8 BOM）
        if (str_starts_with($raw, "\xEF\xBB\xBF")) {
            return ['UTF-8 (BOM)', substr($raw, 3)];
        }

        // mb_detect_encoding でチェック
        $detected = mb_detect_encoding($raw, ['UTF-8', 'SJIS', 'SJIS-win', 'CP932', 'EUC-JP'], true);

        if ($detected === false) {
            // 検出不能 → UTF-8 として扱う
            return ['UTF-8 (unknown)', $raw];
        }

        if (in_array($detected, ['SJIS', 'SJIS-win', 'CP932'], true)) {
            $converted = mb_convert_encoding($raw, 'UTF-8', $detected);
            return [$detected, $converted !== false ? $converted : $raw];
        }

        return [$detected, $raw];
    }

    /**
     * CSV文字列を行・列の配列に分解する
     *
     * @return array<int, array<int, string>>
     */
    private function parseCsvLines(string $content): array
    {
        // 改行コードを統一
        $content = str_replace(["\r\n", "\r"], "\n", $content);
        $lines   = [];
        $stream  = fopen('php://temp', 'r+');
        if ($stream === false) {
            return [];
        }
        fwrite($stream, $content);
        rewind($stream);

        while (!feof($stream)) {
            $row = fgetcsv($stream, 0, ',', '"', '\\');
            if ($row === false) {
                continue;
            }
            // CSVヘッダ・値の前後空白を除去（実際のCSVにはヘッダ末尾スペースが含まれる場合がある）
            $lines[] = array_map('trim', $row);
        }
        fclose($stream);

        return $lines;
    }

    /**
     * 1行を処理して結果配列を返す
     *
     * @param array<int, string> $cols
     * @param array{total: int, valid: int, skip: int, insert: int, update: int, customer_insert: int, error: int} $counters
     * @return array<string, mixed>
     */
    private function processRow(array $cols, int $rowNo, array &$counters): array
    {
        // 列数が足りない行はスキップ
        $maxIndex = self::EXPECTED_COLUMNS - 1;
        while (count($cols) <= $maxIndex) {
            $cols[] = '';
        }

        $raw = $cols;

        $policyNo     = trim($cols[self::COL_POLICY_NO]);
        $customerName = trim($cols[self::COL_CUSTOMER_NAME]);
        $maturityMonth = trim($cols[self::COL_MATURITY_MONTH]);
        $maturityDay   = trim($cols[self::COL_MATURITY_DAY]);

        // STEP 1: 証券番号チェック
        if ($policyNo === '') {
            $counters['skip']++;
            return [
                'raw'          => $raw,
                'policy_no'    => null,
                'customer_name' => $customerName ?: null,
                'row_status'   => 'skip',
                'error_message' => '証券番号が空のためスキップ',
            ];
        }

        // スキップ条件: 顧客名・満期月日が空
        if ($customerName === '' || $maturityMonth === '' || $maturityDay === '') {
            $counters['skip']++;
            return [
                'raw'          => $raw,
                'policy_no'    => $policyNo,
                'customer_name' => $customerName ?: null,
                'row_status'   => 'skip',
                'error_message' => '顧客名または満期月日が空のためスキップ',
            ];
        }

        $counters['valid']++;

        // 満期日の決定
        $maturityDate = $this->resolveMaturityDate((int) $maturityMonth, (int) $maturityDay);
        if ($maturityDate === null) {
            $counters['error']++;
            return [
                'raw'          => $raw,
                'policy_no'    => $policyNo,
                'customer_name' => $customerName,
                'row_status'   => 'error',
                'error_message' => '満期月日が不正: ' . $maturityMonth . '月' . $maturityDay . '日',
            ];
        }

        // STEP 2: 顧客の解決
        $agencyCode  = trim($cols[self::COL_SJNET_AGENCY_CODE]);
        $sjnetStaffName = trim($cols[self::COL_SJNET_STAFF_NAME]);

        [$customerId, $customerWasInserted, $customerError] = $this->resolveCustomer($customerName, $cols);
        if ($customerError !== null) {
            $counters['error']++;
            return [
                'raw'           => $raw,
                'policy_no'     => $policyNo,
                'customer_name' => $customerName,
                'maturity_date' => $maturityDate,
                'sjnet_agency_code' => $agencyCode ?: null,
                'sjnet_staff_name'  => $sjnetStaffName ?: null,
                'row_status'    => 'error',
                'error_message' => $customerError,
            ];
        }

        if ($customerWasInserted) {
            $counters['customer_insert']++;
        }

        // STEP 3: 担当者の解決
        [$resolvedUserId, $mappingStatus] = $this->resolveStaff($agencyCode);

        // STEP 4: 契約の登録・更新
        $insurerName    = trim($cols[self::COL_INSURER_NAME]);
        $startDate      = $this->parseDate(trim($cols[self::COL_START_DATE]));
        $endDate        = $this->parseDate(trim($cols[self::COL_END_DATE]));
        $productType    = trim($cols[self::COL_PRODUCT_TYPE]);
        $paymentCycle   = trim($cols[self::COL_PAYMENT_CYCLE]);
        $premiumAmount  = $this->parsePremium(trim($cols[self::COL_PREMIUM_AMOUNT]));

        [$contractId, $contractWasInserted] = $this->upsertContract(
            $policyNo,
            (int) $customerId,
            $insurerName,
            $startDate,
            $endDate,
            $productType,
            $paymentCycle,
            $premiumAmount,
            $resolvedUserId,
            $maturityDate
        );

        if ($contractWasInserted) {
            $counters['insert']++;
        } else {
            $counters['update']++;
        }

        // STEP 5: 満期案件の登録・更新
        [$renewalCaseId, $renewalCaseWasInserted] = $this->upsertRenewalCase($contractId, $maturityDate, $resolvedUserId);

        // 契約が新規 OR 満期案件が新規 → insert として扱う
        $isInsert = $contractWasInserted || $renewalCaseWasInserted;
        if ($isInsert) {
            // contractWasInserted で既に insert++ されている場合は重複しないよう調整
            if (!$contractWasInserted) {
                // contract は update だったが renewal case は insert → update を取り消して insert に変更
                $counters['update']--;
                $counters['insert']++;
            }
            // contractWasInserted=true の場合は既に insert++ 済みなので何もしない
        }

        return [
            'raw'                    => $raw,
            'policy_no'              => $policyNo,
            'customer_name'          => $customerName,
            'maturity_date'          => $maturityDate,
            'sjnet_agency_code'      => $agencyCode ?: null,
            'sjnet_staff_name'       => $sjnetStaffName ?: null,
            'resolved_staff_id' => $resolvedUserId,
            'staff_mapping_status'   => $mappingStatus,
            'matched_contract_id'    => $contractId,
            'matched_renewal_case_id' => $renewalCaseId,
            'row_status'             => $isInsert ? 'insert' : 'update',
        ];
    }

    /**
     * 満期年を決定して YYYY-MM-DD 形式で返す
     */
    private function resolveMaturityDate(int $month, int $day): ?string
    {
        if ($month < 1 || $month > 12 || $day < 1 || $day > 31) {
            return null;
        }

        $importYear = (int) $this->importDate->format('Y');

        $candidateA = DateTimeImmutable::createFromFormat('Y-n-j', $importYear . '-' . $month . '-' . $day);
        if ($candidateA === false) {
            return null;
        }

        if ($this->importDate <= $candidateA) {
            return $candidateA->format('Y-m-d');
        }

        // 翌年
        $candidateB = DateTimeImmutable::createFromFormat('Y-n-j', ($importYear + 1) . '-' . $month . '-' . $day);
        if ($candidateB === false) {
            return null;
        }

        return $candidateB->format('Y-m-d');
    }

    /**
     * 顧客を解決（名寄せ or 新規登録）
     *
     * @param array<int, string> $cols
     * @return array{0: int|null, 1: bool, 2: string|null}  [customer_id, was_inserted, error_message]
     */
    private function resolveCustomer(string $customerName, array $cols): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id FROM m_customer
             WHERE customer_name = :name
               AND is_deleted = 0
             LIMIT 3'
        );
        $stmt->bindValue(':name', $customerName);
        $stmt->execute();
        $found = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!is_array($found)) {
            $found = [];
        }

        if (count($found) === 1) {
            return [(int) $found[0]['id'], false, null];
        }

        if (count($found) >= 2) {
            return [null, false, '顧客名が複数一致しています（ambiguous_customer）: ' . $customerName];
        }

        // 0件 → 新規登録
        $postalCode = trim($cols[self::COL_POSTAL_CODE]) ?: null;
        $address1   = trim($cols[self::COL_ADDRESS1]) ?: null;
        $phone      = trim($cols[self::COL_PHONE]) ?: null;

        $stmt = $this->pdo->prepare(
            'INSERT INTO m_customer
               (customer_type, customer_name, postal_code, address1, phone,
                status, is_deleted, created_by, updated_by)
             VALUES
               (\'individual\', :name, :postal_code, :address1, :phone,
                \'active\', 0, :created_by, :updated_by)'
        );
        $stmt->bindValue(':name', $customerName);
        $stmt->bindValue(':postal_code', $postalCode);
        $stmt->bindValue(':address1', $address1);
        $stmt->bindValue(':phone', $phone);
        $stmt->bindValue(':created_by', $this->executedBy, PDO::PARAM_INT);
        $stmt->bindValue(':updated_by', $this->executedBy, PDO::PARAM_INT);
        $stmt->execute();

        return [(int) $this->pdo->lastInsertId(), true, null];
    }

    /**
     * 担当者を解決する
     *
     * @return array{0: int|null, 1: string|null}  [resolved_user_id, mapping_status]
     */
    private function resolveStaff(string $agencyCode): array
    {
        if ($agencyCode === '') {
            return [null, null];
        }

        $repo = new StaffRepository($this->pdo);
        $staff = $repo->findBySjnetCode($agencyCode);

        if ($staff === null) {
            return [null, 'unresolved'];
        }

        $isActive = (int) ($staff['is_active'] ?? 0);
        if ($isActive === 0) {
            return [null, 'inactive'];
        }

        return [(int) $staff['id'], 'resolved'];
    }

    /**
     * 契約をINSERT or UPDATE し、contract_id と was_inserted を返す
     *
     * 検索キー: policy_no + policy_end_date（同一証券番号・同一終期 = 同一年度の契約）
     * ヒット → UPDATE（同年度の再取込）
     * ミス   → INSERT（新年度の契約として新規作成）
     *
     * @return array{0: int, 1: bool}  [contract_id, was_inserted]
     */
    private function upsertContract(
        string $policyNo,
        int $customerId,
        string $insurerName,
        ?string $startDate,
        ?string $endDate,
        string $productType,
        string $paymentCycle,
        ?int $premiumAmount,
        ?int $resolvedUserId,
        string $maturityDate
    ): array {
        // 証券番号 + 終期日（policy_end_date）で同一年度の契約を検索する。
        // policy_end_date が CSV に存在しない（null）の場合は IS NULL で照合する。
        if ($endDate !== null) {
            $stmt = $this->pdo->prepare(
                'SELECT id, sales_staff_id
                 FROM t_contract
                 WHERE policy_no = :policy_no
                   AND policy_end_date = :end_date
                   AND is_deleted = 0
                 LIMIT 1'
            );
            $stmt->bindValue(':policy_no', $policyNo);
            $stmt->bindValue(':end_date', $endDate);
        } else {
            $stmt = $this->pdo->prepare(
                'SELECT id, sales_staff_id
                 FROM t_contract
                 WHERE policy_no = :policy_no
                   AND policy_end_date IS NULL
                   AND is_deleted = 0
                 LIMIT 1'
            );
            $stmt->bindValue(':policy_no', $policyNo);
        }
        $stmt->execute();
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        $now = $this->importDate->format('Y-m-d H:i:s');

        if (is_array($existing)) {
            $contractId     = (int) $existing['id'];
            $existingUserId = $existing['sales_staff_id'] !== null ? (int) $existing['sales_staff_id'] : null;

            // sales_staff_id は未設定の場合のみ上書き
            $newSalesUserId = $existingUserId === null ? $resolvedUserId : $existingUserId;

            $stmt = $this->pdo->prepare(
                'UPDATE t_contract
                 SET insurer_name            = :insurer,
                     policy_start_date       = :start_date,
                     policy_end_date         = :end_date,
                     product_type            = :product_type,
                     payment_cycle           = :payment_cycle,
                     premium_amount          = :premium,
                     sales_staff_id           = :sales_staff_id,
                     last_sjnet_imported_at  = :imported_at,
                     updated_by              = :updated_by
                 WHERE id = :id'
            );
            $stmt->bindValue(':insurer', $insurerName !== '' ? $insurerName : null);
            $stmt->bindValue(':start_date', $startDate);
            $stmt->bindValue(':end_date', $endDate);
            $stmt->bindValue(':product_type', $productType !== '' ? $productType : null);
            $stmt->bindValue(':payment_cycle', $paymentCycle !== '' ? $paymentCycle : null);
            $stmt->bindValue(':premium', $premiumAmount ?? 0, PDO::PARAM_INT);
            $stmt->bindValue(':sales_staff_id', $newSalesUserId, $newSalesUserId !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
            $stmt->bindValue(':imported_at', $now);
            $stmt->bindValue(':updated_by', $this->executedBy, PDO::PARAM_INT);
            $stmt->bindValue(':id', $contractId, PDO::PARAM_INT);
            $stmt->execute();

            return [$contractId, false];
        }

        // INSERT
        $stmt = $this->pdo->prepare(
            'INSERT INTO t_contract
               (customer_id, policy_no, insurer_name,
                policy_start_date, policy_end_date,
                product_type, payment_cycle, premium_amount,
                status, sales_staff_id, last_sjnet_imported_at,
                is_deleted, created_by, updated_by)
             VALUES
               (:customer_id, :policy_no, :insurer,
                :start_date, :end_date,
                :product_type, :payment_cycle, :premium,
                \'active\', :sales_staff_id, :imported_at,
                0, :created_by, :updated_by)'
        );
        $stmt->bindValue(':customer_id', $customerId, PDO::PARAM_INT);
        $stmt->bindValue(':policy_no', $policyNo);
        $stmt->bindValue(':insurer', $insurerName !== '' ? $insurerName : null);
        $stmt->bindValue(':start_date', $startDate);
        $stmt->bindValue(':end_date', $endDate);
        $stmt->bindValue(':product_type', $productType !== '' ? $productType : null);
        $stmt->bindValue(':payment_cycle', $paymentCycle !== '' ? $paymentCycle : null);
        $stmt->bindValue(':premium', $premiumAmount ?? 0, PDO::PARAM_INT);
        $stmt->bindValue(':sales_staff_id', $resolvedUserId, $resolvedUserId !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':imported_at', $now);
        $stmt->bindValue(':created_by', $this->executedBy, PDO::PARAM_INT);
        $stmt->bindValue(':updated_by', $this->executedBy, PDO::PARAM_INT);
        $stmt->execute();

        $newContractId = (int) $this->pdo->lastInsertId();

        // 新年度契約を INSERT した場合、同一証券番号の旧案件を自動クローズする
        $this->closeOldRenewalCases($policyNo, $newContractId, $maturityDate);

        return [$newContractId, true];
    }

    /**
     * 新年度契約の INSERT 時に、同一証券番号の旧満期案件を自動クローズする
     *
     * 対象: 同一 policy_no の旧契約に紐づく満期案件のうち
     *       case_status IN ('renewed', 'lost') かつ maturity_date < 新案件の maturity_date
     * 対応途中（not_started/sj_requested 等）の旧案件はクローズしない。
     */
    private function closeOldRenewalCases(string $policyNo, int $newContractId, string $newMaturityDate): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE t_renewal_case rc
               JOIN t_contract c ON c.id = rc.contract_id
             SET rc.case_status = \'closed\',
                 rc.updated_by  = :updated_by
             WHERE c.policy_no         = :policy_no
               AND c.id               != :new_contract_id
               AND rc.maturity_date    < :new_maturity_date
               AND rc.case_status     IN (\'renewed\', \'lost\')
               AND rc.is_deleted       = 0'
        );
        $stmt->bindValue(':updated_by', $this->executedBy, PDO::PARAM_INT);
        $stmt->bindValue(':policy_no', $policyNo);
        $stmt->bindValue(':new_contract_id', $newContractId, PDO::PARAM_INT);
        $stmt->bindValue(':new_maturity_date', $newMaturityDate);
        $stmt->execute();
    }

    /**
     * 満期案件をINSERT or UPDATE し、[renewal_case_id, was_inserted] を返す
     *
     * @return array{0: int, 1: bool}
     */
    private function upsertRenewalCase(int $contractId, string $maturityDate, ?int $resolvedUserId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, assigned_staff_id
             FROM t_renewal_case
             WHERE contract_id = :contract_id
               AND maturity_date = :maturity_date
               AND is_deleted = 0
             LIMIT 1'
        );
        $stmt->bindValue(':contract_id', $contractId, PDO::PARAM_INT);
        $stmt->bindValue(':maturity_date', $maturityDate);
        $stmt->execute();
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if (is_array($existing)) {
            $renewalId      = (int) $existing['id'];
            $existingUserId = $existing['assigned_staff_id'] !== null ? (int) $existing['assigned_staff_id'] : null;

            // assigned_staff_id は未設定の場合のみ上書き
            if ($existingUserId === null && $resolvedUserId !== null) {
                $stmt = $this->pdo->prepare(
                    'UPDATE t_renewal_case
                     SET assigned_staff_id = :uid, updated_by = :updated_by
                     WHERE id = :id'
                );
                $stmt->bindValue(':uid', $resolvedUserId, PDO::PARAM_INT);
                $stmt->bindValue(':updated_by', $this->executedBy, PDO::PARAM_INT);
                $stmt->bindValue(':id', $renewalId, PDO::PARAM_INT);
                $stmt->execute();
            }

            return [$renewalId, false];
        }

        // office_staff_id を t_contract から引き継ぐ
        $contractStmt = $this->pdo->prepare(
            'SELECT office_staff_id FROM t_contract WHERE id = :id LIMIT 1'
        );
        $contractStmt->bindValue(':id', $contractId, PDO::PARAM_INT);
        $contractStmt->execute();
        $contractRow = $contractStmt->fetch(PDO::FETCH_ASSOC);
        $officeUserId = is_array($contractRow) && $contractRow['office_staff_id'] !== null
            ? (int) $contractRow['office_staff_id'] : null;

        $stmt = $this->pdo->prepare(
            'INSERT INTO t_renewal_case
               (contract_id, maturity_date, case_status,
                assigned_staff_id, office_staff_id,
                is_deleted, created_by, updated_by)
             VALUES
               (:contract_id, :maturity_date, \'not_started\',
                :assigned_uid, :office_uid,
                0, :created_by, :updated_by)'
        );
        $stmt->bindValue(':contract_id', $contractId, PDO::PARAM_INT);
        $stmt->bindValue(':maturity_date', $maturityDate);
        $stmt->bindValue(':assigned_uid', $resolvedUserId, $resolvedUserId !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':office_uid', $officeUserId, $officeUserId !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':created_by', $this->executedBy, PDO::PARAM_INT);
        $stmt->bindValue(':updated_by', $this->executedBy, PDO::PARAM_INT);
        $stmt->execute();

        return [(int) $this->pdo->lastInsertId(), true];
    }

    /**
     * 日付文字列を YYYY-MM-DD に変換する（YYYY/MM/DD または YYYY-MM-DD を受け付ける）
     */
    private function parseDate(string $value): ?string
    {
        if ($value === '') {
            return null;
        }
        $v = str_replace('/', '-', $value);
        $d = DateTimeImmutable::createFromFormat('Y-m-d', $v);
        if ($d !== false && $d->format('Y-m-d') === $v) {
            return $v;
        }
        return null;
    }

    /**
     * 保険料文字列を整数に変換する（カンマ区切り除去）
     */
    private function parsePremium(string $value): ?int
    {
        if ($value === '') {
            return null;
        }
        $cleaned = preg_replace('/[^\d]/', '', $value);
        if ($cleaned === null || $cleaned === '') {
            return null;
        }
        return (int) $cleaned;
    }
}
