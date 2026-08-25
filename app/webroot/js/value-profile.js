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
        var revealed = revealedTokens(list);
        var filtered = [];

        listRows(list).forEach(function (row) {
            var hidden = row.dataset.vpHidden;
            var excluded = !!hidden && revealed.indexOf(hidden) === -1;
            var keep = !excluded && rowMatches(row, active);
            if (keep) {
                filtered.push(row);
            } else {
                row.classList.add('d-none');
            }
        });

        var activeCount = Object.keys(active).reduce(function (sum, key) {
            return sum + active[key].length;
        }, 0);

        paginate(list, filtered);
        updateListNotes(list, filtered.length, activeCount);
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
            .forEach(refreshList);
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

            var more = event.target.closest('[data-vp-facet-more]');
            if (more) {
                expandFacetGroup(more);
                return;
            }

            var clearAll = event.target.closest('[data-vp-facet-clear]');
            if (clearAll && !clearAll.disabled) {
                var clearList = clearAll.closest('[data-vp-list]');
                if (clearList) {
                    clearList
                        .querySelectorAll('input[data-vp-facet-key]:checked')
                        .forEach(function (box) {
                            box.checked = false;
                        });
                    listPages.set(clearList, 1);
                    refreshList(clearList);
                }
            }
        });

        document.addEventListener('change', function (event) {
            if (event.target.id === 'vp-occ-deleted-toggle') {
                refreshOccurrences();
            }

            // Narrowing a list changes how many pages it has, so any
            // facet or reveal switch sends the reader back to page one
            // rather than to a page that may no longer exist.
            if (event.target.matches
                && event.target.matches('[data-vp-facet-key], [data-vp-reveal]')) {
                var list = event.target.closest('[data-vp-list]');
                if (list) {
                    listPages.set(list, 1);
                    refreshList(list);
                }
            }
        });

        document.addEventListener('input', function (event) {
            if (event.target.matches
                && event.target.matches('[data-vp-facet-search]')) {
                filterFacetGroup(event.target);
            }
        });

        // Panels arrive after load, one fetch each, so the state the page
        // is holding has to be applied to each one as it lands.
        document.addEventListener('misp:container-loaded', function (event) {
            markDisabled(event.target);
            refreshOccurrences();
            refreshAllLists(event.target);
        });

        refreshOccurrences();
        refreshAllLists(document);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
}());
