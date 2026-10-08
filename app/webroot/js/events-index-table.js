/* Overmind events index — table view: column widths and the narrow-width
   drop order, the column chooser (event_index_hide_columns), the markings
   and lanes fitted to their cells, whole-row click and selection. */
(function () {
    'use strict';

    if (window.eventIndexTable) {
        window.eventIndexTable.scan();
        return;
    }

    function $(s, r) { return (r || document).querySelector(s); }
    function $$(s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); }

    /*
     * k: the te-c-<k> column; opt: the setting that hides it; w / narrow:
     * fixed widths; min / nmin / wt: the flexible title and lanes.
     */
    var COLS = [
        { k: 'sel', w: 34 },
        { k: 'state', w: 30 },
        { k: 'id', w: 56 },
        { k: 'title', flex: true, min: 316, nmin: 200 },
        { k: 'orgc', w: 124, narrow: 84 },
        { k: 'owner', w: 120, opt: 'owner_org' },
        { k: 'user', w: 150, opt: 'creator_user' },
        { k: 'mk', w: 122 },
        { k: 'attrib', ctx: true, min: 112, nmin: 106, wt: 0.29, opt: 'clusters' },
        { k: 'behav', ctx: true, min: 140, nmin: 140, wt: 0.33, opt: 'clusters' },
        { k: 'classif', ctx: true, min: 148, nmin: 116, wt: 0.38, opt: 'clusters' },
        { k: 'attrs', w: 56, narrow: 54, opt: 'attribute_count' },
        { k: 'objs', w: 48, narrow: 46, opt: 'attribute_count' },
        { k: 'reps', w: 40, opt: 'report_count' },
        { k: 'corr', w: 52, narrow: 50, opt: 'correlations' },
        { k: 'extras', w: 100 },
        { k: 'ext', w: 76, opt: 'is_extension' },
        { k: 'dist', w: 44, narrow: 40 },
        { k: 'date', w: 92 },
        { k: 'pub', w: 96, opt: 'publish_timestamp' },
        { k: 'changed', w: 92, narrow: 84, opt: 'timestamp' },
        { k: 'act', w: 40, narrow: 36 }
    ];
    var SUBS = ['sightings', 'proposals', 'discussion'];
    var COL = {};
    COLS.forEach(function (c) { COL[c.k] = c; });
    var LANES = ['attrib', 'behav', 'classif'];
    // Dropped in this order while the columns do not fit; 'narrow' tightens the rest.
    var STEPS = ['ext', 'date', 'user', 'owner', 'pub', 'narrow', 'extras', 'reps'];
    // Graded org names keep about nine characters; the lanes give way.
    var GRADED = { w: 150, narrow: 136 };

    function Table(root) {
        this.root = root;
        var cfg = {};
        try { cfg = JSON.parse(root.getAttribute('data-te') || '{}'); } catch (e) { /* defaults */ }
        this.cfg = cfg;
        this.hidden = {};
        (cfg.hidden || []).forEach(function (k) { this.hidden[k] = true; }, this);
        this.server = cfg.saved === null || cfg.saved === undefined ? null : cfg.saved.slice();
        this.queue = Promise.resolve();
        this.dropped = {};
        this.style = root.previousElementSibling && root.previousElementSibling.matches('style.te-colstyle')
            ? root.previousElementSibling
            : document.head.appendChild(document.createElement('style'));
    }

    Table.prototype.table = function () {
        return $('#tableView table.te-table', this.root);
    };

    Table.prototype.isOn = function (k) {
        var c = COL[k];
        return !this.dropped[k] && !(c && c.opt && this.hidden[c.opt]);
    };

    Table.prototype.fit = function () {
        var table = this.table();
        if (!table) return;
        var wrap = table.parentNode;
        var avail = wrap.clientWidth;
        if (!avail) return;
        var self = this, head = table.querySelector('thead');
        var graded = !!this.cfg.graded;
        var present = {};
        COLS.forEach(function (c) { present[c.k] = !!$('th.te-c-' + c.k, head); });
        var narrow = false;
        function width(c) {
            if (c.k === 'orgc' && graded) return narrow ? GRADED.narrow : GRADED.w;
            return narrow && c.narrow ? c.narrow : c.w;
        }
        function minOf(c) {
            var m = narrow && c.nmin ? c.nmin : c.min;
            // A technique id needs the whole of Behaviour's minimum.
            return graded && c.ctx && narrow && c.k !== 'behav' ? m - 8 : m;
        }
        function on(c) { return present[c.k] && self.isOn(c.k); }
        function need() {
            var s = 0;
            COLS.forEach(function (c) { if (on(c)) s += c.ctx || c.flex ? minOf(c) : width(c); });
            return s;
        }
        this.dropped = {};
        for (var i = 0; i < STEPS.length && need() > avail; i++) {
            if (STEPS[i] === 'narrow') narrow = true;
            else if (present[STEPS[i]] && this.isOn(STEPS[i])) this.dropped[STEPS[i]] = true;
        }
        var fixed = 0;
        COLS.forEach(function (c) {
            if (!on(c) || c.flex || c.ctx) return;
            fixed += width(c);
            $('th.te-c-' + c.k, head).style.width = width(c) + 'px';
        });
        var rem = Math.max(0, avail - fixed);
        var lanes = LANES.filter(function (k) { return on(COL[k]); }).map(function (k) { return COL[k]; });
        var tw = rem;
        if (lanes.length) {
            var lmin = lanes.reduce(function (s, c) { return s + minOf(c); }, 0);
            var wsum = lanes.reduce(function (s, c) { return s + c.wt; }, 0);
            tw = Math.max(minOf(COL.title), Math.min(460, rem - lmin));
            var spare = Math.max(0, rem - tw - lmin), used = 0;
            lanes.forEach(function (c, j) {
                var w = j === lanes.length - 1 ? rem - tw - used : minOf(c) + Math.round(spare * c.wt / wsum);
                used += w;
                $('th.te-c-' + c.k, head).style.width = w + 'px';
            });
        }
        var th = $('th.te-c-title', head);
        if (th) th.style.width = tw + 'px';

        var off = COLS.filter(function (c) { return present[c.k] && !self.isOn(c.k); })
            .map(function (c) { return '.te-index .te-table .te-c-' + c.k; })
            .concat(SUBS.filter(function (s) { return self.hidden[s]; }).map(function (s) { return '.te-index .te-table .dk-x-' + s; }));
        this.style.textContent = off.length ? off.join(',') + '{display:none}' : '';
        this.root.classList.toggle('te-narrow', narrow);

        var nDrop = 0;
        $$('.te-cols label[data-te-opt]', this.root).forEach(function (l) {
            var opt = l.getAttribute('data-te-opt');
            var gone = COLS.some(function (c) { return c.opt === opt && self.dropped[c.k]; });
            var small = $('small', l);
            small.hidden = !gone;
            small.textContent = 'no room at this width';
            l.title = gone ? 'Shown when the window is wider' : '';
            if (gone) nDrop++;
        });
        var note = $('.te-cols-note', this.root);
        if (note) note.textContent = nDrop ? nDrop + ' hidden for width' : '';
    };

    Table.prototype.fitCells = function () {
        var table = this.table();
        if (!table || !table.parentNode.clientWidth) return;
        $$('.te-marks', table).forEach(fitMarks);
        if (!this.hidden.clusters && window.eventIndexCards) {
            window.eventIndexCards.fitLanes($$('tr.te-row .te-lane .te-ctx[data-dk-lane]', table));
        }
    };

    Table.prototype.fitAll = function () {
        this.stickyTop();
        this.fit();
        this.fitCells();
    };

    // The header sticks under whatever chrome is fixed at the top.
    Table.prototype.stickyTop = function () {
        var top = 0;
        $$('body > header, header, nav.rc-nav').forEach(function (el) {
            var p = getComputedStyle(el).position;
            if (p === 'fixed' || p === 'sticky') top = Math.max(top, el.getBoundingClientRect().bottom);
        });
        this.root.style.setProperty('--te-top', Math.max(0, Math.round(top)) + 'px');
    };

    /* ---------- the chooser, written to event_index_hide_columns ---------- */
    Table.prototype.setHidden = function (names) {
        this.hidden = {};
        names.forEach(function (k) { this.hidden[k] = true; }, this);
        $$('.te-cols input[type="checkbox"]', this.root).forEach(function (i) { i.checked = !this.hidden[i.value]; }, this);
        if (window.eventIndexCards) window.eventIndexCards.closePop();
        this.fitAll();
        this.persist();
    };

    // The endpoint flips one name per call, starting from nothing when the
    // reader never saved a choice, so every difference is posted in turn.
    Table.prototype.persist = function () {
        var self = this;
        this.queue = this.queue.then(function () {
            var server = self.server || [];
            var want = Object.keys(self.hidden);
            var flips = want.filter(function (k) { return server.indexOf(k) < 0; })
                .concat(server.filter(function (k) { return !self.hidden[k]; }));
            if (self.server === null && !flips.length) {
                // Nothing hidden still has to be stored, or the defaults return.
                var any = $('.te-cols input[type="checkbox"]', self.root);
                if (any) flips = [any.value, any.value];
            }
            return flips.reduce(function (p, name) {
                return p.then(function () {
                    return fetch(window.baseurl + '/user_settings/eventIndexColumnToggle/' + encodeURIComponent(name), {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'X-CSRF-Token': window.csrfToken || '',
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json'
                        }
                    }).then(function (r) {
                        if (!r.ok) throw new Error(r.status);
                        var now = self.server || [];
                        var at = now.indexOf(name);
                        if (at < 0) now.push(name); else now.splice(at, 1);
                        self.server = now;
                    });
                });
            }, Promise.resolve());
        }).catch(function () {
            if (typeof showMessage === 'function') showMessage('fail', 'Could not save the column choice.');
        });
    };

    /* ---------- rows ---------- */
    Table.prototype.syncSelection = function () {
        var table = this.table();
        if (!table) return;
        $$('tr.te-row', table).forEach(function (tr) {
            var box = $('.item-checkbox', tr);
            tr.classList.toggle('is-selected', !!(box && box.checked));
        });
    };

    Table.prototype.bind = function () {
        var self = this, root = this.root;
        root.addEventListener('change', function (e) {
            if (e.target.closest('.te-cols')) {
                var names = Object.keys(self.hidden).filter(function (k) { return k !== e.target.value; });
                if (!e.target.checked) names.push(e.target.value);
                self.setHidden(names);
                return;
            }
            if (e.target.classList.contains('item-checkbox') || e.target.classList.contains('select_all')) {
                self.syncSelection();
            }
        });
        root.addEventListener('click', function (e) {
            if (e.target.closest('[data-te-reset]')) {
                self.setHidden((self.cfg.defaults || []).slice());
                return;
            }
            var tr = e.target.closest('#tableView tr.te-row');
            if (!tr || e.target.closest('a, button, input, label, select, .dropdown, .dropdown-menu, td.te-c-sel, td.te-c-act, .dk-chip[tabindex]')) return;
            var sel = window.getSelection && window.getSelection();
            if (sel && String(sel).length) return;
            var link = $('.te-title', tr);
            if (!link) return;
            if (e.ctrlKey || e.metaKey) window.open(link.href, '_blank');
            else window.location.href = link.href;
        });
        root.addEventListener('auxclick', function (e) {
            if (e.button !== 1 || e.target.closest('a, button, input')) return;
            var tr = e.target.closest('#tableView tr.te-row');
            var link = tr && $('.te-title', tr);
            if (link) window.open(link.href, '_blank');
        });

        var results = $('#index-results', root);
        if (results) {
            new MutationObserver(function () {
                self.observe();
                self.fitAll();
                self.syncSelection();
            }).observe(results, { childList: true });
        }
        this.observe();
    };

    // Fit whenever the table gets a width: load, resize, the card/table toggle.
    var frame = 0, waiting = new Set();
    var sizes = new ResizeObserver(function (entries) {
        entries.forEach(function (entry) {
            if (entry.contentRect.width && entry.target._teTable) waiting.add(entry.target._teTable);
        });
        if (!frame) {
            frame = requestAnimationFrame(function () {
                frame = 0;
                waiting.forEach(function (t) { t.fitAll(); });
                waiting.clear();
            });
        }
    });
    Table.prototype.observe = function () {
        var view = $('#tableView', this.root);
        if (view && !view._teTable) {
            view._teTable = this;
            sizes.observe(view);
        }
    };

    function fitMarks(cell) {
        var present = $$('.dk-mk:not(.is-none)', cell);
        present.forEach(function (m) { m.classList.remove('te-tight'); });
        cell.classList.add('te-measure');
        for (var j = present.length - 1; j > 0 && cell.scrollWidth > cell.clientWidth; j--) present[j].classList.add('te-tight');
        cell.classList.remove('te-measure');
    }

    var tables = [];
    function scan() {
        $$('.te-index').forEach(function (root) {
            if (root._teTable) return;
            var t = new Table(root);
            root._teTable = t;
            tables.push(t);
            t.bind();
            t.fitAll();
            t.syncSelection();
        });
    }

    window.eventIndexTable = { scan: scan };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', scan);
    } else {
        scan();
    }
    if (document.fonts && document.fonts.ready) {
        document.fonts.ready.then(function () { tables.forEach(function (t) { t.fitAll(); }); });
    }
})();
