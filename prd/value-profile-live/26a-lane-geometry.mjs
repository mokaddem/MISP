/*
 * Phase 26: where the ledger's score numeral lands, and whether it
 * clears both the lane's edges and its own dot.
 *
 * Run at two viewports: wide, and narrow enough to drive the lane
 * column to the 320px floor `.vpa-ledger`'s grid guarantees — which is
 * the width the flip thresholds were derived from.
 */
import { chromium } from '/home/sami/git/pivotick/node_modules/playwright/index.mjs';

const base = 'https://localhost';
const value = process.argv[2] || '8.8.8.8';
const b64 = Buffer.from(value).toString('base64')
    .replace(/\+/g, '-').replace(/\//g, '_');

const browser = await chromium.launch();
const ctx = await browser.newContext({
    ignoreHTTPSErrors: true,
    viewport: { width: 1500, height: 1100 },
});
const page = await ctx.newPage();
await page.goto(`${base}/users/login`);
await page.fill('#UserEmail', 'admin@admin.test');
await page.fill('#UserPassword', 'admin');
await Promise.all([
    page.waitForNavigation(),
    page.click('input[type="submit"], button[type="submit"]'),
]);
await page.goto(`${base}/values/view/${b64}`);
await page.click('.nav-link[href="#tab-analyst"]');
for (let i = 0; i < 25; i++) {
    await page.mouse.wheel(0, 900);
    await page.waitForTimeout(250);
    if (await page.$('[data-vp-analyst-standing] .vpa-row')) break;
}
await page.waitForTimeout(1000);

const probe = () => page.evaluate(() => {
    const r = (e) => {
        if (!e) return null;
        const b = e.getBoundingClientRect();
        return { l: +b.left.toFixed(1), r: +b.right.toFixed(1),
            w: +b.width.toFixed(1) };
    };
    return Array.from(document.querySelectorAll('.vpa-row')).map((row) => {
        const lane = r(row.querySelector('.vpa-lane'));
        const val = r(row.querySelector('.vpa-lane-val'));
        const dot = r(row.querySelector('.vpa-lane-dot'));
        const reading = r(row.querySelector('.vpa-reading'));
        const el = row.querySelector('.vpa-lane-val');
        return {
            score: el ? el.textContent.trim() : null,
            flipped: el ? el.classList.contains('vpa-lane-val-in') : null,
            laneW: lane && lane.w,
            insideLane: lane && val
                ? +Math.min(val.l - lane.l, lane.r - val.r).toFixed(1)
                : null,
            dotGap: dot && val
                ? +(val.l > dot.l
                    ? val.l - dot.r
                    : dot.l - val.r).toFixed(1)
                : null,
            hitsReading: reading && val
                ? val.r > reading.l && val.l < reading.r
                : null,
        };
    });
});

const out = { wide: null, narrow: null, scaleDots: null };
out.wide = await probe();

// The mini scale in the thread, whose dot also sits at `left: score%`.
out.scaleDots = await page.evaluate(() => {
    const rows = Array.from(document.querySelectorAll('.vpa-scale'));
    return rows.map((s) => {
        const sb = s.getBoundingClientRect();
        const d = s.querySelector('.vpa-scale-dot');
        const db = d ? d.getBoundingClientRect() : null;
        const next = s.nextElementSibling;
        const nb = next ? next.getBoundingClientRect() : null;
        return {
            overflowRight: db ? +(db.right - sb.right).toFixed(1) : null,
            overflowLeft: db ? +(sb.left - db.left).toFixed(1) : null,
            hitsNext: db && nb ? db.right > nb.left : null,
        };
    });
});

await page.setViewportSize({ width: 700, height: 1100 });
await page.waitForTimeout(600);
out.narrow = await probe();

console.log(JSON.stringify(out, null, 2));
await browser.close();
