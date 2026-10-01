// Analyst Graph — a stored analyst graph drawn by the Pivot Explorer, its
// third host after the event page and the value Neighbourhood.
//
// The graph's `data` payload (/analyst_graphs/data/<uuid>.json) holds the
// document, the records of the nodes the viewer may read, shaped as the
// explorer's builders read them, and the edges MISP holds between them. This
// file turns it into pivotick data, and reads the document back off the
// canvas for a save.
//
//   MispAnalystGraph.mount(config)   → handle, the graph drawn into config.containerEl
//   MispAnalystGraph.graphData(payload, kit)
//
// config: { graph (uuid), containerEl, loaderEl?, payload?, request, baseurl,
//           labelPlan?, permitted?, orgUuid?, siteAdmin?, valueCard?, canEnrich?, text? }
// request(method, path, body) → Promise<data> is IntelGraph's.
//
// Loaded by IntelGraph.load(), after pivotick.iife, misp-pivot-nodes, the
// sidebar and pivot-explorer.

(function () {
    'use strict';

    var ID_PREFIX = { Event: 'event:', Object: 'obj:', Attribute: 'attr:' };

    function lower(uuid) {
        return String(uuid || '').toLowerCase();
    }

    function nodeKey(node) {
        return node.type + ':' + lower(node.uuid);
    }

    function valueNodeData(v) {
        return {
            type:        'value',
            label:       v.value,
            value:       v.value,
            b64:         v.b64,
            uuid:        v.uuid,
            description: 'Value',
            near_label:  'Value'
        };
    }

    function byUuid(list) {
        var out = {};
        (list || []).forEach(function (r) { out[lower(r.uuid)] = r; });
        return out;
    }

    function hasPosition(n) {
        return typeof n.x === 'number' && typeof n.y === 'number';
    }

    // The payload as pivotick data, and how its ids map onto the document.
    function graphData(payload, kit) {
        var land = kit.landing();
        var doc = payload.document || { nodes: [] };
        var cards = payload.events || {};
        var cardByUuid = {};
        Object.keys(cards).forEach(function (id) { cardByUuid[lower(cards[id].uuid)] = cards[id]; });
        var objects = byUuid(payload.Object);
        var attributes = byUuid(payload.Attribute);
        var clusters = byUuid(payload.GalaxyCluster);
        var values = byUuid(payload.Value);
        kit.mergePriorities(payload.ui_priorities);

        function owner(rec) {
            var card = cards[rec.event_id] || {};
            return kit.provenance(rec.event_id, card.uuid);
        }

        // The objects drawn, so an attribute node inside one is drawn as its
        // child rather than a second node of the same id.
        var drawnObject = {};
        var parentOf = {};
        doc.nodes.forEach(function (n) {
            if (n.type !== 'Object' || !objects[lower(n.uuid)]) return;
            var obj = objects[lower(n.uuid)];
            drawnObject[lower(obj.uuid)] = kit.foreignObjectNode(obj, owner(obj));
            (obj.Attribute || []).forEach(function (a) { parentOf[lower(a.uuid)] = 'obj:' + lower(obj.uuid); });
        });

        var idOf = {}, keyOf = {}, folded = {}, clusterIdOf = {}, valueIdOf = {};
        var positioned = 0, drawn = 0;
        doc.nodes.forEach(function (n) {
            var uuid = lower(n.uuid);
            var node = null;
            switch (n.type) {
                case 'Event':
                    if (cardByUuid[uuid]) node = kit.eventCardNode(cardByUuid[uuid]);
                    break;
                case 'Object':
                    node = drawnObject[uuid] || null;
                    break;
                case 'Attribute':
                    if (!attributes[uuid]) break;
                    if (parentOf[uuid]) {
                        var id = 'attr:' + uuid;
                        var parent = drawnObject[parentOf[uuid].slice(4)];
                        parent.children.forEach(function (c) {
                            if (c.id === id) c.data.graph_node = true;
                        });
                        idOf[nodeKey(n)] = id;
                        keyOf[id] = nodeKey(n);
                        folded[id] = parentOf[uuid];
                        return;
                    }
                    node = { id: 'attr:' + uuid, data: kit.attributeNodeData(attributes[uuid], owner(attributes[uuid])) };
                    break;
                case 'GalaxyCluster':
                    if (clusters[uuid]) {
                        node = kit.clusterNode('cluster:' + clusters[uuid].tag_name, clusters[uuid]);
                        clusterIdOf[uuid] = node.id;
                    }
                    break;
                case 'Value':
                    if (values[uuid]) {
                        node = { id: 'value:' + values[uuid].b64, data: valueNodeData(values[uuid]) };
                        valueIdOf[uuid] = node.id;
                    }
                    break;
            }
            if (!node) return;
            node.data.graph_node = true;
            if (hasPosition(n)) {
                node.x = n.x;
                node.y = n.y;
                positioned++;
                if (n.pinned) {
                    node.fx = n.x;
                    node.fy = n.y;
                }
            }
            drawn++;
            land.node(node);
            idOf[nodeKey(n)] = node.id;
            keyOf[node.id] = nodeKey(n);
        });

        function endId(end) {
            var at = end.indexOf(':');
            var type = end.slice(0, at), uuid = lower(end.slice(at + 1));
            if (ID_PREFIX[type]) return ID_PREFIX[type] + uuid;
            if (type === 'GalaxyCluster') return clusterIdOf[uuid] || null;
            if (type === 'Value') return valueIdOf[uuid] || null;
            return null;
        }

        var hidden = {};
        (doc.hidden_edges || []).forEach(function (id) { hidden[id] = true; });
        (payload.edges || []).forEach(function (e) {
            if (e.kind === 'contains' || hidden[e.id]) return;
            var from = endId(e.from), to = endId(e.to);
            if (!from || !to) return;
            var data = { kind: e.kind, label: e.label || '' };
            if (e.kind === 'object-reference') {
                Object.assign(data, { uuid: e.uuid, relationship_type: e.label });
            } else if (e.kind === 'relationship') {
                Object.assign(data, { kind: 'analyst-relationship', uuid: e.uuid, relationship_type: e.label,
                                      authors: e.authors, orgc: e.orgc_uuid });
            }
            land.edge({ id: e.id, from: from, to: to, data: data });
        });

        var out = land.result();
        return {
            data: out,
            idOf: idOf,
            keyOf: keyOf,
            folded: folded,
            // Every node drawn sits where it was saved: open it as it was left.
            positioned: drawn > 0 && positioned === drawn
        };
    }

    // The records as the explorer's payload, so the sidebar and the pivots
    // read the record behind every node.
    function payloadOf(payload) {
        return { Event: { Attribute: payload.Attribute || [], Object: payload.Object || [] } };
    }

    function round(n) {
        return Math.round(n * 10) / 10;
    }

    // A node brought onto the canvas, as the document names it.
    function documentNode(node) {
        var d = node.getData() || {};
        var at = node.id.indexOf(':');
        var prefix = node.id.slice(0, at + 1);
        if (prefix === 'event:') return { type: 'Event', uuid: lower(d.uuid || node.id.slice(at + 1)) };
        if (prefix === 'obj:') return { type: 'Object', uuid: lower(d.uuid || node.id.slice(at + 1)) };
        if (prefix === 'attr:') return { type: 'Attribute', uuid: lower(d.uuid || node.id.slice(at + 1)) };
        if (prefix === 'cluster:' && d.uuid) return { type: 'GalaxyCluster', uuid: lower(d.uuid) };
        if (prefix === 'value:' && d.value != null) return { type: 'Value', value: String(d.value) };
        return null;
    }

    function mount(config) {
        var state = {
            uuid: lower(config.graph),
            payload: null,
            built: null,
            revision: null,
            kept: []
        };
        var request = config.request;
        var explorer = null;

        function fetchData() {
            return request('GET', '/analyst_graphs/data/' + encodeURIComponent(state.uuid) + '.json');
        }

        function take(payload, kit) {
            state.payload = payload;
            state.built = graphData(payload, kit);
            return state.built;
        }

        function load(kit) {
            var first = config.payload ? Promise.resolve(config.payload) : fetchData();
            return first.then(function (payload) {
                take(payload, kit);
                state.revision = payload.Graph.revision;
                return { raw: payload, event: payloadOf(payload), data: state.built.data };
            });
        }

        function options(opts, kit, seed) {
            Object.assign(opts.render.edgeStyleMap, {
                value: { strokeColor: '#8a8f98', dashed: true, markerEnd: 'none' }
            });
            if (state.built.positioned) {
                opts.simulation.warmupTicks = 0;
                opts.simulation.d3Alpha = 0.05;
            }
            opts.UI.extraPanels = [kit.sharedPanel()];
            var dbclick = opts.callbacks.onNodeDbclick;
            opts.callbacks.onNodeDbclick = function (e, node) {
                var d = node && node.getData ? node.getData() : null;
                if (d && d.type === 'value') {
                    window.location.href = config.baseurl + '/values/view/' + encodeURIComponent(d.b64);
                    return;
                }
                dbclick(e, node);
            };
        }

        function afterMount(graph) {
            state.payload.document.nodes.forEach(function (n) {
                if (!n.pinned) return;
                var id = state.built.idOf[nodeKey(n)];
                var node = id && !state.built.folded[id] ? graph.getMutableNode(id) : null;
                if (node) node.freeze();
            });
        }

        explorer = window.MispPivotExplorer.create({
            containerEl: config.containerEl,
            loaderEl:    config.loaderEl || null,
            fitHeight:   false,
            provenance:  false,
            config: {
                baseurl:   config.baseurl,
                labelPlan: config.labelPlan,
                permitted: config.permitted,
                orgUuid:   config.orgUuid,
                siteAdmin: config.siteAdmin,
                valueCard: config.valueCard,
                canEnrich: config.canEnrich,
                text:      config.text
            },
            load: load,
            pivots: function (kit) {
                var p = kit.pivots;
                return [p.tags(), p.taggedEvents(), p.relatedClusters(), p.surroundings(),
                        p.cardElements('ids'), p.cardElements('network'), p.cardElements('all'),
                        p.enrich()].filter(Boolean);
            },
            options: options,
            afterMount: afterMount
        });

        function graph() {
            return explorer.graph();
        }

        // The document's nodes still on the canvas, where they now sit;
        // the ones not drawn, as stored; then what the user kept.
        function documentOf() {
            var g = graph();
            var doc = state.payload.document;
            var built = state.built;
            var nodes = [];
            doc.nodes.forEach(function (n) {
                var id = built.idOf[nodeKey(n)];
                if (!id) {
                    nodes.push(n);
                    return;
                }
                var onCanvas = g.getMutableNode(built.folded[id] || id);
                if (!onCanvas) return;
                if (built.folded[id]) {
                    nodes.push(n);
                    return;
                }
                var out = Object.assign({}, n);
                delete out.pinned;
                if (typeof onCanvas.x === 'number' && typeof onCanvas.y === 'number') {
                    out.x = round(onCanvas.x);
                    out.y = round(onCanvas.y);
                }
                if (onCanvas.frozen) out.pinned = true;
                nodes.push(out);
            });
            var seen = {};
            nodes.forEach(function (n) { seen[n.type === 'Value' ? 'Value:' + n.value : nodeKey(n)] = true; });
            state.kept.forEach(function (id) {
                var node = g.getMutableNode(id);
                var n = node && documentNode(node);
                if (!n) return;
                var key = n.type === 'Value' ? 'Value:' + n.value : nodeKey(n);
                if (seen[key]) return;
                seen[key] = true;
                if (typeof node.x === 'number') {
                    n.x = round(node.x);
                    n.y = round(node.y);
                }
                if (node.frozen) n.pinned = true;
                nodes.push(n);
            });
            return {
                version: doc.version || 1,
                nodes: nodes,
                hidden_edges: (doc.hidden_edges || []).slice(),
                view: doc.view || {}
            };
        }

        // Pivoted-in nodes join the document on the next save.
        function keep(ids) {
            ids.forEach(function (id) {
                if (state.kept.indexOf(id) === -1 && !state.built.keyOf[id]) state.kept.push(id);
            });
        }

        function save() {
            return request('POST', '/analyst_graphs/save/' + encodeURIComponent(state.uuid) + '.json',
                           { content: documentOf(), revision: state.revision })
            .then(function (report) {
                state.revision = report.revision;
                state.kept = [];
                return refresh().then(function () { return report; });
            });
        }

        // Re-read the graph and change the canvas in place: what the document
        // gained is added, what it lost is removed, everything else stays where
        // it is.
        function refresh() {
            var g = graph();
            return fetchData().then(function (payload) {
                var before = state.built;
                var after = take(payload, explorer.kit);
                after.data.nodes.forEach(function (n) {
                    if (!g.getMutableNode(n.id)) g.addNode(n);
                });
                Object.keys(before.keyOf).forEach(function (id) {
                    if (!after.keyOf[id] && !before.folded[id] && state.kept.indexOf(id) === -1) g.removeNode(id);
                });
                after.data.edges.forEach(function (e) {
                    if (!g.getMutableEdge(e.id) && g.getMutableNode(e.from) && g.getMutableNode(e.to)) g.addEdge(e);
                });
                return handle;
            });
        }

        // An add or a removal made to this graph elsewhere. The base revision
        // moves only when that change is the one right after it.
        function followed(report) {
            if (!report || !report.changed) return Promise.resolve(handle);
            if (report.revision === state.revision + 1) state.revision = report.revision;
            return refresh();
        }

        var handle = {
            uuid:       function () { return state.uuid; },
            graph:      graph,
            kit:        function () { return explorer.kit; },
            payload:    function () { return state.payload; },
            canEdit:    function () { return !!(state.payload && state.payload.Graph._canEdit); },
            revision:   function () { return state.revision; },
            documentOf: documentOf,
            keep:       keep,
            save:       save,
            refresh:    refresh,
            followed:   followed,
            destroy:    function () {
                var g = graph();
                if (g && typeof g.destroy === 'function') g.destroy();
                if (config.containerEl) config.containerEl.innerHTML = '';
            }
        };
        handle.ready = explorer.init().then(function (ok) {
            if (!ok) throw new Error('The graph could not be drawn.');
            return handle;
        });
        return handle;
    }

    window.MispAnalystGraph = {
        mount:     mount,
        graphData: graphData,
        payloadOf: payloadOf
    };
}());
