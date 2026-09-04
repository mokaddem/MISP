// Measure every .vp-rel-cap on the live Relationships tab against the
// .vp-panel that holds it. The caption is designed full-bleed: its left
// and right edges should sit on the panel's inner edges. A caption still
// inside a padded wrapper is inset, which is the defect being checked.
//
// Not part of the application.
//
//   node capgeom.mjs https://localhost 8.8.8.8
import { chromium } from '/home/sami/git/pivotick/node_modules/playwright/index.mjs';

const [, , base, value] = process.argv;
const b64 = Buffer.from(value, 'utf8').toString('base64')
  .replace(/\+/g, '-').replace(/\//g, '_');

const browser = await chromium.launch();
const ctx = await browser.newContext({
  ignoreHTTPSErrors: true,
  viewport: { width: 1500, height: 1200 },
});
const page = await ctx.newPage();
const errors = [];
page.on('pageerror', e => errors.push(`pageerror: ${e.message}`));

await page.goto(`${base}/users/login`, { waitUntil: 'domcontentloaded' });
await page.fill('#UserEmail', 'admin@admin.test');
await page.fill('#UserPassword', 'admin');
await Promise.all([
  page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
  page.click('input[type="submit"], button[type="submit"]'),
]);

await page.goto(`${base}/values/view/${b64}`, { waitUntil: 'domcontentloaded' });
await page.waitForTimeout(1500);
await page.click('.nav-link[href="#tab-relationships"]');
await page.waitForTimeout(4000);
for (let i = 0; i < 20; i++) {
  await page.mouse.wheel(0, 1200);
  await page.waitForTimeout(600);
}
await page.waitForTimeout(4000);

const rows = await page.evaluate(() => {
  const pane = document.querySelector('#tab-relationships');
  const out = [];
  for (const cap of pane.querySelectorAll('.vp-rel-cap')) {
    const panel = cap.closest('.vp-panel');
    const c = cap.getBoundingClientRect();
    if (!c.width) continue;                     // a hidden branch
    if (!panel) {
      out.push({ noPanel: true, text: cap.innerText.trim().slice(0, 44) });
      continue;
    }
    const p = panel.getBoundingClientRect();
    const ps = getComputedStyle(panel);
    const innerL = p.left + parseFloat(ps.borderLeftWidth)
      + parseFloat(ps.paddingLeft);
    const innerR = p.right - parseFloat(ps.borderRightWidth)
      - parseFloat(ps.paddingRight);
    out.push({
      panelId: panel.id || '(unnamed)',
      text: cap.innerText.trim().replace(/\s+/g, ' ').slice(0, 40),
      dl: +(c.left - innerL).toFixed(1),
      dr: +(innerR - c.right).toFixed(1),
    });
  }
  return out;
});

let bad = 0;
console.log(' res     dL    dR   panel                     caption');
for (const r of rows) {
  if (r.noPanel) { console.log(' ???                                       ', r.text); continue; }
  const off = Math.abs(r.dl) > 1.5 || Math.abs(r.dr) > 1.5;
  if (off) bad++;
  console.log(`${off ? ' BAD' : '  OK'} ${String(r.dl).padStart(6)} `
    + `${String(r.dr).padStart(5)}   ${r.panelId.padEnd(24)}  ${r.text}`);
}
console.log(`\ncaptions measured: ${rows.length}, inset: ${bad}`);
if (errors.length) console.log('JS errors:\n  ' + errors.join('\n  '));
await browser.close();
process.exit(bad ? 1 : 0);
