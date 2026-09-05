/*
 * Phase 26: the two checks the HTTP-fragment pass could not make —
 * both themes, and the thread's filter pills driven by real clicks.
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
const errors = [];
page.on('pageerror', (e) => errors.push('pageerror: ' + e.message));
page.on('console', (m) => {
    if (m.type() === 'error') errors.push('console: ' + m.text());
});

await page.goto(`${base}/users/login`);
await page.fill('#UserEmail', 'admin@admin.test');
await page.fill('#UserPassword', 'admin');
await Promise.all([
    page.waitForNavigation(),
    page.click('input[type="submit"], button[type="submit"]'),
]);

await page.goto(`${base}/values/view/${b64}`);
await page.click('.nav-link[href="#tab-analyst"]');

// Lazy panels: wait for all three fragments.
const wanted = ['viewAnalystStanding', 'viewAnalystThread',
    'viewAnalystReports'];
const seen = new Set();
page.on('response', (r) => {
    for (const w of wanted) if (r.url().includes(w)) seen.add(w);
});
for (let i = 0; i < 30 && seen.size < wanted.length; i++) {
    await page.mouse.wheel(0, 900);
    await page.waitForTimeout(400);
}
await page.waitForTimeout(1200);

const report = { value, panels: {}, themes: {}, filter: {}, errors: [] };

report.panels = await page.evaluate(() => ({
    standing: !!document.querySelector('[data-vp-analyst-standing]'),
    thread: !!document.querySelector('[data-vp-analyst-thread]'),
    reports: !!document.querySelector('[data-vp-analyst-reports]'),
    lanes: document.querySelectorAll('.vpa-row').length,
    items: document.querySelectorAll('[data-vp-a-item]').length,
    proposals: document.querySelectorAll(
        '[data-vp-a-item][data-vp-a-kind="proposal"]').length,
    reportRows: document.querySelectorAll('.vpa-report').length,
    /*
     * Every chip that names a record should open it. A chip with no
     * href is either a reply or an unresolved target; anything else is
     * an address the page is holding rather than offering.
     */
    inertChips: Array.from(document.querySelectorAll('.vpa-chip'))
        .filter((c) => c.tagName !== 'A')
        .map((c) => c.textContent.trim().slice(0, 40)),
    links: Array.from(document.querySelectorAll(
        '.vpa-chip-link, .vpa-orglink, .vpa-change-on, .vpa-report-name'
    )).map((a) => a.getAttribute('href')),
    changeStrips: document.querySelectorAll('.vpa-change').length,
    caveat: !!Array.from(
        document.querySelectorAll('[data-vp-analyst-standing] .vp-acl-note')
    ).length,
    /*
     * §19: every report row names the audience it inherits. A badge
     * still reading `Inherit event` is the defect that section closed —
     * the report's own column, printed instead of resolved — so the
     * check is that none of them says it, not that some of them do not.
     */
    reportAudiences: Array.from(
        document.querySelectorAll('.vpa-report .badge')
    ).map((b) => b.textContent.replace(/\s+/g, ' ').trim()),
}));

report.panels.audienceUnresolved = report.panels.reportAudiences
    .filter((a) => /Inherit event/i.test(a));

/* --- the filter pills, clicked --- */
const visible = () => page.evaluate(() =>
    Array.from(document.querySelectorAll('[data-vp-a-item]'))
        .filter((n) => !n.classList.contains('d-none'))
        .map((n) => n.dataset.vpAKind));

report.filter.all = await visible();
for (const kind of ['note', 'opinion', 'proposal']) {
    const pill = await page.$(`[data-vp-a-kind-filter="${kind}"]`);
    if (!pill) { report.filter[kind] = 'no pill'; continue; }
    await pill.click();
    await page.waitForTimeout(250);
    report.filter[kind] = await visible();
}
const allPill = await page.$('[data-vp-a-kind-filter="all"]');
if (allPill) { await allPill.click(); await page.waitForTimeout(250); }
report.filter.backToAll = (await visible()).length;

/* --- sort, clicked --- */
const dates = () => page.evaluate(() =>
    Array.from(document.querySelectorAll('[data-vp-a-item]'))
        .filter((n) => !n.classList.contains('d-none'))
        .map((n) => n.dataset.vpADate));
report.filter.newest = await dates();
await page.click('[data-vp-a-sort="oldest"]');
await page.waitForTimeout(250);
report.filter.oldest = await dates();
await page.click('[data-vp-a-sort="newest"]');
await page.waitForTimeout(250);

/* --- §20: the Overview's preview card, and whether it agrees ---
 *
 * The card and the tab read one union, so their counts cannot drift —
 * that is the claim the conversion rests on, and the only way to check
 * it is to read both off one page. The tab's counts come from its own
 * items rather than from its subtitle, so this compares the card's
 * printed number against the DOM the tab actually rendered.
 */
await page.click('.nav-link[href="#tab-general"]');
for (let i = 0; i < 20; i++) {
    const there = await page.$('[data-vp-analyst-preview]');
    if (there) break;
    await page.waitForTimeout(300);
}
await page.waitForTimeout(800);

report.preview = await page.evaluate(() => {
    const card = document.querySelector('[data-vp-analyst-preview]');
    if (!card) return 'absent';
    const sub = card.querySelector('.small.text-muted');
    const num = (word) => {
        const m = (sub ? sub.textContent : '')
            .match(new RegExp('(\\d+)\\s+' + word));
        return m ? +m[1] : null;
    };
    return {
        subtitle: sub ? sub.textContent.replace(/\s+/g, ' ').trim() : null,
        notes: num('notes?'),
        opinions: num('opinions?'),
        proposals: num('proposals?'),
        shown: card.querySelectorAll('.vp-analyst').length,
        openThread: !!card.querySelector('a[href="#tab-analyst"]'),
        empty: !!card.querySelector('.vp-empty'),
        /*
         * §20.3: coloured by the score against 50, never by the band
         * word. `agree` above it, `dispute` below it, `neither` at
         * exactly 50 — MISP's own `opinion_scale.ctp` rule.
         */
        readings: Array.from(card.querySelectorAll('.vpa-reading'))
            .map((r) => {
                const score = (r.textContent.match(/(\d+)\/100/) || [])[1];
                const side = /vpa-s-(\w+)/.exec(r.className);
                return { score: score ? +score : null,
                    side: side ? side[1] : null };
            }),
        // Every organisation named is an organisation that opens.
        inertOrgs: Array.from(card.querySelectorAll('.vp-analyst-meta'))
            .filter((m) => !m.querySelector('.vpa-orglink')).length,
    };
});

if (report.preview !== 'absent') {
    const tally = { note: 0, opinion: 0, proposal: 0 };
    for (const k of report.filter.all) {
        tally[k] = (tally[k] || 0) + 1;
    }
    report.preview.threadTally = tally;
    report.preview.agrees =
        report.preview.notes === tally.note
        && report.preview.opinions === tally.opinion
        && (report.preview.proposals || 0) === tally.proposal;
    report.preview.pivotHolds = report.preview.readings.every((r) =>
        r.score === null
        || (r.score > 50 && r.side === 'agree')
        || (r.score < 50 && r.side === 'dispute')
        || (r.score === 50 && r.side === 'neither')
        // An opinion rating another item takes no side on the value.
        || r.side === 'neither');
}

/* --- both themes --- */
const probe = async () => page.evaluate(() => {
    const read = (sel, props) => {
        const el = document.querySelector(sel);
        if (!el) return null;
        const cs = getComputedStyle(el);
        const out = {};
        for (const p of props) out[p] = cs.getPropertyValue(p);
        return out;
    };
    const contrast = (fg, bg) => {
        const lum = (c) => {
            const m = c.match(/[\d.]+/g);
            if (!m) return null;
            /*
             * `color-mix()` computes to `color(srgb r g b)` with
             * channels in 0-1, not to `rgb()` with channels in 0-255.
             * Dividing those by 255 reports a near-white background as
             * near-black and turns every mixed surface into a contrast
             * failure that is not there.
             */
            const unit = c.startsWith('color(');
            const [r, g, b] = m.slice(unit ? 0 : 0, 3).map((v) => {
                const s = unit ? parseFloat(v) : parseFloat(v) / 255;
                return s <= 0.03928
                    ? s / 12.92
                    : Math.pow((s + 0.055) / 1.055, 2.4);
            });
            return 0.2126 * r + 0.7152 * g + 0.0722 * b;
        };
        const a = lum(fg); const b = lum(bg);
        if (a === null || b === null) return null;
        const hi = Math.max(a, b); const lo = Math.min(a, b);
        return +((hi + 0.05) / (lo + 0.05)).toFixed(2);
    };
    const bodyBg = getComputedStyle(document.body).backgroundColor;
    const nodes = {
        changeStrip: '.vpa-change',
        changeOp: '.vpa-change-op',
        changeFrom: '.vpa-change-from',
        changeTo: '.vpa-change-to',
        changeOn: '.vpa-change-on',
        chipLink: '.vpa-chip-link',
        orgLink: '.vpa-orglink',
        reportExtract: '.vpa-report-extract',
        reportName: '.vpa-report-name',
        // §19's audience badge, and §20.3's coloured opinion reading.
        reportAudience: '.vpa-report .badge',
        previewReading: '[data-vp-analyst-preview] .vpa-reading',
        caveat: '[data-vp-analyst-standing] .vp-acl-note',
        chip: '.vpa-chip',
    };
    const out = { bodyBg, nodes: {} };
    for (const [k, sel] of Object.entries(nodes)) {
        const s = read(sel, ['color', 'background-color',
            'border-left-color', 'border-color']);
        if (!s) { out.nodes[k] = 'absent'; continue; }
        const bg = s['background-color'] === 'rgba(0, 0, 0, 0)'
            ? bodyBg
            : s['background-color'];
        out.nodes[k] = {
            color: s.color,
            bg,
            contrast: contrast(s.color, bg),
        };
    }
    return out;
});

const setTheme = async (mode) => page.evaluate((m) => {
    document.documentElement.setAttribute('data-bs-theme', m);
}, mode);

report.themes.light = await setTheme('light').then(async () => {
    await page.waitForTimeout(300);
    return probe();
});
report.themes.dark = await setTheme('dark').then(async () => {
    await page.waitForTimeout(300);
    return probe();
});

report.errors = errors;
console.log(JSON.stringify(report, null, 2));
await browser.close();
