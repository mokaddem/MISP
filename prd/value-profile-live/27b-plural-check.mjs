/**
 * The dropped-sections line takes its plural from the number it is
 * substituting. Cache is bypassed: asset URLs are pinned to MISP's
 * version, so a stale script would mimic a working fix.
 */
import { chromium } from '/home/sami/git/pivotick/node_modules/playwright/index.mjs';

const BASE = 'https://localhost';
const b64 = Buffer.from('8.8.8.8').toString('base64url');
const browser = await chromium.launch();
const ctx = await browser.newContext({ ignoreHTTPSErrors: true, bypassCSP: true });
await ctx.route('**/js/value-profile.js*', (route) =>
  route.continue({ headers: { ...route.request().headers(), 'cache-control': 'no-cache' } }));
const page = await ctx.newPage();
const errs = [];
page.on('pageerror', (e) => errs.push(e.message));

await page.goto(BASE + '/users/login', { waitUntil: 'domcontentloaded' });
await page.fill('input[name="data[User][email]"]', 'admin@admin.test');
await page.fill('input[name="data[User][password]"]', 'admin');
await Promise.all([
  page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
  page.click('input[type="submit"], button[type="submit"]'),
]);
await page.goto(`${BASE}/values/view/${b64}`, { waitUntil: 'domcontentloaded' });
await page.click('a[href="#tab-history"]').catch(() => {});
await page.waitForSelector('[data-vp-audit-section]', { timeout: 60000 });
await page.waitForTimeout(1500);

// Confirm the running script is the edited one.
const fresh = await page.evaluate(() =>
  typeof window.__vpProbe === 'undefined'
    ? [...document.querySelectorAll('script[src*="value-profile"]')].map((s) => s.src)
    : []);
console.log('script:', fresh);

const results = [];
const boxes = await page.$$('input[data-vp-facet-key]');
for (let i = 0; i < boxes.length && results.length < 6; i++) {
  const state = await page.evaluate(async (idx) => {
    const all = [...document.querySelectorAll('input[data-vp-facet-key]')];
    all.forEach((b) => { if (b.checked) b.click(); });
    await new Promise((r) => setTimeout(r, 250));
    all[idx].click();
    await new Promise((r) => setTimeout(r, 650));
    const note = document.querySelector('[data-vp-audit-dropped]');
    const heads = [...document.querySelectorAll('[data-vp-audit-count]')]
      .map((n) => n.textContent.trim());
    const emptied = heads.filter((h) => /^0 of /.test(h)).length;
    return {
      label: all[idx].closest('label')?.innerText.trim().slice(0, 26)
             || all[idx].value,
      emptied,
      text: (note?.textContent || '').trim(),
    };
  }, i);
  if (state.emptied > 0) results.push(state);
}

let bad = 0;
for (const r of results) {
  const singular = r.emptied === 1;
  const saysHas = /\bhas no entry\b/.test(r.text);
  const ok = singular ? saysHas : !saysHas;
  if (!ok) bad++;
  console.log(`${ok ? 'ok ' : 'BAD'}  emptied=${r.emptied}  "${r.text}"`);
}
console.log(bad === 0 ? '\nplural agrees with the count in every case'
                      : `\n${bad} MISMATCH`);
console.log('page errors:', errs.length ? errs : 'none');
await browser.close();
