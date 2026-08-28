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
     *     tr[data-vp-time]           the row's own `YmdHi` digits
     *     [data-vp-filter-from|-to]  period bounds against that time
     *     tr[data-vp-times]          `key:YmdHi` pairs, for a panel that
     *                                cuts on more than one date
     *     [data-vp-range-from|-to]   bounds against one of those keys,
     *                                named by the attribute's value
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
     *
     * Lists nest. A `[data-vp-list]` inside another owns its rows, its
     * pager, its range and its narrowing controls, and the outer one
     * leaves them alone — the Relationships tab's object-siblings
     * section pages and narrows separately from the ranked table under
     * it. Reading a control therefore goes through `ownNodes` for the
     * same reason paging does: an unscoped query would let the ranked
     * table's facet bar filter rows it does not describe, and its
     * `Reset` clear ticks the reader never made there.
     * ============================================================== */

    // Current page per list, keyed by the element so several lists on
    // one page keep their own place.
    var listPages = new WeakMap();

    /**
     * The nodes a list owns, which is not the same as the nodes inside
     * it. A panel may hold a second `[data-vp-list]` — the
     * Relationships tab's object-siblings section pages independently
     * of the ranked table below it — and an unscoped query would let
     * the outer control page the inner section's rows and print a
     * range that belongs to neither.
     *
     * @param {Element} list
     * @param {string} selector
     * @return {Array<Element>}
     */
    function ownNodes(list, selector) {
        return ownedBy(list, list.querySelectorAll(selector));
    }

    /**
     * @param {Element} list
     * @param {NodeList} nodes
     * @return {Array<Element>}
     */
    function ownedBy(list, nodes) {
        return Array.prototype.slice.call(nodes).filter(function (node) {
            return node.closest('[data-vp-list]') === list;
        });
    }

    /**
     * @param {Element} list
     * @param {string} selector
     * @return {?Element}
     */
    function ownNode(list, selector) {
        return ownNodes(list, selector)[0] || null;
    }

    /**
     * @param {Element} list
     * @param {string} selector
     * @param {number|string} value
     */
    function setOwnText(list, selector, value) {
        var target = ownNode(list, selector);
        if (target) {
            target.textContent = value;
        }
    }

    /**
     * @param {Element} list
     * @return {Array<Element>}
     */
    function listRows(list) {
        var host = ownNode(list, '[data-vp-list-rows]') || list;
        var explicit = ownedBy(
            list,
            host.querySelectorAll('[data-vp-list-row]')
        );
        if (explicit.length) {
            return explicit;
        }
        return ownedBy(list, host.querySelectorAll('tbody tr'));
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
        ownNodes(list, 'input[data-vp-facet-key]').forEach(function (box) {
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
     * The period bounds, as the same `YmdHi` integer the rows carry.
     *
     * Digits of the printed wall clock rather than epochs: a row's time
     * is rendered server-side, so comparing epochs would hand a reader
     * in another timezone a different set of rows for the period they
     * picked than the times on those rows say it holds.
     *
     * @param {Element} list
     * @return {{from: number|null, to: number|null}}
     */
    function activePeriod(list) {
        return {
            from: periodBound(list, '[data-vp-filter-from]', '0000'),
            to: periodBound(list, '[data-vp-filter-to]', '2359')
        };
    }

    /**
     * The same thing again, but named, for a panel that filters on more
     * than one date at once — the Occurrences rail cuts on when an
     * attribute was last modified *and* on when its event was published,
     * and those are two independent questions about one row.
     *
     * Named rather than a second unnamed pair because there is no limit
     * on how many dates a row can carry, and separate from `activePeriod`
     * rather than a generalisation of it because the unnamed period has a
     * caller already (`value_history`'s audit rail) whose behaviour must
     * not change to add this.
     *
     * @param {Element} list
     * @return {Object} key => {from, to}
     */
    function activeRanges(list) {
        var active = {};
        var read = function (selector, edge, fill) {
            list.querySelectorAll(selector).forEach(function (input) {
                var key = input.dataset[
                    edge === 'from' ? 'vpRangeFrom' : 'vpRangeTo'
                ];
                if (!key) {
                    return;
                }
                var at = boundDigits(input.value, fill);
                if (at === null) {
                    return;
                }
                if (!active[key]) {
                    active[key] = {from: null, to: null};
                }
                active[key][edge] = at;
            });
        };
        read('[data-vp-range-from]', 'from', '0000');
        read('[data-vp-range-to]', 'to', '2359');
        return active;
    }

    /**
     * @param {Element} list
     * @param {string} selector
     * @param {string} fill Clock digits for a bound given as a date
     * @return {number|null}
     */
    function periodBound(list, selector, fill) {
        var input = list.querySelector(selector);
        return input ? boundDigits(input.value, fill) : null;
    }

    /**
     * One date or datetime control's value as the `YmdHi` integer the
     * rows carry, or null when it places no bound. A `type="date"` input
     * gives eight digits and is filled out to the start or end of that
     * day, so a one-day range holds the whole day.
     *
     * @param {string} value
     * @param {string} fill Clock digits for a bound given as a date
     * @return {number|null}
     */
    function boundDigits(value, fill) {
        if (value === '' || value === undefined || value === null) {
            return null;
        }
        var digits = String(value).replace(/\D/g, '');
        if (digits.length < 8) {
            return null;
        }
        if (digits.length < 12) {
            digits = digits.slice(0, 8) + fill;
        }
        return parseInt(digits.slice(0, 12), 10);
    }

    /**
     * A row carrying no time is dropped once a bound is set, for the
     * reason a thresholded row without a number is: the control is
     * asking something that row cannot answer, and keeping it would
     * make the period look like it did nothing.
     *
     * @param {Element} row
     * @param {Object} period
     * @return {boolean}
     */
    function rowMatchesPeriod(row, period) {
        if (period.from === null && period.to === null) {
            return true;
        }
        var at = parseInt(row.dataset.vpTime || '', 10);
        if (isNaN(at)) {
            return false;
        }
        return (period.from === null || at >= period.from)
            && (period.to === null || at <= period.to);
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
        var period = activePeriod(list);
        var ranges = activeRanges(list);
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
                    && rowMatchesPeriod(row, period)
                    && rowMatchesRanges(row, ranges)
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
            + (period.from === null ? 0 : 1)
            + (period.to === null ? 0 : 1)
            + Object.keys(ranges).reduce(function (sum, key) {
                return sum + (ranges[key].from === null ? 0 : 1)
                    + (ranges[key].to === null ? 0 : 1);
            }, 0)
            + (text === '' ? 0 : 1);

        sortRows(list, filtered);
        // The History tab pages inside each section instead of over
        // the union of them; everything before this point is shared.
        if (list.hasAttribute('data-vp-audit')) {
            paginateAuditSections(list, filtered, activeCount);
            // Both read the period rather than the filtered set, so
            // they run here and not inside the pager: the brush shows
            // where the period is and the rail counts what is in it,
            // whatever else is ticked.
            retallyAuditFacets(list, period);
            paintAuditBrush(list);
        } else {
            paginate(list, filtered);
        }
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
     * A row against every named date range that is set.
     *
     * A row carrying no date under a key the reader has cut on is
     * dropped, not kept — the same call `rowMatchesPeriod` makes, and for
     * the same reason: "I do not know when this happened" is not evidence
     * that it happened inside the window. How many rows that is belongs
     * beside the control, which is why the rail counts them.
     *
     * @param {Element} row
     * @param {Object} ranges key => {from, to}
     * @return {boolean}
     */
    function rowMatchesRanges(row, ranges) {
        return Object.keys(ranges).every(function (key) {
            var range = ranges[key];
            if (range.from === null && range.to === null) {
                return true;
            }
            var at = rowTime(row, key);
            if (at === null) {
                return false;
            }
            return (range.from === null || at >= range.from)
                && (range.to === null || at <= range.to);
        });
    }

    /**
     * @param {Element} row
     * @param {string} key
     * @return {number|null} `YmdHi`
     */
    function rowTime(row, key) {
        var match = (row.dataset.vpTimes || '')
            .match(new RegExp('(?:^|\\s)' + key + ':(\\d{12})'));
        return match ? parseInt(match[1], 10) : null;
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
        if (filtered.length < 2) {
            return;
        }
        /*
         * A column heading the reader has clicked wins over the panel's
         * own select, and there is no panel with both.
         *
         * `vp-sorted-col` on the list, not `vp-sort-col`: the headings
         * carry the latter, and writing the state under the same name
         * would put it on the list element too — where a
         * `[data-vp-sort-col="x"]` lookup finds the container before the
         * button it was looking for.
         */
        if (list.dataset.vpSortedCol) {
            sortByColumn(list, filtered, list.dataset.vpSortedCol,
                list.dataset.vpSortedDir === 'desc' ? -1 : 1);
            return;
        }
        /*
         * No column sort. A panel that also carries a select is asking
         * for that select's order — the Relationships pane offers both,
         * and reading the columns first would leave `Most recent first`
         * set and doing nothing the moment its headings became
         * clickable.
         */
        var select = ownNode(list, '[data-vp-sort]');
        /*
         * No select either, but a table that offers column sorting has
         * already had its rows moved by an earlier click — reordering is
         * destructive, so "unsorted" has to be restored rather than
         * merely stopped. Each row carries its server position for
         * exactly this.
         */
        if (!select) {
            if (ownNode(list, '[data-vp-sort-col]')) {
                sortByColumn(list, filtered, 'default', 1);
            }
            return;
        }
        var key = select.value;
        var ordered = filtered.slice().sort(function (a, b) {
            var diff = (rowNumber(b, key) || 0) - (rowNumber(a, key) || 0);
            if (diff !== 0) {
                return diff;
            }
            /*
             * Ties fall back to the order the model sent, which makes
             * this a total order rather than merely a stable one. Once
             * the headings sort, that is what lets the third click put
             * the table back: most of the ranked table shares one
             * weight, and a stable sort over equal keys would keep
             * whatever order the previous click left behind and call it
             * the default. Rows with no position tie as they did.
             */
            var x = a.dataset.vpSortDefault || '';
            var y = b.dataset.vpSortDefault || '';
            return x === y ? 0 : (x < y ? -1 : 1);
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
     * Order rows by the column the reader clicked.
     *
     * Compares `data-vp-sort-<column>`, which the template builds to sort
     * lexicographically — zero-padded numbers, `YmdHi` dates, lowercased
     * text — so one comparison serves every column and the script needs
     * no knowledge of what any of them holds.
     *
     * **An empty token sorts last in both directions.** It means the row
     * has no value for that column, and "no last-seen date" is not
     * earlier than every date; putting it at the top of an ascending sort
     * would bury the rows the reader asked to see.
     *
     * @param {Element} list
     * @param {Array<Element>} filtered
     * @param {string} column Column key, or `default` for server order
     * @param {number} sign 1 ascending, -1 descending
     */
    function sortByColumn(list, filtered, column, sign) {
        var key = 'vpSort' + column.replace(
            /-([a-z])/g,
            function (m, c) { return c.toUpperCase(); }
        ).replace(/^([a-z])/, function (m, c) { return c.toUpperCase(); });
        var ordered = filtered.slice().sort(function (a, b) {
            var x = a.dataset[key] || '';
            var y = b.dataset[key] || '';
            if (x === y) {
                return 0;
            }
            if (x === '') {
                return 1;
            }
            if (y === '') {
                return -1;
            }
            return x < y ? -sign : sign;
        });
        ordered.forEach(function (row) {
            if (row.parentNode) {
                row.parentNode.appendChild(row);
            }
        });
        filtered.length = 0;
        Array.prototype.push.apply(filtered, ordered);
    }

    /**
     * Cycle one column heading: ascending, descending, then back to the
     * order the server sent.
     *
     * Three states rather than the two MISP's paginated headings offer,
     * because this table's default order is itself meaningful — most
     * recently modified first — and `Attribute.timestamp` is not one of
     * the twelve columns, so without a way back the reader could not
     * return to it.
     *
     * @param {Element} button A [data-vp-sort-col]
     */
    function toggleColumnSort(button) {
        var list = button.closest('[data-vp-list], [data-vp-sight-list]');
        if (!list) {
            return;
        }
        var column = button.dataset.vpSortCol;
        if (list.dataset.vpSortedCol !== column) {
            list.dataset.vpSortedCol = column;
            list.dataset.vpSortedDir = 'asc';
        } else if (list.dataset.vpSortedDir === 'asc') {
            list.dataset.vpSortedDir = 'desc';
        } else {
            delete list.dataset.vpSortedCol;
            delete list.dataset.vpSortedDir;
        }
        markSortedColumn(list);
        /*
         * The sightings list is not a faceted list: it pages off `load
         * the rest` rather than a page control, and its rows are chosen
         * by the chart's brush. The state above and the comparison below
         * it are the same; only who redraws differs.
         *
         * Nothing to reset there, either — unexpanded it shows the first
         * ten of whatever order is current, which after a reorder is
         * exactly what the reader asked for.
         */
        if (list.hasAttribute('data-vp-sight-list')) {
            refreshSightList();
            return;
        }
        // A reorder does not change how many rows there are, but page
        // three of a new order is not the rows the reader was looking at.
        listPages.set(list, 1);
        refreshList(list);
    }

    /**
     * `aria-sort` on the sorted heading and nowhere else — the attribute
     * a screen reader announces, and the hook the caret styling keys off,
     * so there is one source of truth for which column is ordered.
     *
     * @param {Element} list
     */
    function markSortedColumn(list) {
        var column = list.dataset.vpSortedCol || null;
        var direction = list.dataset.vpSortedDir === 'desc'
            ? 'descending'
            : 'ascending';
        // Scoped: the sibling section's headings belong to its own list,
        // and the ranked table clearing them would take the caret off a
        // column the reader had just sorted.
        ownNodes(list, '[data-vp-sort-col]').forEach(function (button) {
            var cell = button.closest('th');
            if (!cell) {
                return;
            }
            if (button.dataset.vpSortCol === column) {
                cell.setAttribute('aria-sort', direction);
            } else {
                cell.removeAttribute('aria-sort');
            }
        });
    }

    /* ==================================================================
     * The occurrence rail's time brushes
     * ------------------------------------------------------------------
     * Two small brushable strips — one per date the rail cuts on —
     * sharing the brush primitive with the History chart and the
     * Sightings navigator. What they do not share is a canvas: these are
     * CSS bars a third the height of History's chart, because they sit
     * in a `col-lg-3` rail beside eight other facet groups.
     *
     * The gesture writes the two date inputs and lets their own `change`
     * do the filtering, so there is one filter path whether the reader
     * brushed or typed — the same decision the History chart made, for
     * the same reason.
     *
     * Bounds are applied on `settle` rather than on every pointer move.
     * History repaints its own chart as the drag goes; here a move would
     * re-filter and repage up to three hundred rows, sixty times a
     * second. The window still paints live; only the filter waits for
     * the pointer to come up.
     * ================================================================== */

    // Pending selection per strip while a drag is in flight.
    var timeBrushDrag = new WeakMap();

    /**
     * @param {Element|Document} root
     */
    function initTimeBrushes(root) {
        (root || document).querySelectorAll('[data-vp-timebrush]')
            .forEach(initTimeBrush);
    }

    /**
     * @param {Element} strip A [data-vp-timebrush]
     * @return {Array<Element>} Its bars, in order
     */
    function timeBrushBars(strip) {
        return Array.prototype.slice.call(
            strip.querySelectorAll('[data-vp-bucket-from]')
        );
    }

    /**
     * Wire one strip. Drag picks a range, click clears it.
     *
     * @param {Element} strip A [data-vp-timebrush]
     */
    function initTimeBrush(strip) {
        if (strip.dataset.vpTimebrushReady) {
            return;
        }
        strip.dataset.vpTimebrushReady = '1';
        var key = strip.dataset.vpTimebrush;

        window.VP.brush.attach(strip.querySelector('[data-vp-brush]'), {
            count: function () {
                return timeBrushBars(strip).length;
            },
            range: function (from, to) {
                timeBrushDrag.set(strip, { from: from, to: to });
                window.VP.brush.paint(
                    strip,
                    { from: from, to: to },
                    timeBrushBars(strip).length
                );
                captionBucket(strip, from, to);
            },
            settle: function () {
                var bounds = timeBrushDrag.get(strip);
                if (bounds) {
                    writeTimeBrush(strip, key, bounds.from, bounds.to);
                }
                timeBrushDrag.delete(strip);
            },
            clear: function () {
                timeBrushDrag.delete(strip);
                clearTimeBrush(strip, key);
            },
        });

        /*
         * A three-pixel bar is not self-describing, and the brush layer
         * covers the bars so their own `title` never reaches the reader.
         * The caption under the inputs names whatever is under the
         * pointer instead, and goes back to stating the grain on the way
         * out.
         */
        strip.addEventListener('pointermove', function (event) {
            if (timeBrushDrag.has(strip)) {
                return;
            }
            var bars = timeBrushBars(strip);
            if (!bars.length) {
                return;
            }
            var box = strip.getBoundingClientRect();
            var at = Math.floor(
                ((event.clientX - box.left) / box.width) * bars.length
            );
            at = Math.max(0, Math.min(bars.length - 1, at));
            captionBucket(strip, at, at);
        });

        strip.addEventListener('pointerleave', function () {
            if (!timeBrushDrag.has(strip)) {
                captionDefault(strip);
            }
        });
    }

    /**
     * @param {Element} strip
     * @return {Element|null} The strip's caption
     */
    function timeBrushCaption(strip) {
        var list = strip.closest('[data-vp-list]') || document;
        return list.querySelector(
            '[data-vp-timebrush-caption="' + strip.dataset.vpTimebrush + '"]'
        );
    }

    /**
     * Name the bucket, or the span of buckets, under the pointer.
     *
     * @param {Element} strip
     * @param {number} from Bar index
     * @param {number} to Bar index
     */
    function captionBucket(strip, from, to) {
        var caption = timeBrushCaption(strip);
        var bars = timeBrushBars(strip);
        if (!caption || !bars[from] || !bars[to]) {
            return;
        }
        var total = 0;
        for (var i = from; i <= to; i++) {
            total += parseInt(bars[i].dataset.vpBucketCount, 10) || 0;
        }
        var span = from === to
            ? bars[from].dataset.vpBucketLabel
            : bars[from].dataset.vpBucketLabel + ' – '
                + bars[to].dataset.vpBucketLabel;
        caption.textContent = span + ' · ' + total;
    }

    /**
     * @param {Element} strip
     */
    function captionDefault(strip) {
        var caption = timeBrushCaption(strip);
        if (caption) {
            caption.textContent = caption.dataset.vpCaptionDefault || '';
        }
    }

    /**
     * Write a brushed range into the strip's two date inputs.
     *
     * @param {Element} strip
     * @param {string} key The range's name
     * @param {number} from Bar index
     * @param {number} to Bar index
     */
    function writeTimeBrush(strip, key, from, to) {
        var list = strip.closest('[data-vp-list]');
        var bars = timeBrushBars(strip);
        if (!list || !bars[from] || !bars[to]) {
            return;
        }
        var pairs = [
            ['[data-vp-range-from="' + key + '"]',
                bars[from].dataset.vpBucketFrom],
            ['[data-vp-range-to="' + key + '"]',
                bars[to].dataset.vpBucketTo],
        ];
        pairs.forEach(function (pair) {
            var input = list.querySelector(pair[0]);
            if (input) {
                input.value = pair[1];
            }
        });
        listPages.set(list, 1);
        refreshList(list);
    }

    /**
     * @param {Element} strip
     * @param {string} key
     */
    function clearTimeBrush(strip, key) {
        var list = strip.closest('[data-vp-list]');
        if (!list) {
            return;
        }
        list.querySelectorAll(
            '[data-vp-range-from="' + key + '"],'
            + ' [data-vp-range-to="' + key + '"]'
        ).forEach(function (input) {
            input.value = '';
        });
        listPages.set(list, 1);
        refreshList(list);
    }

    /**
     * Paint every strip in a list from what its inputs currently say, so
     * the window follows a typed date and a cleared one as well as a
     * brushed one. The bounds are the buckets the dates fall in, which
     * is the coarsest honest reading of a date the strip can draw.
     *
     * @param {Element} list
     */
    function paintTimeBrushes(list) {
        list.querySelectorAll('[data-vp-timebrush]').forEach(
            function (strip) {
                var key = strip.dataset.vpTimebrush;
                var bars = timeBrushBars(strip);
                if (!bars.length) {
                    return;
                }
                var from = list.querySelector(
                    '[data-vp-range-from="' + key + '"]'
                );
                var to = list.querySelector(
                    '[data-vp-range-to="' + key + '"]'
                );
                var lower = from && from.value ? from.value : null;
                var upper = to && to.value ? to.value : null;
                if (lower === null && upper === null) {
                    window.VP.brush.paint(strip, null, bars.length);
                    captionDefault(strip);
                    return;
                }
                var first = bars.length - 1;
                var last = 0;
                bars.forEach(function (bar, index) {
                    var start = bar.dataset.vpBucketFrom;
                    var stop = bar.dataset.vpBucketTo;
                    // Any overlap between the bucket and the window.
                    if ((upper === null || start <= upper)
                        && (lower === null || stop >= lower)
                    ) {
                        first = Math.min(first, index);
                        last = Math.max(last, index);
                    }
                });
                window.VP.brush.paint(
                    strip,
                    first <= last ? { from: first, to: last } : null,
                    bars.length
                );
            }
        );
    }

    /**
     * Repage what is already on screen at a size the reader picked.
     *
     * @param {Element} select A [data-vp-page-size-pick]
     */
    function changePageSize(select) {
        var list = select.closest('[data-vp-list]');
        if (!list) {
            return;
        }
        var pager = ownNode(list, '[data-vp-pager]');
        if (!pager) {
            return;
        }
        pager.dataset.vpPageSize = select.value;
        // Page four of sixty-row pages is not page four of twenty-five.
        listPages.set(list, 1);
        refreshList(list);
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
     * Scoped to the controls this list owns. `Reset` under the ranked
     * table must not silently untick the sibling section's facets: the
     * two sections narrow different row sets, and a reader who cleared
     * one has said nothing about the other.
     *
     * @param {Element} list
     */
    function clearListFilters(list) {
        ownNodes(list, 'input[data-vp-facet-key]:checked')
            .forEach(function (box) {
                box.checked = false;
            });
        ownNodes(list, 'select[data-vp-filter-key]')
            .forEach(function (select) {
                select.value = '';
            });
        ownNodes(list, '[data-vp-filter-text]')
            .forEach(function (input) {
                input.value = '';
            });
        ownNodes(list, '[data-vp-filter-min]')
            .forEach(function (input) {
                input.value = input.min === '' ? '0' : input.min;
            });
        ownNodes(list, '[data-vp-filter-from], [data-vp-filter-to],'
            + ' [data-vp-range-from], [data-vp-range-to]')
            .forEach(function (input) {
                input.value = '';
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
        var pager = ownNode(list, '[data-vp-pager]');
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
        setOwnText(list, '[data-vp-page-from]', from);
        setOwnText(list, '[data-vp-page-to]', to);
        setOwnText(list, '[data-vp-page-of]', of);
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
        setOwnText(list, '[data-vp-list-shown]', shown);
        setOwnText(list, '[data-vp-facet-rows]', shown);
        setOwnText(list, '[data-vp-facet-count-active]', activeCount);

        var summary = ownNode(list, '[data-vp-facet-summary]');
        if (summary) {
            summary.classList.toggle('vp-facet-summary-on', activeCount > 0);
        }

        var clear = ownNode(list, '[data-vp-facet-clear]');
        if (clear) {
            clear.disabled = activeCount === 0;
        }

        var empty = ownNode(list, '[data-vp-list-empty]');
        // Only a filter can produce this: a list that was empty to
        // begin with has its own empty state from the template, and
        // saying "no rows match" over it would be a different claim.
        var blank = activeCount > 0 && shown === 0;
        if (empty) {
            empty.classList.toggle('d-none', !blank);
        }
        var rows = ownNode(list, '[data-vp-list-rows]');
        if (rows && empty) {
            rows.classList.toggle('d-none', blank);
        }
        // The brush windows are a second reading of the same two dates,
        // so they are repainted wherever those can have changed —
        // brushed, typed or cleared — rather than only where they were.
        if (ownNode(list, '[data-vp-timebrush]')) {
            paintTimeBrushes(list);
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
            /*
             * A fragment that is re-fetched brings a new canvas under
             * the same id, and the previous boot's theme observer is
             * still watching it. Whatever is attached to the element
             * goes first, or Chart.js refuses the second instance.
             */
            if (typeof Chart.getChart === 'function') {
                var attached = Chart.getChart(el);
                if (attached) {
                    attached.destroy();
                }
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
     * The brush
     * ------------------------------------------------------------------
     * Drag a range over an activity chart. Three tabs offer that
     * gesture and each shipped its own copy of it: the Sightings
     * navigator hides table rows, the Timeline spine moves the window
     * two regions read, and the History months write the two date
     * inputs. What differs between them is what a range *means*, which
     * is the callback. What does not is the pointer arithmetic, the
     * clamping and the two masks.
     *
     * The bucket unit is deliberately not this layer's business. A bar
     * is a day, a week or a month depending on what the caller's data
     * can honestly claim — that decision belongs to
     * `ValueProfileBuckets`, and the drag is index arithmetic whichever
     * way it goes. The unit does decide how many buckets there are,
     * which is why the count is asked for on every gesture rather than
     * captured when the brush is wired: the Sightings range select
     * changes it underneath.
     * ================================================================== */

    /*
     * Travel, in pixels, under which a drag is a click.
     *
     * Two of the three shipped a different rule — a click was the
     * pointer coming up on the bucket it went down on — and this one
     * replaces it rather than averaging with it. That rule makes a
     * one-bucket range unselectable, because releasing inside the
     * bucket you pressed always clears; on the History tab's monthly
     * chart it meant two months was the finest period anyone could
     * brush, which is not a control.
     */
    var BRUSH_CLICK_SLOP = 4;

    /**
     * Move the masks and the window over the buckets `bounds` covers.
     *
     * A `bounds` of null is the case phase 21 made routine: the range
     * the caller has selected lies outside the span the chart is
     * showing. Everything in view is then outside the selection, so
     * everything in view is dimmed and no window is drawn — a window
     * pinned to whichever edge the selection lay beyond would read as a
     * selection at that edge, which is a lie about a chart the reader
     * has just zoomed somewhere else.
     *
     * @param {Element} root Anything containing the brush's parts
     * @param {{from: number, to: number}|null} bounds Bucket indices
     * @param {number} count How many buckets the chart has
     */
    function paintBrush(root, bounds, count) {
        var strip = root.matches && root.matches('[data-vp-brush]')
            ? root
            : root.querySelector('[data-vp-brush]');
        if (strip) {
            strip.classList.toggle('vp-brush-empty', bounds === null);
        }
        if (bounds === null) {
            bounds = { from: count, to: count - 1 };
        }
        var left = (100 * bounds.from) / count;
        var right = (100 * (count - 1 - bounds.to)) / count;
        var parts = [
            ['[data-vp-brush-mask-left]', { width: left + '%' }],
            ['[data-vp-brush-mask-right]', { width: right + '%' }],
            ['[data-vp-brush-handle]', {
                left: left + '%',
                right: right + '%',
            }],
        ];
        parts.forEach(function (part) {
            var el = root.querySelector(part[0]);
            if (!el) {
                return;
            }
            Object.keys(part[1]).forEach(function (property) {
                el.style[property] = part[1][property];
            });
        });
    }

    /**
     * @param {Element|null} strip The layer the drag is read off
     * @param {Object} on `count()` returns how many buckets there are;
     *     `range(from, to)` is called for every pointer move of a real
     *     drag; `clear()` for a click; `settle()` optionally once on
     *     release, for a caller whose answer to a range is too
     *     expensive to give per move — History's re-fetch is one.
     */
    function attachBrush(strip, on) {
        if (!strip) {
            return;
        }
        var anchor = null;
        var origin = 0;
        var moved = false;

        function bucketAt(event) {
            var box = strip.getBoundingClientRect();
            var count = on.count();
            var fraction = (event.clientX - box.left) / box.width;
            var index = Math.floor(fraction * count);
            return Math.max(0, Math.min(count - 1, index));
        }

        strip.addEventListener('pointerdown', function (event) {
            if (on.count() < 1) {
                return;
            }
            anchor = bucketAt(event);
            origin = event.clientX;
            moved = false;
            strip.setPointerCapture(event.pointerId);
            event.preventDefault();
        });

        strip.addEventListener('pointermove', function (event) {
            if (anchor === null) {
                return;
            }
            if (Math.abs(event.clientX - origin) >= BRUSH_CLICK_SLOP) {
                moved = true;
            }
            var to = bucketAt(event);
            on.range(Math.min(anchor, to), Math.max(anchor, to));
        });

        strip.addEventListener('pointerup', function () {
            if (anchor === null) {
                return;
            }
            anchor = null;
            if (!moved) {
                on.clear();
            } else if (on.settle) {
                on.settle();
            }
        });

        strip.addEventListener('pointercancel', function () {
            anchor = null;
        });
    }

    window.VP.brush = { attach: attachBrush, paint: paintBrush };

    /* ==================================================================
     * The zoom
     * ------------------------------------------------------------------
     * Narrow what the chart shows, which is a different question from
     * the brush's *what do I want to filter by* (§13.3). Both gestures
     * live on the same strip, so they are kept apart by having only one
     * of them be a drag: the drag still selects, and zoom is four
     * buttons.
     *
     * Zoom and the bucket unit are one mechanism seen twice (§13.2). A
     * span of a fortnight has no business being drawn as one monthly
     * bar, and a span of fourteen months has no business being drawn as
     * 437 daily ones — §12.5.5 measured the second of those at 0.68px a
     * bar. So the unit follows from the visible span, by a rule the
     * caller owns: the History chart draws its whole span monthly and
     * the Timeline draws every span monthly, and one shared rule could
     * not do both.
     *
     * No fetch, at any zoom level. §13.1 measured the whole span at
     * every grain at about a kilobyte of counts and a dozen of labels,
     * so the server ships all of it once and this is arithmetic. Which
     * is also why the labels are not derived here — they arrive from
     * `ValueProfileBuckets::plan()` already written, so there is one
     * formatter rather than two that have to agree.
     * ================================================================== */

    /*
     * How few bars the chart may be zoomed down to.
     *
     * A chart of two bars is not a chart, and the floor has to be in
     * bars rather than in days because the Timeline's finest unit is a
     * month: four is a readable span of days and a readable span of
     * months, and the same number therefore serves both.
     */
    var ZOOM_MIN_BUCKETS = 4;

    /**
     * @param {string} ymd `Y-m-d`
     * @return {number} Days since the epoch, UTC
     */
    function zoomEpochDay(ymd) {
        var parts = ymd.split('-');
        return Math.round(Date.UTC(
            Number(parts[0]),
            Number(parts[1]) - 1,
            Number(parts[2])
        ) / 86400000);
    }

    /**
     * @param {number} day Days since the epoch, UTC
     * @return {string} `Y-m-d`
     */
    function zoomYmd(day) {
        return new Date(day * 86400000).toISOString().slice(0, 10);
    }

    /*
     * PHP's `date()` is not locale-aware — `M` is always `Jan`..`Dec`,
     * whatever the instance's language — so this table reproduces
     * `ValueProfileBuckets::describe()` exactly rather than
     * approximating it. `toLocaleString` would not: it would render the
     * day grain's bars in the browser's language and the week grain's,
     * which the server still writes, in English.
     */
    var ZOOM_MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
        'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

    /**
     * The bar label for one day, as `describe()` writes it: `j M`.
     *
     * The day grain ships no labels — bucket `i` is the day `from + i`
     * and can be nothing else, so 1,095 of these were 26.7 KB of
     * payload restating the arithmetic this file already does.
     * `ValueProfileBuckets::plan` documents the other half.
     *
     * @param {number} day Days since the epoch, UTC
     * @return {string}
     */
    function zoomDayLabel(day) {
        var at = new Date(day * 86400000);
        return at.getUTCDate() + ' ' + ZOOM_MONTHS[at.getUTCMonth()];
    }

    /**
     * The tooltip for one day, as `describe()` writes it: `j M Y`.
     * A day bucket starts and ends on the same date, so it is the
     * single-date branch there and never the range one.
     *
     * @param {number} day Days since the epoch, UTC
     * @return {string}
     */
    function zoomDayTitle(day) {
        return zoomDayLabel(day) + ' ' + new Date(day * 86400000)
            .getUTCFullYear();
    }

    /**
     * The unit a span of this many days is drawn at.
     *
     * A mirror of `ValueProfileBuckets::unitForSpan()`, and the only
     * one this file keeps: the rule itself is shipped rather than
     * restated, so what is duplicated is the walk over it and not the
     * thresholds.
     *
     * @param {Array} rule `days` and `unit`, first match wins
     * @param {number} days
     * @return {string}
     */
    function zoomUnitFor(rule, days) {
        for (var i = 0; i < rule.length; i += 1) {
            if (rule[i].days === null || days <= rule[i].days) {
                return rule[i].unit;
            }
        }
        return rule[rule.length - 1].unit;
    }

    /**
     * A zoom over one chart's plan.
     *
     * The state is a visible span in day offsets from the plan's start,
     * plus the unit that span is drawn at. The unit is stored rather
     * than re-derived on read, because every transition snaps the span
     * outwards to whole buckets *of the unit the requested span asked
     * for* — deriving it again from the snapped span could pick a
     * different one and the pair would never settle.
     *
     * `selection` is how the zoom offers *look inside what I have
     * already picked* without knowing what a selection is on the
     * caller's tab — a filtered period on the History rail, a brushed
     * window on the Sightings navigator. A callback rather than a
     * value, for the reason phase 20 made the brush's bucket count
     * one: the answer changes under the control, and the control is
     * wired once.
     *
     * @param {Object} plan `from`, `to`, `days`, `rule`, `grains`
     * @param {Function|null} selection Returns `{from, to}` or null
     * @return {Object}
     */
    function makeZoom(plan, selection) {
        var epoch = zoomEpochDay(plan.from);
        var last = plan.days - 1;
        var ranges = {};
        var view = { from: 0, to: last };
        var unit = zoomUnitFor(plan.rule, plan.days);

        /**
         * One grain's bar label, whether the server wrote it or this
         * side derives it.
         *
         * `plan.last_label` replaces the final bar's own label — the
         * navigator's right-hand column reads `today` at every grain —
         * and it is applied here rather than server-side because it is
         * a translated word and the day grain's labels no longer exist
         * for it to be written into.
         *
         * @param {string} which Unit
         * @param {number} index Bar's place in the grain
         * @return {string}
         */
        function labelOf(which, index) {
            var grain = plan.grains[which];
            if (plan.last_label && index === grainCount(which) - 1) {
                return plan.last_label;
            }
            return grain.label
                ? grain.label[index]
                : zoomDayLabel(epoch + index);
        }

        /**
         * The same for a bar's tooltip. No `last_label` here: the end
         * bar is labelled `today` and still titled with its own date,
         * which is what makes the label a relabelling rather than a
         * claim that the bucket is now.
         *
         * @param {string} which Unit
         * @param {number} index
         * @return {string}
         */
        function titleOf(which, index) {
            var grain = plan.grains[which];
            return grain.title
                ? grain.title[index]
                : zoomDayTitle(epoch + index);
        }

        /**
         * How many bars a grain has. `count` is on every grain, so a
         * grain whose label array is gone still knows its own length.
         *
         * @param {string} which
         * @return {number}
         */
        function grainCount(which) {
            var grain = plan.grains[which];
            if (!grain) {
                return 0;
            }
            return grain.count === undefined
                ? grain.label.length
                : grain.count;
        }

        /**
         * Each bucket of a grain as a pair of day offsets, worked out
         * once per grain and kept.
         *
         * The server ships the starts and not the ends, because a
         * bucket ends where the next one begins and the last ends with
         * the span — so the ends are arithmetic, and arithmetic is this
         * side's half of the split `plan()` documents. A `starts` of
         * null is the identity, which is what a daily grain always is.
         *
         * @param {string} which
         * @return {Array}
         */
        function offsets(which) {
            if (!ranges[which]) {
                var grain = plan.grains[which];
                var count = grainCount(which);
                var starts = grain && grain.starts;
                var spans = [];
                for (var i = 0; i < count; i += 1) {
                    var from = starts ? starts[i] : i;
                    var next = i + 1 < count
                        ? (starts ? starts[i + 1] : i + 1)
                        : last + 1;
                    spans.push({ from: from, to: next - 1 });
                }
                ranges[which] = spans;
            }
            return ranges[which];
        }

        /**
         * One drawn bar: the label and title the server wrote, and the
         * dates this side worked out.
         *
         * The dates are clipped to the visible span, which does two
         * jobs. The month grain's first bucket is a whole calendar
         * month and so may begin before the log's first day — a bar the
         * reader may brush, but not one they may filter to days the log
         * does not cover. And where the visible span is a *named* one
         * rather than a zoomed one, its edge bars are clipped to it
         * exactly as the server used to clip them: a range called
         * `last 365 days` covers 365 days, so its first weekly bar is
         * however much of that week falls inside.
         *
         * `index` is the bar's place in the grain rather than in the
         * drawn window, because a caller whose data is parallel arrays
         * — one per organisation, on the Sightings navigator — indexes
         * them by that and not by what happens to be on screen.
         *
         * @param {string} which
         * @param {number} index
         * @param {{from: number, to: number}} span Day offsets
         * @return {Object}
         */
        function describe(which, index, span) {
            var lo = Math.max(Math.max(0, view.from), span.from);
            var hi = Math.min(Math.min(last, view.to), span.to);
            return {
                bucket: {
                    label: labelOf(which, index),
                    title: titleOf(which, index),
                    from: zoomYmd(epoch + lo),
                    to: zoomYmd(epoch + hi),
                },
                index: index,
                from: lo,
                to: hi,
            };
        }

        /**
         * Widen `from`..`to` to the whole buckets of `which` it touches.
         *
         * Outwards and never inwards, so a bar is always the whole
         * bucket the server labelled: §12.4 refused a month bucket
         * starting mid-month on the grounds that it would be a bar
         * labelled `Mar` that is not March, and an edge left where a
         * halving put it would be exactly that.
         *
         * @param {string} which
         * @param {number} from Day offset
         * @param {number} to Day offset
         * @return {{from: number, to: number}}
         */
        function snap(which, from, to) {
            var spans = offsets(which);
            var lo = null;
            var hi = null;
            spans.forEach(function (span) {
                if (span.to >= from && span.from <= to) {
                    if (lo === null) {
                        lo = span.from;
                    }
                    hi = span.to;
                }
            });
            if (lo === null) {
                return { from: 0, to: last };
            }
            return {
                from: Math.max(0, lo),
                to: Math.min(last, hi),
            };
        }

        /**
         * Move to a requested span: pick the unit it asks for, then
         * either snap to that unit's buckets or take the span as given.
         *
         * The two callers want different things and the difference is
         * not a detail. A zoom step has no span to honour — it halves
         * what is on screen — so its edges are arbitrary and snapping
         * them out to whole buckets is what keeps a bar labelled `Mar`
         * from being part of March. A *named* span is the opposite: the
         * reader picked `last 365 days`, so its edges are the point and
         * widening them to 371 would make the label wrong. Its edge
         * bars are clipped instead, which is what the server did when
         * it built those ranges itself.
         *
         * @param {number} from Day offset
         * @param {number} to Day offset
         * @param {boolean} exact Take the span as given
         */
        function settle(from, to, exact) {
            var lo = Math.max(0, Math.min(from, last));
            var hi = Math.min(last, Math.max(to, 0));
            var wanted = zoomUnitFor(plan.rule, hi - lo + 1);
            unit = wanted;
            view = exact ? { from: lo, to: hi } : snap(wanted, lo, hi);
        }

        /**
         * The buckets currently drawn, each with the day offsets it
         * covers so the caller can reduce its own data over them.
         *
         * @return {Array} `bucket`, `from`, `to`
         */
        function drawn() {
            var spans = offsets(unit);
            var out = [];
            spans.forEach(function (span, index) {
                if (span.to >= view.from && span.from <= view.to) {
                    out.push(describe(unit, index, span));
                }
            });
            return out;
        }

        /**
         * What halving the visible span would land on, as a span.
         *
         * Around the centre, because there is no pointer to zoom
         * towards — the gesture is a button, not a wheel over a bar.
         *
         * @param {number} factor 0.5 to zoom in, 2 to zoom out
         * @return {{from: number, to: number}}
         */
        function scaled(factor) {
            var days = view.to - view.from + 1;
            var wanted = Math.max(1, Math.round(days * factor));
            var centre = (view.from + view.to) / 2;
            return {
                from: Math.round(centre - (wanted - 1) / 2),
                to: Math.round(centre + (wanted - 1) / 2),
            };
        }

        /**
         * Whether zooming in would leave a chart worth drawing.
         *
         * Measured on what the step would actually produce rather than
         * on the day count, because snapping can widen it back: at
         * monthly bars a halving that lands inside one month snaps out
         * to that month and the step would do nothing.
         *
         * @return {boolean}
         */
        function canIn() {
            var want = scaled(0.5);
            var wanted = zoomUnitFor(plan.rule, want.to - want.from + 1);
            var bounds = snap(wanted, Math.max(0, want.from),
                Math.min(last, want.to));
            var spans = offsets(wanted);
            var count = 0;
            spans.forEach(function (span) {
                if (span.to >= bounds.from && span.from <= bounds.to) {
                    count += 1;
                }
            });
            if (count < ZOOM_MIN_BUCKETS) {
                return false;
            }
            return bounds.from > view.from || bounds.to < view.to;
        }

        return {
            /** @return {string} */
            unit: function () {
                return unit;
            },
            /** @return {Array} */
            window: function () {
                return drawn();
            },
            /** @return {number} */
            count: function () {
                return drawn().length;
            },
            /** @return {boolean} Whether the whole span is showing */
            whole: function () {
                return view.from === 0 && view.to === last;
            },
            /**
             * The two ends of the visible span, in words.
             *
             * Off the daily grain's titles, indexed by day offset,
             * rather than off the first and last drawn buckets': a
             * weekly bucket's own title is a range — `19 Aug – 25 Aug
             * 2024` — so a caption built from two of them reads as four
             * dates with three dashes. A daily title is one date, which
             * is the whole reason for reaching past the drawn bars.
             *
             * A caller whose rule has no daily grain falls back to the
             * bucket titles, which for a monthly grain are single
             * tokens and read correctly.
             *
             * @return {{from: string, to: string}|null}
             */
            spanText: function () {
                var shown = drawn();
                if (!shown.length) {
                    return null;
                }
                var first = shown[0];
                var final = shown[shown.length - 1];
                var day = plan.grains.day;
                if (day && !day.starts) {
                    return {
                        from: titleOf('day', first.from),
                        to: titleOf('day', final.to),
                    };
                }
                return {
                    from: first.bucket.title,
                    to: final.bucket.title,
                };
            },
            /** @return {{from: string, to: string}} `Y-m-d` bounds */
            span: function () {
                var shown = drawn();
                if (!shown.length) {
                    return { from: plan.from, to: plan.to };
                }
                return {
                    from: shown[0].bucket.from,
                    to: shown[shown.length - 1].bucket.to,
                };
            },
            /**
             * The title of the bucket of `which` that holds `ymd`,
             * whether or not it is on screen.
             *
             * For a caller that wants to name a date in the same words
             * a bar names it — the History tab puts a day inside its
             * month — without keeping its own copy of the grain's
             * shape.
             *
             * @param {string} which Unit
             * @param {string} ymd `Y-m-d`
             * @return {string|null}
             */
            titleAt: function (which, ymd) {
                if (!plan.grains[which]) {
                    return null;
                }
                var wanted = zoomEpochDay(ymd) - epoch;
                var found = null;
                offsets(which).forEach(function (span, index) {
                    if (wanted >= span.from && wanted <= span.to) {
                        found = titleOf(which, index);
                    }
                });
                return found;
            },
            /**
             * Whether looking inside the selection would show anything
             * different from what is on screen.
             *
             * False when there is no selection, when it is not in the
             * plan at all, and when it already *is* the visible span —
             * a button that redraws the same chart is one the reader
             * learns to distrust.
             *
             * @return {boolean}
             */
            canSelection: function () {
                if (!selection) {
                    return false;
                }
                var picked = selection();
                if (!picked || !picked.from || !picked.to) {
                    return false;
                }
                var lo = zoomEpochDay(picked.from) - epoch;
                var hi = zoomEpochDay(picked.to) - epoch;
                if (hi < 0 || lo > last || hi < lo) {
                    return false;
                }
                return Math.max(0, lo) > view.from
                    || Math.min(last, hi) < view.to;
            },
            /**
             * Show the selection. Exact, like a preset: the reader
             * picked these two dates, so widening them to whole buckets
             * would show them a span they did not ask for.
             */
            stepSelection: function () {
                if (!selection) {
                    return;
                }
                var picked = selection();
                if (!picked || !picked.from || !picked.to) {
                    return;
                }
                settle(
                    zoomEpochDay(picked.from) - epoch,
                    zoomEpochDay(picked.to) - epoch,
                    true
                );
            },
            /** @return {boolean} */
            canIn: canIn,
            /** @return {boolean} */
            canOut: function () {
                return !(view.from === 0 && view.to === last);
            },
            /** @return {boolean} */
            canLeft: function () {
                return view.from > 0;
            },
            /** @return {boolean} */
            canRight: function () {
                return view.to < last;
            },
            stepIn: function () {
                var want = scaled(0.5);
                settle(want.from, want.to);
            },
            stepOut: function () {
                var want = scaled(2);
                settle(want.from, want.to);
            },
            /**
             * Sideways by half a window, and never off the end: a pan
             * that ran out of span would otherwise shrink the view.
             *
             * @param {number} direction -1 or 1
             */
            step: function (direction) {
                var days = view.to - view.from + 1;
                var by = direction * Math.max(1, Math.round(days / 2));
                var from = view.from + by;
                var to = view.to + by;
                if (from < 0) {
                    to -= from;
                    from = 0;
                }
                if (to > last) {
                    from -= to - last;
                    to = last;
                }
                settle(from, to);
            },
            /**
             * Show a named date range: one of a caller's presets, or
             * the range a reader has already brushed and wants to look
             * inside. Taken exactly, per `settle`.
             *
             * @param {string} from `Y-m-d`
             * @param {string} to `Y-m-d`
             */
            to: function (from, to) {
                settle(
                    zoomEpochDay(from) - epoch,
                    zoomEpochDay(to) - epoch,
                    true
                );
            },
            reset: function () {
                view = { from: 0, to: last };
                unit = zoomUnitFor(plan.rule, plan.days);
            },
        };
    }

    /**
     * Wire one zoom control's buttons.
     *
     * @param {Element|null} root The control
     * @param {Object} zoom From `makeZoom`
     * @param {Function} changed Called after any step
     */
    function wireZoom(root, zoom, changed) {
        if (!root) {
            return;
        }
        var steps = {
            in: function () {
                zoom.stepIn();
            },
            out: function () {
                zoom.stepOut();
            },
            left: function () {
                zoom.step(-1);
            },
            right: function () {
                zoom.step(1);
            },
            reset: function () {
                zoom.reset();
            },
            selection: function () {
                zoom.stepSelection();
            },
        };
        root.querySelectorAll('[data-vp-zoom-step]').forEach(
            function (button) {
                button.addEventListener('click', function (event) {
                    event.preventDefault();
                    var step = steps[button.dataset.vpZoomStep];
                    if (step) {
                        step();
                        changed();
                    }
                });
            }
        );
    }

    /**
     * Show where the zoom is: which buttons can still do something,
     * what span is on screen and what a bar is worth.
     *
     * Both halves of the caption are the server's own strings — the
     * first and last drawn buckets' titles, and the grain wording the
     * caller shipped — so a reader is never told `Mar` by one formatter
     * and `March` by another.
     *
     * A caller may pass a `note`, which is the one thing about a
     * zoomed chart this layer cannot know: whether what the reader has
     * selected is still on screen. A fully dimmed strip is the truthful
     * painting of *nothing here is in your range*, and it is also
     * indistinguishable from an undimmed one, because a uniform dim has
     * nothing to contrast against — so the fact has to be said in words
     * or not at all.
     *
     * @param {Element|null} root The control
     * @param {Object} zoom From `makeZoom`
     * @param {Object} labels `grain` keyed by unit
     * @param {string|null} note Optional, shown beside the grain
     */
    function paintZoom(root, zoom, labels, note) {
        if (!root) {
            return;
        }
        var can = {
            in: zoom.canIn(),
            out: zoom.canOut(),
            left: zoom.canLeft(),
            right: zoom.canRight(),
            reset: !zoom.whole(),
            selection: zoom.canSelection(),
        };
        root.querySelectorAll('[data-vp-zoom-step]').forEach(
            function (button) {
                button.disabled = !can[button.dataset.vpZoomStep];
            }
        );
        var range = root.querySelector('[data-vp-zoom-range]');
        if (range) {
            var ends = zoom.spanText();
            if (ends === null) {
                range.textContent = '';
            } else if (ends.from === ends.to) {
                range.textContent = ends.from;
            } else {
                range.textContent = ends.from + ' → ' + ends.to;
            }
        }
        var grain = root.querySelector('[data-vp-zoom-grain]');
        if (grain && labels && labels.grain) {
            grain.textContent = labels.grain[zoom.unit()] || '';
        }
        var aside = root.querySelector('[data-vp-zoom-note]');
        if (aside) {
            aside.textContent = note || '';
            aside.hidden = !note;
        }
        root.classList.toggle('vp-zoom-on', !zoom.whole());
    }

    window.VP.zoom = {
        make: makeZoom,
        wire: wireZoom,
        paint: paintZoom,
    };

    /* ==================================================================
     * Sightings tab
     * ------------------------------------------------------------------
     * One overlay and one brush. The chart and the list arrive as two
     * fragments in whichever order the network decides, so the state
     * lives here and each fragment applies it when it lands rather than
     * one of them reaching into the other.
     *
     * Everything is client-side against data the fragments already
     * carry: the span presets and the zoom both re-aggregate one daily
     * tally, and dragging the brush hides table rows. Nothing
     * re-queries, and nothing writes.
     *
     * Phase 21 replaced three precomputed ranges with that one tally.
     * The tab reads its bars, its stacks and its curves through
     * `sightRange()` and always did, so the conversion is behind that
     * one function: what changed is that it derives the range from the
     * visible span rather than looking one up.
     * ================================================================== */

    var sight = {
        data: null,
        rangeKey: null,
        // Which of the three types are drawn. A type with no rows at
        // all is disabled in the markup and never reaches this.
        shown: { sighting: true, fp: true, expiration: true },
        // Organisations the legend has switched off, by their index in
        // `data.orgs`. Keyed by index rather than held as a list of the
        // survivors so that a colour never moves: the ramp is read off
        // this index and a filter must not repaint what it left alone.
        // The chart only — the table below answers to the brush, which
        // is the gesture that has a date range in it.
        hiddenOrgs: {},
        // Bucket index bounds of the brush, or null while it covers the
        // whole range.
        brush: null,
        expanded: false,
        main: null,
        nav: null,
        // The visible span, which the presets and the four buttons both
        // set. Null until the panel has a plan.
        zoom: null,
        labels: null,
        // `sightRange()`'s answer, and the zoom state it was computed
        // for. Rebuilt on a change rather than on every read: a repaint
        // asks for it from the chart, the navigator, the legend and the
        // list, and summing 23 organisations' worth of days four times
        // over is work nobody asked for.
        range: null,
        rangeAt: null,
    };

    var SIGHT_ORG_COLOURS = 6;
    var SIGHT_CURVE_COLOURS = 2;

    /*
     * The three kinds of report, bottom of the stack upwards, and the
     * only place their order is written down. Every series on the count
     * axis is one of these for one organisation — a sighting is not
     * attributed while a false positive is pooled, which is what this
     * tab used to do.
     */
    var SIGHT_KINDS = ['sighting', 'fp', 'expiration'];

    /**
     * Turn the sparse per-day tallies into dense arrays, once.
     *
     * The wire format is sparse because these series are — twenty-three
     * organisations over fourteen months, a few hundred reports between
     * them — and the runtime format is dense because everything above
     * it sums slices, which wants a plain array. Only the wire differs.
     *
     * @param {Object} data The payload
     */
    function sightInflate(data) {
        var days = data.plan.days;

        function dense(at) {
            var counts = new Array(days);
            var i;
            for (i = 0; i < days; i += 1) {
                counts[i] = 0;
            }
            Object.keys(at || {}).forEach(function (offset) {
                var slot = Number(offset);
                if (slot >= 0 && slot < days) {
                    counts[slot] = at[offset];
                }
            });
            return counts;
        }

        SIGHT_KINDS.forEach(function (kind) {
            var series = data.daily[kind] || {};
            data.daily[kind] = Object.keys(series).map(function (key) {
                return dense(series[key]);
            });
        });
    }

    /**
     * Sum one daily series over the days a bar covers.
     *
     * @param {Array} counts One count per day of the plan's span
     * @param {{from: number, to: number}} bar Day offsets
     * @return {number}
     */
    function sightSum(counts, bar) {
        var total = 0;
        for (var i = bar.from; i <= bar.to; i += 1) {
            total += counts[i] || 0;
        }
        return total;
    }

    /**
     * The range the chart is drawing: one entry per visible bar, in the
     * shape every consumer on this tab already read.
     *
     * Built rather than looked up, which is the whole of phase 21 on
     * this tab. The three precomputed ranges were three aggregations of
     * the same rows, so the browser can make any of them — and any span
     * between them — by summing a slice of the daily tally per bar.
     *
     * The curves are sampled, not summed. A count is additive and a
     * decay score is not: it is the value as of a date, so a bar that
     * covers a week takes the score at the end of that week, which is
     * where the per-range curves used to be sampled.
     *
     * @return {Object|null}
     */
    function sightRange() {
        if (!sight.data || !sight.zoom) {
            return null;
        }
        var span = sight.zoom.span();
        var stamp = span.from + '/' + span.to + '/' + sight.zoom.unit();
        if (sight.range !== null && sight.rangeAt === stamp) {
            return sight.range;
        }
        var bars = sight.zoom.window();
        var daily = sight.data.daily;
        function total(series) {
            return series.reduce(function (a, b) {
                return a + b;
            }, 0);
        }
        /*
         * Three tallies per organisation, each summed per drawn bar.
         * `kinds[kind][org]` is one array per bar, which is the shape
         * Chart.js wants a dataset in and the shape the readout groups
         * by — the only reshaping left is choosing which of them are
         * currently drawn.
         */
        var kinds = {};
        var kindCounts = {};
        SIGHT_KINDS.forEach(function (kind) {
            kinds[kind] = (daily[kind] || []).map(function (counts) {
                return bars.map(function (bar) {
                    return sightSum(counts, bar);
                });
            });
            kindCounts[kind] = total(kinds[kind].map(total));
        });
        /*
         * An organisation's count is every report it filed in the
         * range, of any type, which is what the Reporters card counts
         * and what the legend's own heading promises. A per-kind
         * breakdown rides along so the key can say which of the three
         * the number is made of.
         */
        var orgCounts = sight.data.orgs.map(function (name, i) {
            return SIGHT_KINDS.reduce(function (sum, kind) {
                return sum + total(kinds[kind][i] || []);
            }, 0);
        });
        var orgByKind = sight.data.orgs.map(function (name, i) {
            var per = {};
            SIGHT_KINDS.forEach(function (kind) {
                per[kind] = total(kinds[kind][i] || []);
            });
            return per;
        });
        sight.range = {
            unit: sight.zoom.unit(),
            unitLabel: sight.data.labels.perColumn[sight.zoom.unit()],
            from: span.from,
            to: span.to,
            labels: bars.map(function (bar) {
                return bar.bucket.label;
            }),
            starts: bars.map(function (bar) {
                return bar.bucket.from;
            }),
            ends: bars.map(function (bar) {
                return bar.bucket.to;
            }),
            kinds: kinds,
            kindCounts: kindCounts,
            orgCounts: orgCounts,
            orgByKind: orgByKind,
            curves: sight.data.curves.map(function (curve) {
                return {
                    model: curve.model,
                    threshold: curve.threshold,
                    points: bars.map(function (bar) {
                        return curve.points[bar.to];
                    }),
                };
            }),
        };
        sight.rangeAt = stamp;
        return sight.range;
    }

    /**
     * Throw away the derived range, so the next read rebuilds it.
     */
    function sightInvalidate() {
        sight.range = null;
        sight.rangeAt = null;
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

    /* ------------------------------------------------------------------
     * The readout
     * ------------------------------------------------------------------
     * Chart.js draws one list, and this chart hovers two scales at
     * once. A column over a busy week listed nine organisations, a
     * false positive, an expiration, two model scores and two model
     * thresholds as thirteen interchangeable rows of `name: number` —
     * so the count and the score, whose only relationship is the
     * argument the panel exists to make, looked like members of one
     * series.
     *
     * So the canvas tooltip is off and this one is HTML: two sections
     * with a rule between them, the reports totalled in their heading,
     * and the number ahead of the name in every row because the reader
     * arrived already knowing which series they are pointing at. The
     * keys are strokes rather than blocks — at this density a filled
     * swatch is data-weight ink doing a label's job.
     * ------------------------------------------------------------------ */

    /**
     * The node, made once per chart and reused. It lives inside the
     * chart's own relatively-positioned box, so placing it is arithmetic
     * on the caret rather than on the page.
     *
     * @param {Element} host The canvas's parent
     * @return {Element}
     */
    function sightTipNode(host) {
        var node = host.querySelector('[data-vp-sight-tip]');
        if (!node) {
            node = document.createElement('div');
            node.className = 'vp-tip';
            node.setAttribute('data-vp-sight-tip', '');
            node.setAttribute('aria-hidden', 'true');
            host.appendChild(node);
        }
        return node;
    }

    /**
     * @param {Element} node
     * @param {string} title
     * @param {?number} total Rendered beside the heading when given
     * @return {Element} The section to append rows to
     */
    function sightTipSection(node, title, total) {
        var section = document.createElement('div');
        var head = document.createElement('div');
        var name = document.createElement('span');
        section.className = 'vp-tip-sec';
        head.className = 'vp-tip-sec-head';
        name.textContent = title;
        head.appendChild(name);
        if (total !== null) {
            var count = document.createElement('b');
            count.textContent = total;
            head.appendChild(count);
        }
        section.appendChild(head);
        node.appendChild(section);
        return section;
    }

    /**
     * One row. `textContent` throughout: an organisation name is
     * whatever a remote instance called itself.
     *
     * @param {Element} section
     * @param {string} colour Already resolved off the canvas
     * @param {number} value
     * @param {string} name
     */
    function sightTipRow(section, colour, value, name) {
        var row = document.createElement('div');
        var key = document.createElement('i');
        var num = document.createElement('b');
        var label = document.createElement('span');
        row.className = 'vp-tip-row';
        key.className = 'vp-tip-key';
        key.style.background = colour;
        num.textContent = value;
        label.textContent = name;
        row.appendChild(key);
        row.appendChild(num);
        row.appendChild(label);
        section.appendChild(row);
        return row;
    }

    /**
     * Chart.js hands the whole tooltip model here on every move.
     *
     * @param {Object} context `{chart, tooltip}`
     */
    function sightTip(context) {
        var chart = context.chart;
        var model = context.tooltip;
        var node = sightTipNode(chart.canvas.parentNode);

        if (!model || model.opacity === 0
            || !(model.dataPoints || []).length
        ) {
            node.classList.remove('vp-tip-on');
            return;
        }

        var labels = (sight.data && sight.data.labels) || {};
        var kindLabels = labels.kinds || {};
        var points = model.dataPoints || [];
        var at = points.length ? points[0].dataIndex : null;

        node.textContent = '';
        var head = document.createElement('div');
        head.className = 'vp-tip-head';
        head.textContent = model.title.length ? model.title[0] : '';
        node.appendChild(head);

        /*
         * One section per kind of report, so that every row in the
         * readout says the same thing — a count and the organisation
         * that filed it — and the heading says which kind. A single
         * `Reports` list could not: the reporter was the row label for
         * a sighting and there was no row label left for a false
         * positive but the words `False positive` themselves.
         *
         * A kind with nothing in this column is not a section. Its
         * heading totals the column rather than its own rows, because
         * the rows are filtered — an organisation reporting nothing
         * this week is not a row.
         *
         * Magnitudes throughout. The two contradicting kinds are
         * plotted negative so they hang below the axis, and `-2
         * expirations` is a direction printed as though it were a
         * quantity.
         */
        SIGHT_KINDS.forEach(function (kind) {
            var rows = points.filter(function (point) {
                return point.dataset.vpKind === kind;
            });
            var total = 0;
            if (at !== null) {
                chart.data.datasets.forEach(function (set) {
                    if (set.vpKind === kind) {
                        total += Math.abs(set.data[at] || 0);
                    }
                });
            }
            if (total === 0) {
                return;
            }
            var section = sightTipSection(
                node,
                kindLabels[kind] || '',
                total
            );
            rows.forEach(function (point) {
                sightTipRow(
                    section,
                    point.dataset.backgroundColor,
                    Math.abs(point.parsed.y),
                    point.dataset.label
                );
            });
        });

        var lines = points.filter(function (point) {
            return point.dataset.type === 'line';
        });
        if (lines.length) {
            var scores = sightTipSection(node, labels.score || '', null);
            lines.forEach(function (point) {
                sightTipRow(
                    scores,
                    point.dataset.borderColor,
                    point.parsed.y,
                    point.dataset.label
                );
            });
        }

        node.classList.add('vp-tip-on');
        var left = model.caretX + 14;
        if (left + node.offsetWidth > chart.width) {
            left = model.caretX - node.offsetWidth - 14;
        }
        var top = model.caretY - (node.offsetHeight / 2);
        node.style.left = Math.max(0, Math.min(
            left,
            chart.width - node.offsetWidth
        )) + 'px';
        node.style.top = Math.max(0, Math.min(
            top,
            chart.height - node.offsetHeight
        )) + 'px';
    }

    /**
     * Which way a kind of report is drawn. A sighting supports the
     * value and goes up; a false positive and an expiration argue
     * against it and go down.
     *
     * This is the panel's argument moved out of the colour channel and
     * into the geometry. All three used to stack upwards in one column,
     * so `a contradiction was filed` was a hue among six organisation
     * hues that cycle — the two marks the panel exists to make visible
     * were the two hardest to find in a stack of six reporters.
     * Direction cannot be crowded out: whatever is below the line
     * argues against the value, at any grain, in either theme, and in
     * greyscale.
     *
     * @param {string} kind One of SIGHT_KINDS
     * @return {number} 1 above the line, -1 below it
     */
    function sightKindSign(kind) {
        return kind === 'sighting' ? 1 : -1;
    }

    /**
     * The colour a segment of one kind takes.
     *
     * Colour is identity here and direction is meaning, so a sighting
     * is the organisation's own hue. The two contradicting kinds keep a
     * colour of their own because the axis can only say `argues
     * against` and there are two ways of doing that — and below the
     * line they never touch an organisation hue, which is what retires
     * the collision that used to matter most: the benign green against
     * a brown organisation at ΔE 4.1, and the red that replaced it at
     * 9.3, were both pairs that can no longer meet.
     *
     * @param {string} kind One of SIGHT_KINDS
     * @param {number} i Position in `data.orgs`
     * @return {string}
     */
    function sightKindHue(kind, i) {
        if (kind === 'fp') {
            return 'var(--vp-sight-fp)';
        }
        if (kind === 'expiration') {
            return 'var(--vp-sight-exp)';
        }
        return sightHue(i, SIGHT_ORG_COLOURS, 'org');
    }

    /**
     * How far the count axis reaches each way, over the drawn range and
     * over the kinds currently switched on.
     *
     * Computed here rather than left to Chart.js because the score axis
     * has to be told where this one's zero landed. A score is 0–100 and
     * a count is now signed, so the two axes only share a zero if the
     * score scale is given the same negative share of its own height —
     * otherwise a model at 0 draws a line through the middle of the
     * contradictions, which is exactly the region it is supposed to be
     * read against.
     *
     * @param {Object} range From sightRange
     * @return {{up: number, down: number}} Both positive magnitudes
     */
    function sightBounds(range) {
        var up = 0;
        var down = 0;
        range.labels.forEach(function (label, at) {
            var above = 0;
            var below = 0;
            SIGHT_KINDS.forEach(function (kind) {
                if (!sight.shown[kind]) {
                    return;
                }
                range.kinds[kind].forEach(function (counts, i) {
                    if (sight.hiddenOrgs[i]) {
                        return;
                    }
                    if (sightKindSign(kind) > 0) {
                        above += counts[at];
                    } else {
                        below += counts[at];
                    }
                });
            });
            up = Math.max(up, above);
            down = Math.max(down, below);
        });
        /*
         * At least one unit of headroom above the line even when
         * nothing supports the value, because the decay curve is drawn
         * in that band and a value nobody has ever sighted still has a
         * score. Without it, `45.155.205.233` — three false positives
         * and no sighting — would give the curve no height to live in.
         */
        return { up: Math.max(1, up), down: down };
    }

    /**
     * The overlay itself: one stacked bar dataset per organisation per
     * kind of report on the count axis, and one line per decaying model
     * on the score axis.
     *
     * Three datasets per organisation rather than one each plus two
     * pooled ones. Pooling meant a sighting had a reporter and a
     * contradiction had none — the legend filed `False positive` under
     * `Reported by` as though it were an organisation, and the readout
     * listed it beside real ones. Now every count on this axis belongs
     * to somebody, which is also what makes switching an organisation
     * off take its contradictions with it.
     *
     * The kinds go in whole: every organisation's sightings, then every
     * organisation's false positives, then the expirations. So the
     * stack reads as three coloured blocks rather than interleaving red
     * through the organisation hues.
     *
     * The thresholds used to be here too, as a dotted dataset each plus
     * an inline plugin that chipped their value over the plot. They are
     * gone: a threshold is a constant, it was drawn per model and
     * listed again in the readout at every column, and the rail beside
     * the chart already carries it as the tick across each model's bar.
     *
     * An organisation the legend has switched off is skipped rather
     * than emptied, and the colour still comes from its position in
     * `data.orgs` — filtering the series must not repaint the ones that
     * stayed.
     *
     * @param {Element} el
     * @return {Object}
     */
    function buildSightMain(el) {
        var data = sight.data;
        var range = sightRange();
        var datasets = [];

        /*
         * A hairline of the page's own ground between stacked segments.
         * Without it a stack of three organisations with counts 1, 1, 1
         * is one bar in three tones, and the tones are what the reader
         * is being asked to count. It matters more now that two
         * organisations' false positives sit on each other in the same
         * red.
         *
         * The hairline goes on the side away from the axis, which is
         * the top of a bar that grows up and the bottom of one that
         * grows down.
         */
        function segment(sign, extra) {
            return Object.assign({
                type: 'bar',
                stack: 'reports',
                yAxisID: 'y',
                order: 3,
                borderColor: 'var(--bs-body-bg)',
                borderWidth: sign > 0
                    ? { top: 1, right: 0, bottom: 0, left: 0 }
                    : { top: 0, right: 0, bottom: 1, left: 0 },
                borderSkipped: false,
            }, extra);
        }

        /*
         * Three kinds by however many organisations is a lot of series
         * that are entirely zero — twenty-three organisations would be
         * sixty-nine datasets with perhaps twenty carrying anything, and
         * most organisations never file a contradiction at all. A
         * series with nothing in the drawn range is left out: it paints
         * no pixel and, being filtered out of the readout anyway,
         * contributes nothing but a dataset for Chart.js to walk. The
         * bar hue is read off `i` rather than off the dataset's
         * position, so leaving one out never moves a colour.
         */
        SIGHT_KINDS.forEach(function (kind) {
            if (!sight.shown[kind]) {
                return;
            }
            var sign = sightKindSign(kind);
            range.kinds[kind].forEach(function (counts, i) {
                if (sight.hiddenOrgs[i] || !counts.some(Boolean)) {
                    return;
                }
                datasets.push(segment(sign, {
                    label: data.orgs[i],
                    vpKind: kind,
                    // Signed for the plot only. `range.kinds` stays
                    // counts, so the navigator, the legend and the
                    // readout keep summing magnitudes.
                    data: sign > 0 ? counts : counts.map(function (n) {
                        return -n;
                    }),
                    backgroundColor: sightKindHue(kind, i),
                }));
            });
        });

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
        });

        /*
         * Both axes are pinned rather than left to Chart.js, so that
         * one pixel row means zero on both of them. The score keeps its
         * 0–100 over the band above the line and is given the same
         * negative share below it, where it has nothing to draw — so a
         * model sitting at 0 rests exactly on the count axis's zero
         * instead of cutting through the contradictions underneath.
         */
        var bounds = sightBounds(range);
        var scoreFloor = -100 * (bounds.down / bounds.up);

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
                        min: -bounds.down,
                        max: bounds.up,
                        border: { display: false },
                        grid: {
                            color: 'var(--bs-border-color)',
                            /*
                             * The zero is the whole encoding, so it is
                             * drawn as a line and not as another
                             * gridline. Everything below it argues
                             * against the value.
                             */
                            lineWidth: function (context) {
                                return context.tick && context.tick.value === 0
                                    ? 2
                                    : 1;
                            },
                        },
                        ticks: {
                            color: 'var(--bs-secondary-color)',
                            precision: 0,
                            font: { size: 10 },
                            // A count, never a negative number: the
                            // direction is what the sign is saying and
                            // `-2 reports` is not a quantity.
                            callback: function (value) {
                                return Math.abs(value);
                            },
                        },
                    },
                    score: {
                        position: 'right',
                        min: scoreFloor,
                        max: 100,
                        border: { display: false },
                        grid: { drawOnChartArea: false },
                        ticks: {
                            color: 'var(--bs-secondary-color)',
                            stepSize: 25,
                            font: { size: 10 },
                            // The band below zero exists to hold the
                            // two scales' zeroes together, and a score
                            // never reaches it. Labelling it would
                            // claim a scale of negative scores.
                            callback: function (value) {
                                return value < 0 ? '' : value;
                            },
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
                        // The reports first, the scores after, whatever
                        // order the datasets went in — the readout is
                        // grouped and the groups have to stay whole.
                        itemSort: function (a, b) {
                            var rank = function (item) {
                                return item.dataset.type === 'line' ? 1 : 0;
                            };
                            return rank(a) - rank(b)
                                || a.datasetIndex - b.datasetIndex;
                        },
                        enabled: false,
                        external: sightTip,
                    },
                },
            },
        }, el);

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
        // Every report of every kind, whatever the chart above is
        // currently drawing: the navigator is the range, not the view.
        var totals = range.labels.map(function (label, i) {
            var sum = 0;
            SIGHT_KINDS.forEach(function (kind) {
                range.kinds[kind].forEach(function (counts) {
                    sum += counts[i];
                });
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
        var labels = sight.data.labels;
        panel.querySelectorAll('[data-vp-sight-key-org]')
            .forEach(function (key) {
                var i = parseInt(key.dataset.vpSightKeyOrg, 10);
                setText(key, '[data-vp-sight-key-count]', range.orgCounts[i]);
                /*
                 * Every report the organisation filed in the range, of
                 * any kind — the heading over these keys says `Reported
                 * by` and a contradiction is a report. The `title`
                 * carries the split, because the swatch is only the
                 * colour of the sightings among them.
                 */
                var per = range.orgByKind[i];
                var counted = labels.kindCounted || {};
                key.title = SIGHT_KINDS.filter(function (kind) {
                    return per[kind] > 0;
                }).map(function (kind) {
                    var forms = counted[kind] || [kind, kind];
                    return per[kind] + ' '
                        + forms[per[kind] === 1 ? 0 : 1];
                }).join(' · ');
                /*
                 * The count is the range's and stays put whether or not
                 * the bars are drawn: switching an organisation off asks
                 * the chart a question, not the data.
                 */
                key.setAttribute(
                    'aria-pressed',
                    sight.hiddenOrgs[i] ? 'false' : 'true'
                );
                // Nothing left to take out while every kind is switched
                // off above.
                key.disabled = !SIGHT_KINDS.some(function (kind) {
                    return sight.shown[kind];
                });
            });
        setText(panel, '[data-vp-sight-key-fp]', range.kindCounts.fp);
        setText(panel, '[data-vp-sight-key-exp]', range.kindCounts.expiration);

        var axis = panel.querySelector('[data-vp-sight-axis-left]');
        if (axis) {
            axis.textContent = labels.perUnit[range.unit];
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
        var count = sightRange().labels.length;
        window.VP.brush.paint(
            panel,
            sight.brush || { from: 0, to: count - 1 },
            count
        );

        var window_ = sightWindow();
        var label = panel.querySelector('[data-vp-sight-window]');
        if (label) {
            label.textContent = window_.from + ' → ' + window_.to;
        }
        // The brush *is* this tab's selection, so the step that looks
        // inside it goes live and dead with the drag.
        paintSightZoom(panel);
    }

    /**
     * Put the sightings list in the order its sorted heading names, and
     * only when that is not the order the rows are already in.
     *
     * The guard is what makes a sortable heading affordable on this
     * panel. `refreshSightList` runs on every frame of a brush drag, and
     * reordering is an `appendChild` per row — free to skip, and not
     * free to repeat a few hundred times a second.
     *
     * Every row and not the brushed ones, because the brush is a
     * visibility filter over a fixed set: leaving the rows outside it
     * where they were would put the DOM in an order that widening the
     * brush could not recover.
     *
     * @param {Element} list
     */
    function applySightOrder(list) {
        var column = list.dataset.vpSortedCol || 'default';
        var sign = list.dataset.vpSortedDir === 'desc' ? -1 : 1;
        var wanted = column + ':' + sign;
        if (list.dataset.vpSightOrder === wanted) {
            return;
        }
        sortByColumn(
            list,
            Array.prototype.slice.call(list.querySelectorAll('tbody tr')),
            column,
            sign
        );
        list.dataset.vpSightOrder = wanted;
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

        // Before the rows are collected, so `matched` comes out in the
        // order the reader is about to see rather than in the order the
        // model happened to send.
        applySightOrder(list);

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
        paintSightZoom(panel);
        updateSightLegend(panel);
        paintSightBrush(panel);
        refreshSightList();
    }

    /**
     * Drag on the navigator strip. A click clears the brush — the same
     * gesture the `Clear` button offers, for a reader who never found
     * it.
     *
     * The count is a callback because the range select changes it: 90
     * daily buckets become 43 weekly ones without the brush being
     * rewired.
     *
     * @param {Element} panel
     */
    function wireSightBrush(panel) {
        window.VP.brush.attach(panel.querySelector('[data-vp-brush]'), {
            count: function () {
                return sightRange().labels.length;
            },
            range: function (from, to) {
                sight.brush = { from: from, to: to };
                sight.expanded = false;
                paintSightBrush(panel);
                refreshSightList();
            },
            clear: function () {
                sight.brush = null;
                sight.expanded = false;
                paintSightBrush(panel);
                refreshSightList();
            },
        });
    }

    /**
     * Show one of the presets the select offers.
     *
     * @param {string} key `90`, `365` or `all`
     */
    function sightToSpan(key) {
        if (!sight.zoom || !sight.data) {
            return;
        }
        var wanted = null;
        sight.data.spans.forEach(function (span) {
            if (span.key === key) {
                wanted = span;
            }
        });
        if (wanted === null) {
            sight.zoom.reset();
        } else {
            sight.zoom.to(wanted.from, wanted.to);
        }
        sightInvalidate();
    }

    /**
     * Move the preset select onto whichever preset the drawn span is,
     * if it is one of them.
     *
     * Otherwise it is left alone. The two controls answer different
     * questions once a zoom exists — the select is a span the reader
     * asked for by name, the caption is the span on screen — and the
     * caption is the one that is always right, which is why it states
     * the dates and goes bold once they are not the whole span. But
     * `show the whole span` *is* one of the presets, so leaving the
     * select on `last 90 days` after that button had been pressed would
     * be a control contradicting the chart it drives.
     *
     * @param {Element} panel
     */
    function syncSightPreset(panel) {
        var select = panel.querySelector('[data-vp-sight-range]');
        if (!select || !sight.zoom || !sight.data) {
            return;
        }
        var span = sight.zoom.span();
        sight.data.spans.forEach(function (preset) {
            if (preset.from === span.from && preset.to === span.to) {
                select.value = preset.key;
                sight.rangeKey = preset.key;
            }
        });
    }

    /**
     * Paint the zoom control, and say when the brushed range has gone
     * off screen — which on this tab means the list below is filtered
     * to something the chart is no longer showing.
     *
     * @param {Element} panel
     */
    function paintSightZoom(panel) {
        var zoom = panel.querySelector('[data-vp-zoom]');
        if (!zoom || !sight.zoom) {
            return;
        }
        var labels = sight.labels || {};
        window.VP.zoom.paint(zoom, sight.zoom, labels, null);
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
            sightInflate(sight.data);
            sight.rangeKey = sight.data['default'];
            sight.brush = null;
            sight.expanded = false;
            sight.zoom = window.VP.zoom.make(
                sight.data.plan,
                // The brushed window, and only when it is a brush: the
                // window covers the whole visible span when nothing is
                // brushed, and `look inside all of it` is not a step.
                function () {
                    if (!sight.brush) {
                        return null;
                    }
                    var picked = sightWindow();
                    return { from: picked.from, to: picked.to };
                }
            );
            sightInvalidate();
            // The default preset is the narrowest span holding every
            // sighting, and it is what the select is rendered on, so
            // the chart has to land on the same one.
            sightToSpan(sight.rangeKey);
            sight.shown = { sighting: true, fp: true, expiration: true };
            sight.hiddenOrgs = {};
            panel.querySelectorAll('[data-vp-sight-type]')
                .forEach(function (button) {
                    if (button.disabled) {
                        sight.shown[button.dataset.vpSightType] = false;
                    }
                });
            sight.main = window.VP.chart.boot('vp-sight-main', buildSightMain);
            sight.nav = window.VP.chart.boot('vp-sight-nav', buildSightNav);
            wireSightBrush(panel);
            var zoom = panel.querySelector('[data-vp-zoom]');
            if (zoom) {
                var spec = zoom.querySelector('[data-vp-zoom-labels]');
                sight.labels = spec ? JSON.parse(spec.textContent) : null;
                zoom.hidden = false;
                window.VP.zoom.wire(zoom, sight.zoom, function () {
                    // A zoom is a different set of bars, so a brush
                    // drawn over the old ones points at nothing the
                    // reader chose — the same reason a preset clears it.
                    sight.brush = null;
                    sight.expanded = false;
                    syncSightPreset(panel);
                    refreshSight(panel);
                });
            }
            updateSightLegend(panel);
            paintSightBrush(panel);
            paintSightZoom(panel);
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

        /*
         * The legend as a filter. A stack of a dozen organisations is
         * a wall at the one bar the reader cares about, and until now
         * the only way to thin it was the three type toggles, which
         * cannot say `just this one`.
         */
        var org = event.target.closest('[data-vp-sight-key-org]');
        if (org && !org.disabled && panel) {
            var at = parseInt(org.dataset.vpSightKeyOrg, 10);
            if (sight.hiddenOrgs[at]) {
                delete sight.hiddenOrgs[at];
            } else {
                sight.hiddenOrgs[at] = true;
            }
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

    /**
     * Analyst data tab
     * ------------------------------------------------------------
     * Four behaviours, all client-side against markup the two
     * endpoints already sent: the order of the thread, which kinds it
     * shows, whether an item's replies are open, and a strip marker
     * lighting up the table row it belongs to.
     *
     * A reply is never sorted or filtered on its own. The unit is the
     * top-level item and everything written under it — `.vpa-item` —
     * because a reply reordered away from what it replies to is no
     * longer a reply, and a thread that hides the note but keeps the
     * opinion written on it is showing an answer to a question it has
     * taken off the page.
     */

    // Per-panel state, keyed by the element so a panel re-fetched into
    // the tab starts from its markup rather than from what the last
    // one was showing.
    var analystState = new WeakMap();

    /**
     * @param {Element} panel
     * @return {Object} The panel's sort and kind, defaulted
     */
    function analystStateOf(panel) {
        if (!analystState.has(panel)) {
            analystState.set(panel, {sort: 'newest', kind: 'all'});
        }
        return analystState.get(panel);
    }

    /**
     * Reorder and re-show the thread from the panel's current state.
     *
     * @param {Element} panel
     */
    function refreshAnalyst(panel) {
        var thread = panel.querySelector('.vpa-thread');
        if (!thread) {
            return;
        }
        var state = analystStateOf(panel);
        var items = Array.prototype.slice.call(
            thread.querySelectorAll('[data-vp-a-item]')
        );

        items.sort(function (a, b) {
            if (state.sort === 'org') {
                var byOrg = a.dataset.vpAOrg.localeCompare(b.dataset.vpAOrg);
                if (byOrg !== 0) {
                    return byOrg;
                }
            }
            var dates = a.dataset.vpADate.localeCompare(b.dataset.vpADate);
            if (dates !== 0) {
                // Oldest first only when asked; by organisation keeps
                // each organisation's own thread in the order it was
                // written, newest at the top like everything else.
                return state.sort === 'oldest' ? dates : -dates;
            }
            // Same day: the order the endpoint sent, which is the order
            // the rows came back in.
            return parseInt(a.dataset.vpAOrder, 10)
                - parseInt(b.dataset.vpAOrder, 10);
        });

        var shown = 0;
        items.forEach(function (item) {
            thread.appendChild(item);
            var on = state.kind === 'all'
                || item.dataset.vpAKind === state.kind;
            item.classList.toggle('d-none', !on);
            if (on) {
                shown++;
            }
        });

        var empty = panel.querySelector('[data-vp-a-empty]');
        if (empty) {
            empty.classList.toggle('d-none', shown > 0);
        }

        panel.querySelectorAll('[data-vp-a-sort]').forEach(function (button) {
            var active = button.dataset.vpASort === state.sort;
            button.classList.toggle('active', active);
            button.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
        panel
            .querySelectorAll('[data-vp-a-kind-filter]')
            .forEach(function (button) {
                var active = button.dataset.vpAKindFilter === state.kind;
                button.classList.toggle('active', active);
                button.setAttribute('aria-pressed', active ? 'true' : 'false');
            });
    }

    /**
     * Open or close one item's replies.
     *
     * @param {Element} button
     */
    function toggleAnalystReplies(button) {
        /*
         * The replies block is the next sibling of the .vp-analyst the
         * button sits in — never a descendant of it. Walking down from
         * the item would find the replies of a nested item first, and
         * open somebody else's sub-thread.
         */
        var claim = button.closest('.vp-analyst');
        var replies = claim ? claim.nextElementSibling : null;
        if (!replies || !replies.classList.contains('vpa-replies')) {
            return;
        }
        var open = replies.classList.toggle('d-none');
        button.setAttribute('aria-expanded', open ? 'false' : 'true');
        var caret = button.querySelector('i');
        if (caret) {
            caret.classList.toggle('fa-caret-down', !open);
            caret.classList.toggle('fa-caret-right', open);
        }
    }

    /**
     * A strip marker and the table rows it stands for, lit together.
     *
     * A merged marker carries every organisation it swallowed, so
     * hovering the badged one highlights all of them — which is the
     * only way the reader finds out which organisations collided.
     *
     * @param {Element} mark
     * @param {boolean} on
     */
    function highlightAnalystMark(mark, on) {
        var panel = mark.closest('[data-vp-analyst-standing]');
        if (!panel) {
            return;
        }
        mark.classList.toggle('vpa-mark-on', on);
        (mark.dataset.vpAMark || '').split('|').forEach(function (org) {
            panel
                .querySelectorAll('[data-vp-a-org]')
                .forEach(function (row) {
                    if (row.dataset.vpAOrg === org) {
                        row.classList.toggle('vpa-row-on', on);
                    }
                });
        });
    }

    /**
     * @param {Element} root
     */
    function initAnalyst(root) {
        var panels = root.querySelectorAll
            ? root.querySelectorAll('[data-vp-analyst-thread]')
            : [];
        panels.forEach(function (panel) {
            refreshAnalyst(panel);
        });
    }

    /**
     * @param {Event} event
     * @return {boolean} Whether the click belonged to this tab
     */
    function onAnalystClick(event) {
        var sort = event.target.closest('[data-vp-a-sort]');
        if (sort) {
            var sortPanel = sort.closest('[data-vp-analyst-thread]');
            if (sortPanel) {
                analystStateOf(sortPanel).sort = sort.dataset.vpASort;
                refreshAnalyst(sortPanel);
            }
            return true;
        }

        var kind = event.target.closest('[data-vp-a-kind-filter]');
        if (kind) {
            var kindPanel = kind.closest('[data-vp-analyst-thread]');
            if (kindPanel) {
                analystStateOf(kindPanel).kind = kind.dataset.vpAKindFilter;
                refreshAnalyst(kindPanel);
            }
            return true;
        }

        var replies = event.target.closest('[data-vp-a-replies]');
        if (replies) {
            toggleAnalystReplies(replies);
            return true;
        }

        return false;
    }


    /* ==================================================================
     * Timeline tab
     * ------------------------------------------------------------------
     * One window, and two regions that read it. The spine is a control:
     * brushing it sets the window, and the lanes and the chronology both
     * re-scope to whatever it says. Neither of the two owns the window —
     * they read a shared object, which is why they can never disagree
     * about which entries they are describing.
     *
     * Everything is client-side against rows already in the DOM. The
     * lanes are redrawn because a mark's position is a fraction of the
     * window and the window moves; the chronology is only shown and
     * hidden, because a day is wholly inside a window or wholly outside
     * it and its grouping therefore never changes.
     * ================================================================== */

    var tl = {
        data: null,
        // Bin index bounds of the brush, or null while the window is the
        // one the panel was rendered with.
        brush: null,
        // A lane key, or null for every source.
        filter: null,
        // Runs the reader has opened, by their run id.
        expanded: null,
        // Whether the reader asked past the display limit.
        showAll: false,
        spine: null,
    };

    var TL_LIMIT = 14;

    /**
     * The window the whole tab is currently describing.
     *
     * @return {{from: string, to: string, moved: boolean}|null}
     */
    function tlWindow() {
        if (!tl.data) {
            return null;
        }
        if (!tl.brush) {
            return {
                from: tl.data.window.from,
                to: tl.data.window.to,
                moved: false,
            };
        }
        return {
            from: tl.data.bins[tl.brush.from].from,
            to: tl.data.bins[tl.brush.to].to,
            moved: true,
        };
    }

    /**
     * Which bins the current window covers, so the brush can be painted
     * over the window the panel was rendered with and not only over one
     * the reader dragged.
     *
     * @return {{from: number, to: number}}
     */
    function tlBins() {
        if (tl.brush) {
            return tl.brush;
        }
        var window_ = tl.data.window;
        var from = null;
        var to = null;
        tl.data.bins.forEach(function (bin, index) {
            if (bin.to >= window_.from && bin.from <= window_.to) {
                if (from === null) {
                    from = index;
                }
                to = index;
            }
        });
        return {
            from: from === null ? 0 : from,
            to: to === null ? tl.data.bins.length - 1 : to,
        };
    }

    /**
     * Every dated entry, read off the rows the template rendered.
     *
     * The rows are the one copy of the entry set: the lanes derive their
     * marks from these and the chronology *is* these, so the two cannot
     * describe different sets.
     *
     * @param {Element} panel
     * @return {Array}
     */
    function tlEntries(panel) {
        var out = [];
        panel.querySelectorAll('[data-vp-tl-at]').forEach(function (row) {
            var main = row.querySelector('.vp-tl-main');
            out.push({
                at: row.dataset.vpTlAt,
                day: row.dataset.vpTlDay,
                source: row.dataset.vpTlSource,
                precision: row.dataset.vpTlPrecision,
                spanTo: row.dataset.vpTlSpanTo || null,
                ref: row.dataset.vpTlRef || '',
                title: main ? main.textContent.trim() : '',
            });
        });
        return out;
    }

    /**
     * @param {string} text
     * @return {string}
     */
    function tlEscape(text) {
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    /**
     * @param {string} at `Y-m-d H:i:s`, which the fixture writes in UTC
     * @return {number} Epoch milliseconds
     */
    function tlStamp(at) {
        return Date.parse(at.replace(' ', 'T') + 'Z');
    }

    /**
     * Paint the brush over the bins the window covers, and show the
     * reset control only once the reader has moved it.
     *
     * @param {Element} panel
     */
    function tlPaintBrush(panel) {
        window.VP.brush.paint(panel, tlBins(), tl.data.bins.length);

        var reset = panel.querySelector('[data-vp-tl-reset]');
        if (reset) {
            reset.hidden = !tl.brush;
        }
    }

    /**
     * Redraw every lane's marks for the current window, and recount it.
     *
     * A lane that MISP cannot date has no axis element at all, so it is
     * untouched here — its hatch and its count are properties of the
     * schema rather than of the window, and moving the brush must not
     * make them flicker.
     *
     * @param {Element} panel
     * @param {Array} entries
     */
    function tlDrawLanes(panel, entries) {
        var window_ = tlWindow();
        var geometry = tl.data.lane;
        var from = tlStamp(window_.from + ' 00:00:00');
        var to = tlStamp(window_.to + ' 23:59:59');
        var span = Math.max(1, to - from);

        function xFor(at) {
            var fraction = (tlStamp(at) - from) / span;
            fraction = Math.max(0, Math.min(1, fraction));
            return Math.round(
                fraction * (geometry.width - geometry.mark) * 10
            ) / 10;
        }

        panel.querySelectorAll('[data-vp-tl-axis]').forEach(function (axis) {
            var sources = (axis.dataset.vpTlSources || '').split(',');
            var spans = axis.dataset.vpTlDraw === 'spans';
            var hatched = !!axis.querySelector('.vp-lane-fill');
            var svg = axis.querySelector('[data-vp-tl-marks]');
            var mine = entries.filter(function (entry) {
                return sources.indexOf(entry.source) !== -1
                    && entry.day >= window_.from
                    && entry.day <= window_.to;
            });

            if (svg) {
                var marks = '';
                mine.forEach(function (entry) {
                    var x = xFor(entry.at);
                    var hue = 'var(--vp-tl-' + entry.source + ')';
                    var title = '<title>' + tlEscape(entry.title)
                        + '</title>';
                    if (spans) {
                        var end = xFor(entry.spanTo || entry.at);
                        var width = Math.max(geometry.mark, end - x);
                        marks += '<rect class="vp-lane-span" x="' + x
                            + '" y="19" width="' + width
                            + '" height="7" rx="3" style="--vp-tl-hue: '
                            + hue + ';">' + title + '</rect>';
                        return;
                    }
                    if (hatched) {
                        // A mark on a hatch needs a ground, or the one
                        // recorded edit disappears into the reason there
                        // are no others.
                        marks += '<rect class="vp-lane-ground" x="'
                            + (x - 3) + '" y="9" width="11" height="19"'
                            + ' rx="2"></rect>';
                    }
                    marks += '<rect class="vp-lane-mark" x="' + x
                        + '" y="12" width="' + geometry.mark
                        + '" height="13" rx="1.5" style="--vp-tl-hue: '
                        + hue + ';">' + title + '</rect>';
                });
                svg.innerHTML = marks;
            }

            // The span labels are HTML over the axis, never SVG text:
            // the axis is stretched with preserveAspectRatio="none",
            // which would smear a word along with it.
            axis.querySelectorAll('.vp-lane-tag').forEach(function (tag) {
                tag.remove();
            });
            if (spans) {
                mine.forEach(function (entry) {
                    var tag = document.createElement('span');
                    tag.className = 'vp-lane-tag';
                    tag.style.left =
                        (100 * xFor(entry.at)) / geometry.width + '%';
                    tag.textContent = entry.ref;
                    axis.insertBefore(tag, svg);
                });
            }

            var key = axis.dataset.vpTlAxis;
            var cell = panel.querySelector(
                '[data-vp-tl-count="' + key + '"]'
            );
            if (!cell) {
                return;
            }
            setText(cell, '[data-vp-tl-count-n]', mine.length);
            var breakdown = {};
            mine.forEach(function (entry) {
                breakdown[entry.source] = (breakdown[entry.source] || 0) + 1;
            });
            var parts = [];
            Object.keys(breakdown).forEach(function (source) {
                parts.push(breakdown[source] + ' ' + tlLabel(source));
            });
            setText(cell, '[data-vp-tl-count-why]', parts.join(', '));
        });
    }

    /**
     * @param {string} source
     * @return {string} The label the spine's legend already uses, so one
     *                  source is never named two ways on one tab.
     */
    function tlLabel(source) {
        var found = source;
        tl.data.datasets.forEach(function (dataset) {
            if (dataset.source === source) {
                found = dataset.label;
            }
        });
        return found;
    }

    /**
     * How many entries a collapsed run stands for.
     *
     * @param {Element} list
     * @param {string} run
     * @return {number}
     */
    function tlRunSize(list, run) {
        return list.querySelectorAll(
            '[data-vp-tl-in-run="' + run + '"]'
        ).length;
    }

    /**
     * Show the entries in the window, hide the rest, and keep every
     * count that describes them in step.
     *
     * @param {Element} panel
     */
    function tlRefreshList(panel) {
        var list = panel.querySelector('[data-vp-tl-list]');
        if (!list) {
            return;
        }
        var window_ = tlWindow();
        var sources = tl.filter === null ? null : tl.filter.split(',');

        /*
         * What the window holds, counted before anything is decided
         * about how to show it. Collapsing a run is a display device:
         * its entries are still in the window, and counting them only
         * when they are on screen is how this header would come to
         * disagree with the lanes above it.
         */
        var matched = 0;
        var tally = { exact: 0, partial: 0 };
        list.querySelectorAll('[data-vp-tl-at]').forEach(function (row) {
            var day = row.dataset.vpTlDay;
            if (day < window_.from || day > window_.to) {
                return;
            }
            if (sources
                && sources.indexOf(row.dataset.vpTlSource) === -1) {
                return;
            }
            matched++;
            tally[row.dataset.vpTlPrecision]++;
        });

        // What is on screen, and how many entries that accounts for —
        // which is not the same number, because one summary row stands
        // for a whole run.
        var units = 0;
        var covered = 0;
        list.querySelectorAll('[data-vp-tl-row]').forEach(function (row) {
            var run = row.dataset.vpTlRun;
            var inRun = row.dataset.vpTlInRun;
            var day = row.dataset.vpTlDay;
            var keep = day >= window_.from && day <= window_.to;
            if (keep && sources) {
                keep = sources.indexOf(row.dataset.vpTlSource) !== -1;
            }
            // A summary row and the rows it stands for are never both
            // on screen: one of them is the reader's current answer.
            if (keep && run) {
                keep = !tl.expanded[run];
            }
            if (keep && inRun) {
                keep = !!tl.expanded[inRun];
            }
            if (!keep) {
                row.hidden = true;
                return;
            }
            if (!tl.showAll && units >= TL_LIMIT) {
                row.hidden = true;
                return;
            }
            units++;
            covered += run ? tlRunSize(list, run) : 1;
            row.hidden = false;
        });

        // A day heading with nothing under it is a claim that something
        // happened that day.
        list.querySelectorAll('[data-vp-tl-day-head]').forEach(
            function (head) {
                var day = head.dataset.vpTlDayHead;
                var visible = list.querySelector(
                    '[data-vp-tl-day="' + day + '"]:not([hidden])'
                );
                head.hidden = !visible;
            }
        );

        setText(list, '[data-vp-tl-tally-exact]', tally.exact);
        setText(list, '[data-vp-tl-tally-part]', tally.partial);
        setText(panel, '[data-vp-tl-window-count]', matched);
        var label = panel.querySelector('[data-vp-tl-window-label]');
        if (label) {
            label.textContent = window_.from + ' → ' + window_.to;
        }

        var foot = list.querySelector('[data-vp-tl-foot]');
        if (foot) {
            foot.hidden = covered >= matched;
            setText(foot, '[data-vp-tl-more-n]', matched - covered);
        }

        // Only a brush or a filter can empty this list. A value with
        // nothing dated has its own empty state from the template, and
        // "none in this window" over it would be a different claim.
        var blank = list.querySelector('[data-vp-tl-blank]');
        if (blank) {
            blank.hidden = matched > 0
                || !list.querySelector('[data-vp-tl-at]');
        }

        var note = list.querySelector('[data-vp-tl-filter-note]');
        if (note) {
            note.hidden = tl.filter === null;
        }
    }

    /**
     * @param {Element} panel
     */
    function refreshTimeline(panel) {
        if (!tl.data) {
            return;
        }
        tlPaintBrush(panel);
        tlDrawLanes(panel, tlEntries(panel));
        tlRefreshList(panel);
    }

    /**
     * The spine. Stacked bars, one segment per source, over twelve
     * months — and the colours are the tokens the lanes read, so a
     * segment and the lane beneath it are the same colour by
     * construction rather than by being kept in step.
     *
     * @param {Element} canvas
     * @return {Chart}
     */
    function buildTimelineSpine(canvas) {
        var config = window.VP.chart.resolve({
            type: 'bar',
            data: {
                labels: tl.data.bins.map(function (bin) {
                    return bin.label;
                }),
                datasets: tl.data.datasets.map(function (dataset) {
                    return {
                        label: dataset.label,
                        data: dataset.data,
                        backgroundColor: dataset.colour,
                        borderWidth: 0,
                        barPercentage: 0.72,
                        categoryPercentage: 0.86,
                    };
                }),
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: false,
                scales: {
                    x: {
                        stacked: true,
                        grid: { display: false },
                        ticks: {
                            color: 'var(--bs-secondary-color)',
                            font: { size: 10 },
                        },
                    },
                    y: {
                        stacked: true,
                        beginAtZero: true,
                        grid: { color: 'var(--bs-border-color-translucent)' },
                        ticks: {
                            color: 'var(--bs-secondary-color)',
                            font: { size: 10 },
                            precision: 0,
                        },
                    },
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            title: function (items) {
                                return tl.data.bins[items[0].dataIndex].title;
                            },
                        },
                    },
                },
            },
        }, canvas);
        return new Chart(canvas, config);
    }

    /**
     * Drag on the spine. A click clears the brush — the same thing
     * `Reset window` offers, for a reader who never found it.
     *
     * The spine stays monthly however narrow the brush gets: four of
     * this tab's seven sources cannot be dated to the day, so a finer
     * bar would claim a precision the data has not got.
     *
     * @param {Element} panel
     */
    function wireTimelineBrush(panel) {
        window.VP.brush.attach(panel.querySelector('[data-vp-brush]'), {
            count: function () {
                return tl.data.bins.length;
            },
            range: function (from, to) {
                tl.brush = { from: from, to: to };
                tl.showAll = false;
                refreshTimeline(panel);
            },
            clear: function () {
                tl.brush = null;
                tl.showAll = false;
                refreshTimeline(panel);
            },
        });
    }

    /* --------------------------------------------------------------
     * The History tab
     * --------------------------------------------------------------
     * The facets, the reveals, the search and the row set are all the
     * shared list contract (`00-shared.md` §5) and add nothing here.
     * Five behaviours the contract does not cover, and this panel does:
     *
     * 1. A section the filters empty is dropped and counted, not
     *    dimmed. Phase 16 greyed it to `0 of 9` and kept it, which is
     *    right at six sections; at a hundred and ninety the dimmed ones
     *    are the whole problem restated, so the fact that they exist is
     *    now a sentence above the list.
     * 2. Rows page inside their own section and never across the
     *    union, because a union of N event-scoped queries has no
     *    stable ordering key to page on.
     * 3. Sections page too, over whichever of them the filters left.
     * 4. The rail's counts follow the period and nothing else. Not the
     *    facet selections: a count that followed its own group would
     *    drop every sibling to zero the moment one was ticked, which is
     *    a rail nobody can use twice.
     * 5. A brush over a monthly chart writes the two date inputs and
     *    fires their `change`, so the whole existing filter path runs
     *    unchanged and the period stays statable as two dates. Where it
     *    reaches past the window the panel was fetched for, it re-fetches
     *    — the one control on this tab that goes back to the server.
     * -------------------------------------------------------------- */

    var audit = {
        // The plan, the rendered window and the log's span, as the
        // panel was sent them.
        data: null,
        chart: null,
        // The visible span, which the four zoom buttons move and
        // nothing else does. Null until the panel has a plan.
        zoom: null,
        labels: null,
    };

    /**
     * The bars currently drawn, each with the day offsets it covers.
     *
     * Everything about this chart that used to read a fixed array of
     * months reads this instead, because after §13.2 there is no fixed
     * array: what a bar is worth follows from how far the reader has
     * zoomed in.
     *
     * @return {Array} `bucket`, `from`, `to`
     */
    function auditBars() {
        return audit.zoom ? audit.zoom.window() : [];
    }

    /**
     * The whole log's bounds, which are not the visible span's.
     *
     * The period control offers the log and never the view: zooming
     * changes what the reader can see, and a filter that silently
     * narrowed to it would make the two gestures one after all.
     *
     * @return {{from: string, to: string}|null}
     */
    function auditWhole() {
        if (!audit.data || !audit.data.chart) {
            return null;
        }
        return {
            from: audit.data.chart.from,
            to: audit.data.chart.to,
        };
    }

    /**
     * The period the reader currently has set, as days, or nulls where
     * they have set none.
     *
     * Read off the same two inputs `activePeriod()` reads, because they
     * are the same control: the brush is a second way to write them and
     * never a second place the period lives.
     *
     * @param {Element} list
     * @return {{from: string|null, to: string|null}}
     */
    function auditTypedPeriod(list) {
        function day(selector) {
            var input = list.querySelector(selector);
            if (!input || input.value === '') {
                return null;
            }
            return input.value.slice(0, 10);
        }
        return {
            from: day('[data-vp-filter-from]'),
            to: day('[data-vp-filter-to]'),
        };
    }

    /**
     * The period the panel is describing: what the reader typed or
     * brushed, falling back to the window it was fetched for and then to
     * the whole chart.
     *
     * @param {Element} list
     * @return {{from: string, to: string}|null}
     */
    function auditPeriod(list) {
        var whole = auditWhole();
        if (!whole) {
            return null;
        }
        var typed = auditTypedPeriod(list);
        var rendered = audit.data.window;
        return {
            from: typed.from || (rendered ? rendered.from : whole.from),
            to: typed.to || (rendered ? rendered.to : whole.to),
        };
    }

    /**
     * Which month bars the period covers, so the brush can be painted
     * over a window the reader never dragged as well as over one they
     * did.
     *
     * @param {Element} list
     * @return {{from: number, to: number}|null}
     */
    function auditBins(list) {
        var period = auditPeriod(list);
        var bars = auditBars();
        if (!period || !bars.length) {
            return null;
        }
        var from = null;
        var to = null;
        bars.forEach(function (bar, index) {
            if (bar.bucket.to >= period.from
                && bar.bucket.from <= period.to
            ) {
                if (from === null) {
                    from = index;
                }
                to = index;
            }
        });
        // A period outside the visible span covers no bar. Phase 19
        // collapsed the brush onto the nearer edge, which was a
        // once-in-a-while state then and a routine one now that the
        // reader can zoom away from their own period — and an edge
        // window reads as a selection at that edge. `outside` instead,
        // which paints as a fully dimmed strip: none of what is on
        // screen is in the period, which is exactly true.
        if (from === null) {
            return 'outside';
        }
        return { from: from, to: to };
    }

    /**
     * @param {Element} list
     */
    function paintAuditBrush(list) {
        var bars = auditBars();
        var bounds = auditBins(list);
        if (bounds === null || !bars.length) {
            return;
        }
        window.VP.brush.paint(
            list,
            bounds === 'outside' ? null : bounds,
            bars.length
        );
        // The period moves without the chart moving — a typed date, a
        // cleared one, a drag — and whether it is still on screen is
        // what the zoom's note says, so that goes with it.
        paintAuditZoom(list);
    }

    /**
     * How many entries fall in one bar, summed out of the per-day
     * tally the panel was sent.
     *
     * The server ships one count per day and no totals per bar, which
     * is what lets three grains cost one tally rather than three
     * (§13.1). Summing a slice is the whole of the re-aggregation this
     * phase needed.
     *
     * @param {{from: number, to: number}} bar Day offsets
     * @return {number}
     */
    function auditBarTotal(bar) {
        var counts = audit.data.chart.counts;
        var total = 0;
        for (var i = bar.from; i <= bar.to; i += 1) {
            total += counts[i] || 0;
        }
        return total;
    }

    /**
     * The bars. No axes, for the reason the Sightings navigator has
     * none: the brush over it is positioned as a plain fraction of the
     * strip's width, and an axis would put the bars somewhere other
     * than where the drag maths says they are.
     *
     * Chart.js is handed a fresh dataset on every zoom step rather than
     * being told to rescale, because a zoom changes how many bars there
     * are and what each one covers — there is no view transform here,
     * only a different set of bars.
     *
     * @param {Element} canvas
     * @return {Object}
     */
    function buildAuditChart(canvas) {
        var bars = auditBars();
        var config = window.VP.chart.resolve({
            type: 'bar',
            data: {
                labels: bars.map(function (bar) {
                    return bar.bucket.label;
                }),
                datasets: [{
                    data: bars.map(auditBarTotal),
                    backgroundColor: 'var(--bs-secondary-color)',
                    borderWidth: 0,
                    barPercentage: 1,
                    categoryPercentage: 0.88,
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
                    tooltip: {
                        displayColors: false,
                        callbacks: {
                            title: function (items) {
                                var bar = bars[items[0].dataIndex];
                                return bar ? bar.bucket.title : '';
                            },
                        },
                    },
                },
            },
        }, canvas);
        return new Chart(canvas, config);
    }

    /**
     * Redraw the chart, the brush over it and the zoom's own caption
     * after a zoom step.
     *
     * The period is deliberately not touched. That is §13.3's whole
     * point: these buttons change what the chart shows, and a reader
     * who has filtered to March keeps that filter while they look at
     * the rest of the year.
     *
     * @param {Element} list
     */
    function redrawAuditChart(list) {
        // `boot` hands back a refresh rather than the chart, and it is
        // the right thing to call: it rebuilds from the same builder,
        // destroys the instance it replaces, and leaves the theme
        // observer watching. Booting again per zoom step would stack a
        // MutationObserver on the canvas each time.
        if (audit.chart) {
            audit.chart.refresh();
        }
        // Paints the zoom too, because the note is about the pair.
        paintAuditBrush(list);
    }

    /**
     * Paint the zoom control, and tell the reader when the period they
     * have set is no longer on screen.
     *
     * @param {Element} list
     */
    function paintAuditZoom(list) {
        var zoom = list.querySelector('[data-vp-zoom]');
        if (!zoom || !audit.zoom) {
            return;
        }
        var away = auditBins(list) === 'outside';
        var labels = audit.labels || {};
        window.VP.zoom.paint(
            zoom,
            audit.zoom,
            labels,
            away ? (labels.away || null) : null
        );
    }

    /**
     * Write a brushed range into the two date inputs and let their own
     * `change` do the rest.
     *
     * The whole point of decision 5: `activePeriod()`, `refreshList()`,
     * the pagers and the rail all run exactly as they do when someone
     * types, so there is one filter path and not two.
     *
     * @param {Element} list
     * @param {number} from Bar index, over the drawn bars
     * @param {number} to Bar index
     */
    function writeAuditPeriod(list, from, to) {
        var bars = auditBars();
        if (!bars.length) {
            return;
        }
        var pairs = [
            ['[data-vp-filter-from]', bars[from].bucket.from + 'T00:00'],
            ['[data-vp-filter-to]', bars[to].bucket.to + 'T23:59'],
        ];
        pairs.forEach(function (pair) {
            var input = list.querySelector(pair[0]);
            if (input) {
                input.value = pair[1];
            }
        });
        listPages.set(list, 1);
        resetAuditPages(list);
        refreshList(list);
    }

    /**
     * @param {Element} list
     */
    function clearAuditPeriod(list) {
        list.querySelectorAll(
            '[data-vp-filter-from], [data-vp-filter-to]'
        ).forEach(function (input) {
            input.value = '';
        });
        listPages.set(list, 1);
        resetAuditPages(list);
        refreshList(list);
    }

    /**
     * Whether the period the reader has asked for is inside the window
     * the panel was fetched for.
     *
     * Inside is instant, because the rows are already here. Outside is a
     * request, because they are not — and pretending otherwise would
     * show them an empty period over a log that has entries in it.
     *
     * @param {Element} list
     * @return {boolean}
     */
    function auditWithinWindow(list) {
        if (!audit.data || audit.data.window === null) {
            return true;
        }
        var typed = auditTypedPeriod(list);
        var window_ = audit.data.window;
        return (typed.from === null || typed.from >= window_.from)
            && (typed.to === null || typed.to <= window_.to);
    }

    /**
     * Re-fetch the panel for a period, or for the whole log.
     *
     * @param {Element} list
     * @param {string} scope `all`, or `from/to`
     */
    function fetchAuditScope(list, scope) {
        var base = list.dataset.vpAuditBase;
        var container = list.closest('.ajax-tab-content');
        if (!base || !container || !window.reloadAjaxTabIndex) {
            return;
        }
        window.reloadAjaxTabIndex(
            container,
            scope === '' ? base : base + '/' + scope
        );
    }

    /**
     * @param {Element} list
     */
    function fetchAuditPeriod(list) {
        var typed = auditTypedPeriod(list);
        var whole = auditWhole();
        var from = typed.from || whole.from;
        var to = typed.to || whole.to;
        fetchAuditScope(list, from + '/' + to);
    }

    /**
     * Drag on the chart. A click clears the period — the same gesture
     * the Sightings and Timeline brushes offer, and the one this tab's
     * `Clear all` offers to a reader who found the button instead.
     *
     * This is the caller that needs `settle`: a range inside the
     * fetched window is instant, and one past it is a request, so the
     * check runs once on release rather than every few pixels of the
     * drag.
     *
     * @param {Element} list
     */
    function wireAuditBrush(list) {
        window.VP.brush.attach(list.querySelector('[data-vp-brush]'), {
            count: function () {
                return auditBars().length;
            },
            range: function (from, to) {
                writeAuditPeriod(list, from, to);
            },
            clear: function () {
                clearAuditPeriod(list);
            },
            settle: function () {
                if (!auditWithinWindow(list)) {
                    fetchAuditPeriod(list);
                }
            },
        });
    }

    /**
     * Re-tally the rail against the period, over every row the panel
     * holds rather than over the ones a pager left on screen.
     *
     * The facet selections are deliberately not applied. A group whose
     * counts followed its own ticks would drop every sibling to zero as
     * soon as one was ticked; a group that followed the *other* groups
     * would answer a different question in each of the four. The period
     * is a narrowing of the subject — this value, in March — and that is
     * what every log browser in this class scopes its sidebar to.
     *
     * @param {Element} list
     * @param {Object} period From `activePeriod()`
     */
    function retallyAuditFacets(list, period) {
        var counts = {};
        listRows(list).forEach(function (row) {
            if (!rowMatchesPeriod(row, period)) {
                return;
            }
            (row.dataset.vpFacet || '').split(/\s+/).forEach(function (t) {
                if (t !== '') {
                    counts[t] = (counts[t] || 0) + 1;
                }
            });
        });

        list.querySelectorAll('[data-vp-facet-group]')
            .forEach(function (group) {
                var max = 0;
                var rows = [];
                group.querySelectorAll('.vp-facet').forEach(function (row) {
                    var box = row.querySelector('[data-vp-facet-key]');
                    if (!box) {
                        return;
                    }
                    var token = box.dataset.vpFacetKey + ':' + box.value;
                    var count = counts[token] || 0;
                    max = Math.max(max, count);
                    rows.push({ row: row, box: box, count: count });
                });
                rows.forEach(function (entry) {
                    setText(entry.row, '.vp-facet-count', entry.count);
                    var bar = entry.row.querySelector('.vp-facet-bar');
                    if (bar) {
                        bar.style.setProperty(
                            '--vp-facet-share',
                            (max > 0
                                ? Math.round((entry.count / max) * 100)
                                : 0) + '%'
                        );
                    }
                    var zero = entry.count === 0;
                    entry.row.classList.toggle('opacity-50', zero);
                    // Never disable a box the reader has ticked: taking
                    // the control away would strand the filter it is
                    // still applying.
                    entry.box.disabled = zero && !entry.box.checked;
                });
            });
    }

    /**
     * How many sections the filters emptied, said out loud.
     *
     * The period variant names the dates, because decision 4 turns on
     * it: a box disappearing has to read as a filter narrowing, and a
     * count that named no period would read as data going missing.
     *
     * @param {Element} list
     * @param {number} dropped
     */
    function updateAuditDropped(list, dropped) {
        var note = list.querySelector('[data-vp-audit-dropped]');
        if (!note) {
            return;
        }
        note.classList.toggle('d-none', dropped === 0);
        if (dropped === 0) {
            note.textContent = '';
            return;
        }
        var typed = auditTypedPeriod(list);
        if (typed.from !== null || typed.to !== null) {
            var period = auditPeriod(list);
            note.textContent = (note.dataset.vpAuditDropPeriod || '')
                .replace('%1$s', dropped)
                .replace('%2$s', auditDate(period.from))
                .replace('%3$s', auditDate(period.to));
            return;
        }
        note.textContent = (note.dataset.vpAuditDropPlain || '')
            .replace('%1$s', dropped);
    }

    /**
     * A `Y-m-d` as the day the rest of the tab prints.
     *
     * Built from the chart's own month titles rather than from a locale
     * call, so a bar's tooltip and the sentence naming the period it
     * covers say the same month in the same words.
     *
     * @param {string} day
     * @return {string}
     */
    function auditDate(day) {
        // The monthly grain by name and not whichever one is drawn:
        // this wants `June 2024` to put a day inside, and at daily bars
        // the drawn bucket's own title is already a date.
        var title = audit.zoom
            ? audit.zoom.titleAt('month', day.slice(0, 10))
            : null;
        if (title === null) {
            return day;
        }
        return parseInt(day.slice(8, 10), 10) + ' ' + title;
    }

    /**
     * Whether the reader has this section open, remembered on the
     * element so a filter can close it and clearing the filter can put
     * it back the way they left it.
     *
     * @param {Element} section
     * @return {boolean}
     */
    function auditOpen(section) {
        if (section.dataset.vpAuditOpen === undefined) {
            var body = section.querySelector('[data-vp-audit-body]');
            section.dataset.vpAuditOpen =
                body && !body.classList.contains('d-none') ? '1' : '0';
        }
        return section.dataset.vpAuditOpen === '1';
    }

    /**
     * @param {Element} section
     */
    function applyAuditOpen(section) {
        var blank = section.dataset.vpAuditBlank === '1';
        var open = auditOpen(section) && !blank;
        var body = section.querySelector('[data-vp-audit-body]');
        if (body) {
            body.classList.toggle('d-none', !open);
        }
        var toggle = section.querySelector('[data-vp-audit-toggle]');
        if (toggle) {
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        }
        var chevron = section.querySelector('[data-vp-audit-chevron]');
        if (chevron) {
            chevron.classList.toggle('fa-chevron-down', open);
            chevron.classList.toggle('fa-chevron-right', !open);
        }
    }

    /**
     * The section's own count line: `9 entries` with no filter set,
     * `3 of 9 entries` with one. Two numbers that disagree — a header
     * still claiming nine over three visible rows — is the thing this
     * exists to prevent.
     *
     * A section the filters empty is taken off the list rather than
     * dimmed. Phase 16 dimmed it, which is the right call at six
     * sections and the wrong one at a hundred and ninety: the dimmed
     * ones become the list. What it was — a section with nothing in it
     * for this period — is a sentence above the sections instead, where
     * one line covers all of them.
     *
     * @param {Element} section
     * @param {number} shown
     * @param {number} activeCount
     * @return {boolean} Whether the filters emptied it
     */
    function setAuditCount(section, shown, activeCount) {
        var total = parseInt(section.dataset.vpAuditTotal || '0', 10);
        var blank = activeCount > 0 && shown === 0;
        section.dataset.vpAuditBlank = blank ? '1' : '0';

        var label = section.querySelector('[data-vp-audit-count]');
        if (label) {
            label.textContent = activeCount === 0
                ? (label.dataset.vpAuditPlain || label.textContent)
                : (label.dataset.vpAuditTpl || '')
                    .replace('%1$s', shown)
                    .replace('%2$s', total);
        }
        applyAuditOpen(section);
        return blank;
    }

    /**
     * Page each section over its own surviving rows, then page the
     * sections themselves.
     *
     * Replaces the list-level `paginate()` for this panel rather than
     * running beside it: one pager over a union of sections would page
     * rows out of one section to make room for another's, and the range
     * it printed would belong to neither.
     *
     * @param {Element} list
     * @param {Array<Element>} filtered
     * @param {number} activeCount
     */
    function paginateAuditSections(list, filtered, activeCount) {
        var bySection = new Map();
        filtered.forEach(function (row) {
            var section = row.closest('[data-vp-audit-section]');
            if (!section) {
                return;
            }
            if (!bySection.has(section)) {
                bySection.set(section, []);
            }
            bySection.get(section).push(row);
        });

        var standing = [];
        var dropped = 0;
        list.querySelectorAll('[data-vp-audit-section]')
            .forEach(function (section) {
                var rows = bySection.get(section) || [];
                var pager = section.querySelector('[data-vp-pager]');
                var size = pager
                    ? parseInt(pager.dataset.vpPageSize, 10)
                    : 0;

                if (!pager || !size || size < 1) {
                    rows.forEach(function (row) {
                        row.classList.remove('d-none');
                    });
                } else {
                    var pages = Math.max(1, Math.ceil(rows.length / size));
                    var page = Math.min(
                        parseInt(section.dataset.vpAuditPage || '1', 10),
                        pages
                    );
                    section.dataset.vpAuditPage = page;
                    var from = (page - 1) * size;
                    var to = Math.min(from + size, rows.length);
                    rows.forEach(function (row, index) {
                        row.classList.toggle(
                            'd-none',
                            index < from || index >= to
                        );
                    });
                    setText(
                        section,
                        '[data-vp-page-from]',
                        rows.length ? from + 1 : 0
                    );
                    setText(section, '[data-vp-page-to]', to);
                    setText(section, '[data-vp-page-of]', rows.length);
                    renderPager(pager, page, pages);
                }
                var blank = setAuditCount(section, rows.length, activeCount);
                if (blank) {
                    dropped++;
                }
                /*
                 * The event-level section is pinned rather than paged.
                 * It is not an occurrence, and a reader who paged the
                 * publications off the bottom of page one would have to
                 * guess which page they went to.
                 */
                if (!section.hasAttribute('data-vp-audit-pinned')) {
                    standing.push(section);
                }
                section.classList.toggle('d-none', blank);
            });

        pageAuditSections(list, standing);
        updateAuditDropped(list, dropped);
    }

    /**
     * Page the sections the filters left standing.
     *
     * @param {Element} list
     * @param {Array<Element>} standing Blank ones already excluded
     */
    function pageAuditSections(list, standing) {
        var host = ownNode(list, '[data-vp-audit-sectionpager]');
        var pager = host ? host.querySelector('[data-vp-pager]') : null;
        if (!pager) {
            return;
        }
        var size = parseInt(pager.dataset.vpPageSize, 10);
        if (!size || size < 1) {
            return;
        }
        var pages = Math.max(1, Math.ceil(standing.length / size));
        var page = Math.min(
            parseInt(list.dataset.vpAuditSectionPage || '1', 10),
            pages
        );
        list.dataset.vpAuditSectionPage = page;
        var from = (page - 1) * size;
        var to = Math.min(from + size, standing.length);
        standing.forEach(function (section, index) {
            section.classList.toggle('d-none', index < from || index >= to);
        });
        setText(host, '[data-vp-page-from]', standing.length ? from + 1 : 0);
        setText(host, '[data-vp-page-to]', to);
        setText(host, '[data-vp-page-of]', standing.length);
        renderPager(pager, page, pages);
        host.classList.toggle('d-none', standing.length === 0);
    }

    /**
     * Narrowing changes how many pages a section has, so every section
     * goes back to page one rather than to a page that may no longer
     * exist. The section pager goes with them, for the same reason.
     *
     * @param {Element} list
     */
    function resetAuditPages(list) {
        list.querySelectorAll('[data-vp-audit-section]')
            .forEach(function (section) {
                section.dataset.vpAuditPage = 1;
            });
        list.dataset.vpAuditSectionPage = 1;
    }

    /**
     * @param {Element} button
     */
    function toggleAuditSection(button) {
        var section = button.closest('[data-vp-audit-section]');
        if (!section) {
            return;
        }
        section.dataset.vpAuditOpen = auditOpen(section) ? '0' : '1';
        applyAuditOpen(section);
    }

    /**
     * One control, two states: it opens everything while anything is
     * closed, and closes everything once nothing is.
     *
     * @param {Element} button
     */
    function toggleAuditAll(button) {
        var list = button.closest('[data-vp-list]');
        if (!list) {
            return;
        }
        var sections = list.querySelectorAll('[data-vp-audit-section]');
        var opening = false;
        sections.forEach(function (section) {
            if (!auditOpen(section)) {
                opening = true;
            }
        });
        sections.forEach(function (section) {
            section.dataset.vpAuditOpen = opening ? '1' : '0';
            applyAuditOpen(section);
        });
        var label = button.querySelector('[data-vp-audit-expand-label]');
        if (label) {
            label.textContent = opening
                ? (button.dataset.vpAuditLabelCollapse || '')
                : (button.dataset.vpAuditLabelExpand || '');
        }
    }

    /**
     * @param {Element} button
     */
    function toggleAuditDiff(button) {
        var row = button.closest('.vp-audit-row');
        if (!row) {
            return;
        }
        var diff = row.querySelector('.vp-audit-diff');
        if (!diff) {
            return;
        }
        var closed = diff.classList.toggle('d-none');
        button.setAttribute('aria-expanded', closed ? 'false' : 'true');
        var icon = button.querySelector('i');
        if (icon) {
            icon.classList.toggle('fa-chevron-down', closed);
            icon.classList.toggle('fa-chevron-up', !closed);
        }
    }

    /**
     * @param {Event} event
     * @return {boolean} Whether the click was this panel's
     */
    function onAuditClick(event) {
        var toggle = event.target.closest('[data-vp-audit-toggle]');
        if (toggle) {
            toggleAuditSection(toggle);
            return true;
        }
        var all = event.target.closest('[data-vp-audit-expand-all]');
        if (all) {
            toggleAuditAll(all);
            return true;
        }
        var diff = event.target.closest('[data-vp-audit-diff]');
        if (diff) {
            toggleAuditDiff(diff);
            return true;
        }
        var scope = event.target.closest('[data-vp-audit-scope]');
        if (scope) {
            var list = scope.closest('[data-vp-audit]');
            if (list) {
                fetchAuditScope(list, scope.dataset.vpAuditScope);
            }
            return true;
        }
        return false;
    }

    /**
     * @param {Element} root Either the whole page or a fragment
     */
    function initHistory(root) {
        var list = (root || document).querySelector('[data-vp-audit]');
        if (!list) {
            return;
        }
        var payload = list.querySelector('[data-vp-audit-data]');
        if (!payload) {
            // The log has no entries at all, so there is no span to
            // draw and no period to pick inside it.
            audit.data = null;
            return;
        }
        audit.data = JSON.parse(payload.textContent);
        if (!audit.data.chart || !audit.data.chart.days) {
            return;
        }
        audit.zoom = window.VP.zoom.make(
            audit.data.chart,
            // The period, which on this tab is the two date inputs —
            // so `look inside the selection` and the filter are the
            // same range by construction, and the reader who only
            // wanted a closer look still has the four buttons.
            function () {
                var typed = auditTypedPeriod(list);
                if (typed.from === null && typed.to === null) {
                    return null;
                }
                var whole = auditWhole();
                return {
                    from: typed.from || whole.from,
                    to: typed.to || whole.to,
                };
            }
        );
        audit.labels = null;
        var zoom = list.querySelector('[data-vp-zoom]');
        if (zoom) {
            var spec = zoom.querySelector('[data-vp-zoom-labels]');
            audit.labels = spec ? JSON.parse(spec.textContent) : null;
        }
        audit.chart = window.VP.chart.boot(
            'vp-audit-activity',
            buildAuditChart
        );
        // Both controls are rendered hidden, because without this
        // script they would frame an empty canvas and offer gestures
        // that do nothing.
        var brush = list.querySelector('[data-vp-brush]');
        if (brush) {
            brush.hidden = false;
        }
        if (zoom) {
            zoom.hidden = false;
        }
        wireAuditBrush(list);
        window.VP.zoom.wire(zoom, audit.zoom, function () {
            redrawAuditChart(list);
        });
        paintAuditZoom(list);
        // `refreshAllLists` paints and re-tallies on the same pass, so
        // the panel lands with the brush over the window it was fetched
        // for rather than over the whole chart.
        refreshList(list);
    }

    /**
     * @param {Element} root Either the whole page or a fragment
     */
    function initTimeline(root) {
        var panel = (root || document).querySelector('[data-vp-tl]');
        if (!panel) {
            return;
        }
        var payload = panel.querySelector('[data-vp-tl-data]');
        if (!payload) {
            return;
        }
        tl.data = JSON.parse(payload.textContent);
        tl.brush = null;
        tl.filter = null;
        tl.expanded = {};
        tl.showAll = false;
        tl.spine = window.VP.chart.boot('vp-tl-spine', buildTimelineSpine);
        // The brush is rendered hidden, because without this script it
        // would frame an empty canvas and offer a gesture that does
        // nothing.
        var brush = panel.querySelector('[data-vp-brush]');
        if (brush) {
            brush.hidden = false;
        }
        wireTimelineBrush(panel);
        refreshTimeline(panel);
    }

    /**
     * @param {Event} event
     * @return {void}
     */
    function onTimelineClick(event) {
        var panel = event.target.closest
            ? event.target.closest('[data-vp-tl]')
            : null;
        if (!panel || !tl.data) {
            return;
        }

        var lane = event.target.closest('[data-vp-tl-lane]');
        if (lane) {
            // Pressing the lane that is already showing lets it go,
            // which is the same gesture the type chips in the banner
            // use.
            var sources = lane.dataset.vpTlSources;
            var already = tl.filter === sources;
            panel.querySelectorAll('[data-vp-tl-lane]').forEach(
                function (button) {
                    button.setAttribute('aria-pressed', 'false');
                }
            );
            tl.filter = already ? null : sources;
            if (!already) {
                lane.setAttribute('aria-pressed', 'true');
            }
            tl.showAll = false;
            var name = panel.querySelector('[data-vp-tl-filter-name]');
            if (name) {
                name.textContent = lane.textContent.trim();
            }
            tlRefreshList(panel);
            return;
        }

        if (event.target.closest('[data-vp-tl-filter-clear]')) {
            tl.filter = null;
            tl.showAll = false;
            panel.querySelectorAll('[data-vp-tl-lane]').forEach(
                function (button) {
                    button.setAttribute('aria-pressed', 'false');
                }
            );
            tlRefreshList(panel);
            return;
        }

        if (event.target.closest('[data-vp-tl-reset]')) {
            tl.brush = null;
            tl.showAll = false;
            refreshTimeline(panel);
            return;
        }

        if (event.target.closest('[data-vp-tl-more]')) {
            tl.showAll = true;
            tlRefreshList(panel);
            return;
        }

        var expand = event.target.closest('[data-vp-tl-expand]');
        if (expand) {
            var row = expand.closest('[data-vp-tl-run]');
            if (row) {
                tl.expanded[row.dataset.vpTlRun] = true;
                tlRefreshList(panel);
            }
        }
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

            var sortHeader = event.target.closest('[data-vp-sort-col]');
            if (sortHeader) {
                toggleColumnSort(sortHeader);
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
                var pageSection = page.closest('[data-vp-audit-section]');
                var pageSections = page
                    .closest('[data-vp-audit-sectionpager]');
                if (pageSections && pageList) {
                    pageList.dataset.vpAuditSectionPage =
                        parseInt(page.dataset.vpPage, 10);
                } else if (pageSection) {
                    pageSection.dataset.vpAuditPage =
                        parseInt(page.dataset.vpPage, 10);
                } else if (pageList) {
                    listPages.set(pageList, parseInt(page.dataset.vpPage, 10));
                }
                if (pageList) {
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

            if (onAnalystClick(event)) {
                return;
            }

            if (onAuditClick(event)) {
                return;
            }

            onTimelineClick(event);

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
                    resetAuditPages(clearList);
                    refreshList(clearList);
                }
            }
        });

        // Hovering a strip marker lights the table rows it stands
        // for. Delegated, because the standing panel arrives after
        // load like every other fragment on this page.
        document.addEventListener('mouseover', function (event) {
            if (!event.target.closest) {
                return;
            }
            var mark = event.target.closest('[data-vp-a-mark]');
            if (mark) {
                highlightAnalystMark(mark, true);
            }
        });

        document.addEventListener('mouseout', function (event) {
            if (!event.target.closest) {
                return;
            }
            var leaving = event.target.closest('[data-vp-a-mark]');
            if (leaving) {
                highlightAnalystMark(leaving, false);
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
                && event.target.matches('[data-vp-page-size-pick]')) {
                changePageSize(event.target);
                return;
            }

            if (event.target.matches
                && event.target.matches('[data-vp-sight-range]')) {
                // A wider window is a different set of buckets, so a
                // brush drawn over the old ones no longer points at
                // anything the reader chose.
                sight.rangeKey = event.target.value;
                sight.brush = null;
                sight.expanded = false;
                sightToSpan(sight.rangeKey);
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
                    + ' [data-vp-filter-key], [data-vp-filter-min],'
                    + ' [data-vp-filter-from], [data-vp-filter-to],'
                    + ' [data-vp-range-from], [data-vp-range-to]'
                )) {
                var list = event.target.closest('[data-vp-list]');
                if (list) {
                    listPages.set(list, 1);
                    resetAuditPages(list);
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
                '[data-vp-filter-text], [data-vp-filter-min],'
                + ' [data-vp-filter-from], [data-vp-filter-to],'
                + ' [data-vp-range-from], [data-vp-range-to]'
            )) {
                var typedList = event.target.closest('[data-vp-list]');
                if (typedList) {
                    listPages.set(typedList, 1);
                    resetAuditPages(typedList);
                    refreshList(typedList);
                }
            }
        });

        // Panels arrive after load, one fetch each, so the state the page
        // is holding has to be applied to each one as it lands.
        document.addEventListener('misp:container-loaded', function (event) {
            markDisabled(event.target);
            refreshOccurrences();
            initTimeBrushes(event.target);
            refreshAllLists(event.target);
            initSightings(event.target);
            initEnrichment(event.target);
            initAnalyst(event.target);
            initTimeline(event.target);
            initHistory(event.target);
        });

        refreshOccurrences();
        // Before the first refresh, so the windows paint with the rest.
        initTimeBrushes(document);
        refreshAllLists(document);
        initSightings(document);
        initEnrichment(document);
        initAnalyst(document);
        initTimeline(document);
        initHistory(document);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
}());
