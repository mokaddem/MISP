// Does the rail's neighbourhood graph mount a tooltip, and does the
// overlay still have one?
//
// Pivotick only *constructs* its Tooltip element when
// `UI.tooltip.enabled` survives the deep merge with
// `DEFAULT_UI_OPTIONS`, where the flag is `true`. So the honest witness
// is whether a `.pvt-tooltip` node exists inside each surface's
// container — not whether one happens to be visible at hover time.
//
// A hover is still driven on the rail, because "no element" and "an
// element that never shows" are different bugs and only the first is
// the fix.
//
// Not part of the application.
//
//   node 24d-graph-tooltip-harness.mjs https://localhost 8.8.8.8
import { chromium } from '/home/sami/git/pivotick/node_modules/playwright/index.mjs';

const [, , base, value] = process.argv;
const b64 = Buffer.from(value).toString('base64')
  .replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');

const browser = await chromium.launch();
const page = await browser.newPage({
  viewport: { width: 1600, height: 1200 },
  ignoreHTTPSErrors: true,
});
const errors = [];
page.on('pageerror', e => errors.push(e.message));

await page.goto(`${base}/users/login`, { waitUntil: 'networkidle' });
await page.fill('#UserEmail', 'admin@admin.test');
await page.fill('#UserPassword', 'admin');
await Promise.all([
  page.waitForNavigation({ waitUntil: 'networkidle' }),
  page.click('input[type=submit], button[type=submit]'),
]);

await page.goto(`${base}/values/view/${b64}`, { waitUntil: 'networkidle' });
await page.click('a[href="#tab-relationships"]').catch(() => {});
await page.waitForTimeout(1200);

const railHost = '[id^=vp-relgraph-]';
await page.waitForSelector(`${railHost} canvas, ${railHost} svg`,
  { timeout: 20000 }).catch(() => {});
await page.waitForTimeout(1500);

const rail = await page.evaluate(h => {
  const el = document.querySelector(h);
  if (!el) return { host: false };
  return {
    host: true,
    drawn: !!el.querySelector('canvas, svg'),
    tooltips: el.querySelectorAll('.pvt-tooltip').length,
    navs: el.querySelectorAll('[class*=graphnavigation]').length,
  };
}, railHost);

// Hover the middle of the rail canvas, which is where the centre node
// sits, and look again.
const box = await page.locator(`${railHost} canvas, ${railHost} svg`)
  .first().boundingBox().catch(() => null);
let afterHover = null;
if (box) {
  await page.mouse.move(box.x + box.width / 2, box.y + box.height / 2);
  await page.waitForTimeout(900);
  afterHover = await page.evaluate(h => {
    const el = document.querySelector(h);
    const all = [...document.querySelectorAll('.pvt-tooltip')];
    return {
      inHost: el ? el.querySelectorAll('.pvt-tooltip').length : -1,
      anywhere: all.length,
      shown: all.filter(t => t.classList.contains('shown')).length,
    };
  }, railHost);
}

// Now the overlay, which must keep its tooltip.
let overlay = null;
const opener = page.locator('[data-vp-relgraph-expand]').first();
if (await opener.count()) {
  await opener.click().catch(() => {});
  await page.waitForTimeout(2500);
  // Same gesture as the rail: the element is built on demand, so a
  // count taken without hovering says nothing about either surface.
  const obox = await page.locator('.vp-relgraph-overlay canvas,'
    + ' .vp-relgraph-overlay svg').first().boundingBox().catch(() => null);
  if (obox) {
    await page.mouse.move(obox.x + obox.width / 2,
      obox.y + obox.height / 2);
    await page.waitForTimeout(900);
  }
  overlay = await page.evaluate(() => {
    const el = document.querySelector('.vp-relgraph-overlay');
    if (!el) return { present: false };
    return {
      present: true,
      drawn: !!el.querySelector('canvas, svg'),
      anywhere: document.querySelectorAll('.pvt-tooltip').length,
    };
  });
}

console.log(JSON.stringify({ value, rail, afterHover, overlay, errors },
  null, 2));
await browser.close();
