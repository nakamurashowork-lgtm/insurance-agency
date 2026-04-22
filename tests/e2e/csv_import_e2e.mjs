/**
 * CSV取込 E2E テスト
 *
 * 【実行前の前提】
 *   1. XAMPP が起動していること（http://127.0.0.1/insurance-agency/public）
 *   2. 以下のセットアップSQLを適用済みであること（再実行可能）:
 *        mysql -u root xs000001_te001 < tests/fixtures/csv_import_setup.sql
 *
 * 【実行方法】
 *   cd tests/e2e
 *   node csv_import_e2e.mjs
 *
 * 【テストケース一覧】
 *   TC-01 : 新規顧客・新規契約取込（完了・新規登録1件・顧客自動登録1件）
 *   TC-02 : 既存顧客マッチ・新規契約取込（完了・新規登録1件・顧客自動登録0件）
 *   TC-03 : 同名顧客重複・未紐づけ契約（完了・未紐づけ1件）
 *   TC-04 : 既存契約・案件の更新（完了・更新1件）
 *   TC-05 : スキップ・エラー混在（一部エラーあり・処理3行・スキップ1行・エラー1行）
 *   TC-06 : 必須ヘッダー欠落（エラーflash表示・取込結果なし）
 *   TC-07 : スタッフコード解決（完了・解決済み1件）
 *   TC-08 : 同一CSVを2回目取込 → 更新（TC-01 の契約を更新・更新1件）
 *
 * 【対象外（PHPUnit統合テストでカバー済み）】
 *   - Shift-JIS/CP932 エンコーディング
 *   - UTF-8 BOM
 *   - ページング・大量データ
 */

import { createRequire } from 'module';
const require = createRequire(import.meta.url);
const { chromium } = require('playwright');
import { writeFileSync, mkdirSync } from 'fs';
import { fileURLToPath } from 'url';
import { dirname, join } from 'path';

const __dirname = dirname(fileURLToPath(import.meta.url));
const PROJECT_ROOT = join(__dirname, '../..');

const BASE_URL  = 'http://127.0.0.1/insurance-agency/public';
const SS_DIR    = join(PROJECT_ROOT, 'tests/e2e/screenshots/csv_import');
const FIXTURES  = join(PROJECT_ROOT, 'tests/fixtures/csv');

mkdirSync(SS_DIR, { recursive: true });

// ── 結果記録 ─────────────────────────────────────────────────
const results = [];
function pass(label) {
    console.log(`  [PASS] ${label}`);
    results.push({ label, status: 'PASS' });
}
function fail(label, reason = '') {
    console.log(`  [FAIL] ${label}${reason ? ': ' + reason : ''}`);
    results.push({ label, status: 'FAIL', reason });
}
function info(msg) {
    console.log(`  [INFO] ${msg}`);
}

// ── ユーティリティ ────────────────────────────────────────────

/**
 * ダイアログ内のテキストを取得する
 * @param {import('playwright').Page} page
 * @returns {Promise<string>}
 */
async function getDialogText(page) {
    const dlg = page.locator('#renewal-import-dialog');
    return await dlg.textContent() ?? '';
}

/**
 * 取込結果セクションが表示されているか確認する
 * @param {import('playwright').Page} page
 * @returns {Promise<boolean>}
 */
async function hasImportResult(page) {
    return await page.locator('#renewal-import-dialog .modal-result').isVisible();
}

/**
 * インポートダイアログが開いているか確認する
 * @param {import('playwright').Page} page
 * @returns {Promise<boolean>}
 */
async function isDialogOpen(page) {
    return await page.locator('#renewal-import-dialog').evaluate(el => el.open);
}

/**
 * CSVファイルを選択してインポートを実行する
 * リダイレクト後、import_dialog パラメータが含まれた URL へ遷移するまで待機する。
 *
 * @param {import('playwright').Page} page
 * @param {string} csvPath  - フィクスチャCSVの絶対パス
 * @param {string} tcLabel  - スクリーンショット用ラベル
 */
async function runImport(page, csvPath, tcLabel) {
    // 満期一覧を開く（クリーンな状態）
    await page.goto(`${BASE_URL}/?route=renewal/list`);
    await page.waitForLoadState('networkidle');

    // CSV取込ダイアログを開く
    await page.locator('[data-open-dialog="renewal-import-dialog"]').click();
    await page.waitForTimeout(300);

    // ファイルをセット
    await page.locator('#renewal-import-dialog input[name="csv_file"]').setInputFiles(csvPath);

    // 送信 → リダイレクトを待つ
    await Promise.all([
        page.waitForURL(/import_dialog/, { timeout: 15000 }),
        page.locator('#renewal-import-dialog form[method="post"] button[type="submit"]').click(),
    ]);

    await page.waitForLoadState('networkidle');

    // スクリーンショット保存
    await page.screenshot({ path: join(SS_DIR, `${tcLabel}.png`), fullPage: false });
}

/**
 * 取込結果バッジラベルを返す（完了 / 一部エラーあり / 失敗）
 * @param {string} dialogText
 * @returns {string}
 */
function detectBadgeLabel(dialogText) {
    if (dialogText.includes('完了') && !dialogText.includes('一部エラーあり')) return '完了';
    if (dialogText.includes('一部エラーあり')) return '一部エラーあり';
    if (dialogText.includes('失敗')) return '失敗';
    return '(不明)';
}

/**
 * ダイアログテキストから「処理行数」の数値を抽出する
 * "処理行数 3行" のような形式を想定
 * @param {string} dialogText
 * @returns {number|null}
 */
function extractTotalRows(dialogText) {
    const m = dialogText.match(/処理行数\s*(\d+)行/);
    return m ? parseInt(m[1], 10) : null;
}

/**
 * カウンターを取得する汎用関数
 * "契約 新規登録 2件" などのパターンに対応
 * @param {string} dialogText
 * @param {string} label - 例: '契約 新規登録', '契約 更新', 'エラー', 'スキップ'
 * @returns {number|null}
 */
function extractCount(dialogText, label) {
    // ラベルの後に数字 + 件 or 行 が続くパターン
    const escaped = label.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    const m = dialogText.match(new RegExp(escaped + '\\s*(\\d+)[件行]'));
    return m ? parseInt(m[1], 10) : null;
}

// ── メイン ────────────────────────────────────────────────────
async function run() {
    const browser = await chromium.launch({ headless: true });
    const page    = await browser.newPage();
    page.setDefaultTimeout(15000);

    // ── ログイン ──────────────────────────────────────────────
    console.log('\n[dev login]');
    await page.goto(`${BASE_URL}/?route=dev/login`);
    await page.waitForURL(/dashboard/, { timeout: 10000 });
    pass('dev login → dashboard');

    // ==========================================================
    // TC-01: 新規顧客・新規契約取込
    // ==========================================================
    console.log('\n[TC-01] 新規顧客・新規契約取込');
    await runImport(page, join(FIXTURES, 'tc01_new_customer.csv'), 'tc01_result');

    const dlgOpen01 = await isDialogOpen(page);
    if (dlgOpen01) pass('TC-01: ダイアログが自動オープン');
    else           fail('TC-01: ダイアログが開いていない');

    const hasResult01 = await hasImportResult(page);
    if (hasResult01) pass('TC-01: 取込結果セクションが表示される');
    else             fail('TC-01: 取込結果セクションが見当たらない');

    const text01 = await getDialogText(page);
    const badge01 = detectBadgeLabel(text01);
    if (badge01 === '完了') pass(`TC-01: ステータス = 完了`);
    else                    fail(`TC-01: ステータスが期待と異なる（実際: ${badge01}）`);

    const total01 = extractTotalRows(text01);
    if (total01 === 1) pass(`TC-01: 処理行数 = 1行`);
    else               fail(`TC-01: 処理行数が期待と異なる（実際: ${total01}）`);

    const insert01 = extractCount(text01, '契約 新規登録');
    if (insert01 === 1) pass(`TC-01: 契約 新規登録 = 1件`);
    else                fail(`TC-01: 契約 新規登録が期待と異なる（実際: ${insert01}）`);

    const custInsert01 = extractCount(text01, '顧客 自動登録');
    if (custInsert01 === 1) pass(`TC-01: 顧客 自動登録 = 1件`);
    else                    fail(`TC-01: 顧客 自動登録が期待と異なる（実際: ${custInsert01}）`);

    // ==========================================================
    // TC-02: 既存顧客マッチ・新規契約取込
    //         セットアップSQL で "CSV取込TC02テスト / 1975-06-20" が1件存在
    // ==========================================================
    console.log('\n[TC-02] 既存顧客マッチ・新規契約取込');
    await runImport(page, join(FIXTURES, 'tc02_existing_customer.csv'), 'tc02_result');

    const text02  = await getDialogText(page);
    const badge02 = detectBadgeLabel(text02);
    if (badge02 === '完了') pass('TC-02: ステータス = 完了');
    else                    fail(`TC-02: ステータスが期待と異なる（実際: ${badge02}）`);

    const total02 = extractTotalRows(text02);
    if (total02 === 1) pass('TC-02: 処理行数 = 1行');
    else               fail(`TC-02: 処理行数が期待と異なる（実際: ${total02}）`);

    const insert02 = extractCount(text02, '契約 新規登録');
    if (insert02 === 1) pass('TC-02: 契約 新規登録 = 1件（既存顧客に紐づく）');
    else                fail(`TC-02: 契約 新規登録が期待と異なる（実際: ${insert02}）`);

    const custInsert02 = extractCount(text02, '顧客 自動登録');
    if (custInsert02 === 0) pass('TC-02: 顧客 自動登録 = 0件（既存顧客がマッチしたため）');
    else                    fail(`TC-02: 顧客 自動登録が期待と異なる（実際: ${custInsert02}）`);

    // ==========================================================
    // TC-03: 同名顧客重複・未紐づけ契約
    //         セットアップSQL で "CSV取込TC03テスト" が生年月日なしで2件存在
    // ==========================================================
    console.log('\n[TC-03] 同名顧客重複・未紐づけ契約');
    await runImport(page, join(FIXTURES, 'tc03_ambiguous.csv'), 'tc03_result');

    const text03  = await getDialogText(page);
    const badge03 = detectBadgeLabel(text03);
    if (badge03 === '完了') pass('TC-03: ステータス = 完了（未紐づけでも完了扱い）');
    else                    fail(`TC-03: ステータスが期待と異なる（実際: ${badge03}）`);

    const unlinked03 = extractCount(text03, '未紐づけ契約');
    if (unlinked03 !== null && unlinked03 >= 1) pass(`TC-03: 未紐づけ契約 = ${unlinked03}件`);
    else                                        fail(`TC-03: 未紐づけ契約カウントが期待と異なる（実際: ${unlinked03}）`);

    // 未紐づけ契約の値がオレンジ（warning）スタイルで表示されているか
    const warningStyle = await page.locator('#renewal-import-dialog').locator('[style*="text-warning"]').count();
    if (warningStyle > 0) pass('TC-03: 未紐づけ契約数がオレンジ（warning）色で表示');
    else                  fail('TC-03: 未紐づけ契約数の warning スタイルが見当たらない');

    // ==========================================================
    // TC-04: 既存契約・案件の更新
    //         セットアップSQL で CSV-TC04-001 / 2026-09-30 の契約+案件が存在
    // ==========================================================
    console.log('\n[TC-04] 既存契約・案件の更新');
    await runImport(page, join(FIXTURES, 'tc04_update.csv'), 'tc04_result');

    const text04  = await getDialogText(page);
    const badge04 = detectBadgeLabel(text04);
    if (badge04 === '完了') pass('TC-04: ステータス = 完了');
    else                    fail(`TC-04: ステータスが期待と異なる（実際: ${badge04}）`);

    const update04 = extractCount(text04, '契約 更新');
    if (update04 === 1) pass('TC-04: 契約 更新 = 1件（既存契約・案件が更新）');
    else                fail(`TC-04: 契約 更新が期待と異なる（実際: ${update04}）`);

    const insert04 = extractCount(text04, '契約 新規登録');
    if (insert04 === 0) pass('TC-04: 契約 新規登録 = 0件（新規作成なし）');
    else                fail(`TC-04: 契約 新規登録が期待と異なる（実際: ${insert04}）`);

    // ==========================================================
    // TC-05: スキップ・エラー混在
    //         行1: 正常（CSV-TC05-001）
    //         行2: スキップ（証券番号なし）
    //         行3: エラー（保険終期 = 9999/99/99）
    // ==========================================================
    console.log('\n[TC-05] スキップ・エラー混在（部分エラー）');
    await runImport(page, join(FIXTURES, 'tc05_mixed.csv'), 'tc05_result');

    const text05  = await getDialogText(page);
    const badge05 = detectBadgeLabel(text05);
    if (badge05 === '一部エラーあり') pass('TC-05: ステータス = 一部エラーあり');
    else                              fail(`TC-05: ステータスが期待と異なる（実際: ${badge05}）`);

    const total05 = extractTotalRows(text05);
    if (total05 === 3) pass('TC-05: 処理行数 = 3行');
    else               fail(`TC-05: 処理行数が期待と異なる（実際: ${total05}）`);

    const skip05 = extractCount(text05, 'スキップ');
    if (skip05 === 1) pass('TC-05: スキップ = 1行（証券番号なし）');
    else              fail(`TC-05: スキップが期待と異なる（実際: ${skip05}）`);

    const error05 = extractCount(text05, 'エラー');
    if (error05 === 1) pass('TC-05: エラー = 1行（日付不正）');
    else               fail(`TC-05: エラーが期待と異なる（実際: ${error05}）`);

    // エラー件数がred（danger）スタイルで強調表示されているか
    const errorStyle05 = await page.locator('#renewal-import-dialog').locator('[style*="text-danger"]').count();
    if (errorStyle05 > 0) pass('TC-05: エラー件数が赤（danger）色で表示');
    else                  fail('TC-05: エラー件数の danger スタイルが見当たらない');

    // エラー発生時のflashメッセージ確認
    const importErrorFlash05 = await page.locator('#renewal-import-dialog .error').count();
    if (importErrorFlash05 > 0) pass('TC-05: import_error flash メッセージが表示される');
    else                        fail('TC-05: import_error flash が見当たらない');

    // ==========================================================
    // TC-06: 必須ヘッダー欠落（証券番号なし）
    //         ヘッダーバリデーション失敗 → RuntimeException → import_error flash
    //         batch_id は URL に含まれず → 取込結果セクションなし
    // ==========================================================
    console.log('\n[TC-06] 必須ヘッダー欠落');
    await runImport(page, join(FIXTURES, 'tc06_missing_header.csv'), 'tc06_result');

    const dlgOpen06 = await isDialogOpen(page);
    if (dlgOpen06) pass('TC-06: ダイアログが自動オープン');
    else           fail('TC-06: ダイアログが開いていない');

    const hasResult06 = await hasImportResult(page);
    if (!hasResult06) pass('TC-06: 取込結果セクションが表示されない（ヘッダー検証失敗で中断）');
    else              fail('TC-06: 取込結果セクションが表示されてしまっている');

    // エラーflashメッセージの確認
    const text06 = await getDialogText(page);
    const hasErrorMsg06 = text06.includes('失敗') || text06.includes('ヘッダ') || text06.includes('エラー');
    if (hasErrorMsg06) pass('TC-06: エラーメッセージがダイアログ内に表示');
    else               fail(`TC-06: エラーメッセージが見当たらない（ダイアログ内テキスト冒頭: "${text06.slice(0, 80)}"）`);

    // ==========================================================
    // TC-07: スタッフコード解決
    //         CSV の 代理店ｺｰﾄﾞ = CSV-TC07-S1
    //         セットアップSQL で sjnet_code='CSV-TC07-S1' のスタッフが存在
    // ==========================================================
    console.log('\n[TC-07] スタッフコード解決');
    await runImport(page, join(FIXTURES, 'tc07_staff_resolved.csv'), 'tc07_result');

    const text07  = await getDialogText(page);
    const badge07 = detectBadgeLabel(text07);
    if (badge07 === '完了') pass('TC-07: ステータス = 完了');
    else                    fail(`TC-07: ステータスが期待と異なる（実際: ${badge07}）`);

    const total07 = extractTotalRows(text07);
    if (total07 === 1) pass('TC-07: 処理行数 = 1行');
    else               fail(`TC-07: 処理行数が期待と異なる（実際: ${total07}）`);

    // 担当者マッピング「解決済み 1件」の確認
    const resolved07 = extractCount(text07, '解決済み');
    if (resolved07 === 1) pass('TC-07: 担当者マッピング 解決済み = 1件');
    else                  fail(`TC-07: 解決済みカウントが期待と異なる（実際: ${resolved07}）`);

    // コード未登録が 0 件であること
    const unresolved07 = extractCount(text07, 'コード未登録');
    if (unresolved07 === 0) pass('TC-07: コード未登録 = 0件');
    else                    fail(`TC-07: コード未登録が期待と異なる（実際: ${unresolved07}）`);

    // ==========================================================
    // TC-08: 同一CSVを2回目取込 → 更新
    //         TC-01 で作成した CSV-TC01-001 が既存のため update になる
    // ==========================================================
    console.log('\n[TC-08] 同一CSVを2回目取込（更新確認）');
    await runImport(page, join(FIXTURES, 'tc01_new_customer.csv'), 'tc08_result');

    const text08  = await getDialogText(page);
    const badge08 = detectBadgeLabel(text08);
    if (badge08 === '完了') pass('TC-08: ステータス = 完了');
    else                    fail(`TC-08: ステータスが期待と異なる（実際: ${badge08}）`);

    const update08 = extractCount(text08, '契約 更新');
    if (update08 === 1) pass('TC-08: 契約 更新 = 1件（2回目は update になる）');
    else                fail(`TC-08: 契約 更新が期待と異なる（実際: ${update08}）`);

    const insert08 = extractCount(text08, '契約 新規登録');
    if (insert08 === 0) pass('TC-08: 契約 新規登録 = 0件（重複インポートで新規作成なし）');
    else                fail(`TC-08: 契約 新規登録が期待と異なる（実際: ${insert08}）`);

    const custInsert08 = extractCount(text08, '顧客 自動登録');
    if (custInsert08 === 0) pass('TC-08: 顧客 自動登録 = 0件（顧客は既存）');
    else                    fail(`TC-08: 顧客 自動登録が期待と異なる（実際: ${custInsert08}）`);

    // ── ブラウザを閉じる ──────────────────────────────────────
    await browser.close();

    // ── 結果サマリ ────────────────────────────────────────────
    console.log('\n' + '='.repeat(60));
    console.log('CSV取込 E2Eテスト 結果サマリ');
    console.log('='.repeat(60));
    let passCount = 0;
    let failCount = 0;
    for (const r of results) {
        const mark = r.status === 'PASS' ? '✓' : '✗';
        console.log(`  ${mark} ${r.label}${r.reason ? ' → ' + r.reason : ''}`);
        r.status === 'PASS' ? passCount++ : failCount++;
    }
    console.log('='.repeat(60));
    console.log(`  PASS: ${passCount}  FAIL: ${failCount}`);
    console.log(`  スクリーンショット保存先: ${SS_DIR}`);

    writeFileSync(
        join(SS_DIR, 'result.json'),
        JSON.stringify({ results, passCount, failCount, timestamp: new Date().toISOString() }, null, 2)
    );

    if (failCount > 0) process.exit(1);
}

run().catch(err => {
    console.error('予期しないエラー:', err);
    process.exit(1);
});
