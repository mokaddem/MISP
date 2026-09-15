/*
 * `/values/index` — the prompt's behaviour.
 *
 * **It waits for the DOM.** `assetLoader` puts this script tag above
 * the markup, so a binding made at parse time binds to nothing and the
 * page still draws perfectly — which is how a dead prompt ships
 * looking finished.
 *
 * Phase 3 is the resolver, so there are two behaviours: Enter submits,
 * and an empty box cannot be submitted. The live counter, the cap and
 * the worklist's own transport arrive with phase 4; this file grows
 * with them rather than being replaced.
 */
(function () {
    'use strict';

    function ready(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn);
        } else {
            fn();
        }
    }

    ready(function () {
        var form = document.getElementById('vi-prompt');
        if (!form) {
            return;
        }
        var box = form.querySelector('[data-vi-box]');
        var go = form.querySelector('[data-vi-go]');
        if (!box || !go) {
            return;
        }

        function pressable() {
            go.disabled = box.value.trim() === '';
        }

        /*
         * Enter submits and Shift+Enter makes a line, which is the
         * shape every paste box the reader already uses has. It is a
         * textarea rather than an input because phase 4's paste is
         * many lines, and a one-line field that grows would change
         * under the reader's cursor between the two phases.
         */
        box.addEventListener('keydown', function (event) {
            if (event.key !== 'Enter' || event.shiftKey) {
                return;
            }
            if (event.ctrlKey || event.metaKey || event.altKey) {
                return;
            }
            if (box.value.trim() === '') {
                event.preventDefault();
                return;
            }
            event.preventDefault();
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
            } else {
                form.submit();
            }
        });

        box.addEventListener('input', pressable);
        pressable();

        /*
         * The caret goes to the end rather than to the start: the box
         * comes back filled after an answer, and a reader correcting
         * what they pasted is at the end of it.
         */
        if (box.value !== '') {
            box.setSelectionRange(box.value.length, box.value.length);
        }
    });
}());
