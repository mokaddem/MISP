// The event page's Timeline tab: events/viewEventTimeline drawn with
// MispTimeline, built the first time the tab is shown. Filters and the
// brushed window are asked of the server, which caps what it returns.
// Rows are placed by their seen dates or by their timestamp, the basis.
(function () {
    'use strict';

    var DAY = 86400000;
    var MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug',
        'Sep', 'Oct', 'Nov', 'Dec'];
    var SEARCH_DELAY = 250;
    var BASIS_KEY = 'misp.eventTimeline.basis';

    var BASES = {
        seen: {
            button: 'Seen',
            icon: 'fa-eye',
            title: 'Place rows by their first and last seen dates',
            item: ['dated item', 'dated items'],
            datesHead: 'First seen → last seen, UTC',
            perBar: 'rows seen per ',
            order: 'by start',
            emptyTitle: ' has a first or last seen date',
            emptyText: 'An attribute or object appears here once it records'
                + ' when it was first or last seen.',
            undated: ' no seen dates'
        },
        timestamp: {
            button: 'Modified',
            icon: 'fa-pen-to-square',
            title: 'Place rows by when they were last modified',
            item: ['item', 'items'],
            datesHead: 'Last modified, UTC',
            perBar: 'rows last modified per ',
            order: 'by modification time',
            emptyTitle: ' has a modification time',
            emptyText: 'Attributes and objects appear here at the time they'
                + ' were last modified.',
            undated: ' no modification time'
        }
    };

    function storedBasis() {
        try {
            return window.localStorage.getItem(BASIS_KEY) === 'timestamp'
                ? 'timestamp' : 'seen';
        } catch (e) {
            return 'seen';
        }
    }

    function storeBasis(basis) {
        try {
            window.localStorage.setItem(BASIS_KEY, basis);
        } catch (e) {
            // Remembering the choice is a convenience only
        }
    }

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return {
                '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;',
                "'": '&#39;'
            }[c];
        });
    }

    function pad(n) {
        return n < 10 ? '0' + n : '' + n;
    }

    function num(n) {
        return Number(n || 0).toLocaleString('en-GB');
    }

    function plural(n, one, many) {
        return num(n) + ' ' + (n === 1 ? one : many);
    }

    function ms(us) {
        return us == null ? null : Math.round(us / 1000);
    }

    function isoDay(t) {
        var d = new Date(t);
        return d.getUTCFullYear() + '-' + pad(d.getUTCMonth() + 1) + '-'
            + pad(d.getUTCDate());
    }

    function hms(t) {
        var d = new Date(t);
        return pad(d.getUTCHours()) + ':' + pad(d.getUTCMinutes()) + ':'
            + pad(d.getUTCSeconds());
    }

    function hm(t) {
        var d = new Date(t);
        return pad(d.getUTCHours()) + ':' + pad(d.getUTCMinutes());
    }

    function longDay(t) {
        var d = new Date(t);
        return d.getUTCDate() + ' ' + MONTHS[d.getUTCMonth()] + ' '
            + d.getUTCFullYear();
    }

    function full(t) {
        return t % DAY === 0 ? isoDay(t) : isoDay(t) + ' ' + hms(t);
    }

    function parseDay(s) {
        var p = s.split('-');
        return Date.UTC(+p[0], +p[1] - 1, +p[2]);
    }

    function rangeText(from, to) {
        var a = new Date(from);
        var b = new Date(to - 1);
        if (to - from <= DAY) {
            return 'on ' + longDay(from);
        }
        if (a.getUTCFullYear() === b.getUTCFullYear()) {
            return a.getUTCDate() + ' ' + MONTHS[a.getUTCMonth()] + ' – '
                + longDay(to - 1);
        }
        return longDay(from) + ' – ' + longDay(to - 1);
    }

    // The colour lands in a style attribute: let nothing through that could
    // close it.
    function safeColour(c) {
        return c && /^[\w#(),.%\s-]+$/.test(c) ? c : null;
    }

    function attrShape(a, basis) {
        if (basis === 'timestamp') {
            return 'point';
        }
        if (a.first_seen != null && a.last_seen != null) {
            return 'range';
        }
        return a.first_seen != null ? 'first' : 'last';
    }

    function objShape(o, children, basis) {
        if (basis === 'timestamp') {
            return o.end > o.start ? 'range' : 'point';
        }
        if (o.end > o.start) {
            return 'range';
        }
        var first = o.first_seen != null;
        var last = o.last_seen != null;
        (children || []).forEach(function (c) {
            first = first || c.first_seen != null;
            last = last || c.last_seen != null;
        });
        if (first && !last) {
            return 'first';
        }
        if (last && !first) {
            return 'last';
        }
        return 'range';
    }

    function ownDates(o, basis) {
        if (basis === 'timestamp') {
            var ts = ms(o.timestamp);
            return ts == null ? null : { start: ts, end: ts, shape: 'point' };
        }
        var fs = ms(o.first_seen);
        var ls = ms(o.last_seen);
        if (fs != null && ls != null) {
            return { start: fs, end: ls, shape: 'range' };
        }
        if (fs != null) {
            return { start: fs, end: fs, shape: 'first' };
        }
        if (ls != null) {
            return { start: ls, end: ls, shape: 'last' };
        }
        return null;
    }

    function openTab(tab, filter) {
        if (window.MispEventOverview) {
            window.MispEventOverview.openFiltered(tab, filter);
        }
    }

    function Tab(root) {
        var self = this;
        this.root = root;
        this.url = root.getAttribute('data-url');
        this.state = {
            basis: storedBasis(), q: '', kind: '', facet: '', category: '',
            event: '', win: null
        };
        this.request = null;
        this.reset();
        root.addEventListener('click', function (e) {
            var basis = e.target.closest('[data-etl-basis]');
            if (basis) {
                self.setBasis(basis.getAttribute('data-etl-basis'));
            }
        });
        window.addEventListener('resize', function () {
            self.syncOverview();
        });
        this.load(true);
    }

    // Forgets what was drawn from the last unfiltered answer.
    Tab.prototype.reset = function () {
        this.ledger = null;
        this.overview = null;
        this.overviewShown = false;
        this.domain = null;
        this.response = null;
    };

    Tab.prototype.words = function () {
        return BASES[this.state.basis];
    };

    Tab.prototype.setBasis = function (basis) {
        if (!BASES[basis] || basis === this.state.basis) {
            return;
        }
        this.state.basis = basis;
        this.state.win = null;
        storeBasis(basis);
        this.markBasis();
        this.reset();
        this.load(true);
    };

    Tab.prototype.markBasis = function () {
        var basis = this.state.basis;
        this.root.querySelectorAll('.etl-basis [data-etl-basis]')
            .forEach(function (b) {
                var on = b.getAttribute('data-etl-basis') === basis;
                b.classList.toggle('active', on);
                b.setAttribute('aria-pressed', on ? 'true' : 'false');
            });
    };

    Tab.prototype.query = function () {
        var s = this.state;
        var params = new URLSearchParams();
        if (s.basis !== 'seen') {
            params.set('basis', s.basis);
        }
        if (s.win) {
            params.set('from', isoDay(s.win.from));
            params.set('to', isoDay(s.win.to - DAY));
        }
        if (s.q) {
            params.set('q', s.q);
        }
        if (s.kind) {
            params.set('kind', s.kind);
        }
        if (s.facet) {
            var f = s.facet.split(/:(.*)/);
            params.append(f[0] === 't' ? 'types[]' : 'objects[]', f[1]);
        }
        if (s.category) {
            params.append('categories[]', s.category);
        }
        if (s.event) {
            params.append('events[]', s.event);
        }
        var q = params.toString();
        return q ? '?' + q : '';
    };

    Tab.prototype.fetch = function (suffix, signal) {
        return fetch(this.url + suffix, {
            credentials: 'same-origin',
            signal: signal,
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        }).then(function (r) {
            if (!r.ok) {
                throw new Error('HTTP ' + r.status);
            }
            return r.json();
        });
    };

    Tab.prototype.load = function (first) {
        var self = this;
        if (this.request) {
            this.request.abort();
        }
        var request = new AbortController();
        this.request = request;
        this.root.classList.add('etl-busy');
        this.fetch(this.query(), request.signal).then(function (data) {
            if (self.request !== request) {
                return;
            }
            self.request = null;
            self.root.classList.remove('etl-busy');
            if (first) {
                self.build(data);
            }
            self.show(data);
        }, function (error) {
            if (error.name === 'AbortError') {
                return;
            }
            self.request = null;
            self.root.classList.remove('etl-busy');
            self.fail(first);
        });
    };

    Tab.prototype.fail = function (first) {
        var self = this;
        var html = '<div class="etl-notice etl-notice-error" role="alert">'
            + '<i class="fas fa-triangle-exclamation"></i><div>'
            + 'The timeline could not be loaded. '
            + '<button type="button" class="btn btn-link" data-etl-retry>'
            + 'Try again</button></div></div>';
        if (first) {
            this.root.innerHTML = '<div class="etl-body">' + html + '</div>';
        } else {
            this.noticeEl.innerHTML = html;
        }
        this.root.querySelector('[data-etl-retry]')
            .addEventListener('click', function () {
                if (first) {
                    self.root.innerHTML = '';
                }
                self.load(first);
            });
    };

    /* ── the parts drawn once, from the unfiltered first answer ─────── */

    Tab.prototype.build = function (data) {
        var self = this;
        this.events = data.events || {};
        this.eventIds = Object.keys(this.events);
        this.extended = this.eventIds.length > 1;
        this.counts = data.counts || {};
        this.histogram = data.histogram;
        this.span = data.span ? {
            from: parseDay(data.span.from),
            to: parseDay(data.span.to) + DAY
        } : null;
        this.initialWindow = data.window ? {
            from: parseDay(data.window.from),
            to: parseDay(data.window.to) + DAY
        } : null;
        this.initiallyCapped = !!data.capped;
        var span = this.span;
        this.barBounds = (data.histogram ? data.histogram.bars : [])
            .map(function (b) {
                return {
                    from: Math.max(parseDay(b.from), span.from),
                    to: Math.min(parseDay(b.to) + DAY, span.to)
                };
            });

        var dated = this.counts.dated || 0;
        var words = this.words();
        this.root.innerHTML = '<div class="p-3 border-bottom">'
            + '<div class="d-flex flex-wrap align-items-center gap-2">'
            + '<div class="etl-tile rounded-2 d-flex align-items-center'
            + ' justify-content-center"><i class="fas fa-clock"></i></div>'
            + '<div class="me-2"><div class="fw-bold lh-1">Timeline</div>'
            + '<div class="small text-muted mt-1">' + this.subLine(dated)
            + '</div></div>'
            + this.basisHtml()
            + (dated ? this.toolbar(data.facets || {}) : '')
            + '</div></div><div class="etl-body"></div>';
        var body = this.root.querySelector('.etl-body');
        body.addEventListener('click', function (e) {
            var undated = e.target.closest('[data-etl-undated]');
            if (undated) {
                openTab(undated.getAttribute('data-etl-undated'), { seen: 2 });
            } else if (e.target.closest('[data-etl-reset-window]')) {
                self.resetWindow();
            } else if (e.target.closest('[data-etl-clear]')) {
                self.clearFilters();
            }
        });

        if (!dated) {
            var other = this.state.basis === 'seen' && this.counts.undated
                ? '<button type="button" class="btn btn-sm etl-empty-switch"'
                    + ' data-etl-basis="timestamp"><i class="fas '
                    + BASES.timestamp.icon + '"></i>Place them by when they'
                    + ' were last modified</button>'
                : '';
            body.innerHTML = '<div class="etl-empty" data-timeline-empty>'
                + '<div class="etl-empty-ico">'
                + '<i class="fas fa-calendar-xmark"></i></div>'
                + '<div class="etl-empty-box"><h3>Nothing in '
                + (this.extended
                    ? 'these ' + this.eventIds.length + ' events'
                    : 'this event')
                + words.emptyTitle + '</h3>'
                + '<p>' + words.emptyText + '</p>'
                + this.undatedHtml() + other + '</div></div>';
            return;
        }

        this.noticeEl = document.createElement('div');
        this.overviewHost = document.createElement('div');
        this.windowEl = document.createElement('div');
        this.windowEl.className = 'etl-window';
        var ledgerHost = document.createElement('div');
        ledgerHost.className = 'etl-ledger';
        body.appendChild(this.noticeEl);
        body.appendChild(this.overviewHost);
        body.appendChild(this.windowEl);
        body.appendChild(ledgerHost);
        body.insertAdjacentHTML('beforeend', this.undatedHtml());

        this.ledger = new window.MispTimeline.Ledger(ledgerHost, {
            rowHeight: 32,
            labelHead: 'Item',
            datesHead: words.datesHead,
            label: this.labelHtml.bind(this),
            dates: this.datesHtml.bind(this),
            tooltip: this.tooltipHtml.bind(this),
            onClick: this.open.bind(this),
            loadChildren: this.loadChildren.bind(this),
            loadingText: function (p) {
                return 'Loading ' + plural(p.childCount, 'attribute',
                    'attributes') + '…';
            },
            failedText: 'Could not load this object\'s attributes',
            emptyHtml: function () {
                return '<div class="tl-nomatch">Nothing in this window'
                    + ' matches. <button type="button" class="btn btn-link'
                    + ' btn-sm p-0 align-baseline" data-etl-clear>'
                    + 'Clear the filters</button></div>';
            }
        });
        ledgerHost.addEventListener('click', function (e) {
            if (e.target.closest('[data-etl-clear]')) {
                self.clearFilters();
            }
        });
        this.bindToolbar();
        if (this.restoreFilters()) {
            this.load(false);
        }
    };

    // After a rebuild, puts the kept filters back on the new toolbar, and
    // drops those it no longer offers. True when one was dropped.
    Tab.prototype.restoreFilters = function () {
        var s = this.state;
        var root = this.root;
        var dropped = false;
        root.querySelector('[data-timeline-search]').value = s.q;
        var kind = root.querySelector('[data-etl-kind="' + s.kind + '"]');
        if (!kind || kind.disabled) {
            dropped = true;
            s.kind = '';
        }
        this.markKind();
        root.querySelectorAll('select[data-etl-filter]').forEach(function (el) {
            var key = el.getAttribute('data-etl-filter');
            el.value = s[key];
            if (el.value !== s[key]) {
                dropped = true;
                s[key] = '';
                el.value = '';
            }
        });
        return dropped;
    };

    Tab.prototype.basisHtml = function () {
        var basis = this.state.basis;
        return '<div class="btn-group btn-group-sm etl-basis" role="group"'
            + ' aria-label="Place rows by">'
            + Object.keys(BASES).map(function (key) {
                var b = BASES[key];
                var on = key === basis;
                return '<button type="button" class="btn'
                    + (on ? ' active' : '') + '" data-etl-basis="' + key
                    + '" aria-pressed="' + on + '" title="' + esc(b.title)
                    + '"><i class="fas ' + b.icon + '"></i>' + esc(b.button)
                    + '</button>';
            }).join('') + '</div>';
    };

    Tab.prototype.subLine = function (dated) {
        var across = this.extended
            ? ', across ' + this.eventIds.length + ' events'
            : '';
        if (!dated) {
            return 'Nothing dated' + across;
        }
        var item = this.words().item;
        return plural(dated, item[0], item[1]) + ', '
            + rangeText(this.span.from, this.span.to) + ' UTC'
            + '<span class="etl-sub-events">' + across + '</span>';
    };

    Tab.prototype.toolbar = function (facets) {
        var counts = this.counts;
        var events = this.events;
        function options(map, prefix) {
            return Object.keys(map || {}).sort(function (a, b) {
                return map[b] - map[a];
            }).map(function (k) {
                return '<option value="' + esc(prefix + k) + '">' + esc(k)
                    + ' (' + num(map[k]) + ')</option>';
            }).join('');
        }
        var typeOptions = '';
        if (Object.keys(facets.types || {}).length) {
            typeOptions += '<optgroup label="Attribute types">'
                + options(facets.types, 't:') + '</optgroup>';
        }
        if (Object.keys(facets.objects || {}).length) {
            typeOptions += '<optgroup label="Object names">'
                + options(facets.objects, 'o:') + '</optgroup>';
        }
        var eventOptions = this.eventIds.map(function (id) {
            return '<option value="' + esc(id) + '">#' + esc(id) + ' '
                + esc(events[id].info) + ' ('
                + num((facets.events || {})[id]) + ')</option>';
        }).join('');
        function kind(value, label, n) {
            return '<button type="button" class="btn btn-outline-secondary"'
                + ' data-etl-kind="' + value + '"' + (n ? '' : ' disabled')
                + '>' + label + '<span class="etl-kind-n">' + num(n)
                + '</span></button>';
        }
        return '<div class="etl-toolbar">'
            + '<input type="search" class="form-control form-control-sm'
            + ' etl-search" placeholder="Search values"'
            + ' aria-label="Search the timeline" data-timeline-search>'
            + '<div class="btn-group btn-group-sm etl-kind" role="group"'
            + ' aria-label="Kind">'
            + '<button type="button" class="btn btn-outline-secondary active"'
            + ' data-etl-kind="">All</button>'
            + kind('attribute', 'Attributes', counts.attributes)
            + kind('object', 'Objects', counts.objects)
            + '</div>'
            + '<select class="form-select form-select-sm w-auto"'
            + ' data-etl-filter="facet" aria-label="Type or object name"'
            + (typeOptions ? '' : ' disabled') + '>'
            + '<option value="">Any type</option>' + typeOptions + '</select>'
            + '<select class="form-select form-select-sm w-auto"'
            + ' data-etl-filter="category" aria-label="Category">'
            + '<option value="">Any category</option>'
            + options(facets.categories, '') + '</select>'
            + (this.extended
                ? '<select class="form-select form-select-sm w-auto"'
                    + ' data-etl-filter="event" aria-label="Source event">'
                    + '<option value="">All ' + this.eventIds.length
                    + ' events</option>' + eventOptions + '</select>'
                : '')
            + '</div>';
    };

    Tab.prototype.bindToolbar = function () {
        var self = this;
        var root = this.root;
        var timer;
        root.querySelector('[data-timeline-search]')
            .addEventListener('input', function () {
                var value = this.value.trim();
                clearTimeout(timer);
                timer = setTimeout(function () {
                    if (value !== self.state.q) {
                        self.state.q = value;
                        self.load(false);
                    }
                }, SEARCH_DELAY);
            });
        root.querySelectorAll('[data-etl-kind]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                self.state.kind = btn.getAttribute('data-etl-kind');
                self.markKind();
                self.load(false);
            });
        });
        root.querySelectorAll('select[data-etl-filter]').forEach(function (s) {
            s.addEventListener('change', function () {
                self.state[s.getAttribute('data-etl-filter')] = s.value;
                self.load(false);
            });
        });
    };

    Tab.prototype.markKind = function () {
        var kind = this.state.kind;
        this.root.querySelectorAll('[data-etl-kind]').forEach(function (b) {
            b.classList.toggle('active',
                b.getAttribute('data-etl-kind') === kind);
        });
    };

    Tab.prototype.undatedHtml = function () {
        var c = this.counts;
        if (!c.undated) {
            return '';
        }
        var links = [];
        // The tabs filter on seen dates only.
        var linkable = this.state.basis === 'seen';
        if (linkable && c.undated_attributes) {
            links.push('<button type="button" class="btn btn-link"'
                + ' data-etl-undated="attributes">'
                + plural(c.undated_attributes, 'attribute', 'attributes')
                + '</button>');
        }
        if (linkable && c.undated_objects) {
            links.push('<button type="button" class="btn btn-link"'
                + ' data-etl-undated="objects">'
                + plural(c.undated_objects, 'object', 'objects')
                + '</button>');
        }
        return '<div class="etl-undated" data-timeline-undated>'
            + '<i class="fas fa-calendar-xmark"></i><span><b>'
            + num(c.undated) + '</b> '
            + (c.undated === 1 ? 'item has' : 'items have')
            + this.words().undated + ' and ' + (c.undated === 1 ? 'is' : 'are')
            + ' not on the timeline.</span>'
            + (links.length ? '<span>List them: ' + links.join(' · ')
                + '</span>' : '')
            + '</div>';
    };

    /* ── each answer ───────────────────────────────────────────────── */

    Tab.prototype.cue = function (item) {
        if (!this.extended || !this.events[item.event_id]) {
            return null;
        }
        return safeColour(this.events[item.event_id].color);
    };

    Tab.prototype.attrRow = function (a) {
        return {
            key: 'a' + a.id,
            start: ms(a.start),
            end: ms(a.end),
            shape: attrShape(a, this.state.basis),
            data: a,
            cue: this.cue(a)
        };
    };

    Tab.prototype.objRow = function (o) {
        var self = this;
        var row = {
            key: 'o' + o.id,
            start: ms(o.start),
            end: ms(o.end),
            envelope: true,
            data: o,
            childCount: o.children_count || 0,
            cue: this.cue(o),
            children: o.children
                ? o.children.map(function (c) { return self.attrRow(c); })
                : (o.children_count ? null : []),
            shape: objShape(o, o.children, this.state.basis)
        };
        if (row.childCount > 0) {
            row.own = ownDates(o, this.state.basis);
        }
        return row;
    };

    Tab.prototype.show = function (data) {
        if (!this.ledger) {
            return;
        }
        var self = this;
        this.response = data;
        var rows = (data.items || []).map(function (it) {
            return it.kind === 'object' ? self.objRow(it) : self.attrRow(it);
        });
        this.lastStart = rows.reduce(function (m, r) {
            return Math.max(m, r.start);
        }, 0);
        var win = data.window ? {
            from: parseDay(data.window.from),
            to: parseDay(data.window.to) + DAY
        } : this.initialWindow;
        this.shortWindow = win.to - win.from <= 3 * DAY;
        if (!this.domain || this.domain.from !== win.from
            || this.domain.to !== win.to) {
            this.domain = win;
            this.ledger.setDomain(win.from, win.to);
        }
        this.ledger.setRows(rows);
        this.syncOverview();
        this.windowLine(win, false);
        this.noticeEl.innerHTML = this.noticeHtml();
    };

    Tab.prototype.filtersOn = function () {
        var s = this.state;
        return !!(s.q || s.kind || s.facet || s.category || s.event);
    };

    Tab.prototype.windowLine = function (win, live) {
        var range = win.to - win.from <= DAY
            ? longDay(win.from) + ', 00:00 – 24:00'
            : longDay(win.from) + ' – ' + longDay(win.to - 1);
        var meta;
        if (live) {
            meta = 'release to set the window';
        } else {
            meta = plural(this.response.in_window, 'row', 'rows')
                + (this.filtersOn() ? ' match the filters' : '');
        }
        this.windowEl.innerHTML = '<span class="etl-window-range">' + range
            + '</span><span class="etl-window-meta">UTC · ' + meta + '</span>'
            + (!live && this.state.win
                ? '<button type="button" class="btn btn-link"'
                    + ' data-etl-reset-window>Show the whole span</button>'
                : '')
            + (!live && this.filtersOn()
                ? '<button type="button" class="btn btn-link" data-etl-clear>'
                    + 'Clear filters</button>'
                : '');
    };

    Tab.prototype.noticeHtml = function () {
        var data = this.response;
        if (!data.capped) {
            return '';
        }
        var how = this.overviewShown
            ? 'Drag across the activity strip to narrow the window, or narrow'
                + ' by type, category or search.'
            : 'Narrow by type, category or search to reach them.';
        return '<div class="etl-notice" data-timeline-capped>'
            + '<i class="fas fa-triangle-exclamation"></i><div><b>This window'
            + ' holds ' + num(data.in_window) + ' rows; the first '
            + num(data.items.length) + ' ' + this.words().order
            + ' are listed</b>, up to '
            + full(this.lastStart) + ' UTC. Rows starting later are not'
            + ' drawn. <span class="etl-muted">' + how + '</span></div></div>';
    };

    /* ── the overview strip ────────────────────────────────────────── */

    Tab.prototype.barsFor = function (win) {
        if (!win) {
            return null;
        }
        var from = -1;
        var to = -1;
        this.barBounds.forEach(function (b, i) {
            if (b.to > win.from && b.from < win.to) {
                if (from < 0) {
                    from = i;
                }
                to = i;
            }
        });
        if (from < 0 || (from === 0 && to === this.barBounds.length - 1)) {
            return null;
        }
        return { from: from, to: to };
    };

    Tab.prototype.barLabel = function (i) {
        var bars = this.histogram.bars;
        var unit = this.histogram.unit;
        var d = new Date(parseDay(bars[i].from));
        var m = d.getUTCMonth();
        var day = d.getUTCDate();
        var y = d.getUTCFullYear();
        if (i === 0) {
            return {
                text: (unit === 'month' ? '' : day + ' ') + MONTHS[m] + ' ' + y,
                major: true
            };
        }
        var monthStart = unit === 'month'
            || (unit === 'week' ? day <= 7 : day === 1);
        if (monthStart && m === 0) {
            return { text: '' + y, major: true };
        }
        if (monthStart && (unit !== 'month' || bars.length <= 36)) {
            return { text: MONTHS[m], major: false };
        }
        if (unit === 'day' && d.getUTCDay() === 1) {
            return { text: day + ' ' + MONTHS[m], major: false };
        }
        return null;
    };

    Tab.prototype.buildOverview = function () {
        var self = this;
        var h = this.histogram;
        this.overview = window.MispTimeline.overview({
            bars: h.bars,
            max: h.max,
            maxLabels: 12,
            tickLabel: this.barLabel.bind(this),
            selected: this.barsFor(this.state.win),
            head: '<strong>Activity across the event</strong><span>'
                + this.words().perBar + esc(h.unit) + ', tallest ' + num(h.max)
                + ', square-root scale. Drag to set the window below; click'
                + ' to reset it.</span>',
            onRange: function (a, b) {
                self.windowLine({
                    from: self.barBounds[a].from,
                    to: self.barBounds[b].to
                }, true);
            },
            onSettle: function (a, b) {
                self.state.win = {
                    from: self.barBounds[a].from,
                    to: self.barBounds[b].to
                };
                self.load(false);
            },
            onClear: function () {
                self.resetWindow();
            }
        });
        this.overviewHost.appendChild(this.overview.el);
    };

    // Shown once a day would be narrower than 3px on the plot, the event's
    // answer was capped, or the window is narrower than the span.
    Tab.prototype.wantOverview = function () {
        var h = this.histogram;
        if (!h || h.bars.length < 2) {
            return false;
        }
        var days = (this.span.to - this.span.from) / DAY;
        var iw = this.initialWindow;
        return days * 3 > this.ledger.plotWidth || this.initiallyCapped
            || !!this.state.win || iw.from !== this.span.from
            || iw.to !== this.span.to;
    };

    Tab.prototype.syncOverview = function () {
        if (!this.ledger) {
            return;
        }
        var want = this.wantOverview();
        if (want && !this.overview) {
            this.buildOverview();
        }
        if (this.overview) {
            this.overview.el.hidden = !want;
        }
        if (want !== this.overviewShown) {
            this.overviewShown = want;
            if (this.response) {
                this.noticeEl.innerHTML = this.noticeHtml();
            }
        }
    };

    Tab.prototype.resetWindow = function () {
        this.state.win = null;
        if (this.overview) {
            this.overview.set(null);
        }
        this.load(false);
    };

    Tab.prototype.clearFilters = function () {
        var s = this.state;
        s.q = '';
        s.kind = '';
        s.facet = '';
        s.category = '';
        s.event = '';
        this.root.querySelector('[data-timeline-search]').value = '';
        this.root.querySelectorAll('select[data-etl-filter]')
            .forEach(function (select) {
                select.value = '';
            });
        this.markKind();
        this.load(false);
    };

    /* ── rows ──────────────────────────────────────────────────────── */

    Tab.prototype.originBadge = function (eventId) {
        var e = this.events[eventId];
        if (!this.extended || !e || e.role === 'self') {
            return '';
        }
        var m = /hsl\((\d+)/.exec(e.color || '');
        var hue = m ? m[1] : 210;
        var extended = e.role === 'extended';
        var title = (extended
            ? 'Comes from the event this one extends'
            : 'Comes from an event that extends this one') + ' — ' + e.info;
        return '<span class="badge etl-origin d-inline-flex align-items-center'
            + ' gap-1" title="' + esc(title) + '" style="background:hsla('
            + hue + ',65%,55%,var(--galaxy-alpha,0.12));color:hsl(' + hue
            + ',65%,var(--galaxy-text-l,28%));border:1px solid hsl(' + hue
            + ',55%,var(--galaxy-border-l,65%))"><i class="fas '
            + (extended ? 'fa-code-merge' : 'fa-code-branch') + '"></i><span>#'
            + esc(eventId) + '</span></span>';
    };

    Tab.prototype.labelHtml = function (r, ctx) {
        var it = r.data;
        if (r.envelope) {
            var n = r.childCount;
            return '<span class="tl-val" title="' + esc(it.label) + '">'
                + esc(it.label)
                + (n ? '<span class="tl-val-count">'
                    + plural(n, 'attribute', 'attributes') + '</span>' : '')
                + '</span><span class="etl-chip etl-chip-obj">object</span>'
                + this.originBadge(it.event_id)
                + '<span class="etl-cat" title="' + esc(it.category) + '">'
                + esc(it.category) + '</span>';
        }
        var tail = ctx.depth > 0
            ? (it.relation ? 'as ' + it.relation : '')
            : it.category;
        return '<span class="tl-val" title="' + esc(it.label) + '">'
            + esc(it.label) + '</span><span class="etl-chip etl-chip-attr"'
            + ' title="' + esc(it.type) + '">' + esc(it.type) + '</span>'
            + (ctx.depth > 0 ? '' : this.originBadge(it.event_id))
            + '<span class="etl-cat" title="' + esc(tail) + '">' + esc(tail)
            + '</span>';
    };

    Tab.prototype.datesHtml = function (r) {
        var none = '<span class="tl-d-none">not recorded</span>';
        var it = r.data;
        var a;
        var b;
        if (this.state.basis === 'timestamp') {
            var at;
            if (this.shortWindow) {
                at = hms(r.start) + (r.end > r.start ? ' – ' + hms(r.end) : '');
            } else if (isoDay(r.start) === isoDay(r.end)) {
                at = isoDay(r.start) + ' ' + hm(r.start)
                    + (hm(r.end) !== hm(r.start) ? '–' + hm(r.end) : '');
            }
            if (at) {
                return '<span class="tl-d-at">' + at + '</span>';
            }
        }
        if (r.envelope || this.state.basis === 'timestamp') {
            a = r.shape === 'last' ? null : r.start;
            b = r.shape === 'first' ? null : r.end;
        } else {
            a = ms(it.first_seen);
            b = ms(it.last_seen);
        }
        var short = this.shortWindow;
        var from = a == null ? none : (short ? hms(a) : isoDay(a));
        var to;
        if (b == null) {
            to = none;
        } else if (short) {
            to = hms(b);
        } else if (a != null && isoDay(a) === isoDay(b)) {
            to = 'same day';
        } else {
            to = isoDay(b);
        }
        return '<span class="tl-d-from">' + from + '</span>'
            + '<span class="tl-d-arrow">→</span><span class="tl-d-to">' + to
            + '</span>';
    };

    Tab.prototype.tooltipHtml = function (r, ctx) {
        var it = r.data;
        var e = this.events[it.event_id];
        function seen(v) {
            return v == null
                ? '<span class="tl-d-none">not recorded</span>'
                : full(ms(v)) + ' UTC';
        }
        var sub;
        if (r.envelope) {
            sub = 'object · ' + esc(it.category) + ' · '
                + plural(r.childCount, 'dated attribute', 'dated attributes');
        } else {
            sub = esc(it.type) + ' · ' + esc(it.category)
                + (it.to_ids ? ' · IDS' : '');
            if (ctx.parent) {
                sub += '<br>'
                    + (it.relation ? esc(it.relation) + ' in ' : 'in ')
                    + esc(ctx.parent.data.label);
            }
        }
        var seenRows = '<dt>First seen</dt><dd>' + seen(it.first_seen)
            + '</dd><dt>Last seen</dt><dd>' + seen(it.last_seen) + '</dd>';
        var modified = '<dt>Last modified</dt><dd>' + seen(it.timestamp)
            + '</dd>';
        var byTimestamp = this.state.basis === 'timestamp';
        var out = '<div class="tl-tip-title">' + esc(it.label) + '</div>'
            + '<div class="tl-tip-sub">' + sub + '</div><dl>'
            + (byTimestamp ? modified + seenRows : seenRows + modified);
        if (r.envelope) {
            out += '<dt>' + (byTimestamp ? 'Modified, with its attributes'
                : 'With its attributes') + '</dt><dd>' + full(r.start)
                + ' → ' + full(r.end) + '</dd>';
        }
        if (this.extended && e) {
            out += '<dt>Event</dt><dd>#' + esc(e.id) + ' ' + esc(e.info)
                + '</dd>';
        }
        var tab = r.envelope || ctx.parent ? 'Objects' : 'Attributes';
        return out + '</dl><div class="tl-tip-hint">Click to open it in the '
            + tab + ' tab</div>';
    };

    Tab.prototype.open = function (r, ctx) {
        var tab = r.envelope || ctx.parent ? 'objects' : 'attributes';
        openTab(tab, { searchFor: r.data.uuid });
    };

    Tab.prototype.loadChildren = function (r) {
        var self = this;
        var basis = this.state.basis;
        var suffix = '?object=' + encodeURIComponent(r.data.id)
            + (basis === 'seen' ? '' : '&basis=' + basis);
        return this.fetch(suffix).then(function (res) {
            r.shape = objShape(r.data, res.children, basis);
            return res.children.map(function (c) { return self.attrRow(c); });
        });
    };

    function boot() {
        var root = document.querySelector('#tab-timeline [data-timeline]');
        if (!root || !window.MispTimeline) {
            return;
        }
        var started = false;
        function start() {
            if (!started) {
                started = true;
                new Tab(root);
            }
        }
        var pane = document.getElementById('tab-timeline');
        if (pane.classList.contains('active')) {
            start();
            return;
        }
        var link = document.querySelector('.nav-link[href="#tab-timeline"]');
        if (link) {
            link.addEventListener('shown.bs.tab', start);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
