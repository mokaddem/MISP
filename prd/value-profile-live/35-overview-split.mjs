/*
 * Phase 35: what the borrowed tug-bar costs the Overview, measured.
 *
 * Phase 31's whole complaint was that this tab is too tall, and its D5
 * says anything added to it has to be weighed against that rather than
 * against its own merit. So this harness does not check that the bar is
 * present — it measures the pane, both columns, every card, and the
 * block the bar sits in, so the cost is a number rather than a claim.
 *
 * It also asserts the new CSS actually arrived: `.vp-analyst-split`'s
 * padding is the one rule this phase added, and a stale stylesheet
 * would leave the bar drawn and flush against the card's edge, which
 * looks like a layout opinion rather than like a missing file.
 *
 * Two themes, because the segments resolve `--vp-ben` / `--vp-mal` and
 * the caption `--bs-secondary-color`.
 *
 * Not part of the application.
 *
 *   node prd/value-profile-live/35-overview-split.mjs [value...]
 */
import { chromium } from '/home/sami/git/pivotick/node_modules/playwright/index.mjs';

const base = 'https://localhost';
const values = process.argv.slice(2).length
    ? process.argv.slice(2)
    : ['8.8.8.8', '127.0.0.1', '443', 'sage.png'];

const b64 = (v) => Buffer.from(v).toString('base64')
    .replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');

let checks = 0;
let failures = 0;
const check = (ok, label) => {
    checks++;
    if (!ok) failures++;
    console.log(`  ${ok ? 'ok   ' : 'FAIL '} ${label}`);
};

const browser = await chromium.launch();

for (const theme of ['light', 'dark']) {
    const ctx = await browser.newContext({
        ignoreHTTPSErrors: true,
        viewport: { width: 1600, height: 1000 },
    });
    const page = await ctx.newPage();
    const errors = [];
    page.on('pageerror', (e) => errors.push(String(e.message)));

    await page.goto(`${base}/users/login`);
    await page.fill('#UserEmail', 'admin@admin.test');
    await page.fill('#UserPassword', 'admin');
    await Promise.all([
        page.waitForNavigation(),
        page.click('input[type="submit"], button[type="submit"]'),
    ]);
    if (theme === 'dark') {
        await page.evaluate(() => localStorage.setItem('darkMode', 'true'));
    } else {
        await page.evaluate(() => localStorage.removeItem('darkMode'));
    }

    for (const value of values) {
        console.log(`== ${theme} · ${value} ==`);
        await page.goto(`${base}/values/view/${b64(value)}`);
        // The Overview's panels are lazy. Scroll until the analyst
        // card has resolved, then let the rail settle.
        for (let i = 0; i < 25; i++) {
            await page.mouse.wheel(0, 900);
            await page.waitForTimeout(200);
            if (await page.$('[data-vp-analyst-preview]')) break;
        }
        await page.waitForTimeout(1200);
        await page.evaluate(() => window.scrollTo(0, 0));
        await page.waitForTimeout(300);

        const m = await page.evaluate(() => {
            const h = (el) => (el ? +el.getBoundingClientRect()
                .height.toFixed(1) : null);
            const pane = document.querySelector('#tab-general')
                || document.querySelector('.ajax-tab-content');
            // Every card is wrapped: `.ajax-tab-content` in the left
            // column, `.ajax-card` in the rail. The wrapper is what
            // occupies the column, so it is what gets measured.
            const cols = pane
                ? [...pane.querySelectorAll(':scope > .row > [class*="col-"]')]
                : [];
            const colCards = cols.map((c) => [...c
                .querySelectorAll(':scope > .ajax-tab-content,'
                    + ' :scope > .ajax-card,'
                    + ' :scope > .row > [class*="col-"] > .ajax-tab-content,'
                    + ' :scope > .row > [class*="col-"] > .ajax-card')]
                .map((p) => {
                    const panel = p.querySelector('.vp-panel');
                    return {
                        id: panel && panel.dataset
                            ? (Object.keys(panel.dataset)[0] || '?') : '?',
                        h: h(p),
                    };
                }));
            const split = document.querySelector('.vp-analyst-split');
            const card = document.querySelector('[data-vp-analyst-preview]');
            const seg = [...document.querySelectorAll(
                '.vp-analyst-split .vpa-tug-seg')].map((s) => ({
                side: [...s.classList].find((c) => c.startsWith('vpa-s-')),
                w: +s.getBoundingClientRect().width.toFixed(1),
                bg: getComputedStyle(s).backgroundColor,
            }));
            const lead = document.querySelector(
                '.vp-analyst-split .vp-subhead');
            const verdict = document.querySelector(
                '.vp-analyst-split .vpa-verdict');
            return {
                theme: document.documentElement
                    .getAttribute('data-bs-theme') || 'light',
                pane: h(pane),
                columns: colCards.map((cards) => ({
                    cards,
                    sum: +cards.reduce((a, c) => a + (c.h || 0), 0)
                        .toFixed(1),
                })),
                analystCard: h(card),
                splitBlock: h(split),
                splitPadTop: split
                    ? getComputedStyle(split).paddingTop : null,
                tugHeight: h(document.querySelector(
                    '.vp-analyst-split .vpa-tug')),
                segments: seg,
                lead: lead ? lead.textContent.trim() : null,
                verdict: verdict ? verdict.textContent.trim() : null,
                overflowX: document.documentElement.scrollWidth
                    - document.documentElement.clientWidth,
                // Whether the card's own rows and the bar disagree on
                // how many opinions there are, which is the one thing
                // a reader can catch this pairing out on.
                subtitle: (document.querySelector(
                    '[data-vp-analyst-preview] .vp-panel-sub')
                    || {}).textContent,
            };
        });

        /*
         * What the block costs *the pane*, which is not what it costs
         * the card. Phase 31 D5's whole argument is that a tab is as
         * tall as its taller column, so 101px added to the shorter one
         * is free and 101px added to the taller one is 101px. Measured
         * by hiding the block and reading the page again rather than
         * by subtracting, because which column is taller can change
         * when it shrinks.
         */
        const without = await page.evaluate(() => {
            const el = document.querySelector('.vp-analyst-split');
            if (!el) return null;
            el.style.display = 'none';
            const pane = document.querySelector('#tab-general');
            const cols = [...pane.querySelectorAll(
                ':scope > .row > [class*="col-"]')];
            const out = {
                pane: +pane.getBoundingClientRect().height.toFixed(1),
                columns: cols.map((c) => +[...c.querySelectorAll(
                    ':scope > .ajax-tab-content, :scope > .ajax-card,'
                    + ' :scope > .row > [class*="col-"] > .ajax-tab-content,'
                    + ' :scope > .row > [class*="col-"] > .ajax-card')]
                    .reduce((a, p) => a
                        + p.getBoundingClientRect().height, 0).toFixed(1)),
            };
            el.style.display = '';
            return out;
        });
        m.paneWithout = without && without.pane;
        m.paneCost = without && +(m.pane - without.pane).toFixed(1);
        m.columnsWithout = without && without.columns;

        console.log(JSON.stringify(m, null, 1));
        check(errors.length === 0, `no page error (${errors.join('; ')})`);
        check(m.theme === theme, `the ${theme} theme applied (${m.theme})`);
        check(m.overflowX <= 0, `no horizontal overflow (${m.overflowX})`);
        if (m.splitBlock === null) {
            check(m.analystCard !== null,
                'no opinion on the value, and no bar drawn');
        } else {
            check(m.splitPadTop === '16px',
                `phase 35's CSS arrived (padding-top ${m.splitPadTop})`);
            check(m.tugHeight === 30, `bar is 30px (${m.tugHeight})`);
            /*
             * 101px, measured. Not a budget picked in advance — the
             * block is the tab's own, unchanged, and this asserts it
             * has not quietly grown past what §4 recorded as the
             * price. The bar is 30 of it, the lead 24, the caption 18
             * and the padding 29.
             */
            check(m.splitBlock < 110,
                `the block is still ~101px (${m.splitBlock})`);
            check(/^The split — \d+ opinions? on the value$/
                .test(m.lead), `lead states its denominator (${m.lead})`);
            check(m.segments.every((s) => s.w > 0),
                'every drawn segment has width');
            check(m.segments.every((s) => s.bg !== 'rgba(0, 0, 0, 0)'),
                'every segment resolved a colour in this theme');
        }
        errors.length = 0;
    }
    await ctx.close();
}

await browser.close();
console.log(`\n${checks} checks, ${failures} failed`);
process.exit(failures ? 1 : 0);
