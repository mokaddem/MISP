/*
 * Phase 28: does the Enrichment tab render, and does a run land in the
 * pane it was pressed from?
 *
 * The HTTP fetch proves the endpoints; this proves the page. Four
 * things it checks that a fragment cannot:
 *
 *   1. The tab renders in **both themes**, with the panel's accent
 *      token resolved — §6.1's trap, where an unstyled page passes a
 *      colour check for the wrong reason.
 *   2. Picking a module is a **class change and not a request**. The
 *      whole tab's promise is that reading the rail cannot query
 *      anything, so this counts network calls across a walk of every
 *      row and expects zero.
 *   3. A press runs one module, fills its pane, and paints its row.
 *   4. **The rail does not overflow.** Phase 12's split gives the rail
 *      ~40%; a module name is arbitrary text from a third party and
 *      `circl_passivedns` is not the longest one that exists.
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

let requests = 0;
page.on('request', (r) => {
    if (r.url().includes('/values/')) requests++;
});
/* Status only, never the body: reading a response body here changes
 * the timing enough to hide the race this harness first caught. */
page.on('response', (r) => {
    if (r.url().includes('viewEnrichmentRun')) {
        console.log('  POST ->', r.status());
    }
});
if (process.env.VP_DEBUG) {
    page.on('console', (m) => console.log('  console:', m.text()));
    page.on('pageerror', (e) => console.log('  pageerror:', e.message));
}

await page.goto(`${base}/users/login`);
await page.fill('#UserEmail', 'admin@admin.test');
await page.fill('#UserPassword', 'admin');
await Promise.all([
    page.waitForNavigation(),
    page.click('input[type="submit"], button[type="submit"]'),
]);

for (const theme of ['light', 'dark']) {
    await page.goto(`${base}/values/view/${b64}`);
    await page.evaluate((t) => {
        document.documentElement.setAttribute('data-bs-theme', t);
    }, theme);
    await page.click('.nav-link[href="#tab-enrichment"]');
    await page.waitForSelector('[data-vp-enrich] .vp-e-railrow',
        { timeout: 20000 });

    const shape = await page.evaluate(() => {
        const panel = document.querySelector('[data-vp-enrich]');
        const rail = panel.querySelector('.vp-e-rail');
        const pane = panel.querySelector('.vp-e-pane');
        const accent = getComputedStyle(panel)
            .getPropertyValue('--vp-panel-color').trim();
        const rows = [...panel.querySelectorAll('.vp-e-railrow')];
        return {
            accent,
            rows: rows.length,
            panes: panel.querySelectorAll('[data-vp-e-pane]').length,
            railW: Math.round(rail.getBoundingClientRect().width),
            paneW: Math.round(pane.getBoundingClientRect().width),
            // A row wider than the rail it sits in is the overflow.
            overflow: rows.filter((r) =>
                r.scrollWidth > rail.clientWidth + 1).length,
            visiblePanes: [...panel
                .querySelectorAll('[data-vp-e-pane]')]
                .filter((p) => !p.classList.contains('d-none')).length,
        };
    });
    console.log(`${theme.padEnd(6)} accent=${shape.accent || 'UNSET'}` +
        ` rows=${shape.rows} panes=${shape.panes}` +
        ` rail=${shape.railW}px pane=${shape.paneW}px` +
        ` overflow=${shape.overflow} shown=${shape.visiblePanes}`);
    if (!shape.accent) {
        console.log('    *** accent token unresolved — §6.1 trap ***');
    }
}

/* 2. Walking the rail must not make a request. */
const before = requests;
const names = await page.$$eval('[data-vp-e-pick]',
    (els) => els.map((e) => e.dataset.vpEPick));
for (const name of names) {
    await page.click(`[data-vp-e-pick="${name}"]`);
}
await page.waitForTimeout(400);
console.log(`\nwalked ${names.length} rows -> ` +
    `${requests - before} requests (want 0)`);

/* 3. One press.
 *
 * A local module by preference, and not out of squeamishness: a
 * harness that is meant to be re-run should not re-query somebody
 * else's service every time. `mmdb_lookup` answers from a database on
 * this host in ~100 ms. `circl_passivedns` was the alphabetical first
 * choice and started timing out under repeated runs — which is itself
 * the argument for not defaulting to it. */
const preferred = ['mmdb_lookup', 'hashlookup', 'onion_lookup'];
const target = preferred.find((p) => names.includes(p)) || names[0];
await page.click(`[data-vp-e-pick="${target}"]`);
const runBefore = requests;
await page.click(`[data-vp-e-pane="${target}"] [data-vp-e-run]`);
await page.waitForSelector(
    `[data-vp-e-pane="${target}"] [data-vp-e-result]`,
    { timeout: 120000 });
const ran = await page.evaluate((name) => {
    const pane = document.querySelector(`[data-vp-e-pane="${name}"]`);
    const row = document.querySelector(`[data-vp-e-row="${name}"]`);
    const result = pane.querySelector('[data-vp-e-result]');
    return {
        state: result.dataset.vpEStateIs,
        label: row.querySelector('[data-vp-e-state]').textContent.trim(),
        dot: row.querySelector('[data-vp-e-dot]').className,
        capped: !!pane.querySelector('.vp-e-partial'),
        // The answer must land in the pane it was pressed from and
        // nowhere else.
        strays: document.querySelectorAll('[data-vp-e-result]').length,
    };
}, target);
console.log(`ran ${target}: ${requests - runBefore} request(s), ` +
    `state=${ran.state} row="${ran.label}" dot=${ran.dot} ` +
    `capped=${ran.capped} results_on_page=${ran.strays}`);

/* 5. `Select all` counts without running anything. */
const beforeSelect = requests;
await page.click('[data-vp-e-select-all]');
const selected = await page.evaluate(() => {
    const panel = document.querySelector('[data-vp-enrich]');
    const run = panel.querySelector('[data-vp-e-run-selected]');
    return {
        picked: panel.querySelector('[data-vp-e-picked]').textContent,
        count: panel.querySelector('[data-vp-e-runcount]').textContent,
        ext: panel.querySelector('[data-vp-e-ext-n]').textContent,
        runEnabled: !run.disabled,
        costShown: !panel.querySelector('[data-vp-e-cost-out]')
            .classList.contains('d-none'),
    };
});
console.log(`\nselect all -> picked=${selected.picked} ` +
    `count=${selected.count} ext=${selected.ext} ` +
    `run_enabled=${selected.runEnabled} cost=${selected.costShown} ` +
    `requests=${requests - beforeSelect} (want 0)`);

/* Untick, then hand-pick the fast local modules and run the batch.
 * Not `Select all` here: that would drag in the passive-DNS module on
 * every re-run of this harness, which is the thing 3's comment says
 * not to do. */
await page.click('[data-vp-e-select-all]');
const batch = names.filter((n) => preferred.includes(n) || n === 'whois');
for (const name of batch) {
    await page.check(`[data-vp-e-select="${name}"]`);
}
const batchBefore = requests;
await page.click('[data-vp-e-run-selected]');
await page.waitForFunction((n) => {
    const panel = document.querySelector('[data-vp-enrich]');
    return panel.querySelectorAll('[data-vp-e-result]').length >= n;
}, batch.length, { timeout: 120000 });
await page.waitForTimeout(300);

const merged = await page.evaluate(() => {
    const panel = document.querySelector('[data-vp-enrich]');
    const all = panel.querySelector('[data-vp-e-pane="__all"]');
    return {
        results: panel.querySelectorAll('[data-vp-e-result]').length,
        sub: panel.querySelector('[data-vp-e-allsub]').textContent.trim(),
        head: panel.querySelector('[data-vp-e-allhead]').textContent.trim(),
        mergedItems: all.querySelectorAll('[data-vp-e-item]').length,
        allVisible: !all.classList.contains('d-none'),
        cleared: [...panel.querySelectorAll('[data-vp-e-select]')]
            .every((b) => !b.checked),
    };
});
console.log(`run ${batch.length} selected -> ` +
    `${requests - batchBefore} requests (want ${batch.length}), ` +
    `results=${merged.results} merged_items=${merged.mergedItems} ` +
    `all_pane_shown=${merged.allVisible} selection_cleared=${merged.cleared}`);
console.log(`  All results sub: "${merged.sub}"`);

/* 6. The mockup's per-element furniture. */
const furniture = await page.evaluate(() => {
    const panel = document.querySelector('[data-vp-enrich]');
    const res = [...panel.querySelectorAll('[data-vp-e-result]')];
    const disc = panel.querySelector('[data-vp-e-disc]');
    return {
        known: panel.querySelectorAll('.vp-e-known').length,
        actions: panel.querySelectorAll('.vp-e-el-acts').length,
        enabledWrites: [...panel.querySelectorAll('.vp-e-el-acts .btn')]
            .filter((b) => !b.disabled).length,
        addAll: panel.querySelectorAll(
            '[data-vp-e-result] .btn[title]').length,
        folds: panel.querySelectorAll('[data-vp-e-fold]').length,
        discOpen: disc ? disc.getAttribute('aria-expanded') : 'none',
        states: res.map((r) => r.dataset.vpEStateIs).join(','),
    };
});
console.log(`furniture: known=${furniture.known} ` +
    `action_groups=${furniture.actions} ` +
    `enabled_write_buttons=${furniture.enabledWrites} (want 0) ` +
    `folds=${furniture.folds} states=${furniture.states}`);

/* The fold actually folds. */
if (furniture.folds) {
    const was = await page.$eval('[data-vp-e-fold]',
        (f) => f.classList.contains('d-none'));
    await page.click('[data-vp-e-disc]');
    const now = await page.$eval('[data-vp-e-fold]',
        (f) => f.classList.contains('d-none'));
    console.log(`fold toggles: ${was} -> ${now}`);
}

await page.screenshot({
    path: '/home/sami/.claude/jobs/fdd384a2/tmp/28-enrichment.png',
    fullPage: false,
});
await browser.close();
