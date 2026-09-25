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
// Since task 3c draws every live object, "which level put this object on the
// canvas" shows only once L2 is over budget: the tests that care rebuild the
// event with `fillers(1501)` and check what L1 alone seeds.
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
// The node renderers the module requires beside Pivotick, loaded for real.
const NODES_SRC = fs.readFileSync(
    path.join(__dirname, '..', '..', 'app', 'webroot', 'js', 'misp-pivot-nodes.js'), 'utf8');

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
        insertBefore(c) { this.children.unshift(c); return c; },
        get firstChild() { return this.children[0] || null; },
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
        peAnalystSharing: options.analystSharing !== undefined ? options.analystSharing : '',
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
            head: makeEl('head'),
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
                // Live nodes, children included, carrying declared potential.
                const live = this.live = {};
                this.liveNode = (raw, sources) => {
                    const potentials = new Map();
                    const vouched = new Set(sources || ['seed']);
                    return live[raw.id] = {
                        id: raw.id, getData: () => raw.data,
                        setPotential(pivotId, count) {
                            if (count) potentials.set(pivotId, count); else potentials.delete(pivotId);
                        },
                        getPotentials: () => potentials,
                        hasSource: s => vouched.has(s),
                        dropSource: s => { vouched.delete(s); return vouched.size === 0; },
                    };
                };
                Object.keys(drawn).forEach(id => this.liveNode(drawn[id]));
                this.getMutableNode = id => live[id];
                this.getMutableNodes = () => Object.keys(live).map(id => live[id]);
                // Edges carry provenance like nodes: what the library's removeBySource reads.
                const liveEdges = this.liveEdges = data.edges.map(e => ({
                    id: e.from + '>' + e.to, getData: () => e.data, vouched: new Set(['seed']),
                    hasSource(s) { return this.vouched.has(s); },
                    dropSource(s) { this.vouched.delete(s); return this.vouched.size === 0; },
                }));
                this.getMutableEdges = () => liveEdges.slice();
                this.removedBy = [];
                // The library's rule: drop the claim, delete only what nothing else vouches for.
                this.removeBySource = source => {
                    this.removedBy.push(source);
                    const edges = liveEdges.filter(e => e.hasSource(source) && e.dropSource(source));
                    edges.forEach(e => liveEdges.splice(liveEdges.indexOf(e), 1));
                    const nodes = Object.keys(live).map(id => live[id])
                        .filter(n => n.hasSource(source) && n.dropSource(source));
                    nodes.forEach(n => delete live[n.id]);
                    return { nodes, edges };
                };
                this.renderer = { updates: 0, update() { this.updates++; } };
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
            opened: [],
            open(url, target, features) { this.opened.push([url, target, features]); },
            navigator: options.navigator || {},
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
    vm.runInContext(NODES_SRC, sandbox, { filename: 'misp-pivot-nodes.js' });
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
            win: sandbox.window,
            fetchLog,
            tray, errors,
        };
    });
}

/* ─────────────────────── fixture builders ─────────────────────── */

// Real payloads always carry the event's uuid; an analyst relationship
// targeting an Event resolves against it.
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

// Another event, as Relationship::getRelatedElement() attaches it: a
// fetchSimpleEvent row, with no Orgc.
const otherEvent = o => Object.assign({ id: '99', uuid: 'R', info: '', date: '' }, o);

// Enough relationship-less objects to blow the 1,500-node budget, so the seed
// stops at L1 and the L1 rule becomes observable on its own. No test seam —
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

// An analyst relationship pointing at another event, carrying that event's
// record the way the payload does.
const toEvent = (record, o) => arel(Object.assign({
    related_object_type: 'Event', related_object_uuid: record.uuid,
    related_object: { Event: record },
}, o));

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
    ok('non-image attachment is not flagged', doc.image == null, JSON.stringify(doc));
    ok('non-image attachment has no imageUrl', doc.imageUrl == null);
});

// Pivotick skips a null or undefined value everywhere it scans data (filter,
// table, Review tab, properties), so node data is passed as the payload has it.
test('a field the payload leaves null is carried as null, not stripped', async () => {
    const g = await buildGraph(ev({
        Attribute: [attr({ uuid: 'e1', object_relation: null, comment: null, category: 'Other' })],
        Object: [obj({ uuid: 'A', ObjectReference: [ref({ referenced_uuid: 'e1', referenced_type: '0' })] })],
    }));
    const d = byId(g.nodes, 'attr:e1').data;
    eq('object_relation and comment as the payload has them',
       [d.object_relation, d.comment], [null, null]);
    ok('a present value survives', d.category === 'Other');
});

test('a label is the whole value: the canvas shortens it, not the builder', async () => {
    const long = 'x'.repeat(80);
    const g = await buildGraph(ev({
        uuid: 'EV', info: 'i'.repeat(80),
        Attribute: [attr({ uuid: 'e1', value: long })],
        Object: [obj({ uuid: 'A', name: 'n'.repeat(80),
                       ObjectReference: [ref({ referenced_uuid: 'e1', referenced_type: '0' })] })],
        Relationship: [toEvent(otherEvent({ uuid: 'R', info: 'r'.repeat(80) }), { object_uuid: 'EV' })],
    }));
    eq('attribute', byId(g.nodes, 'attr:e1').data.label, long);
    eq('object', byId(g.nodes, 'obj:A').data.label, 'n'.repeat(80));
    eq('event', byId(g.nodes, 'event:EV').data.label, 'i'.repeat(80));
    eq('related event', byId(g.nodes, 'event:R').data.label, 'r'.repeat(80));
    ok('the canvas is left its default truncation',
       !('textTruncate' in g.opts.render.defaultNodeStyle));
});

test('no node can be expanded: the renderer draws no chevron and binds no Enter', async () => {
    const g = await buildGraph(ev({ Object: [obj({ uuid: 'A', Attribute: [attr({ uuid: 'a1' })] })] }));
    eq('expansion is off renderer-wide', g.opts.render.enableNodeExpansion, false);
    eq('the object still carries its attributes as children',
       byId(g.nodes, 'obj:A').children.map(c => c.id), ['attr:a1']);
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
       ['object-reference', 'analyst-relationship', 'correlation',
        'feed-correlation', 'feed-event', 'server-correlation', 'tag', 'cluster-relation']);
    eq('correlations are dashed grey (D1 palette)',
       r.edgeStyleMap['correlation'], { strokeColor: '#888', dashed: true });
    eq('analyst relationships are dashed orange (D1 palette)',
       r.edgeStyleMap['analyst-relationship'], { strokeColor: '#f39a1f', dashed: true });

    const facets = g.opts.UI.filter.edgeFacets;
    eq('two edge facets — the layer switch, then what an edge asserts', facets.length, 2);
    eq('the first is the kind facet', facets[0],
       { key: 'kind', label: 'Relationship', type: 'multiselect' });
});

test('5c: relationship_type is the second edge dimension, a pattern box', async () => {
    const g = await buildGraph(ev({ Object: [
        obj({ uuid: 'A', ObjectReference: [ref({ referenced_uuid: 'B', relationship_type: 'child-of' })],
              Relationship: [arel({ object_uuid: 'A', related_object_uuid: 'B',
                                    relationship_type: 'seen-with' })] }),
        obj({ uuid: 'B', ObjectReference: [ref({ referenced_uuid: 'A', relationship_type: '' })] }),
    ] }));
    const facet = g.opts.UI.filter.edgeFacets[1];
    // Pivotick compiles a regex facet case-insensitively and matches nothing
    // against a missing value; that is its to test, not ours to redo.
    eq('declared as the library\'s regex box — not a 143-row list, not our own matcher',
       facet, { key: 'relationship_type', label: 'Asserts', type: 'regex' });
    eq('every authored edge carries the type it asserts, the default included',
       g.edges.map(e => e.data.kind + ':' + e.data.relationship_type).sort(),
       ['analyst-relationship:seen-with', 'object-reference:child-of', 'object-reference:related-to']);
    eq('and it is the label drawn', g.edges.map(e => e.data.label === e.data.relationship_type),
       [true, true, true]);
});

test('5c: derived edges assert nothing, so carry no relationship_type', async () => {
    const g = await buildGraph(feedEvent());
    const derived = g.edges.filter(e => /-correlation$/.test(e.data.kind));
    ok('there are derived edges', derived.length > 0, String(derived.length));
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
    const objects = [
        obj({ uuid: 'A', name: 'points-at-tombstone',
              ObjectReference: [ref({ referenced_uuid: 'D' })] }),
        obj({ uuid: 'D', deleted: true }),
    ];
    const g = await buildGraph(ev({ Object: objects }));
    eq('the tombstone is not drawn', ids(g.nodes), ['obj:A']);
    eq('and it seeded no edge', g.edges, []);
    const over = await buildGraph(ev({ Object: objects.concat(fillers(1501)) }));
    eq('L2 put the source there, not the reference', over.nodes, []);
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
    const parts = {
        Attribute: [attr({ uuid: 'e1', value: 'gone', deleted: true })],
        Object: [obj({ uuid: 'A', name: 'points-at-gone', Relationship: [
            arel({ object_uuid: 'A', related_object_uuid: 'e1',
                   related_object_type: 'Attribute' }),
        ] })],
    };
    const g = await buildGraph(ev(parts));
    eq('the tombstoned attribute is not drawn', ids(g.nodes), ['obj:A']);
    eq('no edges', g.edges, []);
    const over = await buildGraph(ev(Object.assign({}, parts,
        { Object: parts.Object.concat(fillers(1501)) })));
    eq('L2 put the source there, not the relationship', over.nodes, []);
});

test('the target TYPE gates resolution, not just whether the uuid exists', async () => {
    // 'B' is a real Object here. A relationship naming uuid B but declaring a
    // non-canvas target type must still be skipped — otherwise the type check is
    // only working by accident, rescued by uuids that happen not to exist.
    const objects = [
        obj({ uuid: 'A', Relationship: [
            arel({ object_uuid: 'A', related_object_uuid: 'B', related_object_type: 'EventReport' }),
            arel({ object_uuid: 'A', related_object_uuid: 'B', related_object_type: 'GalaxyCluster' }),
            arel({ object_uuid: 'A', related_object_uuid: 'B', related_object_type: 'Event' }),
        ] }),
        obj({ uuid: 'B' }),
    ];
    const g = await buildGraph(ev({ Object: objects }));
    eq('no edges — every target type is off-canvas', g.edges, []);
    eq('both objects are on the canvas by containment alone', ids(g.nodes),
       ['obj:A', 'obj:B']);
    const over = await buildGraph(ev({ Object: objects.concat(fillers(1501)) }));
    eq('nothing was seeded by them', over.nodes, []);
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
    const objects = [
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
    ];
    const g = await buildGraph(ev({ Object: objects }));
    eq('the live objects are drawn by L2; the tombstoned owners are not',
       ids(g.nodes), ['obj:A', 'obj:B', 'obj:Y']);
    eq('no edges', g.edges, []);
    const over = await buildGraph(ev({ Object: objects.concat(fillers(1501)) }));
    eq('nothing was seeded by a tombstoned relationship', over.nodes, []);
});

test('null provenance on an analyst relationship is carried as null', async () => {
    const g = await buildGraph(ev({ Object: [
        obj({ uuid: 'A', Relationship: [
            arel({ object_uuid: 'A', related_object_uuid: 'B', authors: null, orgc_uuid: null }),
        ] }),
        obj({ uuid: 'B' }),
    ] }));
    const d = g.edges[0].data;
    eq('authors and orgc as the payload has them', [d.authors, d.orgc], [null, null]);
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

/* ──────────────────── event nodes (task 3b, revised) ───────────────────── */

test('correlated events are not drawn: the Correlations tab lists them', async () => {
    const g = await buildGraph(ev({
        RelatedEvent: [{ Event: otherEvent({ id: '22', uuid: 'R1' }) }],
        Attribute: [attr({ uuid: 'e1' })],
    }));
    eq('nothing seeded', g.nodes, []);
    eq('no edges', g.edges, []);
});

test('another event is labelled by what an analyst recognises it by', async () => {
    const g = await buildGraph(ev({ Attribute: [attr({ uuid: 'e1', Relationship: [
        toEvent(otherEvent({ id: '22', uuid: 'R1', info: 'campaign x', date: '2026-01-02',
                             Orgc: { name: 'CIRCL' }, Org: { name: 'HOST' } }), { object_uuid: 'e1' }),
        toEvent(otherEvent({ id: '23', uuid: 'R2', info: '', date: '2026-01-03' }), { object_uuid: 'e1' }),
    ] })] }));
    const a = byId(g.nodes, 'event:R1').data;
    eq('type drives the event node style', a.type, 'event');
    eq('label is the event info', a.label, 'campaign x');
    eq('description is date and creating org', a.description, '2026-01-02 · CIRCL');
    eq('it carries the id the navigation needs', a.event_id, '22');
    eq('and says it is not this event', a.scope, 'foreign');

    const b = byId(g.nodes, 'event:R2').data;
    eq('an event with no info falls back to its id', b.label, 'Event 23');
    eq('and its description to the date alone — the record has no Orgc', b.description, '2026-01-03');
    ok('another event is a leaf, not an expandable container (PRD §4)',
       g.nodes.every(n => !n.children));
});

test('the event node is drawn only when something connects to it', async () => {
    // A bare hexagon would make the seed permanently non-empty and put D11's
    // "nothing to draw" message (task 4) out of reach.
    const g = await buildGraph(ev({ Object: [obj({ uuid: 'A' })] }));
    ok('no event node', !byId(g.nodes, 'event:EV-SELF'), ids(g.nodes));
});

test('an analyst relationship can point at the event itself', async () => {
    const g = await buildGraph(ev({ Attribute: [attr({ uuid: 'e1', value: 'src',
        Relationship: [arel({ object_uuid: 'e1', related_object_uuid: 'EV-SELF',
                              related_object_type: 'Event' })] })] }));
    eq('the event node is drawn for the assertion to land on',
       ids(g.nodes), ['attr:e1', 'event:EV-SELF']);
    eq('edge', edgeKeys(g.edges), ['attr:e1->event:EV-SELF:analysed-with']);
    eq('with the analyst kind', g.edges[0].data.kind, 'analyst-relationship');
});

test('an analyst relationship can point at another event, drawn from the record it carries', async () => {
    const g = await buildGraph(ev({ Attribute: [attr({ uuid: 'e1', Relationship: [
        toEvent(otherEvent({ id: '22', uuid: 'R1', info: 'other' }), { object_uuid: 'e1' }),
    ] })] }));
    eq('the other event, and not this one', ids(g.nodes), ['attr:e1', 'event:R1']);
    eq('one analyst edge', g.edges.map(e => e.from + '->' + e.to + ':' + e.data.kind),
       ['attr:e1->event:R1:analyst-relationship']);
    eq('drawn from the attached record', byId(g.nodes, 'event:R1').data.label, 'other');
});

test('an event the viewer cannot see is not drawable', async () => {
    // getRelatedElement() comes back empty for an event the user may not see,
    // or one that does not exist — the relationship still arrives.
    const g = await buildGraph(ev({ Attribute: [attr({ uuid: 'e1', Relationship: [
        arel({ object_uuid: 'e1', related_object_uuid: 'R1', related_object_type: 'Event',
               related_object: [] }),
        arel({ object_uuid: 'e1', related_object_uuid: 'R2', related_object_type: 'Event',
               related_object: { Event: otherEvent({ uuid: 'NOT-R2' }) } }),
    ] })] }));
    eq('neither end is seeded', g.nodes, []);
});

test('the event itself can be a relationship source', async () => {
    const g = await buildGraph(ev({
        Attribute: [attr({ uuid: 'e1' })],
        Relationship: [
            arel({ object_uuid: 'EV-SELF', object_type: 'Event', related_object_uuid: 'e1',
                   related_object_type: 'Attribute', relationship_type: 'blocks' }),
            toEvent(otherEvent({ id: '22', uuid: 'R1' }),
                    { object_uuid: 'EV-SELF', object_type: 'Event', relationship_type: 'similar' }),
        ],
    }));
    eq('the event and both targets', ids(g.nodes), ['attr:e1', 'event:EV-SELF', 'event:R1']);
    eq('an edge each, out of the event', edgeKeys(g.edges),
       ['event:EV-SELF->attr:e1:blocks', 'event:EV-SELF->event:R1:similar']);
});

test('two relationships to one event draw it once, and charge it once', async () => {
    const r1 = otherEvent({ id: '22', uuid: 'R1' });
    const g = await buildGraph(ev({ Attribute: [
        attr({ uuid: 'e1', Relationship: [toEvent(r1, { object_uuid: 'e1' })] }),
        attr({ uuid: 'e2', Relationship: [toEvent(r1, { object_uuid: 'e2' })] }),
    ] }));
    eq('one event node', ids(g.nodes), ['attr:e1', 'attr:e2', 'event:R1']);
    eq('two edges', g.edges.length, 2);
});

test('with no event uuid in the payload the event is no endpoint', async () => {
    const g = await buildGraph(ev({ uuid: null,
        Relationship: [toEvent(otherEvent({ uuid: 'R1' }), { object_uuid: 'x' })] }));
    eq('nothing drawn', g.nodes, []);
    eq('no edges', g.edges, []);
});

test('double-click on another event opens it, and does nothing anywhere else', async () => {
    const g = await buildGraph(ev({ Attribute: [attr({ uuid: 'e1', Relationship: [
        toEvent(otherEvent({ id: '22', uuid: 'R1' }), { object_uuid: 'e1' }),
    ] })] }));
    const dbl = g.opts.callbacks.onNodeDbclick;
    ok('the callback is declared', typeof dbl === 'function');

    dbl({}, { getData: () => ({ type: 'event', event_id: '22' }) });
    eq('navigates to the other event', g.win.location.href, '/misp/events/view2/22');

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
});

test('L2 never adds a bare event-level attribute (D10 governing principle)', async () => {
    // 80% of all attributes are event-level; seeding those means seeding the
    // whole event again, and a bare attribute conveys less than its table row.
    const g = await buildGraph(ev({
        Attribute: [attr({ uuid: 'e1', value: 'one' }), attr({ uuid: 'e2', value: 'two' })],
    }));
    eq('nothing on the canvas', g.nodes, []);
    eq('both are in the tray', trayLabels(g).sort(), ['one', 'two']);
});

test('the budget is all-or-nothing: one node over and L2 is skipped whole', async () => {
    const at = await buildGraph(ev({ Object: fillers(1500) }));
    eq('and every object is drawn', at.nodes.length, 1500);
    eq('so the tray is empty', at.tray, []);

    const over = await buildGraph(ev({ Object: fillers(1501) }));
    eq('one node over, and not a single one is drawn', over.nodes, []);
    eq('the skipped objects fall back to the tray (D4)', over.tray.length, 1501);
});

test('over budget, the seed falls back to the relationship spine', async () => {
    // Event 4116 in miniature: L2 does not fit, so L1 carries the graph and
    // the element pivot carries the rest.
    const g = await buildGraph(ev({
        Object: [
            obj({ uuid: 'A', ObjectReference: [ref({ referenced_uuid: 'B' })],
                  Relationship: [toEvent(otherEvent({ uuid: 'R1' }), { object_uuid: 'A' })] }),
            obj({ uuid: 'B' }),
        ].concat(fillers(1501)),
    }));
    eq('L1 survives, the event it relates to included', ids(g.nodes),
       ['event:R1', 'obj:A', 'obj:B']);
    eq('with both their edges', g.edges.length, 2);
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
    eq('all 750 fall back to the element pivot', g.tray.length, 750);
});

test('a deleted child does not cost the budget anything', async () => {
    // 1,500 objects with one tombstoned child each: 1,500 live nodes, not 3,000.
    const many = [];
    for (let i = 0; i < 1500; i++) {
        many.push(obj({ uuid: 'd' + i, Attribute: [attr({ uuid: 'd' + i + 'x', deleted: true })] }));
    }
    const g = await buildGraph(ev({ Object: many }));
    eq('and no tombstone was nested', countAll(g.nodes), 1500);
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

test('INVARIANT: every kind the builder emits resolves to a styled kind — both authored ones', async () => {
    const g = await buildGraph(ev({
        Attribute: [attr({ uuid: 'e1' })],
        Object: [
            obj({ uuid: 'A',
                  ObjectReference: [ref({ referenced_uuid: 'e1', referenced_type: '0' })],
                  Relationship: [arel({ object_uuid: 'A', related_object_uuid: 'B' }),
                                 toEvent(otherEvent({ uuid: 'R1' }), { object_uuid: 'A' })] }),
            obj({ uuid: 'B' }),
        ],
    }));
    const styled = Object.keys(g.opts.render.edgeStyleMap);
    const accessor = g.opts.render.edgeTypeAccessor;

    eq('both kinds are in play at once',
       [...new Set(g.edges.map(e => e.data.kind))].sort(),
       ['analyst-relationship', 'object-reference']);
    g.edges.forEach(e => {
        const resolved = accessor({ getData: () => e.data });
        ok('kind ' + JSON.stringify(resolved) + ' for ' + e.from + '->' + e.to + ' is styled',
           styled.indexOf(resolved) !== -1, 'styled kinds: ' + JSON.stringify(styled));
    });
});

/* ───────────────────────── pivot (R1) ────────────────────────── */

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
const PAIRS = {
    pairs: [pair('c1', 'x1', '7', 'R7'), pair('c1', 'x2', '7', 'R7'), pair('e1', 'x1', '8', 'R8')],
    events: {
        '7': {
            id: '7', uuid: 'R7', info: 'Event 7', date: '2024-01-02',
            published: true, publish_timestamp: '1700000000', distribution: '3',
            attribute_count: '36', object_count: 9,
            Orgc: { name: 'CIRCL', uuid: 'O1' },
            Tag: [{ name: 'tlp:white', colour: '#ffffff', is_galaxy: false }],
            Galaxy: [{ type: 'tool', GalaxyCluster: [{ value: 'BabyShark' }] }],
        },
    },
};

function pivotFixture() {
    return ev({
        Attribute: [attr({ uuid: 'e1' })],
        Object: [obj({ uuid: 'A', Attribute: [attr({ uuid: 'c1' })] }),
                 obj({ uuid: 'B', Relationship: [
                     toEvent(otherEvent({ uuid: 'R7', id: '7' }), { object_uuid: 'B' })] })],
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

test('the correlation pivot is declared, capped at the canvas budget, and savable by nobody', async () => {
    const g = await withPivots();
    eq('after the element pivot, correlations, feed events, tags and clusters, then what a tag leads to',
       g.opts.pivots.map(p => p.id),
       ['event-elements', 'correlations', 'feed-events', 'tags', 'tagged-events', 'related-clusters']);
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
});

const potentials = (g, id) => {
    const n = g.graph.getMutableNode(id);
    return n ? Array.from(n.getPotentials()) : undefined;
};

test('15: every counted element declares what its pivot would bring, as rim potential', async () => {
    const g = await withPivots();
    eq('an unlinked attribute is not drawn yet, so declares nothing yet', potentials(g, 'attr:e1'), undefined);
    eq('an object, its own count', potentials(g, 'obj:A'), [['correlations', 2]]);
    eq('a child attribute, for when its object is expanded', potentials(g, 'attr:c1'), [['correlations', 2]]);
    eq('an object nothing correlates with declares nothing', potentials(g, 'obj:B'), []);
    eq('another event declares nothing, however much it shares', potentials(g, 'event:R7'), []);
    eq('one render to draw them', g.graph.renderer.updates, 1);
    eq('no console errors', g.errors, []);
});

test('15: an element that lands later declares its own on arrival', async () => {
    const g = await withPivots();
    const land = (id, data) => {
        const n = g.graph.liveNode({ id, data });
        g.graph.listeners.nodeAdd.forEach(f => f(n));
        return Array.from(n.getPotentials());
    };
    eq('this event\'s attribute, put on the canvas by the element pivot',
       land('attr:e1', { type: 'attribute', uuid: 'e1' }), [['correlations', 1]]);
    eq('a correlated attribute from elsewhere has nothing to declare',
       land('attr:x1', { type: 'attribute', uuid: 'x1', event_id: '7' }), []);
});

test('an object origin fetches by its live attributes', async () => {
    let body = null;
    const g = await withPivots([[/correlatedAttributes/, init => { body = JSON.parse(init.body); return PAIRS; }]]);
    await pivot(g, 'correlations').fetch([pnode({ type: 'object', uuid: 'A' }), pnode({ type: 'attribute', uuid: 'e1' })], {}, {});
    eq('the object expands to its child attribute, the attribute stays itself',
       body, { attribute_uuids: ['c1', 'e1'] });
});

test('correlated attributes land inside their event, joined to this event by correlation edges', async () => {
    const g = await withPivots();
    const r = await pivot(g, 'correlations').fetch([pnode({ type: 'attribute', uuid: 'e1' })], {}, {});
    const containers = r.nodes.filter(n => n.id.indexOf('event:') === 0);
    eq('one container per correlated event, keyed like a drawn event so ingest merges into it',
       containers.map(n => n.id), ['event:R7', 'event:R8']);
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

test('a correlated event\'s container is drawn from its card, not from the pair', async () => {
    const g = await withPivots();
    const r = await pivot(g, 'correlations').fetch([pnode({ type: 'attribute', uuid: 'e1' })], {}, {});
    const d = r.nodes.find(n => n.id === 'event:R7').data;
    eq('the index row', [d.org, d.orgc, d.date, d.published, d.publish_timestamp, d.distribution],
       ['CIRCL', { name: 'CIRCL', uuid: 'O1' }, '2024-01-02', true, 1700000000, 3]);
    eq('its own counts, not the correlated children', [d.attribute_count, d.object_count], [36, 9]);
    eq('its tags', d.tags, [{ name: 'tlp:white', colour: '#ffffff' }]);
    eq('its galaxy clusters', d.context, [{ galaxy_type: 'tool', value: 'BabyShark' }]);
    const bare = r.nodes.find(n => n.id === 'event:R8').data;
    eq('without a card, the pair\'s event still draws its title', [bare.label, bare.tags], ['Event 8', undefined]);
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

test('the confirm bounds a long element name itself; the label stays whole', async () => {
    const g = await withDeletes();
    const edge = pedge('ref:R1', pnode({ type: 'object', uuid: 'A', label: 'file' }),
        pnode({ type: 'attribute', uuid: 'e1', label: 'y'.repeat(80) }),
        { kind: 'object-reference', label: 'drops', uuid: 'R1' });
    const ctx = delCtx({ edges: [edge] }, false);
    await g.opts.callbacks.onBeforeDelete(ctx);
    eq('body', ctx.asked.body, 'This deletes the relationship in MISP: file → drops → '
       + 'y'.repeat(41) + '…. It cannot be undone from the graph.');
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
    eq('analyst only: no link type, and how it is shared in the same form', one.asked.fields.map(f => f.key),
       ['relationship_type', 'custom', 'distribution', 'authors']);
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
    eq('body', g.adds, [{ Relationship: { related_object_uuid: 'x1', related_object_type: 'Attribute',
                                          relationship_type: 'seen-with', distribution: '1' } }]);
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

/* ──── how an analyst relationship is shared: distribution, sharing group, authors ──── */

const SHARING = JSON.stringify({
    levels: [[0, 'Your organisation only'], [1, 'This community only'], [2, 'Connected communities'],
             [3, 'All communities'], [4, 'Sharing group']],
    sharingGroups: [[5, 'Alpha'], [3, 'Beta']],
    default: 2, authors: 'me@x',
});

// A context answering each form in turn, recording every one it was shown.
function edgeCtxSeq(source, target, answers) {
    const ctx = {
        kind: 'edge', source, target, origin: 'drag', asks: [],
        promptData: opts => { ctx.asks.push(opts); return Promise.resolve(answers[ctx.asks.length - 1]); },
    };
    return ctx;
}

test('sharing: the form offers MISP\'s levels, the user\'s sharing groups and the author', async () => {
    const g = await withAnalyst(Object.assign({ analystSharing: SHARING }, analystOnly));
    const ctx = edgeCtxSeq(own.objA, own.objB, [null]);
    await g.opts.callbacks.onBeforeEdgeCreate(ctx);
    eq('one form, saving', [ctx.asks.length, ctx.asks[0].submitLabel], [1, 'Save']);
    const f = {}; ctx.asks[0].fields.forEach(x => { f[x.key] = x; });
    eq('distribution: the five levels, the instance default', [f.distribution.type, f.distribution.options.map(o => o.value),
       f.distribution.defaultValue], ['select', ['0', '1', '2', '3', '4'], '2']);
    eq('named as MISP names them', f.distribution.options[4].label, 'Sharing group');
    eq('sharing groups in the order given, none picked', [f.sharing_group_id.options.map(o => o.label), f.sharing_group_id.defaultValue],
       [['—', 'Alpha', 'Beta'], '']);
    eq('authors, blank meaning the user', [f.authors.type, f.authors.placeholder], ['text', 'me@x']);
});

test('sharing: with no sharing group to offer, none is asked', async () => {
    const none = JSON.stringify(Object.assign(JSON.parse(SHARING), { sharingGroups: [] }));
    const g = await withAnalyst(Object.assign({ analystSharing: none }, analystOnly));
    const ctx = edgeCtxSeq(own.objA, own.objB, [null]);
    await g.opts.callbacks.onBeforeEdgeCreate(ctx);
    eq('fields', ctx.asks[0].fields.map(x => x.key), ['relationship_type', 'custom', 'distribution', 'authors']);
});

test('sharing: with two kinds it is asked second, and only for the analyst kind', async () => {
    const g = await withAnalyst(Object.assign({ analystSharing: SHARING }, both));
    const ref = edgeCtxSeq(own.objA, own.objB, [{ kind: 'object-reference', custom: 'r' }]);
    await g.opts.callbacks.onBeforeEdgeCreate(ref);
    eq('a reference asks once, with no sharing', [ref.asks.length, ref.asks[0].submitLabel,
       ref.asks[0].fields.some(x => x.key === 'distribution')], [1, 'Next', false]);
    const ar = edgeCtxSeq(own.objA, own.objB, [{ kind: 'analyst-relationship', custom: 'a' },
                                               { distribution: '3', authors: ' Alice ' }]);
    const d = await g.opts.callbacks.onBeforeEdgeCreate(ar);
    eq('the second form', [ar.asks.length, ar.asks[1].title, ar.asks[1].submitLabel, ar.asks[1].fields.map(x => x.key)],
       [2, 'Share the relationship', 'Save', ['distribution', 'sharing_group_id', 'authors']]);
    eq('saved as answered, authors trimmed', g.adds, [{ Relationship: { related_object_uuid: 'B', related_object_type: 'Object',
       relationship_type: 'a', distribution: '3', authors: 'Alice' } }]);
    eq('and it lands', d.accept, true);
    const cancelled = edgeCtxSeq(own.objA, own.objB, [{ kind: 'analyst-relationship', custom: 'a' }, null]);
    eq('cancelling the second form saves nothing', [await g.opts.callbacks.onBeforeEdgeCreate(cancelled), g.adds.length],
       [false, 1]);
});

test('sharing: a sharing group level needs its group; any other level drops one', async () => {
    const g = await withAnalyst(Object.assign({ analystSharing: SHARING }, analystOnly));
    const missing = edgeCtxSeq(own.objA, own.objB, [{ custom: 'x', distribution: '4' }]);
    eq('refused before any POST', [await g.opts.callbacks.onBeforeEdgeCreate(missing), g.adds.length], [false, 0]);
    eq('and says why', g.graph.notices.map(n => [n.level, n.title]), [['warning', 'No sharing group']]);
    await g.opts.callbacks.onBeforeEdgeCreate(edgeCtxSeq(own.objA, own.objB, [{ custom: 'x', distribution: '4', sharing_group_id: '5' }]));
    await g.opts.callbacks.onBeforeEdgeCreate(edgeCtxSeq(own.objA, own.objB, [{ custom: 'y', distribution: '0', sharing_group_id: '5' }]));
    eq('the group goes with level 4 only', g.adds.map(a => [a.Relationship.distribution, a.Relationship.sharing_group_id]),
       [['4', '5'], ['0', undefined]]);
});

test('sharing: unreadable options fall back to the defaults', async () => {
    const g = await withAnalyst(Object.assign({ analystSharing: '{not json' }, analystOnly));
    await g.opts.callbacks.onBeforeEdgeCreate(edgeCtxSeq(own.objA, own.objB, [{ custom: 'x' }]));
    eq('saved at level 1', g.adds.map(a => a.Relationship.distribution), ['1']);
    ok('and said so in the console', g.errors.some(e => /analyst sharing/.test(e)), JSON.stringify(g.errors));
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
        ok(evt + ' is watched', (g.graph.listeners[evt] || []).length >= 1);
        g.graph.listeners[evt].forEach(f => f(pnode({})));
    });
    eq('each drops this pivot\'s cache', g.graph.pivots.invalidated, ['event-elements', 'event-elements']);
});

/* ─────────────── task 6: analyst-data badges and panel ─────────────── */

const note = o => Object.assign({ note_type_name: 'Note', note: 'n', authors: 'a@x', created: '2025-03-11 14:06:56', Orgc: { name: 'CIRCL' } }, o);
const opinion = o => Object.assign({ note_type_name: 'Opinion', opinion: '50', comment: '', authors: 'a@x', created: '2025-03-12 09:00:00', Orgc: { name: 'CIRCL' } }, o);

function analystFixture() {
    return ev({
        Note: [note({ note: 'about the event' })],
        // What puts the event node on the canvas for its note to sit on.
        Relationship: [arel({ object_uuid: 'EV-SELF', related_object_uuid: 'c2',
                              related_object_type: 'Attribute' })],
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

test('17: the panel\'s title counts what was said about the selection', async () => {
    const g = await buildGraph(analystFixture());
    const title = g.opts.UI.extraPanels[0].title;
    eq('with a count', title(pnode(nodeById(g.nodes, 'attr:c1').data)), 'Notes & opinions (5)');
    eq('without', [title(pnode({ type: 'attribute', uuid: 'c2' })), title(null), title([pnode({ analyst_count: 2 })])],
       ['Notes & opinions', 'Notes & opinions', 'Notes & opinions']);
});

/* ─────────── task 17: the sidebar's properties, under MISP's names ─────────── */

const props = (g, data) => g.opts.UI.propertiesPanel.nodePropertiesMap(pnode(data))
    .map(p => p.name + ': ' + p.value);
const edgeProps = (g, data) => g.opts.UI.propertiesPanel.edgePropertiesMap({ getData: () => data })
    .map(p => p.name + ': ' + p.value);

test('17: an attribute reads by value, type and category, then where it belongs', async () => {
    const g = await buildGraph(ev({
        Attribute: [attr({ uuid: 'e1', value: '1.2.3.4', to_ids: true, comment: 'c2 box', FeedHit: true })],
        Object: [obj({ uuid: 'A', ObjectReference: [ref({ referenced_uuid: 'e1', referenced_type: '0' })],
                       Attribute: [attr({ uuid: 'c1', object_relation: 'ip', value: 'v' })] })],
    }));
    eq('event-level, flagged and commented', props(g, byId(g.nodes, 'attr:e1').data), [
        'Value: 1.2.3.4', 'Type: ip-dst', 'Category: Network activity', 'IDS flag: Yes', 'Comment: c2 box',
        'Event: This event', 'Seen in a feed: Yes — too many hits in this event to name which', 'UUID: e1']);
    eq('an object\'s attribute, with its relation; blank fields left out',
       props(g, byId(g.nodes, 'obj:A').children[0].data), [
        'Value: v', 'Type: ip-dst', 'Category: Network activity', 'Object relation: ip', 'IDS flag: No',
        'Event: This event', 'UUID: c1']);
    eq('from another event, by its id', props(g, { type: 'attribute', value: 'x', scope: 'foreign', event_id: '7' }),
       ['Value: x', 'Event: Event 7']);
});

test('17: objects, events and sources each read by their own fields', async () => {
    const g = await buildGraph(ev({ info: 'Seed', date: '2025-01-02', Orgc: { name: 'CIRCL' },
        Relationship: [toEvent(otherEvent({ uuid: 'R', id: '7', info: 'Other' }), { object_uuid: 'EV-SELF' })],
        Object: [obj({ uuid: 'A', name: 'domain-ip', 'meta-category': 'network' })] }));
    eq('object', props(g, byId(g.nodes, 'obj:A').data),
       ['Template: domain-ip', 'Meta-category: network', 'Event: This event', 'UUID: A']);
    eq('event', props(g, byId(g.nodes, 'event:EV-SELF').data),
       ['Info: Seed', 'Date: 2025-01-02', 'Organisation: CIRCL', 'Event ID: 1', 'UUID: EV-SELF']);
    eq('feed', props(g, { type: 'feed', provider: 'CIRCL', url: 'https://x', source_format: 'misp',
                          feed_events: 3, source_id: '1', scope: 'foreign' }),
       ['Provider: CIRCL', 'URL: https://x', 'Format: misp', 'Events: 3', 'Feed ID: 1']);
    eq('server, with only a name', props(g, { type: 'server', source_id: '4', scope: 'foreign' }), ['Server ID: 4']);
    eq('anything else, nothing', props(g, { type: 'note' }), []);
});

test('17: an edge reads by the kind of link and what it asserts', async () => {
    const g = await buildGraph(ev({ Object: [obj({ uuid: 'A' })] }));
    eq('an analyst relationship', edgeProps(g, { kind: 'analyst-relationship', relationship_type: 'seen-with',
                                                  authors: 'alice', uuid: 'U1', orgc: 'o' }),
       ['Link: Analyst relationship', 'Relationship: seen-with', 'Authors: alice', 'UUID: U1']);
    eq('every derived kind has a name', ['correlation', 'feed-correlation', 'server-correlation']
       .map(k => edgeProps(g, { kind: k, label: '' })[0]),
       ['Link: Correlation', 'Link: Seen in a feed', 'Link: Seen on a server']);
});

/* ─────────────────── task 18: the node context menu ─────────────────── */

const menuItem = (g, text) => g.opts.UI.contextMenu.menuNode.menu.find(i => i.text === text);
const shows = (g, text, data) => menuItem(g, text).visible(data === null ? null : pnode(data));

test('18: MISP adds four entries to the node menu, after the library\'s own', async () => {
    const g = await buildGraph(ev({ Object: [obj({ uuid: 'A' })] }));
    eq('in this order', g.opts.UI.contextMenu.menuNode.menu.map(i => [i.text, i.iconClass]), [
        ['Open its event', 'fas fa-external-link-alt'], ['Browse feed', 'fas fa-rss'],
        ['Preview in feed', 'fas fa-rss'], ['Copy value', 'fas fa-copy']]);
    ok('no topbar of ours, and the edge and note menus left alone',
       !g.opts.UI.contextMenu.menuNode.topbar
       && JSON.stringify(Object.keys(g.opts.UI.contextMenu)) === JSON.stringify(['menuNode', 'menuCanvas']));
});

test('18: another event\'s page opens in a new tab, for whatever belongs to one', async () => {
    const g = await buildGraph(ev({ Object: [obj({ uuid: 'A' })] }), { baseurl: '/misp' });
    eq('a related event, a correlated attribute or object — never this event or a source', [
        shows(g, 'Open its event', { type: 'event', event_id: '7' }),
        shows(g, 'Open its event', { type: 'attribute', event_id: '7', scope: 'foreign' }),
        shows(g, 'Open its event', { type: 'object', event_id: '7', scope: 'foreign' }),
        shows(g, 'Open its event', { type: 'event', event_id: '1' }),
        shows(g, 'Open its event', { type: 'attribute', event_id: '1', scope: 'self' }),
        shows(g, 'Open its event', { type: 'feed', source_id: '3' }),
        shows(g, 'Open its event', null),
    ], [true, true, true, false, false, false, false]);
    menuItem(g, 'Open its event').onclick({}, pnode({ type: 'attribute', event_id: '7' }));
    eq('view2, in a new tab, without an opener', g.win.opened, [['/misp/events/view2/7', '_blank', 'noopener']]);
    ok('the page itself stays', g.win.location.href === '');
    eq('a multi-selection is not one element', shows(g, 'Open its event', undefined) === false
       && menuItem(g, 'Open its event').visible([pnode({ type: 'event', event_id: '7' }), pnode({ type: 'event', event_id: '8' })]),
       false);
});

test('18: a feed opens on its preview, which every role may read', async () => {
    const g = await buildGraph(ev({ Object: [obj({ uuid: 'A' })] }), { baseurl: '/misp' });
    eq('feeds only', [shows(g, 'Browse feed', { type: 'feed', source_id: '3' }),
                      shows(g, 'Browse feed', { type: 'server', source_id: '3' }),
                      shows(g, 'Browse feed', { type: 'attribute' })], [true, false, false]);
    menuItem(g, 'Browse feed').onclick({}, pnode({ type: 'feed', source_id: '3' }));
    eq('previewIndex', g.win.opened, [['/misp/feeds/previewIndex/3', '_blank', 'noopener']]);
});

test('18: an attribute\'s value copies whole, and says so', async () => {
    const copied = [];
    const g = await buildGraph(ev({ Object: [obj({ uuid: 'A' })] }), {
        navigator: { clipboard: { writeText: v => { copied.push(v); return Promise.resolve(); } } },
    });
    eq('attributes with a value only', [shows(g, 'Copy value', { type: 'attribute', value: 'x' }),
                                        shows(g, 'Copy value', { type: 'attribute', value: '' }),
                                        shows(g, 'Copy value', { type: 'object', name: 'x' })], [true, false, false]);
    const long = 'z'.repeat(120);
    menuItem(g, 'Copy value').onclick({}, pnode({ type: 'attribute', value: long }));
    await new Promise(r => setTimeout(r, 0));
    eq('the whole value', copied, [long]);
    eq('a notice, shortened', g.graph.notices.map(n => [n.level, n.title, n.msg.length]), [['success', 'Copied', 80]]);
});

test('18: a refused or missing clipboard says so', async () => {
    const refused = await buildGraph(ev({ Object: [obj({ uuid: 'A' })] }), {
        navigator: { clipboard: { writeText: () => Promise.reject(new Error('no')) } },
    });
    menuItem(refused, 'Copy value').onclick({}, pnode({ type: 'attribute', value: 'v' }));
    await new Promise(r => setTimeout(r, 0));
    eq('refused', refused.graph.notices.map(n => [n.level, n.title]), [['error', 'Copy failed']]);
    const none = await buildGraph(ev({ Object: [obj({ uuid: 'A' })] }));
    menuItem(none, 'Copy value').onclick({}, pnode({ type: 'attribute', value: 'v' }));
    eq('absent', none.graph.notices.map(n => [n.level, n.title]), [['error', 'Copy failed']]);
});

/* ─────────── task 12: taking fetched correlations back off ─────────── */

const canvasItem = g => g.opts.UI.contextMenu.menuCanvas.menu[0];

// The pivot fixture, then one correlation run's worth of results, vouched as
// Pivotick vouches an ingest: by the pivot's id.
async function withFetched() {
    const g = await withPivots();
    const gr = g.graph;
    gr.liveNode({ id: 'attr:x1', data: { type: 'attribute', uuid: 'x1' } }, ['correlations']);
    gr.liveNode({ id: 'attr:x2', data: { type: 'attribute', uuid: 'x2' } }, ['correlations']);
    gr.liveNode({ id: 'attr:e1', data: { type: 'attribute', uuid: 'e1' } }, ['correlations', 'event-elements']);
    ['c1>x1', 'c1>x2'].forEach(id => gr.liveEdges.push({
        id, getData: () => ({ kind: 'correlation' }), vouched: new Set(['correlations']),
        hasSource(s) { return this.vouched.has(s); },
        dropSource(s) { this.vouched.delete(s); return this.vouched.size === 0; },
    }));
    return g;
}

test('12: the canvas menu offers it only while the correlation pivot has brought something', async () => {
    const seeded = await withPivots();
    eq('one entry', [canvasItem(seeded).text, canvasItem(seeded).iconClass],
       ['Remove fetched correlations', 'fas fa-eraser']);
    eq('nothing fetched, nothing offered', canvasItem(seeded).visible(null), false);
    const fetched = await withFetched();
    eq('after a run, offered', canvasItem(fetched).visible(null), true);
});

test('12: it removes what the correlation pivot brought, and only that', async () => {
    const g = await withFetched();
    const seedNodes = g.graph.getMutableNodes().length - 3, seedEdges = g.graph.getMutableEdges().length - 2;
    canvasItem(g).onclick({}, null);
    eq('through the library', g.graph.removedBy, ['correlations']);
    ok('the fetched elements are gone', !g.graph.getMutableNode('attr:x1') && !g.graph.getMutableNode('attr:x2'));
    ok('one the element pivot also put there stays', !!g.graph.getMutableNode('attr:e1'));
    eq('the seed is untouched', [g.graph.getMutableNodes().length - 1, g.graph.getMutableEdges().length],
       [seedNodes, seedEdges]);
    eq('the notice counts it and says Undo puts it back', g.graph.notices.map(n => [n.level, n.title, n.msg]), [[
        'success', 'Correlations removed',
        '2 elements and 2 links off the canvas. Undo puts them back.']]);
    eq('and then there is nothing left to offer', canvasItem(g).visible(null), false);
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
    ok('it names what is missing', t.indexOf('Nothing in this event is linked yet') !== -1
       && t.indexOf('No object references or analyst relationships to draw') !== -1, t);
    ok('and where correlations come from', t.indexOf('Correlations are fetched from the elements on the canvas') !== -1, t);
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
        Relationship: [toEvent(otherEvent({ uuid: 'R7', id: '7' }), { object_uuid: 'EV-SELF' })],
        Attribute: [attr({ uuid: 'e1', event_id: '1' })],
        Object: [obj({ uuid: 'A', event_id: '1', Attribute: [attr({ uuid: 'c1', event_id: '1' })],
                       ObjectReference: [ref({ referenced_uuid: 'e1', referenced_type: '0' })] })],
    }));
    const all = flat(g.nodes);
    eq('this event', prov(byId(all, 'event:EV-SELF')), ['self', '1', 'EV-SELF']);
    eq('another event', prov(byId(all, 'event:R7')), ['foreign', '7', 'R7']);
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
    ok('Element is the nodeTypeAccessor dimension, its hues declared (drawn nodes have none)',
       sections[0].key === undefined && typeof sections[0].entries === 'function' && sections[0].scope === undefined);
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

/* ────────────── task 5b: feed and server correlations ────────────── */

// A feed as Feed::attachFeedCorrelations() attaches it: the full record on the
// event's source map, and a copy on every attribute it was seen in. The map is
// keyed by feed id in PHP but reaches the browser as a list.
const FEED1 = { id: '1', name: 'CIRCL OSINT Feed', url: 'https://x/osint', provider: 'CIRCL',
                source_format: 'misp', lookup_visible: true, event_uuids: ['u1', 'u2'] };
const FEED9 = { id: '9', name: 'URLHaus', url: 'https://x/urlhaus', provider: 'abuse.ch',
                source_format: 'csv', lookup_visible: true };

function feedEvent(extra) {
    return ev(Object.assign({
        Feed: [FEED1, FEED9],   // FEED9 at index 1: position is not id
        // The first hit met is a trimmed copy, so the node must read the map.
        Attribute: [attr({ uuid: 'e1', value: 'linked', Feed: [{ id: '1', name: 'CIRCL OSINT Feed' }] }),
                    attr({ uuid: 'e2', value: 'loose', Feed: [FEED1, FEED9] })],
        Object: [
            obj({ uuid: 'A', ObjectReference: [ref({ referenced_uuid: 'e1', referenced_type: '0' })],
                  Attribute: [attr({ uuid: 'c1', Feed: [{ id: '1', name: 'CIRCL OSINT Feed' }, FEED9] }),
                              attr({ uuid: 'c2' })] }),
        ],
    }, extra));
}

test('5b: one node per feed, joined to every drawn attribute seen in it', async () => {
    const g = await buildGraph(feedEvent());
    eq('the two feeds join the canvas', ids(g.nodes), ['attr:e1', 'feed:1', 'feed:9', 'obj:A']);
    eq('edges into the referenced attribute and the object\'s child — into, which pivotick draws for a child',
       g.edges.filter(e => e.data.kind === 'feed-correlation').map(e => e.from + '->' + e.to).sort(),
       ['feed:1->attr:c1', 'feed:1->attr:e1', 'feed:9->attr:c1']);
    eq('a correlation asserts nothing', g.edges.filter(e => e.data.kind === 'feed-correlation')
       .map(e => [e.data.label, 'relationship_type' in e.data]), [['', false], ['', false], ['', false]]);
    const f = byId(g.nodes, 'feed:1').data;
    eq('the node reads the event\'s full record, not the attribute\'s copy', f, {
        type: 'feed', label: 'CIRCL OSINT Feed', description: 'CIRCL · misp feed', source_id: '1',
        provider: 'CIRCL', url: 'https://x/osint', source_format: 'misp', feed_events: 2, scope: 'foreign' });
    ok('no name key — that is the Object facet', !('name' in f));
    ok('no feed_events without a MISP-format feed', byId(g.nodes, 'feed:9').data.feed_events == null);
});

test('5b: the source map is read by id, as a list or keyed', async () => {
    const list = await buildGraph(feedEvent());
    const keyed = await buildGraph(feedEvent({ Feed: { 1: FEED1, 9: FEED9 } }));
    [['list', list], ['keyed', keyed]].forEach(([shape, g]) => eq(shape + ': each node under its own name',
        ['feed:1', 'feed:9'].map(i => byId(g.nodes, i).data.label), ['CIRCL OSINT Feed', 'URLHaus']));
});

test('5b: a feed hit never puts an element on the canvas', async () => {
    const g = await buildGraph(feedEvent());
    ok('the loose attribute stays off', !byId(g.nodes, 'attr:e2'));
    eq('and it is still offered by the element pivot', trayLabels(g), ['loose']);
});

test('5b: a feed seen only by elements not drawn draws no node', async () => {
    const g = await buildGraph(ev({
        Feed: [FEED9],
        Attribute: [attr({ uuid: 'e2', Feed: [FEED9] })],
        Object: [obj({ uuid: 'A' })],
    }));
    eq('no feed node', ids(g.nodes), ['obj:A']);
});

test('5b: deleted attributes carry no hits', async () => {
    const g = await buildGraph(ev({
        Feed: [FEED1],
        Object: [obj({ uuid: 'A', Attribute: [attr({ uuid: 'c1', deleted: true, Feed: [FEED1] })] })],
    }));
    eq('no feed node', ids(g.nodes), ['obj:A']);
});

test('5b: past 10,000 hits, a badge on each attribute', async () => {
    const g = await buildGraph(ev({
        FeedCount: 16246,
        Attribute: [attr({ uuid: 'e1', FeedHit: true })],
        Object: [obj({ uuid: 'A', ObjectReference: [ref({ referenced_uuid: 'e1', referenced_type: '0' })],
                       Attribute: [attr({ uuid: 'c1', FeedHit: true }), attr({ uuid: 'c2' })] })],
    }));
    ok('no source node — there is nothing to point at', !g.nodes.some(n => n.data.type === 'feed'));
    ok('no feed edge', !g.edges.some(e => e.data.kind === 'feed-correlation'));
    const c1 = byId(g.nodes, 'obj:A').children.find(c => c.id === 'attr:c1').data;
    const c2 = byId(g.nodes, 'obj:A').children.find(c => c.id === 'attr:c2').data;
    eq('the flag rides on the node', [byId(g.nodes, 'attr:e1').data.feed_hit, c1.feed_hit, c2.feed_hit == null],
       [true, true, true]);
    const b = badgesOf(g, c1);
    eq('one badge, bottom-left, off the analyst corner', b.map(x => [x.position, x.iconClass, x.color]),
       [['sw', 'fas fa-rss', '#5bc0de']]);
    ok('it says why no feed is named', /too many/.test(b[0].title), b[0].title);
    eq('no badge without the flag', badgesOf(g, c2), []);
    eq('both badges when there is analyst data too',
       badgesOf(g, { feed_hit: true, analyst_count: 2, analyst_mood: 'none' }).map(x => x.position), ['nw', 'sw']);
});

test('5b: a server is a source like a feed, on its own layer, even with only id and name', async () => {
    const SRV = { id: '3', name: 'Partner MISP' };
    const g = await buildGraph(ev({
        Server: [SRV],
        Attribute: [attr({ uuid: 'e1', Server: [SRV] })],
        Object: [obj({ uuid: 'A', ObjectReference: [ref({ referenced_uuid: 'e1', referenced_type: '0' })] })],
    }));
    eq('its node', byId(g.nodes, 'server:3').data,
       { type: 'server', label: 'Partner MISP', description: 'Server', source_id: '3', scope: 'foreign' });
    eq('its edge', g.edges.filter(e => e.from === 'server:3').map(e => [e.to, e.data.kind]),
       [['attr:e1', 'server-correlation']]);
});

test('5b: a feed and a server sharing an id are two nodes', async () => {
    const g = await buildGraph(ev({
        Feed: [FEED1], Server: [{ id: '1', name: 'S' }],
        Attribute: [attr({ uuid: 'e1', Feed: [FEED1], Server: [{ id: '1', name: 'S' }] })],
        Object: [obj({ uuid: 'A', ObjectReference: [ref({ referenced_uuid: 'e1', referenced_type: '0' })] })],
    }));
    eq('both', ids(g.nodes).filter(i => /^(feed|server):/.test(i)), ['feed:1', 'server:1']);
});

test('5b: sources are styled, iconed and keyed like the other elements', async () => {
    const g = await buildGraph(feedEvent());
    const r = g.opts.render;
    const feed = r.nodeStyleMap.feed;
    eq('a feed is drawn by misp-pivot-nodes: composed at rest, the authority card as its chip',
       [feed.shape, typeof feed.svgIcon, feed.tiers[0].width, feed.tiers[0].height,
        typeof feed.tiers[0].style.html],
       ['none', 'function', 140, 44, 'function']);
    eq('a server is still a triangle in its layer\'s colour', r.nodeStyleMap.server,
       { shape: 'triangle', color: '#9b59b6', size: 24, iconClass: 'fas fa-server' });
    eq('the accessor reads the type', r.nodeTypeAccessor(pnode({ type: 'feed' })), 'feed');
    const styled = Object.keys(r.edgeStyleMap);
    g.edges.forEach(e => ok('kind ' + e.data.kind + ' is styled',
                            styled.indexOf(r.edgeTypeAccessor({ getData: () => e.data })) !== -1));
    eq('Provenance puts a source with the other events\' elements',
       g.nodes.filter(n => n.data.type === 'feed').map(n => n.data.scope), ['foreign', 'foreign']);
});

test('5b: no write reaches a source node, nor a pivot one that names no events', async () => {
    const g = await withEditor();
    const feed = pnode({ type: 'feed', source_id: '1', scope: 'foreign', label: 'F' });
    const offered = g.opts.pivots.filter(p => p.appliesTo && p.appliesTo([feed]).length);
    eq('no pivot applies', offered.map(p => p.id), []);
    ok('nothing draws from a feed', !g.opts.callbacks.isValidConnection(feed, own.objA));
    ok('nor to one', !g.opts.callbacks.isValidConnection(own.objA, feed));
    const fe = pedge('fc:1', own.attrE1, feed, { kind: 'feed-correlation', label: '' });
    const d = await g.opts.callbacks.onBeforeDelete({ nodes: [], edges: [fe], confirm: () => Promise.resolve(true) });
    eq('its edge is spared, not deleted', d, { accept: true, edges: [] });
});

/* ─────────────── feed events: the events of a MISP feed ─────────────── */

// Each hit names the feed events its value is in; the map holds them all.
function feedEventsEvent() {
    return ev({
        Feed: [FEED1, FEED9],
        Attribute: [attr({ uuid: 'e1', value: 'linked', Feed: [{ id: '1', name: 'CIRCL OSINT Feed', event_uuids: ['u1'] }] }),
                    attr({ uuid: 'e2', value: 'loose', Feed: [{ id: '1', name: 'CIRCL OSINT Feed', event_uuids: ['u2'] }] })],
        Object: [
            obj({ uuid: 'A', ObjectReference: [ref({ referenced_uuid: 'e1', referenced_type: '0' })],
                  Attribute: [attr({ uuid: 'c1', Feed: [{ id: '1', name: 'CIRCL OSINT Feed', event_uuids: ['u1', 'u2'] }, FEED9] })] }),
        ],
    });
}

const CARD_U1 = {
    uuid: 'u1', info: 'ThreatFox IOCs for 2026-09-03', date: '2026-09-03',
    Orgc: { name: 'abuse.ch', uuid: 'O2' },
    Tag: [{ name: 'tlp:white', colour: '#ffffff' }],
    Galaxy: [{ type: 'malpedia', GalaxyCluster: [{ value: 'Cobalt Strike' }] }],
};

function withFeedEvents(onPost) {
    return buildGraph(feedEventsEvent(), {
        routes: [[/feeds\/manifestEvents\.json$/, init => {
            if (onPost) onPost(JSON.parse(init.body));
            return { events: { '1': { u1: CARD_U1 } } };
        }]],
    }).then(g => new Promise(res => setTimeout(() => res(g), 0)));
}

test('feed events: a MISP feed wears the number of its events this event\'s values are in', async () => {
    const g = await withFeedEvents();
    eq('the MISP feed', potentials(g, 'feed:1'), [['feed-events', 2]]);
    eq('a CSV feed names no events', potentials(g, 'feed:9'), []);
    const p = pivot(g, 'feed-events');
    const nodes = [pnode(byId(g.nodes, 'feed:1').data), pnode(byId(g.nodes, 'feed:9').data),
                   pnode({ type: 'attribute', uuid: 'e1' })];
    eq('it applies to the MISP feed alone', p.appliesTo(nodes).map(n => n.getData().source_id), ['1']);
    eq('summarize is its events', p.summarize(p.appliesTo(nodes)), { total: 2 });
    eq('capped at the canvas budget, never saved', [p.maxCandidates, p.save], [1500, undefined]);
});

test('feed events: each lands as a cached event card, joined to its feed and its attributes', async () => {
    let body = null;
    const g = await withFeedEvents(b => { body = b; });
    const r = await pivot(g, 'feed-events').fetch([pnode(byId(g.nodes, 'feed:1').data)], {}, {});
    eq('it asks for the feed\'s events by uuid', body, { feeds: { '1': ['u1', 'u2'] } });
    eq('two feed events, and the attribute not drawn yet',
       r.nodes.map(n => n.id), ['feed-event:1:u1', 'feed-event:1:u2', 'attr:e2']);
    const d = r.nodes[0].data;
    eq('the card is drawn from the manifest entry',
       [d.type, d.label, d.date, d.orgc, d.tags, d.context],
       ['event', 'ThreatFox IOCs for 2026-09-03', '2026-09-03', { name: 'abuse.ch', uuid: 'O2' },
        [{ name: 'tlp:white', colour: '#ffffff' }], [{ galaxy_type: 'malpedia', value: 'Cobalt Strike' }]]);
    eq('as a cached hit of that feed', [d._provenance, d.source.kind, d.source.name, d.feed_id, d.scope],
       ['feed', 'feed', 'CIRCL OSINT Feed', '1', 'foreign']);
    eq('without a manifest entry it is its uuid', [r.nodes[1].data.label, r.nodes[1].data.orgc], ['u2', undefined]);
    eq('the feed holds its events; each event holds the values it was seen with',
       r.edges.map(e => [e.from, e.to, e.data.kind]),
       [['feed:1', 'feed-event:1:u1', 'feed-event'], ['feed:1', 'feed-event:1:u2', 'feed-event'],
        ['feed-event:1:u1', 'attr:e1', 'feed-correlation'], ['feed-event:1:u2', 'attr:e2', 'feed-correlation'],
        ['feed-event:1:u1', 'attr:c1', 'feed-correlation'], ['feed-event:1:u2', 'attr:c1', 'feed-correlation']]);
});

test('feed events: a feed listed once per lookup batch is one feed with all their events', async () => {
    const payload = feedEventsEvent();
    payload.Event.Feed = [Object.assign({}, FEED1, { event_uuids: ['u1'] }), FEED9,
                          Object.assign({}, FEED1, { event_uuids: ['u2'] })];
    const g = await buildGraph(payload);
    eq('its node counts both batches', byId(g.nodes, 'feed:1').data.feed_events, 2);
    eq('and so does its badge', potentials(g, 'feed:1'), [['feed-events', 2]]);
});

test('feed events: one previews in its feed, in a new tab', async () => {
    const g = await buildGraph(ev({ Object: [obj({ uuid: 'A' })] }), { baseurl: '/misp' });
    eq('feed events only', [shows(g, 'Preview in feed', { type: 'event', feed_id: '1', uuid: 'u1' }),
                            shows(g, 'Preview in feed', { type: 'event', event_id: '7', uuid: 'R7' }),
                            shows(g, 'Preview in feed', { type: 'feed', source_id: '1' })], [true, false, false]);
    menuItem(g, 'Preview in feed').onclick({}, pnode({ type: 'event', feed_id: '1', uuid: 'u1' }));
    eq('previewEvent', g.win.opened, [['/misp/feeds/previewEvent/1/u1', '_blank', 'noopener']]);
});

/* ─────────────────────── tags and galaxy clusters ─────────────────────── */

const TAG_TLP = { id: '1', name: 'tlp:amber', colour: '#ffc000', is_galaxy: false, local: false };
const TAG_LUMMA = { id: '2', name: 'LummaC2', colour: '#5000fa', is_galaxy: false, local: true };
const TAG_APT = { id: '3', name: 'misp-galaxy:threat-actor="APT28"', colour: '#0088cc', is_galaxy: true };
const GAL_APT = { type: 'threat-actor', name: 'Threat Actor', GalaxyCluster: [
    { uuid: 'CL-APT28', value: 'APT28', tag_name: 'misp-galaxy:threat-actor="APT28"' }] };

function taggedEvent() {
    return ev({
        Attribute: [
            attr({ uuid: 'e1', value: 'lumma.example', Tag: [TAG_LUMMA, TAG_APT], Galaxy: [GAL_APT],
                   Relationship: [arel({ object_uuid: 'e1', related_object_uuid: 'A' })] }),
            attr({ uuid: 'e2', value: 'plain' }),
        ],
        Object: [obj({ uuid: 'A', Attribute: [
            attr({ uuid: 'c1', value: '1.2.3.4', Tag: [TAG_TLP, TAG_APT], Galaxy: [GAL_APT] }),
            attr({ uuid: 'c2', value: 'untagged' }),
        ] })],
    });
}

test('tags: an attribute carries its tags and its clusters, a cluster keyed by its tag', async () => {
    const g = await buildGraph(taggedEvent());
    const d = byId(g.nodes, 'attr:e1').data;
    eq('the plain tag, marked local', d.tags.map(t => [t.name, t.colour, !!t.local]), [['LummaC2', '#5000fa', true]]);
    eq('the galaxy tag is a cluster, not a tag',
       d.clusters.map(c => [c.tag_name, c.galaxy_type, c.galaxy_name, c.value, c.uuid]),
       [['misp-galaxy:threat-actor="APT28"', 'threat-actor', 'Threat Actor', 'APT28', 'CL-APT28']]);
    ok('an untagged attribute carries neither',
       ['tags', 'clusters'].every(k => byId(g.nodes, 'obj:A').children[1].data[k] === undefined));
});

test('tags: a cluster named only by its tag still reads its galaxy and value', async () => {
    const g = await buildGraph(ev({ Attribute: [attr({ uuid: 'e1', Tag: [TAG_APT],
        Relationship: [arel({ object_uuid: 'e1', related_object_uuid: 'EV-SELF', related_object_type: 'Event' })] })] }));
    eq('parsed off misp-galaxy:TYPE="VALUE"',
       byId(g.nodes, 'attr:e1').data.clusters.map(c => [c.galaxy_type, c.value, c.galaxy_name]),
       [['threat-actor', 'APT28', undefined]]);
});

test('tags: a hidden tag is left out', async () => {
    const g = await buildGraph(ev({ Attribute: [attr({ uuid: 'e1',
        Tag: [Object.assign({}, TAG_TLP, { hide_tag: true })],
        Relationship: [arel({ object_uuid: 'e1', related_object_uuid: 'EV-SELF', related_object_type: 'Event' })] })] }));
    eq('nothing', byId(g.nodes, 'attr:e1').data.tags, undefined);
});

test('tags: the sidebar lists them, and a tagged attribute wears a tag badge', async () => {
    const g = await buildGraph(taggedEvent());
    const rows = props(g, byId(g.nodes, 'attr:e1').data);
    ok('Tags', rows.indexOf('Tags: LummaC2 (local)') !== -1, rows);
    ok('Galaxy clusters', rows.indexOf('Galaxy clusters: Threat Actor: APT28') !== -1, rows);
    const b = badgesOf(g, byId(g.nodes, 'attr:e1').data);
    eq('one badge, bottom-right, in the first tag\'s colour', b.map(x => [x.position, x.iconClass, x.color]),
       [['se', 'fas fa-tag', '#5000fa']]);
    eq('its title names them all', b[0].title, 'LummaC2\nThreat Actor: APT28');
    eq('no badge without tags', badgesOf(g, { type: 'attribute', value: 'x' }), []);
    eq('an event card draws its own', badgesOf(g, { type: 'event', tags: [{ name: 'x' }] }), []);
    eq('a tag node reads as a tag', props(g, { type: 'tag', name: 'tlp:amber' }), ['Tag: tlp:amber']);
    eq('a cluster node reads as a cluster',
       props(g, { type: 'cluster', value: 'APT28', galaxy_name: 'Threat Actor', tag_name: 't', uuid: 'U' }),
       ['Cluster: APT28', 'Galaxy: Threat Actor', 'Tag: t', 'UUID: U']);
});

test('tags: a tag draws as the taxonomy entity and the legend calls it a tag', async () => {
    const g = await buildGraph(taggedEvent());
    eq('style key', g.opts.render.nodeTypeAccessor(pnode({ type: 'tag' })), 'taxonomy');
    ok('drawn by misp-pivot-nodes', !!g.opts.render.nodeStyleMap.taxonomy && !!g.opts.render.nodeStyleMap.cluster);
    const legend = g.opts.UI.legend.sections[0].entries({ getMutableNodes: () => [
        pnode({ type: 'tag' }), pnode({ type: 'cluster' }), pnode({ type: 'attribute' })] });
    eq('labels', legend.map(e => [e.id, e.label]),
       [['taxonomy', 'tag'], ['cluster', 'galaxy cluster'], ['attribute', 'attribute']]);
});

test('tags: the pivot applies to what carries a tag, an object through its attributes', async () => {
    const g = await buildGraph(taggedEvent());
    const p = pivot(g, 'tags');
    const nodes = ['attr:e1', 'obj:A', 'attr:c2'].map(id => g.graph.getMutableNode(id));
    eq('the plain attribute is left out', p.appliesTo(nodes).map(n => n.id), ['attr:e1', 'obj:A']);
    eq('distinct tags and clusters: LummaC2, APT28, tlp:amber', p.summarize(p.appliesTo(nodes)), { total: 3 });
    eq('capped, never saved', [p.maxCandidates, p.save], [1500, undefined]);
    eq('each carrier wears its count as rim potential',
       [potentials(g, 'attr:e1'), potentials(g, 'obj:A'), potentials(g, 'attr:c2')],
       [[['tags', 2]], [['tags', 2]], []]);
});

test('tags: one node per tag or cluster, joined to every carrier on the canvas', async () => {
    const g = await buildGraph(taggedEvent());
    const r = pivot(g, 'tags').fetch([g.graph.getMutableNode('attr:e1')], {}, {});
    eq('the selection\'s tag and cluster', r.nodes.map(n => [n.id, n.data.type, n.data.label]),
       [['tag:LummaC2', 'tag', 'LummaC2'], ['cluster:misp-galaxy:threat-actor="APT28"', 'cluster', 'APT28']]);
    eq('the cluster node is what its drawing reads',
       [r.nodes[1].data.galaxy_type, r.nodes[1].data.galaxy_name, r.nodes[1].data.value],
       ['threat-actor', 'Threat Actor', 'APT28']);
    eq('the object\'s attribute already on the canvas shares the cluster, so it is joined too',
       r.edges.map(e => [e.from, e.to, e.data.kind]),
       [['attr:e1', 'tag:LummaC2', 'tag'], ['attr:e1', 'cluster:misp-galaxy:threat-actor="APT28"', 'tag'],
        ['attr:c1', 'cluster:misp-galaxy:threat-actor="APT28"', 'tag']]);
    ok('tlp:amber was not asked for', !r.nodes.some(n => n.id === 'tag:tlp:amber'));
});

test('tags: a tag\'s relationship type labels its edge', async () => {
    const g = await buildGraph(ev({ Attribute: [attr({ uuid: 'e1',
        Tag: [Object.assign({}, TAG_TLP, { relationship_type: 'classified-as' })],
        Relationship: [arel({ object_uuid: 'e1', related_object_uuid: 'EV-SELF', related_object_type: 'Event' })] })] }));
    const r = pivot(g, 'tags').fetch([g.graph.getMutableNode('attr:e1')], {}, {});
    eq('label', r.edges.map(e => e.data.label), ['classified-as']);
});

test('tags: another event\'s card is a carrier, its galaxy tags read as clusters', async () => {
    const g = await buildGraph(ev({ Object: [obj({ uuid: 'A' })] }));
    const card = pnode({ type: 'event', tags: [{ name: 'tlp:white' }],
                         clusters: [{ tag_name: 'misp-galaxy:threat-actor="APT28"', value: 'APT28' }] });
    card.id = 'event:R1';
    const r = pivot(g, 'tags').fetch([card], {}, {});
    eq('both', r.nodes.map(n => n.id), ['tag:tlp:white', 'cluster:misp-galaxy:threat-actor="APT28"']);
    eq('joined to the card', r.edges.map(e => e.from), ['event:R1', 'event:R1']);
});

test('tags: a tag edge is not deleted in MISP', async () => {
    const g = await buildGraph(taggedEvent());
    const del = g.opts.callbacks.onBeforeDelete;
    const res = await del({ nodes: [], edges: [{ getData: () => ({ kind: 'tag' }) }], confirm: () => true });
    eq('it stays, hide it instead', res, { accept: true, edges: [] });
});

/* ─────────────── from a tag or cluster to other events ─────────── */

const tagNode = (id, data) => Object.assign(pnode(data), { id });
const TLP_NODE = () => tagNode('tag:tlp:amber', { type: 'tag', name: 'tlp:amber' });
const APT_NODE = () => tagNode('cluster:' + TAG_APT.name,
    { type: 'cluster', tag_name: TAG_APT.name, value: 'APT28', uuid: 'CL-APT28' });

// As /events/taggedEvents shapes it: newest first, each card saying how it
// carries each asked-for tag.
const TAGGED = {
    total: 4812,
    events: [
        { id: '812', uuid: 'R812', info: 'Tagged on the event', date: '2026-01-02',
          Orgc: { name: 'CIRCL', uuid: 'O1' },
          Tag: [{ name: 'tlp:amber', colour: '#ffc000', is_galaxy: false }],
          Galaxy: [{ type: 'threat-actor', GalaxyCluster: [{ value: 'APT28', tag_name: TAG_APT.name }] }],
          matched: { 'tlp:amber': 'event', [TAG_APT.name]: 'event' } },
        { id: '813', uuid: 'R813', info: 'Tagged on an attribute', Tag: [], Galaxy: [],
          matched: { 'tlp:amber': 'attribute' } },
    ],
};

function withTagged(route) {
    let bodies = [];
    return buildGraph(taggedEvent(), { routes: [[/events\/taggedEvents\/1\.json$/, init => {
        bodies.push(JSON.parse(init.body));
        return route ? route(init) : TAGGED;
    }]] }).then(g => Object.assign(g, { bodies }));
}

test('tagged events: offered on tag and cluster nodes only', async () => {
    const g = await withTagged();
    const p = pivot(g, 'tagged-events');
    const nodes = [TLP_NODE(), APT_NODE(), tagNode('cluster:x', { type: 'cluster' }),
                   g.graph.getMutableNode('attr:e1'), tagNode('event:R1', { type: 'event', tags: [{ name: 'x' }] })];
    eq('a tag, a cluster with its tag; not a cluster without one, an element or an event',
       p.appliesTo(nodes).map(n => n.id), ['tag:tlp:amber', 'cluster:' + TAG_APT.name]);
    eq('capped at the canvas budget, never saved', [p.maxCandidates, p.save], [1500, undefined]);
});

test('tagged events: one tag asks by name, counts at most the newest 200, offers no mode', async () => {
    const g = await withTagged();
    const s = await pivot(g, 'tagged-events').summarize([TLP_NODE()], {}, {});
    eq('the request', g.bodies, [{ tags: ['tlp:amber'], mode: 'and' }]);
    eq('the count is what the fetch brings', s, { total: 200 });
});

test('tagged events: several tags intersect by default, union on request', async () => {
    const g = await withTagged();
    const p = pivot(g, 'tagged-events');
    const s = await p.summarize([TLP_NODE(), APT_NODE()], {}, {});
    eq('a cluster asks by its tag', g.bodies[0], { tags: ['tlp:amber', TAG_APT.name], mode: 'and' });
    eq('one narrowing control, all of them first',
       s.facets.map(f => [f.key, f.type, f.options.map(o => o.value)]), [['mode', 'select', ['and', 'or']]]);
    await p.summarize([TLP_NODE(), APT_NODE()], { mode: 'or' }, {});
    eq('any of them', g.bodies[1].mode, 'or');
});

test('tagged events: summarize then fetch is one request', async () => {
    const g = await withTagged();
    const p = pivot(g, 'tagged-events');
    await p.summarize([TLP_NODE()], {}, {});
    await p.fetch([TLP_NODE()], {}, {});
    eq('asked once', g.bodies.length, 1);
    await p.fetch([TLP_NODE()], { mode: 'or' }, {});
    eq('a different question is asked again', g.bodies.length, 2);
});

test('tagged events: each lands as an event card, joined to the tags it carries', async () => {
    const g = await withTagged();
    const r = await pivot(g, 'tagged-events').fetch([TLP_NODE(), APT_NODE()], { mode: 'or' }, {});
    eq('cards, keyed like every other event', r.nodes.map(n => [n.id, n.data.type, n.data.label, n.data.event_id]),
       [['event:R812', 'event', 'Tagged on the event', '812'], ['event:R813', 'event', 'Tagged on an attribute', '813']]);
    eq('a card reads its clusters by tag, so the Tags & clusters pivot finds them',
       r.nodes[0].data.clusters.map(c => c.tag_name), [TAG_APT.name]);
    eq('event-level unlabelled, attribute-level said, and only for what each carries',
       r.edges.map(e => [e.from, e.to, e.data.kind, e.data.label]),
       [['event:R812', 'tag:tlp:amber', 'tag', ''],
        ['event:R812', 'cluster:' + TAG_APT.name, 'tag', ''],
        ['event:R813', 'tag:tlp:amber', 'tag', 'via attribute']]);
    eq('an edge the Tags & clusters pivot would draw has the same id',
       r.edges[0].id, 'tagged:event:R812>tag:tlp:amber');
});

test('tagged events: a card is joined to the other tags already drawn, not only the selected one', async () => {
    const g = await withTagged();
    g.graph.liveNode({ id: 'tag:tlp:amber', data: { type: 'tag', name: 'tlp:amber' } });
    const r = await pivot(g, 'tagged-events').fetch([APT_NODE()], {}, {});
    eq('R812 carries tlp:amber too',
       r.edges.map(e => [e.from, e.to, e.data.label]),
       [['event:R812', 'cluster:' + TAG_APT.name, ''], ['event:R812', 'tag:tlp:amber', '']]);
});

test('tags: an element landing after its tag is joined to it', async () => {
    // Over the budget, so the object is not drawn and is on offer.
    const g = await buildGraph(ev({ Object: fillers(1500).concat([obj({ uuid: 'A', Attribute: [
        attr({ uuid: 'c1', value: '1.2.3.4', Tag: [TAG_TLP] }),
        attr({ uuid: 'c2', value: 'untagged', Tag: [TAG_LUMMA] })] })]) }));
    g.graph.liveNode({ id: 'tag:tlp:amber', data: { type: 'tag', name: 'tlp:amber' } });
    const r = pivot(g, 'event-elements').fetch([], { q: '1.2.3.4' }, {});
    eq('the object is offered', r.nodes.map(n => n.id), ['obj:A']);
    ok('the object\'s attribute rides in joined to the drawn tag',
       r.edges.some(e => e.from === 'attr:c1' && e.to === 'tag:tlp:amber' && e.data.kind === 'tag'),
       r.edges.map(e => e.id));
    ok('nothing to a tag not drawn', !r.edges.some(e => e.to === 'tag:LummaC2'));
});

test('tags: a cluster landing from another pivot is joined to the carriers already drawn', async () => {
    const g = await withRelations(() => ({ relations: [{ relation: 'similar',
        cluster: { uuid: 'CL-APT28', value: 'APT28', type: 'threat-actor', tag_name: TAG_APT.name } }] }));
    const src = tagNode('cluster:misp-galaxy:x="Y"', { type: 'cluster', tag_name: 'misp-galaxy:x="Y"', uuid: 'CL-Y' });
    const r = await pivot(g, 'related-clusters').fetch([src], {}, {});
    eq('APT28 lands with its carriers on the canvas',
       r.edges.filter(e => e.data.kind === 'tag').map(e => e.from).sort(), ['attr:c1', 'attr:e1']);
});

test('tagged events: a failed request is not kept', async () => {
    let fail = true;
    const g = await withTagged(() => (fail ? { __status: 500 } : TAGGED));
    const p = pivot(g, 'tagged-events');
    let threw = false;
    await p.summarize([TLP_NODE()], {}, {}).catch(() => { threw = true; });
    ok('the failure reaches the library', threw);
    fail = false;
    eq('asked again', (await p.summarize([TLP_NODE()], {}, {})).total, 200);
});

/* ─────────────────── a cluster's galaxy relations ───────────────── */

const RELATIONS = {
    relations: [
        { relation: 'similar', cluster: { id: '47753', uuid: 'CL-G0007', value: 'APT28 - G0007',
            type: 'mitre-intrusion-set', galaxy_name: 'MITRE ATT&CK Groups',
            tag_name: 'misp-galaxy:mitre-intrusion-set="APT28 - G0007"' } },
        { relation: 'uses', cluster: { id: '5', uuid: 'CL-X', value: 'X-Agent', type: 'tool',
            galaxy_name: 'Tool', tag_name: 'misp-galaxy:tool="X-Agent"' } },
        { relation: 'similar', cluster: { id: '72580', uuid: 'CL-APT28', value: 'APT28', type: 'threat-actor',
            tag_name: TAG_APT.name } },
    ],
};

function withRelations(route) {
    return buildGraph(taggedEvent(), { routes: [[/galaxy_clusters\/relatedClusters\/[^/]+\.json$/,
        route || (() => RELATIONS)]] });
}

test('related clusters: offered on a cluster node that has a uuid', async () => {
    const g = await withRelations();
    const p = pivot(g, 'related-clusters');
    const nodes = [APT_NODE(), tagNode('cluster:y', { type: 'cluster', tag_name: 'y' }), TLP_NODE()];
    eq('a cluster parsed from a bare tag has none', p.appliesTo(nodes).map(n => n.id), ['cluster:' + TAG_APT.name]);
    eq('the count is the relations', await p.summarize([APT_NODE()], {}, {}), { total: 3 });
    ok('asked by uuid', g.fetchLog.some(f => /\/galaxy_clusters\/relatedClusters\/CL-APT28\.json$/.test(f.url)));
});

test('related clusters: each target lands as a cluster node, the edge naming the relation', async () => {
    const g = await withRelations();
    const p = pivot(g, 'related-clusters');
    await p.summarize([APT_NODE()], {}, {});
    const r = await p.fetch([APT_NODE()], {}, {});
    eq('asked once per cluster', g.fetchLog.filter(f => /relatedClusters/.test(f.url)).length, 1);
    eq('keyed by tag, so a cluster already drawn merges',
       r.nodes.map(n => [n.id, n.data.type, n.data.label, n.data.galaxy_type, n.data.galaxy_name, n.data.uuid]),
       [['cluster:misp-galaxy:mitre-intrusion-set="APT28 - G0007"', 'cluster', 'APT28 - G0007',
         'mitre-intrusion-set', 'MITRE ATT&CK Groups', 'CL-G0007'],
        ['cluster:misp-galaxy:tool="X-Agent"', 'cluster', 'X-Agent', 'tool', 'Tool', 'CL-X']]);
    eq('away from the selected cluster; a relation to its own tag is left out',
       r.edges.map(e => [e.from, e.to, e.data.kind, e.data.label]),
       [['cluster:' + TAG_APT.name, 'cluster:misp-galaxy:mitre-intrusion-set="APT28 - G0007"', 'cluster-relation', 'similar'],
        ['cluster:' + TAG_APT.name, 'cluster:misp-galaxy:tool="X-Agent"', 'cluster-relation', 'uses']]);
});

test('related clusters: a galaxy relation is its own edge kind', async () => {
    const g = await withRelations();
    const style = g.opts.render.edgeStyleMap['cluster-relation'];
    eq('solid, in the galaxy hue', [style.dashed, style.strokeColor],
       [undefined, g.win.MispPivotNodes.palette().galaxy.core]);
    eq('the sidebar names it', g.opts.UI.propertiesPanel.edgePropertiesMap(
        { getData: () => ({ kind: 'cluster-relation', label: 'uses' }) }), [{ name: 'Link', value: 'Galaxy relation' }]);
});

test('related clusters: a failed request is not kept', async () => {
    let fail = true;
    const g = await withRelations(() => (fail ? { __status: 404 } : RELATIONS));
    const p = pivot(g, 'related-clusters');
    let threw = false;
    await p.summarize([APT_NODE()], {}, {}).catch(() => { threw = true; });
    ok('the failure reaches the library', threw);
    fail = false;
    eq('asked again', (await p.summarize([APT_NODE()], {}, {})).total, 3);
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
