// Draw the Proposed additions block in a real browser, in both themes,
// and report its geometry rather than its presence.
//
// `misp-ui-interaction-harness` is the rule this follows: a block that
// exists in the DOM and renders 0px tall, or overflows its card, looks
// exactly like a working one to a string search of the HTML. So this
// measures — the card's box, every row's box, and whether anything
// spills horizontally — and it checks the block's colour actually
// resolved rather than falling back to an unset custom property.
//
// Not part of the application.
//
//   node 22b-standalone-render.mjs 123.123.123.1 out-prefix
import { chromium } from '/home/sami/git/pivotick/node_modules/playwright/index.mjs';

const [, , value, prefix] = process.argv;
const BASE = 'https://localhost';

const browser = await chromium.launch();
const context = await browser.newContext({
  ignoreHTTPSErrors: true,
  viewport: { width: 1400, height: 1200 },
});
const page = await context.newPage();
const problems = [];
page.on('pageerror', e => problems.push(`pageerror: ${e.message}`));
page.on('console', m => {
  if (m.type() === 'error') problems.push(`console: ${m.text()}`);
});

await page.goto(`${BASE}/users/login`, { waitUntil: 'domcontentloaded' });
await page.fill('input[name="data[User][email]"]', 'admin@admin.test');
await page.fill('input[name="data[User][password]"]', 'admin');
await Promise.all([
  page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
  page.click('input[type="submit"], button[type="submit"]'),
]);

const b64 = Buffer.from(value).toString('base64url').replace(/=+$/, '');
await page.goto(`${BASE}/values/view/${b64}`, { waitUntil: 'load' });

// The tabs are lazily loaded; the block lives in the Occurrences one.
await page.click('a[href="#tab-occurrences"]');
await page.waitForSelector('[data-vp-standalone-proposals]',
  { timeout: 20000 });
await page.waitForTimeout(900);

for (const theme of ['light', 'dark']) {
  await page.evaluate(t => {
    document.documentElement.setAttribute('data-bs-theme', t);
  }, theme);
  await page.waitForTimeout(300);

  const read = await page.evaluate(() => {
    const card = document.querySelector('[data-vp-standalone-proposals]');
    if (!card) return { card: false };
    const box = card.getBoundingClientRect();
    const cs = getComputedStyle(card);
    const rows = [...card.querySelectorAll('.vp-sap')].map(r => {
      const b = r.getBoundingClientRect();
      const value = r.querySelector('.vp-sap-value');
      return {
        h: Math.round(b.height),
        w: Math.round(b.width),
        gone: r.classList.contains('vp-sap-gone'),
        value: value ? value.textContent.trim() : null,
        strike: value
          ? getComputedStyle(value).textDecorationLine
          : null,
      };
    });
    return {
      card: true,
      h: Math.round(box.height),
      w: Math.round(box.width),
      accent: cs.getPropertyValue('--vp-panel-color').trim(),
      // The card paints its accent from --vp-tl-proposal; an unset
      // custom property resolves to the empty string, which is the
      // failure a screenshot would not show in a thumbnail.
      resolved: getComputedStyle(document.documentElement)
        .getPropertyValue('--vp-tl-proposal').trim(),
      overflowsX: document.documentElement.scrollWidth
        > document.documentElement.clientWidth,
      rows,
    };
  });

  console.log(`-- ${theme} --`);
  console.log(JSON.stringify(read, null, 1));

  const card = await page.$('[data-vp-standalone-proposals]');
  if (card) await card.screenshot({ path: `${prefix}-${theme}.png` });
}

console.log(problems.length ? `PROBLEMS: ${problems.join(' | ')}`
  : 'no console errors');
await browser.close();
