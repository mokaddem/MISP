// Drive the co-occurrence panel's narrowing on the live instance: tick
// a warninglist, untick it, then switch the roll-up. §7.5 and §7.6 of
// `24b-relationships.md` are what it was written for, and the reported
// bug lives in the round-trip — a harness serving a saved fragment
// cannot show it, so this logs in and drives the real page.
//
// `VP_JS=<path>` serves another build of `value-profile.js` by request
// interception, which is how the before column of that table was
// measured rather than reasoned about.
//
// Not part of the application.
//
//   node 24b-narrow-locality-harness.js [urlsafe-b64 value]
//   VP_JS=/tmp/pre-fix.js node 24b-narrow-locality-harness.js
const puppeteer = require('/home/sami/node_modules/puppeteer');

const B64 = process.argv[2] || 'OC44LjguOA==';
const LIST = '[data-vp-list][data-vp-narrow-url]';

function sleep(ms) {
    return new Promise(function (r) { setTimeout(r, ms); });
}

async function readState(page) {
    return page.evaluate(function (sel) {
        var list = document.querySelector(sel);
        if (!list) {
            return null;
        }
        var rows = Array.prototype.slice.call(
            list.querySelectorAll('[data-vp-group-pane="value"] tbody tr')
        );
        var eventRows = Array.prototype.slice.call(
            list.querySelectorAll('[data-vp-group-pane="event"] tbody tr')
        );
        var text = function (s) {
            var el = list.querySelector(s);
            return el ? el.textContent.trim() : null;
        };
        var summary = list.querySelector('[data-vp-facet-summary]');
        var empty = list.querySelector('[data-vp-list-empty]');
        var token = function (row, t) {
            return (row.dataset.vpFacet || '').split(/\s+/).indexOf(t) !== -1;
        };
        return {
            gen: list.dataset.testGen || null,
            cut: list.dataset.vpNarrowCut || '',
            active: list.dataset.vpNarrowActive || '',
            group: list.dataset.vpGroupActive || '',
            carried: rows.length,
            visible: rows.filter(function (r) {
                return !r.classList.contains('d-none');
            }).length,
            eventCarried: eventRows.length,
            eventVisible: eventRows.filter(function (r) {
                return !r.classList.contains('d-none');
            }).length,
            hitRows: rows.filter(function (r) {
                return token(r, 'warninglist:_hit');
            }).length,
            clearRows: rows.filter(function (r) {
                return token(r, 'warninglist:_clear');
            }).length,
            from: text('[data-vp-page-from]'),
            to: text('[data-vp-page-to]'),
            of: text('[data-vp-page-of]'),
            summary: summary
                ? (summary.className.indexOf('vp-facet-summary-on') !== -1
                    ? 'FILTERED'
                    : 'NO FILTER')
                : null,
            emptyState: empty
                ? !empty.classList.contains('d-none')
                : null,
            ticked: Array.prototype.slice.call(
                list.querySelectorAll('input[data-vp-facet-key]:checked')
            ).map(function (b) {
                return b.dataset.vpFacetKey + ':' + b.value;
            }),
        };
    }, LIST);
}

async function stamp(page, gen) {
    await page.evaluate(function (sel, g) {
        var list = document.querySelector(sel);
        if (list) {
            list.dataset.testGen = g;
        }
    }, LIST, gen);
}

async function waitReplaced(page, gen, ms) {
    var waited = 0;
    while (waited < ms) {
        var fresh = await page.evaluate(function (sel, g) {
            var list = document.querySelector(sel);
            return !!list && list.dataset.testGen !== g;
        }, LIST, gen);
        if (fresh) {
            return true;
        }
        await sleep(250);
        waited += 250;
    }
    return false;
}

async function clickFacet(page, key, value) {
    return page.evaluate(function (sel, k, v) {
        var list = document.querySelector(sel);
        var box = list.querySelector('input[data-vp-facet-key="' + k
            + '"][value="' + v + '"]');
        if (!box) {
            return false;
        }
        box.click();
        return true;
    }, LIST, key, value);
}

(async function () {
    const browser = await puppeteer.launch({
        headless: 'new',
        ignoreHTTPSErrors: true,
        args: ['--ignore-certificate-errors', '--no-sandbox'],
    });
    const page = await browser.newPage();
    await page.setViewport({ width: 1600, height: 1400 });
    page.on('pageerror', function (e) {
        console.log('PAGEERROR:', String(e).slice(0, 300));
    });

    // Replay the same drive against another build of the panel script,
    // so a claimed fix can be shown to be one.
    if (process.env.VP_JS) {
        const body = require('fs').readFileSync(process.env.VP_JS, 'utf8');
        await page.setRequestInterception(true);
        page.on('request', function (req) {
            if (/value-profile\.js/.test(req.url())) {
                console.log('serving', process.env.VP_JS, 'for', req.url());
                req.respond({
                    status: 200,
                    contentType: 'application/javascript',
                    body: body,
                });
                return;
            }
            req.continue();
        });
    }

    await page.goto('https://localhost/users/login',
        { waitUntil: 'networkidle2' });
    await page.type('#UserEmail', 'admin@admin.test');
    await page.type('#UserPassword', 'admin');
    await Promise.all([
        page.waitForNavigation({ waitUntil: 'networkidle2' }),
        page.click('input[type=submit], button[type=submit]'),
    ]);
    console.log('login ->', page.url());

    await page.goto('https://localhost/values/view/' + B64
        + '#tab-relationships', { waitUntil: 'networkidle2' });
    await sleep(1000);
    const clicked = await page.evaluate(function () {
        var link = document.querySelector(
            '.nav-link[href="#tab-relationships"]'
        );
        if (!link) {
            return false;
        }
        link.click();
        return true;
    });
    console.log('relationships tab clicked:', clicked);
    try {
        await page.waitForSelector(LIST, { timeout: 90000 });
    } catch (e) {
        console.log('FAIL: co-occurrence panel never loaded');
        await page.screenshot({ path: '/home/sami/.claude/jobs/fdd384a2/'
            + 'tmp/vp-noload.png' });
        await browser.close();
        return;
    }
    await sleep(1500);

    console.log('\n== 1. as served ==');
    console.log(JSON.stringify(await readState(page)));

    const pick = await page.evaluate(function (sel) {
        var list = document.querySelector(sel);
        var boxes = Array.prototype.slice.call(
            list.querySelectorAll('input[data-vp-facet-key="warninglist"]')
        );
        var named = boxes.filter(function (b) {
            return b.value !== '_hit' && b.value !== '_clear';
        });
        var box = named.find(function (b) { return !b.dataset.vpComplete; })
            || named[0] || boxes[0];
        if (!box) {
            return null;
        }
        var label = box.closest('.vp-facet');
        return {
            value: box.value,
            complete: !!box.dataset.vpComplete,
            listed: box.dataset.vpFacetListed,
            count: label
                ? (label.querySelector('.vp-facet-count') || {}).textContent
                : null,
            offered: boxes.map(function (b) { return b.value; }),
        };
    }, LIST);
    console.log('\npicked warninglist facet:', JSON.stringify(pick));
    if (!pick) {
        console.log('FAIL: no warninglist facet offered on this value');
        await browser.close();
        return;
    }

    await stamp(page, 'g1');
    await clickFacet(page, 'warninglist', pick.value);
    const served = await waitReplaced(page, 'g1', 30000);
    await sleep(1200);
    console.log('\n== 2. after ticking ' + pick.value
        + ' (panel re-requested: ' + served + ') ==');
    console.log(JSON.stringify(await readState(page)));

    await stamp(page, 'g2');
    await clickFacet(page, 'warninglist', pick.value);
    const reloaded = await waitReplaced(page, 'g2', 30000);
    await sleep(1200);
    const after = await readState(page);
    console.log('\n== 3. after unticking it (panel re-requested: '
        + reloaded + ') ==');
    console.log(JSON.stringify(after));

    console.log('\nASSERT untick went back to the fold:',
        reloaded ? 'PASS' : 'FAIL');
    console.log('ASSERT rows include values with no warninglist hit:',
        after && after.clearRows > 0 ? 'PASS' : 'FAIL',
        '(clear=' + (after && after.clearRows)
        + ' hit=' + (after && after.hitRows) + ')');
    console.log('ASSERT no filter is claimed and none applies:',
        after && after.summary === 'NO FILTER' && after.ticked.length === 0
            ? 'PASS' : 'FAIL');

    // Group switch over a fold-narrowed page.
    await stamp(page, 'g3');
    await clickFacet(page, 'warninglist', pick.value);
    const again = await waitReplaced(page, 'g3', 30000);
    await sleep(1200);
    console.log('\n== 4. narrowed again (re-requested: ' + again + ') ==');
    console.log(JSON.stringify(await readState(page)));

    await page.evaluate(function (sel) {
        var list = document.querySelector(sel);
        var pill = list.querySelector('[data-vp-group] [data-vp-pill="event"]');
        if (pill) {
            pill.click();
        }
    }, LIST);
    await sleep(1200);
    const evented = await readState(page);
    console.log('\n== 5. Group by -> Events, filter still ticked ==');
    console.log(JSON.stringify(evented));
    console.log('\nASSERT the event roll-up is not emptied by the value'
        + ' filter:',
        evented && evented.eventVisible > 0 && !evented.emptyState
            ? 'PASS' : 'FAIL');

    await page.evaluate(function (sel) {
        var list = document.querySelector(sel);
        var pill = list.querySelector('[data-vp-group] [data-vp-pill="value"]');
        if (pill) {
            pill.click();
        }
    }, LIST);
    await sleep(1200);
    const back = await readState(page);
    console.log('\n== 6. back to the value roll-up ==');
    console.log(JSON.stringify(back));
    console.log('\nASSERT the narrowing comes back with the roll-up:',
        back && back.visible > 0 && back.summary === 'FILTERED'
            && back.ticked.length === 1 ? 'PASS' : 'FAIL');

    await page.screenshot({
        path: '/home/sami/.claude/jobs/fdd384a2/tmp/vp-events-pane.png',
        fullPage: false,
    });
    await browser.close();
}()).catch(function (e) {
    console.log('THREW:', String(e).slice(0, 500));
    process.exit(1);
});
