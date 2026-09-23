// Pivot Explorer — the object-reference graph on the event view's
// "Pivot Explorer" tab (Elements/Events/View/event_pivot_explorer.ctp).
//
// The element owns the markup and the server-side values; this file owns all
// behaviour. Config is read off #pe-card's data-* attributes:
//
//   [data-pe-event-id]     event fetched as /events/view/{id}.json
//   [data-pe-baseurl]      MISP $baseurl, prefixed onto every request
//   [data-pe-can-edit]     "1" when the viewer may add object references
//   [data-pe-lib-missing]  translated error: pivotick failed to load
//   [data-pe-load-failed]  translated error: the event fetch failed
//
// Requires window.Pivotick (pivotick.iife.js); the element's assetLoader call
// pulls in both.

(function () {
    'use strict';

    /* ── config (read off #pe-card in boot()) ──────────────── */
    var eventId = '';
    var baseurl = '';
    var canEdit = false;
    var text    = { libMissing: '', loadFailed: '' };

    /* ── state ─────────────────────────────────────────────── */
    var _initialized = false;
    var _graph       = null;
    var _event       = null;

    /* ── helpers ───────────────────────────────────────────── */
    function truncate(str, max) {
        str = String(str == null ? '' : str);
        return str.length > max ? str.substring(0, max - 1) + '…' : str;
    }

    // Drop null/undefined fields from node data: pivotick's filter builder
    // indexes every data value and calls `.length` on it, so a null/undefined
    // value (e.g. object_relation on an event-level attribute) crashes it.
    function compact(obj) {
        var out = {};
        for (var k in obj) {
            if (obj[k] != null) out[k] = obj[k];   // skips both null and undefined
        }
        return out;
    }

    // Screenshots and other pictures are stored as `attachment` attributes whose
    // value is an image filename — mirror MispAttribute::isImage() server-side.
    function isImageAttribute(attr) {
        if (!attr || attr.type !== 'attachment') return false;
        return /\.(jpe?g|png|gif|webp)$/i.test(String(attr.value == null ? '' : attr.value));
    }

    // Thumbnail served by AttributesController::viewPicture() (ACL-checked,
    // accepts the attribute UUID). The webp variant is generated at 400px
    // (vs 200px for png) and cached, so the framed picture stays crisp.
    function attributeImageUrl(attr) {
        return baseurl + '/attributes/viewPicture/' + attr.uuid + '/webp';
    }

    // Which event a node belongs to, and whether it is the one the graph was
    // seeded from. Read by the Provenance facet and the sidebar, never drawn:
    // the analyst decides which event is the subject.
    function provenance(id, uuid) {
        return {
            scope:      String(id) === String(eventId) ? 'self' : 'foreign',
            event_id:   id,
            event_uuid: uuid
        };
    }

    // The owner of a record in an event payload. Extension events merge their
    // elements in with only an id to tell them apart.
    function ownerIn(ev, rec) {
        var id = (rec && rec.event_id != null) ? rec.event_id : ev.id;
        return provenance(id, String(id) === String(ev.id) ? ev.uuid : undefined);
    }

    // Shared by the graph builder and the pivots so an attribute brought in
    // renders identically to one that was referenced from the start.
    function attributeNodeData(attr, owner) {
        var val   = attr.value != null ? String(attr.value) : '';
        var isImg = isImageAttribute(attr);
        return compact(Object.assign({
            type:            'attribute',
            label:           truncate(val, 42),
            description:     (attr.object_relation ? attr.object_relation + ' · ' : '')
                             + (attr.category || '') + (attr.type ? ' / ' + attr.type : ''),
            value:           val,
            'attr-type':     attr.type,
            category:        attr.category,
            object_relation: attr.object_relation,
            to_ids:          attr.to_ids,
            comment:         attr.comment,
            uuid:            attr.uuid,
            image:           isImg || undefined,
            imageUrl:        isImg ? attributeImageUrl(attr) : undefined
        }, owner, analystFields(attr)));
    }

    // Soft-deleted records (deleted=1) are tombstones — refs create no edge and
    // don't count a node as connected; deleted attributes/objects aren't shown.
    function isDeleted(rec) {
        return !!rec && (rec.deleted === true || rec.deleted === 1 || rec.deleted === '1');
    }

    // Node id an analyst relationship's target maps to, or null when it has no
    // node on this canvas. AnalystData::valid_targets is far wider than the
    // canvas — EventReport, GalaxyCluster, Organisation, SharingGroup and the
    // analyst-data types are all legal targets. 'Event' resolves now that L0
    // draws this event and the events it correlates with.
    function analystTargetId(rel) {
        var t = String(rel.related_object_type || '');
        if (t === 'Attribute') return 'attr:'  + rel.related_object_uuid;
        if (t === 'Object')    return 'obj:'   + rel.related_object_uuid;
        if (t === 'Event')     return 'event:' + rel.related_object_uuid;
        return null;
    }

    // L0's candidate event nodes: this event, plus one proxy per correlated
    // event. `RelatedEvent` is the correlation aggregate MISP already ships
    // (Event.php:3358) — 5,629 correlations collapse to 86 neighbours.
    function relatedEvents(ev) {
        var selfUuid = ev.uuid ? String(ev.uuid) : '';
        var seen     = {};
        var out      = [];
        (ev.RelatedEvent || []).forEach(function (entry) {
            var e = (entry && entry.Event) ? entry.Event : entry;
            if (!e || !e.uuid || String(e.uuid) === selfUuid) return;
            if (seen[e.uuid]) return;         // extended events merge two lists
            seen[e.uuid] = true;
            out.push(e);
        });
        return out;
    }

    // Walk every outbound analyst relationship in the event, calling
    // cb(relationship, sourceNodeId). Sources are event-level attributes,
    // objects, and objects' child attributes; a tombstoned owner is skipped
    // whole, exactly as it is on the canvas.
    //
    // RelationshipInbound is deliberately absent: the bulk path attaches it only
    // at event level (PRD §3.4), and the event node arrives with L0 (task 3b).
    function eachAnalystRelationship(ev, cb) {
        function walk(rec, sourceId) {
            if (isDeleted(rec)) return;
            (rec.Relationship || []).forEach(function (rel) {
                if (!isDeleted(rel)) cb(rel, sourceId);
            });
        }
        (ev.Attribute || []).forEach(function (a) { walk(a, 'attr:' + a.uuid); });
        (ev.Object || []).forEach(function (obj) {
            if (isDeleted(obj)) return;
            walk(obj, 'obj:' + obj.uuid);
            (obj.Attribute || []).forEach(function (a) { walk(a, 'attr:' + a.uuid); });
        });
    }

    // Single source of truth for "what is authored", read through the seed.
    // D5' seeds any element
    // participating in an object reference *or* an analyst relationship. An
    // object counts if it is an endpoint itself, OR owns a child attribute that
    // some live relationship touches.
    function computeConnectivity(ev) {
        var linkedAttrUuids   = {};
        var connectedObjUuids = {};
        var eventTouched      = {};   // event node id -> true
        var attrOwner         = {};   // object child attr uuid -> owning object uuid
        var liveObj           = {};   // uuid -> true, for endpoint resolution
        var liveAttr          = {};
        var liveEvent         = {};   // event node id -> true (L0 candidates)

        if (ev.uuid) liveEvent['event:' + ev.uuid] = true;
        relatedEvents(ev).forEach(function (e) { liveEvent['event:' + e.uuid] = true; });

        (ev.Attribute || []).forEach(function (a) {
            if (!isDeleted(a)) liveAttr[a.uuid] = true;
        });
        (ev.Object || []).forEach(function (obj) {
            var live = !isDeleted(obj);
            if (live) liveObj[obj.uuid] = true;
            (obj.Attribute || []).forEach(function (a) {
                attrOwner[a.uuid] = obj.uuid;
                if (live && !isDeleted(a)) liveAttr[a.uuid] = true;
            });
        });

        // Does this node id name a live element of *this* event? A relationship
        // whose other end is a tombstone, lives in another event, or is an
        // element type the canvas does not draw is not drawable — and an
        // undrawable relationship must seed neither end, or its source arrives
        // as an isolated node with no edge.
        function exists(id) {
            if (!id) return false;
            if (id.indexOf('event:') === 0) return !!liveEvent[id];
            return id.indexOf('obj:') === 0
                ? !!liveObj[id.slice(4)]
                : !!liveAttr[id.slice(5)];
        }

        // Mark one endpoint, given the node id it would carry. A touched child
        // attribute pulls its owning object onto the canvas with it.
        function markEndpoint(id) {
            if (!id) return;
            if (id.indexOf('event:') === 0) {
                eventTouched[id] = true;
                return;
            }
            if (id.indexOf('obj:') === 0) {
                connectedObjUuids[id.slice(4)] = true;
                return;
            }
            var uuid = id.slice(5);
            linkedAttrUuids[uuid] = true;
            if (attrOwner[uuid]) connectedObjUuids[attrOwner[uuid]] = true;
        }

        (ev.Object || []).forEach(function (obj) {
            if (isDeleted(obj)) return;
            (obj.ObjectReference || []).forEach(function (ref) {
                if (isDeleted(ref)) return;
                var targetId = (String(ref.referenced_type) === '1' ? 'obj:' : 'attr:')
                               + ref.referenced_uuid;
                if (!exists(targetId)) return;
                markEndpoint('obj:' + obj.uuid);
                markEndpoint(targetId);
            });
        });

        eachAnalystRelationship(ev, function (rel, sourceId) {
            if (rel.related_object_uuid && rel.object_uuid === rel.related_object_uuid) {
                return;   // self-reference; the model rejects these, guard anyway
            }
            var targetId = analystTargetId(rel);
            if (!exists(targetId)) return;
            markEndpoint(sourceId);
            markEndpoint(targetId);
        });

        return {
            linkedAttrUuids:   linkedAttrUuids,
            connectedObjUuids: connectedObjUuids,
            eventTouched:      eventTouched
        };
    }

    // Pivotick's own detail threshold: past 1,500 nodes the minimap stops
    // resolving per-node style and reads as a density map. D12 reuses it as the
    // seed budget, so the canvas never opens past the point of legibility.
    var NODE_BUDGET = 1500;

    function liveChildCount(obj) {
        var n = 0;
        (obj.Attribute || []).forEach(function (a) { if (!isDeleted(a)) n++; });
        return n;
    }

    // The seed (D12). Decides which resolution levels this event affords and
    // which elements each one contributes, analytically — no nodes are built.
    // The canvas builder reads it; the element pivot offers whatever it left out.
    //
    //   L0  event node + one proxy per correlated event
    //   L1  everything an object reference or analyst relationship touches
    //   L2  the remaining objects, containment only, if the whole set fits
    //
    // L2 is all-or-nothing on purpose. D10 says that above the budget "the seed
    // stops at L0+L1": a greedy partial fill would draw an arbitrary 40 of
    // 28,410 objects, and no statement could honestly explain which 40.
    function computeSeed(ev) {
        var conn    = computeConnectivity(ev);
        var selfId  = ev.uuid ? 'event:' + ev.uuid : null;
        var proxies = selfId ? relatedEvents(ev) : [];

        // The event node needs an edge to be worth drawing — a proxy to
        // correlate with, or an analyst relationship pointing at the event.
        // Without that gate a bare hexagon would make L0 permanently non-empty
        // and D11's "nothing to draw" message unreachable.
        var seedEventNode = !!selfId
            && (proxies.length > 0 || !!conn.eventTouched[selfId]);

        var l1 = 0;
        (ev.Attribute || []).forEach(function (a) {
            if (!isDeleted(a) && conn.linkedAttrUuids[a.uuid]) l1++;
        });

        var l2Uuids = {};
        var l2Count = 0;   // objects
        var l2Cost  = 0;   // nodes: the object plus its live children
        (ev.Object || []).forEach(function (obj) {
            if (isDeleted(obj)) return;
            var cost = 1 + liveChildCount(obj);
            if (conn.connectedObjUuids[obj.uuid]) { l1 += cost; return; }
            l2Uuids[obj.uuid] = true;
            l2Count++;
            l2Cost += cost;
        });

        var l0     = seedEventNode ? 1 + proxies.length : 0;
        var l2Fits = (l0 + l1 + l2Cost) <= NODE_BUDGET;

        return {
            linkedAttrUuids:   conn.linkedAttrUuids,
            connectedObjUuids: conn.connectedObjUuids,
            selfId:            selfId,
            proxies:           proxies,
            seedEventNode:     seedEventNode,
            // Objects L2 actually draws — empty when the level did not fit, so
            // "is this object on the canvas?" is one lookup for every caller.
            l2Uuids:           l2Fits ? l2Uuids : {},
            objectsSkipped:    l2Fits ? 0 : l2Count,
            cost:              { l0: l0, l1: l1, l2: l2Fits ? l2Cost : 0 },
            budget:            NODE_BUDGET
        };
    }

    // Event node — this event, or a correlated-event proxy. `info` is what an
    // analyst recognises an event by; `event_id` is what the double-click
    // navigation needs. Proxies are leaves: correlated events do not expand in
    // this phase (PRD §4).
    function eventNodeData(e) {
        var info = e.info != null ? String(e.info) : '';
        var org  = (e.Orgc && e.Orgc.name) || (e.Org && e.Org.name) || '';
        var meta = [];
        if (e.date) meta.push(String(e.date));
        if (org)    meta.push(org);
        return compact(Object.assign({
            type:        'event',
            label:       truncate(info || ('Event ' + (e.id || '')), 42),
            description: meta.join(' · ') || 'Event',
            info:        info,
            date:        e.date,
            org:         org || undefined,
            uuid:        e.uuid
        }, provenance(e.id, e.uuid), analystFields(e)));
    }

    // Shared object node data (graph builder + element pivot).
    function objectNodeData(obj, owner) {
        return compact(Object.assign({
            type:            'object',
            label:           truncate(obj.name || 'Object', 42),
            description:     obj['meta-category'] ? (obj['meta-category'] + ' object') : 'Object',
            name:            obj.name,
            'meta-category': obj['meta-category'],
            uuid:            obj.uuid
        }, owner, analystFields(obj)));
    }

    /* ── misp-iconify (webfont) integration ────────────────── */
    // Pivotick resolves the glyph AND its font from the icon class itself
    // (font-agnostically, off the computed `::before`) and simply skips any
    // class it can't resolve — so an attribute type without a dedicated icon
    // just shows the bare coloured chip. We only map a node to its class name.
    function nodeIconClass(node) {
        var d = (node && node.getData) ? node.getData() : null;
        if (!d) return undefined;
        if (d.type === 'attribute') {
            // Image attachments render as an embedded thumbnail (imagePath),
            // so leave the icon unset to let the picture take over.
            if (d.image) return undefined;
            return d['attr-type']
                ? 'misp-icon misp-icon-' + d['attr-type'] + ' misp-attributes'
                : undefined;
        }
        if (d.type === 'object') {
            return d.name
                ? 'misp-icon misp-icon-' + d.name + ' misp-objects-framed'
                : undefined;
        }
        if (d.type === 'event') {
            return 'misp-icon misp-icon-event misp-simple';
        }
        return undefined;
    }

    /* ── build pivotick nodes/edges from a MISP event ──────── */
    function buildGraphData(event) {
        var ev      = (event && event.Event) ? event.Event : {};
        var nodes   = [];
        var edges   = [];
        var nodeSet = {};   // id -> true (dedupe + existence check for edges)
        var edgeSet = {};   // key -> true (dedupe)

        function addNode(id, node) {
            if (nodeSet[id]) return;
            nodeSet[id] = true;
            nodes.push(node);
        }
        // `kind` is how the edge came to exist (D1's first edge dimension) and is
        // part of the edge's identity: two kinds may assert the same label between
        // the same pair, and both belong on the canvas.
        function addEdge(from, to, label, kind, extra) {
            if (!nodeSet[from] || !nodeSet[to]) return false;   // only link existing nodes
            var key = kind + ' ' + from + ' ' + to + ' ' + (label || '');
            if (edgeSet[key]) return false;
            edgeSet[key] = true;
            var data = { kind: kind, label: label || '' };
            // Same null-dropping as node data: a null value breaks the filter builder.
            for (var k in (extra || {})) {
                if (extra[k] != null) data[k] = extra[k];
            }
            edges.push({ from: from, to: to, data: data });
            return true;
        }

        // Register an attribute's id (dedupe + edge existence) and return its node
        // dict, or null if already added. The caller decides where to place it —
        // top-level (nodes) or nested inside an object (children).
        function buildAttributeNode(attr) {
            var id = 'attr:' + attr.uuid;
            if (nodeSet[id]) return null;
            nodeSet[id] = true;
            return { id: id, data: attributeNodeData(attr, ownerIn(ev, attr)) };
        }

        // Top-level attribute node (used for event-level attributes).
        function addAttributeNode(attr) {
            var node = buildAttributeNode(attr);
            if (node) nodes.push(node);
            return 'attr:' + attr.uuid;
        }

        /* The seed decides what each resolution level contributes (D12); this
           builder only lays it out. */
        var seed                = computeSeed(ev);
        var linkedAttrUuids     = seed.linkedAttrUuids;
        var connectedObjUuids   = seed.connectedObjUuids;

        /* L0 — the event and the events it correlates with. Only drawn when the
           event node has an edge to carry (see computeSeed). */
        if (seed.seedEventNode) {
            addNode(seed.selfId, { id: seed.selfId, data: eventNodeData(ev) });
            seed.proxies.forEach(function (e) {
                var id = 'event:' + e.uuid;
                addNode(id, { id: id, data: eventNodeData(e) });
            });
        }

        /* L1 — event-level attributes, surfaced only when an authored
           relationship touches them, so every node stays connected to the
           graph. This also covers referenced screenshots. */
        (ev.Attribute || []).forEach(function (attr) {
            if (!isDeleted(attr) && linkedAttrUuids[attr.uuid]) {
                addAttributeNode(attr);
            }
        });

        /* L1 objects (an authored relationship touches them) and, when the
           level fits the budget, L2's containment-only clusters. An L2 object
           has no relationship by definition, so it arrives with no edge — the
           cluster's own structure is what it says. */
        (ev.Object || []).forEach(function (obj) {
            if (isDeleted(obj)) return;
            // Everything else is offered by the element pivot instead.
            if (!connectedObjUuids[obj.uuid] && !seed.l2Uuids[obj.uuid]) return;
            var objId = 'obj:' + obj.uuid;

            // Attributes are nested as children of their object rather than
            // linked by an edge — containment expresses the object/attribute
            // relationship (the object_relation is folded into the description).
            var children = [];
            (obj.Attribute || []).forEach(function (attr) {
                if (isDeleted(attr)) return;
                var child = buildAttributeNode(attr);
                if (child) children.push(child);
            });

            addNode(objId, {
                id:       objId,
                children: children,
                data:     objectNodeData(obj, ownerIn(ev, obj))
            });
        });

        /* L0's edges — the correlation aggregate, event to event. Its own kind
           rather than `correlation`: 86 correlated events and 5,629 attribute
           correlations (event 4116) are different granularities that must
           toggle apart, and under D9 the `correlation` layer has to stay empty
           until it is fetched. The label is blank because there is no assertion
           here — only "these two events share a value". */
        if (seed.seedEventNode) {
            seed.proxies.forEach(function (e) {
                addEdge(seed.selfId, 'event:' + e.uuid, '', 'event-correlation');
            });
        }

        /* Object references (added last so both ends already exist) */
        (ev.Object || []).forEach(function (obj) {
            var objId = 'obj:' + obj.uuid;
            (obj.ObjectReference || []).forEach(function (ref) {
                if (isDeleted(ref)) return;
                var prefix   = (String(ref.referenced_type) === '1') ? 'obj:' : 'attr:';
                var targetId = prefix + ref.referenced_uuid;
                addEdge(objId, targetId, ref.relationship_type || 'related-to',
                        'object-reference');
            });
        });

        /* Analyst relationships (D1's second kind, L1 alongside object
           references). Both endpoints were seeded above, so a rejected edge
           means the target has no node on this canvas at all — a relationship
           pointing at another event's attribute, or at an element type the
           canvas does not draw. Those are counted, not silently dropped. */
        var relationshipsSkipped = 0;
        eachAnalystRelationship(ev, function (rel, sourceId) {
            if (rel.related_object_uuid && rel.object_uuid === rel.related_object_uuid) {
                relationshipsSkipped++;
                return;
            }
            var targetId = analystTargetId(rel);
            if (!targetId || !nodeSet[targetId] || !nodeSet[sourceId]) {
                relationshipsSkipped++;
                return;
            }
            addEdge(sourceId, targetId, rel.relationship_type || 'related-to',
                    'analyst-relationship',
                    { authors: rel.authors, orgc: rel.orgc_uuid });
        });

        /* What the graph must be able to say about itself (D12, §7): which
           levels it took, how big that made it, and what it left out. */
        var levels = [];
        if (seed.cost.l0) levels.push('L0');
        if (seed.cost.l1) levels.push('L1');
        if (seed.cost.l2) levels.push('L2');

        return {
            nodes: nodes,
            edges: edges,
            stats: {
                levels:               levels,
                nodeCount:            seed.cost.l0 + seed.cost.l1 + seed.cost.l2,
                budget:               seed.budget,
                objectsSkipped:       seed.objectsSkipped,
                relationshipsSkipped: relationshipsSkipped
            }
        };
    }

    // Which levels the seed took and what it left out. Without it 87 nodes
    // read as the whole of a 28,410-object event. `correlations` joins it
    // once the counts arrive.
    function resolutionStatement(stats, correlations) {
        var parts = [];
        // A seed that took no level at all still owes the skip clause: an event
        // whose only content is 28,410 relationship-less objects draws nothing
        // and must say why, not fall silent.
        if (stats.levels.length) {
            parts.push('Seeded ' + stats.levels.join('+'));
            parts.push(stats.nodeCount + (stats.nodeCount === 1 ? ' node' : ' nodes'));
        }
        if (stats.objectsSkipped) {
            parts.push('L2 skipped (' + stats.objectsSkipped + ' object'
                       + (stats.objectsSkipped === 1 ? '' : 's') + ' not shown)');
        }
        if (stats.relationshipsSkipped) {
            parts.push(stats.relationshipsSkipped + ' relationship'
                       + (stats.relationshipsSkipped === 1 ? '' : 's')
                       + ' not drawable');
        }
        if (correlations) {
            parts.push(plural(correlations, 'correlation', 'correlations') + ' available');
        }
        return parts.join(' · ');
    }

    // Nothing on the canvas says which event seeded it, so the card does.
    function identityLine(ev) {
        var org = (ev.Orgc && ev.Orgc.name) || '';
        return ['Event ' + (ev.id || eventId), ev.info, org, ev.date]
            .filter(function (p) { return p != null && p !== ''; })
            .join(' · ');
    }

    var _stats = null;
    function renderHeader() {
        var headerEl = document.getElementById('pe-header');
        var idEl     = document.getElementById('pe-identity');
        var resEl    = document.getElementById('pe-resolution');
        var ev       = (_event && _event.Event) || {};
        if (idEl) {
            idEl.textContent = identityLine(ev);
            idEl.setAttribute('title', idEl.textContent);
        }
        if (resEl && _stats) {
            var res = resolutionStatement(_stats, _counts && _counts.total);
            resEl.textContent   = res;
            resEl.style.display = res ? '' : 'none';
        }
        if (headerEl) headerEl.style.display = '';
    }

    /* ── analyst data: one folded badge, detail in the sidebar (D2, §6.2) ── */
    // Notes and opinions on an element, and the notes and opinions left on
    // those in turn — everything somebody has said about it. Relationships are
    // not counted: they are drawn as edges.
    function analystRecords(rec) {
        var out = [];
        (function walk(r, depth) {
            ['Note', 'Opinion'].forEach(function (k) {
                (r[k] || []).forEach(function (a) {
                    out.push({ kind: k, rec: a, depth: depth });
                    walk(a, depth + 1);
                });
            });
        })(rec, 0);
        return out;
    }

    // The existing bands (opinion_scale.ctp): under 41 disagree, over 60
    // agree. Only opinions on the element itself decide its colour; an
    // opinion on a note is about the note.
    function opinionMood(values) {
        if (!values.length) return 'none';
        var mean = values.reduce(function (s, v) { return s + v; }, 0) / values.length;
        return mean < 41 ? 'disputed' : (mean > 60 ? 'endorsed' : 'neutral');
    }

    function opinionLabel(v) {
        return v >= 81 ? 'Strongly agree' : v >= 61 ? 'Agree' : v >= 41 ? 'Neutral'
             : v >= 21 ? 'Disagree' : 'Strongly disagree';
    }

    // The two fields a node carries, flat so the filter builder can index
    // them. Absent — not zero — without analyst data, so such a node's data
    // is exactly what it was before.
    function analystFields(rec) {
        var all = analystRecords(rec);
        if (!all.length) return {};
        var own = (rec.Opinion || []).map(function (o) { return Number(o.opinion); })
            .filter(function (v) { return !isNaN(v); });
        return { analyst_count: all.length, analyst_mood: opinionMood(own) };
    }

    var MOOD_COLOR = { disputed: '#b94a48', endorsed: '#6fbe80', neutral: '#999', none: '#999' };

    function analystBadges(node) {
        var d = node && node.getData ? node.getData() : null;
        if (!d || !d.analyst_count) return [];
        var n = d.analyst_count;
        return [{
            position: 'nw',
            text:     String(n),
            color:    MOOD_COLOR[d.analyst_mood] || MOOD_COLOR.none,
            title:    n + (n === 1 ? ' note or opinion' : ' notes and opinions')
                      + (d.analyst_mood !== 'none' ? ' — ' + d.analyst_mood : ''),
            onClick:  function (e, clicked) { showAnalystData(clicked); }
        }];
    }

    // Every record of this event that can carry analyst data, by uuid.
    var _analystIndex = null, _analystIndexFor = null;
    function analystSource(uuid) {
        if (!_analystIndex || _analystIndexFor !== _event) {
            var ev = (_event && _event.Event) || {};
            _analystIndex = {};
            if (ev.uuid) _analystIndex[ev.uuid] = ev;
            (ev.Attribute || []).forEach(function (a) { _analystIndex[a.uuid] = a; });
            (ev.Object || []).forEach(function (o) {
                _analystIndex[o.uuid] = o;
                (o.Attribute || []).forEach(function (a) { _analystIndex[a.uuid] = a; });
            });
            _analystIndexFor = _event;
        }
        return _analystIndex[uuid] || null;
    }

    function eventHasAnalystData(ev) {
        if (analystRecords(ev).length) return true;
        return (ev.Attribute || []).some(function (a) { return analystRecords(a).length; })
            || (ev.Object || []).some(function (o) {
                return analystRecords(o).length
                    || (o.Attribute || []).some(function (a) { return analystRecords(a).length; });
            });
    }

    function showAnalystData(node) {
        if (!_graph || !node) return;
        if (typeof _graph.selectElement === 'function') _graph.selectElement(node);
        var sidebar = _graph.UIManager && _graph.UIManager.sidebar;
        if (sidebar && typeof sidebar.showSidebar === 'function') sidebar.showSidebar();
    }

    function el(tag, cls, textContent) {
        var e = document.createElement(tag);
        if (cls) e.className = cls;
        if (textContent != null) e.textContent = textContent;
        return e;
    }

    // One entry: who, when, and what they said. Replies are indented a step
    // under what they answer; the list itself stays flat (§4).
    function analystEntry(item) {
        var a = item.rec, row = el('div', 'pe-analyst-entry');
        row.style.cssText = 'margin:0 0 .6rem ' + (item.depth * .9) + 'rem;';
        var head = [];
        if (item.kind === 'Opinion') {
            var v = Number(a.opinion);
            head.push(isNaN(v) ? 'Opinion' : opinionLabel(v) + ' (' + v + '/100)');
        } else {
            head.push('Note');
        }
        if (item.depth) head.push('reply');
        var meta = [(a.Orgc && a.Orgc.name) || a.authors, a.created && String(a.created).slice(0, 10)]
            .filter(Boolean).join(' · ');
        var h = el('div', null, head.join(' · '));
        h.style.cssText = 'font-weight:600;';
        row.appendChild(h);
        if (meta) {
            var m = el('div', null, meta);
            m.style.cssText = 'font-size:.75em;opacity:.65;';
            row.appendChild(m);
        }
        var body = item.kind === 'Opinion' ? a.comment : a.note;
        if (body) {
            var b = el('div', null, String(body));
            b.style.cssText = 'white-space:pre-wrap;word-break:break-word;';
            row.appendChild(b);
        }
        return row;
    }

    function renderAnalystPanel(selection) {
        var wrap = el('div', 'pe-analyst');
        wrap.style.cssText = 'font-size:.85rem;';
        if (Array.isArray(selection)) {
            wrap.appendChild(el('div', null, 'Select a single element to read what was said about it.'));
            return wrap;
        }
        var d = selection && selection.getData ? selection.getData() : null;
        var rec = d && d.uuid && analystSource(d.uuid);
        var items = rec ? analystRecords(rec) : [];
        if (!items.length) {
            var none = el('div', null, 'No notes or opinions on this element.');
            none.style.cssText = 'opacity:.65;';
            wrap.appendChild(none);
            return wrap;
        }
        items.forEach(function (item) { wrap.appendChild(analystEntry(item)); });
        return wrap;
    }

    function analystPanel() {
        return { id: 'analyst-data', title: 'Notes & opinions', render: renderAnalystPanel };
    }

    /* ── pivots: correlations (R1) and related events (R2) ──── */
    // Counts come from /events/correlationCounts, which counts exactly the
    // pairs /events/correlatedAttributes returns — so a summary never promises
    // what the fetch cannot bring. Null until loaded: nothing applies until then.
    var _counts = null;

    function ownElementCount(d) {
        if (!_counts || !d || !d.uuid) return 0;
        if (d.type === 'attribute') return _counts.attributes[d.uuid] || 0;
        if (d.type === 'object')    return _counts.objects[d.uuid] || 0;
        return 0;
    }

    function relatedEventCount(d) {
        if (!_counts || !d || d.type !== 'event') return 0;
        if (String(d.event_id) === String(eventId)) return 0;
        return _counts.events[String(d.event_id)] || 0;
    }

    function sumCounts(nodes, count) {
        return nodes.reduce(function (s, n) { return s + count(n.getData()); }, 0);
    }

    // This event's live attributes by uuid, and each object's attribute uuids.
    // Built once per payload: isValidConnection reads it on every pointer move.
    var _ownIndex = null, _ownIndexFor = null;
    function ownAttributeIndex() {
        if (_ownIndex && _ownIndexFor === _event) return _ownIndex;
        var ev = (_event && _event.Event) || {};
        var byUuid = {}, byObject = {};
        (ev.Attribute || []).forEach(function (a) { if (!isDeleted(a)) byUuid[a.uuid] = a; });
        (ev.Object || []).forEach(function (o) {
            if (isDeleted(o)) return;
            byObject[o.uuid] = [];
            (o.Attribute || []).forEach(function (a) {
                if (isDeleted(a)) return;
                byUuid[a.uuid] = a;
                byObject[o.uuid].push(a.uuid);
            });
        });
        _ownIndexFor = _event;
        _ownIndex = { byUuid: byUuid, byObject: byObject };
        return _ownIndex;
    }

    // Is this node one of this event's live attributes or objects? A
    // correlated attribute a pivot brought in looks the same but is not.
    function isOwnElement(d) {
        if (!d || !d.uuid) return false;
        var index = ownAttributeIndex();
        if (d.type === 'object')    return !!index.byObject[d.uuid];
        if (d.type === 'attribute') return !!index.byUuid[d.uuid];
        return false;
    }

    function attributeUuidsOf(nodes) {
        var index = ownAttributeIndex();
        var out = [];
        nodes.forEach(function (n) {
            var d = n.getData() || {};
            if (d.type === 'attribute') out.push(d.uuid);
            else if (d.type === 'object') out = out.concat(index.byObject[d.uuid] || []);
        });
        return out;
    }

    // Correlated attributes land inside their event's node — the L0 proxy when
    // it is on the canvas, since ingest merges children into a container by id.
    // This event's side of each pair is brought along when it is not drawn yet
    // (an event-level attribute, or one inside an object L2 skipped).
    function correlationResult(pairs) {
        var index = ownAttributeIndex();
        var containers = {}, order = [], edges = [], seen = {}, sourceNodes = [];
        pairs.forEach(function (p) {
            var ev  = p.Event || {};
            var cid = 'event:' + ev.uuid;
            if (!containers[cid]) {
                containers[cid] = { id: cid, data: eventNodeData(ev), children: [] };
                order.push(cid);
            }
            var tid = 'attr:' + p.Attribute.uuid;
            if (!seen[cid + tid]) {
                seen[cid + tid] = true;
                containers[cid].children.push({
                    id:   tid,
                    data: attributeNodeData(p.Attribute, provenance(ev.id, ev.uuid))
                });
            }
            var sid = 'attr:' + p.source_uuid;
            if (!seen[sid]) {
                seen[sid] = true;
                var drawn = _graph && typeof _graph.getNode === 'function' && _graph.getNode(sid);
                var own = index.byUuid[p.source_uuid];
                if (!drawn && own) {
                    sourceNodes.push({ id: sid, data: attributeNodeData(own, ownerIn(_event.Event, own)) });
                }
            }
            var eid = 'corr:' + p.source_uuid + ':' + p.Attribute.uuid;
            if (!seen[eid]) {
                seen[eid] = true;
                edges.push({ id: eid, from: sid, to: tid, data: { kind: 'correlation', label: '' } });
            }
        });
        return {
            nodes: order.map(function (cid) { return containers[cid]; }).concat(sourceNodes),
            edges: edges
        };
    }

    function fetchCorrelated(body, signal) {
        return fetch(baseurl + '/events/correlatedAttributes/' + encodeURIComponent(eventId) + '.json', {
            method: 'POST',
            credentials: 'same-origin',
            signal: signal,
            headers: {
                'Content-Type':     'application/json',
                'Accept':           'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(body)
        })
        .then(function (r) {
            if (!r.ok) throw new Error('HTTP ' + r.status);
            return r.json();
        })
        .then(correlationResult);
    }

    function correlationPivot() {
        return {
            id:            'correlations',
            label:         'Correlations',
            maxCandidates: NODE_BUDGET,
            appliesTo: function (nodes) {
                return nodes.filter(function (n) { return ownElementCount(n.getData()) > 0; });
            },
            summarize: function (nodes) {
                return { total: sumCounts(nodes, ownElementCount) };
            },
            fetch: function (nodes, narrowing, ctx) {
                return fetchCorrelated({ attribute_uuids: attributeUuidsOf(nodes) }, ctx && ctx.signal);
            }
        };
    }

    function relatedEventPivot() {
        return {
            id:            'related-event',
            label:         'Correlations with this event',
            maxCandidates: NODE_BUDGET,
            appliesTo: function (nodes) {
                return nodes.filter(function (n) { return relatedEventCount(n.getData()) > 0; });
            },
            summarize: function (nodes) {
                return { total: sumCounts(nodes, relatedEventCount) };
            },
            fetch: function (nodes, narrowing, ctx) {
                var ids = nodes.map(function (n) { return (n.getData() || {}).event_id; });
                return fetchCorrelated({ event_ids: ids }, ctx && ctx.signal);
            }
        };
    }

    // Declared, never queried (pivotick draws the rim badge from it): each
    // related event wears the number of correlations its pivot would bring.
    function declareRelatedEventPotential(graph) {
        if (!graph || typeof graph.getNodes !== 'function') return;
        graph.getNodes().forEach(function (n) {
            var n2 = relatedEventCount(n.getData());
            if (!n2) return;
            var live = graph.getMutableNode(n.id);
            if (live && typeof live.setPotential === 'function') live.setPotential('related-event', n2);
        });
        if (graph.renderer && typeof graph.renderer.update === 'function') graph.renderer.update();
    }

    function loadCorrelationCounts(graph) {
        return fetch(baseurl + '/events/correlationCounts/' + encodeURIComponent(eventId) + '.json', {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        })
        .then(function (r) {
            if (!r.ok) throw new Error('HTTP ' + r.status);
            return r.json();
        })
        .then(function (c) {
            if (!c || !c.attributes) return;
            _counts = c;
            renderHeader();
            declareRelatedEventPotential(graph);
        })
        .catch(function (err) {
            console.error('[pivot-explorer] correlation counts failed:', err);
        });
    }

    /* ── the event's elements, as an origin-less pivot (D4, P0) ── */
    // Everything the canvas does not hold yet: event-level attributes, and
    // objects whole. Its Review tab is the searchable, paged list; ingesting
    // is putting elements on the canvas, and undo takes them back off. A view
    // write, so every viewer gets it.
    var ELEMENT_PIVOT = 'event-elements';

    // Lower-cased text a search matches against, built once per element. An
    // object answers for its attributes, since it is what gets ingested.
    var _haystacks = {};
    function haystack(kind, rec) {
        var key = kind + ':' + rec.uuid;
        if (_haystacks[key] !== undefined) return _haystacks[key];
        var parts = kind === 'object'
            ? [rec.name, rec['meta-category'], rec.comment]
            : [rec.value, rec.type, rec.category, rec.comment];
        if (kind === 'object') {
            (rec.Attribute || []).forEach(function (a) {
                if (!isDeleted(a)) parts.push(a.value, a.type, a.object_relation);
            });
        }
        _haystacks[key] = parts.filter(function (p) { return p != null; }).join('\n').toLowerCase();
        return _haystacks[key];
    }

    function elementCategory(kind, rec) {
        return kind === 'object' ? (rec['meta-category'] || 'object') : (rec.category || 'Other');
    }

    function elementCandidates() {
        var ev = (_event && _event.Event) || {};
        var drawn = function (id) {
            return !!(_graph && typeof _graph.getNode === 'function' && _graph.getNode(id));
        };
        var out = [];
        (ev.Attribute || []).forEach(function (a) {
            if (!isDeleted(a) && !drawn('attr:' + a.uuid)) out.push({ kind: 'attribute', rec: a });
        });
        (ev.Object || []).forEach(function (o) {
            if (!isDeleted(o) && !drawn('obj:' + o.uuid)) out.push({ kind: 'object', rec: o });
        });
        return out;
    }

    function matchesNarrowing(c, narrowing) {
        var q = String(narrowing.q || '').trim().toLowerCase();
        if (q && haystack(c.kind, c.rec).indexOf(q) === -1) return false;
        if (narrowing.element && narrowing.element !== c.kind) return false;
        if (narrowing.category && narrowing.category !== elementCategory(c.kind, c.rec)) return false;
        return true;
    }

    function countOptions(candidates, keyOf) {
        var counts = {};
        candidates.forEach(function (c) {
            var k = keyOf(c);
            counts[k] = (counts[k] || 0) + 1;
        });
        return Object.keys(counts).sort().map(function (k) {
            return { label: k, value: k, count: counts[k] };
        });
    }

    function elementNode(c) {
        var ev = _event.Event;
        if (c.kind === 'attribute') {
            return { id: 'attr:' + c.rec.uuid, data: attributeNodeData(c.rec, ownerIn(ev, c.rec)) };
        }
        return {
            id:       'obj:' + c.rec.uuid,
            data:     objectNodeData(c.rec, ownerIn(ev, c.rec)),
            children: (c.rec.Attribute || []).filter(function (a) { return !isDeleted(a); })
                .map(function (a) { return { id: 'attr:' + a.uuid, data: attributeNodeData(a, ownerIn(ev, a)) }; })
        };
    }

    function elementPivot() {
        return {
            id:            ELEMENT_PIVOT,
            label:         'Event elements',
            origin:        'none',
            maxCandidates: NODE_BUDGET,
            summarize: function (nodes, narrowing) {
                var all = elementCandidates();
                narrowing = narrowing || {};
                return {
                    total: all.filter(function (c) { return matchesNarrowing(c, narrowing); }).length,
                    facets: [
                        { key: 'q', label: 'Search', type: 'text' },
                        { key: 'element', label: 'Element', type: 'select',
                          options: countOptions(all, function (c) { return c.kind; }) },
                        { key: 'category', label: 'Category', type: 'select',
                          options: countOptions(all, function (c) { return elementCategory(c.kind, c.rec); }) }
                    ]
                };
            },
            fetch: function (nodes, narrowing) {
                narrowing = narrowing || {};
                return {
                    nodes: elementCandidates()
                        .filter(function (c) { return matchesNarrowing(c, narrowing); })
                        .map(elementNode),
                    edges: []
                };
            }
        };
    }

    // The library caches summaries until told otherwise, and what this pivot
    // offers is exactly what the canvas lacks.
    function watchElementPivot(graph) {
        if (!graph || typeof graph.on !== 'function' || !graph.pivots) return;
        var drop = function () { graph.pivots.invalidate(ELEMENT_PIVOT); };
        graph.on('nodeAdd', drop);
        graph.on('nodeRemove', drop);
    }

    /* ── the empty canvas (D11) ────────────────────────────── */
    // A canvas with nothing on it says why, and where the event's contents
    // are. Correlations are never the answer here: any correlated event puts
    // L0 on the canvas, so an empty seed has none to offer.
    function plural(n, one, many) { return n + ' ' + (n === 1 ? one : many); }

    // `seededEmpty`: the seed drew nothing. Otherwise the analyst emptied the
    // canvas, and "nothing is related" would be false.
    function emptyStatement(ev, seededEmpty) {
        var attrs = 0, objs = 0;
        (ev.Attribute || []).forEach(function (a) { if (!isDeleted(a)) attrs++; });
        (ev.Object || []).forEach(function (o) { if (!isDeleted(o)) objs++; });
        if (!attrs && !objs) {
            return { title: 'This event has no attributes or objects to draw.', detail: '', action: false };
        }
        var parts = [];
        if (attrs) parts.push(plural(attrs, 'attribute', 'attributes'));
        if (objs)  parts.push(plural(objs, 'object', 'objects'));
        var listed = parts.join(' and ') + (attrs + objs === 1 ? ' is' : ' are')
                     + ' listed under Event elements, to search and add.';
        return seededEmpty ? {
            title:  'Nothing in this event is related yet',
            detail: 'No object references, analyst relationships or correlations to draw. Its ' + listed,
            action: true
        } : {
            title:  'The canvas is empty',
            detail: 'The event\'s ' + listed,
            action: true
        };
    }

    function emptyStateContent(statement) {
        var box = document.createElement('div');
        var h = document.createElement('div');
        h.className = 'fw-semibold';
        h.textContent = statement.title;
        box.appendChild(h);
        if (statement.detail) {
            var p = document.createElement('div');
            p.className = 'mt-1';
            p.textContent = statement.detail;
            box.appendChild(p);
        }
        if (statement.action) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn btn-sm btn-primary mt-2';
            btn.textContent = 'Browse event elements';
            btn.addEventListener('click', function () {
                if (_graph && _graph.UIManager && typeof _graph.UIManager.openPivotMode === 'function') {
                    _graph.UIManager.openPivotMode([], ELEMENT_PIVOT);
                }
            });
            box.appendChild(btn);
        }
        return box;
    }

    // Pivotick shows the card while the canvas is empty and re-renders it on
    // each appearance; `initial` is whether the graph has ever held a node.
    function emptyStateOption(ev) {
        return {
            render: function (ctx) { return emptyStateContent(emptyStatement(ev, ctx.initial)); }
        };
    }

    /* ── filter panel ──────────────────────────────────────── */
    // Declaring any node facet replaces pivotick's derivation from every data
    // key, so the panel names the ones an analyst filters on. Provenance is
    // here rather than in the legend: it has no colour to sample.
    function distinctOptions(key) {
        return function (graph) {
            var seen = {};
            graph.getMutableNodes().forEach(function (n) {
                var v = (n.getData() || {})[key];
                if (v != null && v !== '') seen[String(v)] = true;
            });
            return Object.keys(seen).sort().map(function (v) { return { label: v, value: v }; });
        };
    }

    function nodeFacets() {
        return [
            { key: 'scope', label: 'Provenance', type: 'multiselect', options: [
                { label: 'This event',   value: 'self' },
                { label: 'Other events', value: 'foreign' }
            ] },
            { key: 'type',      label: 'Element',        type: 'multiselect', options: distinctOptions('type') },
            { key: 'category',  label: 'Category',       type: 'multiselect', options: distinctOptions('category') },
            { key: 'attr-type', label: 'Attribute type', type: 'multiselect', options: distinctOptions('attr-type') },
            { key: 'name',      label: 'Object',         type: 'multiselect', options: distinctOptions('name') },
            { key: 'to_ids',    label: 'IDS flag',       type: 'boolean' },
            { key: 'value',     label: 'Value',          type: 'regex' }
        ];
    }

    /* ── pivotick options ──────────────────────────────────── */
    function graphOptions() {
        return {
            isDirected: true,
            render: {
                type: 'svg',
                nodeTypeAccessor: function (node) {
                    var d = node.getData();
                    if (!d) return undefined;
                    return d.image ? 'image' : d.type;
                },
                nodeStyleMap: {
                    event:     { shape: 'hexagon', color: '#6fbe80', size: 26 },
                    object:    { shape: 'square',  color: '#428bca', size: 20 },
                    attribute: { shape: 'circle',  color: '#f39a1f', size: 13 },
                    image:     {
                        imageFit:    'frame',
                        size:        80,
                        strokeColor: 'rgba(255,255,255,0.55)',
                        strokeWidth: 2
                    }
                },
                defaultNodeStyle: {
                    // Labels are not drawn by default — render data.label above each node.
                    text: function (node) {
                        var d = node.getData();
                        return d ? d.label : '';
                    },
                    // Overlay a misp-iconify glyph on the coloured shape; pivotick
                    // resolves the glyph + "MISP Icons" font from the class itself.
                    iconClass: nodeIconClass,
                    // Image attachments (screenshots) draw an embedded thumbnail
                    // instead of a glyph — pivotick renders imagePath as an
                    // <image>, but only when iconClass is left unset (see above).
                    imagePath: function (node) {
                        var d = node.getData();
                        return (d && d.image) ? d.imageUrl : undefined;
                    },
                    textVerticalShift: -1,
                    badges: analystBadges
                },
                // D1's first edge dimension. Two kinds so far; correlations and
                // feed/server kinds arrive with their layers (PRD tasks 5, 5b).
                edgeTypeAccessor: function (edge) {
                    var d = edge.getData ? edge.getData() : null;
                    return d ? d.kind : undefined;
                },
                edgeStyleMap: {
                    'object-reference':     { strokeColor: '#428bca' },
                    'analyst-relationship': { strokeColor: '#f39a1f', dashed: true },
                    // Green to match the event nodes it joins; dashed like every
                    // other derived (as opposed to authored) relationship.
                    'event-correlation':    { strokeColor: '#6fbe80', dashed: true },
                    'correlation':          { strokeColor: '#888', dashed: true }
                },
                // Draw the relationship_type on every edge (referenced + newly created).
                defaultLabelStyle: {
                    labelAccessor: function (edge) {
                        var d = edge.getData ? edge.getData() : null;
                        return d ? (d.label || '') : '';
                    }
                }
            },
            simulation: {
                d3LinkDistance: 200
            },
            // No `save`: correlations are derived, never counted unsaved.
            pivots: [elementPivot(), correlationPivot(), relatedEventPivot()],
            callbacks: {
                // A correlated event is a leaf here (PRD §4) — it cannot expand
                // in place, so double-click hands the analyst over to its own
                // event page rather than leaving the proxy a dead end.
                onNodeDbclick: function (e, node) {
                    var d = node && node.getData ? node.getData() : null;
                    if (!d || d.type !== 'event') return;
                    if (!d.event_id || String(d.event_id) === String(eventId)) return;
                    window.location.href = baseurl + '/events/view2/' + d.event_id;
                }
            },
            UI: {
                mode: 'full',
                theme: 'dark',
                // Only drawing a reference reaches MISP, so creating or editing
                // a node or an edge's data is offered to nobody. A user who
                // cannot write the event gets no write tool at all; notes,
                // hides and layout stay, being canvas-only.
                editors: {
                    nodeEditor:  { enabled: false },
                    nodeCreator: { enabled: false },
                    edgeEditor:  { enabled: false },
                    edgeCreator: { enabled: canEdit },
                    deletion:    { enabled: canEdit }
                },
                // The layer switch. Declaring the facet is what makes edges
                // filterable at all — pivotick never derives edge facets.
                filter: {
                    facets: nodeFacets(),
                    edgeFacets: [
                        { key: 'kind', label: 'Relationship', type: 'multiselect' }
                    ]
                }
            }
        };
    }

    // Messages arrive already translated but unescaped (the browser decodes
    // the attribute), so build the node instead of interpolating innerHTML.
    function showError(loaderEl, msg) {
        if (!loaderEl) return;
        var p = document.createElement('p');
        p.className = 'text-danger mb-0';
        var icon = document.createElement('i');
        icon.className = 'fas fa-exclamation-triangle me-2';
        p.appendChild(icon);
        p.appendChild(document.createTextNode(msg));
        loaderEl.innerHTML = '';
        loaderEl.appendChild(p);
    }

    /* ── init ──────────────────────────────────────────────── */
    function initGraph() {
        if (_initialized) return;
        _initialized = true;

        var loaderEl    = document.getElementById('pivot-explorer-loader');
        var containerEl = document.getElementById('pivot-explorer-graph');

        if (typeof window.Pivotick !== 'function') {
            showError(loaderEl, text.libMissing);
            return;
        }

        fetch(baseurl + '/events/view/' + eventId + '.json', { credentials: 'same-origin' })
            .then(function (r) {
                if (!r.ok) throw new Error(r.status);
                return r.json();
            })
            .then(function (event) {
                _event   = event;
                var data = buildGraphData(event);

                if (loaderEl)    loaderEl.style.display    = 'none';
                if (containerEl) containerEl.style.display = '';

                _stats = data.stats;
                renderHeader();

                var editor = canEdit ? createEditor() : null;
                var opts   = graphOptions();
                if (editor) Object.assign(opts.callbacks, editor.callbacks);
                opts.UI.emptyState = emptyStateOption((event && event.Event) || {});
                // Only an event with something to show gets the panel (§8.10).
                if (eventHasAnalystData((event && event.Event) || {})) {
                    opts.UI.extraPanels = [analystPanel()];
                }

                _graph = new window.Pivotick(
                    containerEl,
                    { nodes: data.nodes, edges: data.edges },   // `stats` is ours, not pivotick's
                    opts
                );

                if (editor) {
                    try { editor.attach(_graph); }
                    catch (e) { console.error('[pivot-explorer] editor attach failed:', e); }
                }
                watchElementPivot(_graph);
                loadCorrelationCounts(_graph);
            })
            .catch(function (err) {
                console.error('[pivot-explorer] graph build failed:', err);
                _initialized = false;   // allow a retry on the next tab activation
                showError(loaderEl, text.loadFailed);
            });
    }

    /* ══════════════════════════════════════════════════════════
       EDITOR — drawing an object reference in pivotick's Create
       mode: isValidConnection gates the gesture, onBeforeEdgeCreate
       asks for the relationship and persists it before the edge lands.
       Putting an element on the canvas is not an edit; that is the
       element pivot, offered to every viewer.
       ══════════════════════════════════════════════════════════ */
    function createEditor() {
        var graph = null;

        /* ── notifications (fall back to console) ──────────── */
        function notify(kind, title, msg) {
            var n = graph && graph.notifier;
            if (n && typeof n[kind] === 'function') { n[kind](title, msg); return; }
            console.log('[pivot-explorer] ' + kind + ': ' + title + (msg ? ' — ' + msg : ''));
        }

        /* ── edge creation → what it can be, ask, save ──────── */
        function nodeData(n) {
            return n && typeof n.getData === 'function' ? n.getData() : null;
        }

        // Which link kinds a drawn edge could become (D2b). An object
        // reference is owned by an object and stays inside its event, so both
        // ends must be this event's own elements, and only an attribute or an
        // object can be referenced.
        function possibleKinds(source, target) {
            var s = nodeData(source) || {}, t = nodeData(target) || {};
            var kinds = [];
            if (canEdit && s.type === 'object' && isOwnElement(s)
                && (t.type === 'object' || t.type === 'attribute') && isOwnElement(t)) {
                kinds.push('object-reference');
            }
            return kinds;
        }

        // A note (no getData) is linking itself, which is not ours to judge.
        function isValidConnection(source, target) {
            if (!source || typeof source.getData !== 'function') return true;
            return possibleKinds(source, target).length > 0;
        }

        // The object_relationships vocabulary, fetched once on first use. A
        // failed fetch leaves only the free-text field, which the server
        // accepts anyway.
        var _vocabulary = null;
        function loadVocabulary() {
            if (_vocabulary) return _vocabulary;
            _vocabulary = fetch(baseurl + '/objectRelationships/index.json', {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' }
            })
            .then(function (r) {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.json();
            })
            .then(function (rows) {
                return (Array.isArray(rows) ? rows : [])
                    .map(function (r) { return r && r.name; })
                    .filter(function (n) { return typeof n === 'string' && n !== ''; })
                    .sort();
            })
            .catch(function (err) {
                console.error('[pivot-explorer] relationship list failed:', err);
                _vocabulary = null;   // ask again next time
                return [];
            });
            return _vocabulary;
        }

        function relationshipFields(names) {
            var fields = [];
            if (names.length) {
                fields.push({
                    key:          'relationship_type',
                    label:        'Relationship type',
                    type:         'select',
                    options:      names.map(function (n) { return { label: n, value: n }; }),
                    defaultValue: names.indexOf('related-to') !== -1 ? 'related-to' : names[0]
                });
            }
            fields.push({
                key:         'custom',
                label:       names.length ? 'Or a custom one' : 'Relationship type',
                type:        'text',
                placeholder: 'custom relationship'
            });
            return fields;
        }

        // The edge only lands once the reference is saved, so the history
        // records it as persisted and a refused save leaves nothing behind.
        function onBeforeEdgeCreate(ctx) {
            if (ctx.kind !== 'edge') return true;
            if (!possibleKinds(ctx.source, ctx.target).length) return false;
            var fromData = nodeData(ctx.source);
            var toData   = nodeData(ctx.target);

            return loadVocabulary().then(function (names) {
                return ctx.promptData({
                    title:       'Add relationship',
                    submitLabel: 'Save',
                    fields:      relationshipFields(names)
                });
            }).then(function (values) {
                if (!values) return false;   // cancelled
                var rel = String(values.custom || '').trim()
                          || String(values.relationship_type || '').trim();
                if (!rel) {
                    notify('warning', 'No relationship type', 'Pick one or type your own.');
                    return false;
                }
                return saveReference(fromData.uuid, toData.uuid, rel).then(function (ok) {
                    return ok ? {
                        accept:    true,
                        data:      { kind: 'object-reference', label: rel },
                        persisted: true
                    } : false;
                });
            });
        }

        function saveReference(sourceUuid, targetUuid, rel) {
            return fetch(baseurl + '/objectReferences/add/' + encodeURIComponent(sourceUuid) + '.json', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type':     'application/json',
                    'Accept':           'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    ObjectReference: {
                        referenced_uuid:   targetUuid,
                        relationship_type: rel,
                        comment:           ''
                    }
                })
            })
            .then(function (res) {
                if (!res.ok) {
                    return res.json().catch(function () { return {}; })
                        .then(function (body) {
                            throw new Error((body && (body.errors || body.message)) || ('HTTP ' + res.status));
                        });
                }
                return res.json().catch(function () { return {}; });
            })
            .then(function () {
                notify('success', 'Relationship added', rel);
                return true;
            })
            .catch(function (err) {
                console.error('[pivot-explorer] save failed:', err);
                notify('error', 'Save failed', String(err && err.message || err));
                return false;
            });
        }

        /* ── public: callbacks (options-time), attach (post-construction) ── */
        return {
            callbacks: {
                isValidConnection:  isValidConnection,
                onBeforeEdgeCreate: onBeforeEdgeCreate
            },
            attach: function (g) { graph = g; }
        };
    }

    /* ── boot ──────────────────────────────────────────────── */
    // The element loads this via assetLoader, i.e. ahead of its own markup, so
    // #pe-card is not there yet on a normal page load. Checking readyState also
    // covers the tab arriving after load.
    function boot() {
        var cardEl = document.getElementById('pe-card');
        if (!cardEl) return;

        var d = cardEl.dataset;
        eventId = d.peEventId || '';
        baseurl = d.peBaseurl || '';
        canEdit = d.peCanEdit === '1';
        text = {
            libMissing: d.peLibMissing || 'Graph library failed to load.',
            loadFailed: d.peLoadFailed || 'Failed to load event graph.'
        };

        // Lazy-load: build the graph only once its tab is actually shown.
        document.addEventListener('shown.bs.tab', function (e) {
            if (e.target && e.target.getAttribute('href') === '#tab-pivot-explorer') {
                initGraph();
            }
        });

        var pane = document.getElementById('tab-pivot-explorer');
        if (pane && pane.classList.contains('active')) {
            initGraph();
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }

}());
