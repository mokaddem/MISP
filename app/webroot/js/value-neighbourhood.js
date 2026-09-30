// Value Neighbourhood — a value's occurrences drawn as the Pivot Explorer
// draws an event: the objects and attributes holding it, their events, what
// references and analyst relationships reach from them, the feeds and servers
// that know it and the values close to it.
//
// Built on window.MispPivotExplorer (pivot-explorer.js): this file is the
// value's host — its seed, its pivots and its options — and the explorer's
// builders do the drawing.
//
//   MispValueNeighbourhood.explorer(config)  the full graph, into config.containerEl
//   MispValueNeighbourhood.graphData(seed, kit)
//
// config: { value, b64, baseurl, containerEl, loaderEl, labelPlan, permitted,
//           orgUuid, siteAdmin, text }

(function () {
    'use strict';

    var MORE_PIVOT  = 'more-occurrences';
    var WHERE_PIVOT = 'where-else';

    function valueNodeId(b64) {
        return 'value:' + b64;
    }

    function valueNodeData(v, centre) {
        return {
            type:        'value',
            label:       v.value,
            value:       v.value,
            b64:         v.b64,
            'attr-type': (v.types || [])[0],
            types:       v.types,
            centre:      centre || undefined,
            description: centre ? 'This value' : (v.description || 'Value')
        };
    }

    function nearLabel(n) {
        if (n.engine === 'cidr') return 'in /' + n.closeness;
        if (n.engine === 'ssdeep') return 'ssdeep ' + n.closeness;
        if (n.engine === 'typosquat') return n.closeness ? 'look-alike · ' + n.closeness : 'look-alike';
        return n.engine;
    }

    // The relations an object files the value under, from its held attributes.
    function heldRelations(obj) {
        var held = {};
        (obj.holds || []).forEach(function (uuid) { held[uuid] = true; });
        var names = {};
        (obj.Attribute || []).forEach(function (a) {
            if (held[a.uuid] && a.object_relation) names[a.object_relation] = true;
        });
        return Object.keys(names).sort().join(', ');
    }

    // Occurrences as the explorer lands elements: each object closed with its
    // attributes, each event-level attribute free, each beside its event card,
    // and every one joined to the value node that holds it.
    function landOccurrences(kit, land, slice, valueIdOf, kind) {
        kind = kind || 'occurrence';
        var cards = slice.events || {};
        kit.mergePriorities(slice.ui_priorities);
        function card(eventId) {
            var c = cards[eventId];
            return c ? land.node(kit.eventCardNode(c)) : null;
        }
        (slice.Object || []).forEach(function (obj) {
            var c = cards[obj.event_id] || {};
            var owner = kit.provenance(obj.event_id, c.uuid);
            var node = kit.foreignObjectNode(obj, owner);
            var held = {};
            (obj.holds || []).forEach(function (uuid) { held[uuid] = true; });
            node.children.forEach(function (child) {
                if (held[child.data.uuid]) child.data.holds_value = true;
            });
            var id = land.node(node);
            var cid = card(obj.event_id);
            if (cid) land.edge(kit.inEventEdge(id, cid));
            var vid = valueIdOf(obj);
            if (vid) {
                land.edge({ id: kind + ':' + vid + ':' + id, from: vid, to: id,
                            data: { kind: kind, label: heldRelations(obj) } });
            }
        });
        (slice.Attribute || []).forEach(function (a) {
            var c = cards[a.event_id] || {};
            var id = land.node({ id: 'attr:' + a.uuid, data: kit.attributeNodeData(a, kit.provenance(a.event_id, c.uuid)) });
            var cid = card(a.event_id);
            if (cid) land.edge(kit.inEventEdge(id, cid));
            var vid = valueIdOf(a);
            if (vid) {
                land.edge({ id: kind + ':' + vid + ':' + id, from: vid, to: id,
                            data: { kind: kind, label: a.type || '' } });
            }
        });
    }

    // The seed as pivotick data.
    function graphData(seed, kit) {
        var land = kit.landing();
        var centre = valueNodeId(seed.value.b64);
        land.node({ id: centre, data: valueNodeData(seed.value, true) });

        landOccurrences(kit, land, seed, function () { return centre; });

        // Reference far ends, each beside its event card.
        var cards = seed.events || {};
        var far = seed.far || {};
        (far.objects || []).forEach(function (obj) {
            var c = cards[obj.event_id] || {};
            var id = land.node(kit.foreignObjectNode(obj, kit.provenance(obj.event_id, c.uuid)));
            if (cards[obj.event_id]) land.edge(kit.inEventEdge(id, land.node(kit.eventCardNode(cards[obj.event_id]))));
        });
        (far.attributes || []).forEach(function (a) {
            var c = cards[a.event_id] || {};
            var id = land.node({ id: 'attr:' + a.uuid, data: kit.attributeNodeData(a, kit.provenance(a.event_id, c.uuid)) });
            if (cards[a.event_id]) land.edge(kit.inEventEdge(id, land.node(kit.eventCardNode(cards[a.event_id]))));
        });

        // Every id an edge may end on: nodes, and the attributes inside objects.
        var ends = {};
        land.result().nodes.forEach(function (n) {
            ends[n.id] = true;
            (n.children || []).forEach(function (c) { ends[c.id] = true; });
        });

        (seed.references || []).forEach(function (r) {
            var from = 'obj:' + r.object_uuid;
            var to = (r.referenced_type === 'object' ? 'obj:' : 'attr:') + r.referenced_uuid;
            if (!ends[from] || !ends[to]) return;
            var rel = r.relationship_type || 'related-to';
            land.edge({ id: 'ref:' + r.uuid, from: from, to: to,
                        data: { kind: 'object-reference', label: rel, uuid: r.uuid, relationship_type: rel } });
        });

        // Analyst relationships, their far ends drawn as the explorer's seed
        // draws another event's element.
        var ev = { Attribute: seed.Attribute || [], Object: seed.Object || [] };
        kit.eachAnalystRelationship(ev, function (rel, fromId, toId, farId) {
            if (!fromId || !toId || fromId === toId) return;
            if (!ends[farId]) {
                var f = kit.relationshipFarEnd(rel, farId);
                if (!f) return;
                if (f.type === 'event') {
                    land.node({ id: farId, data: kit.eventNodeData(f.record) });
                } else {
                    var owner = kit.provenance(f.event.id, f.event.uuid);
                    land.node({ id: farId, data: f.type === 'attribute'
                        ? kit.attributeNodeData(f.record, owner) : kit.objectNodeData(f.record, owner) });
                    var cid = land.node({ id: 'event:' + f.event.uuid, data: kit.eventNodeData(f.event) });
                    land.edge(kit.inEventEdge(farId, cid));
                }
                ends[farId] = true;
            }
            var type = rel.relationship_type || 'related-to';
            land.edge({ id: 'analyst:' + rel.uuid, from: fromId, to: toId,
                        data: { kind: 'analyst-relationship', label: type, authors: rel.authors,
                                orgc: rel.orgc_uuid, uuid: rel.uuid, relationship_type: type } });
        });

        // The value's feeds and servers.
        kit.sources.forEach(function (s) {
            var known = kit.sourceMap(seed, s.scope);
            Object.keys(known).forEach(function (id) {
                var srcId = land.node({ id: s.type + ':' + id, data: kit.sourceNodeData(s.type, known[id]) });
                land.edge({ id: s.kind + ':' + id, from: srcId, to: centre, data: { kind: s.kind, label: '' } });
            });
        });

        (seed.near || []).forEach(function (n) {
            var id = land.node({ id: valueNodeId(n.b64), data: valueNodeData({
                value: n.value, b64: n.b64, types: [], description: 'Close to this value'
            }) });
            land.edge({ id: 'near:' + n.b64, from: centre, to: id,
                        data: { kind: 'near', label: nearLabel(n), engine: n.engine } });
        });

        return land.result();
    }

    // The slice as the explorer's payload, so the sidebar, the analyst panel
    // and the tag pivots read the records behind every node drawn from it.
    function payloadOf(seed) {
        var far = seed.far || {};
        return { Event: {
            Attribute: (seed.Attribute || []).concat(far.attributes || []),
            Object:    (seed.Object || []).concat(far.objects || []),
            Feed:      seed.Feed || [],
            Server:    seed.Server || []
        } };
    }

    /* ── pivots: more of a value's occurrences ─────────────── */
    function drawnUuids(graph) {
        var out = [];
        (graph ? graph.getMutableNodes() : []).forEach(function (n) {
            var d = n.getData() || {};
            if ((d.type === 'object' || d.type === 'attribute') && d.uuid) out.push(d.uuid);
        });
        return out.sort();
    }

    function facetOptions(counts, label) {
        return Object.keys(counts || {}).map(function (k) {
            return { label: label ? label(k) : k, value: k, count: counts[k] };
        }).sort(function (a, b) { return b.count - a.count; });
    }

    function occurrenceFacets(c) {
        return [
            { key: 'template', label: 'Object', type: 'multiselect',
              options: facetOptions(c.by_template, function (k) { return k === '' ? 'Not in an object' : k; }) },
            { key: 'org', label: 'Creator org', type: 'multiselect',
              options: (c.by_org || []).map(function (o) { return { label: o.name, value: String(o.id), count: o.count }; }) },
            { key: 'year', label: 'Year', type: 'multiselect',
              options: facetOptions(c.by_year).sort(function (a, b) { return b.value.localeCompare(a.value); }) }
        ];
    }

    function occurrencesPivot(kit, spec) {
        var cache = {};
        function body(nodes, narrowing, count) {
            narrowing = narrowing || {};
            return { values: spec.valuesOf(nodes), exclude: drawnUuids(kit.graph()),
                     template: narrowing.template || [], org: narrowing.org || [],
                     year: narrowing.year || [], count: count };
        }
        function ask(b, signal) {
            var key = JSON.stringify(b);
            if (!cache[key]) {
                cache[key] = kit.postJson('/values/graphOccurrences.json', b, signal).catch(function (err) {
                    delete cache[key];
                    throw err;
                });
            }
            return cache[key];
        }
        return {
            id:            spec.id,
            label:         spec.label,
            maxCandidates: 200,
            appliesTo:     function (nodes) { return nodes.filter(spec.applies); },
            summarize: function (nodes, narrowing, ctx) {
                return ask(body(nodes, narrowing, true), ctx && ctx.signal).then(function (c) {
                    return { total: c.total || 0, facets: occurrenceFacets(c) };
                });
            },
            fetch: function (nodes, narrowing, ctx) {
                return ask(body(nodes, narrowing, false), ctx && ctx.signal).then(function (slice) {
                    var land = kit.landing();
                    landOccurrences(kit, land, slice, spec.holderOf(nodes), spec.kind);
                    return land.result();
                });
            }
        };
    }

    function isValueNode(n) {
        var d = n.getData() || {};
        return d.type === 'value';
    }

    function isAttributeNode(n) {
        var d = n.getData() || {};
        return d.type === 'attribute' && d.value !== undefined && d.value !== '';
    }

    function valuesOf(nodes) {
        var seen = {};
        nodes.forEach(function (n) { seen[String((n.getData() || {}).value)] = true; });
        return Object.keys(seen);
    }

    // Which selected node a landed unit holds the value of.
    function holderOf(nodes) {
        var byValue = {};
        nodes.forEach(function (n) { byValue[String((n.getData() || {}).value)] = n.id; });
        return function (unit) {
            var attrs = unit.Attribute ? unit.Attribute.filter(function (a) {
                return (unit.holds || []).indexOf(a.uuid) !== -1;
            }) : [unit];
            for (var i = 0; i < attrs.length; i++) {
                var a = attrs[i];
                var hit = byValue[String(a.value)] || byValue[String(a.value1)] || byValue[String(a.value2)];
                if (hit) return hit;
            }
            return null;
        };
    }

    function valuePivots(kit) {
        var more = occurrencesPivot(kit, {
            id: MORE_PIVOT, label: 'More occurrences', kind: 'occurrence',
            applies: isValueNode, valuesOf: valuesOf, holderOf: holderOf
        });
        var where = occurrencesPivot(kit, {
            id: WHERE_PIVOT, label: 'Where else this appears', kind: 'correlation',
            applies: isAttributeNode, valuesOf: valuesOf, holderOf: holderOf
        });
        var p = kit.pivots;
        return [more, where, p.feedEvents(), p.tags(), p.taggedEvents(), p.relatedClusters(),
                p.surroundings(), p.cardElements('ids'), p.cardElements('network'), p.cardElements('all')];
    }

    /* ── options ───────────────────────────────────────────── */
    function valueStyle(kit) {
        var styles = kit.mispNodeStyles();
        return Object.assign({}, styles.attribute);
    }

    function openValue(baseurl, b64) {
        window.open(baseurl + '/values/view/' + encodeURIComponent(b64), '_blank', 'noopener');
    }

    function valueOptions(config) {
        return function (opts, kit, seed) {
            opts.render.nodeStyleMap.value = valueStyle(kit);
            Object.assign(opts.render.edgeStyleMap, {
                occurrence: { strokeColor: '#6c737d', strokeWidth: 1.5, markerEnd: 'none' },
                near:       { strokeColor: '#8a8f98', dashed: true, markerEnd: 'none' }
            });
            opts.UI.extraPanels = [kit.sharedPanel()];
            if (kit.eventHasAnalystData(payloadOf(seed.raw).Event)) opts.UI.extraPanels.push(kit.analystPanel());
            opts.UI.contextMenu.menuNode.menu.unshift({
                text:      'Open value page',
                iconClass: 'fas fa-external-link-alt',
                visible:   function (el) {
                    var n = Array.isArray(el) ? (el.length === 1 ? el[0] : null) : el;
                    var d = n && n.getData ? n.getData() : null;
                    return !!d && d.type === 'value' && !d.centre;
                },
                onclick:   function (e, el) {
                    var n = Array.isArray(el) ? el[0] : el;
                    openValue(config.baseurl, n.getData().b64);
                }
            });
            var dbclick = opts.callbacks.onNodeDbclick;
            opts.callbacks.onNodeDbclick = function (e, node) {
                var d = node && node.getData ? node.getData() : null;
                if (d && d.type === 'value' && !d.centre) {
                    window.location.href = config.baseurl + '/values/view/' + encodeURIComponent(d.b64);
                    return;
                }
                dbclick(e, node);
            };
        };
    }

    // What is left of the value's occurrences sits on its rim.
    function afterMount(graph, kit, seed) {
        var m = (seed.raw.meta || {}).occurrences || {};
        var rest = (m.units || 0) - (m.seeded || 0);
        var node = graph.getMutableNode && graph.getMutableNode(valueNodeId(seed.raw.value.b64));
        if (node && typeof node.setPotential === 'function' && rest > 0) node.setPotential(MORE_PIVOT, rest);
        if (typeof graph.on === 'function' && graph.pivots) {
            var drop = function () {
                graph.pivots.invalidate(MORE_PIVOT);
                graph.pivots.invalidate(WHERE_PIVOT);
            };
            graph.on('nodeAdd', drop);
            graph.on('nodeRemove', drop);
        }
    }

    function loadSeed(config) {
        return function (kit) {
            return fetch(config.baseurl + '/values/graph/' + encodeURIComponent(config.b64) + '.json', {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' }
            }).then(function (r) {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.json();
            }).then(function (seed) {
                return { raw: seed, event: payloadOf(seed), data: graphData(seed, kit) };
            });
        };
    }

    function explorer(config) {
        return window.MispPivotExplorer.create({
            containerEl: config.containerEl,
            loaderEl:    config.loaderEl,
            fitHeight:   false,
            provenance:  false,
            config: {
                baseurl:   config.baseurl,
                labelPlan: config.labelPlan,
                permitted: config.permitted,
                orgUuid:   config.orgUuid,
                siteAdmin: config.siteAdmin,
                text:      config.text
            },
            load:       loadSeed(config),
            pivots:     valuePivots,
            options:    valueOptions(config),
            afterMount: afterMount
        });
    }

    window.MispValueNeighbourhood = {
        explorer:  explorer,
        graphData: graphData,
        payloadOf: payloadOf,
        valueNodeId: valueNodeId
    };
}());
