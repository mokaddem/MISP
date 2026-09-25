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
// Requires window.Pivotick (pivotick.iife.js) and window.MispPivotNodes
// (misp-pivot-nodes.js, the node drawings); the element's assetLoader call
// pulls in all three.

(function () {
    'use strict';

    /* ── config (read off #pe-card in boot()) ──────────────── */
    var eventId = '';
    var baseurl = '';
    var canEdit = false;
    var text    = { libMissing: '', loadFailed: '' };
    // Analyst relationships are gated on role alone, not on the event (D8);
    // deleting one needs its creator org, or site admin.
    var canAnalyst = false;
    var orgUuid    = '';
    var siteAdmin  = false;
    // What an analyst relationship can be shared with: [[level, name]],
    // [[sharing group id, name]], the default level and the user's email.
    var analystSharing = { levels: [], sharingGroups: [], default: 1, authors: '' };
    // Per object template ('uuid.version'), the ui-priority of each relation.
    var uiPriorities = {};

    /* ── state ─────────────────────────────────────────────── */
    var _initialized = false;
    var _graph       = null;
    var _event       = null;

    /* ── helpers ───────────────────────────────────────────── */
    function truncate(str, max) {
        str = String(str == null ? '' : str);
        return str.length > max ? str.substring(0, max - 1) + '…' : str;
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
        return Object.assign({
            type:            'attribute',
            label:           val,
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
            imageUrl:        isImg ? attributeImageUrl(attr) : undefined,
            feed_hit:        attr.FeedHit ? true : undefined
        }, warningFields(attr), tagFields(attr), owner, analystFields(attr));
    }

    // The warninglists an attribute's value is on, as MISP's event payload
    // names them. Only to_ids attributes are checked, unless the instance
    // checks every attribute.
    function warningFields(attr) {
        var seen = {};
        var out = (attr.warnings || []).filter(function (w) {
            if (!w || w.warninglist_id == null || seen[w.warninglist_id]) return false;
            return (seen[w.warninglist_id] = true);
        }).map(function (w) {
            return { warninglist_id: String(w.warninglist_id), warninglist_name: w.warninglist_name,
                     warninglist_category: w.warninglist_category, match: w.match };
        });
        return { warnings: out.length ? out : undefined, warninglisted: out.length > 0 };
    }

    // An object's attribute, ranked by its template: the node drawing leads
    // with the highest ui_priority.
    function objectChildData(obj, attr, owner) {
        var ranks = uiPriorities[obj.template_uuid + '.' + obj.template_version];
        var data = attributeNodeData(attr, owner);
        if (ranks && ranks[attr.object_relation]) data.ui_priority = ranks[attr.object_relation];
        return data;
    }

    // Soft-deleted records (deleted=1) are tombstones — refs create no edge and
    // don't count a node as connected; deleted attributes/objects aren't shown.
    function isDeleted(rec) {
        return !!rec && (rec.deleted === true || rec.deleted === 1 || rec.deleted === '1');
    }

    // Node id an analyst relationship's target maps to, or null when it has no
    // node on this canvas. AnalystData::valid_targets is far wider than the
    // canvas — EventReport, GalaxyCluster, Organisation, SharingGroup and the
    // analyst-data types are all legal targets. An 'Event' target is drawn as
    // an event node, from the record the payload attaches (relationshipEvent).
    function analystTargetId(rel) {
        var t = String(rel.related_object_type || '');
        if (t === 'Attribute') return 'attr:'  + rel.related_object_uuid;
        if (t === 'Object')    return 'obj:'   + rel.related_object_uuid;
        if (t === 'Event')     return 'event:' + rel.related_object_uuid;
        return null;
    }

    // The event an 'Event'-typed relationship points at, as Relationship's
    // afterFind attaches it (fetchSimpleEvent), or null. Empty when the viewer
    // cannot see that event, which leaves the relationship undrawable.
    function relationshipEvent(rel) {
        var e = rel.related_object && rel.related_object.Event;
        return (e && e.uuid && String(e.uuid) === String(rel.related_object_uuid)) ? e : null;
    }

    // Walk every outbound analyst relationship in the event, calling
    // cb(relationship, sourceNodeId). Sources are the event itself, event-level
    // attributes, objects, and objects' child attributes; a tombstoned owner is
    // skipped whole, exactly as it is on the canvas.
    //
    // RelationshipInbound is deliberately absent: the bulk path attaches it only
    // at event level (PRD §3.4).
    function eachAnalystRelationship(ev, cb) {
        function walk(rec, sourceId) {
            if (isDeleted(rec)) return;
            (rec.Relationship || []).forEach(function (rel) {
                if (!isDeleted(rel)) cb(rel, sourceId);
            });
        }
        if (ev.uuid) walk(ev, 'event:' + ev.uuid);
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
        var eventTouched      = {};   // event node id -> the record it draws from
        var attrOwner         = {};   // object child attr uuid -> owning object uuid
        var liveObj           = {};   // uuid -> true, for endpoint resolution
        var liveAttr          = {};
        var liveEvent         = {};   // event node id -> record; others join per relationship

        if (ev.uuid) liveEvent['event:' + ev.uuid] = ev;

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
                eventTouched[id] = liveEvent[id];
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
            if (targetId && targetId.indexOf('event:') === 0 && !liveEvent[targetId]) {
                var other = relationshipEvent(rel);
                if (other) liveEvent[targetId] = other;
            }
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
    //   L1  everything an object reference or analyst relationship touches
    //   L2  the remaining objects, containment only, if the whole set fits
    //
    // Correlations are not a level: they are fetched per element (R1), and the
    // Correlations tab already lists the events this one shares values with.
    //
    // L2 is all-or-nothing on purpose. D10 says that above the budget "the seed
    // stops at L1": a greedy partial fill would draw an arbitrary 40 of
    // 28,410 objects, with nothing to say which 40.
    function computeSeed(ev) {
        var conn = computeConnectivity(ev);

        // An event node is an analyst relationship's endpoint or nothing: a
        // bare hexagon would make the seed permanently non-empty and D11's
        // "nothing to draw" message unreachable.
        var eventNodes = Object.keys(conn.eventTouched).map(function (id) {
            return { id: id, record: conn.eventTouched[id] };
        });

        var l1 = eventNodes.length;
        (ev.Attribute || []).forEach(function (a) {
            if (!isDeleted(a) && conn.linkedAttrUuids[a.uuid]) l1++;
        });

        var l2Uuids = {};
        var l2Cost  = 0;   // nodes: the object plus its live children
        (ev.Object || []).forEach(function (obj) {
            if (isDeleted(obj)) return;
            var cost = 1 + liveChildCount(obj);
            if (conn.connectedObjUuids[obj.uuid]) { l1 += cost; return; }
            l2Uuids[obj.uuid] = true;
            l2Cost += cost;
        });

        var l2Fits = (l1 + l2Cost) <= NODE_BUDGET;

        return {
            linkedAttrUuids:   conn.linkedAttrUuids,
            connectedObjUuids: conn.connectedObjUuids,
            eventNodes:        eventNodes,
            // Objects L2 actually draws — empty when the level did not fit, so
            // "is this object on the canvas?" is one lookup for every caller.
            l2Uuids:           l2Fits ? l2Uuids : {}
        };
    }

    // Galaxy clusters and non-galaxy tags, for the event card's context row.
    // A relationship's target event carries neither, and draws none.
    function eventContext(e) {
        var out = [];
        (e.Galaxy || []).forEach(function (g) {
            (g.GalaxyCluster || []).forEach(function (c) {
                out.push({ galaxy_type: g.type, value: c.value });
            });
        });
        return out.length ? out : undefined;
    }

    // A galaxy cluster's tag, misp-galaxy:TYPE="VALUE".
    function galaxyTag(name) {
        var m = /^misp-galaxy:([^=]+)="(.*)"$/.exec(String(name || ''));
        return m ? { galaxy_type: m[1], value: m[2] } : null;
    }

    // An element's tags and galaxy clusters, as `tags` and `clusters`. A
    // cluster is keyed by its tag, which is how it is attached everywhere:
    // the Tag list names it even where Galaxy is missing or trimmed.
    function tagFields(rec) {
        var named = {};
        (rec.Galaxy || []).forEach(function (g) {
            (g.GalaxyCluster || []).forEach(function (c) {
                if (c.tag_name) named[c.tag_name] = { galaxy_type: g.type, galaxy_name: g.name, value: c.value, uuid: c.uuid };
            });
        });
        var tags = [], clusters = [], seen = {};
        function cluster(tagName, local) {
            var n = named[tagName] || {}, g = galaxyTag(tagName) || {};
            clusters.push({ tag_name: tagName, galaxy_type: n.galaxy_type || g.galaxy_type,
                            galaxy_name: n.galaxy_name, value: n.value || g.value || tagName,
                            uuid: n.uuid, local: local || undefined });
        }
        (rec.Tag || []).forEach(function (t) {
            if (!t || !t.name || t.hide_tag || seen[t.name]) return;
            seen[t.name] = true;
            if (t.is_galaxy || galaxyTag(t.name)) { cluster(t.name, !!t.local); return; }
            tags.push({ name: t.name, colour: t.colour, local: t.local ? true : undefined,
                        relationship_type: t.relationship_type || undefined });
        });
        Object.keys(named).forEach(function (tagName) { if (!seen[tagName]) cluster(tagName, false); });
        return { tags: tags.length ? tags : undefined, clusters: clusters.length ? clusters : undefined };
    }

    function numberOr(v) {
        return (v == null || v === '') ? undefined : Number(v);
    }

    function eventNodeData(e) {
        var info = e.info != null ? String(e.info) : '';
        var orgc = e.Orgc || e.Org;
        var org  = (orgc && orgc.name) || '';
        var meta = [];
        if (e.date) meta.push(String(e.date));
        if (org)    meta.push(org);
        return Object.assign({
            type:        'event',
            label:       info || ('Event ' + (e.id || '')),
            description: meta.join(' · ') || 'Event',
            info:        info,
            date:        e.date,
            org:         org || undefined,
            uuid:        e.uuid,
            // Read by the event card (misp-pivot-nodes), not by the sidebar.
            id:                e.id,
            orgc:              orgc ? { name: orgc.name, uuid: orgc.uuid } : undefined,
            published:         e.published,
            publish_timestamp: numberOr(e.publish_timestamp) || undefined,
            distribution:      numberOr(e.distribution),
            attribute_count:   numberOr(e.attribute_count),
            object_count:      e.Object ? e.Object.filter(function (o) { return !isDeleted(o); }).length : numberOr(e.object_count),
            report_count:      e.EventReport ? e.EventReport.length : undefined,
            context:           eventContext(e)
        }, tagFields(e), provenance(e.id, e.uuid), analystFields(e));
    }

    // Shared object node data (graph builder + element pivot).
    function objectNodeData(obj, owner) {
        return Object.assign({
            type:            'object',
            label:           obj.name || 'Object',
            description:     obj['meta-category'] ? (obj['meta-category'] + ' object') : 'Object',
            name:            obj.name,
            'meta-category': obj['meta-category'],
            uuid:            obj.uuid
        }, owner, analystFields(obj));
    }

    // A feed or server this event's values were seen in: one node per source,
    // off the payload's deduplicated event.Feed / event.Server map. No `name`
    // key, which the Object facet reads. A restricted Server source carries
    // only id and name (Feed.php), so everything past the label is optional.
    var SOURCES = [
        { scope: 'Feed',   type: 'feed',   kind: 'feed-correlation' },
        { scope: 'Server', type: 'server', kind: 'server-correlation' }
    ];

    // The event's feeds or servers by id. Keyed by id when MISP builds it,
    // but serialised as a list, and a source can appear once per batch of
    // attributes MISP looked up, each naming only its share of the events.
    function sourceMap(ev, scope) {
        var known = {};
        Object.keys(ev[scope] || {}).forEach(function (k) {
            var src = ev[scope][k];
            if (!src || src.id == null) return;
            var id = String(src.id);
            if (!known[id]) known[id] = Object.assign({}, src, { event_uuids: undefined });
            (src.event_uuids || []).forEach(function (uuid) {
                var list = known[id].event_uuids = known[id].event_uuids || [];
                if (list.indexOf(uuid) === -1) list.push(uuid);
            });
        });
        return known;
    }

    function sourceNodeData(type, src) {
        var fmt = src.source_format ? src.source_format + ' feed' : '';
        return {
            type:          type,
            label:         src.name || (type + ' ' + src.id),
            description:   [src.provider, fmt].filter(Boolean).join(' · ')
                           || (type === 'feed' ? 'Feed' : 'Server'),
            source_id:     String(src.id),
            provider:      src.provider,
            url:           src.url,
            source_format: src.source_format,
            feed_events:   (src.event_uuids || []).length || undefined,
            // Not this event's. Provenance is binary (D2); the node's type
            // already says it is a feed and not another event.
            scope:         'foreign'
        };
    }

    /* ── node drawings (misp-pivot-nodes) ──────────────────── */
    // Each MISP element draws its S design at rest, its M chip once the
    // canvas renders it at the chip's size, and its richest drawing (the XL
    // card for events and objects, the chip otherwise) on hover or a lone
    // selection. XL stays out of the zoom tiers so every element shares one
    // footprint, and so one threshold. Badges stay the explorer's own.
    var CHIP = { width: 140, height: 44 };

    // Spaces the layout for the glyph at rest rather than the chip's half
    // width, so chips may touch once zoomed in.
    var LAYOUT_SIZE = 45;

    // The chip engages a little before zoom 1, drawn slightly under its size.
    var CHIP_FROM_ZOOM = 0.8;

    // The others fall back to their chip at XL, so once the chip tier is on
    // screen their hover drawing would only repeat it, smaller.
    var HAS_OWN_XL = { event: true, object: true };

    function withBadges(style) {
        return Object.assign({}, style, { badges: nodeBadges });
    }

    function elementOf(node) {
        var d = node.getData();
        if (!d) return undefined;
        if (d.image) return 'image';
        return d.type === 'tag' ? 'taxonomy' : d.type;
    }

    var ELEMENT_LABELS = { taxonomy: 'tag', cluster: 'galaxy cluster' };

    // The legend samples a node's resolved `color`, which a drawn node leaves
    // transparent, so the Element rows carry the entity hues themselves.
    function elementLegendEntries(graph) {
        var P = window.MispPivotNodes.palette();
        var colour = {
            event: P.event.core, object: P.object.core, attribute: P.attribute.core,
            image: P.attribute.core, cluster: P.galaxy.core, taxonomy: P.tag.core,
            feed: P.feed.core, server: '#9b59b6'
        };
        var seen = {};
        graph.getMutableNodes().forEach(function (n) {
            var e = elementOf(n);
            if (e) seen[e] = true;
        });
        return Object.keys(seen).map(function (e) {
            return {
                id: e, label: ELEMENT_LABELS[e] || e, color: colour[e] || '#888',
                predicate: function (node) { return elementOf(node) === e; }
            };
        });
    }

    function mispNodeStyles() {
        var N = window.MispPivotNodes;
        var rest = N.options({
            size:    'S',
            theme:   'dark',
            fontUrl: baseurl + '/webfonts/misp-iconify.woff2'
        }).nodeStyleMap;
        var chip = N.styleMap('M'), focus = N.styleMap('XL');
        var map = {};
        Object.keys(rest).forEach(function (entity) {
            map[entity] = Object.assign(withBadges(rest[entity]), {
                tiers:      [{ width: CHIP.width, height: CHIP.height,
                               minRenderedSize: 2 * LAYOUT_SIZE * CHIP_FROM_ZOOM,
                               style: withBadges(chip[entity]) }],
                focusTier:  withBadges(focus[entity]),
                layoutSize: LAYOUT_SIZE
            });
            if (!HAS_OWN_XL[entity]) map[entity].focusTierYieldsAt = 0;
        });
        return map;
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
            var data = Object.assign({ kind: kind, label: label || '' }, extra);
            edges.push({ from: from, to: to, data: data });
            return true;
        }

        // Register an attribute's id (dedupe + edge existence) and return its node
        // dict, or null if already added. The caller decides where to place it —
        // top-level (nodes) or nested inside an object (children).
        function buildAttributeNode(attr, obj) {
            var id = 'attr:' + attr.uuid;
            if (nodeSet[id]) return null;
            nodeSet[id] = true;
            var owner = ownerIn(ev, attr);
            return { id: id, data: obj ? objectChildData(obj, attr, owner) : attributeNodeData(attr, owner) };
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

        /* L1 events — this one or another, each an analyst relationship's
           endpoint. Another event is a leaf: only its record came along. */
        seed.eventNodes.forEach(function (e) {
            addNode(e.id, { id: e.id, data: eventNodeData(e.record) });
        });

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
                var child = buildAttributeNode(attr, obj);
                if (child) children.push(child);
            });

            addNode(objId, {
                id:       objId,
                children: children,
                data:     objectNodeData(obj, ownerIn(ev, obj))
            });
        });

        /* Object references (added last so both ends already exist) */
        (ev.Object || []).forEach(function (obj) {
            var objId = 'obj:' + obj.uuid;
            (obj.ObjectReference || []).forEach(function (ref) {
                if (isDeleted(ref)) return;
                var prefix   = (String(ref.referenced_type) === '1') ? 'obj:' : 'attr:';
                var targetId = prefix + ref.referenced_uuid;
                var rel      = ref.relationship_type || 'related-to';
                addEdge(objId, targetId, rel, 'object-reference',
                        { uuid: ref.uuid, relationship_type: rel });
            });
        });

        /* Analyst relationships (D1's second kind, L1 alongside object
           references). Both endpoints were seeded above, so a rejected edge
           means the target has no node on this canvas at all — a relationship
           pointing at another event's attribute, or at an element type the
           canvas does not draw. */
        eachAnalystRelationship(ev, function (rel, sourceId) {
            if (rel.related_object_uuid && rel.object_uuid === rel.related_object_uuid) return;
            var targetId = analystTargetId(rel);
            if (!targetId || !nodeSet[targetId] || !nodeSet[sourceId]) return;
            var type = rel.relationship_type || 'related-to';
            addEdge(sourceId, targetId, type, 'analyst-relationship',
                    { authors: rel.authors, orgc: rel.orgc_uuid, uuid: rel.uuid,
                      relationship_type: type });
        });

        /* Feed and server correlations (D1). Derived, like correlations, so a
           hit never puts an element on the canvas: it is drawn from elements
           the seed already took, and a source node appears with its first
           drawable hit. Past 10,000
           hits MISP drops the sources and flags each attribute FeedHit, which
           attributeNodeData turns into a badge.
           The edge runs source → attribute: pivotick stands in for an edge
           into a collapsed object's child, and draws none out of one. */
        function eachLiveAttribute(fn) {
            (ev.Attribute || []).forEach(function (a) { if (!isDeleted(a)) fn(a); });
            (ev.Object || []).forEach(function (o) {
                if (isDeleted(o)) return;
                (o.Attribute || []).forEach(function (a) { if (!isDeleted(a)) fn(a); });
            });
        }
        SOURCES.forEach(function (s) {
            var known = sourceMap(ev, s.scope);
            eachLiveAttribute(function (a) {
                (a[s.scope] || []).forEach(function (hit) {
                    var attrId = 'attr:' + a.uuid;
                    if (!nodeSet[attrId]) return;
                    var srcId = s.type + ':' + hit.id;
                    if (!nodeSet[srcId]) {
                        addNode(srcId, { id: srcId,
                            data: sourceNodeData(s.type, known[String(hit.id)] || hit) });
                    }
                    addEdge(srcId, attrId, '', s.kind);
                });
            });
        });

        return { nodes: nodes, edges: edges };
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

    var FEED_COLOR = '#5bc0de';

    function nodeBadges(node) {
        return analystBadges(node).concat(feedHitBadges(node), tagBadges(node));
    }

    function clusterName(c) {
        return (c.galaxy_name || c.galaxy_type || 'Cluster') + ': ' + c.value;
    }

    // An attribute's tags and clusters, read in the sidebar. An event card
    // draws its own.
    function tagBadges(node) {
        var d = node && node.getData ? node.getData() : null;
        if (!d || d.type !== 'attribute' || !(d.tags || d.clusters)) return [];
        var tags = d.tags || [], clusters = d.clusters || [];
        var P = window.MispPivotNodes && window.MispPivotNodes.palette();
        return [{
            position:  'se',
            iconClass: 'fas fa-tag',
            color:     tags.length ? (tags[0].colour || '#888') : ((P && P.galaxy.core) || '#888'),
            title:     tags.map(function (t) { return t.name; }).concat(clusters.map(clusterName)).join('\n'),
            onClick:   function (e, clicked) { showInSidebar(clicked); }
        }];
    }

    // The degraded shape: MISP saw the value in a feed but did not say which,
    // so there is no node to draw an edge to (§7).
    function feedHitBadges(node) {
        var d = node && node.getData ? node.getData() : null;
        if (!d || !d.feed_hit) return [];
        return [{
            position:  'sw',
            iconClass: 'fas fa-rss',
            color:     FEED_COLOR,
            title:     'Seen in a feed — too many hits in this event to name which'
        }];
    }

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
            onClick:  function (e, clicked) { showInSidebar(clicked); }
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

    function showInSidebar(node) {
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

    function analystPanelTitle(selection) {
        var d = (selection && !Array.isArray(selection) && selection.getData) ? selection.getData() : null;
        return 'Notes & opinions' + (d && d.analyst_count ? ' (' + d.analyst_count + ')' : '');
    }

    function analystPanel() {
        return { id: 'analyst-data', title: analystPanelTitle, render: renderAnalystPanel };
    }

    /* ── pivot: correlations (R1) ──────────────────────────── */
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

    // Correlated attributes land inside their event's node — the one already
    // drawn, if any, since ingest merges children into a container by id.
    // This event's side of each pair is brought along when it is not drawn yet
    // (an event-level attribute, or one inside an object L2 skipped).
    function correlationResult(payload) {
        var index = ownAttributeIndex();
        var cards = payload.events || {};
        var containers = {}, order = [], edges = [], seen = {}, sourceNodes = [];
        (payload.pairs || []).forEach(function (p) {
            var ev  = p.Event || {};
            var cid = 'event:' + ev.uuid;
            if (!containers[cid]) {
                containers[cid] = { id: cid, data: eventNodeData(cards[ev.id] || ev), children: [] };
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

    var CORRELATION_PIVOT = 'correlations';

    // The selection's attributes that correlate, each with its own count: an
    // object is closed, so this is where one of its attributes is picked.
    function correlatingAttributes(nodes) {
        var index = ownAttributeIndex();
        return attributeUuidsOf(nodes).map(function (uuid) {
            var a = index.byUuid[uuid] || {};
            var name = a.object_relation || a.type || 'attribute';
            return { uuid: uuid, label: name + ': ' + String(a.value == null ? '' : a.value).slice(0, 60),
                     count: (_counts && _counts.attributes[uuid]) || 0 };
        }).filter(function (a) { return a.count > 0; });
    }

    function pickedAttributes(nodes, narrowing) {
        var all = correlatingAttributes(nodes);
        var picks = (narrowing && narrowing.attribute) || [];
        return picks.length ? all.filter(function (a) { return picks.indexOf(a.uuid) !== -1; }) : all;
    }

    function correlationPivot() {
        return {
            id:            CORRELATION_PIVOT,
            label:         'Correlations',
            maxCandidates: NODE_BUDGET,
            appliesTo: function (nodes) {
                return nodes.filter(function (n) { return ownElementCount(n.getData()) > 0; });
            },
            summarize: function (nodes, narrowing) {
                var all = correlatingAttributes(nodes);
                var summary = { total: pickedAttributes(nodes, narrowing).reduce(function (s, a) { return s + a.count; }, 0) };
                if (all.length > 1) {
                    summary.facets = [{ key: 'attribute', label: 'Attribute', type: 'multiselect',
                        options: all.map(function (a) { return { label: a.label, value: a.uuid, count: a.count }; }) }];
                }
                return summary;
            },
            fetch: function (nodes, narrowing, ctx) {
                var picks = (narrowing && narrowing.attribute) || [];
                var uuids = attributeUuidsOf(nodes).filter(function (uuid) {
                    return !picks.length || picks.indexOf(uuid) !== -1;
                });
                return fetchCorrelated({ attribute_uuids: uuids }, ctx && ctx.signal);
            }
        };
    }

    // Declared, never queried (pivotick draws the rim badge from it): this
    // event's attributes and objects wear the number of correlations the pivot
    // would bring. Zero declares nothing.
    function declarePotential(node) {
        var own = ownElementCount(node.getData());
        if (own) node.setPotential('correlations', own);
    }

    // Once on the counts, then on each node as it lands (an ingest, an undo's
    // redo), before the render that follows it.
    function declareAllPotential(graph) {
        graph.getMutableNodes().forEach(declarePotential);
        graph.renderer.update();
        graph.on('nodeAdd', declarePotential);
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
            declareAllPotential(graph);
        })
        .catch(function (err) {
            console.error('[pivot-explorer] correlation counts failed:', err);
        });
    }

    /* ── pivot: the events of a MISP feed ──────────────────── */
    // The payload names, per attribute, the MISP-feed events its value is in;
    // /feeds/manifestEvents adds what each card draws, from the manifest the
    // instance holds. A feed event is a leaf, like another event, keyed by its
    // feed: two feeds can carry the same event.
    var FEED_EVENTS_PIVOT = 'feed-events';

    var _feeds = null, _feedsFor = null;
    function feedSource(feedId) {
        if (!_feeds || _feedsFor !== _event) {
            _feeds = sourceMap((_event && _event.Event) || {}, 'Feed');
            _feedsFor = _event;
        }
        return _feeds[String(feedId)] || null;
    }

    function feedEventUuids(d) {
        if (!d || d.type !== 'feed') return [];
        var src = feedSource(d.source_id);
        return (src && src.event_uuids) || [];
    }

    function feedEventId(feedId, uuid) {
        return 'feed-event:' + feedId + ':' + uuid;
    }

    function feedEventNodeData(card, src) {
        var orgc = card.Orgc && card.Orgc.name ? card.Orgc : null;
        var info = card.info != null ? String(card.info) : '';
        return {
            type:        'event',
            label:       info || card.uuid,
            description: [card.date, orgc && orgc.name, 'in ' + src.name].filter(Boolean).join(' · '),
            info:        info || undefined,
            date:        card.date || undefined,
            org:         orgc ? orgc.name : undefined,
            orgc:        orgc ? { name: orgc.name, uuid: orgc.uuid } : undefined,
            uuid:        card.uuid,
            context:     eventContext(card),
            tags:        tagFields(card).tags,
            clusters:    tagFields(card).clusters,
            // Read by the event drawings: a cached hit, and whose.
            _provenance: 'feed',
            source:      { kind: 'feed', type: 'Feed', name: src.name,
                           provider: src.provider, url: src.url },
            feed_id:     String(src.id),
            feed_name:   src.name,
            scope:       'foreign'
        };
    }

    // Each feed event lands beside its feed, joined to the attributes of this
    // event whose values it holds; one not drawn yet comes along.
    function feedEventsResult(feedNodes, payload) {
        var cards = (payload && payload.events) || {};
        var index = ownAttributeIndex();
        var nodes = [], edges = [], seen = {};
        function drawn(id) {
            return !!(_graph && typeof _graph.getNode === 'function' && _graph.getNode(id));
        }
        feedNodes.forEach(function (fn) {
            var d = fn.getData();
            var src = feedSource(d.source_id);
            if (!src) return;
            var feedCards = cards[String(src.id)] || {};
            var srcId = 'feed:' + src.id;
            (src.event_uuids || []).forEach(function (uuid) {
                var id = feedEventId(src.id, uuid);
                if (seen[id]) return;
                seen[id] = true;
                nodes.push({ id: id, data: feedEventNodeData(feedCards[uuid] || { uuid: uuid }, src) });
                edges.push({ id: 'feedev:' + src.id + ':' + uuid, from: srcId, to: id,
                             data: { kind: 'feed-event', label: '' } });
            });
            Object.keys(index.byUuid).forEach(function (attrUuid) {
                var a = index.byUuid[attrUuid];
                (a.Feed || []).forEach(function (hit) {
                    if (String(hit.id) !== String(src.id)) return;
                    var attrId = 'attr:' + attrUuid;
                    (hit.event_uuids || []).forEach(function (uuid) {
                        if (!seen[feedEventId(src.id, uuid)]) return;
                        if (!seen[attrId] && !drawn(attrId)) {
                            nodes.push({ id: attrId, data: attributeNodeData(a, ownerIn(_event.Event, a)) });
                        }
                        seen[attrId] = true;
                        edges.push({ id: 'feedhit:' + src.id + ':' + uuid + ':' + attrUuid,
                                     from: feedEventId(src.id, uuid), to: attrId,
                                     data: { kind: 'feed-correlation', label: '' } });
                    });
                });
            });
        });
        return { nodes: nodes, edges: edges };
    }

    function fetchFeedEvents(feedNodes, signal) {
        var feeds = {};
        feedNodes.forEach(function (n) {
            var d = n.getData();
            feeds[d.source_id] = feedEventUuids(d);
        });
        return fetch(baseurl + '/feeds/manifestEvents.json', {
            method: 'POST',
            credentials: 'same-origin',
            signal: signal,
            headers: {
                'Content-Type':     'application/json',
                'Accept':           'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ feeds: feeds })
        })
        .then(function (r) {
            if (!r.ok) throw new Error('HTTP ' + r.status);
            return r.json();
        })
        .then(function (payload) { return feedEventsResult(feedNodes, payload); });
    }

    function feedEventsPivot() {
        return {
            id:            FEED_EVENTS_PIVOT,
            label:         'Feed events',
            maxCandidates: NODE_BUDGET,
            appliesTo: function (nodes) {
                return nodes.filter(function (n) { return feedEventUuids(n.getData()).length > 0; });
            },
            summarize: function (nodes) {
                return { total: sumCounts(nodes, function (d) { return feedEventUuids(d).length; }) };
            },
            fetch: function (nodes, narrowing, ctx) {
                return fetchFeedEvents(nodes, ctx && ctx.signal);
            }
        };
    }

    // A MISP feed wears the number of its events this event's values are in.
    function declareFeedPotential(node) {
        var n = feedEventUuids(node.getData()).length;
        if (n) node.setPotential(FEED_EVENTS_PIVOT, n);
        return n > 0;
    }

    function declareAllFeedPotential(graph) {
        var any = false;
        graph.getMutableNodes().forEach(function (n) { any = declareFeedPotential(n) || any; });
        if (any) graph.renderer.update();
        graph.on('nodeAdd', declareFeedPotential);
    }

    /* ── pivot: tags and galaxy clusters ───────────────────── */
    // One node per tag or cluster, joined to every element on the canvas that
    // carries it, so two elements sharing a cluster are visibly linked. An
    // object answers for its attributes: MISP tags those, not the object.
    var TAG_PIVOT = 'tags';

    function tagNodeId(t)     { return 'tag:' + t.name; }
    function clusterNodeId(c) { return 'cluster:' + c.tag_name; }

    // Tag data per attribute uuid, for objects whose children are not drawn.
    var _tagIndex = {}, _tagIndexFor = null;
    function attributeTags(uuid) {
        if (_tagIndexFor !== _event) { _tagIndex = {}; _tagIndexFor = _event; }
        if (!(uuid in _tagIndex)) {
            var a = ownAttributeIndex().byUuid[uuid];
            _tagIndex[uuid] = a ? tagFields(a) : {};
        }
        return _tagIndex[uuid];
    }

    // Who carries what, for one node: [{ id, tags, clusters }].
    function carriers(node) {
        return carriersOfData(node.id, node.getData());
    }

    function carriersOfData(id, d) {
        d = d || {};
        if (d.type === 'attribute' || d.type === 'event') {
            return (d.tags || d.clusters) ? [{ id: id, tags: d.tags, clusters: d.clusters }] : [];
        }
        if (d.type === 'object') {
            return (ownAttributeIndex().byObject[d.uuid] || []).map(function (uuid) {
                var f = attributeTags(uuid);
                return { id: 'attr:' + uuid, tags: f.tags, clusters: f.clusters };
            }).filter(function (c) { return c.tags || c.clusters; });
        }
        return [];
    }

    // Distinct tags and clusters over some carriers, by node id.
    function labelsOf(list) {
        var out = {};
        list.forEach(function (c) {
            (c.tags || []).forEach(function (t) {
                if (!out[tagNodeId(t)]) out[tagNodeId(t)] = { tag: t };
            });
            (c.clusters || []).forEach(function (cl) {
                if (!out[clusterNodeId(cl)]) out[clusterNodeId(cl)] = { cluster: cl };
            });
        });
        return out;
    }

    function carriersOf(nodes) {
        return nodes.reduce(function (all, n) { return all.concat(carriers(n)); }, []);
    }

    function labelCount(node) {
        return Object.keys(labelsOf(carriers(node))).length;
    }

    function labelNode(id, l) {
        if (l.tag) {
            return { id: id, data: { type: 'tag', label: l.tag.name, name: l.tag.name,
                                     colour: l.tag.colour, local: l.tag.local } };
        }
        var c = l.cluster;
        return { id: id, data: { type: 'cluster', label: c.value, value: c.value,
                                 galaxy_type: c.galaxy_type, galaxy_name: c.galaxy_name,
                                 tag_name: c.tag_name, uuid: c.uuid } };
    }

    function tagEdge(from, to, rel) {
        return { id: 'tagged:' + from + '>' + to, from: from, to: to,
                 data: { kind: 'tag', label: rel || '' } };
    }

    // One carrier's edges to every tag and cluster it carries.
    function tagEdgesOf(c) {
        return (c.tags || []).map(function (t) { return tagEdge(c.id, tagNodeId(t), t.relationship_type); })
            .concat((c.clusters || []).map(function (cl) { return tagEdge(c.id, clusterNodeId(cl)); }));
    }

    // The selection's tags and clusters, each joined to its carriers in the
    // selection and to any other carrier already on the canvas.
    function tagsResult(nodes) {
        var labels = labelsOf(carriersOf(nodes));
        var drawn = _graph ? carriersOf(_graph.getMutableNodes()) : [];
        var edges = [], seen = {};
        carriersOf(nodes).concat(drawn).forEach(function (c) {
            tagEdgesOf(c).forEach(function (e) {
                if (labels[e.to] && !seen[e.id]) { seen[e.id] = true; edges.push(e); }
            });
        });
        return {
            nodes: Object.keys(labels).map(function (id) { return labelNode(id, labels[id]); }),
            edges: edges
        };
    }

    function tagPivot() {
        return {
            id:            TAG_PIVOT,
            label:         'Tags & clusters',
            maxCandidates: NODE_BUDGET,
            appliesTo: function (nodes) {
                return nodes.filter(function (n) { return labelCount(n) > 0; });
            },
            summarize: function (nodes) {
                return { total: Object.keys(labelsOf(carriersOf(nodes))).length };
            },
            fetch: function (nodes) { return tagsResult(nodes); }
        };
    }

    // Whatever a pivot lands is joined to the tags and clusters already
    // drawn, and a tag or cluster it lands to the carriers already drawn, so
    // the order things reached the canvas in does not decide their links.
    // Each edge rides with the node that lands it.
    function joinDrawnTags(result) {
        if (!_graph || !result || !result.nodes) return result;
        var edges = result.edges = result.edges || [], have = {}, landing = [], labels = {};
        edges.forEach(function (e) { have[e.id] = true; });
        function add(e) { if (!have[e.id]) { have[e.id] = true; edges.push(e); } }
        function drawn(id) { return !!_graph.getMutableNode(id); }
        (function walk(list) {
            list.forEach(function (raw) {
                if (!drawn(raw.id)) {
                    landing.push(raw);
                    var t = (raw.data || {}).type;
                    if (t === 'tag' || t === 'cluster') labels[raw.id] = true;
                }
                walk(raw.children || []);
            });
        })(result.nodes);
        landing.forEach(function (raw) {
            carriersOfData(raw.id, raw.data).forEach(function (c) {
                tagEdgesOf(c).forEach(function (e) { if (drawn(e.to)) add(e); });
            });
        });
        if (Object.keys(labels).length) {
            carriersOf(_graph.getMutableNodes()).forEach(function (c) {
                tagEdgesOf(c).forEach(function (e) { if (labels[e.to]) add(e); });
            });
        }
        return result;
    }

    function joiningDrawnTags(def) {
        var fetchResult = def.fetch;
        def.fetch = function () {
            var r = fetchResult.apply(this, arguments);
            return r && typeof r.then === 'function' ? r.then(joinDrawnTags) : joinDrawnTags(r);
        };
        return def;
    }

    function declareTagPotential(node) {
        var n = labelCount(node);
        if (n) node.setPotential(TAG_PIVOT, n);
        return n > 0;
    }

    function declareAllTagPotential(graph) {
        var any = false;
        graph.getMutableNodes().forEach(function (n) { any = declareTagPotential(n) || any; });
        if (any) graph.renderer.update();
        graph.on('nodeAdd', declareTagPotential);
    }

    /* ── pivot: other events carrying a tag or cluster ─────── */
    // Each event lands as a card joined to the selected tag and cluster nodes
    // it carries. /events/taggedEvents says, per event and tag, whether the
    // event carries it or only one of its attributes does.
    var TAGGED_EVENTS_PIVOT = 'tagged-events';
    var TAGGED_EVENTS_LIMIT = 200;

    function tagNameOf(node) {
        var d = node.getData() || {};
        if (d.type === 'tag') return d.name || null;
        if (d.type === 'cluster') return d.tag_name || null;
        return null;
    }

    function tagNamesOf(nodes) {
        var seen = {};
        return nodes.map(tagNameOf).filter(function (name) {
            if (!name || seen[name]) return false;
            return (seen[name] = true);
        });
    }

    function matchMode(narrowing) {
        return narrowing && narrowing.mode === 'or' ? 'or' : 'and';
    }

    // summarize and fetch ask the same question back to back.
    var _tagged = { key: null, promise: null };
    function fetchTaggedEvents(names, mode, signal) {
        var body = JSON.stringify({ tags: names, mode: mode });
        if (_tagged.key !== body) {
            var promise = fetch(baseurl + '/events/taggedEvents/' + encodeURIComponent(eventId) + '.json', {
                method: 'POST',
                credentials: 'same-origin',
                signal: signal,
                headers: {
                    'Content-Type':     'application/json',
                    'Accept':           'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: body
            })
            .then(function (r) {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.json();
            })
            .catch(function (err) {
                if (_tagged.promise === promise) _tagged = { key: null, promise: null };
                throw err;
            });
            _tagged = { key: body, promise: promise };
        }
        return _tagged.promise;
    }

    function taggedEventsResult(nodes, payload) {
        var out = [], edges = [];
        ((payload && payload.events) || []).forEach(function (card) {
            var id = 'event:' + card.uuid;
            out.push({ id: id, data: eventNodeData(card) });
            nodes.forEach(function (n) {
                var how = (card.matched || {})[tagNameOf(n)];
                if (how) edges.push(tagEdge(id, n.id, how === 'attribute' ? 'via attribute' : ''));
            });
        });
        return { nodes: out, edges: edges };
    }

    function taggedEventsPivot() {
        return {
            id:            TAGGED_EVENTS_PIVOT,
            label:         'Events with this tag',
            maxCandidates: NODE_BUDGET,
            appliesTo: function (nodes) {
                return nodes.filter(function (n) { return !!tagNameOf(n); });
            },
            summarize: function (nodes, narrowing, ctx) {
                var names = tagNamesOf(nodes);
                return fetchTaggedEvents(names, matchMode(narrowing), ctx && ctx.signal)
                    .then(function (r) {
                        var summary = { total: Math.min(r.total || 0, TAGGED_EVENTS_LIMIT) };
                        if (names.length > 1) {
                            summary.facets = [{ key: 'mode', label: 'Match', type: 'select', options: [
                                { label: 'All of them', value: 'and' },
                                { label: 'Any of them', value: 'or' }
                            ] }];
                        }
                        return summary;
                    });
            },
            fetch: function (nodes, narrowing, ctx) {
                return fetchTaggedEvents(tagNamesOf(nodes), matchMode(narrowing), ctx && ctx.signal)
                    .then(function (r) { return taggedEventsResult(nodes, r); });
            }
        };
    }

    /* ── pivot: a cluster's galaxy relations ───────────────── */
    // The relations stored on the selected cluster, outbound only: each
    // target lands as a cluster node, so it merges with one already drawn.
    var RELATED_CLUSTERS_PIVOT = 'related-clusters';

    var _relations = {};
    function clusterRelations(uuid, signal) {
        if (!_relations[uuid]) {
            _relations[uuid] = fetch(baseurl + '/galaxy_clusters/relatedClusters/' + encodeURIComponent(uuid) + '.json', {
                credentials: 'same-origin',
                signal: signal,
                headers: { 'Accept': 'application/json' }
            })
            .then(function (r) {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.json();
            })
            .then(function (payload) { return (payload && payload.relations) || []; })
            .catch(function (err) {
                delete _relations[uuid];
                throw err;
            });
        }
        return _relations[uuid];
    }

    function relationsOf(nodes, signal) {
        return Promise.all(nodes.map(function (n) { return clusterRelations(n.getData().uuid, signal); }));
    }

    function relatedClustersResult(nodes, lists) {
        var out = [], edges = [], seen = {};
        nodes.forEach(function (n, i) {
            lists[i].forEach(function (r) {
                var c = r.cluster || {};
                if (!c.tag_name) return;
                var id = clusterNodeId(c);
                if (id === n.id) return;
                if (!seen[id]) {
                    seen[id] = true;
                    out.push(labelNode(id, { cluster: { tag_name: c.tag_name, value: c.value,
                        galaxy_type: c.type, galaxy_name: c.galaxy_name, uuid: c.uuid } }));
                }
                var eid = 'clrel:' + n.id + '>' + id + ':' + r.relation;
                if (!seen[eid]) {
                    seen[eid] = true;
                    edges.push({ id: eid, from: n.id, to: id,
                                 data: { kind: 'cluster-relation', label: r.relation || '' } });
                }
            });
        });
        return { nodes: out, edges: edges };
    }

    function relatedClustersPivot() {
        return {
            id:            RELATED_CLUSTERS_PIVOT,
            label:         'Related clusters',
            maxCandidates: NODE_BUDGET,
            appliesTo: function (nodes) {
                return nodes.filter(function (n) {
                    var d = n.getData() || {};
                    return d.type === 'cluster' && !!d.uuid;
                });
            },
            summarize: function (nodes, narrowing, ctx) {
                return relationsOf(nodes, ctx && ctx.signal).then(function (lists) {
                    return { total: lists.reduce(function (s, l) { return s + l.length; }, 0) };
                });
            },
            fetch: function (nodes, narrowing, ctx) {
                return relationsOf(nodes, ctx && ctx.signal).then(function (lists) {
                    return relatedClustersResult(nodes, lists);
                });
            }
        };
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
                .map(function (a) { return { id: 'attr:' + a.uuid, data: objectChildData(c.rec, a, ownerIn(ev, a)) }; })
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
    // are. Correlations do not seed the canvas: they are reached from the
    // elements put on it.
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
            title:  'Nothing in this event is linked yet',
            detail: 'No object references or analyst relationships to draw. Its ' + listed
                    + ' Correlations are fetched from the elements on the canvas.',
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

    /* ── sidebar properties ────────────────────────────────── */
    // The fields an analyst reads an element by, under MISP's names, rather
    // than every data key under its own. Blank fields are left out.
    var KIND_LABELS = {
        'object-reference':     'Object reference',
        'analyst-relationship': 'Analyst relationship',
        'correlation':          'Correlation',
        'feed-correlation':     'Seen in a feed',
        'feed-event':           'Event in a feed',
        'server-correlation':   'Seen on a server',
        'tag':                  'Tagged',
        'cluster-relation':     'Galaxy relation'
    };

    function field(name, value) {
        return (value == null || value === '') ? null : { name: name, value: String(value) };
    }

    function localMark(t) { return t.local ? ' (local)' : ''; }

    function tagsField(d) {
        return field('Tags', (d.tags || []).map(function (t) { return t.name + localMark(t); }).join(', '));
    }

    function clustersField(d) {
        return field('Galaxy clusters', (d.clusters || []).map(function (c) {
            return clusterName(c) + localMark(c);
        }).join(', '));
    }

    function belongsTo(d) {
        if (d.scope === 'self') return 'This event';
        return d.event_id ? 'Event ' + d.event_id : null;
    }

    function nodeProperties(node) {
        var d = node.getData() || {};
        var rows = [];
        if (d.type === 'attribute') {
            rows = [
                field('Value', d.value), field('Type', d['attr-type']), field('Category', d.category),
                field('Object relation', d.object_relation),
                field('IDS flag', d.to_ids == null ? null : (d.to_ids ? 'Yes' : 'No')),
                field('Comment', d.comment), field('Event', belongsTo(d)),
                field('Seen in a feed', d.feed_hit ? 'Yes — too many hits in this event to name which' : null),
                field('Warninglists', (d.warnings || []).map(function (w) {
                    return w.warninglist_name + (w.warninglist_category === 'false_positive' ? ' (false positive)' : '');
                }).join(', ')),
                tagsField(d), clustersField(d),
                field('UUID', d.uuid)
            ];
        } else if (d.type === 'object') {
            rows = [field('Template', d.name), field('Meta-category', d['meta-category']),
                    field('Event', belongsTo(d)), field('UUID', d.uuid)];
        } else if (d.type === 'event') {
            rows = [field('Info', d.info), field('Date', d.date), field('Organisation', d.org),
                    field('Feed', d.feed_name), tagsField(d), clustersField(d),
                    field('Event ID', d.event_id), field('UUID', d.uuid)];
        } else if (d.type === 'tag') {
            rows = [field('Tag', d.name), field('Local', d.local ? 'Yes' : null)];
        } else if (d.type === 'cluster') {
            rows = [field('Cluster', d.value), field('Galaxy', d.galaxy_name || d.galaxy_type),
                    field('Tag', d.tag_name), field('UUID', d.uuid)];
        } else if (d.type === 'feed' || d.type === 'server') {
            rows = [field('Provider', d.provider), field('URL', d.url), field('Format', d.source_format),
                    field('Events', d.feed_events),
                    field(d.type === 'feed' ? 'Feed ID' : 'Server ID', d.source_id)];
        }
        return rows.filter(Boolean);
    }

    function edgeProperties(edge) {
        var d = edge.getData() || {};
        return [field('Link', KIND_LABELS[d.kind] || d.kind), field('Relationship', d.relationship_type),
                field('Authors', d.authors), field('UUID', d.uuid)].filter(Boolean);
    }

    /* ── node context menu ─────────────────────────────────── */
    // Appended after the library's own entries, *Pivot ▸* among them. MISP's
    // pages open in a new tab, so the canvas the analyst built survives.
    function menuData(element) {
        var n = Array.isArray(element) ? (element.length === 1 ? element[0] : null) : element;
        return (n && typeof n.getData === 'function') ? (n.getData() || {}) : null;
    }

    // Another event's page, for anything on the canvas that belongs to one.
    function foreignEventId(d) {
        if (!d || !d.event_id || String(d.event_id) === String(eventId)) return null;
        return (d.type === 'event' || d.type === 'attribute' || d.type === 'object') ? d.event_id : null;
    }

    function openInTab(path) {
        window.open(baseurl + path, '_blank', 'noopener');
    }

    function copyValue(value) {
        var notifier = _graph && _graph.notifier;
        var clip = window.navigator && window.navigator.clipboard;
        if (!clip) {
            if (notifier) notifier.error('Copy failed', 'This browser gives the page no clipboard.');
            return;
        }
        clip.writeText(value).then(function () {
            if (notifier) notifier.success('Copied', truncate(value, 80));
        }, function () {
            if (notifier) notifier.error('Copy failed', 'The browser refused the clipboard.');
        });
    }

    function nodeMenu() {
        return [
            {
                text:      'Open its event',
                iconClass: 'fas fa-external-link-alt',
                visible:   function (el) { return !!foreignEventId(menuData(el)); },
                onclick:   function (e, el) { openInTab('/events/view2/' + foreignEventId(menuData(el))); }
            },
            {
                text:      'Browse feed',
                iconClass: 'fas fa-rss',
                visible:   function (el) { var d = menuData(el); return !!d && d.type === 'feed' && !!d.source_id; },
                onclick:   function (e, el) { openInTab('/feeds/previewIndex/' + menuData(el).source_id); }
            },
            {
                text:      'Preview in feed',
                iconClass: 'fas fa-rss',
                visible:   function (el) { var d = menuData(el); return !!d && !!d.feed_id && !!d.uuid; },
                onclick:   function (e, el) {
                    var d = menuData(el);
                    openInTab('/feeds/previewEvent/' + d.feed_id + '/' + d.uuid);
                }
            },
            {
                text:      'Copy value',
                iconClass: 'fas fa-copy',
                visible:   function (el) { var d = menuData(el); return !!d && d.type === 'attribute' && d.value !== ''; },
                onclick:   function (e, el) { copyValue(menuData(el).value); }
            }
        ];
    }

    /* ── canvas menu: taking fetched correlations back off ─── */
    // Everything the correlation pivot brought, over however many runs; what
    // the seed or the element pivot also vouches for stays.

    function fromCorrelationPivot(element) {
        return element.hasSource(CORRELATION_PIVOT);
    }

    function holdsFetchedCorrelations() {
        return !!_graph && (_graph.getMutableEdges().some(fromCorrelationPivot)
            || _graph.getMutableNodes().some(fromCorrelationPivot));
    }

    function removeFetchedCorrelations() {
        var removed = _graph.removeBySource(CORRELATION_PIVOT);
        _graph.notifier.success('Correlations removed',
            plural(removed.nodes.length, 'element', 'elements') + ' and '
            + plural(removed.edges.length, 'link', 'links')
            + ' off the canvas. Undo puts them back.');
    }

    function canvasMenu() {
        return [{
            text:      'Remove fetched correlations',
            iconClass: 'fas fa-eraser',
            visible:   holdsFetchedCorrelations,
            onclick:   removeFetchedCorrelations
        }];
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
            { key: 'warninglisted', label: 'On a warninglist', type: 'boolean' },
            { key: 'value',     label: 'Value',          type: 'regex' }
        ];
    }

    /* ── pivotick options ──────────────────────────────────── */
    function graphOptions() {
        return {
            isDirected: true,
            render: {
                type: 'svg',
                minLabelFontSize: 8,
                // Renderer-wide: objects and correlation containers stay closed,
                // with no expand chevron or Enter shortcut.
                enableNodeExpansion: false,
                nodeTypeAccessor: elementOf,
                // A feed is drawn by misp-pivot-nodes, like the elements.
                nodeStyleMap: Object.assign(mispNodeStyles(), {
                    // misp-iconify has no server mark; pivotick resolves any icon font's class.
                    server:    { shape: 'triangle', color: '#9b59b6', size: 24, iconClass: 'fas fa-server' },
                    // Image attachments (screenshots) draw an embedded thumbnail.
                    image:     {
                        imageFit:    'frame',
                        size:        80,
                        strokeColor: 'rgba(255,255,255,0.55)',
                        strokeWidth: 2,
                        imagePath:   function (node) {
                            var d = node.getData();
                            return d ? d.imageUrl : undefined;
                        }
                    }
                }),
                defaultNodeStyle: {
                    // Labels are not drawn by default — render data.label above each node.
                    text: function (node) {
                        var d = node.getData();
                        return d ? d.label : '';
                    },
                    textVerticalShift: -1,
                    badges: nodeBadges
                },
                // D1's first edge dimension.
                edgeTypeAccessor: function (edge) {
                    var d = edge.getData ? edge.getData() : null;
                    return d ? d.kind : undefined;
                },
                // A moving dash is reserved for what must draw the eye; a kind
                // opts in with animateDash: true.
                defaultEdgeStyle: { animateDash: false },
                edgeStyleMap: {
                    'object-reference':     { strokeColor: '#428bca' },
                    'analyst-relationship': { strokeColor: '#f39a1f', dashed: true },
                    'correlation':          { strokeColor: '#888', dashed: true },
                    'feed-correlation':     { strokeColor: FEED_COLOR, dashed: true },
                    'feed-event':           { strokeColor: FEED_COLOR },
                    'server-correlation':   { strokeColor: '#9b59b6', dashed: true },
                    'tag':                  { strokeColor: '#8a8f98', dashed: true },
                    'cluster-relation':     { strokeColor: window.MispPivotNodes.palette().galaxy.core }
                },
                // Draw the relationship_type on every edge (referenced + newly created).
                defaultLabelStyle: {
                    labelAccessor: function (edge) {
                        var d = edge.getData ? edge.getData() : null;
                        return d ? (d.label || '') : '';
                    }
                }
            },
            // The link distance seeds the opening frame; auto re-tunes from
            // there, since a seed can be 20 nodes or 1,500 (D7).
            simulation: {
                physics:        'auto',
                d3LinkDistance: 200
            },
            // No `save`: correlations are derived, never counted unsaved.
            pivots: [elementPivot(), correlationPivot(), feedEventsPivot(), tagPivot(),
                     taggedEventsPivot(), relatedClustersPivot()].map(joiningDrawnTags),
            callbacks: {
                // Another event is a leaf here (PRD §4) — it cannot expand in
                // place, so double-click hands the analyst over to its own
                // event page rather than leaving the node a dead end.
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
                sidebar: { collapsed: true },
                tooltip: { enabled: false },
                propertiesPanel: {
                    nodePropertiesMap: nodeProperties,
                    edgePropertiesMap: edgeProperties
                },
                contextMenu: {
                    menuNode:   { menu: nodeMenu() },
                    menuCanvas: { menu: canvasMenu() }
                },
                // Only drawing or deleting a relationship reaches MISP, so
                // creating or editing a node or an edge's data is offered to
                // nobody. A user who can write neither a reference nor an
                // analyst relationship gets no write tool at all; notes,
                // hides and layout stay, being canvas-only.
                editors: {
                    nodeEditor:  { enabled: false },
                    nodeCreator: { enabled: false },
                    edgeEditor:  { enabled: false },
                    edgeCreator: { enabled: canEdit || canAnalyst },
                    deletion:    { enabled: canEdit || canAnalyst }
                },
                // Element keys on nodeTypeAccessor. Relationship names the
                // edge facet's key, so legend and panel drive one filter.
                legend: {
                    sections: [
                        { title: 'Element', entries: elementLegendEntries },
                        { title: 'Relationship', scope: 'edge', key: 'kind' }
                    ]
                },
                // The layer switch, then what an edge asserts. The latter is
                // a pattern box, not a list: references alone use ~143 types
                // (D1). A regex facet is case-blind, so `by` finds
                // `Characterized_By` too. Derived edges assert nothing and
                // carry no relationship_type, so a typed filter hides them.
                filter: {
                    facets: nodeFacets(),
                    edgeFacets: [
                        { key: 'kind', label: 'Relationship', type: 'multiselect' },
                        { key: 'relationship_type', label: 'Asserts', type: 'regex' }
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

        if (typeof window.Pivotick !== 'function' || !window.MispPivotNodes) {
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

                var editor = (canEdit || canAnalyst) ? createEditor() : null;
                var opts   = graphOptions();
                if (editor) Object.assign(opts.callbacks, editor.callbacks);
                opts.UI.emptyState = emptyStateOption((event && event.Event) || {});
                // Only an event with something to show gets the panel (§8.10).
                if (eventHasAnalystData((event && event.Event) || {})) {
                    opts.UI.extraPanels = [analystPanel()];
                }

                _graph = new window.Pivotick(
                    containerEl,
                    data,
                    opts
                );

                if (editor) {
                    try { editor.attach(_graph); }
                    catch (e) { console.error('[pivot-explorer] editor attach failed:', e); }
                }
                watchElementPivot(_graph);
                declareAllFeedPotential(_graph);
                declareAllTagPotential(_graph);
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
    // Canvas node type → AnalystData::valid_targets name.
    var ANALYST_TYPES = { attribute: 'Attribute', object: 'Object', event: 'Event' };

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
        //
        // An analyst relationship may join any two elements MISP can name,
        // this event's or another's (D8).
        function possibleKinds(source, target) {
            var s = nodeData(source) || {}, t = nodeData(target) || {};
            var kinds = [];
            if (canEdit && s.type === 'object' && isOwnElement(s)
                && (t.type === 'object' || t.type === 'attribute') && isOwnElement(t)) {
                kinds.push('object-reference');
            }
            if (canAnalyst && ANALYST_TYPES[s.type] && ANALYST_TYPES[t.type]
                && s.uuid && t.uuid && s.uuid !== t.uuid) {
                kinds.push('analyst-relationship');
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

        // Both kinds take the same answer: an analyst relationship's type is
        // free text, so a name from the reference vocabulary is as good there.
        function relationshipFields(names, kinds) {
            var fields = [];
            if (kinds.length > 1) {
                fields.push({
                    key:          'kind',
                    label:        'Link type',
                    type:         'select',
                    options:      kinds.map(function (k) { return { label: KIND_LABELS[k], value: k }; }),
                    defaultValue: kinds[0]
                });
            }
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

        // What analystData/add asks of a relationship beyond its type, as its
        // own form does. Pivotick's form has no field that depends on another,
        // so the sharing group is always listed and read only for level 4.
        function sharingFields() {
            var fields = [{
                key:          'distribution',
                label:        'Distribution',
                type:         'select',
                options:      analystSharing.levels.map(function (l) { return { label: l[1], value: String(l[0]) }; }),
                defaultValue: String(analystSharing.default)
            }];
            if (analystSharing.sharingGroups.length) {
                fields.push({
                    key:          'sharing_group_id',
                    label:        'Sharing group (for that distribution)',
                    type:         'select',
                    options:      [{ label: '—', value: '' }].concat(analystSharing.sharingGroups.map(function (g) {
                        return { label: g[1], value: String(g[0]) };
                    })),
                    defaultValue: ''
                });
            }
            fields.push({
                key:         'authors',
                label:       'Authors',
                type:        'text',
                placeholder: analystSharing.authors || 'your email, if left blank'
            });
            return fields;
        }

        // The sharing an analyst relationship is saved with, or null when the
        // answer cannot be saved: a sharing group level needs its group.
        function sharingFrom(values) {
            var level = values.distribution != null ? String(values.distribution) : String(analystSharing.default);
            var out = { distribution: level };
            if (level === '4') {
                if (!values.sharing_group_id) {
                    notify('warning', 'No sharing group', 'Pick the sharing group to share it with.');
                    return null;
                }
                out.sharing_group_id = String(values.sharing_group_id);
            }
            var authors = String(values.authors || '').trim();
            if (authors) out.authors = authors;
            return out;
        }

        // The edge only lands once the relationship is saved, so the history
        // records it as persisted and a refused save leaves nothing behind.
        // An analyst relationship also asks how it is shared: in the one form
        // when it is the only kind, else in a second once it is chosen.
        function onBeforeEdgeCreate(ctx) {
            if (ctx.kind !== 'edge') return true;
            var kinds = possibleKinds(ctx.source, ctx.target);
            if (!kinds.length) return false;
            var fromData = nodeData(ctx.source);
            var toData   = nodeData(ctx.target);
            var analystOnly = kinds.length === 1 && kinds[0] === 'analyst-relationship';
            var rel, kind;

            return loadVocabulary().then(function (names) {
                return ctx.promptData({
                    title:       'Add relationship',
                    submitLabel: analystOnly || kinds.length === 1 ? 'Save' : 'Next',
                    fields:      relationshipFields(names, kinds).concat(analystOnly ? sharingFields() : [])
                });
            }).then(function (values) {
                if (!values) return false;   // cancelled
                rel = String(values.custom || '').trim()
                      || String(values.relationship_type || '').trim();
                if (!rel) {
                    notify('warning', 'No relationship type', 'Pick one or type your own.');
                    return false;
                }
                kind = kinds.indexOf(values.kind) !== -1 ? values.kind : kinds[0];
                if (kind !== 'analyst-relationship' || analystOnly) return values;
                return ctx.promptData({
                    title:       'Share the relationship',
                    submitLabel: 'Save',
                    fields:      sharingFields()
                });
            }).then(function (values) {
                if (!values) return false;   // cancelled, or refused above
                var sharing = kind === 'analyst-relationship' ? sharingFrom(values) : null;
                if (kind === 'analyst-relationship' && !sharing) return false;
                var save = kind === 'object-reference'
                    ? saveReference(fromData, toData, rel)
                    : saveRelationship(fromData, toData, rel, sharing);
                return save.then(function (saved) {
                    if (!saved) return false;
                    var data = { kind: kind, label: rel, relationship_type: rel };
                    if (saved.uuid) data.uuid = saved.uuid;
                    if (saved.orgc_uuid) data.orgc = saved.orgc_uuid;
                    if (saved.authors) data.authors = saved.authors;
                    notify('success', 'Relationship added', rel);
                    return { accept: true, data: data, persisted: true };
                });
            });
        }

        // POST to MISP's REST API, resolving the response body or rejecting
        // with MISP's own message.
        function post(path, body) {
            return fetch(baseurl + path, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type':     'application/json',
                    'Accept':           'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(body)
            })
            .then(function (res) {
                return res.json().catch(function () { return {}; }).then(function (json) {
                    if (res.ok) return json || {};
                    throw new Error((json && (json.errors || json.message)) || ('HTTP ' + res.status));
                });
            });
        }

        function saveFailed(err) {
            console.error('[pivot-explorer] save failed:', err);
            notify('error', 'Save failed', String(err && err.message || err));
            return null;
        }

        function saveReference(from, to, rel) {
            return post('/objectReferences/add/' + encodeURIComponent(from.uuid) + '.json', {
                ObjectReference: { referenced_uuid: to.uuid, relationship_type: rel, comment: '' }
            }).then(function (body) { return body.ObjectReference || {}; }, saveFailed);
        }

        function saveRelationship(from, to, rel, sharing) {
            return post('/analystData/add/Relationship/' + encodeURIComponent(from.uuid)
                        + '/' + ANALYST_TYPES[from.type] + '.json', {
                Relationship: Object.assign({
                    related_object_uuid: to.uuid,
                    related_object_type: ANALYST_TYPES[to.type],
                    relationship_type:   rel
                }, sharing)
            }).then(function (body) { return body.Relationship || {}; }, saveFailed);
        }

        /* ── deletion → the relationship behind the edge, never an element ── */
        // Only a relationship whose uuid we know can be found again in MISP,
        // and an analyst one only by its creator org (or a site admin), which
        // is the rule MISP applies. Correlations are derived.
        function isDeletable(edge) {
            var d = edge.getData ? edge.getData() : null;
            if (!d || !d.uuid) return false;
            if (d.kind === 'object-reference') return canEdit;
            if (d.kind === 'analyst-relationship') {
                return canAnalyst && (siteAdmin || (!!orgUuid && d.orgc === orgUuid));
            }
            return false;
        }

        function describeEdge(edge) {
            var d = edge.getData() || {};
            var end = function (n) { return truncate((nodeData(n) || {}).label || n.id, 42); };
            return end(edge.from) + ' → ' + (d.label || 'related-to') + ' → ' + end(edge.to);
        }

        function confirmBody(edges) {
            var shown = edges.slice(0, 3).map(describeEdge);
            if (edges.length > 3) shown.push('and ' + (edges.length - 3) + ' more');
            return 'This deletes ' + (edges.length === 1 ? 'the relationship ' : edges.length + ' relationships ')
                 + 'in MISP: ' + shown.join('; ') + '. It cannot be undone from the graph.';
        }

        // Delete sits beside Hide, so a node is refused outright and an edge
        // is deleted in MISP only after a confirm saying so. The history row
        // is sealed as persisted, and narrowed to what MISP actually deleted.
        function onBeforeDelete(ctx) {
            if (ctx.nodes.length) {
                notify('warning', 'Elements are not deleted here',
                       'Hide takes one off the canvas; the event view deletes it from MISP.');
                return false;
            }
            var doomed = ctx.edges.filter(isDeletable);
            if (doomed.length < ctx.edges.length) {
                notify('info', 'Some relationships stay',
                       'Correlations, tags, and relationships you cannot delete in MISP, stay; hide them instead.');
            }
            if (!doomed.length) return ctx.edges.length ? { accept: true, edges: [] } : true;

            return ctx.confirm({
                title:        doomed.length === 1 ? 'Delete relationship' : 'Delete relationships',
                body:         confirmBody(doomed),
                confirmLabel: 'Delete in MISP',
                variant:      'danger'
            }).then(function (confirmed) {
                if (!confirmed) return false;
                return Promise.all(doomed.map(deleteRelationship)).then(function (results) {
                    var done = doomed.filter(function (e, i) { return results[i]; });
                    if (!done.length) return false;
                    notify('success', done.length === 1 ? 'Relationship deleted' : done.length + ' relationships deleted');
                    return { accept: true, edges: done, persisted: true };
                });
            });
        }

        // Each the way MISP's own views delete it: a reference soft, so the
        // deletion syncs; an analyst relationship hard, which blocklists it.
        function deleteRelationship(edge) {
            var d = edge.getData();
            var path = d.kind === 'object-reference'
                ? '/objectReferences/delete/' + encodeURIComponent(d.uuid) + '.json'
                : '/analystData/delete/Relationship/' + encodeURIComponent(d.uuid) + '.json';
            return post(path, {}).then(function () { return true; }, function (err) {
                console.error('[pivot-explorer] delete failed:', err);
                notify('error', 'Delete failed', describeEdge(edge) + ': ' + String(err && err.message || err));
                return false;
            });
        }

        /* ── public: callbacks (options-time), attach (post-construction) ── */
        return {
            callbacks: {
                isValidConnection:  isValidConnection,
                onBeforeEdgeCreate: onBeforeEdgeCreate,
                onBeforeDelete:     onBeforeDelete
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
        canAnalyst = d.peCanAnalyst === '1';
        try {
            analystSharing = Object.assign(analystSharing, JSON.parse(d.peAnalystSharing || '{}'));
        } catch (e) {
            console.error('[pivot-explorer] unreadable analyst sharing options:', e);
        }
        try {
            uiPriorities = JSON.parse(d.peUiPriorities || '{}');
        } catch (e) {
            console.error('[pivot-explorer] unreadable object template priorities:', e);
        }
        orgUuid    = d.peOrgUuid || '';
        siteAdmin  = d.peSiteAdmin === '1';
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
