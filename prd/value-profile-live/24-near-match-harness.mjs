// Drive the near-match panel's `Similarity ≥` control in a real
// browser, against ssdeep rows.
//
// The control filters on `data-vp-num="closeness:NN"`, which for a CIDR
// row is a prefix over the address width and for an ssdeep row is the
// score itself — one input, two scales, and the bar beside it has to
// agree with whichever wrote the row. That agreement is the thing this
// checks, because the two engines reach it by different arithmetic.
//
// `24-relationships-browser.md` explains the two silent gates
// (`data-controller="values"` and `misp:container-loaded`) and carries
// the `__harness.html` page this drives. The witness comes first: the
// row count has to *move* when the input changes, or a filtered count
// read back from an inert page is just the model's own count.
//
// Not part of the application.
//
//   node 24-near-match-harness.mjs http://127.0.0.1:8917 \
//        /__frag-ssdeep.html light out.png
import { chromium } from '/home/sami/git/pivotick/node_modules/playwright/index.mjs';

const [, , base, fragment, theme, outPng] = process.argv;

const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: 1180, height: 1200 } });
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
  const rows = [...document.querySelectorAll('tbody tr')];
  const visible = rows.filter(r => r.offsetParent !== null);
  return {
    rows: rows.length,
    visible: visible.length,
    scores: visible.map(r => r.dataset.vpNum),
    // The cell truncates at 18rem; a hash is always wider than that, so
    // whether the full value is recoverable at all is a `title` away.
    firstCell: (() => {
      const cell = visible[0] && visible[0].querySelector('.vp-rel-cell');
      if (!cell) return null;
      return {
        clipped: cell.scrollWidth > cell.clientWidth + 1,
        title: cell.getAttribute('title'),
        text: cell.textContent.trim().slice(0, 24),
      };
    })(),
  };
});

const before = await read();

// Between the fifth and sixth rung: 60 keeps four of the six.
await page.fill('#vp-rel-similarity', '60');
await page.waitForTimeout(400);
const filtered = await read();

await page.fill('#vp-rel-similarity', '0');
await page.waitForTimeout(400);
const restored = await read();

if (outPng) {
  await page.screenshot({ path: outPng, fullPage: true });
}
console.log(JSON.stringify({ before, filtered, restored, logs }, null, 2));
await browser.close();
