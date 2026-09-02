// The rest of the local/remote matrix on the co-occurrence panel: a
// complete facet (must stay in the page), Reset over a served
// narrowing (must go back to the fold), and a re-rank over a cut table
// (must go back, carrying the narrowing). The regression half of §7.5's
// table in `24b-relationships.md`; run it with and without `VP_JS` to
// show that nothing answered in the page before it is fetched now.
//
// Not part of the application.
//
//   node 24b-narrow-matrix-harness.js [urlsafe-b64 value]
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
        var sort = list.querySelector('[data-vp-sort] .vp-pill.active');
        var text = function (s) {
            var el = list.querySelector(s);
            return el ? el.textContent.trim() : null;
        };
        return {
            gen: list.dataset.testGen || null,
            active: list.dataset.vpNarrowActive || '',
            carried: rows.length,
            visible: rows.filter(function (r) {
                return !r.classList.contains('d-none');
            }).length,
            of: text('[data-vp-page-of]'),
            rank: sort ? sort.dataset.vpPill : null,
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
        document.querySelector(sel).dataset.testGen = g;
    }, LIST, gen);
}

async function replaced(page, gen, ms) {
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

async function facet(page, value) {
    return page.evaluate(function (sel, v) {
        var box = document.querySelector(sel)
            .querySelector('input[data-vp-facet-key="warninglist"][value="'
                + v + '"]');
        if (!box) {
            return false;
        }
        box.click();
        return true;
    }, LIST, value);
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

    if (process.env.VP_JS) {
        const body = require('fs').readFileSync(process.env.VP_JS, 'utf8');
        await page.setRequestInterception(true);
        page.on('request', function (req) {
            if (/value-profile\.js/.test(req.url())) {
                req.respond({
                    status: 200,
                    contentType: 'application/javascript',
                    body: body,
                });
                return;
            }
            req.continue();
        });
        console.log('serving', process.env.VP_JS);
    }

    await page.goto('https://localhost/users/login',
        { waitUntil: 'networkidle2' });
    await page.type('#UserEmail', 'admin@admin.test');
    await page.type('#UserPassword', 'admin');
    await Promise.all([
        page.waitForNavigation({ waitUntil: 'networkidle2' }),
        page.click('input[type=submit], button[type=submit]'),
    ]);
    await page.goto('https://localhost/values/view/' + B64
        + '#tab-relationships', { waitUntil: 'networkidle2' });
    await sleep(800);
    await page.evaluate(function () {
        document.querySelector('.nav-link[href="#tab-relationships"]').click();
    });
    await page.waitForSelector(LIST, { timeout: 90000 });
    await sleep(1500);

    const COMPLETE = 'list-of-known-ipv4-public-dns-resolvers';

    console.log('== A. a complete facet must not cost a request ==');
    console.log('before:', JSON.stringify(await readState(page)));
    await stamp(page, 'a1');
    await facet(page, COMPLETE);
    var went = await replaced(page, 'a1', 4000);
    var a1 = await readState(page);
    console.log('after tick (re-requested: ' + went + '):',
        JSON.stringify(a1));
    console.log('ASSERT stayed local and narrowed to its whole count:',
        !went && a1.visible > 0 && a1.of === '11' ? 'PASS' : 'FAIL');

    await stamp(page, 'a2');
    await facet(page, COMPLETE);
    var went2 = await replaced(page, 'a2', 4000);
    var a2 = await readState(page);
    console.log('after untick (re-requested: ' + went2 + '):',
        JSON.stringify(a2));
    console.log('ASSERT a locally narrowed table widens locally:',
        !went2 && a2.of === '100' ? 'PASS' : 'FAIL');

    console.log('\n== B. Reset over a served narrowing goes back ==');
    await stamp(page, 'b1');
    await facet(page, 'list-of-rfc-5735-cidr-blocks');
    await replaced(page, 'b1', 30000);
    await sleep(1000);
    console.log('narrowed:', JSON.stringify(await readState(page)));
    await stamp(page, 'b2');
    await page.evaluate(function (sel) {
        document.querySelector(sel)
            .querySelector('[data-vp-facet-clear]').click();
    }, LIST);
    var wentB = await replaced(page, 'b2', 30000);
    await sleep(1000);
    var b = await readState(page);
    console.log('after Reset (re-requested: ' + wentB + '):',
        JSON.stringify(b));
    console.log('ASSERT Reset refetched the whole neighbourhood:',
        wentB && b.of === '100' && b.active === '' ? 'PASS' : 'FAIL');

    console.log('\n== C. a re-rank over a cut table goes back ==');
    await stamp(page, 'c1');
    await page.evaluate(function (sel) {
        var pills = Array.prototype.slice.call(
            document.querySelector(sel)
                .querySelectorAll('[data-vp-sort] [data-vp-pill]')
        );
        var other = pills.find(function (p) {
            return !p.classList.contains('active');
        });
        if (other) {
            other.click();
        }
    }, LIST);
    var wentC = await replaced(page, 'c1', 30000);
    await sleep(1000);
    var c = await readState(page);
    console.log('after re-rank (re-requested: ' + wentC + '):',
        JSON.stringify(c));
    console.log('ASSERT ranking is decided by the fold, not the page:',
        wentC && c.rank === 'recent' ? 'PASS' : 'FAIL');

    console.log('\n== D. re-rank keeps a served narrowing ==');
    await stamp(page, 'd1');
    await facet(page, 'list-of-rfc-5735-cidr-blocks');
    await replaced(page, 'd1', 30000);
    await sleep(1000);
    await stamp(page, 'd2');
    await page.evaluate(function (sel) {
        var pills = Array.prototype.slice.call(
            document.querySelector(sel)
                .querySelectorAll('[data-vp-sort] [data-vp-pill]')
        );
        var other = pills.find(function (p) {
            return !p.classList.contains('active');
        });
        if (other) {
            other.click();
        }
    }, LIST);
    var wentD = await replaced(page, 'd2', 30000);
    await sleep(1000);
    var d = await readState(page);
    console.log('after re-rank (re-requested: ' + wentD + '):',
        JSON.stringify(d));
    console.log('ASSERT the narrowing survived the re-rank:',
        wentD && d.ticked.length === 1 && d.active === '1'
            && d.of === '21' ? 'PASS' : 'FAIL');

    await browser.close();
}()).catch(function (e) {
    console.log('THREW:', String(e).slice(0, 500));
    process.exit(1);
});
