// Unit tests for the Pivot Explorer graph builder.
//
//   node tests/js/pivot-explorer-graph.test.js
//
// Zero dependencies — plain node, no package.json, no test runner. Exits 0 on
// success, 1 on the first failing assertion's suite.
//
// What this covers: `computeConnectivity()` and `buildGraphData()` in
// app/webroot/js/pivot-explorer.js are pure functions from a MISP event payload
// to pivotick's {nodes, edges}. They are the substance of the layer work in
// docs/dev/pivot-explorer-v16-prd.md §9 (tasks 3, 3b, 3c, 5, 5b), and they need
// no browser — so they are tested here rather than by clicking through
// /events/view2. Anything visual (styling, legend, layout) still needs the
// manual pass in PRD §8.
//
// How it works: the module is an IIFE with no exports, so rather than reaching
// inside it, we stub just enough DOM for it to boot, resolve its event fetch
// with a fixture, and capture the {nodes, edges} it hands to `new Pivotick()`.
// Assertions therefore run through the real code path, and the module needs no
// test-only seam. The editor tray is read the same way, off the extraPanel the
// module hands over at construction time.

'use strict';

const fs = require('fs');
const path = require('path');
const vm = require('vm');

// PIVOT_EXPLORER_JS overrides the module under test, so the suite can be
// pointed at a deliberately broken copy to check that it still fails.
const MODULE_PATH = process.env.PIVOT_EXPLORER_JS
    || path.join(__dirname, '..', '..', 'app', 'webroot', 'js', 'pivot-explorer.js');
const SRC = fs.readFileSync(MODULE_PATH, 'utf8');

/* ─────────────────────────── DOM stub ─────────────────────────── */

function textNode(t) {
    return { tagName: '#text', _text: String(t), children: [],
             get textContent() { return this._text; } };
}

function makeEl(tag) {
    return {
        tagName: String(tag).toUpperCase(),
        className: '', type: '', placeholder: '', autocomplete: '', value: '',
        children: [], style: {}, attrs: {}, _html: '', _listeners: {},
        appendChild(c) { this.children.push(c); return c; },
        removeChild(c) { this.children = this.children.filter(x => x !== c); return c; },
        setAttribute(k, v) { this.attrs[k] = String(v); },
        getAttribute(k) { return k in this.attrs ? this.attrs[k] : null; },
        addEventListener(t, f) { (this._listeners[t] = this._listeners[t] || []).push(f); },
        removeEventListener() {},
        querySelector() { return null; },
        focus() {},
        classList: {
            _s: new Set(),
            add(c) { this._s.add(c); }, remove(c) { this._s.delete(c); },
            contains(c) { return this._s.has(c); },
        },
        get textContent() { return this.children.map(c => c.textContent).join(''); },
        set textContent(v) { this.children = [textNode(v)]; },
        get innerHTML() { return this._html; },
        set innerHTML(v) { this._html = String(v); if (v === '') this.children = []; },
    };
}

/** Depth-first walk, collecting elements whose className contains `cls`. */
function findByClass(el, cls, out) {
    out = out || [];
    if (el && typeof el.className === 'string' && el.className.split(/\s+/).indexOf(cls) !== -1) {
        out.push(el);
    }
    (el && el.children || []).forEach(c => findByClass(c, cls, out));
    return out;
}

/* ───────────────────────── the driver ─────────────────────────── */

/**
 * Boot the module against `payload` and return what it built.
 * Resolves to { nodes, edges, panel, tray, trayGroups, trayEmptyHtml, errors }.
 */
function buildGraph(payload, options) {
    options = options || {};
    const errors = [];
    let constructed = null;

    const card = makeEl('div');
    card.dataset = {
        peEventId: '1',
        peBaseurl: options.baseurl !== undefined ? options.baseurl : '/misp',
        peCanEdit: options.canEdit === false ? '0' : '1',
        peLibMissing: 'lib missing',
        peLoadFailed: 'load failed',
    };
    const pane = makeEl('div');
    pane.classList.add('active');

    const byId = {
        'pe-card': card,
        'pe-stage': makeEl('div'),
        'pivot-explorer-loader': makeEl('div'),
        'pivot-explorer-graph': makeEl('div'),
        'tab-pivot-explorer': pane,
    };

    const sandbox = {
        document: {
            readyState: 'complete',
            getElementById: id => (id in byId ? byId[id] : null),
            createElement: makeEl,
            createTextNode: textNode,
            addEventListener() {},
            removeEventListener() {},
            body: makeEl('body'),
        },
        window: {
            Pivotick: function Pivotick(container, data, opts) {
                constructed = { data, opts };
            },
        },
        Image: function () { return { src: '' }; },
        fetch: () => Promise.resolve({
            ok: true, status: 200, json: () => Promise.resolve(payload),
        }),
        console: { log() {}, error: (...a) => errors.push(a.map(String).join(' ')) },
        Promise, JSON, Object, String, Number, Array, Math, RegExp, Error,
        encodeURIComponent, setTimeout,
    };
    sandbox.globalThis = sandbox;

    vm.createContext(sandbox);
    vm.runInContext(SRC, sandbox, { filename: 'pivot-explorer.js' });

    // The build happens in a promise chain off fetch(); let it settle.
    return new Promise(res => setTimeout(res, 0)).then(() => {
        if (!constructed) {
            throw new Error('Pivotick was never constructed. errors=' + JSON.stringify(errors));
        }
        const panelDef = constructed.opts.UI.extraPanels && constructed.opts.UI.extraPanels[0];
        let panel = null, tray = [], trayGroups = [], trayEmptyHtml = '';
        if (panelDef) {
            panel = panelDef.render();
            tray = findByClass(panel, 'pe-chip').map(chip => ({
                label: chip.children[0] ? chip.children[0].textContent : '',
                meta: chip.children[1] ? chip.children[1].textContent : '',
                kind: chip.className.indexOf('pe-chip-object') !== -1 ? 'object' : 'attribute',
                draggable: chip.getAttribute('draggable'),
            }));
            trayGroups = findByClass(panel, 'pe-group-label').map(g => g.textContent);
            const list = findByClass(panel, 'pe-tray-list')[0];
            trayEmptyHtml = list ? list.innerHTML : '';
        }
        return {
            nodes: constructed.data.nodes,
            edges: constructed.data.edges,
            opts: constructed.opts,
            panel, tray, trayGroups, trayEmptyHtml, errors,
        };
    });
}

/* ─────────────────────── fixture builders ─────────────────────── */

const ev = parts => ({ Event: Object.assign({ id: '1', Attribute: [], Object: [] }, parts) });

const attr = o => Object.assign({
    type: 'ip-dst', category: 'Network activity', value: 'v', to_ids: false, comment: '',
}, o);

const obj = o => Object.assign({
    name: 'file', 'meta-category': 'file', Attribute: [], ObjectReference: [],
}, o);

const ref = o => Object.assign({ referenced_type: '1', relationship_type: 'related-to' }, o);

/* ──────────────────────── assertions ──────────────────────────── */

let passed = 0, failed = 0;
const failures = [];

function eq(label, actual, expected) {
    const a = JSON.stringify(actual), e = JSON.stringify(expected);
    if (a === e) { passed++; return; }
    failed++; failures.push(label);
    console.log('  FAIL  ' + label + '\n          expected ' + e + '\n          actual   ' + a);
}

function ok(label, cond, detail) {
    if (cond) { passed++; return; }
    failed++; failures.push(label);
    console.log('  FAIL  ' + label + (detail !== undefined ? '\n          ' + detail : ''));
}

const ids = nodes => nodes.map(n => n.id).sort();
const edgeKeys = edges => edges.map(e => e.from + '->' + e.to + ':' + e.data.label).sort();
const byId = (nodes, id) => nodes.filter(n => n.id === id)[0];

/* ───────────────────────────── tests ──────────────────────────── */

const TESTS = [];
const test = (name, fn) => TESTS.push({ name, fn });

test('connectivity: reference source and target are on the canvas, an isolated object is not', async () => {
    const g = await buildGraph(ev({ Object: [
        obj({ uuid: 'A', ObjectReference: [ref({ referenced_uuid: 'B' })] }),
        obj({ uuid: 'B' }),
        obj({ uuid: 'C' }),
    ] }));
    eq('nodes', ids(g.nodes), ['obj:A', 'obj:B']);
    eq('edges', edgeKeys(g.edges), ['obj:A->obj:B:related-to']);
    eq('the isolated object is offered in the tray', g.tray.map(t => t.label), ['file']);
    eq('no console errors', g.errors, []);
});

test('an object counts as connected when a reference points at one of its child attributes', async () => {
    const g = await buildGraph(ev({ Object: [
        obj({ uuid: 'A', ObjectReference: [ref({ referenced_uuid: 'd1', referenced_type: '0' })] }),
        obj({ uuid: 'D', Attribute: [attr({ uuid: 'd1', value: 'child' })] }),
    ] }));
    eq('both objects present', ids(g.nodes), ['obj:A', 'obj:D']);
    eq('edge targets the attribute, not its owning object',
       edgeKeys(g.edges), ['obj:A->attr:d1:related-to']);
    eq('tray is empty', g.tray, []);
});

test('event-level attributes appear only when a reference points at them', async () => {
    const g = await buildGraph(ev({
        Attribute: [attr({ uuid: 'e1', value: 'seen' }), attr({ uuid: 'e2', value: 'unseen' })],
        Object: [obj({ uuid: 'A', ObjectReference: [ref({ referenced_uuid: 'e1', referenced_type: '0' })] })],
    }));
    eq('only the referenced one is a node', ids(g.nodes), ['attr:e1', 'obj:A']);
    eq('the unreferenced one is in the tray', g.tray.map(t => t.label), ['unseen']);
});

test('soft-deleted records are tombstones, in all three encodings', async () => {
    const g = await buildGraph(ev({ Object: [
        // deleted:true on the reference -> no edge, and B is not pulled in
        obj({ uuid: 'A', ObjectReference: [ref({ referenced_uuid: 'B', deleted: true })] }),
        obj({ uuid: 'B' }),
        // deleted:1 on the object itself -> absent even though it is referenced
        obj({ uuid: 'X', deleted: 1, ObjectReference: [ref({ referenced_uuid: 'Y' })] }),
        // deleted:'1' on a child attribute -> not nested
        obj({ uuid: 'Y', Attribute: [attr({ uuid: 'y1', deleted: '1' }), attr({ uuid: 'y2' })],
              ObjectReference: [ref({ referenced_uuid: 'B' })] }),
    ] }));
    ok('A is absent — its only reference is deleted', !byId(g.nodes, 'obj:A'), ids(g.nodes));
    ok('the deleted object X is absent', !byId(g.nodes, 'obj:X'));
    ok('Y is present via its live reference', !!byId(g.nodes, 'obj:Y'));
    eq('only the live edge survives', edgeKeys(g.edges), ['obj:Y->obj:B:related-to']);
    const y = byId(g.nodes, 'obj:Y');
    eq('the deleted child attribute is not nested', y.children.map(c => c.id), ['attr:y2']);
});

test('object attributes are nested as children, with no containment edges', async () => {
    const g = await buildGraph(ev({ Object: [
        obj({ uuid: 'A', Attribute: [attr({ uuid: 'a1' }), attr({ uuid: 'a2' })],
              ObjectReference: [ref({ referenced_uuid: 'B' })] }),
        obj({ uuid: 'B' }),
    ] }));
    eq('children are not top-level nodes', ids(g.nodes), ['obj:A', 'obj:B']);
    eq('both children nested', byId(g.nodes, 'obj:A').children.map(c => c.id), ['attr:a1', 'attr:a2']);
    eq('containment produces no edge — only the reference does',
       edgeKeys(g.edges), ['obj:A->obj:B:related-to']);
});

test('edges dedupe on from/to/label, and dangling references are dropped', async () => {
    const g = await buildGraph(ev({ Object: [
        obj({ uuid: 'A', ObjectReference: [
            ref({ referenced_uuid: 'B' }),
            ref({ referenced_uuid: 'B' }),                              // exact duplicate
            ref({ referenced_uuid: 'B', relationship_type: 'includes' }), // different label
            ref({ referenced_uuid: 'nope' }),                            // dangling
        ] }),
        obj({ uuid: 'B' }),
    ] }));
    eq('duplicate collapsed, distinct label kept, dangling dropped',
       edgeKeys(g.edges), ['obj:A->obj:B:includes', 'obj:A->obj:B:related-to']);
});

test('a missing relationship_type falls back to related-to', async () => {
    const g = await buildGraph(ev({ Object: [
        obj({ uuid: 'A', ObjectReference: [ref({ referenced_uuid: 'B', relationship_type: '' })] }),
        obj({ uuid: 'B' }),
    ] }));
    eq('label defaulted', edgeKeys(g.edges), ['obj:A->obj:B:related-to']);
});

test('image attachments carry image + imageUrl; other attachments do not', async () => {
    const g = await buildGraph(ev({
        Attribute: [
            attr({ uuid: 'img', type: 'attachment', value: 'shot.PNG' }),
            attr({ uuid: 'doc', type: 'attachment', value: 'report.pdf' }),
        ],
        Object: [obj({ uuid: 'A', ObjectReference: [
            ref({ referenced_uuid: 'img', referenced_type: '0' }),
            ref({ referenced_uuid: 'doc', referenced_type: '0' }),
        ] })],
    }), { baseurl: '/misp' });
    const img = byId(g.nodes, 'attr:img').data;
    const doc = byId(g.nodes, 'attr:doc').data;
    eq('image flagged (extension match is case-insensitive)', img.image, true);
    eq('thumbnail URL is the ACL-checked viewPicture route',
       img.imageUrl, '/misp/attributes/viewPicture/img/webp');
    ok('non-image attachment has no image key', !('image' in doc), JSON.stringify(doc));
    ok('non-image attachment has no imageUrl key', !('imageUrl' in doc));
});

test('null fields are dropped from node data (pivotick indexes every value and calls .length)', async () => {
    const g = await buildGraph(ev({
        Attribute: [attr({ uuid: 'e1', object_relation: null, comment: null, category: 'Other' })],
        Object: [obj({ uuid: 'A', ObjectReference: [ref({ referenced_uuid: 'e1', referenced_type: '0' })] })],
    }));
    const d = byId(g.nodes, 'attr:e1').data;
    ok('object_relation key absent, not null', !('object_relation' in d), JSON.stringify(d));
    ok('comment key absent, not null', !('comment' in d));
    ok('a present value survives', d.category === 'Other');
});

test('labels truncate at 42 characters', async () => {
    const long = 'x'.repeat(80);
    const g = await buildGraph(ev({
        Attribute: [attr({ uuid: 'e1', value: long })],
        Object: [obj({ uuid: 'A', ObjectReference: [ref({ referenced_uuid: 'e1', referenced_type: '0' })] })],
    }));
    const d = byId(g.nodes, 'attr:e1').data;
    eq('label length', d.label.length, 42);
    ok('ellipsis appended', d.label.slice(-1) === '…', d.label);
    eq('the untruncated value is preserved in data', d.value, long);
});

test('INVARIANT: every live element is either on the canvas or in the tray, never both', async () => {
    const payload = ev({
        Attribute: [
            attr({ uuid: 'e1', value: 'referenced' }),
            attr({ uuid: 'e2', value: 'loose-1' }),
            attr({ uuid: 'e3', value: 'loose-2' }),
            attr({ uuid: 'e4', value: 'gone', deleted: true }),
        ],
        Object: [
            obj({ uuid: 'A', ObjectReference: [
                ref({ referenced_uuid: 'e1', referenced_type: '0' }),
                ref({ referenced_uuid: 'B' }),
            ] }),
            obj({ uuid: 'B' }),
            obj({ uuid: 'C', name: 'url' }),
            obj({ uuid: 'D', name: 'domain' }),
        ],
    });
    const g = await buildGraph(payload);

    const canvas = new Set(g.nodes.map(n => n.id.replace(/^(obj|attr):/, '')));
    // Tray chips carry the label, so map fixture labels back to uuids.
    const trayLabels = new Set(g.tray.map(t => t.label));

    eq('canvas holds the referenced attribute and the two linked objects',
       [...canvas].sort(), ['A', 'B', 'e1']);
    eq('tray holds exactly the live leftovers',
       [...trayLabels].sort(), ['domain', 'loose-1', 'loose-2', 'url']);
    ok('no element is in both places',
       ![...trayLabels].some(l => ['referenced'].indexOf(l) !== -1));
    ok('the deleted attribute appears in neither', !canvas.has('e4') && !trayLabels.has('gone'));
    eq('tray groups the leftovers', g.trayGroups.sort(), ['Network activity', 'Objects']);
    eq('chips are draggable', [...new Set(g.tray.map(t => t.draggable))], ['true']);
});

test('an event with nothing in it builds an empty graph and says so in the tray', async () => {
    const g = await buildGraph(ev({}));
    eq('no nodes', g.nodes, []);
    eq('no edges', g.edges, []);
    ok('tray states the empty case', g.trayEmptyHtml.indexOf('Nothing unlinked.') !== -1,
       g.trayEmptyHtml);
    eq('no console errors', g.errors, []);
});

test('a read-only viewer gets no editor tray at all', async () => {
    const g = await buildGraph(ev({ Object: [
        obj({ uuid: 'A', ObjectReference: [ref({ referenced_uuid: 'B' })] }),
        obj({ uuid: 'B' }),
        obj({ uuid: 'C' }),
    ] }), { canEdit: false });
    eq('graph still builds', ids(g.nodes), ['obj:A', 'obj:B']);
    ok('no extraPanels handed to pivotick', !g.opts.UI.extraPanels);
    eq('no tray', g.tray, []);
});

test('edges are tagged with the kind that created them (D1 dimension 1)', async () => {
    const g = await buildGraph(ev({ Object: [
        obj({ uuid: 'A', ObjectReference: [
            ref({ referenced_uuid: 'B' }),
            ref({ referenced_uuid: 'B', relationship_type: 'includes' }),
        ] }),
        obj({ uuid: 'B' }),
    ] }));
    eq('every edge carries object-reference',
       [...new Set(g.edges.map(e => e.data.kind))], ['object-reference']);
    eq('the label still carries relationship_type',
       g.edges.map(e => e.data.label).sort(), ['includes', 'related-to']);
});

test('the edge-kind dimension is declared for pivotick', async () => {
    const g = await buildGraph(ev({ Object: [
        obj({ uuid: 'A', ObjectReference: [ref({ referenced_uuid: 'B' })] }),
        obj({ uuid: 'B' }),
    ] }));
    const r = g.opts.render;

    ok('edgeTypeAccessor declared', typeof r.edgeTypeAccessor === 'function');
    eq('it reads .kind off the edge data',
       r.edgeTypeAccessor({ getData: () => ({ kind: 'object-reference' }) }), 'object-reference');
    eq('it tolerates an edge with no getData', r.edgeTypeAccessor({}), undefined);
    eq('it tolerates an edge whose data is null',
       r.edgeTypeAccessor({ getData: () => null }), undefined);

    eq('object-reference is styled in D1 blue',
       r.edgeStyleMap['object-reference'], { strokeColor: '#428bca' });
    eq('only the one implemented kind is styled so far',
       Object.keys(r.edgeStyleMap), ['object-reference']);

    const facets = g.opts.UI.filter.edgeFacets;
    eq('one edge facet — the layer switch', facets.length, 1);
    eq('it is the kind facet', facets[0],
       { key: 'kind', label: 'Relationship', type: 'multiselect' });
});

test('INVARIANT: every kind the builder emits resolves to a styled kind', async () => {
    // Closes the loop between the tag and the style map: a typo on either side
    // silently drops edges back to the default stroke. Must keep holding as
    // tasks 3, 5 and 5b add their own kinds.
    const g = await buildGraph(ev({
        Attribute: [attr({ uuid: 'e1' })],
        Object: [
            obj({ uuid: 'A', ObjectReference: [
                ref({ referenced_uuid: 'B' }),
                ref({ referenced_uuid: 'e1', referenced_type: '0' }),
            ] }),
            obj({ uuid: 'B', Attribute: [attr({ uuid: 'b1' })] }),
        ],
    }));
    const styled = Object.keys(g.opts.render.edgeStyleMap);
    const accessor = g.opts.render.edgeTypeAccessor;

    ok('there are edges to check', g.edges.length === 2, String(g.edges.length));
    g.edges.forEach(e => {
        const resolved = accessor({ getData: () => e.data });
        ok('kind ' + JSON.stringify(resolved) + ' for ' + e.from + '->' + e.to + ' is styled',
           styled.indexOf(resolved) !== -1, 'styled kinds: ' + JSON.stringify(styled));
    });
});

/* ───────────────────────────── runner ─────────────────────────── */

(async () => {
    for (const t of TESTS) {
        console.log('\n' + t.name);
        try {
            await t.fn();
        } catch (e) {
            failed++;
            failures.push(t.name + ' (threw)');
            console.log('  THREW ' + (e && e.stack || e));
        }
    }
    console.log('\n' + '─'.repeat(66));
    console.log((failed ? 'FAILED' : 'OK') + ' — ' + passed + ' passed, ' + failed + ' failed');
    if (failed) { console.log('\nFailing:\n  ' + failures.join('\n  ')); }
    process.exit(failed ? 1 : 0);
})();
