// The clock band, measured rather than asserted.
//
// The HTTP probe reads the band's words; this reads its box. A band
// whose markup is perfect and whose body has collapsed, overflowed the
// card, or gone invisible against the card behind it passes every
// string assertion ever written about it — `09c-wiring.md` §7 is the
// phase that learned this, where a 200 with an empty body satisfied
// every check that did not measure a size.
//
// Three things are measured, in both themes:
//
//   1. The band is drawn and has height. Nothing inside it is a
//      promise, so a zero-height band is a missing band.
//   2. It stays inside the card. The body is capped at 620px because
//      the shelf is a bar with a label at each end and the full column
//      pushes them apart; the cap is only right if the band itself
//      still fills the card's width and the bar does not spill out of
//      it.
//   3. The bar is drawn at a width that means something — the runway
//      as a fraction of the track, against the days the band prints.
//      A fill of 0px under `69 days left` is the failure this axis
//      shipped once already in another costume.
//
//   node prd/analyst-profile/10-clock-band-geometry.mjs \
//        https://localhost 8.8.8.8
import { chromium } from
    '/home/sami/git/pivotick/node_modules/playwright/index.mjs';

const BASE = process.argv[2] || 'https://localhost';
const VALUE = process.argv[3] || '8.8.8.8';
const EMAIL = process.argv[4] || 'admin@admin.test';
const PASS = process.argv[5] || 'admin';

let passed = 0;
let failed = 0;
const ok = (m) => { passed++; console.log('ok   ' + m); };
const no = (m) => { failed++; console.log('FAIL ' + m); };

const b64 = Buffer.from(VALUE).toString('base64')
    .replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');

const browser = await chromium.launch();
const ctx = await browser.newContext({
    ignoreHTTPSErrors: true,
    viewport: { width: 1440, height: 1000 },
});
const page = await ctx.newPage();

await page.goto(`${BASE}/users/login`, { waitUntil: 'domcontentloaded' });
await page.fill('#UserEmail', EMAIL);
await page.fill('#UserPassword', PASS);
await Promise.all([
    page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
    page.click('input[type="submit"], button[type="submit"]'),
]);

await page.goto(`${BASE}/values/view/${b64}`,
    { waitUntil: 'domcontentloaded' });
await page.click('a[href="#tab-assessment"]').catch(() => {});
await page.waitForSelector('.vp-vc-clock', { timeout: 20000 })
    .catch(() => {});

for (const theme of ['light', 'dark']) {
    await page.evaluate((t) => {
        document.documentElement.setAttribute('data-bs-theme', t);
    }, theme);
    await page.waitForTimeout(150);

    const m = await page.evaluate(() => {
        const band = document.querySelector('.vp-vc-clock');
        if (!band) { return null; }
        const card = band.closest('.vp-vc');
        const body = band.querySelector('.vp-vc-clock-body');
        const track = band.querySelector('.vp-shelf-track');
        const fill = band.querySelector('.vp-shelf-fill');
        const days = band.querySelector('.vp-shelf-days');
        const box = (el) => (el ? el.getBoundingClientRect() : null);
        const seen = (el) => {
            if (!el) { return null; }
            const s = getComputedStyle(el);
            return { bg: s.backgroundColor, colour: s.color };
        };
        return {
            band: box(band),
            card: box(card),
            body: box(body),
            track: box(track),
            fill: box(fill),
            daysText: days ? days.textContent.trim() : null,
            chrome: seen(band),
        };
    });

    if (m === null) {
        no(`${theme}: no clock band on the page at all`);
        continue;
    }

    // 1. Drawn, with height.
    if (m.band.height > 20) {
        ok(`${theme}: the band is drawn (${Math.round(m.band.height)}px tall)`);
    } else {
        no(`${theme}: the band is ${Math.round(m.band.height)}px tall`);
    }

    // 2. Inside the card, and filling its width.
    const spill = Math.round(m.band.right - m.card.right);
    if (spill <= 1) {
        ok(`${theme}: the band stays inside the card (${spill}px)`);
    } else {
        no(`${theme}: the band overflows the card by ${spill}px`);
    }
    if (Math.abs(m.band.width - m.card.width) <= 2) {
        ok(`${theme}: and spans it, so it reads as a band rather than`
            + ` an inset box`);
    } else {
        no(`${theme}: the band is ${Math.round(m.band.width)}px in a`
            + ` ${Math.round(m.card.width)}px card`);
    }
    if (m.body.width <= 620 + 32 + 1) {
        ok(`${theme}: the body is capped (${Math.round(m.body.width)}px)`);
    } else {
        no(`${theme}: the body ran to ${Math.round(m.body.width)}px`);
    }

    // 3. The bar means what the words say.
    if (m.track === null) {
        ok(`${theme}: no track, so this value has no clock to draw`);
    } else {
        const pct = Math.round((m.fill.width / m.track.width) * 100);
        const stated = /(\d+) days? left/.exec(m.daysText || '');
        console.log(`     fill ${pct}% of the track, label`
            + ` "${m.daysText}"`);
        if (m.fill.width > 0 && pct <= 100) {
            ok(`${theme}: the bar is drawn at ${pct}% of its track`);
        } else {
            no(`${theme}: the bar is ${Math.round(m.fill.width)}px wide`);
        }
        if (stated && pct === 0) {
            no(`${theme}: ${stated[1]} days left drawn as an empty bar`);
        }
    }

    // The band has to be distinguishable from the card it sits in, in
    // both themes — the whole reason it is a band and not a paragraph.
    if (m.chrome.bg && m.chrome.bg !== 'rgba(0, 0, 0, 0)') {
        ok(`${theme}: the band carries its own ground (${m.chrome.bg})`);
    } else {
        no(`${theme}: the band has no background of its own`);
    }
}

await browser.close();
console.log(`\npassed: ${passed}   failed: ${failed}`);
process.exit(failed ? 1 : 0);
