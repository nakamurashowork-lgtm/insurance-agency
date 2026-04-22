// フェーズA UI目視確認スクリプト
// 実行: npx playwright test tests/e2e/phase_a_ui_check.mjs --reporter=list
// または: node tests/e2e/phase_a_ui_check.mjs

import { createRequire } from 'module';
const require = createRequire(import.meta.url);
const { chromium } = require('playwright');
import { writeFileSync, mkdirSync } from 'fs';
import { fileURLToPath } from 'url';
import { dirname, join } from 'path';
const __dirname = dirname(fileURLToPath(import.meta.url));
const PROJECT_ROOT = join(__dirname, '../..');

const BASE_URL  = 'http://127.0.0.1/insurance-agency/public';
const SS_DIR    = join(PROJECT_ROOT, 'tests/e2e/screenshots/phase_a');
const POLICY_C  = 'TEST-PHASE-C-001';
mkdirSync(SS_DIR, { recursive: true });

// 確認結果の記録
const results = [];
function pass(label) {
    console.log(`  [PASS] ${label}`);
    results.push({ label, status: 'PASS' });
}
function fail(label, reason = '') {
    console.log(`  [FAIL] ${label}${reason ? ': ' + reason : ''}`);
    results.push({ label, status: 'FAIL', reason });
}

async function run() {
    const browser = await chromium.launch({ headless: true });
    const page    = await browser.newPage();
    page.setDefaultTimeout(10000);

    // ── ① dev ログイン ──────────────────────────────────────────
    console.log('\n[dev login]');
    await page.goto(`${BASE_URL}/?route=dev/login`);
    await page.waitForURL(/dashboard/);
    pass('dev login → dashboard redirect');

    // ── ① 満期一覧画面（テスト契約のみ絞り込み）───────────────
    console.log('\n[① 満期一覧]');
    // customer_name フィルタでテスト契約を全件表示（ページをまたがないよう絞り込む）
    await page.goto(`${BASE_URL}/?route=renewal/list&customer_name=%E3%83%95%E3%82%A7%E3%83%BC%E3%82%BA`);
    await page.waitForLoadState('networkidle');
    await page.screenshot({ path: `${SS_DIR}/01_renewal_list.png`, fullPage: true });

    // 未紐づけバッジ
    const badge = await page.locator('text=未紐づけ').first();
    if (await badge.isVisible()) {
        const badgeText = await badge.textContent();
        pass(`未紐づけバッジ表示: "${badgeText?.trim()}"`);
    } else {
        fail('未紐づけバッジ非表示');
    }

    // TEST-PHASE-C-001 行の顧客名がプレーンテキスト（<a> なし）
    const rowC = page.locator('tr').filter({ hasText: POLICY_C }).first();
    if (await rowC.count() > 0) {
        // 行内の <a> タグで顧客名リンクがないことを確認
        const links = rowC.locator('a[href*="customer/detail"]');
        const linkCount = await links.count();
        if (linkCount === 0) {
            pass('TEST-PHASE-C-001: 顧客名がプレーンテキスト（リンクなし）');
        } else {
            fail('TEST-PHASE-C-001: 顧客名が <a> タグになっている（期待: プレーンテキスト）');
        }
        // sjnet_customer_name が表示されているか
        const cellText = await rowC.textContent();
        if (cellText?.includes('フェーズC同姓同名')) {
            pass('TEST-PHASE-C-001: sjnet_customer_name 表示あり');
        } else {
            fail('TEST-PHASE-C-001: sjnet_customer_name が見当たらない', cellText ?? '');
        }
    } else {
        fail('TEST-PHASE-C-001: 一覧に行が見当たらない');
    }

    // 他の契約（A/B/D）が顧客名リンクを持つか
    for (const [policy, customerName] of [
        ['TEST-PHASE-A-001', 'フェーズAテスト花子'],
        ['TEST-PHASE-B-001', 'フェーズBテスト太郎'],
        ['TEST-PHASE-D-001', 'フェーズDテスト山田'],
    ]) {
        const row = page.locator('tr').filter({ hasText: policy }).first();
        if (await row.count() > 0) {
            const link = row.locator('a[href*="customer/detail"]').first();
            if (await link.count() > 0) {
                pass(`${policy}: 顧客名がリンク表示`);
            } else {
                fail(`${policy}: 顧客名リンクが見当たらない`);
            }
        } else {
            fail(`${policy}: 一覧に行が見当たらない（ページ外の可能性あり）`);
        }
    }

    // ── ② 満期詳細（未リンク状態）──────────────────────────────
    console.log('\n[② 満期詳細 TEST-PHASE-C-001（未リンク）]');

    // TEST-PHASE-C-001 の満期詳細ページへのリンクをたどる
    let detailUrl = null;
    const rowC2 = page.locator('tr').filter({ hasText: POLICY_C }).first();
    if (await rowC2.count() > 0) {
        // 行の最初のリンク（詳細リンクのはず）
        const rowLink = rowC2.locator('a[href*="renewal/detail"]').first();
        if (await rowLink.count() > 0) {
            detailUrl = await rowLink.getAttribute('href');
        } else {
            // 証券番号などがリンクの場合
            const anyLink = rowC2.locator('a').first();
            if (await anyLink.count() > 0) detailUrl = await anyLink.getAttribute('href');
        }
    }

    if (!detailUrl) {
        fail('TEST-PHASE-C-001: 詳細ページへのリンクが見つからない');
    } else {
        await page.goto(detailUrl.startsWith('http') ? detailUrl : `${BASE_URL}${detailUrl}`);
        await page.screenshot({ path: `${SS_DIR}/02_renewal_detail_unlinked.png`, fullPage: true });

        // 未リンク状態の表示確認（「顧客情報」パネルの「未紐づけ」バッジ）
        const unlinkedBadge = page.locator('.badge').filter({ hasText: '未紐づけ' }).first();
        if (await unlinkedBadge.isVisible()) {
            pass('詳細: 「未紐づけ」バッジ表示あり（未リンク状態）');
        } else {
            fail('詳細: 「未紐づけ」バッジが見当たらない');
        }

        // 「紐づける」ボタン
        const linkBtn = page.locator('button, [role="button"]').filter({ hasText: '紐づける' }).first();
        if (await linkBtn.count() > 0 && await linkBtn.isVisible()) {
            pass('詳細: 「紐づける」ボタン表示あり');
        } else {
            fail('詳細: 「紐づける」ボタンが見当たらない');
        }

        // sjnet_customer_name の表示
        const content = await page.content();
        if (content.includes('フェーズC同姓同名')) {
            pass('詳細: sjnet_customer_name「フェーズC同姓同名」表示あり');
        } else {
            fail('詳細: sjnet_customer_name が見当たらない');
        }

        // ── ③ 顧客選択モーダル ────────────────────────────────
        console.log('\n[③ 顧客選択モーダル]');
        await linkBtn.click();
        await page.waitForTimeout(500);
        await page.screenshot({ path: `${SS_DIR}/03_modal_open.png`, fullPage: true });

        // モーダル内の検索フォーム
        const searchInput = page.locator('dialog input[type="text"], dialog input[type="search"]').first();
        if (await searchInput.count() > 0 && await searchInput.isVisible()) {
            pass('モーダル: 検索フォーム表示あり');
            await searchInput.fill('フェーズC');
            await page.waitForTimeout(800); // API レスポンス待ち
            await page.screenshot({ path: `${SS_DIR}/03_customer_search_modal.png`, fullPage: true });

            // 候補2件
            const resultRows = page.locator('dialog tbody tr');
            const rowCount = await resultRows.count();
            if (rowCount === 2) {
                pass(`モーダル: 候補 ${rowCount} 件表示（期待: 2件）`);
            } else {
                fail(`モーダル: 候補 ${rowCount} 件（期待: 2件）`);
            }
        } else {
            fail('モーダル: 検索フォームが見当たらない');
        }

        // ── ④ 紐づけ操作 ──────────────────────────────────────
        console.log('\n[④ 紐づけ操作]');
        // 1件目の「選択」ボタンをクリック
        const selectBtn = page.locator('dialog tbody tr').first().locator('button').first();
        if (await selectBtn.count() > 0) {
            await selectBtn.click();
            // フォーム送信（dialog 内の hidden form が submit されるはず）
            await page.waitForURL(/renewal\/detail/, { timeout: 5000 }).catch(() => {});
            await page.screenshot({ path: `${SS_DIR}/04_renewal_detail_linked.png`, fullPage: true });

            // 顧客名リンク表示
            const customerLink = page.locator('a[href*="customer/detail"]').first();
            if (await customerLink.count() > 0) {
                const linkText = await customerLink.textContent();
                pass(`詳細（紐づけ後）: 顧客名がリンク表示: "${linkText?.trim()}"`);
            } else {
                fail('詳細（紐づけ後）: 顧客名リンクが見当たらない');
            }
        } else {
            fail('モーダル: 選択ボタンが見当たらない');
        }

        // ── ④ 満期一覧でバッジ消滅確認 ───────────────────────
        console.log('\n[④ 満期一覧 紐づけ後]');
        await page.goto(`${BASE_URL}/?route=renewal/list&customer_name=%E3%83%95%E3%82%A7%E3%83%BC%E3%82%BA`);
        await page.waitForLoadState('networkidle');
        await page.screenshot({ path: `${SS_DIR}/05_renewal_list_after_link.png`, fullPage: true });

        const badgeAfter = page.locator('text=未紐づけ').first();
        if (await badgeAfter.isVisible()) {
            const t = await badgeAfter.textContent();
            if (t?.includes('0')) {
                pass(`バッジ: 「${t?.trim()}」（0件表示）`);
            } else {
                fail(`バッジ: まだ残っている「${t?.trim()}」`);
            }
        } else {
            pass('バッジ: 非表示（0件で消えた）');
        }

        // 一覧の TEST-PHASE-C-001 行が今度はリンクになっているか
        const rowCAfter = page.locator('tr', { hasText: POLICY_C }).first();
        if (await rowCAfter.count() > 0) {
            const linkAfter = rowCAfter.locator('a[href*="customer/detail"]').first();
            if (await linkAfter.count() > 0) {
                pass('一覧（紐づけ後）: TEST-PHASE-C-001 の顧客名がリンク表示');
            } else {
                fail('一覧（紐づけ後）: TEST-PHASE-C-001 の顧客名がまだプレーンテキスト');
            }
        }

        // ── ⑤ 紐づけ解除 ─────────────────────────────────────
        console.log('\n[⑤ 紐づけ解除]');
        const rowCAgain = page.locator('tr').filter({ hasText: POLICY_C }).first();
        const detailLinkAgain = rowCAgain.locator('a[href*="renewal/detail"]').first();
        if (await detailLinkAgain.count() > 0) {
            const href = await detailLinkAgain.getAttribute('href');
            await page.goto(href?.startsWith('http') ? href : `${BASE_URL}${href}`);

            // 「解除」ボタンを探す
            const unlinkBtn = page.locator('button').filter({ hasText: /解除/ }).first();
            if (await unlinkBtn.count() > 0 && await unlinkBtn.isVisible()) {
                await unlinkBtn.click();
                // confirm dialog が出る場合は accept
                page.once('dialog', d => d.accept());
                await page.waitForTimeout(500);
                // 確認フォームがあれば送信
                const confirmForm = page.locator('form').filter({ hasText: /解除/ }).first();
                if (await confirmForm.count() > 0) {
                    await confirmForm.locator('button[type="submit"]').first().click();
                    await page.waitForURL(/renewal\/detail/, { timeout: 5000 }).catch(() => {});
                }
                await page.screenshot({ path: `${SS_DIR}/06_renewal_detail_unlinked_again.png`, fullPage: true });

                const unlinkedAgain = page.locator('.badge').filter({ hasText: '未紐づけ' }).first();
                if (await unlinkedAgain.isVisible()) {
                    pass('解除後: 「未紐づけ」バッジ表示に戻った');
                } else {
                    fail('解除後: 「未紐づけ」バッジが見当たらない');
                }
            } else {
                fail('解除: 「解除」ボタンが見当たらない');
            }
        }
    }

    await browser.close();

    // ── 結果サマリ ─────────────────────────────────────────────
    console.log('\n' + '='.repeat(60));
    console.log('結果サマリ');
    console.log('='.repeat(60));
    let passCount = 0, failCount = 0;
    for (const r of results) {
        const mark = r.status === 'PASS' ? '✓' : '✗';
        console.log(`  ${mark} ${r.label}${r.reason ? ' → ' + r.reason : ''}`);
        r.status === 'PASS' ? passCount++ : failCount++;
    }
    console.log('='.repeat(60));
    console.log(`  PASS: ${passCount}  FAIL: ${failCount}`);

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
