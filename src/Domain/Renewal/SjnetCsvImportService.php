<?php
declare(strict_types=1);

namespace App\Domain\Renewal;

use App\Domain\Tenant\StaffRepository;
use DateTimeImmutable;
use PDO;
use Throwable;

final class SjnetCsvImportService
{
    // CSV ヘッダ名（必須）
    private const HDR_POLICY_NO      = '証券番号';
    private const HDR_CUSTOMER_NAME  = '顧客名';
    private const HDR_BIRTH_DATE     = '生年月日';
    private const HDR_END_DATE       = '保険終期';
    private const HDR_PRODUCT_TYPE   = '種目種類';
    private const HDR_PREMIUM_AMOUNT = '合計保険料';
    private const HDR_AGENCY_CODE    = '代理店ｺｰﾄﾞ'; // 半角カタカナ

    // CSV ヘッダ名（任意）
    private const HDR_POSTAL_CODE   = '郵便番号';
    private const HDR_ADDRESS1      = '住所';
    private const HDR_PHONE         = 'ＴＥＬ';
    private const HDR_START_DATE    = '保険始期';
    private const HDR_PAYMENT_CYCLE = '払込方法';
    private const HDR_STAFF_NAME    = '担当者';

    private const REQUIRED_HEADERS = [
        self::HDR_POLICY_NO,
        self::HDR_CUSTOMER_NAME,
        self::HDR_BIRTH_DATE,
        self::HDR_END_DATE,
        self::HDR_PRODUCT_TYPE,
        self::HDR_PREMIUM_AMOUNT,
        self::HDR_AGENCY_CODE,
    ];

    public function __construct(
        private PDO $pdo,
        private int $executedBy,
        private DateTimeImmutable $importDate
    ) {
    }

    /**
     * CSVファイルを取り込む
     *
     * @return array{batch_id: int, total: int, valid: int, skip: int, insert: int, update: int, customer_insert: int, unlinked: int, error: int}
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
            'unlinked'        => 0,
            'error'           => 0,
        ];

        $lines = $this->parseCsvLines($content);

        // 空ファイル → 0 件取込として正常終了（エラーにしない）
        if (count($lines) === 0) {
            $importRepo->finishBatch($batchId, $counters);
            return array_merge(['batch_id' => $batchId], $counters);
        }

        // ヘッダ行を解析して colMap を構築
        $colMap = [];
        foreach ($lines[0] as $idx => $header) {
            $colMap[$header] = $idx;
        }

        // 必須ヘッダの存在チェック（不足時は fail して例外を投げる）
        foreach (self::REQUIRED_HEADERS as $required) {
            if (!array_key_exists($required, $colMap)) {
                $importRepo->failBatch($batchId);
                throw new \RuntimeException('CSVヘッダが不足しています: ' . $required);
            }
        }

        $dataRows = array_slice($lines, 1); // ヘッダ行をスキップ

        foreach ($dataRows as $rowIndex => $cols) {
            $rowNo = $rowIndex + 2; // 2行目から（1行目がヘッダ）
            $counters['total']++;
            $countersBefore = $counters; // processRow 前のスナップショット（total 込み）

            try {
                $this->pdo->beginTransaction();
                $rowData = $this->processRow($cols, $rowNo, $counters, $colMap);
                $this->pdo->commit();
            } catch (Throwable $e) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                // カウンタを processRow 前の状態に戻して error++ する
                $counters = $countersBefore;
                $counters['error']++;
                $policyNoRaw     = $this->col($cols, $colMap, self::HDR_POLICY_NO);
                $customerNameRaw = $this->col($cols, $colMap, self::HDR_CUSTOMER_NAME);
                $rowData = [
                    'raw'           => $cols,
                    'policy_no'     => $policyNoRaw !== '' ? $policyNoRaw : null,
                    'customer_name' => $customerNameRaw !== '' ? $customerNameRaw : null,
                    'row_status'    => 'error',
                    'error_message' => 'システムエラー: ' . $e->getMessage(),
                ];
            }

            // ログ書き込みはトランザクション外で必ず実行する
            $importRepo->insertRow($batchId, $rowNo, $rowData);
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
     * @param array{total: int, valid: int, skip: int, insert: int, update: int, customer_insert: int, unlinked: int, error: int} $counters
     * @param array<string, int> $colMap ヘッダ名 → 列インデックスのマップ
     * @return array<string, mixed>
     */
    private function processRow(array $cols, int $rowNo, array &$counters, array $colMap): array
    {
        $raw = $cols;

        $policyNo     = $this->col($cols, $colMap, self::HDR_POLICY_NO);
        $customerName = $this->col($cols, $colMap, self::HDR_CUSTOMER_NAME);
        $endDateRaw   = $this->col($cols, $colMap, self::HDR_END_DATE);

        // STEP 1: 証券番号チェック
        if ($policyNo === '') {
            $counters['skip']++;
            return [
                'raw'           => $raw,
                'policy_no'     => null,
                'customer_name' => $customerName ?: null,
                'row_status'    => 'skip',
                'error_message' => '証券番号が空のためスキップ',
            ];
        }

        // スキップ条件: 顧客名・保険終期が空
        if ($customerName === '' || $endDateRaw === '') {
            $counters['skip']++;
            return [
                'raw'           => $raw,
                'policy_no'     => $policyNo,
                'customer_name' => $customerName ?: null,
                'row_status'    => 'skip',
                'error_message' => '顧客名または保険終期が空のためスキップ',
            ];
        }

        $counters['valid']++;

        // 満期日の決定: 保険終期をそのまま使用
        $maturityDate = $this->parseDate($endDateRaw);
        if ($maturityDate === null) {
            $counters['error']++;
            return [
                'raw'           => $raw,
                'policy_no'     => $policyNo,
                'customer_name' => $customerName,
                'row_status'    => 'error',
                'error_message' => '保険終期の日付が不正: ' . $endDateRaw,
            ];
        }

        // STEP 2: 顧客の照合
        $agencyCode     = $this->col($cols, $colMap, self::HDR_AGENCY_CODE);
        $sjnetStaffName = $this->col($cols, $colMap, self::HDR_STAFF_NAME);

        [$customerId, $customerWasInserted, $isUnlinked] = $this->resolveCustomer($customerName, $cols, $colMap);

        if ($isUnlinked) {
            $counters['unlinked']++;
        } elseif ($customerWasInserted) {
            $counters['customer_insert']++;
        }

        // STEP 3: 担当者の解決
        [$resolvedUserId, $mappingStatus] = $this->resolveStaff($agencyCode);

        // STEP 4: 契約の登録・更新
        $startDate     = $this->parseDate($this->col($cols, $colMap, self::HDR_START_DATE));
        $endDate       = $maturityDate; // 保険終期 = 満期日
        $productType   = $this->col($cols, $colMap, self::HDR_PRODUCT_TYPE);
        $paymentCycle  = $this->col($cols, $colMap, self::HDR_PAYMENT_CYCLE);
        $premiumAmount = $this->parsePremium($this->col($cols, $colMap, self::HDR_PREMIUM_AMOUNT));

        [$contractId, $contractWasInserted] = $this->upsertContract(
            $policyNo,
            $customerId,
            $customerName,
            $startDate,
            $endDate,
            $productType,
            $paymentCycle,
            $premiumAmount
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

        $rowStatus = $isUnlinked ? 'unlinked' : ($isInsert ? 'insert' : 'update');

        return [
            'raw'                     => $raw,
            'policy_no'               => $policyNo,
            'customer_name'           => $customerName,
            'maturity_date'           => $maturityDate,
            'sjnet_agency_code'       => $agencyCode ?: null,
            'sjnet_staff_name'        => $sjnetStaffName ?: null,
            'resolved_staff_id'       => $resolvedUserId,
            'staff_mapping_status'    => $mappingStatus,
            'matched_contract_id'     => $contractId,
            'matched_renewal_case_id' => $renewalCaseId,
            'row_status'              => $rowStatus,
        ];
    }

    /**
     * 顧客を照合する（顧客名+生年月日で検索）
     *
     * 照合ロジック:
     *   - 生年月日が CSV に存在する場合: customer_name AND birth_date AND is_deleted=0 で検索
     *   - 生年月日が空の場合: customer_name AND birth_date IS NULL AND is_deleted=0 で検索
     * 結果:
     *   0件 → 新規 INSERT、customer_id を返す
     *   1件 → そのまま customer_id を返す（既存顧客は一切 UPDATE しない）
     *   複数件 → customer_id=NULL で未リンク取込（isUnlinked=true）
     *
     * @param array<int, string> $cols
     * @param array<string, int> $colMap
     * @return array{0: int|null, 1: bool, 2: bool}  [customer_id, was_inserted, is_unlinked]
     */
    private function resolveCustomer(string $customerName, array $cols, array $colMap): array
    {
        $birthDateRaw = $this->col($cols, $colMap, self::HDR_BIRTH_DATE);
        $birthDate    = $birthDateRaw !== '' ? $this->parseBirthDate($birthDateRaw) : null;

        if ($birthDate !== null) {
            // 生年月日あり: 顧客名 + 生年月日で照合
            $stmt = $this->pdo->prepare(
                'SELECT id FROM m_customer
                 WHERE customer_name = :name
                   AND birth_date = :birth_date
                   AND is_deleted = 0
                 LIMIT 3'
            );
            $stmt->bindValue(':name', $customerName);
            $stmt->bindValue(':birth_date', $birthDate);
        } else {
            // 生年月日なし: 顧客名 + birth_date IS NULL で照合
            $stmt = $this->pdo->prepare(
                'SELECT id FROM m_customer
                 WHERE customer_name = :name
                   AND birth_date IS NULL
                   AND is_deleted = 0
                 LIMIT 3'
            );
            $stmt->bindValue(':name', $customerName);
        }
        $stmt->execute();
        $found = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!is_array($found)) {
            $found = [];
        }

        if (count($found) === 1) {
            // 1件ヒット: 自動リンク（既存顧客の情報は一切 UPDATE しない）
            return [(int) $found[0]['id'], false, false];
        }

        if (count($found) >= 2) {
            // 複数件ヒット: 未リンク取込（エラーにしない）
            return [null, false, true];
        }

        // 0件ヒット: 新規 INSERT
        $postalCode = $this->col($cols, $colMap, self::HDR_POSTAL_CODE) ?: null;
        $address1   = $this->col($cols, $colMap, self::HDR_ADDRESS1) ?: null;
        $phone      = $this->col($cols, $colMap, self::HDR_PHONE) ?: null;

        $stmt = $this->pdo->prepare(
            'INSERT INTO m_customer
               (customer_type, customer_name, birth_date, postal_code, address1, phone,
                status, is_deleted, created_by, updated_by)
             VALUES
               (\'individual\', :name, :birth_date, :postal_code, :address1, :phone,
                \'active\', 0, :created_by, :updated_by)'
        );
        $stmt->bindValue(':name', $customerName);
        $stmt->bindValue(':birth_date', $birthDate);
        $stmt->bindValue(':postal_code', $postalCode);
        $stmt->bindValue(':address1', $address1);
        $stmt->bindValue(':phone', $phone);
        $stmt->bindValue(':created_by', $this->executedBy, PDO::PARAM_INT);
        $stmt->bindValue(':updated_by', $this->executedBy, PDO::PARAM_INT);
        $stmt->execute();

        return [(int) $this->pdo->lastInsertId(), true, false];
    }

    /**
     * ヘッダ名でカラム値を取得するヘルパ（任意列は colMap に存在しない場合は空文字を返す）
     *
     * @param array<int, string> $cols
     * @param array<string, int> $colMap
     */
    private function col(array $cols, array $colMap, string $header): string
    {
        $idx = $colMap[$header] ?? null;
        return $idx !== null ? ($cols[$idx] ?? '') : '';
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

        $repo  = new StaffRepository($this->pdo);
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
     * UPDATE 時の customer_id 扱い:
     *   - 既存 customer_id が NULL 以外 → 維持（上書きしない）
     *   - 既存 customer_id が NULL     → 再照合結果（$customerId）を反映
     *
     * @return array{0: int, 1: bool}  [contract_id, was_inserted]
     */
    private function upsertContract(
        string $policyNo,
        ?int $customerId,
        string $sjnetCustomerName,
        ?string $startDate,
        ?string $endDate,
        string $productType,
        string $paymentCycle,
        ?int $premiumAmount
    ): array {
        // 証券番号 + 終期日（policy_end_date）で同一年度の契約を検索する
        if ($endDate !== null) {
            $stmt = $this->pdo->prepare(
                'SELECT id, customer_id
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
                'SELECT id, customer_id
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
            $contractId = (int) $existing['id'];

            // customer_id: 既存が NULL の場合のみ再照合結果を反映、それ以外は維持
            $existingCustomerId = $existing['customer_id'] !== null ? (int) $existing['customer_id'] : null;
            $newCustomerId = $existingCustomerId ?? $customerId;

            $stmt = $this->pdo->prepare(
                'UPDATE t_contract
                 SET customer_id             = :customer_id,
                     sjnet_customer_name     = :sjnet_customer_name,
                     policy_start_date       = :start_date,
                     policy_end_date         = :end_date,
                     product_type            = :product_type,
                     payment_cycle           = :payment_cycle,
                     premium_amount          = :premium,
                     last_sjnet_imported_at  = :imported_at,
                     updated_by              = :updated_by
                 WHERE id = :id'
            );
            $stmt->bindValue(':customer_id', $newCustomerId, $newCustomerId !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
            $stmt->bindValue(':sjnet_customer_name', $sjnetCustomerName);
            $stmt->bindValue(':start_date', $startDate);
            $stmt->bindValue(':end_date', $endDate);
            $stmt->bindValue(':product_type', $productType !== '' ? $productType : null);
            $stmt->bindValue(':payment_cycle', $paymentCycle !== '' ? $paymentCycle : null);
            $stmt->bindValue(':premium', $premiumAmount ?? 0, PDO::PARAM_INT);
            $stmt->bindValue(':imported_at', $now);
            $stmt->bindValue(':updated_by', $this->executedBy, PDO::PARAM_INT);
            $stmt->bindValue(':id', $contractId, PDO::PARAM_INT);
            $stmt->execute();

            return [$contractId, false];
        }

        // INSERT
        $stmt = $this->pdo->prepare(
            'INSERT INTO t_contract
               (customer_id, sjnet_customer_name, policy_no,
                policy_start_date, policy_end_date,
                product_type, payment_cycle, premium_amount,
                status, last_sjnet_imported_at,
                is_deleted, created_by, updated_by)
             VALUES
               (:customer_id, :sjnet_customer_name, :policy_no,
                :start_date, :end_date,
                :product_type, :payment_cycle, :premium,
                \'active\', :imported_at,
                0, :created_by, :updated_by)'
        );
        $stmt->bindValue(':customer_id', $customerId, $customerId !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':sjnet_customer_name', $sjnetCustomerName);
        $stmt->bindValue(':policy_no', $policyNo);
        $stmt->bindValue(':start_date', $startDate);
        $stmt->bindValue(':end_date', $endDate);
        $stmt->bindValue(':product_type', $productType !== '' ? $productType : null);
        $stmt->bindValue(':payment_cycle', $paymentCycle !== '' ? $paymentCycle : null);
        $stmt->bindValue(':premium', $premiumAmount ?? 0, PDO::PARAM_INT);
        $stmt->bindValue(':imported_at', $now);
        $stmt->bindValue(':created_by', $this->executedBy, PDO::PARAM_INT);
        $stmt->bindValue(':updated_by', $this->executedBy, PDO::PARAM_INT);
        $stmt->execute();

        return [(int) $this->pdo->lastInsertId(), true];
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

            // assigned_staff_id: resolvedUserId がある場合は常に上書き（1年後再取込で担当者が変わりうる）
            if ($resolvedUserId !== null) {
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
        $contractRow  = $contractStmt->fetch(PDO::FETCH_ASSOC);
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
     * 生年月日文字列を YYYY-MM-DD に変換する
     * YYYY/MM/DD・YYYY-MM-DD・YYYYMMDD を受け付ける
     */
    private function parseBirthDate(string $value): ?string
    {
        if ($value === '') {
            return null;
        }
        // YYYYMMDD（8桁）
        if (preg_match('/^\d{8}$/', $value)) {
            $v = substr($value, 0, 4) . '-' . substr($value, 4, 2) . '-' . substr($value, 6, 2);
            $d = DateTimeImmutable::createFromFormat('Y-m-d', $v);
            return ($d !== false && $d->format('Y-m-d') === $v) ? $v : null;
        }
        // YYYY/MM/DD or YYYY-MM-DD
        $v = str_replace('/', '-', $value);
        $d = DateTimeImmutable::createFromFormat('Y-m-d', $v);
        if ($d !== false && $d->format('Y-m-d') === $v) {
            return $v;
        }
        return null;
    }

    /**
     * 保険料文字列を整数に変換する（カンマ区切り・通貨記号除去）
     *
     * 負値は null を返す。DDL の CHECK (premium_amount >= 0) に違反するため、
     * 呼び出し側で INSERT/UPDATE 時に 0 へフォールバックさせる。
     * 返戻金は仕様上「不使用」列（列24）のため、このメソッドには渡らない。
     */
    private function parsePremium(string $value): ?int
    {
        if ($value === '') {
            return null;
        }
        // 符号（-）が含まれる場合は負値と判断して null を返す
        if (str_contains($value, '-')) {
            return null;
        }
        $cleaned = preg_replace('/[^\d]/', '', $value);
        if ($cleaned === null || $cleaned === '') {
            return null;
        }
        return (int) $cleaned;
    }
}
