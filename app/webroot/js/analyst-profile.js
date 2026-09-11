/*
 * The Analyst Profile editor.
 *
 * Four jobs and no arithmetic: switch the open section, mark the
 * fields this session has changed, put a value on the bench, and ask
 * the server what the changed document does to it.
 *
 * The last is what makes the bench recomputed rather than a reading
 * of the saved document, and it is the only kind of network call the
 * page makes. Nothing here re-implements the ledger: a second scoring
 * engine in JavaScript is exactly the thing this feature exists to
 * avoid having, so every number on the bench arrives from the one
 * that already exists.
 */
(function () {
    'use strict';

    /*
     * `assetLoader` echoes the script tag where the view runs, which is
     * above the markup this file drives — so at the moment it executes
     * there is no `#ap-rail` to find, and a lookup here answers null.
     * Waiting for the document is the whole difference between an
     * editor and a page that shipped no JS at all.
     */
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }

function boot() {
    var form = document.getElementById('ap-form');
    var rail = document.getElementById('ap-rail');
    var bench = document.querySelector('.wb-bench');
    if (!rail && !bench) {
        return;
    }

    var raw = document.getElementById('ap-raw');
    var body = rail ? rail.parentNode : null;
    /*
     * The editor and the read-only viewer carry the form, so their
     * bench recomputes in place. The expanded simulator is a page with
     * no form: there the same controls navigate instead.
     */
    var carried = document.getElementById('ap-bench-value');
    var live = !!(form && bench && carried);

    /* ------------------------------------------------------------ *
     * One section open at a time
     * ------------------------------------------------------------ */

    function open(section) {
        if (!rail || !body) {
            return;
        }
        Array.prototype.forEach.call(
            rail.querySelectorAll('.wb-rail-item'),
            function (item) {
                item.classList.toggle('is-open',
                    item.getAttribute('data-sec') === section);
            }
        );
        Array.prototype.forEach.call(
            body.querySelectorAll('.wb-sec'),
            function (pane) {
                pane.classList.toggle('is-open',
                    pane.getAttribute('data-sec') === section);
            }
        );
        /*
         * A disabled field posts nothing, and `edit` prefers a pasted
         * document over a merged section — so the textarea is live
         * only while its own pane is the one on screen. Otherwise
         * saving a weight would post the whole stored document beside
         * it and the paste would win.
         */
        if (raw) {
            raw.disabled = section !== 'raw';
        }
    }

    if (rail) {
        rail.addEventListener('click', function (event) {
            var item = event.target.closest
                ? event.target.closest('.wb-rail-item')
                : null;
            if (!item || !item.getAttribute('data-sec')) {
                return;
            }
            open(item.getAttribute('data-sec'));
        });
    }

    /* ------------------------------------------------------------ *
     * What this session has changed, and where
     * ------------------------------------------------------------ */

    function changed(field) {
        var was = field.getAttribute('data-ap-was');
        if (was === null) {
            return false;
        }
        if (field.type === 'checkbox') {
            return String(field.checked) !== was;
        }
        return String(field.value) !== was;
    }

    function sectionOf(field) {
        var pane = field.closest ? field.closest('.wb-sec') : null;
        return pane ? pane.getAttribute('data-sec') : null;
    }

    function mark() {
        var dirty = {};
        var count = 0;
        Array.prototype.forEach.call(
            document.querySelectorAll('[data-ap-field]'),
            function (field) {
                var moved = changed(field);
                var chip = field.closest ? field.closest('.kv') : null;
                if (chip) {
                    chip.classList.toggle('is-edited', moved);
                }
                if (!moved) {
                    return;
                }
                count++;
                var section = sectionOf(field);
                if (section) {
                    dirty[section] = true;
                }
            }
        );
        if (rail) {
            Array.prototype.forEach.call(
                rail.querySelectorAll('[data-ap-dirty-mark]'),
                function (glyph) {
                    glyph.hidden =
                        !dirty[glyph.getAttribute('data-ap-dirty-mark')];
                }
            );
        }
        return count;
    }

    /* ------------------------------------------------------------ *
     * The bench, recomputed by the engine that scores the real thing
     * ------------------------------------------------------------ */

    var pending = null;
    var inflight = null;

    /*
     * The recompute answers the bench and nothing else, so the
     * contribution column in the signals pane would keep the numbers
     * the page loaded with — a confident `+7` beside a quality that had
     * just moved to 48. The fragment carries the new ledger back and
     * this writes it into the cells, which is the whole reason the
     * column can be trusted enough to colour.
     *
     * No arithmetic here either: the numbers, the labels and the
     * direction pair all arrive computed.
     */
    function repaintLedger() {
        if (!bench) {
            return;
        }
        var carrier = bench.querySelector('[data-ap-ledger]');
        if (!carrier) {
            return;
        }
        var carry;
        try {
            carry = JSON.parse(carrier.getAttribute('data-ap-ledger'));
        } catch (error) {
            return;
        }
        var rows = carry.rows || {};
        var labels = carry.labels || {};

        var table = document.querySelector(
            '.wb-sec[data-sec="signals"] table.wb-tbl');
        if (table) {
            /*
             * Editing a weight can move the lean itself, and the pair
             * is *with* and *against* it — so the swap is re-applied,
             * not assumed to be the one the page rendered with.
             */
            if (carry.direction) {
                table.setAttribute('style', carry.direction);
            } else {
                table.removeAttribute('style');
            }
        }
        var anchor = document.querySelector('[data-ap-anchor]');
        if (anchor) {
            anchor.textContent = carry.anchor || '';
            anchor.hidden = !carry.anchor;
        }

        Array.prototype.forEach.call(
            document.querySelectorAll('[data-ap-contrib]'),
            function (cell) {
                var id = cell.getAttribute('data-ap-contrib');
                var has = Object.prototype.hasOwnProperty.call(rows, id);
                while (cell.firstChild) {
                    cell.removeChild(cell.firstChild);
                }
                if (!carry.benched) {
                    cell.appendChild(sub(labels.none || '—'));
                    return;
                }
                if (!has) {
                    cell.appendChild(sub(labels.no_row || ''));
                    cell.appendChild(sub(labels.no_row_sub || ''));
                    return;
                }
                var points = rows[id];
                var box = document.createElement('div');
                box.className = 'num fw-bold '
                    + (points > 0 ? 'd-up' : (points < 0 ? 'd-dn' : 'd-0'));
                box.textContent = points > 0 ? '+' + points : String(points);
                cell.appendChild(box);
            }
        );
    }

    function sub(text) {
        var node = document.createElement('div');
        node.className = 'wb-sub';
        node.textContent = text;
        return node;
    }

    function refresh() {
        if (!form || !bench) {
            return;
        }
        var url = form.getAttribute('data-ap-simulate');
        if (!url) {
            return;
        }
        if (inflight) {
            inflight.abort();
        }
        var request = new XMLHttpRequest();
        inflight = request;
        request.open('POST', url, true);
        request.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        bench.classList.add('is-recomputing');
        request.onload = function () {
            inflight = null;
            bench.classList.remove('is-recomputing');
            if (request.status >= 200 && request.status < 300) {
                bench.innerHTML = request.responseText;
                repaintLedger();
            }
        };
        request.onerror = function () {
            inflight = null;
            bench.classList.remove('is-recomputing');
        };
        request.send(new FormData(form));
    }

    document.addEventListener('change', function (event) {
        if (!event.target.hasAttribute
            || !event.target.hasAttribute('data-ap-field')) {
            return;
        }
        mark();
        window.clearTimeout(pending);
        pending = window.setTimeout(refresh, 250);
    });

    /* ------------------------------------------------------------ *
     * Putting a value on the bench, and keeping one there
     * ------------------------------------------------------------ */

    /*
     * The URL-safe base64 `ValueUrlTool` mints, so a value holding a
     * slash survives the trip. `btoa` takes bytes, not characters, and
     * a value can hold any of them.
     */
    function encodeValue(value) {
        var bytes = new TextEncoder().encode(value);
        var binary = '';
        for (var i = 0; i < bytes.length; i++) {
            binary += String.fromCharCode(bytes[i]);
        }
        return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_');
    }

    /*
     * Benching is not navigation. The bench exists to be read while a
     * weight is being changed, so swapping the value under it must not
     * throw away the edits that have not been saved — the value rides
     * in a hidden field the recompute already posts, and the address
     * bar is corrected afterwards so a reload lands on the same value.
     *
     * The expanded simulator has no form to post, and nothing unsaved
     * to lose, so there the same press is a link.
     */
    function benchValue(value) {
        if (!live) {
            var inner = document.querySelector('[data-ap-bench-url]');
            var target = inner
                ? inner.getAttribute('data-ap-bench-url')
                : null;
            if (target) {
                window.location.href = target
                    + (target.indexOf('?') === -1 ? '?' : '&')
                    + 'value=' + encodeURIComponent(encodeValue(value));
            }
            return;
        }
        carried.value = value === '' ? '' : encodeValue(value);
        if (window.history && window.history.replaceState) {
            var url = new URL(window.location.href);
            if (carried.value === '') {
                url.searchParams.delete('value');
            } else {
                url.searchParams.set('value', carried.value);
            }
            window.history.replaceState(null, '', url.toString());
        }
        refresh();
    }

    document.addEventListener('click', function (event) {
        var press = event.target.closest
            ? event.target.closest('[data-ap-bench]')
            : null;
        if (!press) {
            return;
        }
        event.preventDefault();
        var value = press.getAttribute('data-ap-bench');
        if (value === '') {
            var input = document.querySelector('[data-ap-bench-input]');
            value = input ? input.value.trim() : '';
            if (value === '') {
                if (input) {
                    input.focus();
                }
                return;
            }
        }
        benchValue(value);
    });

    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Enter'
            || !event.target.hasAttribute
            || !event.target.hasAttribute('data-ap-bench-input')
        ) {
            return;
        }
        /* Inside the editor's form, so Enter would otherwise save. */
        event.preventDefault();
        var value = event.target.value.trim();
        if (value !== '') {
            benchValue(value);
        }
    });

    /*
     * Pinning writes a user setting, never the profile — so it is a
     * POST, and the token that authenticates it is the editor form's.
     * Re-rendering the bench afterwards rather than following the
     * redirect is what keeps the unsaved document on screen.
     */
    document.addEventListener('click', function (event) {
        var press = event.target.closest
            ? event.target.closest('[data-ap-pin]')
            : null;
        if (!press || !form) {
            return;
        }
        event.preventDefault();
        /*
         * Unpinning is not un-benching. Without this the last pinned
         * value is what the bench was reading, so dropping it from the
         * set would empty the pane the press was made from.
         */
        var keep = press.getAttribute('data-ap-value');
        var request = new XMLHttpRequest();
        request.open('POST', press.getAttribute('data-ap-pin'), true);
        request.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        press.disabled = true;
        request.onload = function () {
            if (live && keep) {
                carried.value = encodeValue(keep);
            }
            refresh();
        };
        request.onerror = function () {
            press.disabled = false;
        };
        request.send(new FormData(form));
    });

    /* ------------------------------------------------------------ *
     * Map rows: removing one and adding one
     * ------------------------------------------------------------ */

    document.addEventListener('click', function (event) {
        var drop = event.target.closest
            ? event.target.closest('[data-ap-drop]')
            : null;
        if (!drop) {
            return;
        }
        event.preventDefault();
        /*
         * A map is written whole on save, so a row taken off the page
         * is a key removed from the document. Nothing is written until
         * Save — the bench below says what removing it did.
         */
        var row = drop.closest('tr');
        if (row) {
            row.parentNode.removeChild(row);
            mark();
            refresh();
        }
    });

    /*
     * A box the browser refuses sits in whatever pane it belongs to,
     * and a closed pane is `display: none` — a control the browser
     * cannot report on, so it blocks the save and says nothing at all.
     * Opening the pane first is what turns a silent refusal back into
     * the message beside the box.
     *
     * The first refusal only. The events arrive in tree order and the
     * browser reports on the first of them, so opening a pane for each
     * would leave the last one on screen and the reported box behind
     * it. The flag clears on the next tick, which is after the whole
     * pass.
     */
    var reported = false;
    document.addEventListener('invalid', function (event) {
        if (reported) {
            return;
        }
        reported = true;
        window.setTimeout(function () {
            reported = false;
        }, 0);
        var pane = event.target.closest
            ? event.target.closest('.wb-sec')
            : null;
        if (!pane || pane.classList.contains('is-open')) {
            return;
        }
        open(pane.getAttribute('data-sec'));
    }, true);

    document.addEventListener('change', function (event) {
        var add = event.target;
        if (!add.hasAttribute || !add.hasAttribute('data-ap-add')) {
            return;
        }
        var key = add.value.trim();
        var prefix = add.getAttribute('data-ap-add-name');
        if (key === '' || !prefix) {
            return;
        }
        /*
         * The map this control belongs to, not the first table in the
         * pane: the relevance section holds three of them and the
         * buckets table is the one that comes first.
         */
        var map = add.closest ? add.closest('.ap-map') : null;
        var table = map ? map.querySelector('table.wb-tbl tbody') : null;
        if (!table) {
            return;
        }
        var row = document.createElement('tr');
        var name = document.createElement('td');
        name.innerHTML = '<div class="fw-semibold"></div>';
        name.firstChild.textContent = key;
        var value = document.createElement('td');
        var input = document.createElement('input');
        input.className = 'form-control form-control-sm num text-end';
        /*
         * The same box the server would have drawn for this row. A map
         * whose values are days gets a `number`; without the type the
         * row added on the page is the one place in the editor where a
         * numeric setting still takes a word.
         */
        var kind = add.getAttribute('data-ap-add-type');
        input.type = kind === 'int' || kind === 'float' ? 'number' : 'text';
        if (input.type === 'number') {
            input.step = kind === 'int' ? '1' : 'any';
        }
        input.name = prefix + '[' + key + ']';
        input.setAttribute('data-ap-field', '1');
        input.setAttribute('data-ap-was', '');
        value.appendChild(input);
        row.appendChild(name);
        row.appendChild(value);
        row.appendChild(document.createElement('td'));
        table.appendChild(row);
        add.value = '';
        input.focus();
    });

    /* ------------------------------------------------------------ *
     * A bucket's types: chips in, chips out
     * ------------------------------------------------------------ */

    document.addEventListener('click', function (event) {
        var drop = event.target.closest
            ? event.target.closest('.ap-chip-drop')
            : null;
        if (!drop) {
            return;
        }
        event.preventDefault();
        var chip = drop.closest('.chip');
        if (chip) {
            chip.parentNode.removeChild(chip);
            mark();
            refresh();
        }
    });

    document.addEventListener('change', function (event) {
        var picker = event.target;
        if (!picker.hasAttribute
            || !picker.hasAttribute('data-ap-type-add')) {
            return;
        }
        var type = picker.value.trim();
        if (type === '') {
            return;
        }
        var chip = document.createElement('span');
        chip.className = 'chip is-on';
        chip.appendChild(document.createTextNode(type + ' '));
        var carried = document.createElement('input');
        carried.type = 'hidden';
        // Type-first, like the chips the server drew: the name carries
        // the type and the value carries the bucket.
        carried.name = picker.getAttribute('data-ap-type-add')
            + '[' + type + ']';
        carried.value = picker.getAttribute('data-ap-type-value');
        carried.setAttribute('data-ap-field', '1');
        carried.setAttribute('data-ap-was', '');
        chip.appendChild(carried);
        var remove = document.createElement('a');
        remove.href = '#';
        remove.className = 'ap-chip-drop';
        remove.textContent = '\u00d7';
        chip.appendChild(remove);
        picker.parentNode.insertBefore(chip, picker);
        picker.value = '';
        mark();
        refresh();
    });

    /*
     * Exposed for the header's Save button.
     *
     * `requestSubmit()` and not `submit()`: the second one skips the
     * browser's own checks altogether, so the Save in the header would
     * post a number box holding a half-typed number where the Save
     * inside a pane refuses it. A button that is the page's main one
     * cannot be the one that validates least.
     */
    window.analystProfileSave = function () {
        if (!form) {
            return;
        }
        if (form.requestSubmit) {
            form.requestSubmit();
        } else {
            form.submit();
        }
    };

    mark();
}
})();
