// Screenshot B9's tactic group on the live instance, in both themes.
//
// The card is lazily loaded and its chips carry a hover card, so
// neither the strip nor the hover can be checked by reading markup:
// `24-relationships-browser.md` is the standing note on why —
// `value-profile.js` binds nothing unless `body[data-controller]` is
// `values`, and a card read off an inert page looks exactly like a live
// one. This logs in with the form (an authkey 302s on HTML endpoints),
// opens the value page, clicks through to Relationships, waits for the
// rail card, and captures it.
//
// Not part of the application.
//
//   node 24b-tactics-harness.mjs 8.8.8.8 /tmp/tactics
import { chromium } from '/home/sami/git/pivotick/node_modules/playwright/index.mjs';

const [, , value, outPrefix] = process.argv;
const BASE = 'https://localhost';
const b64 = Buffer.from(value, 'utf8').toString('base64url');

const browser = await chromium.launch();
const context = await browser.newContext({
  ignoreHTTPSErrors: true,
  viewport: { width: 1600, height: 1200 },
});
const page = await context.newPage();
const logs = [];
page.on('console', m => {
  if (m.type() === 'error') logs.push(`console: ${m.text()}`);
});
page.on('pageerror', e => logs.push(`pageerror: ${e.message}`));

await page.goto(`${BASE}/users/login`, { waitUntil: 'domcontentloaded' });
await page.fill('#UserEmail', 'admin@admin.test');
await page.fill('#UserPassword', 'admin');
await Promise.all([
  page.waitForURL(u => !String(u).includes('/users/login'), { timeout: 30000 }),
  page.click('#UserLoginForm button[type="submit"]'),
]);

await page.goto(`${BASE}/values/view/${b64}`, { waitUntil: 'domcontentloaded' });

// The tab bar is the only way in: the panels behind it are lazy.
const tab = page.locator('a,button').filter({ hasText: /^\s*Relationships/ }).first();
await tab.click();
await page.waitForSelector('[data-vp-threats]', { timeout: 30000 });
await page.waitForFunction(
  () => !!document.querySelector('[data-vp-threats] .vp-tactic, [data-vp-threats] .vp-threat-none'),
  null,
  { timeout: 30000 }
);
await page.waitForTimeout(700);

const read = () => page.evaluate(() => {
  const card = document.querySelector('[data-vp-threats]');
  const chips = [...card.querySelectorAll('.vp-tactic')];
  return {
    controller: document.body.dataset.controller,
    threats: card.querySelectorAll('.vp-threat').length,
    chips: chips.map(c => ({
      name: c.querySelector('.vp-tactic-name')?.textContent.trim(),
      n: c.querySelector('.vp-tactic-n')?.textContent.trim(),
      unplaced: c.classList.contains('vp-tactic-unplaced'),
      // One line each, or the strip has wrapped a chip mid-word.
      lines: Math.round(c.getBoundingClientRect().height),
    })),
    note: card.querySelector('.vp-tactic-note')?.textContent
      .replace(/\s+/g, ' ').trim(),
    subhead: card.querySelector('.vp-tactics .vp-subhead')
      ?.textContent.trim(),
  };
});

console.log(JSON.stringify(await read(), null, 1));

const card = page.locator('[data-vp-threats]');
for (const theme of ['light', 'dark']) {
  await page.evaluate(t => {
    document.documentElement.setAttribute('data-bs-theme', t);
    document.documentElement.classList.toggle('dark', t === 'dark');
  }, theme);
  await page.waitForTimeout(350);
  await card.screenshot({ path: `${outPrefix}-${theme}.png` });
}

// The hover card, which is CSS on :hover and so needs a real pointer.
const chip = page.locator('[data-vp-threats] .vp-tactic').first();
if (await chip.count()) {
  await page.evaluate(() => {
    document.documentElement.setAttribute('data-bs-theme', 'light');
    document.documentElement.classList.remove('dark');
  });
  await chip.hover();
  await page.waitForTimeout(500);
  const tip = await page.evaluate(() => {
    const t = document.querySelector('[data-vp-threats] .vp-tactic .vp-claim-tip');
    if (!t) return null;
    const r = t.getBoundingClientRect();
    return {
      visible: getComputedStyle(t).visibility,
      opacity: getComputedStyle(t).opacity,
      left: Math.round(r.left), width: Math.round(r.width),
      offscreen: r.left < 0 || r.right > window.innerWidth,
    };
  });
  console.log('hover:', JSON.stringify(tip));
  await page.screenshot({ path: `${outPrefix}-hover.png`, fullPage: false });
}

if (logs.length) console.log('LOGS:', logs.slice(0, 8).join(' | '));
await browser.close();
