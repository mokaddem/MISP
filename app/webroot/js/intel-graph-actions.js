// "Add to graph" (PRD §8.3). Every control on the page that carries
// data-intel-graph-add — a JSON list of items as IntelGraph.add() takes them,
// each with a label — sends them to the active graph; data-intel-graph-other
// asks which graph instead. With no active graph the dock asks.
//
// What was added is said once: by the dock's arrivals when it is open, by a
// toast with Open and Undo when it is not. Both hold the same undo.
//
//   IntelGraphActions.add(items, { other })   the same, from code (the explorer's menus)
//
// Needs intel-graph.js; loaded with it by Elements/intel_graph_boot.ctp.

(function () {
    'use strict';

    var DELAY = 8000;

    function IG() { return window.IntelGraph; }

    function el(tag, cls, text) {
        var e = document.createElement(tag);
        if (cls) e.className = cls;
        if (text != null) e.textContent = text;
        return e;
    }

    function message(err) {
        var body = err && err.body;
        return (body && (body.message || body.name)) || (err && err.message) || 'error';
    }

    function describe(items) {
        if (items.length === 1) {
            var i = items[0];
            return i.label || (i.type === 'Value' ? i.value : i.type);
        }
        return items.length + ' records';
    }

    function dockOpen() {
        return !!(window.IntelGraphDock && window.IntelGraphDock.isOpen());
    }

    function openDock() {
        if (window.IntelGraphDock) window.IntelGraphDock.open({ focus: true });
    }

    // A toast in Overmind's container, with its own buttons; its text can be
    // rewritten while it shows.
    function toast(variant, text, buttons) {
        var container = document.getElementById('mainToastContainer');
        if (!container || !window.bootstrap) return null;
        var t = el('div', 'toast align-items-center border-0 text-bg-' + variant);
        t.setAttribute('role', variant === 'danger' ? 'alert' : 'status');
        t.setAttribute('aria-live', variant === 'danger' ? 'assertive' : 'polite');
        t.setAttribute('aria-atomic', 'true');
        var row = el('div', 'd-flex align-items-center');
        var body = el('div', 'toast-body', text);
        row.appendChild(body);
        var acts = el('div', 'd-flex align-items-center gap-1 me-2 flex-shrink-0');
        (buttons || []).forEach(function (b) {
            var btn = el('button', 'btn btn-sm btn-light py-0', b.label);
            btn.type = 'button';
            btn.addEventListener('click', function () { b.onClick(btn, handle); });
            acts.appendChild(btn);
        });
        var close = el('button', 'btn-close btn-close-white ms-1');
        close.type = 'button';
        close.setAttribute('data-bs-dismiss', 'toast');
        close.setAttribute('aria-label', 'Close');
        acts.appendChild(close);
        row.appendChild(acts);
        t.appendChild(row);
        container.appendChild(t);
        var bs = new window.bootstrap.Toast(t, { delay: DELAY });
        t.addEventListener('hidden.bs.toast', function () { t.remove(); });
        bs.show();
        var handle = {
            el: t,
            say: function (s) { body.textContent = s; },
            done: function () { acts.querySelectorAll('.btn-light').forEach(function (b) { b.remove(); }); }
        };
        return handle;
    }

    var OPEN = { label: 'Open', onClick: function () { openDock(); } };

    function add(items, options) {
        options = options || {};
        if (!IG() || !items || !items.length) return Promise.resolve(null);
        return IG().add(items, { other: !!options.other }).then(function (out) {
            if (out.status === 'no-graph' || dockOpen()) return out;
            var graph = IG().active();
            var name = '“' + ((graph && graph.name) || 'the graph') + '”';
            var what = describe(items);
            if (out.status === 'unchanged') {
                toast('secondary', 'Already in ' + name + ': ' + what + '.', [OPEN]);
                return out;
            }
            var refused = (out.report.refused || []).length;
            var t = toast('success', 'Added to ' + name + ': ' + what + (refused ? ' (' + refused + ' not added)' : '') + '.', [
                OPEN,
                { label: 'Undo', onClick: function (btn, h) {
                    btn.disabled = true;
                    out.undo().then(function () {
                        h.say('Undone: ' + what + ' is out of ' + name + ' again.');
                        h.done();
                    }, function (err) {
                        btn.disabled = false;
                        h.say('Undo failed: ' + message(err));
                    });
                } }
            ]);
            // Undone from the dock instead: this toast says so.
            if (t && out.undo) {
                var keys = out.report.added || [];
                var off = IG().on('removed', function (d) {
                    var gone = (d.report && d.report.removed) || [];
                    if (d.graph !== out.graph || !keys.every(function (k) { return gone.indexOf(k) !== -1; })) return;
                    t.say('Undone: ' + what + ' is out of ' + name + ' again.');
                    t.done();
                    off();
                });
                t.el.addEventListener('hidden.bs.toast', off);
            }
            return out;
        }, function (err) {
            if (!dockOpen()) {
                toast('danger', 'Not added: ' + message(err), err && err.status === 403 ? [OPEN] : []);
            }
            return null;
        });
    }

    document.addEventListener('click', function (e) {
        var control = e.target.closest && e.target.closest('[data-intel-graph-add]');
        if (!control) return;
        e.preventDefault();
        var items;
        try {
            items = JSON.parse(control.getAttribute('data-intel-graph-add'));
        } catch (err) {
            return;
        }
        if (!Array.isArray(items)) return;
        add(items, { other: control.hasAttribute('data-intel-graph-other') });
    });

    window.IntelGraphActions = { add: add };
}());
