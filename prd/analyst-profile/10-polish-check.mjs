// The polish pass (§20), measured rather than looked at.
//
// `10-band-geometry.mjs` measures the two axis bands. This measures
// what the polish pass changed around them, and it measures it at two
// widths in both themes, because the band split is the first thing on
// this tab with a breakpoint in it — and the defect it caught was the
// one only the narrow case has. A check that runs at one width would
// have shipped a list running the full width of the card, stacked,
// with `independent sighting` 900px from the organisation it belongs
// to. The split worked; the case underneath it did not.
//
// What it asserts:
//
//   1. Nothing overflows the pane. Bootstrap's own negative gutter on
//      `.row` / `.col-*` is not spill — every page in MISP has it —
//      so those are skipped rather than the tolerance being widened
//      until they pass.
//   2. The bar column is capped in *both* layouts, and the band
//      splits only above the breakpoint. Split-when-too-narrow is a
//      620px bar and a starved list, which is worse than stacking.
//   3. The hero carries no action block and the provenance strip
//      does. Two assertions rather than one: `moved` is a claim about
//      both ends, and a block deleted from the hero and rendered
//      nowhere passes the first alone.
//   4. The gauge carries both band floors, wherever there is a gauge.
//      A value with nothing to assess has no gauge and no floors to
//      mark, which is §C3 and is not a failure.
//
//   node prd/analyst-profile/10-polish-check.mjs \
//        https://localhost 8.8.8.8 127.0.0.1 awake-weaves.cyou
import { chromium } from
    '/home/sami/git/pivotick/node_modules/playwright/index.mjs';

const BASE = process.argv[2] || 'https://localhost';
const VALUES = process.argv.length > 3
    ? process.argv.slice(3)
    : ['8.8.8.8', '127.0.0.1', 'awake-weaves.cyou'];
const EMAIL = 'admin@admin.test';
const PASS = 'admin';

// Above `SPLIT_AT` the card is wide enough to seat a 620px bar and a
// legible list beside it. The stylesheet owns the number; this is the
// same number, and the two moving apart is itself a failure the
// assertions below would report.
const SPLIT_AT = 1400;
const WIDTHS = [1600, 1280];

let passed = 0;
let failed = 0;
const ok = (m) => { passed++; console.log('ok   ' + m); };
const no = (m) => { failed++; console.log('FAIL ' + m); };

const browser = await chromium.launch();
const ctx = await browser.newContext({
    ignoreHTTPSErrors: true,
    viewport: { width: 1600, height: 1100 },
});
const page = await ctx.newPage();
await page.goto(`${BASE}/users/login`, { waitUntil: 'domcontentloaded' });
await page.fill('#UserEmail', EMAIL);
await page.fill('#UserPassword', PASS);
await Promise.all([
    page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
    page.click('input[type="submit"], button[type="submit"]'),
]);

for (const value of VALUES) {
    const b64 = Buffer.from(value).toString('base64')
        .replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
    for (const w of WIDTHS) {
        await page.setViewportSize({ width: w, height: 1100 });
        await page.goto(`${BASE}/values/view/${b64}`,
            { waitUntil: 'domcontentloaded' });
        await page.click('a[href="#tab-assessment"]').catch(() => {});
        await page.waitForSelector('.vp-vc', { timeout: 20000 })
            .catch(() => {});
        await page.waitForTimeout(1200);

        const m = await page.evaluate(() => {
            const box = (s) => {
                const e = document.querySelector(s);
                if (!e) { return null; }
                const b = e.getBoundingClientRect();
                return {
                    w: Math.round(b.width), h: Math.round(b.height),
                    display: getComputedStyle(e).display,
                };
            };
            const pane = document.querySelector('#tab-assessment');
            let spill = 0;
            if (pane) {
                const pr = pane.getBoundingClientRect().right;
                pane.querySelectorAll('*').forEach((e) => {
                    const cls = (e.className || '').toString();
                    if (/(^|\s)(row|col(-[a-z0-9]+)*)(\s|$)/.test(cls)) {
                        return;
                    }
                    const b = e.getBoundingClientRect();
                    if (b.width > 0 && b.right > pr + 2
                        && getComputedStyle(e).position !== 'fixed') {
                        spill = Math.max(spill, Math.round(b.right - pr));
                    }
                });
            }
            return {
                card: box('.vp-vc'),
                score: box('.vp-vc-score'),
                marks: document.querySelectorAll('.vp-vc-score-mark').length,
                leanBody: box('.vp-vc-lean-body'),
                leanBar: box('.vp-vc-lean-body > .vp-vc-band-bar'),
                clockBody: box('.vp-vc-clock-body'),
                clockBar: box('.vp-vc-clock-body > .vp-vc-band-bar'),
                metaActions: box('.vp-verdict-meta-actions'),
                heroActions: !!document.querySelector('.vp-vc-hero-actions'),
                spill,
            };
        });

        const tag = `${value}@${w}`;
        if (m.card === null) { no(`${tag}: no assessment card`); continue; }

        if (m.spill === 0) {
            ok(`${tag}: nothing overflows the pane`);
        } else {
            no(`${tag}: ${m.spill}px of horizontal spill`);
        }

        if (!m.heroActions) {
            ok(`${tag}: the hero carries no action block`);
        } else {
            no(`${tag}: the actions are still in the hero`);
        }
        if (m.metaActions && m.metaActions.w > 0) {
            ok(`${tag}: the actions are drawn in the provenance strip`);
        } else {
            no(`${tag}: the actions are drawn nowhere`);
        }

        for (const band of ['lean', 'clock']) {
            const body = m[`${band}Body`];
            const bar = m[`${band}Bar`];
            if (!body) {
                console.log(`     ${tag}: no ${band} band on this value`);
                continue;
            }
            if (!bar) { no(`${tag}/${band}: no bar column`); continue; }

            if (bar.w <= 620 + 1) {
                ok(`${tag}/${band}: bar column capped (${bar.w}px)`);
            } else {
                no(`${tag}/${band}: bar column ran to ${bar.w}px`);
            }

            // Stacking below the breakpoint is a reading, not a
            // failure — a value with no list stacks at every width,
            // by design. Splitting below it is the failure.
            const split = body.display === 'grid';
            if (!split || w >= SPLIT_AT) {
                ok(`${tag}/${band}: ${split ? 'split' : 'stacked'}`
                    + ` at ${w}px`);
            } else {
                no(`${tag}/${band}: split at ${w}px, under the`
                    + ` ${SPLIT_AT}px the stylesheet sets`);
            }
        }

        if (m.score === null) {
            ok(`${tag}: no gauge on this value, so no floors to mark`);
        } else if (m.marks === 2) {
            ok(`${tag}: the gauge carries both band floors`);
        } else {
            no(`${tag}: the gauge carries ${m.marks} floor mark(s)`);
        }
        console.log(`     ${tag}: card ${m.card.w}x${m.card.h}`);
    }
}

await browser.close();
console.log(`\npassed: ${passed}   failed: ${failed}`);
process.exit(failed ? 1 : 0);
