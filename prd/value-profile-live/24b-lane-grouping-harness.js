// Read the dated strip's lane grouping off the live tab, for both
// sides of the threshold B7 introduces (§9 of `24b-relationships.md`).
//
// The two witnesses are the ones the task names: `8.8.8.8`, whose three
// resolutions used to collapse into two template lanes, and
// `github.com`, whose 46 relations in one template are why template
// lanes were chosen in the first place and which must render exactly as
// it did before.
//
// It also drives one facet tick, because the lane pairing key changed
// with the grouping: `VP.paintSpanStrips` finds a lane's count cell by
// the token on its axis, so a strip whose counts stop moving under a
// narrowing is the failure this checks for rather than reasons about.
//
// Not part of the application.
//
//   node 24b-lane-grouping-harness.js
const puppeteer = require('/home/sami/node_modules/puppeteer');

const OUT = '/home/sami/.claude/jobs/fdd384a2/tmp/';
const STRIP = '#vp-dated-strip';
const PANEL = '#vp-relation-dated';
// `draculax.myq-see.com.` is the section's founding example and the
// tallest the value grouping can get: five `passive-dns` objects, so a
// lane each is the worst case for the height the tab is not allowed to
// grow. The other two bracket it.
const CASES = [
    { name: '8.8.8.8', b64: 'OC44LjguOA==', expect: 'value' },
    {
        name: 'draculax.myq-see.com.',
        b64: 'ZHJhY3VsYXgubXlxLXNlZS5jb20u',
        expect: 'value',
    },
    {
        name: 'luxtrust-unlock.com',
        b64: 'bHV4dHJ1c3QtdW5sb2NrLmNvbQ==',
        expect: '?',
    },
    { name: 'github.com', b64: 'Z2l0aHViLmNvbQ==', expect: 'template' },
];

function sleep(ms) {
    return new Promise(function (r) { setTimeout(r, ms); });
}

// Everything the grouping decides, read from the rendered markup rather
// than from the fold — the point is what a reader is looking at.
async function readStrip(page) {
    return page.evaluate(function (stripSel, panelSel) {
        var strip = document.querySelector(stripSel);
        var panel = document.querySelector(panelSel);
        if (!strip || !panel) {
            return { missing: true, strip: !!strip, panel: !!panel };
        }
        var heads = Array.prototype.slice.call(
            strip.querySelectorAll('.vp-lane-head')
        ).map(function (h) { return h.textContent.trim(); });
        var lanes = Array.prototype.slice.call(
            strip.querySelectorAll('.vp-lane-label')
        ).map(function (label) {
            var chip = label.querySelector('.vp-strip-tag');
            var axis = label.nextElementSibling;
            var token = axis ? axis.dataset.vpSpanLane : null;
            var cell = token
                ? strip.querySelector('[data-vp-span-count="' + token + '"]')
                : null;
            return {
                text: chip ? chip.textContent.trim() : null,
                title: chip ? chip.getAttribute('title') : null,
                mono: chip
                    ? chip.classList.contains('vp-strip-tag-mono')
                    : null,
                icon: !!(chip && chip.querySelector('i')),
                token: token,
                shown: cell ? cell.textContent.trim() : null,
                total: cell ? cell.dataset.vpSpanTotal : null,
                mark: axis
                    ? axis.querySelectorAll('[data-vp-span-row]').length
                    : null,
                font: chip
                    ? getComputedStyle(chip).fontFamily.slice(0, 24)
                    : null,
                transform: chip
                    ? getComputedStyle(chip).textTransform
                    : null,
            };
        });
        // What the rows actually are, so the two groupings can be
        // compared without reverting the code: the lane a row would
        // have had is its template, the lane it has now is its value.
        var pairs = Array.prototype.slice.call(
            panel.querySelectorAll('tbody tr[data-vp-span-key]')
        ).map(function (tr) {
            var cells = tr.querySelectorAll('td');
            return [
                cells[0] ? cells[0].textContent.trim().split('\n')[0] : '?',
                cells[1] ? cells[1].textContent.trim().split('\n')[0] : '?',
                cells[2] ? cells[2].textContent.trim().split('\n')[0] : '?',
            ].join(' | ');
        });
        var note = panel.querySelector('.vp-strip-note');
        return {
            pairs: pairs.slice(0, 12),
            heads: heads,
            lanes: lanes,
            laneCount: lanes.length,
            note: note ? note.textContent.trim() : null,
            noteInsideStrip: note ? strip.contains(note) : null,
            ariaLabel: strip.getAttribute('aria-label'),
            rows: panel.querySelectorAll('[data-vp-span-key]').length,
            legend: !!panel.querySelector('.vp-strip-legend'),
        };
    }, stripSel(), panelSel());
}

function stripSel() { return STRIP; }
function panelSel() { return PANEL; }

// Tick the first named value in a facet group, so the lane counts have
// to move. Returns what was ticked, or null when the group is absent.
async function tickFirstFacet(page, key) {
    return page.evaluate(function (panelSel, key) {
        var panel = document.querySelector(panelSel);
        var boxes = Array.prototype.slice.call(
            panel.querySelectorAll(
                'input[data-vp-facet-key="' + key + '"]'
            )
        ).filter(function (b) {
            return b.value !== '_hit' && b.value !== '_clear';
        });
        if (!boxes.length) {
            return null;
        }
        boxes[0].click();
        return boxes[0].value;
    }, PANEL, key);
}

(async function () {
    const browser = await puppeteer.launch({
        headless: 'new',
        ignoreHTTPSErrors: true,
        args: ['--ignore-certificate-errors', '--no-sandbox'],
    });
    const page = await browser.newPage();
    // The value page fires nine lazy panels at once and this machine
    // runs other jobs' test suites; 30s is not a verdict about the tab.
    page.setDefaultNavigationTimeout(180000);
    page.setDefaultTimeout(180000);
    await page.setViewport({ width: 1600, height: 1400 });
    page.on('pageerror', function (e) {
        console.log('PAGEERROR:', String(e).slice(0, 300));
    });

    await page.goto('https://localhost/users/login',
        { waitUntil: 'networkidle2' });
    await page.type('#UserEmail', 'admin@admin.test');
    await page.type('#UserPassword', 'admin');
    await Promise.all([
        page.waitForNavigation({ waitUntil: 'networkidle2' }),
        page.click('input[type=submit], button[type=submit]'),
    ]);
    console.log('login ->', page.url());

    for (const c of CASES) {
        console.log('\n================ ' + c.name
            + ' (expect ' + c.expect + ' lanes) ================');
        await page.goto('https://localhost/values/view/' + c.b64
            + '#tab-relationships', { waitUntil: 'domcontentloaded' });
        await sleep(2000);
        await page.evaluate(function () {
            var link = document.querySelector(
                '.nav-link[href="#tab-relationships"]'
            );
            if (link) {
                link.click();
            }
        });
        try {
            // The scan is cold on the first read after a CACHE_SHAPE
            // bump, and this machine is not quiet.
            await page.waitForSelector(PANEL, { timeout: 180000 });
        } catch (e) {
            console.log('FAIL: dated panel never loaded');
            await page.screenshot({ path: OUT + 'b7-noload-'
                + c.name + '.png', fullPage: true });
            continue;
        }
        await sleep(1500);

        const empty = await page.evaluate(function (sel) {
            var panel = document.querySelector(sel);
            return !panel.querySelector('#vp-dated-strip');
        }, PANEL);
        if (empty) {
            console.log('no strip: the panel is in its empty state');
            continue;
        }

        const served = await readStrip(page);
        console.log('as served:', JSON.stringify(served, null, 1));

        await page.evaluate(function (sel) {
            document.querySelector(sel).scrollIntoView({ block: 'center' });
        }, STRIP);
        await sleep(400);
        const panelEl = await page.$(PANEL);
        await panelEl.screenshot({ path: OUT + 'b7-' + c.name + '.png' });
        console.log('shot ->', OUT + 'b7-' + c.name + '.png');

        const ticked = await tickFirstFacet(page, 'datedtype');
        await sleep(600);
        if (ticked === null) {
            console.log('no datedtype facet to tick');
        } else {
            const narrowed = await readStrip(page);
            console.log('after ticking datedtype=' + ticked + ':',
                JSON.stringify(narrowed.lanes.map(function (l) {
                    return l.text + ' ' + l.shown + '/' + l.total;
                })));
        }

        await panelEl.screenshot({
            path: OUT + 'b7-' + c.name + '-narrowed.png',
        });
        console.log('shot ->', OUT + 'b7-' + c.name + '-narrowed.png');
    }

    await browser.close();
}()).catch(function (e) {
    console.log('THREW:', e && e.stack ? e.stack : String(e));
    process.exit(1);
});
