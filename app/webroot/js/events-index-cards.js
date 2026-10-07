/* Overmind events index — card view: the +N on each one-line context row,
   and the selection state of the cards. */
(function () {
    'use strict';

    if (window.eventIndexCards) {
        window.eventIndexCards.scan();
        return;
    }

    var GAP = 4, RESERVE = 34;

    function fit(row) {
        var more = row.querySelector('.dk-more');
        if (!more) {
            return;
        }
        var fold = more.closest('.dk-fold') || more;
        var chips = Array.prototype.slice.call(row.querySelectorAll('.dk-chip'));
        chips.forEach(function (c) { c.hidden = false; });
        fold.hidden = true;
        var width = row.clientWidth;
        if (!width) {
            return;
        }
        var used = 0, i = 0;
        for (; i < chips.length; i++) {
            var last = i === chips.length - 1;
            if (used + chips[i].offsetWidth + (last ? 0 : RESERVE) > width) {
                break;
            }
            used += chips[i].offsetWidth + GAP;
        }
        if (i < chips.length) {
            var rest = chips.slice(i);
            rest.forEach(function (c) { c.hidden = true; });
            fold.hidden = false;
            more.textContent = '+' + rest.length;
        }
    }

    function fitAll(scope) {
        scope.querySelectorAll('.dk-ctx').forEach(fit);
    }

    function syncSelection(root) {
        var any = false;
        root.querySelectorAll('.dk-card').forEach(function (card) {
            var box = card.querySelector('.item-checkbox');
            var on = !!(box && box.checked);
            card.classList.toggle('is-selected', on);
            any = any || on;
        });
        root.querySelectorAll('.idx-card-grid').forEach(function (grid) {
            grid.classList.toggle('dk-selecting', any);
        });
    }

    // The card view starts hidden behind the table toggle, so a grid is
    // measured whenever it gets a size, not only on load.
    var pending = new Set();
    var frame = 0;
    var resizes = new ResizeObserver(function (entries) {
        entries.forEach(function (entry) {
            if (entry.contentRect.width) {
                pending.add(entry.target);
            }
        });
        if (!frame) {
            frame = requestAnimationFrame(function () {
                frame = 0;
                pending.forEach(fitAll);
                pending.clear();
            });
        }
    });

    function observeGrids(root) {
        root.querySelectorAll('.idx-card-grid').forEach(function (grid) {
            if (!grid.dataset.dkObserved) {
                grid.dataset.dkObserved = '1';
                resizes.observe(grid);
            }
        });
    }

    function bind(root) {
        if (root.dataset.dkBound) {
            return;
        }
        root.dataset.dkBound = '1';
        observeGrids(root);
        root.addEventListener('change', function (e) {
            if (e.target.classList && e.target.classList.contains('item-checkbox')) {
                syncSelection(root);
            }
        });
        // The filter bar and the pager swap the children of #index-results.
        var results = root.querySelector('#index-results');
        if (results) {
            new MutationObserver(function () {
                observeGrids(root);
                fitAll(root);
                syncSelection(root);
            }).observe(results, { childList: true });
        }
    }

    function scan() {
        document.querySelectorAll('.dk-index').forEach(bind);
    }

    window.eventIndexCards = { scan: scan, fit: fitAll };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', scan);
    } else {
        scan();
    }
    if (document.fonts && document.fonts.ready) {
        document.fonts.ready.then(function () {
            document.querySelectorAll('.dk-index').forEach(fitAll);
        });
    }
})();
