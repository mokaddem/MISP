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
// The resolution statement (#pe-resolution) is read back as `g.resolution`.
// It is more than chrome here: since task 3c draws every live object, "which
// level put this object on the canvas" is otherwise unobservable, and the
// statement is what separates a seeded L1 spine from an L2 fallback.
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
    const fetchLog = [];
    let constructed = null;

    const card = makeEl('div');
    card.dataset = {
        peEventId: '1',
        peBaseurl: options.baseurl !== undefined ? options.baseurl : '/misp',
        peCanEdit: options.canEdit === false ? '0' : '1',
        peCanAnalyst: options.canAnalyst ? '1' : '0',
        peOrgUuid: options.orgUuid || '',
        peSiteAdmin: options.siteAdmin ? '1' : '0',
        peLibMissing: 'lib missing',
        peLoadFailed: 'load failed',
    };
    const pane = makeEl('div');
    pane.classList.add('active');

    const byId = {
        'pe-card': card,
        'pe-stage': makeEl('div'),
        'pe-header': makeEl('div'),
        'pe-identity': makeEl('div'),
        'pe-resolution': makeEl('div'),
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
            // Just enough graph for the element pivot to ask what is drawn.
            Pivotick: function Pivotick(container, data, opts) {
                constructed = { data, opts };
                const drawn = {};
                (function index(nodes) {
                    (nodes || []).forEach(n => { drawn[n.id] = n; index(n.children); });
                })(data.nodes);
                this.getNode = id => drawn[id] || undefined;
                this.nodeList = data.nodes.slice();
                this.getNodes = () => this.nodeList;
                this.listeners = {};
                this.on = (evt, f) => { (this.listeners[evt] = this.listeners[evt] || []).push(f); };
                this.pivots = { invalidated: [], invalidate(id) { this.invalidated.push(id); } };
                this.selected = [];
                this.selectElement = n => { this.selected.push(n); };
                this.UIManager = { sidebar: { shown: 0, showSidebar() { this.shown++; } } };
                const notices = this.notices = [];
                this.notifier = {};
                ['success', 'warning', 'error', 'info'].forEach(level => {
                    this.notifier[level] = (title, msg) => notices.push({ level, title, msg });
                });
                constructed.graph = this;
            },
            location: { href: '' },
        },
        Image: function () { return { src: '' }; },
        fetch: (url, init) => {
            fetchLog.push({ url: String(url), init: init || {} });
            const route = (options.routes || []).find(r => r[0].test(String(url)));
            const body = route ? (typeof route[1] === 'function' ? route[1](init) : route[1]) : payload;
            // A route answering { __status: 500 } stands for a failed request.
            const status = body && body.__status ? body.__status : 200;
            return Promise.resolve({ ok: status < 400, status, json: () => Promise.resolve(body) });
        },
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
        // What the element pivot offers at open, unnarrowed: everything the
        // canvas does not hold. Still called the tray, which it replaced.
        const elements = constructed.opts.pivots.find(p => p.id === 'event-elements');
        const tray = elements.fetch([], {}, {}).nodes.map(n => ({
            id: n.id, label: n.data.label, kind: n.data.type,
        }));
        return {
            nodes: constructed.data.nodes,
            edges: constructed.data.edges,
            opts: constructed.opts,
            graph: constructed.graph,
            // Read live: the correlation counts rewrite the line after construction.
            get resolution() { return byId['pe-resolution'].textContent; },
            get resolutionShown() { return byId['pe-resolution'].style.display === ''; },
            header: byId,
            win: sandbox.window,
            fetchLog,
            tray, errors,
        };
    });
}

/* ─────────────────────── fixture builders ─────────────────────── */

// Real payloads always carry the event's uuid; L0 needs it, and an analyst
// relationship targeting an Event resolves against it.
const ev = parts => ({
    Event: Object.assign({ id: '1', uuid: 'EV-SELF', Attribute: [], Object: [] }, parts),
});

const attr = o => Object.assign({
    type: 'ip-dst', category: 'Network activity', value: 'v', to_ids: false, comment: '',
}, o);

const obj = o => Object.assign({
    name: 'file', 'meta-category': 'file', Attribute: [], ObjectReference: [],
}, o);

const ref = o => Object.assign({ referenced_type: '1', relationship_type: 'related-to' }, o);

// One `RelatedEvent` entry, in the shape Event::getRelatedEvents() rearranges
// it into: the event under an 'Event' key, with Org/Orgc folded inside.
const relEvent = o => ({ Event: Object.assign({ id: '99', uuid: 'R', info: '', date: '' }, o) });

// Enough relationship-less objects to blow the 1,500-node budget, so the seed
// stops at L0+L1 and the L1 rule becomes observable on its own. No test seam —
// this is the same arithmetic a 28,410-object event triggers.
const fillers = n => {
    const out = [];
    for (let i = 0; i < n; i++) out.push(obj({ uuid: 'fill-' + i, name: 'filler' }));
    return out;
};

// An outbound analyst relationship, as it arrives on an Attribute or Object.
const arel = o => Object.assign({
    relationship_type: 'analysed-with', authors: 'alice', orgc_uuid: 'org-1',
    related_object_type: 'Object',
}, o);

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
// Every node the canvas holds, nested children included — what the budget counts.
const countAll = nodes =>
    (nodes || []).reduce((n, x) => n + 1 + countAll(x.children), 0);
const trayLabels = g => g.tray.map(t => t.label).filter(l => l !== 'filler');
const edgeKeys = edges => edges.map(e => e.from + '->' + e.to + ':' + e.data.label).sort();
const byId = (nodes, id) => nodes.filter(n => n.id === id)[0];

/* ───────────────────────────── tests ──────────────────────────── */

const TESTS = [];
const test = (name, fn) => TESTS.push({ name, fn });

test('connectivity: a reference seeds both ends into L1; an untouched object arrives via L2', async () => {
    const g = await buildGraph(ev({ Object: [
        obj({ uuid: 'A', ObjectReference: [ref({ referenced_uuid: 'B' })] }),
        obj({ uuid: 'B' }),
        obj({ uuid: 'C' }),
    ] }));
    eq('nodes', ids(g.nodes), ['obj:A', 'obj:B', 'obj:C']);
    eq('edges', edgeKeys(g.edges), ['obj:A->obj:B:related-to']);
    eq('the untouched object draws no edge — its containment is all it says',
       g.edges.filter(e => e.from === 'obj:C' || e.to === 'obj:C'), []);
    eq('so nothing is left for the tray', g.tray, []);
    eq('and the statement names both levels', g.resolution, 'Seeded L1+L2 · 3 nodes');
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
    eq('the live objects, and only those', ids(g.nodes), ['obj:A', 'obj:B', 'obj:Y']);
    ok('the deleted object X is absent', !byId(g.nodes, 'obj:X'));
    ok('Y is present via its live reference', !!byId(g.nodes, 'obj:Y'));
    eq('A drew nothing — its only reference is a tombstone, so L2 is what put it there',
       g.edges.filter(e => e.from === 'obj:A' || e.to === 'obj:A'), []);
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

    eq('canvas holds the L1 spine and the L2 clusters',
       [...canvas].sort(), ['A', 'B', 'C', 'D', 'e1']);
    eq('tray holds exactly the live event-level leftovers — L2 never adds those',
       [...trayLabels].sort(), ['loose-1', 'loose-2']);
    ok('no element is in both places',
       ![...trayLabels].some(l => ['referenced', 'url', 'domain'].indexOf(l) !== -1));
    ok('the deleted attribute appears in neither', !canvas.has('e4') && !trayLabels.has('gone'));
});

test('an event with nothing in it builds an empty graph and offers nothing', async () => {
    const g = await buildGraph(ev({}));
    eq('no nodes', g.nodes, []);
    eq('no edges', g.edges, []);
    eq('the element pivot counts nothing',
       g.opts.pivots.find(p => p.id === 'event-elements').summarize([], {}).total, 0);
    eq('the statement stays silent — nothing was seeded and nothing was skipped',
       g.resolution, '');
    ok('and the line stays hidden', !g.resolutionShown);
    eq('no console errors', g.errors, []);
});

test('a read-only viewer still gets the element pivot — putting an element on the canvas is not an edit', async () => {
    const g = await buildGraph(ev({ Object: [
        obj({ uuid: 'A', ObjectReference: [ref({ referenced_uuid: 'B' })] }),
        obj({ uuid: 'B' }),
        obj({ uuid: 'C' }),
    ] }), { canEdit: false });
    eq('graph still builds', ids(g.nodes), ['obj:A', 'obj:B', 'obj:C']);
    ok('the element pivot is declared', g.opts.pivots.some(p => p.id === 'event-elements'));
    eq('and has nothing to offer here', g.tray, []);
    eq('the statement is not editor chrome — a read-only viewer gets it too',
       g.resolution, 'Seeded L1+L2 · 3 nodes');
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
    eq('the implemented kinds are styled',
       Object.keys(r.edgeStyleMap),
       ['object-reference', 'analyst-relationship', 'event-correlation', 'correlation']);
    eq('correlations are dashed grey (D1 palette)',
       r.edgeStyleMap['correlation'], { strokeColor: '#888', dashed: true });
    eq('analyst relationships are dashed orange (D1 palette)',
       r.edgeStyleMap['analyst-relationship'], { strokeColor: '#f39a1f', dashed: true });
    eq('event correlations are dashed green, matching the event nodes they join',
       r.edgeStyleMap['event-correlation'], { strokeColor: '#6fbe80', dashed: true });

    const facets = g.opts.UI.filter.edgeFacets;
    eq('two edge facets — the layer switch, then what an edge asserts', facets.length, 2);
    eq('the first is the kind facet', facets[0],
       { key: 'kind', label: 'Relationship', type: 'multiselect' });
});

test('5c: relationship_type is the second edge dimension, a substring box', async () => {
    const g = await buildGraph(ev({ Object: [
        obj({ uuid: 'A', ObjectReference: [ref({ referenced_uuid: 'B', relationship_type: 'child-of' })],
              Relationship: [arel({ object_uuid: 'A', related_object_uuid: 'B',
                                    relationship_type: 'seen-with' })] }),
        obj({ uuid: 'B', ObjectReference: [ref({ referenced_uuid: 'A', relationship_type: '' })] }),
    ] }));
    const facet = g.opts.UI.filter.edgeFacets[1];
    eq('declared as a text box — not a 143-row list',
       [facet.key, facet.label, facet.type], ['relationship_type', 'Asserts', 'text']);
    const hit = (type, q) => facet.predicate({ getData: () => ({ relationship_type: type }) }, q);
    eq('a substring anywhere in the type', [hit('dropped-by', '-by'), hit('child-of', 'ild'), hit('child-of', '-by')],
       [true, true, false]);
    eq('blind to case either way', [hit('Characterized_By', 'by'), hit('dropped-by', 'BY')], [true, true]);
    eq('an edge asserting nothing never matches',
       [facet.predicate({ getData: () => ({ kind: 'correlation', label: '' }) }, 'x'),
        facet.predicate({ getData: () => null }, 'x'), facet.predicate({}, 'x')], [false, false, false]);
    eq('every authored edge carries the type it asserts, the default included',
       g.edges.map(e => e.data.kind + ':' + e.data.relationship_type).sort(),
       ['analyst-relationship:seen-with', 'object-reference:child-of', 'object-reference:related-to']);
    eq('and it is the label drawn', g.edges.map(e => e.data.label === e.data.relationship_type),
       [true, true, true]);
});

test('5c: derived edges assert nothing, so carry no relationship_type', async () => {
    const g = await buildGraph(ev({
        Attribute: [attr({ uuid: 'e1' })],
        RelatedEvent: [{ Event: { id: '77', uuid: 'R1', info: 'other', Orgc: { name: 'o' } } }],
    }));
    const derived = g.edges.filter(e => e.data.kind === 'event-correlation');
    ok('there are event-correlation edges', derived.length > 0, String(derived.length));
    derived.forEach(e => ok('no relationship_type on ' + e.from + '->' + e.to,
                            !('relationship_type' in e.data), JSON.stringify(e.data)));
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

test('an analyst relationship becomes an edge of its own kind', async () => {
    const g = await buildGraph(ev({ Object: [
        obj({ uuid: 'A', Relationship: [
            arel({ object_uuid: 'A', related_object_uuid: 'B' }),
        ] }),
        obj({ uuid: 'B' }),
    ] }));
    eq('both endpoints seeded with no object reference in sight',
       ids(g.nodes), ['obj:A', 'obj:B']);
    eq('one analyst edge', edgeKeys(g.edges), ['obj:A->obj:B:analysed-with']);
    eq('tagged with its kind', g.edges[0].data.kind, 'analyst-relationship');
    eq('provenance carried on the edge',
       [g.edges[0].data.authors, g.edges[0].data.orgc], ['alice', 'org-1']);
});

test('D5 prime: an analyst relationship alone is enough to seed an element', async () => {
    // Before task 3 neither of these was on the canvas: A has no object
    // reference, and e1 is an event-level attribute nothing references.
    const g = await buildGraph(ev({
        Attribute: [attr({ uuid: 'e1', value: 'linked-by-analyst' }),
                    attr({ uuid: 'e2', value: 'truly-loose' })],
        Object: [
            obj({ uuid: 'A', Relationship: [
                arel({ object_uuid: 'A', related_object_uuid: 'e1',
                       related_object_type: 'Attribute' }),
            ] }),
            obj({ uuid: 'Z' }),
        ],
    }));
    eq('the analyst-linked pair is seeded, and Z rides in on L2',
       ids(g.nodes), ['attr:e1', 'obj:A', 'obj:Z']);
    eq('edge points at the attribute', edgeKeys(g.edges), ['obj:A->attr:e1:analysed-with']);
    eq('the untouched attribute stays in the tray — L2 never adds a bare attribute',
       g.tray.map(t => t.label).sort(), ['truly-loose']);
});

test('a relationship on a child attribute pulls its owning object in', async () => {
    const g = await buildGraph(ev({ Object: [
        obj({ uuid: 'OWNER', Attribute: [
            attr({ uuid: 'c1', Relationship: [
                arel({ object_uuid: 'c1', related_object_uuid: 'T' }),
            ] }),
        ] }),
        obj({ uuid: 'T' }),
    ] }));
    eq('owner seeded via its child', ids(g.nodes), ['obj:OWNER', 'obj:T']);
    eq('the child is nested, not top-level',
       byId(g.nodes, 'obj:OWNER').children.map(c => c.id), ['attr:c1']);
    eq('the edge starts at the child attribute',
       edgeKeys(g.edges), ['attr:c1->obj:T:analysed-with']);
});

test('CLOSES THE TASK-2 GAP: kind is part of edge identity', async () => {
    // Same pair, same label, two different kinds — both edges belong. This is
    // what makes `kind` in the dedupe key observable.
    const g = await buildGraph(ev({ Object: [
        obj({ uuid: 'A',
              ObjectReference: [ref({ referenced_uuid: 'B', relationship_type: 'includes' })],
              Relationship: [arel({ object_uuid: 'A', related_object_uuid: 'B',
                                    relationship_type: 'includes' })] }),
        obj({ uuid: 'B' }),
    ] }));
    eq('two edges, not one', g.edges.length, 2);
    eq('one of each kind',
       g.edges.map(e => e.data.kind).sort(), ['analyst-relationship', 'object-reference']);
    eq('both carry the same label',
       [...new Set(g.edges.map(e => e.data.label))], ['includes']);
});

test('relationships the canvas cannot draw are skipped, not half-drawn', async () => {
    const g = await buildGraph(ev({ Object: [
        obj({ uuid: 'A', Relationship: [
            // legal AnalystData targets with no node on this canvas
            arel({ object_uuid: 'A', related_object_uuid: 'r1', related_object_type: 'EventReport' }),
            arel({ object_uuid: 'A', related_object_uuid: 'g1', related_object_type: 'GalaxyCluster' }),
            arel({ object_uuid: 'A', related_object_uuid: 'o1', related_object_type: 'Organisation' }),
            // Event resolves since task 3b, but only to this event or one of its
            // correlated neighbours — 'ev1' is neither
            arel({ object_uuid: 'A', related_object_uuid: 'ev1', related_object_type: 'Event' }),
            // an Object in some *other* event
            arel({ object_uuid: 'A', related_object_uuid: 'elsewhere' }),
            // self-reference — the model rejects these, we guard anyway
            arel({ object_uuid: 'A', related_object_uuid: 'A' }),
            // the one that does resolve
            arel({ object_uuid: 'A', related_object_uuid: 'B' }),
        ] }),
        obj({ uuid: 'B' }),
    ] }));
    eq('only the resolvable relationship drew an edge',
       edgeKeys(g.edges), ['obj:A->obj:B:analysed-with']);
    eq('no phantom nodes for unresolvable targets', ids(g.nodes), ['obj:A', 'obj:B']);
    eq('no console errors', g.errors, []);
});

test('an object whose only reference dangles is not seeded by it', async () => {
    // Task 3 fixed the source being seeded before the target was checked. Since
    // task 3c the dangler is still drawn — but by L2, as a bare cluster — so
    // what proves the seeding rule is the absent edge, and the companion test
    // below, where L2 does not fit and the dangler disappears entirely.
    const g = await buildGraph(ev({ Object: [
        obj({ uuid: 'A', name: 'dangler', ObjectReference: [
            ref({ referenced_uuid: 'not-in-this-event' }),
        ] }),
        obj({ uuid: 'B', name: 'linked', ObjectReference: [ref({ referenced_uuid: 'C' })] }),
        obj({ uuid: 'C', name: 'target' }),
    ] }));
    eq('all three are drawn, the dangler among them', ids(g.nodes),
       ['obj:A', 'obj:B', 'obj:C']);
    eq('no edge was invented', edgeKeys(g.edges), ['obj:B->obj:C:related-to']);
    eq('nothing is left in the tray', g.tray, []);
});

test('...and once L2 does not fit, the dangler is gone entirely', async () => {
    // The L1 rule in isolation. This is the assertion task 3 shipped, preserved
    // at the resolution where it is still visible.
    const g = await buildGraph(ev({ Object: [
        obj({ uuid: 'A', name: 'dangler', ObjectReference: [
            ref({ referenced_uuid: 'not-in-this-event' }),
        ] }),
        obj({ uuid: 'B', name: 'linked', ObjectReference: [ref({ referenced_uuid: 'C' })] }),
        obj({ uuid: 'C', name: 'target' }),
    ].concat(fillers(1501)) }));
    eq('only the real pair survives', ids(g.nodes), ['obj:B', 'obj:C']);
    eq('and it still drew exactly one edge', edgeKeys(g.edges), ['obj:B->obj:C:related-to']);
    eq('the dangler is offered in the tray instead',
       trayLabels(g), ['dangler']);
});

test('a reference to a deleted element does not seed its source', async () => {
    const g = await buildGraph(ev({ Object: [
        obj({ uuid: 'A', name: 'points-at-tombstone',
              ObjectReference: [ref({ referenced_uuid: 'D' })] }),
        obj({ uuid: 'D', deleted: true }),
    ] }));
    eq('the tombstone is not drawn', ids(g.nodes), ['obj:A']);
    eq('and it seeded no edge', g.edges, []);
    eq('the statement proves L2 put the source there, not the reference',
       g.resolution, 'Seeded L2 · 1 node');
});

test('a deleted link between two on-canvas elements still draws nothing', async () => {
    // The realistic soft-delete: a user removes one link of several. Both ends
    // stay on the canvas for other reasons, so the seeding pass cannot save us
    // here — the drawing pass has to honour the tombstone itself.
    const g = await buildGraph(ev({ Object: [
        obj({ uuid: 'A', ObjectReference: [
            ref({ referenced_uuid: 'B', relationship_type: 'includes' }),
            ref({ referenced_uuid: 'B', relationship_type: 'was-linked', deleted: true }),
        ], Relationship: [
            arel({ object_uuid: 'A', related_object_uuid: 'B',
                   relationship_type: 'was-asserted', deleted: 1 }),
        ] }),
        obj({ uuid: 'B' }),
    ] }));
    eq('both ends are on the canvas', ids(g.nodes), ['obj:A', 'obj:B']);
    eq('only the live reference drew an edge',
       edgeKeys(g.edges), ['obj:A->obj:B:includes']);
});

test('a relationship pointing at a tombstoned element seeds neither end', async () => {
    // The deleted attribute is never drawn, so seeding its partner would leave
    // that partner alone on the canvas with nothing to connect to.
    const g = await buildGraph(ev({
        Attribute: [attr({ uuid: 'e1', value: 'gone', deleted: true })],
        Object: [obj({ uuid: 'A', name: 'points-at-gone', Relationship: [
            arel({ object_uuid: 'A', related_object_uuid: 'e1',
                   related_object_type: 'Attribute' }),
        ] })],
    }));
    eq('the tombstoned attribute is not drawn', ids(g.nodes), ['obj:A']);
    eq('no edges', g.edges, []);
    eq('the statement proves L2 put the source there, not the relationship',
       g.resolution, 'Seeded L2 · 1 node · 1 relationship not drawable');
});

test('the target TYPE gates resolution, not just whether the uuid exists', async () => {
    // 'B' is a real Object here. A relationship naming uuid B but declaring a
    // non-canvas target type must still be skipped — otherwise the type check is
    // only working by accident, rescued by uuids that happen not to exist.
    const g = await buildGraph(ev({ Object: [
        obj({ uuid: 'A', Relationship: [
            arel({ object_uuid: 'A', related_object_uuid: 'B', related_object_type: 'EventReport' }),
            arel({ object_uuid: 'A', related_object_uuid: 'B', related_object_type: 'GalaxyCluster' }),
            arel({ object_uuid: 'A', related_object_uuid: 'B', related_object_type: 'Event' }),
        ] }),
        obj({ uuid: 'B' }),
    ] }));
    eq('no edges — every target type is off-canvas', g.edges, []);
    eq('both objects are on the canvas by containment alone', ids(g.nodes),
       ['obj:A', 'obj:B']);
    eq('nothing was seeded by them, and all three skips are reported',
       g.resolution, 'Seeded L2 · 2 nodes · 3 relationships not drawable');
});

test('an event-level attribute can be the source of a relationship', async () => {
    const g = await buildGraph(ev({
        Attribute: [attr({ uuid: 'e1', value: 'source-attr', Relationship: [
            arel({ object_uuid: 'e1', related_object_uuid: 'B' }),
        ] })],
        Object: [obj({ uuid: 'B' })],
    }));
    eq('both ends seeded', ids(g.nodes), ['attr:e1', 'obj:B']);
    eq('edge runs from the event-level attribute',
       edgeKeys(g.edges), ['attr:e1->obj:B:analysed-with']);
    eq('nothing left in the tray', g.tray, []);
});

test('tombstones apply to analyst relationships too', async () => {
    const g = await buildGraph(ev({ Object: [
        // a deleted relationship on a live object
        obj({ uuid: 'A', Relationship: [
            arel({ object_uuid: 'A', related_object_uuid: 'B', deleted: true }),
        ] }),
        obj({ uuid: 'B' }),
        // a live relationship on a deleted object
        obj({ uuid: 'X', deleted: 1, Relationship: [
            arel({ object_uuid: 'X', related_object_uuid: 'B' }),
        ] }),
        // a live relationship on a deleted child attribute
        obj({ uuid: 'Y', Attribute: [attr({ uuid: 'y1', deleted: '1', Relationship: [
            arel({ object_uuid: 'y1', related_object_uuid: 'B' }),
        ] })] }),
        // a deleted object whose child attribute is live and carries one: the
        // tombstoned owner takes the child's relationship with it
        obj({ uuid: 'W', deleted: true, Attribute: [attr({ uuid: 'w1', Relationship: [
            arel({ object_uuid: 'w1', related_object_uuid: 'B' }),
        ] })] }),
    ] }));
    eq('the live objects are drawn by L2; the tombstoned owners are not',
       ids(g.nodes), ['obj:A', 'obj:B', 'obj:Y']);
    eq('no edges', g.edges, []);
    eq('nothing was seeded by a tombstoned relationship',
       g.resolution, 'Seeded L2 · 3 nodes');
});

test('null provenance is dropped from edge data, not carried as null', async () => {
    const g = await buildGraph(ev({ Object: [
        obj({ uuid: 'A', Relationship: [
            arel({ object_uuid: 'A', related_object_uuid: 'B', authors: null, orgc_uuid: null }),
        ] }),
        obj({ uuid: 'B' }),
    ] }));
    const d = g.edges[0].data;
    ok('authors key absent', !('authors' in d), JSON.stringify(d));
    ok('orgc key absent', !('orgc' in d));
    eq('the kind and label survive', [d.kind, d.label], ['analyst-relationship', 'analysed-with']);
});

test('INVARIANT still holds with two kinds in play', async () => {
    const g = await buildGraph(ev({
        Attribute: [attr({ uuid: 'e1', value: 'analyst-linked' }),
                    attr({ uuid: 'e2', value: 'loose' })],
        Object: [
            obj({ uuid: 'A', ObjectReference: [ref({ referenced_uuid: 'B' })],
                  Relationship: [arel({ object_uuid: 'A', related_object_uuid: 'e1',
                                        related_object_type: 'Attribute' })] }),
            obj({ uuid: 'B' }),
            obj({ uuid: 'C', name: 'url' }),
        ],
    }));
    const canvas = new Set(g.nodes.map(n => n.id.replace(/^(obj|attr):/, '')));
    const tray = new Set(g.tray.map(t => t.label));
    eq('canvas', [...canvas].sort(), ['A', 'B', 'C', 'e1']);
    eq('tray holds only the event-level attribute no relationship touches',
       [...tray].sort(), ['loose']);

    const styled = Object.keys(g.opts.render.edgeStyleMap);
    const accessor = g.opts.render.edgeTypeAccessor;
    g.edges.forEach(e => {
        ok('kind ' + JSON.stringify(e.data.kind) + ' is styled',
           styled.indexOf(accessor({ getData: () => e.data })) !== -1);
    });
});

/* ─────────────────────── task 3b — L0 ─────────────────────────── */

test('L0: the event and one proxy per correlated event, joined by the aggregate', async () => {
    const g = await buildGraph(ev({ RelatedEvent: [
        relEvent({ id: '22', uuid: 'R1', info: 'campaign x', date: '2026-01-02',
                   Orgc: { name: 'CIRCL' } }),
        relEvent({ id: '23', uuid: 'R2' }),
    ] }));
    eq('the event, plus a proxy each', ids(g.nodes),
       ['event:EV-SELF', 'event:R1', 'event:R2']);
    eq('each proxy hangs off the event', edgeKeys(g.edges).sort(),
       ['event:EV-SELF->event:R1:', 'event:EV-SELF->event:R2:']);
    eq('every L0 edge carries the aggregate kind',
       [...new Set(g.edges.map(e => e.data.kind))], ['event-correlation']);
    eq('and asserts nothing — sharing a value is not a claim',
       [...new Set(g.edges.map(e => e.data.label))], ['']);
    ok('proxies are leaves, not expandable containers (PRD §4)',
       g.nodes.every(n => !n.children));
    eq('the statement says L0 and only L0', g.resolution, 'Seeded L0 · 3 nodes');
});

test('a correlated-event proxy is labelled by what an analyst recognises it by', async () => {
    const g = await buildGraph(ev({ RelatedEvent: [
        relEvent({ id: '22', uuid: 'R1', info: 'campaign x', date: '2026-01-02',
                   Orgc: { name: 'CIRCL' }, Org: { name: 'HOST' } }),
        relEvent({ id: '23', uuid: 'R2', info: '', date: '2026-01-03' }),
    ] }));
    const a = byId(g.nodes, 'event:R1').data;
    eq('type drives the green hexagon nodeStyleMap already registers', a.type, 'event');
    eq('label is the event info', a.label, 'campaign x');
    eq('description is date and creating org', a.description, '2026-01-02 · CIRCL');
    eq('it carries the id the navigation needs', a.event_id, '22');

    const b = byId(g.nodes, 'event:R2').data;
    eq('an event with no info falls back to its id', b.label, 'Event 23');
    eq('and its description to the date alone', b.description, '2026-01-03');
});

test('the event node is drawn only when something connects to it', async () => {
    // A bare hexagon would make L0 permanently non-empty and put D11's
    // "nothing to draw" message (task 4) out of reach.
    const g = await buildGraph(ev({ Object: [obj({ uuid: 'A' })] }));
    ok('no event node', !byId(g.nodes, 'event:EV-SELF'), ids(g.nodes));
    eq('so L0 contributed nothing', g.resolution, 'Seeded L2 · 1 node');
});

test('an analyst relationship can point at the event itself', async () => {
    const g = await buildGraph(ev({ Attribute: [attr({ uuid: 'e1', value: 'src',
        Relationship: [arel({ object_uuid: 'e1', related_object_uuid: 'EV-SELF',
                              related_object_type: 'Event' })] })] }));
    eq('the event node is drawn for the assertion to land on',
       ids(g.nodes), ['attr:e1', 'event:EV-SELF']);
    eq('edge', edgeKeys(g.edges), ['attr:e1->event:EV-SELF:analysed-with']);
    eq('with the analyst kind, not the correlation aggregate',
       g.edges[0].data.kind, 'analyst-relationship');
    eq('levels', g.resolution, 'Seeded L0+L1 · 2 nodes');
});

test('an analyst relationship can point at a correlated event', async () => {
    const g = await buildGraph(ev({
        RelatedEvent: [relEvent({ id: '22', uuid: 'R1' })],
        Attribute: [attr({ uuid: 'e1', Relationship: [
            arel({ object_uuid: 'e1', related_object_uuid: 'R1',
                   related_object_type: 'Event' }),
        ] })],
    }));
    eq('the proxy is a legal target', ids(g.nodes),
       ['attr:e1', 'event:EV-SELF', 'event:R1']);
    eq('both edges land, each with its own kind',
       g.edges.map(e => e.from + '->' + e.to + ':' + e.data.kind).sort(),
       ['attr:e1->event:R1:analyst-relationship',
        'event:EV-SELF->event:R1:event-correlation']);
});

test('RelatedEvent dedupes, and the event never proxies itself', async () => {
    // Extended events merge two RelatedEvent lists (Event.php:3788-3794).
    const g = await buildGraph(ev({ RelatedEvent: [
        relEvent({ uuid: 'R1' }), relEvent({ uuid: 'R1' }), relEvent({ uuid: 'EV-SELF' }),
    ] }));
    eq('one proxy, and no self-loop', ids(g.nodes), ['event:EV-SELF', 'event:R1']);
    eq('one edge', g.edges.length, 1);
    // addNode/addEdge dedupe by id, so a duplicate proxy is invisible in the
    // graph itself — it shows up only as a node the budget paid for and the
    // canvas never drew.
    eq('and the budget was charged for two nodes, not three',
       g.resolution, 'Seeded L0 · 2 nodes');
});

test('with no event uuid in the payload there is no L0 at all', async () => {
    // Proxies edge to the event node; without one they would be floating dots.
    const g = await buildGraph(ev({ uuid: null, RelatedEvent: [relEvent({ uuid: 'R1' })] }));
    eq('nothing drawn', g.nodes, []);
    eq('no edges', g.edges, []);
});

test('double-click on a proxy opens that event, and does nothing anywhere else', async () => {
    const g = await buildGraph(ev({ RelatedEvent: [relEvent({ id: '22', uuid: 'R1' })] }));
    const dbl = g.opts.callbacks.onNodeDbclick;
    ok('the callback is declared', typeof dbl === 'function');

    dbl({}, { getData: () => ({ type: 'event', event_id: '22' }) });
    eq('navigates to the neighbour', g.win.location.href, '/misp/events/view2/22');

    g.win.location.href = '';
    dbl({}, { getData: () => ({ type: 'event', event_id: '1' }) });
    eq('but not to the event we are already on', g.win.location.href, '');

    dbl({}, { getData: () => ({ type: 'object', uuid: 'A' }) });
    eq('and not for any other node type', g.win.location.href, '');

    dbl({}, { getData: () => null });
    eq('a node with no data is survivable', g.win.location.href, '');
});

/* ─────────────────────── task 3c — L2 ─────────────────────────── */

test('L2: a relationship-less object is a containment cluster — parent, children, no edge', async () => {
    const g = await buildGraph(ev({ Object: [
        obj({ uuid: 'C', name: 'file',
              Attribute: [attr({ uuid: 'c1' }), attr({ uuid: 'c2' })] }),
    ] }));
    eq('one top-level node', ids(g.nodes), ['obj:C']);
    eq('with its attributes nested inside it',
       byId(g.nodes, 'obj:C').children.map(c => c.id), ['attr:c1', 'attr:c2']);
    eq('and no edges — containment is the whole statement', g.edges, []);
    eq('the children count against the budget too', g.resolution, 'Seeded L2 · 3 nodes');
});

test('L2 never adds a bare event-level attribute (D10 governing principle)', async () => {
    // 80% of all attributes are event-level; seeding those means seeding the
    // whole event again, and a bare attribute conveys less than its table row.
    const g = await buildGraph(ev({
        Attribute: [attr({ uuid: 'e1', value: 'one' }), attr({ uuid: 'e2', value: 'two' })],
    }));
    eq('nothing on the canvas', g.nodes, []);
    eq('nothing to state', g.resolution, '');
    eq('both are in the tray', trayLabels(g).sort(), ['one', 'two']);
});

test('the budget is all-or-nothing: one node over and L2 is skipped whole', async () => {
    const at = await buildGraph(ev({ Object: fillers(1500) }));
    eq('exactly at the budget, L2 fits', at.resolution, 'Seeded L2 · 1500 nodes');
    eq('and every object is drawn', at.nodes.length, 1500);
    eq('so the tray is empty', at.tray, []);

    const over = await buildGraph(ev({ Object: fillers(1501) }));
    eq('one node over, and not a single one is drawn', over.nodes, []);
    eq('but the graph still says what it left out — never falls silent (§7)',
       over.resolution, 'L2 skipped (1501 objects not shown)');
    ok('and the line is shown', over.resolutionShown);
    eq('the skipped objects fall back to the tray (D4)', over.tray.length, 1501);
});

test('over budget, the seed falls back to the relationship spine', async () => {
    // Event 4116 in miniature: L2 does not fit, so L0+L1 carry the graph and
    // the statement carries the rest.
    const g = await buildGraph(ev({
        RelatedEvent: [relEvent({ uuid: 'R1' })],
        Object: [
            obj({ uuid: 'A', ObjectReference: [ref({ referenced_uuid: 'B' })] }),
            obj({ uuid: 'B' }),
        ].concat(fillers(1501)),
    }));
    eq('L0 and L1 survive', ids(g.nodes),
       ['event:EV-SELF', 'event:R1', 'obj:A', 'obj:B']);
    eq('with both their edges', g.edges.length, 2);
    eq('and the graph states exactly what it did and did not draw', g.resolution,
       'Seeded L0+L1 · 4 nodes · L2 skipped (1501 objects not shown)');
});

test('an object is counted at its true cost — children included — before L2 is judged', async () => {
    // 750 two-attribute objects is 2,250 nodes, not 750. Counting the parents
    // alone would let a 28,410-object event through the budget three times over.
    const heavy = [];
    for (let i = 0; i < 750; i++) {
        heavy.push(obj({ uuid: 'h' + i,
                         Attribute: [attr({ uuid: 'h' + i + 'a' }), attr({ uuid: 'h' + i + 'b' })] }));
    }
    const g = await buildGraph(ev({ Object: heavy }));
    eq('L2 is refused', g.nodes, []);
    eq('and says so by object count, not by node count', g.resolution,
       'L2 skipped (750 objects not shown)');
});

test('a deleted child does not cost the budget anything', async () => {
    // 1,500 objects with one tombstoned child each: 1,500 live nodes, not 3,000.
    const many = [];
    for (let i = 0; i < 1500; i++) {
        many.push(obj({ uuid: 'd' + i, Attribute: [attr({ uuid: 'd' + i + 'x', deleted: true })] }));
    }
    const g = await buildGraph(ev({ Object: many }));
    eq('it fits exactly', g.resolution, 'Seeded L2 · 1500 nodes');
    eq('and no tombstone was nested', countAll(g.nodes), 1500);
});

test('the statement counts every node the builder actually emitted', async () => {
    // Guards the seed\'s analytic arithmetic against the build: they are
    // computed by different code and must not drift.
    const g = await buildGraph(ev({
        RelatedEvent: [relEvent({ uuid: 'R1' }), relEvent({ uuid: 'R2' })],
        Attribute: [attr({ uuid: 'e1' }), attr({ uuid: 'e2', value: 'loose' })],
        Object: [
            obj({ uuid: 'A', Attribute: [attr({ uuid: 'a1' })],
                  ObjectReference: [ref({ referenced_uuid: 'e1', referenced_type: '0' })] }),
            obj({ uuid: 'B', Attribute: [attr({ uuid: 'b1' }), attr({ uuid: 'b2' })] }),
            obj({ uuid: 'C' }),
        ],
    }));
    const declared = Number(/· (\d+) nodes/.exec(g.resolution)[1]);
    eq('every level contributed', g.resolution.indexOf('Seeded L0+L1+L2') === 0, true);
    eq('the declared count matches the graph, nested children included',
       declared, countAll(g.nodes));
    eq('3 event nodes + 1 linked attribute + 3 objects + 3 nested children',
       declared, 10);
});

test('a skipped L2 leaves the tray as the only route to those objects', async () => {
    // The invariant, at the resolution where it actually bites: 1,501 objects
    // are in exactly one of the two places, and it is not the canvas.
    const g = await buildGraph(ev({
        Attribute: [attr({ uuid: 'e1', value: 'loose' })],
        Object: fillers(1501),
    }));
    eq('canvas empty', g.nodes, []);
    eq('tray holds the objects and the attribute', g.tray.length, 1502);
    eq('the attribute among them', trayLabels(g), ['loose']);
});

test('INVARIANT: every kind the builder emits resolves to a styled kind — all three', async () => {
    const g = await buildGraph(ev({
        RelatedEvent: [relEvent({ uuid: 'R1' })],
        Attribute: [attr({ uuid: 'e1' })],
        Object: [
            obj({ uuid: 'A',
                  ObjectReference: [ref({ referenced_uuid: 'e1', referenced_type: '0' })],
                  Relationship: [arel({ object_uuid: 'A', related_object_uuid: 'B' })] }),
            obj({ uuid: 'B' }),
        ],
    }));
    const styled = Object.keys(g.opts.render.edgeStyleMap);
    const accessor = g.opts.render.edgeTypeAccessor;

    eq('all three kinds are in play at once',
       [...new Set(g.edges.map(e => e.data.kind))].sort(),
       ['analyst-relationship', 'event-correlation', 'object-reference']);
    g.edges.forEach(e => {
        const resolved = accessor({ getData: () => e.data });
        ok('kind ' + JSON.stringify(resolved) + ' for ' + e.from + '->' + e.to + ' is styled',
           styled.indexOf(resolved) !== -1, 'styled kinds: ' + JSON.stringify(styled));
    });
});

/* ───────────────────── pivots (R1, R2) ───────────────────────── */

// A node as pivotick hands it to a pivot: only getData() is read.
const pnode = data => ({ getData: () => data });

// Counts and pairs as /events/correlationCounts and /events/correlatedAttributes
// shape them.
const COUNTS = {
    total: 3,
    attributes: { e1: 1, c1: 2 },
    objects: { A: 2 },
    events: { '7': 2, '8': 1 },
    limit: 5000,
};
const pair = (src, uuid, evId, evUuid) => ({
    source_uuid: src,
    Attribute: { id: '9' + uuid, uuid, type: 'ip-dst', category: 'Network activity', value: '10.0.0.' + uuid },
    Event: { id: evId, uuid: evUuid, info: 'Event ' + evId },
    Object: null,
});
const PAIRS = [pair('c1', 'x1', '7', 'R7'), pair('c1', 'x2', '7', 'R7'), pair('e1', 'x1', '8', 'R8')];

function pivotFixture() {
    return ev({
        RelatedEvent: [relEvent({ uuid: 'R7', id: '7' }), relEvent({ uuid: 'R8', id: '8' })],
        Attribute: [attr({ uuid: 'e1' })],
        Object: [obj({ uuid: 'A', Attribute: [attr({ uuid: 'c1' })] }), obj({ uuid: 'B' })],
    });
}

function withPivots(extraRoutes) {
    return buildGraph(pivotFixture(), {
        routes: (extraRoutes || []).concat([
            [/correlationCounts\/1\.json$/, COUNTS],
            [/correlatedAttributes\/1\.json$/, PAIRS],
        ]),
    }).then(g => new Promise(res => setTimeout(() => res(g), 0)));
}

const pivot = (g, id) => g.opts.pivots.find(p => p.id === id);

test('both pivots are declared, capped at the canvas budget, and savable by nobody', async () => {
    const g = await withPivots();
    eq('the two pivots, after the element pivot', g.opts.pivots.map(p => p.id),
       ['event-elements', 'correlations', 'related-event']);
    g.opts.pivots.filter(p => p.id !== 'event-elements').forEach(p => {
        eq(p.id + ' refuses above 1,500', p.maxCandidates, 1500);
        ok(p.id + ' has no save — correlations are derived', p.save === undefined);
    });
    ok('the counts were asked for once the graph existed',
       g.fetchLog.some(f => /\/misp\/events\/correlationCounts\/1\.json$/.test(f.url)));
});

test('the correlation pivot applies only where the counts say something correlates', async () => {
    const g = await withPivots();
    const p = pivot(g, 'correlations');
    const nodes = [
        pnode({ type: 'attribute', uuid: 'e1' }),
        pnode({ type: 'object', uuid: 'A' }),
        pnode({ type: 'object', uuid: 'B' }),
        pnode({ type: 'event', uuid: 'R7', event_id: '7' }),
    ];
    eq('it keeps the correlated attribute and object',
       p.appliesTo(nodes).map(n => n.getData().uuid), ['e1', 'A']);
    eq('summarize is the counts, summed over the origin',
       p.summarize(p.appliesTo(nodes)), { total: 3 });
});

test('before the counts arrive, no pivot applies', async () => {
    const g = await buildGraph(pivotFixture());     // counts route answers with the event payload
    eq('correlations', pivot(g, 'correlations').appliesTo([pnode({ type: 'attribute', uuid: 'e1' })]).length, 0);
    eq('related-event', pivot(g, 'related-event').appliesTo([pnode({ type: 'event', event_id: '7' })]).length, 0);
});

test('the related-event pivot applies to other events, never to this one', async () => {
    const g = await withPivots();
    const p = pivot(g, 'related-event');
    const nodes = [
        pnode({ type: 'event', uuid: 'R7', event_id: '7' }),
        pnode({ type: 'event', uuid: 'R9', event_id: '9' }),
        pnode({ type: 'event', uuid: 'SELF', event_id: '1' }),
    ];
    eq('only the counted foreign event', p.appliesTo(nodes).map(n => n.getData().event_id), ['7']);
    eq('its summary is that event\'s count', p.summarize(p.appliesTo(nodes)), { total: 2 });
});

test('an object origin fetches by its live attributes', async () => {
    let body = null;
    const g = await withPivots([[/correlatedAttributes/, init => { body = JSON.parse(init.body); return PAIRS; }]]);
    await pivot(g, 'correlations').fetch([pnode({ type: 'object', uuid: 'A' }), pnode({ type: 'attribute', uuid: 'e1' })], {}, {});
    eq('the object expands to its child attribute, the attribute stays itself',
       body, { attribute_uuids: ['c1', 'e1'] });
});

test('a related-event origin fetches by event id', async () => {
    let body = null;
    const g = await withPivots([[/correlatedAttributes/, init => { body = JSON.parse(init.body); return PAIRS; }]]);
    await pivot(g, 'related-event').fetch([pnode({ type: 'event', uuid: 'R7', event_id: '7' })], {}, {});
    eq('event ids', body, { event_ids: ['7'] });
});

test('correlated attributes land inside their event, joined to this event by correlation edges', async () => {
    const g = await withPivots();
    const r = await pivot(g, 'correlations').fetch([pnode({ type: 'attribute', uuid: 'e1' })], {}, {});
    const containers = r.nodes.filter(n => n.id.indexOf('event:') === 0);
    eq('one container per correlated event, keyed like the L0 proxy', containers.map(n => n.id), ['event:R7', 'event:R8']);
    eq('R7 holds both of its attributes once', containers[0].children.map(c => c.id), ['attr:x1', 'attr:x2']);
    eq('a correlated attribute is drawn like any attribute, and says which event it is in',
       [containers[0].children[0].data.type, containers[0].children[0].data.event_id], ['attribute', '7']);
    eq('the container is an event node', containers[0].data.type, 'event');
    eq('one correlation edge per pair, with a stable id',
       r.edges.map(e => [e.id, e.from, e.to, e.data.kind]),
       [['corr:c1:x1', 'attr:c1', 'attr:x1', 'correlation'],
        ['corr:c1:x2', 'attr:c1', 'attr:x2', 'correlation'],
        ['corr:e1:x1', 'attr:e1', 'attr:x1', 'correlation']]);
});

test('this event\'s side of a pair comes along when it is not on the canvas', async () => {
    const g = await withPivots();
    const r = await pivot(g, 'correlations').fetch([pnode({ type: 'attribute', uuid: 'e1' })], {}, {});
    const own = r.nodes.filter(n => n.id.indexOf('attr:') === 0).map(n => n.id).sort();
    eq('only the one not drawn: c1 is already on the canvas, inside object A', own, ['attr:e1']);
    eq('and drawn from the event payload', r.nodes.find(n => n.id === 'attr:e1').data.uuid, 'e1');
});

/* ──────────────── task 10: what a drawn edge can be ─────────────── */

const VOCAB = [
    { name: 'related-to' }, { name: 'drops' }, { name: "<script>alert('name')</script>" },
    { name: '' }, { name: null },
];

function editorFixture() {
    return ev({
        Attribute: [attr({ uuid: 'e1' })],
        Object: [
            obj({ uuid: 'A', Attribute: [attr({ uuid: 'c1' })] }),
            obj({ uuid: 'B' }),
        ],
    });
}

// Boot as an editor, recording every POST to objectReferences/add.
function withEditor(extraRoutes) {
    const posts = [];
    return buildGraph(editorFixture(), {
        routes: (extraRoutes || []).concat([
            [/objectRelationships\/index\.json$/, VOCAB],
            [/objectReferences\/add\//, init => { posts.push(JSON.parse(init.body)); return {}; }],
            [/correlationCounts/, { attributes: {}, objects: {}, events: {} }],
        ]),
    }).then(g => { g.posts = posts; return g; });
}

// An EdgeCreateContext whose form answers `answer`, recording what it was asked.
function edgeCtx(source, target, answer) {
    const ctx = {
        kind: 'edge', source, target, origin: 'drag', asked: null,
        promptData: opts => { ctx.asked = opts; return Promise.resolve(answer); },
    };
    return ctx;
}

const own = {
    objA: pnode({ type: 'object', uuid: 'A' }),
    objB: pnode({ type: 'object', uuid: 'B' }),
    attrE1: pnode({ type: 'attribute', uuid: 'e1' }),
    childC1: pnode({ type: 'attribute', uuid: 'c1' }),
};
const foreign = {
    attr: pnode({ type: 'attribute', uuid: 'x1', event_id: '7' }),
    obj: pnode({ type: 'object', uuid: 'X' }),
    event: pnode({ type: 'event', uuid: 'R7', event_id: '7' }),
};

test('only drawing a reference reaches MISP, so it is the only write tool an editor keeps', async () => {
    const g = await withEditor();
    eq('editor', g.opts.UI.editors, {
        nodeEditor: { enabled: false }, nodeCreator: { enabled: false },
        edgeEditor: { enabled: false }, edgeCreator: { enabled: true }, deletion: { enabled: true },
    });
    const r = await buildGraph(editorFixture(), { canEdit: false });
    ok('a read-only viewer gets none of them',
       Object.keys(r.opts.UI.editors).every(k => r.opts.UI.editors[k].enabled === false));
    ok('and no edge hooks', !r.opts.callbacks.isValidConnection && !r.opts.callbacks.onBeforeEdgeCreate);
});

test('a reference runs from one of this event\'s objects to one of its attributes or objects', async () => {
    const g = await withEditor();
    const valid = g.opts.callbacks.isValidConnection;
    ok('object → object', valid(own.objA, own.objB));
    ok('object → event-level attribute', valid(own.objA, own.attrE1));
    ok('object → another object\'s attribute', valid(own.objB, own.childC1));
    ok('an attribute cannot own a reference', !valid(own.attrE1, own.objA));
    ok('an event node cannot own one', !valid(foreign.event, own.objA));
    ok('nor be referenced', !valid(own.objA, foreign.event));
    ok('a correlated attribute from another event cannot be referenced', !valid(own.objA, foreign.attr));
    ok('an object that is not this event\'s cannot own one', !valid(foreign.obj, own.objB));
    ok('a note linking itself is not ours to judge', valid({}, own.objA));
});

test('an invalid pair is refused without asking anything', async () => {
    const g = await withEditor();
    const ctx = edgeCtx(own.attrE1, own.objA, { relationship_type: 'drops' });
    eq('refused', await g.opts.callbacks.onBeforeEdgeCreate(ctx), false);
    ok('no form', ctx.asked === null);
    eq('no POST', g.posts.length, 0);
});

test('the form offers the object_relationships vocabulary, sorted, defaulting to related-to', async () => {
    const g = await withEditor();
    const ctx = edgeCtx(own.objA, own.objB, null);
    await g.opts.callbacks.onBeforeEdgeCreate(ctx);
    const select = ctx.asked.fields[0];
    eq('select of names, blanks dropped', [select.key, select.type, select.options.map(o => o.value)],
       ['relationship_type', 'select', ["<script>alert('name')</script>", 'drops', 'related-to']]);
    eq('a name is a label, handed over as text', select.options[0].label, "<script>alert('name')</script>");
    eq('defaults to related-to', select.defaultValue, 'related-to');
    eq('plus a free-text field', [ctx.asked.fields[1].key, ctx.asked.fields[1].type], ['custom', 'text']);
    ok('the vocabulary was asked for once, and only when needed',
       g.fetchLog.filter(f => /objectRelationships/.test(f.url)).length === 1);
});

test('saving: the chosen type is POSTed and the edge lands persisted', async () => {
    const g = await withEditor();
    const d = await g.opts.callbacks.onBeforeEdgeCreate(edgeCtx(own.objA, own.attrE1, { relationship_type: 'drops' }));
    eq('POST body', g.posts, [{ ObjectReference: { referenced_uuid: 'e1', relationship_type: 'drops', comment: '' } }]);
    eq('decision', d, { accept: true, data: { kind: 'object-reference', label: 'drops', relationship_type: 'drops' }, persisted: true });
    ok('to the source object', g.fetchLog.some(f => /\/misp\/objectReferences\/add\/A\.json$/.test(f.url)));
});

test('a typed relationship wins over the list; an empty answer saves nothing', async () => {
    const g = await withEditor();
    await g.opts.callbacks.onBeforeEdgeCreate(edgeCtx(own.objA, own.objB, { relationship_type: 'drops', custom: '  beacons-to ' }));
    eq('custom, trimmed', g.posts[0].ObjectReference.relationship_type, 'beacons-to');
    eq('blank', await g.opts.callbacks.onBeforeEdgeCreate(edgeCtx(own.objA, own.objB, { relationship_type: '', custom: ' ' })), false);
    eq('cancelled', await g.opts.callbacks.onBeforeEdgeCreate(edgeCtx(own.objA, own.objB, null)), false);
    eq('only the first was POSTed', g.posts.length, 1);
});

test('a refused save leaves no edge', async () => {
    const g = await withEditor([[/objectReferences\/add\//, { __status: 403, message: 'no' }]]);
    eq('refused', await g.opts.callbacks.onBeforeEdgeCreate(edgeCtx(own.objA, own.objB, { relationship_type: 'drops' })), false);
});

test('without the vocabulary the form is one free-text field, and asks again next time', async () => {
    const g = await withEditor([[/objectRelationships\/index\.json$/, { __status: 500 }]]);
    const ctx = edgeCtx(own.objA, own.objB, { custom: 'drops' });
    eq('saved', (await g.opts.callbacks.onBeforeEdgeCreate(ctx)).accept, true);
    eq('one text field', ctx.asked.fields.map(f => [f.key, f.type]), [['custom', 'text']]);
    await g.opts.callbacks.onBeforeEdgeCreate(edgeCtx(own.objA, own.objB, null));
    eq('asked twice', g.fetchLog.filter(f => /objectRelationships/.test(f.url)).length, 2);
});

test('nothing carries a pending flag any more (D2)', async () => {
    const g = await withEditor();
    ok('no styleCb', g.opts.render.defaultNodeStyle.styleCb === undefined);
    ok('no node data carries pending', JSON.stringify(g.nodes).indexOf('pending') === -1);
});

/* ─────────────── task 11: physics adapts after the first frame ─────────────── */

test('physics is auto, seeded by the hand-tuned link distance', async () => {
    const g = await buildGraph(ev({ Attribute: [attr({ uuid: 'e1' })] }));
    eq('both set: a d3 value alone would pin physics to manual',
       g.opts.simulation, { physics: 'auto', d3LinkDistance: 200 });
});

/* ──────────── task 10c: deleting an edge deletes the reference ──────────── */

function deleteFixture() {
    return ev({
        Attribute: [attr({ uuid: 'e1', value: '1.2.3.4' })],
        Object: [
            obj({ uuid: 'A', name: 'file', ObjectReference: [
                ref({ uuid: 'R1', referenced_uuid: 'e1', referenced_type: '0', relationship_type: 'drops' }),
            ] }),
            obj({ uuid: 'B', name: 'domain-ip' }),
        ],
    });
}

// Boot as an editor; every delete POST is logged and answered by `answer`.
function withDeletes(answer) {
    const deletes = [];
    return buildGraph(deleteFixture(), {
        routes: [
            [/objectReferences\/delete\//, init => {
                deletes.push(init);
                return answer ? answer(deletes.length) : { saved: true };
            }],
            [/objectReferences\/add\//, { ObjectReference: { uuid: 'R-NEW', id: '9' } }],
            [/objectRelationships\/index\.json$/, VOCAB],
            [/correlationCounts/, { attributes: {}, objects: {}, events: {} }],
        ],
    }).then(g => { g.deletes = deletes; return g; });
}

const pedge = (id, from, to, data) => ({ id, from, to, getData: () => data });
const refEdge = (uuid, label) => pedge('ref:' + uuid,
    pnode({ type: 'object', uuid: 'A', label: 'file' }),
    pnode({ type: 'attribute', uuid: 'e1', label: '1.2.3.4' }),
    { kind: 'object-reference', label: label || 'drops', uuid });
const corrEdge = pedge('corr:1', pnode({ label: 'a' }), pnode({ label: 'b' }), { kind: 'correlation', label: '' });
const arelEdge = pedge('arel:1', pnode({ label: 'a' }), pnode({ label: 'b' }), { kind: 'analyst-relationship', label: 'x' });

// A DeleteContext whose confirm answers `yes`, recording what it was shown.
function delCtx(parts, yes) {
    const ctx = Object.assign({ nodes: [], edges: [], notes: [], cascadingEdges: [], origin: 'bulk-action' }, parts);
    ctx.asked = null;
    ctx.confirm = opts => { ctx.asked = opts; return Promise.resolve(yes); };
    return ctx;
}

test('a seeded reference edge knows its reference, so it can be found again in MISP', async () => {
    const g = await withDeletes();
    eq('uuid on the edge', g.edges.filter(e => e.data.kind === 'object-reference').map(e => e.data.uuid), ['R1']);
});

test('a drawn reference takes the uuid MISP gave it', async () => {
    const g = await withDeletes();
    const d = await g.opts.callbacks.onBeforeEdgeCreate(edgeCtx(own.objA, own.objB, { relationship_type: 'drops' }));
    eq('decision', d, { accept: true, data: { kind: 'object-reference', label: 'drops', relationship_type: 'drops', uuid: 'R-NEW' }, persisted: true });
});

test('deleting a node is refused, and says where it is done instead', async () => {
    const g = await withDeletes();
    const ctx = delCtx({ nodes: [own.objA], edges: [refEdge('R1')] }, true);
    eq('vetoed', await g.opts.callbacks.onBeforeDelete(ctx), false);
    ok('nothing asked', ctx.asked === null);
    eq('nothing deleted', g.deletes.length, 0);
    eq('one warning naming Hide', g.graph.notices.map(n => [n.level, /Hide/.test(n.msg)]), [['warning', true]]);
});

test('an edge is deleted in MISP only after a danger confirm saying it cannot be undone', async () => {
    const g = await withDeletes();
    const ctx = delCtx({ edges: [refEdge('R1')] }, true);
    const d = await g.opts.callbacks.onBeforeDelete(ctx);
    eq('confirm', [ctx.asked.variant, ctx.asked.confirmLabel, ctx.asked.title], ['danger', 'Delete in MISP', 'Delete relationship']);
    eq('body names the relationship and the consequence', ctx.asked.body,
       'This deletes the relationship in MISP: file → drops → 1.2.3.4. It cannot be undone from the graph.');
    ok('a soft delete, by uuid, as a POST',
       g.fetchLog.some(f => /\/misp\/objectReferences\/delete\/R1\.json$/.test(f.url) && f.init.method === 'POST'));
    eq('the history row is sealed', [d.accept, d.edges.map(e => e.id), d.persisted], [true, ['ref:R1'], true]);
});

test('cancelling the confirm deletes nothing', async () => {
    const g = await withDeletes();
    eq('vetoed', await g.opts.callbacks.onBeforeDelete(delCtx({ edges: [refEdge('R1')] }, false)), false);
    eq('no POST', g.deletes.length, 0);
});

test('only what MISP deleted leaves the canvas', async () => {
    const g = await withDeletes(n => (n === 2 ? { __status: 403, saved: false, errors: 'no' } : { saved: true }));
    const edges = [refEdge('R1'), refEdge('R2', 'uses'), refEdge('R3', 'hosts'), refEdge('R4', 'x')];
    const ctx = delCtx({ edges }, true);
    const d = await g.opts.callbacks.onBeforeDelete(ctx);
    ok('the body lists three and counts the rest', /hosts → 1\.2\.3\.4; and 1 more\. /.test(ctx.asked.body), ctx.asked.body);
    eq('narrowed to the three that went', d.edges.length, 3);
    eq('the refusal is reported in MISP\'s words',
       g.graph.notices.filter(n => n.level === 'error').map(n => n.msg), ['file → uses → 1.2.3.4: no']);
    const none = await withDeletes(() => ({ __status: 500 }));
    eq('all refused: nothing leaves', await none.opts.callbacks.onBeforeDelete(delCtx({ edges: [refEdge('R1')] }, true)), false);
});

test('derived and analyst edges are spared, not deleted, and the rest goes ahead', async () => {
    const g = await withDeletes();
    const d = await g.opts.callbacks.onBeforeDelete(delCtx({ edges: [corrEdge, refEdge('R1'), arelEdge] }, true));
    eq('only the reference', d.edges.map(e => e.id), ['ref:R1']);
    eq('one POST', g.deletes.length, 1);
    ok('told why', g.graph.notices.some(n => n.level === 'info'));
    const ctx = delCtx({ edges: [corrEdge], notes: [{ id: 'n1' }] }, true);
    eq('nothing deletable: the note still goes, the edge stays', await g.opts.callbacks.onBeforeDelete(ctx), { accept: true, edges: [] });
    ok('without a confirm', ctx.asked === null);
    eq('a reference with no uuid is spared too',
       (await g.opts.callbacks.onBeforeDelete(delCtx({ edges: [refEdge(undefined)] }, true))).edges, []);
});

test('notes alone are canvas-only and go straight through', async () => {
    const g = await withDeletes();
    const ctx = delCtx({ notes: [{ id: 'n1' }] }, true);
    eq('accepted', await g.opts.callbacks.onBeforeDelete(ctx), true);
    ok('no confirm, no POST', ctx.asked === null && g.deletes.length === 0);
});

test('a read-only viewer has no delete hook', async () => {
    const r = await buildGraph(deleteFixture(), { canEdit: false });
    ok('none', !r.opts.callbacks.onBeforeDelete);
});

/* ──────── task 10b: analyst relationships, drawn and deleted ──────── */

// Boot with the given rights, recording every analyst-data POST.
function withAnalyst(options, answers) {
    const adds = [], deletes = [];
    answers = answers || {};
    return buildGraph(editorFixture(), Object.assign({
        routes: [
            [/analystData\/add\/Relationship\//, init => {
                adds.push(JSON.parse(init.body));
                return answers.add || { Relationship: { uuid: 'AR-NEW', orgc_uuid: 'ORG-ME', authors: 'me@x' } };
            }],
            [/analystData\/delete\/Relationship\//, init => { deletes.push(init); return answers.del || { saved: true }; }],
            [/objectReferences\/add\//, { ObjectReference: { uuid: 'R-NEW' } }],
            [/objectReferences\/delete\//, { saved: true }],
            [/objectRelationships\/index\.json$/, VOCAB],
            [/correlationCounts/, { attributes: {}, objects: {}, events: {} }],
        ],
    }, options)).then(g => { g.adds = adds; g.deletes = deletes; return g; });
}
const analystOnly = { canEdit: false, canAnalyst: true, orgUuid: 'ORG-ME' };
const both = { canAnalyst: true, orgUuid: 'ORG-ME' };

const arEdge = (uuid, orgc) => pedge('ar:' + uuid,
    pnode({ type: 'attribute', uuid: 'e1', label: '1.2.3.4' }),
    pnode({ type: 'object', uuid: 'A', label: 'file' }),
    { kind: 'analyst-relationship', label: 'seen-with', uuid, orgc });

test('a seeded analyst relationship edge knows its relationship', async () => {
    const g = await buildGraph(ev({
        Attribute: [attr({ uuid: 'e1', Relationship: [arel({ uuid: 'AR1', related_object_uuid: 'A' })] })],
        Object: [obj({ uuid: 'A' })],
    }));
    eq('uuid and creator org on the edge',
       g.edges.filter(e => e.data.kind === 'analyst-relationship').map(e => [e.data.uuid, e.data.orgc]),
       [['AR1', 'org-1']]);
});

test('analyst rights alone give back the edge tool and delete, and the hooks', async () => {
    const g = await withAnalyst(analystOnly);
    eq('edge tool and delete only', Object.keys(g.opts.UI.editors).filter(k => g.opts.UI.editors[k].enabled),
       ['edgeCreator', 'deletion']);
    ok('hooks', !!g.opts.callbacks.isValidConnection && !!g.opts.callbacks.onBeforeEdgeCreate
       && !!g.opts.callbacks.onBeforeDelete);
});

test('an analyst relationship joins any two nameable elements, this event\'s or not', async () => {
    const g = await withAnalyst(analystOnly);
    const valid = g.opts.callbacks.isValidConnection;
    ok('attribute → object', valid(own.attrE1, own.objA));
    ok('to another event\'s attribute', valid(own.objA, foreign.attr));
    ok('from another event\'s attribute', valid(foreign.attr, own.objB));
    ok('from and to an event node', valid(foreign.event, own.attrE1) && valid(own.objA, foreign.event));
    ok('not to itself', !valid(own.objA, pnode({ type: 'object', uuid: 'A' })));
    ok('not to an element MISP cannot name', !valid(own.objA, pnode({ type: 'feed', uuid: 'F' })));
    ok('not without a uuid', !valid(own.objA, pnode({ type: 'attribute' })));
    const e = await withEditor();
    ok('an editor without analyst rights still draws references only', !e.opts.callbacks.isValidConnection(own.attrE1, own.objA));
});

test('one possible kind asks no link type; two ask, defaulting to the reference', async () => {
    const a = await withAnalyst(analystOnly);
    const one = edgeCtx(own.objA, own.objB, null);
    await a.opts.callbacks.onBeforeEdgeCreate(one);
    eq('analyst only: no link type', one.asked.fields.map(f => f.key), ['relationship_type', 'custom']);
    const b = await withAnalyst(both);
    const two = edgeCtx(own.objA, own.objB, null);
    await b.opts.callbacks.onBeforeEdgeCreate(two);
    const kind = two.asked.fields[0];
    eq('link type first', [kind.key, kind.type, kind.defaultValue], ['kind', 'select', 'object-reference']);
    eq('both offered, worded', kind.options, [
        { label: 'Object reference', value: 'object-reference' },
        { label: 'Analyst relationship', value: 'analyst-relationship' },
    ]);
    const c = await withAnalyst(both);
    const across = edgeCtx(own.objA, foreign.attr, null);
    await c.opts.callbacks.onBeforeEdgeCreate(across);
    ok('across events only the analyst kind remains, so no question', across.asked.fields[0].key !== 'kind');
});

test('saving an analyst relationship: addressed by MISP type, landing with what MISP returned', async () => {
    const g = await withAnalyst(analystOnly);
    const d = await g.opts.callbacks.onBeforeEdgeCreate(edgeCtx(own.attrE1, foreign.attr, { custom: 'seen-with' }));
    ok('to the source, typed', g.fetchLog.some(f => /\/misp\/analystData\/add\/Relationship\/e1\/Attribute\.json$/.test(f.url)));
    eq('body', g.adds, [{ Relationship: { related_object_uuid: 'x1', related_object_type: 'Attribute', relationship_type: 'seen-with' } }]);
    eq('decision', d, { accept: true,
        data: { kind: 'analyst-relationship', label: 'seen-with', relationship_type: 'seen-with',
                uuid: 'AR-NEW', orgc: 'ORG-ME', authors: 'me@x' },
        persisted: true });
    await g.opts.callbacks.onBeforeEdgeCreate(edgeCtx(foreign.event, own.objA, { custom: 'about' }));
    eq('an event source and an object target', g.adds[1].Relationship.related_object_type, 'Object');
    ok('event source path', g.fetchLog.some(f => /analystData\/add\/Relationship\/R7\/Event\.json$/.test(f.url)));
});

test('the chosen link type decides the write; an unoffered one falls back to the first', async () => {
    const g = await withAnalyst(both);
    await g.opts.callbacks.onBeforeEdgeCreate(edgeCtx(own.objA, own.objB, { kind: 'analyst-relationship', custom: 'x' }));
    eq('analyst chosen: analyst POST only', [g.adds.length, g.fetchLog.filter(f => /objectReferences\/add/.test(f.url)).length], [1, 0]);
    const d = await g.opts.callbacks.onBeforeEdgeCreate(edgeCtx(own.objA, own.objB, { kind: 'bogus', custom: 'y' }));
    eq('bogus: the reference', d.data.kind, 'object-reference');
});

test('a refused analyst save leaves no edge', async () => {
    const g = await withAnalyst(analystOnly, { add: { __status: 403, message: 'nope' } });
    eq('refused', await g.opts.callbacks.onBeforeEdgeCreate(edgeCtx(own.attrE1, own.objA, { custom: 'x' })), false);
    ok('reported', g.graph.notices.some(n => n.level === 'error' && n.msg === 'nope'));
});

test('an analyst relationship is deleted only where MISP would allow it', async () => {
    const g = await withAnalyst(analystOnly);
    const d = await g.opts.callbacks.onBeforeDelete(delCtx({ edges: [arEdge('AR1', 'ORG-ME'), arEdge('AR2', 'ORG-THEM')] }, true));
    eq('my org\'s goes, theirs stays', d.edges.map(e => e.id), ['ar:AR1']);
    ok('by uuid', g.fetchLog.some(f => /\/misp\/analystData\/delete\/Relationship\/AR1\.json$/.test(f.url)));
    eq('sealed', d.persisted, true);
    eq('an analyst-only user cannot delete a reference',
       await g.opts.callbacks.onBeforeDelete(delCtx({ edges: [refEdge('R1')] }, true)), { accept: true, edges: [] });
    const admin = await withAnalyst(Object.assign({ siteAdmin: true }, analystOnly));
    eq('a site admin deletes any org\'s',
       (await admin.opts.callbacks.onBeforeDelete(delCtx({ edges: [arEdge('AR2', 'ORG-THEM')] }, true))).edges.length, 1);
    const editor = await withAnalyst({ orgUuid: 'ORG-ME' });
    eq('an editor without analyst rights cannot delete one',
       await editor.opts.callbacks.onBeforeDelete(delCtx({ edges: [arEdge('AR1', 'ORG-ME')] }, true)), { accept: true, edges: [] });
    const noOrg = await withAnalyst({ canEdit: false, canAnalyst: true });
    eq('an unknown org matches nothing, not even a blank orgc',
       await noOrg.opts.callbacks.onBeforeDelete(delCtx({ edges: [arEdge('AR3', '')] }, true)), { accept: true, edges: [] });
});

test('a mixed selection deletes each kind at its own endpoint', async () => {
    const g = await withAnalyst(both);
    const d = await g.opts.callbacks.onBeforeDelete(delCtx({ edges: [refEdge('R1'), arEdge('AR1', 'ORG-ME')] }, true));
    eq('both', d.edges.map(e => e.id), ['ref:R1', 'ar:AR1']);
    ok('reference soft', g.fetchLog.some(f => /objectReferences\/delete\/R1\.json$/.test(f.url)));
    ok('relationship at analystData', g.fetchLog.some(f => /analystData\/delete\/Relationship\/AR1\.json$/.test(f.url)));
});

/* ─────────── task 9: the event's elements, as an origin-less pivot ─────────── */

const elementsOf = g => g.opts.pivots.find(p => p.id === 'event-elements');
const offered = (g, narrowing) => elementsOf(g).fetch([], narrowing || {}, {}).nodes.map(n => n.id).sort();

// Over the budget, so L2 is skipped and objects are on offer too.
function elementFixture() {
    return ev({
        Attribute: [
            attr({ uuid: 'a1', value: 'Evil.COM', type: 'domain', category: 'Network activity' }),
            attr({ uuid: 'a2', value: '10.0.0.1', type: 'ip-dst', category: 'Network activity', comment: 'the evil box' }),
            attr({ uuid: 'a3', value: 'deadbeef', type: 'md5', category: 'Payload delivery' }),
            attr({ uuid: 'a4', value: 'evil.com', deleted: true }),
        ],
        Object: fillers(1500).concat([
            obj({ uuid: 'O', name: 'domain-ip', 'meta-category': 'network', Attribute: [
                attr({ uuid: 'oc1', value: 'sub.evil.com', object_relation: 'domain' }),
                attr({ uuid: 'oc2', value: 'hidden', deleted: true }),
            ] }),
        ]),
    });
}

test('the element pivot needs no origin, refuses above the budget, and saves nothing', async () => {
    const g = await buildGraph(ev({}));
    const p = elementsOf(g);
    eq('shape', [p.origin, p.maxCandidates, p.save, typeof p.appliesTo], ['none', 1500, undefined, 'undefined']);
    eq('named for what it lists', p.label, 'Event elements');
});

test('it offers every live element the canvas lacks, objects whole', async () => {
    const g = await buildGraph(elementFixture());
    const ids = offered(g);
    ok('event-level attributes, deleted ones excluded',
       ['attr:a1', 'attr:a2', 'attr:a3'].every(i => ids.indexOf(i) !== -1) && ids.indexOf('attr:a4') === -1);
    ok('an object, not its attributes on their own',
       ids.indexOf('obj:O') !== -1 && ids.indexOf('attr:oc1') === -1);
    const o = elementsOf(g).fetch([], { q: 'domain-ip' }, {}).nodes[0];
    eq('the object arrives with its live children', o.children.map(c => c.id), ['attr:oc1']);
    eq('drawn like any object', o.data.type, 'object');
});

test('search is case-insensitive, and reaches values, types, categories and comments', async () => {
    const g = await buildGraph(elementFixture());
    eq('a value, either case', offered(g, { q: 'EVIL.com' }), ['attr:a1', 'obj:O']);
    eq('a comment', offered(g, { q: 'evil box' }), ['attr:a2']);
    eq('a type', offered(g, { q: 'md5' }), ['attr:a3']);
    eq('a category', offered(g, { q: 'payload' }), ['attr:a3']);
    eq('surrounding space ignored', offered(g, { q: '  deadbeef ' }), ['attr:a3']);
    eq('an object answers for its attributes', offered(g, { q: 'sub.evil' }), ['obj:O']);
    eq('but not for its deleted ones', offered(g, { q: 'hidden' }), []);
});

test('element and category narrow, and the summary counts what the fetch would bring', async () => {
    const g = await buildGraph(elementFixture());
    const p = elementsOf(g);
    eq('element', offered(g, { element: 'attribute' }), ['attr:a1', 'attr:a2', 'attr:a3']);
    eq('category', offered(g, { category: 'Network activity' }), ['attr:a1', 'attr:a2']);
    eq('together with search', offered(g, { q: 'evil', element: 'object' }), ['obj:O']);
    [{}, { q: 'evil' }, { element: 'object' }, { category: 'Payload delivery' }].forEach(n => {
        eq('summary = fetch for ' + JSON.stringify(n), p.summarize([], n).total, p.fetch([], n, {}).nodes.length);
    });
    eq('unnarrowed, the budget refuses it: 3 attributes and 1,501 objects', p.summarize([], {}).total, 1504);
});

test('the form: a search box, then element and category with their counts', async () => {
    const g = await buildGraph(elementFixture());
    const f = elementsOf(g).summarize([], {}).facets;
    eq('fields', f.map(x => [x.key, x.type]), [['q', 'text'], ['element', 'select'], ['category', 'select']]);
    eq('element counts', f[1].options, [
        { label: 'attribute', value: 'attribute', count: 3 },
        { label: 'object', value: 'object', count: 1501 },
    ]);
    eq('category counts, objects by meta-category',
       f[2].options.map(o => [o.value, o.count]),
       [['Network activity', 2], ['Payload delivery', 1], ['file', 1500], ['network', 1]]);
});

test('its summaries are dropped whenever a node comes or goes', async () => {
    const g = await buildGraph(ev({}));
    ['nodeAdd', 'nodeRemove'].forEach(evt => {
        eq(evt + ' is watched', (g.graph.listeners[evt] || []).length, 1);
        g.graph.listeners[evt][0]();
    });
    eq('each drops this pivot\'s cache', g.graph.pivots.invalidated, ['event-elements', 'event-elements']);
});

/* ─────────────── task 6: analyst-data badges and panel ─────────────── */

const note = o => Object.assign({ note_type_name: 'Note', note: 'n', authors: 'a@x', created: '2025-03-11 14:06:56', Orgc: { name: 'CIRCL' } }, o);
const opinion = o => Object.assign({ note_type_name: 'Opinion', opinion: '50', comment: '', authors: 'a@x', created: '2025-03-12 09:00:00', Orgc: { name: 'CIRCL' } }, o);

function analystFixture() {
    return ev({
        Note: [note({ note: 'about the event' })],
        RelatedEvent: [relEvent({ uuid: 'R7', id: '7' })],
        Object: [
            obj({ uuid: 'A', Opinion: [opinion({ opinion: '10', comment: 'Clearly a FP' })], Attribute: [
                attr({ uuid: 'c1', value: 'noted',
                       Note: [note({ note: '<b>first</b>', Opinion: [opinion({ opinion: '0' })] }), note({ note: 'second' })],
                       Opinion: [opinion({ opinion: '80' }), opinion({ opinion: '70' })],
                       Relationship: [arel({ related_object_uuid: 'c2', related_object_type: 'Attribute' })] }),
                attr({ uuid: 'c2', value: 'quiet' }),
            ] }),
            obj({ uuid: 'B', Opinion: [opinion({ opinion: '55' })] }),
            obj({ uuid: 'C', Note: [note()] }),
        ].concat(['40', '41', '60', '61'].map(v =>
            obj({ uuid: 'at' + v, Opinion: [opinion({ opinion: v })] }))),
    });
}

const nodeById = (nodes, id) => {
    for (const n of nodes || []) {
        if (n.id === id) return n;
        const c = nodeById(n.children, id);
        if (c) return c;
    }
    return null;
};
const badgesOf = (g, data) => g.opts.render.defaultNodeStyle.badges(pnode(data));

test('an element wears one badge: everything said about it, coloured by its own opinions', async () => {
    const g = await buildGraph(analystFixture());
    const c1 = nodeById(g.nodes, 'attr:c1').data;
    eq('two notes, two opinions, and an opinion on a note — not the relationship, which is an edge', c1.analyst_count, 5);
    eq('mean of its own opinions, 75: endorsed — the 0 on a note does not count', c1.analyst_mood, 'endorsed');
    eq('an object\'s 10 is disputed', nodeById(g.nodes, 'obj:A').data.analyst_mood, 'disputed');
    eq('55 is neutral', nodeById(g.nodes, 'obj:B').data.analyst_mood, 'neutral');
    eq('notes alone have no mood', nodeById(g.nodes, 'obj:C').data.analyst_mood, 'none');
    eq('the band edges: 40 | 41 … 60 | 61', ['40', '41', '60', '61'].map(v => nodeById(g.nodes, 'obj:at' + v).data.analyst_mood),
       ['disputed', 'neutral', 'neutral', 'endorsed']);
    eq('the event node carries its own', nodeById(g.nodes, 'event:EV-SELF').data.analyst_count, 1);
    const b = badgesOf(g, c1);
    eq('one badge, north-west, clear of the expand corners',
       b.map(x => [x.position, x.text, x.color]), [['nw', '5', '#6fbe80']]);
    eq('it says what it counts', b[0].title, '5 notes and opinions — endorsed');
    eq('colours', ['disputed', 'neutral', 'none'].map(m => badgesOf(g, { analyst_count: 1, analyst_mood: m })[0].color),
       ['#b94a48', '#999', '#999']);
    eq('singular', badgesOf(g, { analyst_count: 1, analyst_mood: 'none' })[0].title, '1 note or opinion');
});

test('an object does not add up its attributes\' badges', async () => {
    const g = await buildGraph(analystFixture());
    eq('A counts only its own opinion', nodeById(g.nodes, 'obj:A').data.analyst_count, 1);
});

test('nothing said, nothing changed: no fields, no badge', async () => {
    const g = await buildGraph(analystFixture());
    const c2 = nodeById(g.nodes, 'attr:c2').data;
    ok('no analyst fields at all', !('analyst_count' in c2) && !('analyst_mood' in c2));
    eq('an empty badge list', badgesOf(g, c2), []);
});

test('the panel exists only where there is analyst data to show', async () => {
    const quiet = await buildGraph(ev({ Object: [obj({ uuid: 'A' })] }));
    ok('an event without any gets no sidebar panel', !quiet.opts.UI.extraPanels);
    const deep = await buildGraph(ev({ Object: [obj({ uuid: 'A', Attribute: [attr({ uuid: 'c', Note: [note()] })] })] }));
    eq('one note on one object attribute is enough', deep.opts.UI.extraPanels.map(p => p.id), ['analyst-data']);
});

const panelText = el => (el.children || []).map(c => c.tagName === '#text' ? c._text : panelText(c)).join('|');

test('the panel lists what was said about the selected element, as text', async () => {
    const g = await buildGraph(analystFixture());
    const panel = g.opts.UI.extraPanels[0];
    ok('reactive by default, hidden with nothing selected', panel.reactive === undefined && !panel.alwaysVisible);
    const out = panel.render(pnode({ type: 'attribute', uuid: 'c1' }));
    const entries = findByClass(out, 'pe-analyst-entry');
    eq('five entries, replies after what they answer', entries.length, 5);
    const t = panelText(out);
    ok('a note\'s text, left as text', t.indexOf('<b>first</b>') !== -1, t);
    ok('an opinion names its band and value', t.indexOf('Agree (80/100)') !== -1 && t.indexOf('Strongly disagree (0/100)') !== -1, t);
    ok('who and when', t.indexOf('CIRCL · 2025-03-11') !== -1, t);
    ok('the reply is marked', t.indexOf('Opinion · reply') !== -1 || t.indexOf('Strongly disagree (0/100) · reply') !== -1, t);
    eq('and indented one step', entries.map(e => e.style.cssText.indexOf('0.9rem') !== -1), [false, true, false, false, false]);
});

test('the panel for anything else says so', async () => {
    const g = await buildGraph(analystFixture());
    const render = g.opts.UI.extraPanels[0].render;
    ok('an element nobody commented on', panelText(render(pnode({ type: 'attribute', uuid: 'c2' }))).indexOf('No notes or opinions') !== -1);
    ok('a correlated element from another event', panelText(render(pnode({ type: 'attribute', uuid: 'x1' }))).indexOf('No notes or opinions') !== -1);
    ok('a multi-selection', panelText(render([pnode({ uuid: 'c1' }), pnode({ uuid: 'A' })])).indexOf('single element') !== -1);
    ok('this event, through its node', findByClass(render(pnode({ type: 'event', uuid: 'EV-SELF' })), 'pe-analyst-entry').length === 1);
});

test('clicking the badge selects its node and opens the sidebar', async () => {
    const g = await buildGraph(analystFixture());
    const n = pnode(nodeById(g.nodes, 'attr:c1').data);
    badgesOf(g, n.getData())[0].onClick({}, n);
    eq('selected', g.graph.selected, [n]);
    eq('sidebar shown', g.graph.UIManager.sidebar.shown, 1);
});

/* ─────────────────── task 4: the empty canvas (D11) ─────────────────── */
// Pivotick shows and hides the card (UI.emptyState); MISP owns what it says.

const emptyCard = (g, initial) => g.opts.UI.emptyState.render({ initial: initial !== false, graph: g.graph });
const emptyText = (g, initial) => panelText(emptyCard(g, initial));
const emptyButton = (g, initial) => findByClass(emptyCard(g, initial), 'btn')[0];

test('an empty seed says why, and where the contents are', async () => {
    const g = await buildGraph(ev({ Attribute: [attr({ uuid: 'a1' }), attr({ uuid: 'a2' }), attr({ uuid: 'gone', deleted: true })] }));
    eq('nothing drawn', g.nodes, []);
    const t = emptyText(g);
    ok('it names what is missing, correlations included', t.indexOf('Nothing in this event is related yet') !== -1
       && t.indexOf('No object references, analyst relationships or correlations') !== -1, t);
    ok('and counts what is there, deleted ones aside', t.indexOf('Its 2 attributes are listed under Event elements') !== -1, t);
    eq('one action', emptyButton(g).textContent, 'Browse event elements');
});

test('the action opens the element pivot', async () => {
    const g = await buildGraph(ev({ Attribute: [attr({ uuid: 'a1' })] }));
    const calls = [];
    g.graph.UIManager.openPivotMode = (nodes, id) => calls.push([nodes, id]);
    emptyButton(g)._listeners.click[0]();
    eq('with no origin', calls, [[[], 'event-elements']]);
    ok('singular', emptyText(g).indexOf('Its 1 attribute is listed') !== -1, emptyText(g));
});

test('an event whose objects blow the budget says so too', async () => {
    const g = await buildGraph(ev({ Attribute: [attr({ uuid: 'a1' })], Object: fillers(1501) }));
    eq('nothing drawn', g.nodes, []);
    ok('both counted', emptyText(g).indexOf('Its 1 attribute and 1501 objects are listed') !== -1, emptyText(g));
    eq('the resolution line still states the skip', g.resolution, 'L2 skipped (1501 objects not shown)');
});

test('an event with no content at all offers nothing to browse', async () => {
    const g = await buildGraph(ev({}));
    ok('says it', emptyText(g).indexOf('This event has no attributes or objects to draw.') !== -1, emptyText(g));
    ok('no button', !emptyButton(g));
});

test('a canvas emptied by hand is not told that nothing is related', async () => {
    const g = await buildGraph(ev({ Object: [obj({ uuid: 'A' })] }));
    const t = emptyText(g, false);
    ok('it says the canvas is empty', t.indexOf('The canvas is empty') !== -1 && t.indexOf('related') === -1, t);
    ok('and still points at the elements', t.indexOf("The event's 1 object is listed under Event elements") !== -1, t);
    ok('with the same action', !!emptyButton(g, false));
});

test('the statement is text, never markup', async () => {
    const g = await buildGraph(ev({ Attribute: [attr({ uuid: 'a1' })] }));
    const card = emptyCard(g);
    ok('no innerHTML anywhere in the card', (function noHtml(el) {
        return !el._html && (el.children || []).every(noHtml);
    })(card));
});

/* ─────────── provenance, the facets, the header ─────────── */

const flat = nodes => (nodes || []).reduce((out, n) => out.concat([n], flat(n.children)), []);
const prov = n => [n.data.scope, n.data.event_id, n.data.event_uuid];

test('every seeded node says which event it belongs to', async () => {
    const g = await buildGraph(ev({
        RelatedEvent: [relEvent({ uuid: 'R7', id: '7' })],
        Attribute: [attr({ uuid: 'e1', event_id: '1' })],
        Object: [obj({ uuid: 'A', event_id: '1', Attribute: [attr({ uuid: 'c1', event_id: '1' })],
                       ObjectReference: [ref({ referenced_uuid: 'e1', referenced_type: '0' })] })],
    }));
    const all = flat(g.nodes);
    eq('this event', prov(byId(all, 'event:EV-SELF')), ['self', '1', 'EV-SELF']);
    eq('a correlated event', prov(byId(all, 'event:R7')), ['foreign', '7', 'R7']);
    eq('an object', prov(byId(all, 'obj:A')), ['self', '1', 'EV-SELF']);
    eq('its child attribute', prov(byId(all, 'attr:c1')), ['self', '1', 'EV-SELF']);
    eq('an event-level attribute', prov(byId(all, 'attr:e1')), ['self', '1', 'EV-SELF']);
    ok('every node carries a scope', all.every(n => n.data.scope === 'self' || n.data.scope === 'foreign'));
});

test('an element merged in from an extension event is foreign, known by id alone', async () => {
    const g = await buildGraph(ev({ Object: [
        obj({ uuid: 'A', event_id: '1', ObjectReference: [ref({ referenced_uuid: 'X' })] }),
        obj({ uuid: 'X', event_id: '42', Attribute: [attr({ uuid: 'x1', event_id: '42' })] }),
    ] }));
    const all = flat(g.nodes);
    eq('the extension object', prov(byId(all, 'obj:X')), ['foreign', '42', undefined]);
    eq('and its attribute', prov(byId(all, 'attr:x1')), ['foreign', '42', undefined]);
    eq('this event\'s own object stays self', prov(byId(all, 'obj:A')), ['self', '1', 'EV-SELF']);
});

test('a record without an event_id belongs to the event it came in', async () => {
    const g = await buildGraph(ev({ Object: [obj({ uuid: 'A' })] }));
    eq('self', prov(byId(g.nodes, 'obj:A')), ['self', '1', 'EV-SELF']);
});

test('pivot results carry provenance: correlated elements foreign, this event\'s side self', async () => {
    const g = await withPivots();
    const r = await pivot(g, 'correlations').fetch([pnode({ type: 'attribute', uuid: 'e1' })], {}, {});
    const all = flat(r.nodes);
    eq('a correlated attribute', prov(all.find(n => n.id === 'attr:x1')), ['foreign', '7', 'R7']);
    eq('its container', prov(all.find(n => n.id === 'event:R7')), ['foreign', '7', 'R7']);
    eq('this event\'s side brought along', prov(all.find(n => n.id === 'attr:e1')), ['self', '1', 'EV-SELF']);
});

test('elements put on the canvas from the element pivot are this event\'s', async () => {
    const g = await buildGraph(ev({
        Attribute: [attr({ uuid: 'e1' })],
        Object: [obj({ uuid: 'A', Attribute: [attr({ uuid: 'c1' })] })],
    }), { routes: [[/correlationCounts/, { attributes: {}, objects: {}, events: {} }]] });
    // Push A past the canvas so the pivot offers it too.
    g.graph.getNode = () => undefined;
    const r = pivot(g, 'event-elements').fetch([], {}, {});
    eq('each, children included', flat(r.nodes).map(n => n.id + '=' + n.data.scope).sort(),
       ['attr:c1=self', 'attr:e1=self', 'obj:A=self']);
});

test('the filter panel declares its facets, provenance first', async () => {
    const g = await buildGraph(ev({}));
    const facets = g.opts.UI.filter.facets;
    eq('the facet set', facets.map(f => [f.key, f.type]),
       [['scope', 'multiselect'], ['type', 'multiselect'], ['category', 'multiselect'],
        ['attr-type', 'multiselect'], ['name', 'multiselect'], ['to_ids', 'boolean'], ['value', 'regex']]);
    eq('provenance names both sides in words', facets[0].options,
       [{ label: 'This event', value: 'self' }, { label: 'Other events', value: 'foreign' }]);
    eq('provenance is labelled as such', facets[0].label, 'Provenance');
    eq('the edge facets are unchanged', g.opts.UI.filter.edgeFacets.map(f => f.key),
       ['kind', 'relationship_type']);
});

test('the legend keys elements and relationships, the latter on the layer facet', async () => {
    const g = await buildGraph(ev({}));
    const sections = g.opts.UI.legend.sections;
    eq('two sections', sections.map(s => s.title), ['Element', 'Relationship']);
    ok('Element is the nodeTypeAccessor dimension: no key, no entries',
       sections[0].key === undefined && sections[0].entries === undefined && sections[0].scope === undefined);
    eq('Relationship keys on edges by kind', [sections[1].scope, sections[1].key], ['edge', 'kind']);
    ok('the same key the layer facet declares',
       g.opts.UI.filter.edgeFacets.some(f => f.key === sections[1].key));
    ok('no provenance section: it has no colour to sample', !sections.some(s => s.key === 'scope'));
});

test('a facet\'s options are what the live graph holds, children included', async () => {
    const g = await buildGraph(ev({}));
    const cat = g.opts.UI.filter.facets.find(f => f.key === 'category');
    const graph = { getMutableNodes: () => [
        pnode({ category: 'Payload delivery' }), pnode({ category: 'Network activity' }),
        pnode({ category: 'Network activity' }), pnode({ type: 'object' }), pnode({ category: '' }),
    ] };
    eq('distinct, sorted, blanks dropped', cat.options(graph),
       [{ label: 'Network activity', value: 'Network activity' },
        { label: 'Payload delivery', value: 'Payload delivery' }]);
});

test('the header names the event the graph was seeded from', async () => {
    const g = await buildGraph(ev({ info: 'Phishing <b>wave</b>', date: '2026-09-01',
                                    Orgc: { name: 'CIRCL' }, Object: [obj({ uuid: 'A' })] }));
    eq('identity line', g.header['pe-identity'].textContent, 'Event 1 · Phishing <b>wave</b> · CIRCL · 2026-09-01');
    eq('the full line is its tooltip, since it truncates',
       g.header['pe-identity'].getAttribute('title'), 'Event 1 · Phishing <b>wave</b> · CIRCL · 2026-09-01');
    ok('written as text', !g.header['pe-identity']._html);
    ok('the header is shown', g.header['pe-header'].style.display === '');
    eq('the resolution line sits under it', g.resolution, 'Seeded L2 · 1 node');
});

test('the identity line leaves out what the event lacks', async () => {
    const g = await buildGraph(ev({ Object: [obj({ uuid: 'A' })] }));
    eq('id only', g.header['pe-identity'].textContent, 'Event 1');
});

test('the correlation total joins the resolution line once counted', async () => {
    const g = await withPivots();
    ok('it ends the statement', /· 3 correlations available$/.test(g.resolution), g.resolution);
});

test('no correlations, no clause', async () => {
    const g = await buildGraph(ev({ Object: [obj({ uuid: 'A' })] }),
        { routes: [[/correlationCounts/, { total: 0, attributes: {}, objects: {}, events: {} }]] });
    await new Promise(res => setTimeout(res, 0));
    eq('unchanged', g.resolution, 'Seeded L2 · 1 node');
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
