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
    caveat: !!Array.from(
        document.querySelectorAll('[data-vp-analyst-standing] .vp-acl-note')
    ).length,
}));

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
            const [r, g, b] = m.slice(0, 3).map((v) => {
                const s = parseFloat(v) / 255;
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
        proposalWhat: '.vpa-proposal-what',
        reportExtract: '.vpa-report-extract',
        reportName: '.vpa-report-name',
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
