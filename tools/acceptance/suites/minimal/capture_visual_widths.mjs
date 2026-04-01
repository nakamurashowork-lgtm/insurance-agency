import fs from 'node:fs';
import path from 'node:path';
import { chromium } from 'playwright';

const root = process.cwd();
const targetPath = path.join(root, 'tmp', 'visual_targets.json');
const outDir = path.join(root, 'tmp', 'visual_shots');
const pcDir = path.join(outDir, 'pc');
const spDir = path.join(outDir, 'sp');

for (const dir of [outDir, pcDir, spDir]) {
  fs.mkdirSync(dir, { recursive: true });
}

const targets = JSON.parse(fs.readFileSync(targetPath, 'utf8'));
const routes = targets.routes;
const sessionCookieName = targets.session_cookie_name;
const sessionId = targets.session_id;

const browser = await chromium.launch({ headless: true });

async function captureSet(label, viewport, isMobile) {
  const context = await browser.newContext({ viewport, isMobile, hasTouch: isMobile });

  await context.addCookies([
    {
      name: sessionCookieName,
      value: sessionId,
      domain: 'localhost',
      path: '/',
      httpOnly: false,
      secure: false,
      sameSite: 'Lax',
    },
  ]);

  const report = {};

  for (const [name, url] of Object.entries(routes)) {
    const page = await context.newPage();
    await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 60000 });
    await page.waitForTimeout(800);

    const metrics = await page.evaluate(() => {
      const docEl = document.documentElement;
      const body = document.body;
      const pageScrollWidth = Math.max(docEl?.scrollWidth ?? 0, body?.scrollWidth ?? 0);
      const pageClientWidth = docEl?.clientWidth ?? 0;

      const tableWraps = Array.from(document.querySelectorAll('.table-wrap'));
      const overflowingTableWraps = tableWraps.filter((el) => el.scrollWidth > el.clientWidth + 1).length;

      const allOverflowingBlocks = Array.from(document.querySelectorAll('body *')).filter((el) => {
        const style = window.getComputedStyle(el);
        if (style.position === 'fixed') {
          return false;
        }
        return el.scrollWidth > el.clientWidth + 1;
      }).length;

      const mainTitle = document.querySelector('.title')?.textContent?.trim() ?? '';

      return {
        mainTitle,
        pageClientWidth,
        pageScrollWidth,
        hasPageHorizontalScroll: pageScrollWidth > pageClientWidth + 1,
        overflowingTableWraps,
        allOverflowingBlocks,
      };
    });

    const filePath = path.join(label === 'pc' ? pcDir : spDir, `${name}.png`);
    await page.screenshot({ path: filePath, fullPage: true });

    report[name] = {
      url,
      screenshot: path.relative(root, filePath).replaceAll('\\', '/'),
      ...metrics,
    };

    await page.close();
  }

  await context.close();
  return report;
}

const pcReport = await captureSet('pc', { width: 1440, height: 900 }, false);
const spReport = await captureSet('sp', { width: 390, height: 844 }, true);

const finalReport = {
  generatedAt: new Date().toISOString(),
  pc: pcReport,
  sp: spReport,
};

const reportPath = path.join(outDir, 'metrics.json');
fs.writeFileSync(reportPath, JSON.stringify(finalReport, null, 2), 'utf8');

await browser.close();
console.log(path.relative(root, reportPath).replaceAll('\\', '/'));
