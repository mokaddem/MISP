// The Assessment card's two axis bands, measured rather than asserted.
//
// The HTTP probe reads their words; this reads their boxes. A band
// whose markup is perfect and whose body has collapsed, overflowed the
// card, or gone invisible against the card behind it passes every
// string assertion ever written about it — `09c-wiring.md` §7 is the
// phase that learned this, where a 200 with an empty body satisfied
// every check that did not measure a size.
//
// Both bands, in both themes:
//
//   1. Drawn, with height. Nothing inside either is a promise, so a
//      zero-height band is a missing band.
//   2. Inside the card and spanning it. Each body is capped — the
//      clock's shelf and the lean's split are both a bar with a label
//      at each end, and the full column pushes those apart — so the
//      cap is only right if the band itself still fills the card.
//   3. The bar means what the words say. A fill of 0px under
//      `69 days left`, or a lean fill indistinguishable from its own
//      track, is the failure each of these axes shipped once already
//      in another costume.
//
//   node prd/analyst-profile/10-band-geometry.mjs \
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
await page.waitForSelector('.vp-vc-clock, .vp-vc-lean', { timeout: 20000 })
    .catch(() => {});

const measure = (sel) => page.evaluate((s) => {
    const band = document.querySelector(s);
    if (!band) { return null; }
    const card = band.closest('.vp-vc');
    const body = band.querySelector(
        '.vp-vc-clock-body, .vp-vc-lean-body');
    const track = band.querySelector(
        '.vp-shelf-track, .vp-vc-lean-track');
    const fill = band.querySelector('.vp-shelf-fill, .vp-vc-lean-fill');
    const label = band.querySelector('.vp-shelf-days, .vp-vc-lean-note');
    const marks = Array.from(
        band.querySelectorAll('.vp-shelf-mark, .vp-vc-lean-bar'));
    const box = (el) => (el ? el.getBoundingClientRect() : null);
    const cs = getComputedStyle(band);
    return {
        band: box(band),
        card: box(card),
        body: box(body),
        track: box(track),
        fill: box(fill),
        marks: marks.map((m) => box(m).left - box(track).left),
        trackBg: track ? getComputedStyle(track).backgroundColor : null,
        fillBg: fill ? getComputedStyle(fill).backgroundColor : null,
        labelText: label ? label.textContent.trim() : null,
        bandBg: cs.backgroundColor,
        borderTop: parseFloat(cs.borderTopWidth),
    };
}, sel);

for (const theme of ['light', 'dark']) {
    await page.evaluate((t) => {
        document.documentElement.setAttribute('data-bs-theme', t);
    }, theme);
    await page.waitForTimeout(150);

    for (const band of [
        { sel: '.vp-vc-clock', name: 'clock', ground: true },
        { sel: '.vp-vc-lean', name: 'lean', ground: false },
    ]) {
        const m = await measure(band.sel);
        const tag = `${theme}/${band.name}`;
        if (m === null) {
            no(`${tag}: the band is not on the page`);
            continue;
        }

        if (m.band.height > 20) {
            ok(`${tag}: drawn (${Math.round(m.band.height)}px tall)`);
        } else {
            no(`${tag}: ${Math.round(m.band.height)}px tall`);
        }

        const spill = Math.round(m.band.right - m.card.right);
        if (spill <= 1 && Math.abs(m.band.width - m.card.width) <= 2) {
            ok(`${tag}: spans the card without overflowing it`);
        } else {
            no(`${tag}: ${Math.round(m.band.width)}px in a`
                + ` ${Math.round(m.card.width)}px card, spill ${spill}px`);
        }
        if (m.body.width <= 620 + 32 + 1) {
            ok(`${tag}: body capped (${Math.round(m.body.width)}px)`);
        } else {
            no(`${tag}: body ran to ${Math.round(m.body.width)}px`);
        }

        if (m.track === null) {
            ok(`${tag}: no bar, so this value has none to draw`);
        } else {
            const pct = Math.round((m.fill.width / m.track.width) * 100);
            console.log(`     ${tag}: fill ${pct}% —`
                + ` "${(m.labelText || '').slice(0, 60)}"`);
            // An empty bar is a reading, not a failure — an expired
            // value has no runway left and a benign one has no threat
            // share. What must never happen is an empty bar under a
            // label claiming something remains. The first version of
            // this check called every zero a defect, which was true of
            // `8.8.8.8` and of nothing else: a value 2,224 days past
            // its lifetime drew `0%` correctly and failed.
            const claimsLeft = /\d+ days? left/.test(m.labelText || '');
            if (pct < 0 || pct > 100) {
                no(`${tag}: the bar is drawn at ${pct}% of its track`);
            } else if (claimsLeft && pct === 0) {
                no(`${tag}: "${m.labelText}" drawn as an empty bar`);
            } else {
                ok(`${tag}: the bar is drawn at ${pct}% of its track`);
            }
            // The two sides have to be two colours, or the share is a
            // picture of nothing.
            if (m.fillBg !== m.trackBg) {
                ok(`${tag}: the bar and its ground are different`
                    + ` colours`);
            } else {
                no(`${tag}: the bar is the same colour as its track`);
            }
            // Every mark sits on the track it marks.
            const stray = m.marks.filter(
                (left) => left < -1 || left > m.track.width + 1);
            if (m.marks.length && !stray.length) {
                ok(`${tag}: ${m.marks.length} threshold mark(s), all on`
                    + ` the track`);
            } else if (!m.marks.length) {
                ok(`${tag}: no threshold to mark on this value`);
            } else {
                no(`${tag}: ${stray.length} mark(s) off the track`);
            }
        }

        // The clock band carries its own ground; the lean band
        // deliberately does not — it has two sides and a tinted frame
        // would be the frame picking one. So each is checked for the
        // separation it actually uses.
        if (band.ground) {
            if (m.bandBg && m.bandBg !== 'rgba(0, 0, 0, 0)') {
                ok(`${tag}: carries its own ground (${m.bandBg})`);
            } else {
                no(`${tag}: has no background of its own`);
            }
        } else if (m.borderTop >= 1) {
            ok(`${tag}: separated by a rule rather than a tint`
                + ` (${m.borderTop}px)`);
        } else {
            no(`${tag}: no ground and no border — nothing separates it`);
        }
    }
}

await browser.close();
console.log(`\npassed: ${passed}   failed: ${failed}`);
process.exit(failed ? 1 : 0);
