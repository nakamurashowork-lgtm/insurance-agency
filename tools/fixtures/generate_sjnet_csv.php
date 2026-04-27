<?php
declare(strict_types=1);

/**
 * SJNET CSV ダミーデータ生成スクリプト。
 *
 * - 6ヶ月分（2026-05 ～ 2026-10）の月別ファイルを tests/fixtures/sjnet_csv/ に出力。
 * - 各ファイル 2000 件、合計 12000 件。
 * - 各ファイル冒頭 10 件は m_customer / m_staff の既存ダミーデータと紐付く統合行
 *   （顧客名 + 生年月日 が一致して auto-link、代理店コードが m_staff.sjnet_code と一致）。
 * - 残り 1990 件は新規顧客扱いの一意な「ダミー顧客{連番}」。
 *
 * 列順（SjnetCsvImportService の HDR 定数に準拠）:
 *   顧客名,生年月日,郵便番号,住所,ＴＥＬ,保険始期,保険終期,種目種類,証券番号,払込方法,合計保険料,担当者,代理店ｺｰﾄﾞ
 *
 * 実行: php tools/fixtures/generate_sjnet_csv.php
 */

$outputDir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'fixtures' . DIRECTORY_SEPARATOR . 'sjnet_csv';
if (!is_dir($outputDir) && !mkdir($outputDir, 0777, true) && !is_dir($outputDir)) {
    fwrite(STDERR, "Failed to create output dir: {$outputDir}\n");
    exit(1);
}

$header = [
    '顧客名', '生年月日', '郵便番号', '住所', 'ＴＥＬ',
    '保険始期', '保険終期', '種目種類', '証券番号',
    '払込方法', '合計保険料', '担当者', '代理店ｺｰﾄﾞ',
];

// 既存 m_customer と紐付く統合行（顧客名 + 生年月日が一致すると auto-link される）。
// 各ファイルの先頭 10 行に挿入。
$integrationRows = [
    ['山田 太郎',                       '1980-05-15', '150-0001', '東京都渋谷区神宮前1-2-3',  '03-1100-0001', '一般自動車', '一時払', 'SJ001'],
    ['佐藤 花子',                       '1975-11-03', '160-0023', '東京都新宿区西新宿4-5',    '03-1100-0002', '火災',       '月払',   'SJ002'],
    ['高橋 健一',                       '1968-07-22', '170-0013', '東京都豊島区東池袋1-2',    '03-1100-0003', '一般自動車', '一時払', 'SJ001'],
    ['渡辺 真由美',                     '1985-02-18', '180-0001', '東京都武蔵野市吉祥寺3-4',  '0422-11-0001', '傷害',       '一時払', 'SJ002'],
    ['鈴木 一郎',                       '1990-03-20', '190-0011', '東京都立川市曙町1-1',      '042-100-0001', '一般自動車', '月払',   'SJ001'],
    ['加藤 裕子',                       '1960-09-10', '210-0001', '神奈川県川崎市川崎区本町', '044-100-0001', '火災',       '一時払', 'SJ002'],
    ['株式会社テストコーポレーション', '',           '100-0001', '東京都千代田区千代田1-1',  '03-1111-2222', '企業総合',   '一時払', 'SJ001'],
    ['テスト運輸株式会社',             '',           '530-0001', '大阪府大阪市北区梅田1-1',  '06-1100-0001', '自動車',     '月払',   'SJ002'],
    ['株式会社名古屋商事',             '',           '460-0001', '愛知県名古屋市中区三の丸', '052-100-0001', '賠償責任',   '一時払', 'SJ001'],
    ['株式会社東京建設',               '',           '105-0001', '東京都港区虎ノ門1-1',      '03-1100-0010', '工事',       '月払',   'SJ002'],
];

// ダミー行用の循環素材
$productTypes = ['一般自動車', '火災', '傷害', '賠償責任', '企業総合', '工事', '自動車'];
$paymentCycles = ['一時払', '月払', '年払'];
$staffCodes = ['SJ001', 'SJ002'];

$startMonth = new DateTimeImmutable('2026-05-01');
$globalIndex = 1; // 全ファイル通しの連番（証券番号と顧客名に使用）

for ($fileNo = 1; $fileNo <= 6; $fileNo++) {
    $monthStart = $startMonth->modify('+' . ($fileNo - 1) . ' months');
    $monthLabel = $monthStart->format('Y_m');
    $daysInMonth = (int) $monthStart->format('t');
    $filePath = $outputDir . DIRECTORY_SEPARATOR . sprintf('sjnet_%s.csv', $monthLabel);

    $fp = fopen($filePath, 'w');
    if ($fp === false) {
        fwrite(STDERR, "Failed to open: {$filePath}\n");
        exit(1);
    }
    // BOM なし UTF-8。SjnetCsvImportService 側で UTF-8/SJIS/CP932 自動判別する。
    fputcsv($fp, $header);

    $rowsInFile = 0;
    foreach ($integrationRows as $integ) {
        [$name, $birth, $postal, $address, $phone, $product, $payment, $sjnet] = $integ;
        // 統合行の満期日は当月内の固定日（1日刻み、整列）
        $maturity = $monthStart->modify('+' . ($rowsInFile % $daysInMonth) . ' days');
        $startDate = $maturity->modify('-1 year');
        $policyNo = sprintf('POL-INT-%02d-%05d', $fileNo, $rowsInFile + 1);
        $premium = 50000 + (($globalIndex % 30) * 1000);

        fputcsv($fp, [
            $name,
            $birth,
            $postal,
            $address,
            $phone,
            $startDate->format('Y/m/d'),
            $maturity->format('Y/m/d'),
            $product,
            $policyNo,
            $payment,
            (string) $premium,
            '',
            $sjnet,
        ]);
        $rowsInFile++;
        $globalIndex++;
    }

    while ($rowsInFile < 2000) {
        $dayOfMonth = ($rowsInFile - count($integrationRows)) % $daysInMonth;
        $maturity = $monthStart->modify('+' . $dayOfMonth . ' days');
        $startDate = $maturity->modify('-1 year');

        // ダミー顧客名（一意）
        $customerName = sprintf('ダミー顧客%05d', $globalIndex);
        // 個人/法人 を 8:2 で混在
        $isCorporate = ($globalIndex % 5 === 0);
        $birthDate = $isCorporate ? '' : sprintf('%04d-%02d-%02d',
            1950 + ($globalIndex % 50),
            (($globalIndex % 12) + 1),
            (($globalIndex % 28) + 1)
        );
        if ($isCorporate) {
            $customerName = sprintf('株式会社ダミー%05d', $globalIndex);
        }

        $product = $productTypes[$globalIndex % count($productTypes)];
        $payment = $paymentCycles[$globalIndex % count($paymentCycles)];
        $sjnet = $staffCodes[$globalIndex % count($staffCodes)];
        $policyNo = sprintf('POL-DUMMY-%02d-%05d', $fileNo, $rowsInFile + 1);
        $premium = 30000 + (($globalIndex % 100) * 1000);

        fputcsv($fp, [
            $customerName,
            $birthDate,
            '',
            '',
            '',
            $startDate->format('Y/m/d'),
            $maturity->format('Y/m/d'),
            $product,
            $policyNo,
            $payment,
            (string) $premium,
            '',
            $sjnet,
        ]);
        $rowsInFile++;
        $globalIndex++;
    }

    fclose($fp);
    printf("Wrote %s (%d rows)\n", basename($filePath), $rowsInFile);
}

printf("Total rows generated: %d\n", $globalIndex - 1);
