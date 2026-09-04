// §21. The spine's key as a filter: a press narrows the chart and the
// chronology, shift-press drops one source, and the lane buttons and
// the key stay in step because both are recomputed from one state.
//
//   node prd/value-profile-live/25-key-filter-harness.mjs <value> [theme]
import { chromium } from '/home/sami/git/pivotick/node_modules/playwright/index.mjs';

const BASE = 'https://localhost';
const OUT = '/home/sami/.claude/jobs/fdd384a2/tmp';
const value = process.argv[2] || '8.8.8.8';
const theme = process.argv[3] || 'light';
const b64 = Buffer.from(value).toString('base64')
  .replace(/\+/g, '-').replace(/\//g, '_');

const browser = await chromium.launch();
const ctx = await browser.newContext({ ignoreHTTPSErrors: true,
  viewport: { width: 1600, height: 1400 } });
const page = await ctx.newPage();
page.on('pageerror', e => console.log('PAGEERROR', String(e).slice(0, 300)));
page.on('console', m => {
  if (m.type() === 'error') console.log('CONSOLE', m.text().slice(0, 200));
});

await page.goto(BASE + '/users/login', { waitUntil: 'domcontentloaded' });
await page.fill('input[name="data[User][email]"]', 'admin@admin.test');
await page.fill('input[name="data[User][password]"]', 'admin');
await Promise.all([
  page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
  page.click('button[type="submit"], input[type="submit"]'),
]);

await page.goto(`${BASE}/values/view/${b64}#tab-timeline`,
  { waitUntil: 'domcontentloaded' });
await page.waitForSelector('[data-vp-tl]', { timeout: 180000 });
await page.waitForTimeout(4000);
if (theme === 'dark') {
  await page.evaluate(
    () => document.documentElement.setAttribute('data-bs-theme', 'dark'));
  await page.waitForTimeout(800);
}

const read = () => page.evaluate(() => {
  const panel = document.querySelector('[data-vp-tl]');
  const canvas = document.getElementById('vp-tl-spine');
  const chart = window.Chart && Chart.getChart ? Chart.getChart(canvas) : null;
  const txt = (el) => el ? el.textContent.replace(/\s+/g, ' ').trim() : null;
  const rows = [...panel.querySelectorAll('[data-vp-tl-at]:not([hidden])')];
  const seen = {};
  rows.forEach((r) => {
    seen[r.dataset.vpTlSource] = (seen[r.dataset.vpTlSource] || 0) + 1;
  });
  return {
    keys: [...panel.querySelectorAll('[data-vp-tl-key]')].map((k) => ({
      source: k.dataset.vpTlKey,
      tag: k.tagName,
      on: k.getAttribute('aria-pressed'),
      disabled: k.disabled,
      title: k.title,
      swatch: (() => {
        const s = k.querySelector('.vp-tl-swatch');
        const cs = getComputedStyle(s);
        return { bg: cs.backgroundColor, ring: cs.boxShadow };
      })(),
    })),
    lanes: [...panel.querySelectorAll('[data-vp-tl-lane]')].map((b) => [
      b.dataset.vpTlLane, b.getAttribute('aria-pressed'),
    ]),
    datasets: chart
      ? chart.data.datasets.map((d, i) => [d.label, !!d.hidden,
        chart.isDatasetVisible(i)])
      : 'NO CHART',
    yMax: chart ? chart.scales.y.max : null,
    note: (() => {
      const n = panel.querySelector('[data-vp-tl-filter-note]');
      return { shown: n ? !n.hidden : null, text: txt(n) };
    })(),
    windowCount: txt(panel.querySelector('[data-vp-tl-window-count]')),
    listedBySource: seen,
    listed: rows.length,
    tally: [txt(panel.querySelector('[data-vp-tl-tally-exact]')),
      txt(panel.querySelector('[data-vp-tl-tally-part]'))],
    blank: (() => {
      const a = panel.querySelector('[data-vp-tl-blank]');
      const b = panel.querySelector('[data-vp-tl-blank-capped]');
      return {
        plain: a ? !a.hidden : null,
        capped: b ? !b.hidden : null,
        cappedText: txt(b),
      };
    })(),
  };
});

const press = async (source, mods) => {
  await page.click(`[data-vp-tl-key="${source}"]`,
    mods ? { modifiers: mods } : {});
  await page.waitForTimeout(500);
};

const show = (label, state) => {
  console.log(`\n=== ${label} ===`);
  console.log(' keys   ', state.keys.map(
    (k) => `${k.source}:${k.on}${k.disabled ? '(off)' : ''}`).join(' '));
  console.log(' lanes  ', state.lanes.map((l) => l.join(':')).join(' '));
  console.log(' chart  ', JSON.stringify(state.datasets), 'yMax', state.yMax);
  console.log(' note   ', state.note.shown, JSON.stringify(state.note.text));
  console.log(' window ', state.windowCount, 'listed', state.listed,
    JSON.stringify(state.listedBySource), 'tally', state.tally.join('/'));
  console.log(' blank  ', JSON.stringify(state.blank));
  console.log(' swatch ', state.keys.map(
    (k) => `${k.source}:${k.swatch.bg}|${k.swatch.ring}`).join('  '));
};

const first = await read();
show('default', first);
console.log('\n titles:', first.keys.map((k) => k.title).join('\n         '));
await page.locator('[data-vp-tl] .vp-tl-card').first()
  .screenshot({ path: `${OUT}/key-default-${theme}.png` });

// The lanes, which the filter deliberately leaves alone — and whose
// buttons are gone wherever narrowing to the lane could only empty
// everything.
await page.locator('[data-vp-tl] .vp-tl-card').nth(1)
  .screenshot({ path: `${OUT}/key-lanes-${theme}.png` });

const sources = first.keys.map((k) => k.source);
if (!sources.length) {
  console.log('NO KEYS — this value has no dated entries');
  await browser.close();
  process.exit(0);
}

// 1. Solo the first source, which is the one the stack is tallest in.
await press(sources[0]);
show(`solo ${sources[0]}`, await read());
await page.locator('[data-vp-tl] .vp-tl-card').first()
  .screenshot({ path: `${OUT}/key-solo-${theme}.png` });

// 2. Press it again — the same gesture the lane buttons offer.
await press(sources[0]);
show(`release ${sources[0]}`, await read());

// 3. Solo a single-source lane's source, so the lane button and the
//    note should both name the lane.
const lane = sources.find((s) => ['publication', 'edit', 'seen']
  .includes(s)) || sources[sources.length - 1];
await press(lane);
show(`solo ${lane}`, await read());
await page.locator('[data-vp-tl] .vp-tl-card').first()
  .screenshot({ path: `${OUT}/key-lane-${theme}.png` });

// 4. Clear from the chronology's note.
await page.click('[data-vp-tl-filter-clear]');
await page.waitForTimeout(400);
show('cleared', await read());

// 5. Shift-press drops one source and keeps the rest.
await press(sources[0], ['Shift']);
show(`drop ${sources[0]}`, await read());
await page.locator('[data-vp-tl] .vp-tl-card').first()
  .screenshot({ path: `${OUT}/key-drop-${theme}.png` });

// 6. Shift-press it back: every source selected is no filter at all.
await press(sources[0], ['Shift']);
show('all back', await read());

// 7. A lane button drives the same state, and the key follows.
const laneButton = await page.$('[data-vp-tl-lane]');
if (laneButton) {
  await laneButton.click();
  await page.waitForTimeout(500);
  show('lane pressed', await read());
  await laneButton.click();
  await page.waitForTimeout(500);
  show('lane released', await read());
}

// 8. And a filter survives a brush, which is the other control on the
//    card: the chart keeps its hidden datasets while the window moves.
const brushed = await page.evaluate(() => {
  const el = document.querySelector('[data-vp-brush]');
  const box = el.getBoundingClientRect();
  return { x: box.left, y: box.top + box.height / 2, w: box.width };
});
await press(sources[0]);
await page.mouse.move(brushed.x + brushed.w * 0.55, brushed.y);
await page.mouse.down();
await page.mouse.move(brushed.x + brushed.w * 0.95, brushed.y, { steps: 8 });
await page.mouse.up();
await page.waitForTimeout(2500);
show(`brushed while soloed on ${sources[0]}`, await read());

// 9. And a brush that goes back to the server: the fragment is new, so
//    the filter resets — but the keys must come back live, the way the
//    brush does.
let fetched = false;
page.on('response', (r) => {
  if (/viewTimeline/.test(r.url())) fetched = true;
});
// The first bar that carries entries, which on a capped value is the
// one whose rows the fragment does not have.
const firstActive = await page.evaluate(() => {
  const data = JSON.parse(
    document.querySelector('[data-vp-tl-data]').textContent);
  const days = Object.keys(data.by_day);
  let at = null;
  data.bins.forEach((bin, i) => {
    if (at === null && days.some((d) => d >= bin.from && d <= bin.to)) at = i;
  });
  return { at, count: data.bins.length };
});
console.log(' first active bin', JSON.stringify(firstActive));
const step = brushed.w / firstActive.count;
const mid = brushed.x + step * (firstActive.at + 0.5);
await page.mouse.move(mid - step * 0.3, brushed.y);
await page.mouse.down();
await page.mouse.move(mid + step * 0.3, brushed.y, { steps: 6 });
await page.mouse.up();
await page.waitForTimeout(8000);
console.log('\n fetched a window:', fetched);
show('after the oldest bars were brushed', await read());
await press(sources[0]);
show(`solo ${sources[0]} on the fetched fragment`, await read());

await browser.close();
