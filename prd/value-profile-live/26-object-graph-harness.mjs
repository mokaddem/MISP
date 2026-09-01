// Render one Value Profile panel fragment the way mispOvermind.js does,
// in a real browser, and report what pivotick drew — phase 26's version,
// which reads five edge kinds instead of three and knows the rail is
// handed the rolled-up `peek` feed rather than the overlay's.
//
// Not part of the application. Setup is `24-relationships-browser.md`;
// the only change is the token list and the label assertions below.
//
//   node 26-object-graph-harness.mjs http://127.0.0.1:8917 \
//        /__frag-google-graph.html dark out.png
//
// EXPAND=1 also opens the overlay and reports what it drew.
import { chromium } from '/home/sami/git/pivotick/node_modules/playwright/index.mjs';

const [, , base, fragment, theme, outPng] = process.argv;

const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: 460, height: 1100 } });
const logs = [];
page.on('console', m => logs.push(`${m.type()}: ${m.text()}`));
page.on('pageerror', e => logs.push(`pageerror: ${e.message}`));

await page.goto(`${base}/__harness.html?theme=${theme}&frag=${fragment}`, {
  waitUntil: 'load',
});

let drawn = false;
for (let i = 0; i < 80; i++) {
  drawn = await page.evaluate(() => {
    const stage = document.querySelector('[data-vp-relgraph-stage]');
    if (!stage || stage.classList.contains('d-none')) return false;
    const busy = [...stage.querySelectorAll('*')].some(
      el => el.childElementCount === 0
        && /Optimizing node positions/.test(el.textContent || '')
    );
    if (busy) return false;
    return stage.querySelectorAll('svg path').length > 1;
  });
  if (drawn) break;
  await page.waitForTimeout(250);
}
await page.waitForTimeout(1500);

const report = await page.evaluate(() => {
  const stage = document.querySelector('[data-vp-relgraph-stage]');
  const sketch = document.querySelector('[data-vp-relgraph-sketch]');
  const expand = document.querySelector('[data-vp-relgraph-expand]');
  const ink = k => getComputedStyle(document.documentElement)
    .getPropertyValue(k).trim();
  const all = stage ? [...stage.querySelectorAll('svg')] : [];
  const strokeTally = {};
  const dashTally = {};
  for (const svg of all) {
    for (const el of svg.querySelectorAll('line, path')) {
      const cs = getComputedStyle(el);
      const stroke = cs.stroke;
      if (!stroke || stroke === 'none') continue;
      const box = el.getBBox ? el.getBBox() : { width: 0, height: 0 };
      if (box.width < 4 && box.height < 4) continue;
      strokeTally[stroke] = (strokeTally[stroke] || 0) + 1;
      const dash = el.classList.contains('dashed')
        ? `class:dashed(${cs.strokeDasharray})`
        : cs.strokeDasharray;
      dashTally[dash] = (dashTally[dash] || 0) + 1;
    }
  }
  // The whole point of the rail's roll-up is that its labels fit, so
  // the labels themselves are the assertion — not just their count.
  const labels = all.flatMap(
    s => [...s.querySelectorAll('text')].map(t => t.textContent.trim())
  ).filter(Boolean);
  return {
    stageVisible: stage ? !stage.classList.contains('d-none') : null,
    stripHidden: sketch ? sketch.classList.contains('d-none') : null,
    expandVisible: expand ? !expand.classList.contains('d-none') : null,
    svgCount: all.length,
    svgShapes: all.reduce(
      (n, s) => n + s.querySelectorAll('circle, polygon, rect').length, 0),
    labels,
    truncated: labels.filter(t => /…|\.\.\./.test(t)),
    strokeTally,
    dashTally,
    tokens: {
      object: ink('--vp-rel-object'),
      event: ink('--vp-rel-event'),
      near: ink('--vp-rel-near'),
      reference: ink('--vp-rel-reference'),
      human: ink('--vp-rel-human'),
    },
    pivotick: typeof window.Pivotick,
  };
});

if (outPng) {
  await page.screenshot({ path: outPng, fullPage: true });
}

let overlay = null;
if (process.env.EXPAND) {
  await page.setViewportSize({ width: 1400, height: 900 });
  await page.click('[data-vp-relgraph-expand]');
  await page.waitForTimeout(5000);
  overlay = await page.evaluate(() => {
    const o = document.querySelector('.vp-relgraph-overlay');
    if (!o) return { present: false };
    const svgs = [...o.querySelectorAll('svg')];
    const labels = svgs.flatMap(
      s => [...s.querySelectorAll('text')].map(t => t.textContent.trim())
    ).filter(Boolean);
    return {
      present: true,
      title: o.querySelector('.vp-relgraph-overlay-title').textContent,
      labelCount: labels.length,
      sample: labels.slice(0, 14),
      shapes: svgs.reduce(
        (n, s) => n + s.querySelectorAll('circle, polygon, rect').length, 0),
    };
  });
  if (outPng) {
    await page.screenshot({ path: outPng.replace('.png', '-full.png') });
  }
}
console.log(JSON.stringify({ drawn, report, overlay, logs }, null, 2));
await browser.close();
