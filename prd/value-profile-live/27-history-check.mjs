/**
 * Phase 27 browser pass — the History tab on the real page, both
 * themes, both readers where the run is given a second login.
 */
import { chromium } from '/home/sami/git/pivotick/node_modules/playwright/index.mjs';

const BASE = 'https://localhost';
const EMAIL = process.env.MISP_EMAIL || 'admin@admin.test';
const PASS = process.env.MISP_PASS || 'admin';
const VALUE = process.env.MISP_VALUE || '8.8.8.8';
const b64 = Buffer.from(VALUE).toString('base64url');

const out = (...a) => console.log(...a);

const contrast = (fg, bg) => {
  const lum = (c) => {
    const [r, g, b] = c.match(/\d+(\.\d+)?/g).slice(0, 3).map(Number)
      .map((v) => v / 255)
      .map((v) => (v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4));
    return 0.2126 * r + 0.7152 * g + 0.0722 * b;
  };
  const a = lum(fg), b = lum(bg);
  return ((Math.max(a, b) + 0.05) / (Math.min(a, b) + 0.05));
};

const browser = await chromium.launch();
const ctx = await browser.newContext({ ignoreHTTPSErrors: true });
const page = await ctx.newPage();
const errors = [];
page.on('console', (m) => { if (m.type() === 'error') errors.push(m.text()); });
page.on('pageerror', (e) => errors.push('pageerror: ' + e.message));

await page.goto(BASE + '/users/login', { waitUntil: 'domcontentloaded' });
await page.fill('input[name="data[User][email]"]', EMAIL);
await page.fill('input[name="data[User][password]"]', PASS);
await Promise.all([
  page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
  page.click('input[type="submit"], button[type="submit"]'),
]);
out('logged in as', EMAIL, '->', page.url());

await page.goto(`${BASE}/values/view/${b64}`, { waitUntil: 'domcontentloaded' });
await page.click('a[href="#tab-history"]').catch(() => {});
await page.waitForSelector('[data-vp-audit-section]', { timeout: 60000 });
await page.waitForTimeout(1500);

const shape = await page.evaluate(() => {
  const sections = [...document.querySelectorAll('[data-vp-audit-section]')];
  const rows = [...document.querySelectorAll('[data-vp-list-row]')];
  return {
    sections: sections.length,
    rows: rows.length,
    visibleRows: rows.filter((r) => r.offsetParent !== null).length,
    header: (document.querySelector('[data-vp-list-shown]')?.textContent || '').trim(),
    total: (document.querySelector('[data-vp-list-total]')?.textContent || '').trim(),
    facetGroups: document.querySelectorAll('[data-vp-facet-group]').length,
    facetRows: document.querySelectorAll('input[data-vp-facet-key]').length,
    aclBand: (document.querySelector('.vp-acl-note-band')?.innerText || '').trim().slice(0, 90),
    elided: (document.querySelector('[data-vp-audit-elided]')?.innerText || '').trim().slice(0, 120),
    hiddenBands: [...document.querySelectorAll('.vp-acl-note')]
      .filter((n) => /cannot see|not obtainable/i.test(n.innerText)).length,
    diffToggles: document.querySelectorAll('[data-vp-audit-diff]').length,
    chart: !!document.querySelector('canvas'),
  };
});
out('shape:', JSON.stringify(shape, null, 1));

// The diff opens from the row, with no network request.
const requests = [];
page.on('request', (r) => requests.push(r.url()));
const before = requests.length;
const firstToggle = await page.$('[data-vp-audit-diff]');
let diffOpened = null;
if (firstToggle) {
  await firstToggle.click();
  await page.waitForTimeout(700);
  diffOpened = await page.evaluate(() => {
    const t = document.querySelector('table.vp-audit-diff');
    return t ? { visible: t.offsetHeight > 0, height: t.offsetHeight,
                 width: t.offsetWidth, rows: t.rows.length } : null;
  });
}
const newReqs = requests.slice(before).filter((u) => /fullChange|viewHistory/.test(u));
out('diff opened:', JSON.stringify(diffOpened), 'requests during open:', newReqs);

// A facet narrows, and the section headers follow.
const facetState = await page.evaluate(() => {
  const box = document.querySelector('input[data-vp-facet-key]');
  if (!box) return null;
  box.click();
  return new Promise((res) => setTimeout(() => {
    const heads = [...document.querySelectorAll('[data-vp-audit-count]')]
      .map((n) => n.textContent.trim()).slice(0, 6);
    res({ heads, shown: (document.querySelector('[data-vp-list-shown]')?.textContent || '').trim() });
  }, 600));
});
out('after one facet:', JSON.stringify(facetState));

for (const theme of ['light', 'dark']) {
  await page.evaluate((t) => {
    document.documentElement.setAttribute('data-bs-theme', t);
  }, theme);
  await page.waitForTimeout(400);
  const c = await page.evaluate(() => {
    const nodes = [...document.querySelectorAll(
      '.vp-audit-act, .vp-audit-row, .vp-acl-note-band, .vp-audit-diff th,'
      + ' .vp-audit-diff td, .vp-panel, .vp-facetgrp, .vp-facetgrp label,'
      + ' .vp-audit-mix, [data-vp-audit-count], .vp-empty, .vp-aside'
    )].slice(0, 40);
    return nodes.map((n) => {
      const s = getComputedStyle(n);
      let bg = s.backgroundColor, el = n;
      while (bg === 'rgba(0, 0, 0, 0)' && el.parentElement) {
        el = el.parentElement; bg = getComputedStyle(el).backgroundColor;
      }
      return { cls: n.className.split(' ')[0], fg: s.color, bg };
    });
  });
  const ratios = c.map((n) => contrast(n.fg, n.bg));
  out(`${theme}: ${ratios.length} nodes, contrast ` +
      `${Math.min(...ratios).toFixed(2)}–${Math.max(...ratios).toFixed(2)}`);
  await page.screenshot({
    path: `/home/sami/.claude/jobs/fdd384a2/tmp/history-${theme}.png`,
    fullPage: false,
  });
}

out('console errors:', errors.length ? errors.slice(0, 5) : 'none');
await browser.close();
