/*
 * Picker filter bars (genericElementsBS5/IndexTable/filter_bar.ctp with
 * `picker` children).
 *
 * The URL is the state. Pickers edit one filter and apply when they close;
 * chips, toggles, stat cards, the pager and the sort links are links the
 * server already built. Applying fetches the page at the new URL and swaps
 * in the results and every `[data-ifp-swap][id]` node, so all wiring here is
 * delegated and survives the swap.
 */
(function () {
    'use strict';

    var UUID_RE = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;
    var MIN_TERM = 2;
    var THROTTLE = 300;

    var open = null;
    var inFlight = null;

    function bar() { return document.querySelector('[data-ifp-bar]'); }

    function cfg() {
        var el = bar();
        if (!el) { return null; }
        if (!el.__ifp) { el.__ifp = JSON.parse(el.getAttribute('data-ifp-bar')); }
        return el.__ifp;
    }

    function S(key) { var c = cfg(); return (c && c.strings && c.strings[key]) || ''; }

    function pathOf(url) { return (url || '').replace(/^[a-z]+:\/\/[^/]+/i, ''); }

    // Text and attribute values alike: cluster tag names carry double quotes.
    function esc(text) {
        return String(text == null ? '' : text).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    /* ── URLs ─────────────────────────────────────────────────────────── */

    function currentParts() {
        var c = cfg();
        return parseIndexUrl(window.location.pathname + window.location.search, pathOf(c.base));
    }

    // changes: unprefixed name => value, null removes it. Paging restarts.
    function urlWith(changes) {
        var c = cfg();
        var parts = currentParts();
        var named = parts.named;
        delete named.page;
        Object.keys(changes).forEach(function (name) {
            var key = c.prefix + name;
            var value = changes[name];
            if (value === null || value === undefined || value === '') {
                delete named[key];
            } else {
                named[key] = value;
            }
        });
        return formatIndexUrl(c.base, { positional: parts.positional, named: named });
    }

    function searchUrl() {
        var c = cfg();
        var field = bar().querySelector('#filterField');
        var term = field ? field.value.trim() : '';
        var changes = {};
        if (c.searchField) { changes[c.searchField] = null; }
        if (c.idField) { changes[c.idField] = null; }
        if (term !== '') {
            var key = (c.idField && (UUID_RE.test(term) || /^[0-9]+$/.test(term))) ? c.idField : c.searchField;
            changes[key] = term;
        }
        return urlWith(changes);
    }

    /* ── loading ──────────────────────────────────────────────────────── */

    function setBusy(busy) {
        var results = document.querySelector(cfg().results);
        if (!results) { return; }
        results.classList.toggle('is-busy', busy);
        var overlay = results.querySelector(':scope > .index-results-overlay');
        if (busy && !overlay) {
            overlay = document.createElement('div');
            overlay.className = 'index-results-overlay';
            overlay.innerHTML = '<div class="misp-loader" role="status"></div>';
            results.appendChild(overlay);
        } else if (!busy && overlay) {
            overlay.remove();
        }
    }

    function showError() {
        var b = bar();
        var old = document.querySelector('.ifp-error');
        if (old) { old.remove(); }
        var alert = document.createElement('div');
        alert.className = 'alert alert-danger alert-dismissible fade show mb-3 ifp-error';
        alert.innerHTML = '<i class="fas fa-exclamation-triangle me-1"></i>' + esc(S('loadError'))
            + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
        var row = document.querySelector('.ifp-row');
        (row || b).insertAdjacentElement('afterend', alert);
    }

    function swapIn(doc) {
        var c = cfg();
        var results = document.querySelector(c.results);
        var fresh = doc.querySelector(c.results);
        if (!results || !fresh) { throw new Error('no results container'); }
        results.innerHTML = fresh.innerHTML;

        // A picker opened while this page was loading reopens on its new node,
        // and a focused toggle or picker button keeps the focus.
        var reopen = null;
        var refocus = null;
        document.querySelectorAll('[data-ifp-swap][id]').forEach(function (el) {
            var next = doc.getElementById(el.id);
            if (!next) { return; }
            if (open && open.picker === el) {
                reopen = el.id;
                closePicker(false);
            }
            if (el.contains(document.activeElement)) { refocus = el.id; }
            el.replaceWith(document.importNode(next, true));
        });
        if (reopen) {
            openPicker(document.getElementById(reopen));
        } else if (refocus) {
            var node = document.getElementById(refocus);
            var target = node.matches('a, button') ? node : node.querySelector('.ifp-btn, a, button');
            if (target) { target.focus(); }
        }
        ['#headerCountBadge', '.index-filter-pager'].forEach(function (selector) {
            var target = document.querySelector(selector);
            var next = doc.querySelector(selector);
            if (target && next) { target.innerHTML = next.innerHTML; }
        });
        var field = bar().querySelector('#filterField');
        var nextField = doc.querySelector('[data-ifp-bar] #filterField');
        if (field && nextField && document.activeElement !== field) { field.value = nextField.value; }
    }

    function afterSwap() {
        if (window.selectedItems && typeof selectedItems.clear === 'function') {
            selectedItems.clear();
            if (typeof updateMultiSelectToolbar === 'function') { updateMultiSelectToolbar(); }
        }
        var scope = document;
        if (scope.querySelector('#viewCard') && typeof setView === 'function') {
            var mobile = typeof isMobile === 'function' && isMobile();
            setView(mobile ? 'card' : (localStorage.getItem('indexViewMode') || 'table'), false, scope);
        }
        var old = document.querySelector('.ifp-error');
        if (old) { old.remove(); }
    }

    function revealTop() {
        var results = document.querySelector(cfg().results);
        if (!results) { return; }
        var navHeight = 56;
        var top = results.getBoundingClientRect().top;
        if (top >= navHeight) { return; }
        window.scrollTo({ top: Math.max(0, top + window.pageYOffset - navHeight - 8), behavior: 'smooth' });
    }

    function load(url, push) {
        if (!bar()) { window.location.href = url; return; }
        if (inFlight) { inFlight.abort(); }
        var controller = new AbortController();
        inFlight = controller;
        setBusy(true);
        fetch(url, { credentials: 'same-origin', signal: controller.signal })
            .then(function (response) {
                if (!response.ok) { throw new Error('HTTP ' + response.status); }
                return response.text();
            })
            .then(function (html) {
                var doc = new DOMParser().parseFromString(html, 'text/html');
                swapIn(doc);
                if (push) { history.pushState({ ifp: true }, '', url); }
                afterSwap();
                revealTop();
            })
            .catch(function (error) {
                if (error.name !== 'AbortError') { showError(); }
            })
            .finally(function () {
                if (inFlight === controller) { inFlight = null; setBusy(false); }
            });
    }

    /* ── pickers ──────────────────────────────────────────────────────── */

    function pickerCfg(picker) {
        if (!picker.__ifp) { picker.__ifp = JSON.parse(picker.getAttribute('data-ifp-picker')); }
        return picker.__ifp;
    }

    function serialize(pc, selection) {
        if (!selection.length) { return null; }
        var included = selection.filter(function (s) { return !s.exclude; });
        // Every fixed option ticked is the same as no filter.
        if (pc.options && !pc.exclude && included.length === pc.options.length) { return null; }
        return selection.map(function (s) { return (s.exclude ? '!' : '') + s.value; }).join('|');
    }

    function swatch(style) {
        if (!style) { return ''; }
        if (style.colour) {
            return '<span class="ifp-swatch" style="--ifp-swatch:' + esc(style.colour) + '"></span>';
        }
        if (style.icon) {
            return '<i class="' + esc(style.icon) + ' ifp-gicon" aria-hidden="true"></i>';
        }
        return '';
    }

    function stateOf(value) {
        var hit = open.selection.find(function (s) { return s.value === value; });
        return hit ? (hit.exclude ? 'exclude' : 'include') : 'none';
    }

    function rowHtml(item) {
        var state = stateOf(item.value);
        var pc = open.pc;
        var sub = item.style && item.style.galaxy ? ' <small>' + esc(item.style.galaxy) + '</small>' : '';
        var title = item.unresolved ? ' title="' + esc(S('unresolved')) + '"' : '';
        var html = '<li class="ifp-opt" role="option" tabindex="-1" data-state="' + state + '"'
            + ' aria-selected="' + (state === 'include') + '" data-value="' + esc(item.value) + '"' + title + '>'
            + '<span class="ifp-opt-check" aria-hidden="true"><i class="fas ' + (state === 'exclude' ? 'fa-ban' : 'fa-check') + '"></i></span>'
            + swatch(item.style)
            + '<span class="ifp-opt-name' + (item.unresolved ? ' is-unresolved' : '') + '">' + esc(item.label) + sub + '</span>';
        if (pc.exclude) {
            html += '<button type="button" class="ifp-opt-ex" data-ifp-exclude aria-pressed="' + (state === 'exclude') + '"'
                + ' title="' + esc(state === 'exclude' ? S('include') : S('exclude')) + '"'
                + ' aria-label="' + esc((state === 'exclude' ? S('include') : S('exclude')) + ' ' + item.label) + '">'
                + '<i class="fas fa-ban" aria-hidden="true"></i></button>';
        }
        return html + '</li>';
    }

    function itemFor(value) {
        var pools = [open.selection, open.results || [], open.pc.options || []];
        for (var i = 0; i < pools.length; i++) {
            var hit = pools[i].find(function (s) { return String(s.value) === value; });
            if (hit) { return hit; }
        }
        return null;
    }

    function renderLists() {
        var pc = open.pc;
        var pop = open.pop;
        var sel = pop.querySelector('.ifp-sel');
        var res = pop.querySelector('.ifp-res');
        var status = pop.querySelector('.ifp-status');
        var activeValue = open.active ? open.active.getAttribute('data-value') : null;
        var focused = document.activeElement && pop.contains(document.activeElement)
            ? document.activeElement.closest('.ifp-opt') : null;
        var focusValue = focused ? focused.getAttribute('data-value') : null;

        if (pc.options) {
            res.innerHTML = pc.options.map(rowHtml).join('');
        } else {
            sel.innerHTML = open.selection.map(rowHtml).join('');
            sel.previousElementSibling.hidden = !open.selection.length;
            var shown = (open.results || []).filter(function (r) {
                return !open.selection.some(function (s) { return s.value === r.value; });
            });
            res.innerHTML = shown.map(rowHtml).join('');
            status.hidden = false;
            if (open.status === 'idle') {
                status.textContent = S('typeToSearch');
            } else if (open.status === 'loading') {
                status.textContent = S('searching');
            } else if (open.status === 'error') {
                status.textContent = S('searchFailed');
            } else if (!open.results.length) {
                status.textContent = S('noMatch');
            } else {
                status.hidden = true;
            }
        }
        var rowFor = function (value) {
            return value === null ? null : pop.querySelector('.ifp-opt[data-value="' + CSS.escape(value) + '"]');
        };
        open.active = rowFor(activeValue);
        if (open.active) { open.active.classList.add('is-active'); }
        // Rebuilding the list drops the focused row; keep focus in the picker.
        if (focusValue !== null) {
            var again = rowFor(focusValue) || pop.querySelector('input') || pop;
            again.focus();
        }
    }

    function toggle(value, exclude) {
        var item = itemFor(value);
        if (!item) { return; }
        var i = open.selection.findIndex(function (s) { return s.value === value; });
        var current = i === -1 ? 'none' : (open.selection[i].exclude ? 'exclude' : 'include');
        var next = exclude
            ? (current === 'exclude' ? 'none' : 'exclude')
            : (current === 'none' ? 'include' : 'none');
        if (i !== -1) { open.selection.splice(i, 1); }
        if (next !== 'none') {
            open.selection.push({ value: item.value, label: item.label, style: item.style || null,
                exclude: next === 'exclude', unresolved: !!item.unresolved });
        }
        open.dirty = true;
        renderLists();
    }

    function search(term) {
        var pc = open.pc;
        if (open.timer) { clearTimeout(open.timer); open.timer = null; }
        if (open.request) { open.request.abort(); open.request = null; }
        if (term.length < MIN_TERM) {
            open.status = 'idle';
            open.results = [];
            renderLists();
            return;
        }
        open.status = 'loading';
        renderLists();
        var state = open;
        state.timer = setTimeout(function () {
            var controller = new AbortController();
            state.request = controller;
            var sep = pc.source.indexOf('?') === -1 ? '?' : '&';
            fetch(pc.source + sep + 'q=' + encodeURIComponent(term), {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
                signal: controller.signal
            })
                .then(function (r) {
                    if (!r.ok) { throw new Error('HTTP ' + r.status); }
                    return r.json();
                })
                .then(function (rows) {
                    if (open !== state) { return; }
                    state.results = Array.isArray(rows) ? rows : [];
                    state.status = 'done';
                    renderLists();
                })
                .catch(function (error) {
                    if (error.name === 'AbortError' || open !== state) { return; }
                    state.status = 'error';
                    renderLists();
                });
        }, THROTTLE);
    }

    function build(picker) {
        var pc = pickerCfg(picker);
        var pop = picker.querySelector('.ifp-pop');
        var html = '';
        if (pc.source) {
            html += '<div class="ifp-search"><i class="fas fa-search" aria-hidden="true"></i>'
                + '<input type="search" class="form-control form-control-sm" autocomplete="off" spellcheck="false"'
                + ' placeholder="' + esc(S('search')) + '" aria-label="' + esc(S('search') + ' ' + pc.label) + '"></div>';
        }
        if (pc.allOf) {
            html += '<p class="ifp-note"><i class="fas fa-circle-info" aria-hidden="true"></i> ' + esc(S('allOf')) + '</p>';
        }
        if (!pc.options) {
            html += '<div class="ifp-head" hidden>' + esc(S('selected')) + '</div>'
                + '<ul class="ifp-opts ifp-sel" role="listbox" aria-multiselectable="true"></ul>';
        }
        html += '<ul class="ifp-opts ifp-res" role="listbox" aria-multiselectable="true"></ul>';
        if (!pc.options) { html += '<div class="ifp-status" role="status"></div>'; }
        var foot = cfg().apply === 'draft' ? S('applyDraft') : S('applyOnClose');
        if (pc.hint) { foot = pc.hint + ' ' + foot; }
        html += '<div class="ifp-foot"><span>' + esc(foot) + '</span>';
        if (cfg().apply === 'draft') {
            html += '<button type="button" class="btn btn-sm btn-primary" data-ifp-apply>' + esc(S('apply')) + '</button>';
        }
        html += '</div>';
        pop.innerHTML = html;
        return pop;
    }

    function openPicker(picker) {
        if (open && open.picker === picker) { return; }
        closePicker(true);
        var pc = pickerCfg(picker);
        var pop = build(picker);
        open = {
            picker: picker,
            pc: pc,
            pop: pop,
            selection: pc.selected.map(function (s) { return Object.assign({}, s); }),
            initial: pc.allOf ? null : serialize(pc, pc.selected),
            dirty: false,
            results: [],
            status: 'idle',
            active: null,
            timer: null,
            request: null,
        };
        pop.hidden = false;
        placePop(picker, pop);
        picker.querySelector('.ifp-btn').setAttribute('aria-expanded', 'true');
        renderLists();
        var input = pop.querySelector('input');
        if (input) {
            input.focus();
        } else {
            var first = pop.querySelector('.ifp-opt');
            if (first) { setActive(first); first.focus(); }
        }
    }

    // Open to the right of the button unless that runs off the viewport.
    function placePop(picker, pop) {
        pop.classList.remove('is-end');
        var rect = pop.getBoundingClientRect();
        if (rect.right > document.documentElement.clientWidth - 8) { pop.classList.add('is-end'); }
    }

    function closePicker(apply) {
        if (!open) { return; }
        var state = open;
        open = null;
        if (state.timer) { clearTimeout(state.timer); }
        if (state.request) { state.request.abort(); }
        state.pop.hidden = true;
        state.pop.innerHTML = '';
        state.picker.querySelector('.ifp-btn').setAttribute('aria-expanded', 'false');
        if (!apply || !state.dirty) { return; }
        if (cfg().apply === 'draft' && apply !== 'draft') { return; }
        var next = serialize(state.pc, state.selection);
        if (next === state.initial) { return; }
        var changes = {};
        changes[state.pc.name] = next;
        load(urlWith(changes), true);
    }

    function setActive(row) {
        if (open.active) { open.active.classList.remove('is-active'); }
        open.active = row;
        if (row) { row.classList.add('is-active'); }
    }

    function move(delta) {
        var rows = Array.prototype.slice.call(open.pop.querySelectorAll('.ifp-opt'));
        if (!rows.length) { return; }
        var at = rows.indexOf(open.active);
        var next = rows[Math.max(0, Math.min(rows.length - 1, at === -1 ? 0 : at + delta))];
        setActive(next);
        next.scrollIntoView({ block: 'nearest' });
    }

    /* ── wiring ───────────────────────────────────────────────────────── */

    function isPlainClick(event) {
        return !(event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0);
    }

    function navLink(target) {
        var link = target.closest('a[href]');
        if (!link) { return null; }
        if (link.hasAttribute('data-ifp-nav')) { return link; }
        var c = cfg();
        if (link.closest('.index-filter-pager')) { return link; }
        var results = link.closest(c.results);
        if (results && (link.closest('.pagination') || link.closest('thead'))) { return link; }
        return null;
    }

    document.addEventListener('click', function (event) {
        if (!bar()) { return; }
        var target = event.target;

        // A click elsewhere closes the open picker, then still does its own job.
        if (open && !open.picker.contains(target)) { closePicker(true); }

        var btn = target.closest('.ifp-btn');
        if (btn) {
            var picker = btn.closest('.ifp-picker');
            if (open && open.picker === picker) { closePicker(true); } else { openPicker(picker); }
            return;
        }

        var opener = target.closest('[data-ifp-open]');
        if (opener) {
            var named = document.getElementById('ifp-picker-' + opener.getAttribute('data-ifp-open'));
            if (named) {
                event.preventDefault();
                named.scrollIntoView({ block: 'nearest' });
                openPicker(named);
            }
            return;
        }

        if (open && open.picker.contains(target)) {
            if (target.closest('[data-ifp-apply]')) { closePicker('draft'); return; }
            var row = target.closest('.ifp-opt');
            if (row) {
                toggle(row.getAttribute('data-value'), !!target.closest('[data-ifp-exclude]'));
                var input = open && open.pop.querySelector('input');
                if (input) { input.focus(); }
            }
            return;
        }

        if (target.closest('#filterButton') && bar().contains(target)) {
            load(searchUrl(), true);
            return;
        }

        var link = navLink(target);
        if (link && isPlainClick(event)) {
            event.preventDefault();
            load(link.getAttribute('href'), true);
        }
    });

    document.addEventListener('input', function (event) {
        if (open && event.target.matches('.ifp-search input')) {
            search(event.target.value.trim());
        }
    });

    document.addEventListener('keydown', function (event) {
        if (!bar()) { return; }
        if (open && event.key === 'Escape') {
            event.preventDefault();
            event.stopPropagation();
            var btn = open.picker.querySelector('.ifp-btn');
            closePicker(true);
            btn.focus();
            return;
        }
        if (open && open.picker.contains(event.target)) {
            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();
                move(event.key === 'ArrowDown' ? 1 : -1);
            } else if (event.key === 'Enter' && open.active && !event.target.closest('[data-ifp-exclude]')) {
                event.preventDefault();
                toggle(open.active.getAttribute('data-value'), false);
            }
            return;
        }
        if (event.key === 'Enter' && event.target.id === 'filterField' && bar().contains(event.target)) {
            event.preventDefault();
            load(searchUrl(), true);
        }
    }, true);

    // Tabbing out of a picker closes it; a click on its own padding does not.
    document.addEventListener('focusout', function (event) {
        if (!open || !open.picker.contains(event.target)) { return; }
        var next = event.relatedTarget;
        if (next && !open.picker.contains(next)) { closePicker(true); }
    });

    window.addEventListener('popstate', function () {
        var c = cfg();
        if (!c || window.location.pathname.indexOf(pathOf(c.base)) !== 0) { return; }
        closePicker(false);
        load(window.location.pathname + window.location.search, false);
    });

    window.IndexFilters = { load: load, urlWith: urlWith };
}());
