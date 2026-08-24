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
            }
        });

        document.addEventListener('change', function (event) {
            if (event.target.id === 'vp-occ-deleted-toggle') {
                refreshOccurrences();
            }
        });

        // Panels arrive after load, one fetch each, so the state the page
        // is holding has to be applied to each one as it lands.
        document.addEventListener('misp:container-loaded', function (event) {
            markDisabled(event.target);
            refreshOccurrences();
        });

        refreshOccurrences();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
}());
