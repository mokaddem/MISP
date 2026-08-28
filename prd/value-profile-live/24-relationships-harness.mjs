// Render one Value Profile panel fragment the way mispOvermind.js does,
// in a real browser, and report what pivotick drew.
import { chromium } from '/home/sami/git/pivotick/node_modules/playwright/index.mjs';

const [, , base, fragment, theme, outPng] = process.argv;

const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: 460, height: 900 } });
const logs = [];
page.on('console', m => logs.push(`${m.type()}: ${m.text()}`));
page.on('pageerror', e => logs.push(`pageerror: ${e.message}`));

await page.goto(`${base}/__harness.html?theme=${theme}&frag=${fragment}`, {
  waitUntil: 'load',
});

// Wait for pivotick to have drawn, or give up.
let drawn = false;
for (let i = 0; i < 80; i++) {
  drawn = await page.evaluate(() => {
    const stage = document.querySelector('[data-vp-relgraph-stage]');
    if (!stage || stage.classList.contains('d-none')) return false;
    // Wait past the "Optimizing node positions" overlay: the layout
    // is settled when the progress panel is gone and nodes are placed.
    const busy = [...stage.querySelectorAll('*')].some(
      el => el.childElementCount === 0
        && /Optimizing node positions/.test(el.textContent || '')
    );
    if (busy) return false;
    return stage.querySelectorAll('svg path').length > 3;
  });
  if (drawn) break;
  await page.waitForTimeout(250);
}
await page.waitForTimeout(1200);

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
      if (box.width < 4 && box.height < 4) continue;  // toolbar glyphs
      strokeTally[stroke] = (strokeTally[stroke] || 0) + 1;
      // Pivotick dashes via a `.dashed` class, not the attribute.
      const dash = el.classList.contains('dashed')
        ? `class:dashed(${cs.strokeDasharray})`
        : cs.strokeDasharray;
      dashTally[dash] = (dashTally[dash] || 0) + 1;
    }
  }
  const shapes = all.reduce(
    (n, s) => n + s.querySelectorAll('circle, polygon, rect').length, 0);
  return {
    stageVisible: stage ? !stage.classList.contains('d-none') : null,
    sketchHidden: sketch ? sketch.classList.contains('d-none') : null,
    expandVisible: expand ? !expand.classList.contains('d-none') : null,
    svgCount: all.length,
    svgShapes: shapes,
    svgTexts: all.reduce(
      (n, s) => n + s.querySelectorAll('text').length, 0),
    strokeTally,
    dashTally,
    tokens: {
      co: ink('--vp-rel-co'),
      near: ink('--vp-rel-near'),
      human: ink('--vp-rel-human'),
    },
    pivotick: typeof window.Pivotick,
  };
});

if (outPng) {
  await page.screenshot({ path: outPng, fullPage: true });
}

// Hover a neighbour node: the tooltip is where the value is legible.
let tooltip = null;
if (process.env.HOVER) {
  const node = await page.$('[data-vp-relgraph-stage] svg g.node');
  if (node) {
    await node.hover();
    await page.waitForTimeout(900);
    tooltip = await page.evaluate(() => {
      const t = document.querySelector('.pvt-tooltip, [class*="tooltip"]');
      return t ? t.textContent.replace(/\s+/g, ' ').trim().slice(0, 160)
        : null;
    });
  }
}

// The overlay: same data, full UI, labels on.
let overlay = null;
if (process.env.EXPAND) {
  await page.setViewportSize({ width: 1400, height: 900 });
  await page.click('[data-vp-relgraph-expand]');
  await page.waitForTimeout(4000);
  overlay = await page.evaluate(() => {
    const o = document.querySelector('.vp-relgraph-overlay');
    if (!o) return { present: false };
    const svgs = [...o.querySelectorAll('svg')];
    return {
      present: true,
      title: o.querySelector('.vp-relgraph-overlay-title').textContent,
      texts: svgs.reduce(
        (n, s) => n + s.querySelectorAll('text').length, 0),
      shapes: svgs.reduce(
        (n, s) => n + s.querySelectorAll('circle, polygon, rect').length,
        0),
    };
  });
  await page.screenshot({ path: outPng.replace('.png', '-full.png') });
}
console.log(JSON.stringify({ drawn, report, tooltip, overlay, logs }, null, 2));
await browser.close();
