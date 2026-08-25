/**
 * Value Profile page interactions (/values/view).
 *
 * Everything here works against markup already in the DOM. This pass has
 * no endpoint to filter against and nothing to write, so a type chip
 * narrows rows the page already holds, and a control that would write is
 * stopped before it can look like it did something.
 *
 * Scoped by <body data-controller="values">, which the layout already
 * emits, so nothing here reaches another page.
 */
(function () {
    'use strict';

    // Slug of the type chip currently pressed, or null for no filter.
    var typeFilter = null;

    function onValuePage() {
        return !!document.body
            && document.body.dataset.controller === 'values';
    }

    /**
     * Row visibility in the occurrence table has two independent inputs —
     * the type filter and the soft-deleted toggle — so it is computed in
     * one place rather than by two handlers racing on the same class.
     */
    function refreshOccurrences() {
        var panel = document.querySelector('[data-vp-occurrences]');
        if (!panel) {
            return;
        }
        var toggle = panel.querySelector('#vp-occ-deleted-toggle');
        var includeDeleted = !!(toggle && toggle.checked);
        var shown = 0;
        var eligible = 0;

        panel.querySelectorAll('tbody tr').forEach(function (row) {
            var deleted = row.classList.contains('vp-occ-deleted');
            var counts = includeDeleted || !deleted;
            var matches = !typeFilter
                || row.classList.contains('vp-occ-type-' + typeFilter);
            if (counts) {
                eligible++;
            }
            if (counts && matches) {
                shown++;
            }
            row.classList.toggle('d-none', !(counts && matches));
        });

        updateFilterNote(panel, shown, eligible);
    }

    /**
     * @param {Element} panel
     * @param {number} shown
     * @param {number} eligible Rows the filter chose from, which is not
     *                          the value's occurrence count: rows hidden
     *                          by ACL or by the soft-deleted toggle were
     *                          never candidates.
     */
    function updateFilterNote(panel, shown, eligible) {
        var note = panel.querySelector('[data-vp-filter-note]');
        var empty = panel.querySelector('[data-vp-filter-empty]');
        var table = panel.querySelector('[data-vp-occ-table]');
        var label = activeChipLabel();

        if (note) {
            note.classList.toggle('d-none', !typeFilter);
            setText(note, '[data-vp-filter-type]', label);
            setText(note, '[data-vp-filter-shown]', shown);
            setText(note, '[data-vp-filter-total]', eligible);
        }
        // Filtering to a type whose only rows are hidden is a real
        // answer, and an empty table with a live header does not say it.
        var blank = !!typeFilter && shown === 0;
        if (empty) {
            empty.classList.toggle('d-none', !blank);
            setText(empty, '[data-vp-filter-type]', label);
        }
        if (table) {
            table.classList.toggle('d-none', blank);
        }
    }

    function setText(root, selector, value) {
        var target = root.querySelector(selector);
        if (target) {
            target.textContent = value;
        }
    }

    function activeChipLabel() {
        var chip = document.querySelector('.vp-type-chip.active');
        return chip ? chip.dataset.vpType : '';
    }

    function toggleTypeFilter(chip) {
        var slug = chip.dataset.vpTypeSlug;
        typeFilter = typeFilter === slug ? null : slug;

        document.querySelectorAll('.vp-type-chip').forEach(function (el) {
            var pressed = el.dataset.vpTypeSlug === typeFilter;
            el.classList.toggle('active', pressed);
            el.setAttribute('aria-pressed', pressed ? 'true' : 'false');
        });

        // The table the chip filters lives in the Overview tab, so
        // filtering from anywhere else goes there rather than quietly
        // narrowing a table nobody is looking at.
        if (typeFilter && window.location.hash !== '#tab-general') {
            window.location.hash = '#tab-general';
        }
        refreshOccurrences();
    }

    /**
     * Bootstrap only takes the pointer away from a disabled control, so
     * its title — the whole explanation of why it is disabled — cannot be
     * read. The stylesheet gives the pointer back; this stops the
     * activation that comes with it, for the mouse and for the keyboard,
     * and states the condition for assistive technology.
     *
     * @param {Element|Document} root
     */
    function markDisabled(root) {
        root.querySelectorAll('a.disabled').forEach(function (link) {
            link.setAttribute('aria-disabled', 'true');
        });
    }

    /* ==============================================================
     * Faceted lists
     * --------------------------------------------------------------
     * One shared control for every panel that narrows a list by
     * counted facets and pages what survives — the Occurrences rail
     * and the Relationships co-occurrence pane both are one.
     *
     * A panel opts in with `data-vp-list` on the region that owns the
     * rows, and the markup carries the rest:
     *
     *   [data-vp-list]              the region
     *     [data-vp-list-rows]         the row host; rows are its
     *                                 tbody > tr, or [data-vp-list-row]
     *     tr[data-vp-facet]           space-separated `key:value`
     *                                 tokens; a row may carry several
     *                                 values for one key
     *     tr[data-vp-hidden]         a token that keeps the row out
     *                                 until something reveals it
     *     input[data-vp-facet-key]   a facet checkbox
     *     input[data-vp-reveal]      reveals rows hidden by that token
     *     [data-vp-pager]            page control, data-vp-page-size
     *     [data-vp-list-empty]       shown when a filter empties the list
     *
     * Within one key the checked values are alternatives; across keys
     * they all have to hold. That is what a reader means by ticking
     * `ip-dst` and `ip-src` under Type and `Org A` under Organisation.
     *
     * Soft-deleted rows are not a facet value: they are excluded
     * until revealed, because filtering *to* deleted rows and
     * including them alongside the rest are different questions.
     * ============================================================== */

    // Current page per list, keyed by the element so several lists on
    // one page keep their own place.
    var listPages = new WeakMap();

    /**
     * @param {Element} list
     * @return {Array<Element>}
     */
    function listRows(list) {
        var host = list.querySelector('[data-vp-list-rows]') || list;
        var explicit = host.querySelectorAll('[data-vp-list-row]');
        if (explicit.length) {
            return Array.prototype.slice.call(explicit);
        }
        return Array.prototype.slice.call(host.querySelectorAll('tbody tr'));
    }

    /**
     * Checked facets, grouped by key. A key absent from the result
     * places no constraint — an unticked group is not an empty one.
     *
     * @param {Element} list
     * @return {Object}
     */
    function activeFacets(list) {
        var active = {};
        list.querySelectorAll('input[data-vp-facet-key]').forEach(function (box) {
            if (!box.checked) {
                return;
            }
            var key = box.dataset.vpFacetKey;
            if (!active[key]) {
                active[key] = [];
            }
            active[key].push(box.value);
        });
        return active;
    }

    /**
     * The filter row's constraints, which are conjunctive where the
     * facet groups are disjunctive.
     *
     * A select and a facet group can name the same key — `Any type`
     * beside a counted `Type` dropdown is exactly the pane the graft
     * asks for. Ticking `domain` and `sha256` in the dropdown means
     * *either*; picking `domain` in the select on top of it means
     * *and also*. Merging the two into one bucket would turn the second
     * control into a third value of the first, which is not what
     * anybody reading the row means by it.
     *
     * @param {Element} list
     * @return {Object} key => token, one per set control
     */
    function activeSelects(list) {
        var active = {};
        list.querySelectorAll('select[data-vp-filter-key]')
            .forEach(function (select) {
                if (select.value === '') {
                    return;
                }
                active[select.dataset.vpFilterKey] = select.value;
            });
        return active;
    }

    /**
     * The free-text box, matched against the row's own `data-vp-text`
     * rather than its rendered cells: a cell can carry a badge, a bar
     * and a truncation, and searching what the reader sees would then
     * mean searching an ellipsis.
     *
     * @param {Element} list
     * @return {string}
     */
    function activeText(list) {
        var input = list.querySelector('[data-vp-filter-text]');
        return input ? input.value.trim().toLowerCase() : '';
    }

    /**
     * The numeric thresholds — `Shared events ≥`, `Similarity ≥`. A
     * threshold at its floor places no constraint and is not counted
     * as a set filter, so the panel does not claim a filter is applied
     * when the control is where it started.
     *
     * @param {Element} list
     * @return {Object} key => minimum
     */
    function activeMinimums(list) {
        var active = {};
        list.querySelectorAll('[data-vp-filter-min]').forEach(function (input) {
            var value = parseFloat(input.value);
            var floor = parseFloat(input.min);
            if (isNaN(value) || (!isNaN(floor) && value <= floor)) {
                return;
            }
            active[input.dataset.vpFilterMin] = value;
        });
        return active;
    }

    /**
     * @param {Element} row
     * @param {string} key
     * @return {number|null}
     */
    function rowNumber(row, key) {
        var match = (row.dataset.vpNum || '')
            .match(new RegExp('(?:^|\\s)' + key + ':(-?\\d+(?:\\.\\d+)?)'));
        return match ? parseFloat(match[1]) : null;
    }

    /**
     * @param {Element} list
     * @return {Array<string>} Tokens whose hidden rows are revealed.
     */
    function revealedTokens(list) {
        var tokens = [];
        list.querySelectorAll('input[data-vp-reveal]').forEach(function (box) {
            if (box.checked) {
                tokens.push(box.dataset.vpReveal);
            }
        });
        return tokens;
    }

    /**
     * @param {Element} row
     * @param {Object} active
     * @return {boolean}
     */
    function rowMatches(row, active) {
        var tokens = (row.dataset.vpFacet || '').split(/\s+/);
        return Object.keys(active).every(function (key) {
            return active[key].some(function (value) {
                return tokens.indexOf(key + ':' + value) !== -1;
            });
        });
    }

    /**
     * The one place a faceted list's row visibility is decided, so the
     * facets, the reveal switches and the page control cannot each
     * hold a different opinion about which rows are showing.
     *
     * @param {Element} list
     */
    function refreshList(list) {
        var active = activeFacets(list);
        var selects = activeSelects(list);
        var minimums = activeMinimums(list);
        var text = activeText(list);
        var revealed = revealedTokens(list);
        // A roll-up the reader is not looking at is excluded before
        // anything else: its rows are a different kind of row, and
        // paging them alongside the visible ones would give the page
        // control a count nothing on screen agrees with.
        var group = list.dataset.vpGroupActive || null;
        var filtered = [];

        listRows(list).forEach(function (row) {
            var hidden = row.dataset.vpHidden;
            var keep = !(!!hidden && revealed.indexOf(hidden) === -1);
            if (keep && group && row.dataset.vpGroup) {
                keep = row.dataset.vpGroup === group;
            }
            if (keep) {
                keep = rowMatches(row, active)
                    && rowMatchesSelects(row, selects)
                    && rowMatchesMinimums(row, minimums)
                    && rowMatchesText(row, text);
            }
            if (keep) {
                filtered.push(row);
            } else {
                row.classList.add('d-none');
            }
        });

        var activeCount = Object.keys(active).reduce(function (sum, key) {
            return sum + active[key].length;
        }, 0)
            + Object.keys(selects).length
            + Object.keys(minimums).length
            + (text === '' ? 0 : 1);

        sortRows(list, filtered);
        paginate(list, filtered);
        updateListNotes(list, filtered.length, activeCount);
    }

    /**
     * @param {Element} row
     * @param {Object} selects
     * @return {boolean}
     */
    function rowMatchesSelects(row, selects) {
        var tokens = (row.dataset.vpFacet || '').split(/\s+/);
        return Object.keys(selects).every(function (key) {
            return tokens.indexOf(key + ':' + selects[key]) !== -1;
        });
    }

    /**
     * A row with no number for a key it is being thresholded on is
     * dropped rather than kept: the control is asking a question that
     * row cannot answer, and keeping it would make the threshold look
     * like it did nothing.
     *
     * @param {Element} row
     * @param {Object} minimums
     * @return {boolean}
     */
    function rowMatchesMinimums(row, minimums) {
        return Object.keys(minimums).every(function (key) {
            var value = rowNumber(row, key);
            return value !== null && value >= minimums[key];
        });
    }

    /**
     * @param {Element} row
     * @param {string} needle
     * @return {boolean}
     */
    function rowMatchesText(row, needle) {
        if (needle === '') {
            return true;
        }
        return (row.dataset.vpText || '').indexOf(needle) !== -1;
    }

    /**
     * Reorder the surviving rows, descending, on whichever number the
     * rank select names.
     *
     * Done in the DOM rather than by re-rendering, because the rows are
     * already here: this pass pages over what the fragment carries, and
     * a sort that re-queried would be the one control on the tab that
     * did. Rows are re-appended to their own host, so the three
     * roll-ups cannot end up interleaved.
     *
     * @param {Element} list
     * @param {Array<Element>} filtered
     */
    function sortRows(list, filtered) {
        var select = list.querySelector('[data-vp-sort]');
        if (!select || filtered.length < 2) {
            return;
        }
        var key = select.value;
        var ordered = filtered.slice().sort(function (a, b) {
            return (rowNumber(b, key) || 0) - (rowNumber(a, key) || 0);
        });
        ordered.forEach(function (row) {
            if (row.parentNode) {
                row.parentNode.appendChild(row);
            }
        });
        // `filtered` is what paginate() slices, so it has to end up in
        // the order the reader is about to see rather than the order
        // the server happened to render.
        filtered.length = 0;
        Array.prototype.push.apply(filtered, ordered);
    }

    /**
     * Switch which roll-up is on screen.
     *
     * The narrowing controls belong to the value roll-up and are put
     * away with it: a facet like Type is a property of a correlated
     * value, and an event row is not a value. Anything set is cleared
     * on the way out rather than left applying invisibly.
     *
     * @param {Element} select A [data-vp-group] select
     */
    function switchGroup(select) {
        var list = select.closest('[data-vp-list]');
        if (!list) {
            return;
        }
        var group = select.value;
        list.dataset.vpGroupActive = group;
        list.querySelectorAll('[data-vp-group-pane]').forEach(function (pane) {
            pane.classList.toggle('d-none',
                pane.dataset.vpGroupPane !== group);
        });
        list.querySelectorAll('[data-vp-group-only]').forEach(function (el) {
            el.classList.toggle('d-none', el.dataset.vpGroupOnly !== group);
        });
        list.querySelectorAll('[data-vp-group-not]').forEach(function (el) {
            el.classList.toggle('d-none', el.dataset.vpGroupNot === group);
        });
        clearListFilters(list);
        listPages.set(list, 1);
        refreshList(list);
    }

    /**
     * Put every narrowing control in this list back where it started.
     *
     * @param {Element} list
     */
    function clearListFilters(list) {
        list.querySelectorAll('input[data-vp-facet-key]:checked')
            .forEach(function (box) {
                box.checked = false;
            });
        list.querySelectorAll('select[data-vp-filter-key]')
            .forEach(function (select) {
                select.value = '';
            });
        list.querySelectorAll('[data-vp-filter-text]')
            .forEach(function (input) {
                input.value = '';
            });
        list.querySelectorAll('[data-vp-filter-min]')
            .forEach(function (input) {
                input.value = input.min === '' ? '0' : input.min;
            });
    }

    /**
     * How many rows are ticked, and the actions that would act on them.
     *
     * Counts every checked row including one a filter has since taken
     * off screen: it is still selected, and a bulk action would still
     * carry it.
     *
     * @param {Element} list
     */
    function refreshSelection(list) {
        var readout = list.querySelector('[data-vp-rel-selected]');
        if (!readout) {
            return;
        }
        var boxes = list.querySelectorAll('[data-vp-rel-select]');
        var checked = list.querySelectorAll('[data-vp-rel-select]:checked');
        readout.textContent = checked.length;

        var all = list.querySelector('[data-vp-rel-select-all]');
        if (all) {
            all.checked = boxes.length > 0 && checked.length === boxes.length;
            all.indeterminate = checked.length > 0
                && checked.length < boxes.length;
        }
        list.querySelectorAll('[data-vp-rel-select]').forEach(function (box) {
            var row = box.closest('tr');
            if (row) {
                row.classList.toggle('vp-rel-rowsel', box.checked);
            }
        });
    }

    /**
     * Show one page of the rows a filter left, and redraw the control
     * so its page count matches. Nothing re-queries: the pages are
     * slices of rows the fragment already carries.
     *
     * @param {Element} list
     * @param {Array<Element>} filtered
     */
    function paginate(list, filtered) {
        var pager = list.querySelector('[data-vp-pager]');
        var size = pager ? parseInt(pager.dataset.vpPageSize, 10) : 0;

        if (!pager || !size || size < 1) {
            filtered.forEach(function (row) {
                row.classList.remove('d-none');
            });
            setListRange(list, filtered.length ? 1 : 0, filtered.length,
                filtered.length);
            return;
        }

        var pages = Math.max(1, Math.ceil(filtered.length / size));
        var page = Math.min(listPages.get(list) || 1, pages);
        listPages.set(list, page);

        var from = (page - 1) * size;
        var to = Math.min(from + size, filtered.length);
        filtered.forEach(function (row, index) {
            row.classList.toggle('d-none', index < from || index >= to);
        });

        setListRange(list, filtered.length ? from + 1 : 0, to,
            filtered.length);
        renderPager(pager, page, pages);
    }

    /**
     * Bootstrap pagination markup, arrows dead at the ends rather than
     * present and inert.
     *
     * @param {Element} pager
     * @param {number} page
     * @param {number} pages
     */
    function renderPager(pager, page, pages) {
        var list = pager.querySelector('ul.pagination');
        if (!list) {
            return;
        }
        var items = [];
        items.push(pageItem('«', page - 1, page === 1, false));
        for (var n = 1; n <= pages; n++) {
            items.push(pageItem(String(n), n, false, n === page));
        }
        items.push(pageItem('»', page + 1, page === pages, false));
        list.innerHTML = items.join('');
        // A single page is not a choice, so the control says nothing.
        pager.classList.toggle('d-none', pages < 2);
    }

    /**
     * @param {string} label
     * @param {number} target
     * @param {boolean} disabled
     * @param {boolean} current
     * @return {string}
     */
    function pageItem(label, target, disabled, current) {
        var classes = ['page-item'];
        if (disabled) {
            classes.push('disabled');
        }
        if (current) {
            classes.push('active');
        }
        return '<li class="' + classes.join(' ') + '">'
            + '<button type="button" class="page-link"'
            + ' data-vp-page="' + target + '"'
            + (disabled ? ' disabled' : '')
            + (current ? ' aria-current="page"' : '')
            + '>' + label + '</button></li>';
    }

    /**
     * @param {Element} list
     * @param {number} from
     * @param {number} to
     * @param {number} of
     */
    function setListRange(list, from, to, of) {
        setText(list, '[data-vp-page-from]', from);
        setText(list, '[data-vp-page-to]', to);
        setText(list, '[data-vp-page-of]', of);
    }

    /**
     * The lines that say what the reader is looking at. `shown` is the
     * rows the filter left; the list's total is left alone, because it
     * is what the value has and not what this panel is displaying —
     * conflating them is how a filtered table starts claiming the
     * value has fewer occurrences than it does.
     *
     * @param {Element} list
     * @param {number} shown
     * @param {number} activeCount
     */
    function updateListNotes(list, shown, activeCount) {
        setText(list, '[data-vp-list-shown]', shown);
        setText(list, '[data-vp-facet-rows]', shown);
        setText(list, '[data-vp-facet-count-active]', activeCount);

        var summary = list.querySelector('[data-vp-facet-summary]');
        if (summary) {
            summary.classList.toggle('vp-facet-summary-on', activeCount > 0);
        }

        var clear = list.querySelector('[data-vp-facet-clear]');
        if (clear) {
            clear.disabled = activeCount === 0;
        }

        var empty = list.querySelector('[data-vp-list-empty]');
        // Only a filter can produce this: a list that was empty to
        // begin with has its own empty state from the template, and
        // saying "no rows match" over it would be a different claim.
        var blank = activeCount > 0 && shown === 0;
        if (empty) {
            empty.classList.toggle('d-none', !blank);
        }
        var rows = list.querySelector('[data-vp-list-rows]');
        if (rows && empty) {
            rows.classList.toggle('d-none', blank);
        }
    }

    function refreshAllLists(root) {
        (root || document).querySelectorAll('[data-vp-list]')
            .forEach(function (list) {
                refreshList(list);
                refreshBulkScope(list);
                refreshSelection(list);
            });
    }

    /**
     * A long tail is cut visibly: the group renders its top ten and an
     * `n more` that reveals the rest in place.
     *
     * @param {Element} button
     */
    function expandFacetGroup(button) {
        var group = button.closest('.vp-facetgrp');
        if (!group) {
            return;
        }
        group.querySelectorAll('[data-vp-facet-overflow]')
            .forEach(function (row) {
                row.classList.remove('d-none');
            });
        button.classList.add('d-none');
    }

    /**
     * Past ~50 values a group is a search box rather than a list, and
     * the box narrows the group's own rows — not the table's.
     *
     * @param {Element} input
     */
    function filterFacetGroup(input) {
        var group = input.closest('.vp-facetgrp');
        if (!group) {
            return;
        }
        var needle = input.value.trim().toLowerCase();
        group.querySelectorAll('.vp-facet').forEach(function (row) {
            var label = row.querySelector('.vp-facet-label');
            var text = (label ? label.textContent : '').toLowerCase();
            row.classList.toggle('d-none',
                needle !== '' && text.indexOf(needle) === -1);
        });
    }

    /* ==============================================================
     * The Occurrences tab's table
     * --------------------------------------------------------------
     * Two controls the faceted list does not own: which columns are
     * showing, and what the current selection spans.
     * ============================================================== */

    /**
     * Twelve columns, nine of them shown, and the panel header states
     * the ratio — so a toggle has to move the heading with the cells
     * and correct the ratio, or the table quietly stops matching the
     * number above it.
     *
     * @param {Element} box A [data-vp-col] checkbox
     */
    function toggleColumn(box) {
        var panel = box.closest('.vp-panel');
        if (!panel) {
            return;
        }
        panel.querySelectorAll('.' + box.dataset.vpCol)
            .forEach(function (cell) {
                cell.classList.toggle('d-none', !box.checked);
            });
        setText(panel, '[data-vp-col-shown]',
            panel.querySelectorAll('[data-vp-col]:checked').length);
    }

    /**
     * What the selection spans, beside the count of it. On an index
     * whose rows are attributes drawn from several events the count
     * alone is ambiguous: three rows can be one event or three, and a
     * bulk action means something different in each case.
     *
     * Counts every checked row, including one a filter has since taken
     * off screen — it is still selected, and MISP's own selection map
     * still holds it.
     *
     * @param {Element} list
     */
    function refreshBulkScope(list) {
        var bulk = list.querySelector('[data-vp-bulk]');
        var note = bulk && bulk.querySelector('#multiSelectScopeNote');
        if (!note || !bulk.dataset.vpScopeTemplate) {
            return;
        }
        var events = {};
        var orgs = {};
        var rows = 0;
        list.querySelectorAll('.item-checkbox:checked').forEach(function (box) {
            var row = box.closest('tr');
            if (!row) {
                return;
            }
            rows++;
            events[row.dataset.vpEvent] = true;
            orgs[row.dataset.vpOrg] = true;
        });
        note.textContent = bulk.dataset.vpScopeTemplate
            .replace('%1$s', rows)
            .replace('%2$s', Object.keys(events).length)
            .replace('%3$s', Object.keys(orgs).length);
    }


    /* ==================================================================
     * Charts
     * ------------------------------------------------------------------
     * A canvas cannot inherit a CSS variable, so a chart's colours are
     * resolved against the canvas at init and resolved again whenever
     * the theme flips. Two panels draw charts and both need exactly
     * this, so it lives here rather than in whichever fragment happened
     * to want it first.
     * ================================================================== */

    /**
     * Turn every `var(--x)` in a config into the value that variable
     * has on `el`.
     *
     * Walks the whole config rather than a list of known keys: colours
     * turn up in scales, plugins and datasets alike, and a config is
     * plain data by the time it gets here.
     *
     * @param {*} node
     * @param {Element} el
     * @return {*}
     */
    function resolveChartColours(node, el) {
        if (typeof node === 'string') {
            var match = node.match(/^var\((--[\w-]+)\)$/);
            if (!match) {
                return node;
            }
            var resolved = getComputedStyle(el)
                .getPropertyValue(match[1]).trim();
            return resolved || node;
        }
        if (Array.isArray(node)) {
            return node.map(function (item) {
                return resolveChartColours(item, el);
            });
        }
        if (node && typeof node === 'object') {
            var out = {};
            Object.keys(node).forEach(function (key) {
                out[key] = resolveChartColours(node[key], el);
            });
            return out;
        }
        return node;
    }

    /**
     * Build a chart once Chart.js has arrived, and rebuild it on demand
     * or whenever the theme changes.
     *
     * Safe inside a lazily-injected fragment: the global is polled for
     * rather than assumed, since a fragment cannot know it is the first
     * one to land.
     *
     * @param {string} id Canvas id, namespaced by the caller
     * @param {function(Element): Object} build Returns a Chart
     * @return {{refresh: function}} A handle the caller redraws through
     */
    function bootChart(id, build) {
        var chart = null;

        function make() {
            var el = document.getElementById(id);
            if (!el || typeof Chart === 'undefined') {
                return false;
            }
            if (chart) {
                chart.destroy();
            }
            chart = build(el);
            return true;
        }

        function boot() {
            if (typeof Chart === 'undefined') {
                setTimeout(boot, 100);
                return;
            }
            if (!make()) {
                return;
            }
            // The observer stops itself once the canvas is gone — a
            // reloaded fragment brings its own script with it.
            var observer = new MutationObserver(function () {
                if (!document.getElementById(id)) {
                    observer.disconnect();
                    return;
                }
                make();
            });
            observer.observe(document.documentElement, {
                attributes: true,
                attributeFilter: ['data-bs-theme'],
            });
        }

        boot();
        return { refresh: make };
    }

    window.VP = window.VP || {};
    window.VP.chart = { resolve: resolveChartColours, boot: bootChart };

    /* ==================================================================
     * Sightings tab
     * ------------------------------------------------------------------
     * One overlay and one brush. The chart and the list arrive as two
     * fragments in whichever order the network decides, so the state
     * lives here and each fragment applies it when it lands rather than
     * one of them reaching into the other.
     *
     * Everything is client-side against data the fragments already
     * carry: changing the range redraws from an array the template
     * transposed, and dragging the brush hides table rows. Nothing
     * re-queries, and nothing writes.
     * ================================================================== */

    var sight = {
        data: null,
        rangeKey: null,
        // Which of the three types are drawn. A type with no rows at
        // all is disabled in the markup and never reaches this.
        shown: { sighting: true, fp: true, expiration: true },
        // Bucket index bounds of the brush, or null while it covers the
        // whole range.
        brush: null,
        expanded: false,
        main: null,
        nav: null,
    };

    var SIGHT_ORG_COLOURS = 6;
    var SIGHT_CURVE_COLOURS = 2;

    /**
     * @return {Object|null}
     */
    function sightRange() {
        if (!sight.data) {
            return null;
        }
        var found = null;
        sight.data.ranges.forEach(function (range) {
            if (range.key === sight.rangeKey) {
                found = range;
            }
        });
        return found || sight.data.ranges[0];
    }

    /**
     * A categorical colour by position, cycling once the palette runs
     * out. Named variables rather than literals: the canvas resolves
     * them against the page, so the ramp follows the theme.
     *
     * @param {number} index
     * @param {number} count How many the palette defines
     * @param {string} name `org` or `curve`
     * @return {string}
     */
    function sightHue(index, count, name) {
        return 'var(--vp-sight-' + name + '-' + ((index % count) + 1) + ')';
    }

    /**
     * The overlay itself: one stacked bar dataset per organisation on
     * the count axis, one line per decaying model on the score axis,
     * and a dotted threshold under each line.
     *
     * The thresholds are drawn as datasets rather than annotations —
     * the annotation plugin is not loaded — and labelled by a plugin
     * declared inline, which is the one thing this chart does that
     * `value_chart` could not have done for it.
     *
     * @param {Element} el
     * @return {Object}
     */
    function buildSightMain(el) {
        var data = sight.data;
        var range = sightRange();
        var datasets = [];

        if (sight.shown.sighting) {
            range.org.forEach(function (counts, i) {
                datasets.push({
                    type: 'bar',
                    label: data.orgs[i],
                    data: counts,
                    backgroundColor: sightHue(i, SIGHT_ORG_COLOURS, 'org'),
                    stack: 'reports',
                    yAxisID: 'y',
                    order: 3,
                });
            });
        }
        if (sight.shown.fp) {
            datasets.push({
                type: 'bar',
                label: data.labels.falsePositive,
                data: range.fp,
                backgroundColor: 'var(--vp-sight-fp)',
                stack: 'reports',
                yAxisID: 'y',
                order: 3,
            });
        }
        if (sight.shown.expiration) {
            datasets.push({
                type: 'bar',
                label: data.labels.expiration,
                data: range.expiration,
                backgroundColor: 'var(--vp-sight-exp)',
                stack: 'reports',
                yAxisID: 'y',
                order: 3,
            });
        }

        var thresholds = [];
        range.curves.forEach(function (curve, i) {
            var colour = sightHue(i, SIGHT_CURVE_COLOURS, 'curve');
            datasets.push({
                type: 'line',
                label: curve.model,
                data: curve.points,
                borderColor: colour,
                backgroundColor: colour,
                borderWidth: 2,
                pointRadius: 0,
                pointHoverRadius: 3,
                tension: 0.25,
                spanGaps: false,
                yAxisID: 'score',
                order: 1,
            });
            var line = range.labels.map(function () {
                return curve.threshold;
            });
            var label = data.labels.threshold.replace('%s', curve.threshold);
            datasets.push({
                type: 'line',
                label: label,
                data: line,
                borderColor: colour,
                borderWidth: 1,
                borderDash: [4, 3],
                pointRadius: 0,
                pointHoverRadius: 0,
                yAxisID: 'score',
                order: 2,
            });
            thresholds.push({ value: curve.threshold, text: label });
        });

        var config = window.VP.chart.resolve({
            data: { labels: range.labels, datasets: datasets },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: false,
                interaction: { mode: 'index', intersect: false },
                scales: {
                    x: {
                        stacked: true,
                        grid: { display: false },
                        border: { color: 'var(--bs-border-color)' },
                        ticks: {
                            color: 'var(--bs-secondary-color)',
                            maxRotation: 0,
                            autoSkipPadding: 24,
                            font: { size: 10 },
                        },
                    },
                    y: {
                        stacked: true,
                        beginAtZero: true,
                        border: { display: false },
                        grid: { color: 'var(--bs-border-color)' },
                        ticks: {
                            color: 'var(--bs-secondary-color)',
                            precision: 0,
                            font: { size: 10 },
                        },
                    },
                    score: {
                        position: 'right',
                        min: 0,
                        max: 100,
                        border: { display: false },
                        grid: { drawOnChartArea: false },
                        ticks: {
                            color: 'var(--bs-secondary-color)',
                            stepSize: 25,
                            font: { size: 10 },
                        },
                    },
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        // A stack of ten one-report bars would otherwise
                        // list ten organisations reporting zero.
                        filter: function (item) {
                            return item.parsed.y !== 0
                                || item.dataset.type === 'line';
                        },
                    },
                },
            },
        }, el);

        /*
         * A dotted line at 60 means nothing until it says 60. Chart.js
         * has no annotation plugin loaded, so the labels are drawn here
         * — on a chip of the page's own ground, because a curve passing
         * under the text is the normal case rather than the unlucky
         * one.
         */
        config.plugins = [{
            id: 'vpThresholdLabels',
            afterDatasetsDraw: function (chart) {
                var scale = chart.scales.score;
                var ctx = chart.ctx;
                var style = getComputedStyle(el);
                var ink = style.getPropertyValue('--bs-secondary-color')
                    .trim();
                var ground = style.getPropertyValue('--bs-body-bg').trim();
                ctx.save();
                ctx.font = '10px sans-serif';
                ctx.textAlign = 'right';
                ctx.textBaseline = 'middle';
                thresholds.forEach(function (threshold) {
                    var right = chart.chartArea.right - 3;
                    var y = scale.getPixelForValue(threshold.value);
                    var width = ctx.measureText(threshold.text).width;
                    ctx.globalAlpha = 0.88;
                    ctx.fillStyle = ground;
                    ctx.fillRect(right - width - 4, y - 7, width + 6, 14);
                    ctx.globalAlpha = 1;
                    ctx.fillStyle = ink;
                    ctx.fillText(threshold.text, right, y);
                });
                ctx.restore();
            },
        }];
        config.type = 'bar';
        return new Chart(el, config);
    }

    /**
     * The navigator: every report in the range as one bar per bucket,
     * with no axes, so the brush above it can be positioned as a plain
     * fraction of the strip's width.
     *
     * @param {Element} el
     * @return {Object}
     */
    function buildSightNav(el) {
        var range = sightRange();
        var totals = range.labels.map(function (label, i) {
            var sum = range.fp[i] + range.expiration[i];
            range.org.forEach(function (counts) {
                sum += counts[i];
            });
            return sum;
        });
        var config = window.VP.chart.resolve({
            type: 'bar',
            data: {
                labels: range.labels,
                datasets: [{
                    data: totals,
                    backgroundColor: 'var(--vp-sight-nav)',
                    barPercentage: 1,
                    categoryPercentage: 1,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: false,
                layout: { padding: 0 },
                scales: {
                    x: { display: false },
                    y: { display: false, beginAtZero: true },
                },
                plugins: {
                    legend: { display: false },
                    tooltip: { enabled: false },
                },
            },
        }, el);
        return new Chart(el, config);
    }

    /**
     * @param {Element} panel
     */
    function updateSightLegend(panel) {
        var range = sightRange();
        panel.querySelectorAll('[data-vp-sight-key-org]')
            .forEach(function (key) {
                var i = parseInt(key.dataset.vpSightKeyOrg, 10);
                setText(key, '[data-vp-sight-key-count]', range.orgCounts[i]);
            });
        setText(panel, '[data-vp-sight-key-fp]', range.fpCount);
        setText(panel, '[data-vp-sight-key-exp]', range.expirationCount);

        var axis = panel.querySelector('[data-vp-sight-axis-left]');
        if (axis) {
            axis.textContent = range.step === 1
                ? sight.data.labels.perDay
                : sight.data.labels.perWeek;
        }
    }

    /**
     * Where the brush currently sits, as dates.
     *
     * @return {{from: string, to: string, whole: boolean}}
     */
    function sightWindow() {
        var range = sightRange();
        var last = range.labels.length - 1;
        var from = sight.brush ? sight.brush.from : 0;
        var to = sight.brush ? sight.brush.to : last;
        return {
            from: range.starts[from],
            to: range.ends[to],
            whole: from === 0 && to === last,
        };
    }

    /**
     * @param {Element} panel
     */
    function paintSightBrush(panel) {
        var range = sightRange();
        var count = range.labels.length;
        var from = sight.brush ? sight.brush.from : 0;
        var to = sight.brush ? sight.brush.to : count - 1;
        var left = (100 * from) / count;
        var right = (100 * (count - 1 - to)) / count;

        var maskLeft = panel.querySelector('[data-vp-sight-mask-left]');
        var maskRight = panel.querySelector('[data-vp-sight-mask-right]');
        var handle = panel.querySelector('[data-vp-sight-handle]');
        if (maskLeft) {
            maskLeft.style.width = left + '%';
        }
        if (maskRight) {
            maskRight.style.width = right + '%';
        }
        if (handle) {
            handle.style.left = left + '%';
            handle.style.right = right + '%';
        }

        var window_ = sightWindow();
        var label = panel.querySelector('[data-vp-sight-window]');
        if (label) {
            label.textContent = window_.from + ' → ' + window_.to;
        }
    }

    /**
     * Row visibility in the sightings list, decided in one place: the
     * brush chooses which rows are candidates and `load the rest`
     * chooses how many of them are on screen.
     */
    function refreshSightList() {
        var list = document.querySelector('[data-vp-sight-list]');
        if (!list) {
            return;
        }
        var size = parseInt(list.dataset.vpSightPageSize, 10) || 10;
        var window_ = sight.data ? sightWindow() : null;
        var matched = [];

        list.querySelectorAll('tbody tr').forEach(function (row) {
            var day = row.dataset.vpSightDate;
            var keep = !window_
                || (day >= window_.from && day <= window_.to);
            if (keep) {
                matched.push(row);
            }
            row.classList.add('d-none');
        });

        var limit = sight.expanded
            ? matched.length
            : Math.min(size, matched.length);
        matched.slice(0, limit).forEach(function (row) {
            row.classList.remove('d-none');
        });

        setText(list, '[data-vp-sight-in-range]', matched.length);
        setText(list, '[data-vp-sight-range-shown]', matched.length);
        setText(list, '[data-vp-sight-shown]', limit);
        setText(list, '[data-vp-sight-of]', matched.length);

        var note = list.querySelector('[data-vp-sight-range-note]');
        if (note) {
            // The note is the brush's, not the range select's: the
            // select says what it selected in its own label, and a note
            // repeating it would be noise on every page load.
            var narrowed = !!window_ && !window_.whole;
            note.classList.toggle('d-none', !narrowed);
            if (narrowed) {
                setText(note, '[data-vp-sight-range-from]', window_.from);
                setText(note, '[data-vp-sight-range-to]', window_.to);
            }
        }

        var more = list.querySelector('[data-vp-sight-more]');
        if (more) {
            more.hidden = limit >= matched.length;
        }

        // Only a brush can empty this list. A value with no sightings
        // has its own empty state from the template, and "none in this
        // range" over it would be a different and false claim.
        var empty = list.querySelector('[data-vp-sight-empty]');
        var rows = list.querySelector('[data-vp-sight-rows]');
        var blank = matched.length === 0;
        if (empty) {
            empty.classList.toggle('d-none', !blank);
        }
        if (rows) {
            rows.classList.toggle('d-none', blank);
        }
        var foot = list.querySelector('.vp-sight-foot');
        if (foot) {
            foot.classList.toggle('d-none', blank);
        }
    }

    /**
     * Redraw everything the chart panel owns, then hand the window to
     * the list.
     *
     * @param {Element} panel
     */
    function refreshSight(panel) {
        if (sight.main) {
            sight.main.refresh();
        }
        if (sight.nav) {
            sight.nav.refresh();
        }
        updateSightLegend(panel);
        paintSightBrush(panel);
        refreshSightList();
    }

    /**
     * Drag on the navigator strip. A drag that does not move is a
     * click, and a click clears the brush — the same gesture the
     * `Clear` button offers, for a reader who never found it.
     *
     * @param {Element} panel
     */
    function wireSightBrush(panel) {
        var strip = panel.querySelector('[data-vp-sight-brush]');
        if (!strip) {
            return;
        }
        var anchor = null;

        function bucketAt(event) {
            var box = strip.getBoundingClientRect();
            var count = sightRange().labels.length;
            var fraction = (event.clientX - box.left) / box.width;
            var index = Math.floor(fraction * count);
            return Math.max(0, Math.min(count - 1, index));
        }

        strip.addEventListener('pointerdown', function (event) {
            anchor = bucketAt(event);
            strip.setPointerCapture(event.pointerId);
            event.preventDefault();
        });

        strip.addEventListener('pointermove', function (event) {
            if (anchor === null) {
                return;
            }
            var to = bucketAt(event);
            sight.brush = {
                from: Math.min(anchor, to),
                to: Math.max(anchor, to),
            };
            sight.expanded = false;
            paintSightBrush(panel);
            refreshSightList();
        });

        strip.addEventListener('pointerup', function (event) {
            if (anchor !== null && bucketAt(event) === anchor) {
                sight.brush = null;
                sight.expanded = false;
                paintSightBrush(panel);
                refreshSightList();
            }
            anchor = null;
        });

        strip.addEventListener('pointercancel', function () {
            anchor = null;
        });
    }

    /**
     * @param {Element} root Either the whole page or a fragment
     */
    function initSightings(root) {
        var panel = (root || document).querySelector('[data-vp-sight]');
        if (panel) {
            var payload = panel.querySelector('[data-vp-sight-data]');
            if (!payload) {
                return;
            }
            sight.data = JSON.parse(payload.textContent);
            sight.rangeKey = sight.data['default'];
            sight.brush = null;
            sight.expanded = false;
            sight.shown = { sighting: true, fp: true, expiration: true };
            panel.querySelectorAll('[data-vp-sight-type]')
                .forEach(function (button) {
                    if (button.disabled) {
                        sight.shown[button.dataset.vpSightType] = false;
                    }
                });
            sight.main = window.VP.chart.boot('vp-sight-main', buildSightMain);
            sight.nav = window.VP.chart.boot('vp-sight-nav', buildSightNav);
            wireSightBrush(panel);
            updateSightLegend(panel);
            paintSightBrush(panel);
        }
        // The list can land before or after the chart, so it is caught
        // here either way: with the chart's state if there is one, and
        // with its own untouched rows if there is not yet.
        refreshSightList();
    }

    /**
     * @param {Event} event
     * @return {boolean} Whether the event was a sightings control
     */
    function onSightClick(event) {
        var panel = document.querySelector('[data-vp-sight]');

        var toggle = event.target.closest('[data-vp-sight-type]');
        if (toggle && !toggle.disabled && panel) {
            var key = toggle.dataset.vpSightType;
            sight.shown[key] = !sight.shown[key];
            toggle.setAttribute('aria-pressed', String(sight.shown[key]));
            refreshSight(panel);
            return true;
        }

        if (event.target.closest('[data-vp-sight-clear]')) {
            sight.brush = null;
            sight.expanded = false;
            if (panel) {
                paintSightBrush(panel);
            }
            refreshSightList();
            return true;
        }

        var more = event.target.closest('[data-vp-sight-more]');
        if (more) {
            sight.expanded = true;
            refreshSightList();
            return true;
        }

        return false;
    }

    /**
     * Enrichment tab
     * ------------------------------------------------------------
     * Everything here is a class change over markup the endpoint
     * already sent. That is not an implementation shortcut: running a
     * module spends quota and tells whoever operates it that you are
     * looking at this value, so picking one to read must not be
     * capable of querying anything. The tab's one behavioural
     * promise is that no request leaves the browser — on load, on tab
     * switch, or on selecting a module — and rendering every pane up
     * front is what makes that promise keepable rather than merely
     * intended.
     */

    /**
     * Swap which module the pane is showing.
     *
     * @param {Element} panel
     * @param {string} key Module name, or `__all` for the merged pane
     */
    function pickEnrichModule(panel, key) {
        panel.querySelectorAll('[data-vp-e-pane]').forEach(function (pane) {
            pane.classList.toggle('d-none', pane.dataset.vpEPane !== key);
        });
        panel.querySelectorAll('[data-vp-e-row]').forEach(function (row) {
            var on = row.dataset.vpERow === key;
            row.classList.toggle('vp-e-railrow-on', on);
            var body = row.querySelector('[data-vp-e-pick]');
            if (body) {
                body.setAttribute('aria-pressed', on ? 'true' : 'false');
            }
        });
    }

    /**
     * What the current selection would cost.
     *
     * Two chips rather than one, because quota is money and a third
     * party is disclosure and a reader may accept one and not the
     * other. Nothing is selected on arrival, and the resting line
     * says so rather than printing two zeroes.
     *
     * @param {Element} panel
     */
    function refreshEnrichTray(panel) {
        var boxes = panel.querySelectorAll('[data-vp-e-select]');
        var picked = 0;
        var quota = 0;
        var external = 0;

        boxes.forEach(function (box) {
            if (!box.checked) {
                return;
            }
            picked++;
            if (box.dataset.vpEQuota === '1') {
                quota++;
            }
            if (box.dataset.vpEExternal === '1') {
                external++;
            }
        });

        setText(panel, '[data-vp-e-picked]', picked);
        setText(panel, '[data-vp-e-runcount]', picked);
        setText(panel, '[data-vp-e-quota-n]', quota);
        setText(panel, '[data-vp-e-ext-n]', external);

        showEnrich(panel, '[data-vp-e-cost-quota]', quota > 0);
        showEnrich(panel, '[data-vp-e-cost-out]', external > 0);
        showEnrich(panel, '[data-vp-e-cost-none]', picked === 0);

        var all = panel.querySelector('[data-vp-e-select-all]');
        if (all) {
            all.checked = picked > 0 && picked === boxes.length;
            // Some but not all is its own state, and a box that reads
            // "unchecked" over six ticked rows is a lie about them.
            all.indeterminate = picked > 0 && picked < boxes.length;
        }
    }

    /**
     * @param {Element} root
     * @param {string} selector
     * @param {boolean} visible
     */
    function showEnrich(root, selector, visible) {
        var target = root.querySelector(selector);
        if (target) {
            target.classList.toggle('d-none', !visible);
        }
    }

    /**
     * Narrow a pane to what this run brought back that the last one
     * did not.
     *
     * @param {Element} button
     */
    function toggleEnrichNew(button) {
        var pane = button.closest('[data-vp-e-pane]');
        if (!pane) {
            return;
        }
        var on = button.getAttribute('aria-pressed') !== 'true';
        button.setAttribute('aria-pressed', on ? 'true' : 'false');
        button.classList.toggle('active', on);

        var shown = 0;
        pane.querySelectorAll('[data-vp-e-item]').forEach(function (item) {
            var hide = on && !item.hasAttribute('data-vp-e-new');
            item.classList.toggle('d-none', hide);
            if (!hide) {
                shown++;
            }
        });

        // Only when the filter produced the emptiness. A pane that had
        // nothing to begin with keeps its own wording.
        showEnrich(pane, '[data-vp-e-empty]', shown === 0);
    }

    /**
     * Fold an object's relations away, or open an element's
     * provenance.
     *
     * @param {Element} button
     */
    function toggleEnrichDisc(button) {
        var item = button.closest('[data-vp-e-item]');
        if (!item) {
            return;
        }
        var fold = item.querySelector('[data-vp-e-fold]');
        if (!fold) {
            return;
        }
        var open = button.getAttribute('aria-expanded') !== 'true';
        button.setAttribute('aria-expanded', open ? 'true' : 'false');
        fold.classList.toggle('d-none', !open);
    }

    /**
     * @param {Element} root
     */
    function initEnrichment(root) {
        var panels = root.querySelectorAll
            ? root.querySelectorAll('[data-vp-enrich]')
            : [];
        panels.forEach(function (panel) {
            refreshEnrichTray(panel);
        });
    }

    /**
     * @param {Event} event
     * @return {boolean} Whether the click belonged to this tab
     */
    function onEnrichClick(event) {
        var pick = event.target.closest('[data-vp-e-pick]');
        if (pick) {
            var panel = pick.closest('[data-vp-enrich]');
            if (panel) {
                pickEnrichModule(panel, pick.dataset.vpEPick);
            }
            return true;
        }

        var disc = event.target.closest('[data-vp-e-disc]');
        if (disc) {
            toggleEnrichDisc(disc);
            return true;
        }

        var onlyNew = event.target.closest('[data-vp-e-only-new]');
        if (onlyNew) {
            toggleEnrichNew(onlyNew);
            return true;
        }

        return false;
    }

    function init() {
        if (!onValuePage()) {
            return;
        }

        markDisabled(document);

        document.addEventListener('click', function (event) {
            if (!event.target.closest) {
                return;
            }
            if (event.target.closest('a.disabled, a[aria-disabled="true"]')) {
                event.preventDefault();
                event.stopPropagation();
            }
        }, true);

        document.addEventListener('click', function (event) {
            if (!event.target.closest) {
                return;
            }
            var chip = event.target.closest('.vp-type-chip');
            if (chip) {
                toggleTypeFilter(chip);
                return;
            }
            if (event.target.closest('[data-vp-filter-clear]')) {
                var active = document.querySelector('.vp-type-chip.active');
                if (active) {
                    toggleTypeFilter(active);
                }
                return;
            }

            var page = event.target.closest('[data-vp-page]');
            if (page && !page.disabled) {
                var pageList = page.closest('[data-vp-list]');
                if (pageList) {
                    listPages.set(pageList, parseInt(page.dataset.vpPage, 10));
                    refreshList(pageList);
                }
                return;
            }

            if (onSightClick(event)) {
                return;
            }

            if (onEnrichClick(event)) {
                return;
            }

            var more = event.target.closest('[data-vp-facet-more]');
            if (more) {
                expandFacetGroup(more);
                return;
            }

            var clearAll = event.target.closest('[data-vp-facet-clear]');
            if (clearAll && !clearAll.disabled) {
                var clearList = clearAll.closest('[data-vp-list]');
                if (clearList) {
                    clearListFilters(clearList);
                    listPages.set(clearList, 1);
                    refreshList(clearList);
                }
            }
        });

        document.addEventListener('change', function (event) {
            if (event.target.id === 'vp-occ-deleted-toggle') {
                refreshOccurrences();
            }

            if (event.target.matches
                && event.target.matches('[data-vp-e-select-all]')) {
                var allPanel = event.target.closest('[data-vp-enrich]');
                if (allPanel) {
                    allPanel
                        .querySelectorAll('[data-vp-e-select]')
                        .forEach(function (box) {
                            box.checked = event.target.checked;
                        });
                    refreshEnrichTray(allPanel);
                }
                return;
            }

            if (event.target.matches
                && event.target.matches('[data-vp-e-select]')) {
                var enrichPanel = event.target.closest('[data-vp-enrich]');
                if (enrichPanel) {
                    refreshEnrichTray(enrichPanel);
                }
                return;
            }

            if (event.target.matches && event.target.matches('[data-vp-col]')) {
                toggleColumn(event.target);
            }

            if (event.target.matches
                && event.target.matches('[data-vp-sight-range]')) {
                // A wider window is a different set of buckets, so a
                // brush drawn over the old ones no longer points at
                // anything the reader chose.
                sight.rangeKey = event.target.value;
                sight.brush = null;
                sight.expanded = false;
                var sightPanel = document.querySelector('[data-vp-sight]');
                if (sightPanel) {
                    refreshSight(sightPanel);
                }
            }

            if (event.target.classList
                && event.target.classList.contains('item-checkbox')) {
                var selectionList = event.target.closest('[data-vp-list]');
                if (selectionList) {
                    refreshBulkScope(selectionList);
                }
            }

            if (event.target.matches
                && event.target.matches('[data-vp-group]')) {
                switchGroup(event.target);
                return;
            }

            if (event.target.matches
                && event.target.matches('[data-vp-sort]')) {
                var sortList = event.target.closest('[data-vp-list]');
                if (sortList) {
                    // A reorder does not change how many rows there
                    // are, but page three of a new order is not the
                    // rows the reader was looking at either.
                    listPages.set(sortList, 1);
                    refreshList(sortList);
                }
                return;
            }

            if (event.target.matches
                && event.target.matches(
                    '[data-vp-rel-select], [data-vp-rel-select-all]'
                )) {
                var pickList = event.target.closest('[data-vp-list]');
                if (pickList) {
                    if (event.target.matches('[data-vp-rel-select-all]')) {
                        // Only what is on screen: ticking the header
                        // box over a filtered table must not quietly
                        // select rows the filter has taken away.
                        pickList
                            .querySelectorAll('tr:not(.d-none) '
                                + '[data-vp-rel-select]')
                            .forEach(function (box) {
                                box.checked = event.target.checked;
                            });
                    }
                    refreshSelection(pickList);
                }
                return;
            }

            // Narrowing a list changes how many pages it has, so any
            // facet, select, threshold or reveal switch sends the
            // reader back to page one rather than to a page that may no
            // longer exist.
            if (event.target.matches
                && event.target.matches(
                    '[data-vp-facet-key], [data-vp-reveal],'
                    + ' [data-vp-filter-key], [data-vp-filter-min]'
                )) {
                var list = event.target.closest('[data-vp-list]');
                if (list) {
                    listPages.set(list, 1);
                    refreshList(list);
                }
            }
        });

        document.addEventListener('input', function (event) {
            if (!event.target.matches) {
                return;
            }
            if (event.target.matches('[data-vp-facet-search]')) {
                filterFacetGroup(event.target);
                return;
            }
            if (event.target.matches(
                '[data-vp-filter-text], [data-vp-filter-min]'
            )) {
                var typedList = event.target.closest('[data-vp-list]');
                if (typedList) {
                    listPages.set(typedList, 1);
                    refreshList(typedList);
                }
            }
        });

        // Panels arrive after load, one fetch each, so the state the page
        // is holding has to be applied to each one as it lands.
        document.addEventListener('misp:container-loaded', function (event) {
            markDisabled(event.target);
            refreshOccurrences();
            refreshAllLists(event.target);
            initSightings(event.target);
            initEnrichment(event.target);
        });

        refreshOccurrences();
        refreshAllLists(document);
        initSightings(document);
        initEnrichment(document);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
}());
