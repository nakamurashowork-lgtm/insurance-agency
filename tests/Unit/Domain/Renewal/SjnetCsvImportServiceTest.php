<?php
declare(strict_types=1);

namespace Tests\Unit\Domain\Renewal;

use App\Domain\Renewal\SjnetCsvImportService;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * SjnetCsvImportService の純粋ロジックをテストする。
 * DB接続を必要としない private メソッドをリフレクションで検証する。
 */
final class SjnetCsvImportServiceTest extends TestCase
{
    private ReflectionClass $ref;
    private SjnetCsvImportService $service;

    protected function setUp(): void
    {
        $pdoStub = $this->createStub(\PDO::class);
        $this->service = new SjnetCsvImportService($pdoStub, 1, new DateTimeImmutable('2026-04-06'));
        $this->ref = new ReflectionClass($this->service);
    }

    private function call(string $method, mixed ...$args): mixed
    {
        $m = $this->ref->getMethod($method);
        $m->setAccessible(true);
        return $m->invoke($this->service, ...$args);
    }

    // =========================================================
    // parseDate
    // =========================================================

    public function testParseDate_SlashFormat(): void
    {
        $this->assertSame('2026-04-01', $this->call('parseDate', '2026/04/01'));
    }

    public function testParseDate_HyphenFormat(): void
    {
        $this->assertSame('2026-04-01', $this->call('parseDate', '2026-04-01'));
    }

    public function testParseDate_Empty(): void
    {
        $this->assertNull($this->call('parseDate', ''));
    }

    public function testParseDate_InvalidString(): void
    {
        $this->assertNull($this->call('parseDate', 'invalid'));
        $this->assertNull($this->call('parseDate', '20260401'));   // 区切り文字なし
        $this->assertNull($this->call('parseDate', '2026/13/01')); // 月=13
    }

    // =========================================================
    // parsePremium
    // =========================================================

    public function testParsePremium_PlainNumber(): void
    {
        $this->assertSame(120000, $this->call('parsePremium', '120000'));
    }

    public function testParsePremium_WithCommas(): void
    {
        $this->assertSame(120000, $this->call('parsePremium', '120,000'));
    }

    public function testParsePremium_Empty(): void
    {
        $this->assertNull($this->call('parsePremium', ''));
    }

    public function testParsePremium_NonNumeric(): void
    {
        // 数字を一切含まない文字列 → null
        $this->assertNull($this->call('parsePremium', 'abc'));
    }

    public function testParsePremium_HalfWidthYenSign(): void
    {
        // 半角円記号（\）混入 → 数字のみ取り出して 50000
        $this->assertSame(50000, $this->call('parsePremium', '¥50,000'));
    }

    public function testParsePremium_FullWidthYenSign(): void
    {
        // 全角円記号（￥）混入 → Excel コピペで発生しやすい
        // /[^\d]/ は多バイト文字も非数字として除去するため 50000 を返す
        $this->assertSame(50000, $this->call('parsePremium', '￥50,000'));
    }

    public function testParsePremium_NegativeNumber(): void
    {
        // 負の保険料は DDL の CHECK (premium_amount >= 0) に違反するため null を返す。
        // 返戻金は仕様上「不使用」列（列24）のため、このメソッドには渡らない。
        $this->assertNull($this->call('parsePremium', '-5000'));
    }

    // =========================================================
    // parseCsvLines
    // =========================================================

    public function testParseCsvLines_BasicUtf8(): void
    {
        $csv = "col1,col2,col3\nval1,val2,val3\n";
        $lines = $this->call('parseCsvLines', $csv);

        $this->assertCount(2, $lines);
        $this->assertSame(['col1', 'col2', 'col3'], $lines[0]);
        $this->assertSame(['val1', 'val2', 'val3'], $lines[1]);
    }

    public function testParseCsvLines_CrLfNewline(): void
    {
        $csv = "a,b\r\nc,d\r\n";
        $lines = $this->call('parseCsvLines', $csv);
        $this->assertCount(2, $lines);
    }

    public function testParseCsvLines_QuotedFields(): void
    {
        // カンマを含む値はダブルクォートで囲まれる
        $csv = "\"山田 太郎\",\"東京都,港区\"\n";
        $lines = $this->call('parseCsvLines', $csv);
        $this->assertSame(['山田 太郎', '東京都,港区'], $lines[0]);
    }

    public function testParseCsvLines_TrimsWhitespace(): void
    {
        // ヘッダに末尾スペースがある実 CSV を想定
        $csv = "col1 ,col2 \nval1,val2\n";
        $lines = $this->call('parseCsvLines', $csv);
        $this->assertSame(['col1', 'col2'], $lines[0]);
    }

    public function testParseCsvLines_ColumnCountMismatch(): void
    {
        // ヘッダ3列・データ行2列 → 実装は fgetcsv に委ねるため列数が少ない行をそのまま返す
        // processRow() 内で不足列は空文字で補完されるため、ここでは分解結果のみ確認
        $csv = "col1,col2,col3\nval1,val2\n";
        $lines = $this->call('parseCsvLines', $csv);
        $this->assertCount(2, $lines);
        $this->assertCount(3, $lines[0]); // ヘッダ行
        $this->assertCount(2, $lines[1]); // データ行（列数が少ない）
    }

    public function testParseCsvLines_EmptyFile(): void
    {
        // 空ファイル → 空配列を返す
        $lines = $this->call('parseCsvLines', '');
        $this->assertSame([], $lines);
    }

    // =========================================================
    // decodeContent
    // =========================================================

    public function testDecodeContent_Utf8Bom(): void
    {
        $bom = "\xEF\xBB\xBF";
        $content = $bom . "test content";

        [$encoding, $decoded] = $this->call('decodeContent', $content);

        $this->assertSame('UTF-8 (BOM)', $encoding);
        $this->assertSame('test content', $decoded);
    }

    public function testDecodeContent_Utf8NoBom(): void
    {
        $content = "UTF-8 text ここ";
        [$encoding, $decoded] = $this->call('decodeContent', $content);
        $this->assertSame('UTF-8', $encoding);
        $this->assertSame($content, $decoded);
    }

    // =========================================================
    // decodeContent — SJIS (E1-E3)
    // =========================================================

    /**
     * E1: SJIS バイト列（ASCII + 日本語）が UTF-8 に変換されること。
     * mb_detect_encoding は 'SJIS'/'SJIS-win'/'CP932' のいずれかを返し、
     * mb_convert_encoding($raw, 'UTF-8', $detected) で正しく変換される。
     */
    public function testDecodeContent_Sjis_BasicJapanese_ConvertedToUtf8(): void
    {
        // Arrange: ひらがな・カタカナ・漢字を含む SJIS バイト列
        $originalUtf8 = 'テスト顧客名';
        $sjisBytes = (string) mb_convert_encoding($originalUtf8, 'SJIS', 'UTF-8');

        // Act
        [$encoding, $decoded] = $this->call('decodeContent', $sjisBytes);

        // Assert
        $this->assertContains(
            $encoding,
            ['SJIS', 'SJIS-win', 'CP932'],
            "SJIS エンコードは 'SJIS'/'SJIS-win'/'CP932' のいずれかとして検出されるべき"
        );
        $this->assertSame(
            $originalUtf8,
            $decoded,
            'UTF-8 に変換された文字列が元の文字列と一致すること'
        );
    }

    /**
     * E2: CP932 固有文字（① = NEC特殊文字 0x8740）が文字化けせずに変換されること。
     *
     * 0x8740 は SJIS の構造的に valid なバイト列（先行 0x87、後続 0x40）のため、
     * mb_detect_encoding が 'SJIS' を返す可能性がある。
     * PHP の mbstring において 'SJIS' が CP932 と実質同一かどうかをこのテストで確定する。
     * もし assertSame が失敗する場合は Phase 5 指摘事項に昇格し実装修正を検討する。
     */
    public function testDecodeContent_Cp932VendorChar_NotGarbled(): void
    {
        // Arrange: CP932 固有文字を含むバイト列（① = 0x87 0x40 in CP932）
        $originalUtf8 = '①テスト';
        $cp932Bytes = (string) mb_convert_encoding($originalUtf8, 'CP932', 'UTF-8');

        // Act
        [$encoding, $decoded] = $this->call('decodeContent', $cp932Bytes);

        // Assert: SJIS 系として検出されること
        $this->assertContains(
            $encoding,
            ['SJIS', 'SJIS-win', 'CP932'],
            'CP932 固有文字を含むバイト列は SJIS 系として検出されるべき'
        );
        // Assert: ① が文字化けせずに正しく変換されること
        $this->assertSame(
            $originalUtf8,
            $decoded,
            'CP932 固有文字 ① が文字化けせずに UTF-8 に変換されること'
        );
    }

    /**
     * E3: BOM なし SJIS は UTF-8 BOM 検出パスをすり抜け、encoding 変換パスを通ること。
     * BOM チェックが誤って適用されると encoding='UTF-8 (BOM)' が返るため、
     * それが起きていないことを確認する。
     */
    public function testDecodeContent_SjisNoBom_DoesNotTriggerBomPath(): void
    {
        // Arrange: BOM なし SJIS バイト列
        $originalUtf8 = '山田太郎';
        $sjisBytes = (string) mb_convert_encoding($originalUtf8, 'SJIS', 'UTF-8');

        // BOM が付いていないことを前提確認
        $this->assertStringNotContainsString("\xEF\xBB\xBF", $sjisBytes);

        // Act
        [$encoding, $decoded] = $this->call('decodeContent', $sjisBytes);

        // Assert: BOM パスに誤って入っていないこと
        $this->assertNotSame(
            'UTF-8 (BOM)',
            $encoding,
            'BOM なし SJIS は BOM 検出パスではなく encoding 変換パスを通ること'
        );
        // Assert: 正しく UTF-8 に変換されていること
        $this->assertSame($originalUtf8, $decoded);
    }
}
