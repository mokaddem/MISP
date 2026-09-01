// Drive one of phase 26's two new table panels in a real browser, and
// report that its client-side machinery is actually live before
// reporting anything it did.
//
// `24-relationships-browser.md` explains why the witness comes first:
// `value-profile.js` binds nothing unless `document.body.dataset
// .controller` is `values` and the container fires
// `misp:container-loaded`, and both gates fail silently. A row order
// read back from an inert page looks exactly like a sorted one.
//
// Not part of the application.
//
//   node 26-panel-harness.mjs http://127.0.0.1:8917 \
//        /__frag-github-dated.html light out.png
import { chromium } from '/home/sami/git/pivotick/node_modules/playwright/index.mjs';

const [, , base, fragment, theme, outPng] = process.argv;

const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: 1180, height: 1400 } });
const logs = [];
page.on('console', m => logs.push(`${m.type()}: ${m.text()}`));
page.on('pageerror', e => logs.push(`pageerror: ${e.message}`));

await page.goto(
  `${base}/__harness.html?theme=${theme}&wide=1&frag=${fragment}`,
  { waitUntil: 'load' }
);
await page.waitForFunction(() => document.body.dataset.fragReady === '1',
  null, { timeout: 15000 }).catch(() => {});
await page.waitForTimeout(600);

const read = () => page.evaluate(() => {
  const list = document.querySelector('[data-vp-list]');
  if (!list) return { list: false };
  const rows = [...list.querySelectorAll('tbody tr')];
  const shown = rows.filter(r => r.style.display !== 'none'
    && !r.classList.contains('d-none'));
  const pager = list.querySelector('[data-vp-pager]');
  const range = pager
    ? pager.textContent.replace(/\s+/g, ' ').trim().slice(0, 80)
    : null;
  return {
    list: true,
    rows: rows.length,
    visible: shown.length,
    range,
    pages: list.querySelectorAll('[data-vp-pager] [data-vp-page]')
      .length,
  };
});

// The witness: an unpaged list shows every row, a live one shows
// page_size of them.
const before = await read();
let after = null;
if (before.rows > before.visible) {
  await page.click('[data-vp-pager] [data-vp-page="2"]');
  await page.waitForTimeout(300);
  after = await read();
}

if (outPng) {
  await page.screenshot({ path: outPng, fullPage: true });
}
console.log(JSON.stringify({ before, after, logs }, null, 2));
await browser.close();
