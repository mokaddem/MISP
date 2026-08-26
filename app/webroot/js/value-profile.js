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
     * pager and its range, and the outer one leaves them alone — the
     * Relationships tab's object-siblings section pages separately from
     * the ranked table under it. Narrowing controls are not nested in
     * this pass: the inner section has none, and one that grew them
     * would need the facet lookups scoped the same way.
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
     * @param {Element} list
     * @param {string} selector
     * @param {string} fill Clock digits for a bound given as a date
     * @return {number|null}
     */
    function periodBound(list, selector, fill) {
        var input = list.querySelector(selector);
        if (!input || input.value === '') {
            return null;
        }
        var digits = input.value.replace(/\D/g, '');
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
        var select = ownNode(list, '[data-vp-sort]');
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
        list.querySelectorAll('[data-vp-filter-from], [data-vp-filter-to]')
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
        var count = tl.data.bins.length;
        var bounds = tlBins();
        var left = (100 * bounds.from) / count;
        var right = (100 * (count - 1 - bounds.to)) / count;

        var maskLeft = panel.querySelector('[data-vp-tl-mask-left]');
        var maskRight = panel.querySelector('[data-vp-tl-mask-right]');
        var handle = panel.querySelector('[data-vp-tl-handle]');
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
     * Drag on the spine. A drag that does not move is a click, and a
     * click clears the brush — the same thing `Reset window` offers, for
     * a reader who never found it.
     *
     * @param {Element} panel
     */
    function wireTimelineBrush(panel) {
        var strip = panel.querySelector('[data-vp-tl-brush]');
        if (!strip) {
            return;
        }
        var anchor = null;

        function binAt(event) {
            var box = strip.getBoundingClientRect();
            var count = tl.data.bins.length;
            var fraction = (event.clientX - box.left) / box.width;
            var index = Math.floor(fraction * count);
            return Math.max(0, Math.min(count - 1, index));
        }

        strip.addEventListener('pointerdown', function (event) {
            anchor = binAt(event);
            strip.setPointerCapture(event.pointerId);
            event.preventDefault();
        });

        strip.addEventListener('pointermove', function (event) {
            if (anchor === null) {
                return;
            }
            var to = binAt(event);
            tl.brush = {
                from: Math.min(anchor, to),
                to: Math.max(anchor, to),
            };
            tl.showAll = false;
            refreshTimeline(panel);
        });

        strip.addEventListener('pointerup', function (event) {
            if (anchor !== null && binAt(event) === anchor) {
                tl.brush = null;
                tl.showAll = false;
                refreshTimeline(panel);
            }
            anchor = null;
        });

        strip.addEventListener('pointercancel', function () {
            anchor = null;
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
        // The months, the rendered window and the log's span, as the
        // panel was sent them.
        data: null,
        chart: null,
    };

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
        if (!audit.data || !audit.data.months.length) {
            return null;
        }
        var months = audit.data.months;
        var typed = auditTypedPeriod(list);
        var rendered = audit.data.window;
        return {
            from: typed.from
                || (rendered ? rendered.from : months[0].from),
            to: typed.to
                || (rendered ? rendered.to : months[months.length - 1].to),
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
        if (!period) {
            return null;
        }
        var from = null;
        var to = null;
        audit.data.months.forEach(function (month, index) {
            if (month.to >= period.from && month.from <= period.to) {
                if (from === null) {
                    from = index;
                }
                to = index;
            }
        });
        // A period entirely off the end of the chart covers no bar. The
        // brush collapses onto the nearest edge rather than vanishing:
        // the reader has to be able to see where they are.
        if (from === null) {
            var last = audit.data.months.length - 1;
            var edge = period.to < audit.data.months[0].from ? 0 : last;
            return { from: edge, to: edge };
        }
        return { from: from, to: to };
    }

    /**
     * @param {Element} list
     */
    function paintAuditBrush(list) {
        var bounds = auditBins(list);
        if (!bounds) {
            return;
        }
        var count = audit.data.months.length;
        var left = (100 * bounds.from) / count;
        var right = (100 * (count - 1 - bounds.to)) / count;

        var maskLeft = list.querySelector('[data-vp-audit-mask-left]');
        var maskRight = list.querySelector('[data-vp-audit-mask-right]');
        var handle = list.querySelector('[data-vp-audit-handle]');
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
    }

    /**
     * The monthly bars. No axes, for the reason the Sightings navigator
     * has none: the brush over it is positioned as a plain fraction of
     * the strip's width, and an axis would put the bars somewhere other
     * than where the drag maths says they are.
     *
     * @param {Element} canvas
     * @return {Object}
     */
    function buildAuditMonths(canvas) {
        var months = audit.data.months;
        var config = window.VP.chart.resolve({
            type: 'bar',
            data: {
                labels: months.map(function (month) {
                    return month.label;
                }),
                datasets: [{
                    data: months.map(function (month) {
                        return month.total;
                    }),
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
                                return months[items[0].dataIndex].title;
                            },
                        },
                    },
                },
            },
        }, canvas);
        return new Chart(canvas, config);
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
     * @param {number} from Month index
     * @param {number} to Month index
     */
    function writeAuditPeriod(list, from, to) {
        var months = audit.data.months;
        var pairs = [
            ['[data-vp-filter-from]', months[from].from + 'T00:00'],
            ['[data-vp-filter-to]', months[to].to + 'T23:59'],
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
        var months = audit.data.months;
        var from = typed.from || months[0].from;
        var to = typed.to || months[months.length - 1].to;
        fetchAuditScope(list, from + '/' + to);
    }

    /**
     * Drag on the chart. A drag that does not move is a click, and a
     * click clears the period — the same gesture the Sightings and
     * Timeline brushes offer, and the one this tab's `Clear all` offers
     * to a reader who found the button instead.
     *
     * Where this differs from those two, deliberately: they read the
     * click off *which bucket the pointer came up on*, so releasing on
     * the bucket you pressed is always a clear and a one-bucket range
     * cannot be selected at all. On a monthly chart that would mean the
     * finest period a reader could brush is two months, which is not a
     * control — so a click here is a pointer that did not travel, in
     * pixels. Phase 20 folds all three into one primitive and this is
     * the rule it should carry.
     *
     * @param {Element} list
     */
    function wireAuditBrush(list) {
        var strip = list.querySelector('[data-vp-audit-brush]');
        if (!strip) {
            return;
        }
        var anchor = null;
        var origin = 0;
        var moved = false;

        function binAt(event) {
            var box = strip.getBoundingClientRect();
            var count = audit.data.months.length;
            var fraction = (event.clientX - box.left) / box.width;
            var index = Math.floor(fraction * count);
            return Math.max(0, Math.min(count - 1, index));
        }

        strip.addEventListener('pointerdown', function (event) {
            anchor = binAt(event);
            origin = event.clientX;
            moved = false;
            strip.setPointerCapture(event.pointerId);
            event.preventDefault();
        });

        strip.addEventListener('pointermove', function (event) {
            if (anchor === null) {
                return;
            }
            if (Math.abs(event.clientX - origin) > 3) {
                moved = true;
            }
            var to = binAt(event);
            writeAuditPeriod(
                list,
                Math.min(anchor, to),
                Math.max(anchor, to)
            );
        });

        strip.addEventListener('pointerup', function (event) {
            if (anchor === null) {
                return;
            }
            anchor = null;
            if (!moved) {
                clearAuditPeriod(list);
            } else if (!auditWithinWindow(list)) {
                // On release and not during the drag: a fetch per
                // pointermove would be a request every few pixels.
                fetchAuditPeriod(list);
            }
        });

        strip.addEventListener('pointercancel', function () {
            anchor = null;
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
        var months = audit.data ? audit.data.months : [];
        for (var i = 0; i < months.length; i++) {
            if (months[i].key === day.slice(0, 7)) {
                return parseInt(day.slice(8, 10), 10) + ' '
                    + months[i].title;
            }
        }
        return day;
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
        if (!audit.data.months.length) {
            return;
        }
        audit.chart = window.VP.chart.boot('vp-audit-months', buildAuditMonths);
        // Rendered hidden, because without this script it would frame an
        // empty canvas and offer a gesture that does nothing.
        var brush = list.querySelector('[data-vp-audit-brush]');
        if (brush) {
            brush.hidden = false;
        }
        wireAuditBrush(list);
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
        var brush = panel.querySelector('[data-vp-tl-brush]');
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
                    + ' [data-vp-filter-key], [data-vp-filter-min],'
                    + ' [data-vp-filter-from], [data-vp-filter-to]'
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
                + ' [data-vp-filter-from], [data-vp-filter-to]'
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
            refreshAllLists(event.target);
            initSightings(event.target);
            initEnrichment(event.target);
            initAnalyst(event.target);
            initTimeline(event.target);
            initHistory(event.target);
        });

        refreshOccurrences();
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
